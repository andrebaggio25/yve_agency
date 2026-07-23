# YVE Agency — Análise Técnica Completa do Sistema

> Documento de engenharia sênior · Auditoria e documentação para preparação de comercialização
> Data: 2026-07-06 · Revisado: 2026-07-14 (ciclo 2) · 2026-07-23 (ciclo 3) · Stack: PHP 8.3 puro · PostgreSQL (Supabase) · Tailwind + Alpine.js
> Escopo: arquitetura, módulos, integrações, segurança, banco, frontend, testes e achados.

Este documento é a **fotografia técnica** do sistema. Ele descreve o que existe, como funciona e onde estão os riscos. A análise de produto (SWOT + notas por módulo) está em [ANALISE_PRODUTO.md](ANALISE_PRODUTO.md); o roteiro vigente em [PLANO_MESTRE.md](PLANO_MESTRE.md). O guia funcional de uso está em [GUIA_SISTEMA.md](GUIA_SISTEMA.md).

> **Nota do ciclo 2 (2026-07-14):** os achados 🔴/🟠 do §6 foram **corrigidos** nos Marcos 0–2 (ver histórico em [historico/PLANO_MESTRE_2026-07-06.md](historico/PLANO_MESTRE_2026-07-06.md)); permanecem abertos os itens 🟡 de polish (agora INFRA-01/02/03 e SEC-10 no plano vigente). O texto original foi mantido como registro do estado auditado.
>
> **Nota do ciclo 3 (2026-07-23):** fotografia atualizada nos números, não no texto: **188 testes / 417 asserts** (banco PG real via Docker + smoke de Chromium), PHPStan nível 6 = 0, audit limpo (dompdf 3.1.6 / guzzle 7.15.1). Mudanças estruturais desde o ciclo 2: upload direto browser→Drive em produção (UP-01), fila única de jobs com timeline de entregas (INFRA-01/OBS-02), `/api/health` + heartbeat + alerta (OBS-01), assets self-hosted purgados (FE-01), JS extraído das 3 views gigantes (FE-02), CSRF no portal (SEC-08), testes de arquitetura travando invariantes. Catálogo de automações agora documentado em [AUTOMACOES.md](AUTOMACOES.md). Estado por módulo: [ANALISE_PRODUTO.md](ANALISE_PRODUTO.md) (ciclo 3).

---

## 1. Visão geral

O YVE Agency é uma **plataforma multi-tenant (SaaS) de gestão de agência de marketing**. Um único código-base serve várias agências (tenants), cada uma isolada por `agency_id`. Há três planos de acesso:

| Plano | URL base | Quem | Isolamento |
|-------|----------|------|------------|
| **Platform Admin** | `/admin/*` | Operador da plataforma (dono do SaaS) | Vê todos os tenants |
| **Tenant (Agência)** | `/dashboard`, `/clientes`, … | Equipe da agência | Escopo por `agency_id` |
| **Portal do Cliente** | `/portal/{token}` | Cliente final da agência | Sem login, capability-token na URL |

O sistema cobre o ciclo operacional de uma agência: cadastro de clientes → planejamento de conteúdo → aprovação pelo cliente → comunicação (WhatsApp/e-mail) → tráfego pago (Meta) → orgânico (Instagram) → IA de insights → financeiro (contratos/faturas/pagamentos) → tarefas → automações.

**Maturidade atual:** Fases 1 e 2 do [PLANO_FASES.md](PLANO_FASES.md) completas (core, RBAC, clientes, conteúdo, aprovação, portal). Fases 3–12 implementadas em graus variados — a maioria dos módulos existe e funciona, mas com dívidas de robustez que este documento detalha.

---

## 2. Arquitetura

### 2.1 Micro-framework próprio (`app/Core`)

O projeto **não usa Laravel/Symfony**. Tem um micro-framework MVC próprio, bem estruturado:

```
Requisição HTTP
  └─ public/index.php (front controller)
       ├─ carrega autoload, .env (vlucas/phpdotenv), sessão segura
       ├─ Container (PSR-11 mínimo, auto-wiring por reflection)
       ├─ Router::dispatch(Request)
       │    └─ casa rota por regex → Pipeline de middlewares → Controller
       ├─ Controller (orquestra, sem SQL/regra)
       │    └─ Service (regra de negócio)
       │         └─ Repository (SQL isolado + escopo agency_id)
       │              └─ PDO (prepared statements)
       └─ Response (view | json | redirect) → send()
```

**Componentes do Core:**

| Classe | Responsabilidade | Observação |
|--------|------------------|------------|
| `Router` | Registro de rotas + match por regex + grupos de middleware | Suporta `get/post/put/patch/delete/any` e `group()` |
| `Route` | DTO de rota (método, pattern, handler, middlewares) | — |
| `Pipeline` | Execução encadeada de middlewares (padrão "onion") | Resolve middleware pelo Container |
| `Container` | DI PSR-11 com auto-wiring por `ReflectionClass` | Resolve dependências de construtor automaticamente |
| `Request` | Wrapper de `$_GET/$_POST/$_FILES/$_SERVER` | Faz method-override via `_method`; parseia JSON body |
| `Response` | Factory `view/json/redirect/text`; injeta headers de segurança no `send()` | — |
| `View` | Renderização de templates PHP com layout + sections + partials | Sem engine externa |
| `Database` | Factory PDO singleton (pgsql/mysql/sqlite) | `ATTR_PERSISTENT = true` (ver §7) |
| `Repository` | Base com escopo `agency_id` automático + helpers de query/paginação | Todas as queries via prepared statements |
| `Crypto` | Cifra simétrica libsodium (XSalsa20-Poly1305) para segredos | Chave derivada de `APP_KEY` via SHA-256 |
| `Secret` | Camada null-safe sobre Crypto, tolerante a valores legados em texto puro | — |
| `Lang` | i18n (pt/en/es) por arquivos em `resources/lang` | — |
| `Logger` | Factory Monolog por canal | — |

**Qualidade do Core:** é código limpo, com `declare(strict_types=1)`, type hints, PSR-12. A separação Controller→Service→Repository é respeitada de forma consistente. É a maior força do projeto.

### 2.2 Camadas da aplicação

- **31 Controllers** (+ 5 no namespace `Admin`) — cada método público é uma rota. Guardas de autorização chamadas explicitamente (`Auth::requirePermission(...)`) no início de cada método.
- **26 Services** — regra de negócio. Recebem repositórios por injeção.
- **28 Repositories** — SQL isolado, herdam `Core\Repository` (exceto `PlatformSettingsRepository`, que é global sem `agency_id`).
- **7 Middlewares** — Auth, Permission, ClientAccess, Csrf, RateLimit, PlatformAdmin, Portal.
- **11 Automations** + **AutomationHandler** — regras agendadas (lembretes de fatura, digest, SLA de tarefa, etc.).
- **2 Jobs** — `RunAutomationRuleJob`, `ClickUpPushJob`.

### 2.3 Modelo de autorização (RBAC + escopo de cliente)

No login, `AuthService::attempt()` carrega em sessão:
- `$_SESSION['user']` — dados do usuário (inclui `agency_id`, `is_platform_admin`)
- `$_SESSION['permissions']` — array achatado de slugs (`content.view`, `clients.edit`, …) vindo de `user_roles → role_permissions → permissions`
- `$_SESSION['client_ids']` — IDs de clientes acessíveis (via `client_user_access`)

`App\Support\Auth` é o ponto único de checagem: `can()`, `canAccessClient()`, `requirePermission()`, `requirePlatformAdmin()`. **83 permissões** canônicas em `config/permissions.php`.

O isolamento multi-tenant tem **duas camadas**:
1. `Core\Repository::agencyScope()` injeta `WHERE agency_id = :__agency_id` automaticamente nos finders genéricos.
2. Muitos repositórios recebem `$agencyId` explicitamente nos métodos (`findByIdAndAgency`), reforçando o escopo.

---

## 3. Módulos (mapa funcional)

| Módulo | Rotas (pt) | Controller | Estado |
|--------|-----------|------------|--------|
| **Dashboard** | `/dashboard` | DashboardController | OK |
| **Clientes** | `/clientes` | ClientController + ClientService | OK, robusto |
| **Acesso por cliente** | `/clientes/{id}/acesso` | ClientController | OK |
| **Planos de conteúdo** | `/conteudo` | ContentPlanController + Service | OK |
| **Aprovações (interno)** | `/aprovacoes` | ApprovalController | OK |
| **Portal do cliente** | `/portal/{token}` | PortalController (696 linhas) | Funcional, mas grande (ver §8) |
| **Envio de conteúdos (Drive)** | `/portal/{token}/drive/*`, `/clientes/{id}/conteudos` | Portal + ClientFiles + GoogleDriveApiService | OK, bem-feito (proxy privado) |
| **Financeiro — Contratos** | `/contratos` | ContractController | OK |
| **Financeiro — Faturas** | `/faturas` | InvoiceController + Service | OK |
| **Financeiro — Pagamentos** | `/pagamentos` | PaymentController | OK |
| **Relatórios financeiros** | `/financeiro/relatorios` | FinancialReportController | OK |
| **Tráfego pago (Meta)** | `/trafego` | TrafficController + AdsAccountController + MetaAdsService | OK, OAuth + sync |
| **Ações em campanha** | `/trafego/acoes` | AdsActionController | OK, fluxo de aprovação |
| **Orgânico (Instagram)** | `/organico` | OrganicController + MetaOrganicService | OK |
| **IA & Insights** | `/ia` | AiInsightController + AiInsightService | OK (OpenAI/Anthropic com fallback) |
| **Tarefas (Kanban)** | `/tarefas` | TaskController | OK |
| **Automações** | `/automations` | AutomationController + AutomationService | OK |
| **WhatsApp** | `/configuracoes/whatsapp` | WhatsAppController + EvolutionApiService | OK |
| **Integração ClickUp** | `/integrations/clickup` | ClickUpController + ClickUpService | OK, bidirecional |
| **Integração Google Drive** | `/integrations/google-drive` | GoogleDriveController | OK, OAuth |
| **Relatório executivo** | `/relatorio-executivo` | ReportController | OK |
| **Configurações da agência** | `/configuracoes` | SettingsController | OK |
| **Assinatura (tenant)** | `/assinatura` | BillingController | OK |
| **Notificações** | `/notificacoes` | SettingsController | OK (in-app + bell) |
| **Usuários / Perfis** | `/usuarios`, `/usuarios/perfis` | UserController + RoleController | OK |
| **Admin — Tenants** | `/admin/tenants` | Admin\TenantController | OK |
| **Admin — Planos/Assinaturas** | `/admin/planos`, `/admin/assinaturas` | Admin\SubscriptionPlanController | OK |
| **Admin — Config global** | `/admin/configuracoes` | Admin\GlobalSettingsController | OK |
| **Admin — Usuários globais** | `/admin/usuarios` | Admin\PlatformUserController | OK |
| **Cron/Queue** | `/queue/*` | QueueController | OK (token-protected) |
| **Webhooks** | `/webhook/evolution/{token}`, `/webhook/clickup/{token}` | Webhook + ClickUpWebhook | OK (HMAC no ClickUp) |

**Observação de rotas:** cada módulo tem rotas duplicadas em **pt e en** (ex.: `/clientes` e `/clients`). Isso ~dobra a tabela de rotas (~200 rotas). Ver §8 (dívida de manutenção).

---

## 4. Integrações externas

| Integração | Serviço | Autenticação | Segredos | Estado |
|-----------|---------|--------------|----------|--------|
| **Meta Marketing API** | MetaAdsService | OAuth (app id/secret globais) + token por conta | Token cifrado (`AdAccountRepository` usa `Secret`) | OK; trata expiração |
| **Instagram Graph** | MetaOrganicService | Token por conta | Cifrado | OK |
| **Google Drive** | GoogleDriveApiService (481 linhas) | OAuth por agência (refresh_token) | `access_token`/`refresh_token` cifrados | **Bem-feito** — escopo `drive.file`, upload via relay server-side, proxy de preview mantendo arquivo privado, lixeira + restore |
| **Evolution API (WhatsApp)** | EvolutionApiService | API key global + instância por agência | Key em `platform_settings` | OK; webhook valida token + instance name |
| **ClickUp** | ClickUpService | Token por agência | Cifrado (`ClickUpIntegrationRepository`) | OK; sync bidirecional, webhook com HMAC-SHA256 |
| **OpenAI / Anthropic** | AiInsightService | API key global | Em `platform_settings` | OK; fallback entre provedores |
| **SMTP (PHPMailer)** | EmailService | Credenciais globais | `mail_password` em `platform_settings` | OK; templates i18n |

**Padrão de credenciais:** tokens de integração **por agência** são cifrados em repouso com libsodium (`Secret::encrypt`). Credenciais **globais** (SMTP, Meta app secret, IA keys, Evolution key) ficam em `platform_settings` **em texto puro** — ver achado SEC-05 em §6.

---

## 5. Banco de dados

**23 migrations** (Phinx + SQL cru). PostgreSQL com `BIGSERIAL`, `TIMESTAMPTZ`, `JSONB`, `gen_random_uuid()`.

**Pontos fortes:**
- **Dinheiro como `DECIMAL`** (nunca float) — correto. Padrão multi-moeda (`amount`, `currency_code`, `exchange_rate`, `base_currency_amount`) presente no financeiro.
- **Foreign keys** com `ON DELETE` definido (28 CASCADE, 9 RESTRICT) nas migrations do núcleo/financeiro/tráfego/conteúdo.
- **Índices** em FKs e colunas de filtro (`agency_id`, `status`, `next_run_at`, `(status, next_try_at)`).
- **Fila de jobs** (`jobs`) com reserva concorrente correta: `FOR UPDATE SKIP LOCKED`, retry com backoff exponencial, `max_attempts`.
- Constraints de unicidade e dedupe (`automation_log.dedupe_key`) para idempotência.

**Fragilidades (detalhe em §6):**
- **Dois sistemas de fila paralelos:** `notification_jobs` (Phinx, migration de notificações) e `jobs` (genérica). Fonte de confusão.
- **Migrations recentes em SQL cru** (`drive_files`, `drive_folders`, `google_drive_integrations`, `clickup`) criam `agency_id`/`client_id` **sem FK** para `agencies`/`clients`. Perde integridade referencial nessas tabelas.
- `Repository::insert()` usa `lastInsertId()` **sem nome de sequência** — no PostgreSQL isso é frágil; parte do código já usa `RETURNING id` (inconsistência).

---

## 6. Achados de segurança e correção (priorizados)

Régua: **blocking** = corrigir antes de comercializar · **important** = risco real · **polish** = robustez.
IDs referenciados no [PLANO_MESTRE.md](PLANO_MESTRE.md).

### 🔴 Blocking

**SEC-01 · `.env.production` com `APP_ENV=development`**
Em produção, `public/index.php` liga `error_reporting(E_ALL)` + `display_errors=1` quando `APP_ENV=development`. Com o arquivo de produção nesse modo, **stack traces, mensagens de SQL e caminhos internos vazam para o usuário final** em qualquer erro 500 — inclusive detalhes do banco Supabase. Também `Router::handleError()` inclui a mensagem/trace da exceção na resposta em modo dev.
*Correção:* `APP_ENV=production` em `.env.production`; garantir `display_errors=0` e `log_errors=1` no PHP de produção.

**SEC-02 · Rate limit de login burlável por header**
`Request::ip()` confia **incondicionalmente** em `HTTP_X_FORWARDED_FOR` (`app/Core/Request.php:143`). `RateLimitMiddleware` chaveia o balde por `md5(ip . path)`. Um atacante que varie o header `X-Forwarded-For` a cada requisição recebe um balde novo toda vez → **proteção contra brute-force de senha anulada** quando o app está exposto direto (cenário Hostinger/PHP-FPM sem proxy confiável).
*Correção:* usar `REMOTE_ADDR` como fonte de verdade; só honrar `X-Forwarded-For` se a origem for um proxy confiável configurado (`TRUSTED_PROXIES`). Reduzir `maxAttempts` de login para ~5/min.

**DEP-01 · CVEs em guzzlehttp/guzzle e guzzlehttp/psr7**
`composer audit` reporta 3 advisories médios: cookie domain match (CVE-2026-55767), downgrade silencioso de proxy HTTPS→cleartext (CVE-2026-55568) e CRLF injection na serialização de start-line (CVE-2026-55766). Guzzle é usado em todas as integrações (Meta, Drive, ClickUp).
*Correção:* `composer update guzzlehttp/guzzle guzzlehttp/psr7` (alvo ≥7.12.1 / ≥2.12.1).

### 🟠 Important

**SEC-03 · Sem Content-Security-Policy e sem HSTS**
`Response::send()` envia `X-Frame-Options`, `X-Content-Type-Options`, `Referrer-Policy` — mas **não** `Content-Security-Policy` nem `Strict-Transport-Security`. O app carrega scripts de 4 CDNs (Tailwind, Alpine, Chart.js, marked). Sem CSP, qualquer brecha de XSS tem execução total; sem HSTS, há janela de downgrade para HTTP.
*Correção:* CSP com allowlist dos CDNs (ou self-host, ver PERF-01); HSTS em produção sob HTTPS.

**SEC-04 · DOM XSS potencial em IA + `marked` sem pin**
`resources/views/ia/show.php:80` faz `innerHTML = marked.parse(raw)` com o conteúdo do insight. `marked` não sanitiza por padrão e é carregado de CDN **sem versão fixada** (`marked/marked.min.js`). Se o texto da IA incorporar dados controlados pelo cliente (nome, legenda, comentário do portal), há vetor de XSS armazenado. Risco médio hoje (conteúdo é da IA), mas o padrão é inseguro.
*Correção:* sanitizar com DOMPurify após `marked.parse`, ou renderizar como texto; fixar versão do CDN com SRI.

**SEC-05 · Credenciais globais em texto puro em `platform_settings`**
`mail_password`, `meta_app_secret`, `openai_api_key`, `anthropic_api_key`, `evolution_api_key` são gravadas sem cifra (`GlobalSettingsController::save` → `PlatformSettingsRepository::set`). Tokens **por agência** são cifrados, mas os **globais** não. Um dump do banco expõe todas as chaves de API da plataforma.
*Correção:* aplicar `Secret::encrypt/decrypt` também nas chaves sensíveis de `platform_settings`.

**SEC-06 · CSRF ausente em endpoints que mudam estado**
Inconsistência: `planApprove` tem `CsrfMiddleware`, mas `itemFeedback`, os endpoints `/portal/{token}/drive/*` (upload/delete/restore) e a API `/api/comentarios/*` (POST) **não**. Mitigado parcialmente por `SameSite=Lax` (bloqueia POST cross-site top-level) e pelo token do portal na URL, mas é uma superfície inconsistente.
*Correção:* aplicar CSRF (ou double-submit token) uniformemente em toda ação que muda estado; para APIs JSON, exigir header `X-CSRF-Token`.

**BUG-01 · Notificações in-app sem link de destino**
`NotificationService::createInApp()` (linha 89) usa `compact('agency_id','user_id','type','title','body','action_url')`, mas as variáveis em escopo são camelCase (`$actionUrl`, `$agencyId`, …). Resultado: `action_url` **nunca é salvo** — toda notificação in-app aponta para `#`. Confirmado pelo PHPStan (`undefined variable $action_url`).
*Correção:* montar o array com as chaves corretas explicitamente.

**SEC-07 · Validação de posse de entidade ausente (IDOR leve)**
`InternalCommentController::store` e `InternalCommentService::add` gravam comentário com o `agency_id` da sessão e o `entity_id` da URL **sem verificar** que a entidade (tarefa/plano) pertence à agência. Não vaza dados entre tenants (a leitura escopa por `agency_id`), mas cria comentários órfãos e é um padrão a corrigir.
*Correção:* validar posse do `entity_id` na agência antes de gravar.

**SCHEMA-01 · FKs faltando nas tabelas de Drive/ClickUp**
`drive_files`, `drive_folders`, `google_drive_integrations`, `clickup_*` têm `agency_id`/`client_id` sem FK para as tabelas-mãe. Exclusão de cliente/agência deixa registros órfãos no Drive/ClickUp.
*Correção:* adicionar FKs com `ON DELETE CASCADE` numa migration nova.

### 🟡 Polish

- **PDO persistente + Supabase pooler:** `ATTR_PERSISTENT = true` com pgBouncer/pooler pode causar "too many connections" e vazamento de estado de sessão entre requisições. Reavaliar em produção.
- **`Repository::insert()` com `lastInsertId()` sem sequência:** frágil no PG; padronizar em `RETURNING id`.
- **RateLimit com race condition:** leitura-modificação-escrita em arquivo sem lock; sob concorrência subconta tentativas.
- **PHPStan nível 6:** 22 erros (a maioria `new static()` "unsafe" e tipos em `View`/`Container`). Não são bugs, mas sujam o sinal — resolver para manter o gate limpo.
- **Duas filas paralelas** (`jobs` + `notification_jobs`): unificar ou documentar claramente a fronteira.

### ✅ Pontos fortes (praise)

- **Zero SQL injection** encontrado — prepared statements em 100% das queries com input; identificadores dinâmicos não vêm de input do usuário.
- **XSS bem controlado** — `e()` (htmlspecialchars ENT_QUOTES) consistente nas views; o único vetor DOM é SEC-04.
- **Cifra de segredos por agência** em repouso (libsodium) — acima da média para PHP puro.
- **CSRF com token estável por sessão** e `hash_equals` (design correto, documentado no próprio middleware).
- **Auth sólido:** Argon2id, `session_regenerate_id(true)` no login, cookie `HttpOnly`/`SameSite`/`Secure`, `use_strict_mode`.
- **Fila com `FOR UPDATE SKIP LOCKED`** — concorrência correta.
- **Webhooks validados** — HMAC no ClickUp, token + instance-name no Evolution.
- **Drive privado por proxy** — arquivos não ficam públicos; o servidor faz streaming autenticado com suporte a Range.

---

## 7. Performance

**Backend:**
- **N+1 no Portal:** `PortalController::index` itera contas de anúncio/orgânicas chamando `summaryForAccount` por conta em loop. Aceitável no volume atual; virar agregação única se crescer.
- **PDO persistente:** reduz handshake SSL (bom para Supabase remoto), mas risco de esgotamento de conexões com pooler (ver §6 polish).
- **Paginação real** via `Repository::paginate()` (COUNT + LIMIT/OFFSET) — ok; OFFSET fica caro em tabelas grandes, migrar para keyset se necessário.

**Frontend:**
- **Tailwind via CDN (`cdn.tailwindcss.com`)** — o próprio Tailwind alerta que isso é para prototipagem, não produção: recompila no browser a cada load, sem purge, sem cache de build. Ver PERF-01.
- **4 CDNs externos** sem SRI: Tailwind, Alpine (`@3.x.x` sem pin), Chart.js, marked. Latência + risco de supply chain.
- **Views com muito JS inline:** `content/show.php` (1130 linhas), `portal/files.php` (566). Difícil de manter/testar; sem build step.
- **`fetch()` sem checagem de `response.ok`** em vários pontos — erros de rede/servidor silenciados (só `catch {}`).

---

## 8. Manutenibilidade e dívida técnica

| Item | Impacto | Detalhe |
|------|---------|---------|
| **Rotas pt+en duplicadas** | Alto | ~200 rotas; cada endpoint mantido em dois lugares. Toda mudança precisa ser feita 2×. Considerar um mapa de aliases ou escolher um idioma canônico + redirect. |
| **PortalController 696 linhas** | Médio | Mistura dashboard, planos, feedback e todo o CRUD de Drive. Extrair `PortalDriveController`. |
| **JS inline em views grandes** | Médio | Sem reuso, sem teste. Extrair para `public/js/` módulos. |
| **Sem CLAUDE.md / skills de projeto** | Médio | Agentes recomeçam do zero a cada sessão. **Resolvido** por este trabalho (skills em `.claude/skills/`). |
| **PHPStan sujo (22 erros)** | Baixo | Gate de qualidade perde valor com ruído. |
| **Cobertura de testes rasa** | Médio | 38 testes (unit de Auth, Container, Billing, MetaAds, Secret). Faltam testes de autorização negativa por rota e de isolamento multi-tenant. |

---

## 9. Estado de qualidade (medições)

```
Em 2026-07-06 (auditoria):        38 testes · PHPStan 22 erros · 3 CVEs (guzzle/psr7)
Em 2026-07-14 (ciclo 2, medido):  77 testes, 140 asserts — 100% verde
                                  PHPStan nível 6 (v2.2.5): 0 erros
                                  composer audit: 0 advisories
Em 2026-07-23 (ciclo 3, medido):  188 testes, 417 asserts — 100% verde
                                  (feature com banco PG real + smoke Chromium)
                                  PHPStan nível 6: 0 erros
                                  composer audit: 0 advisories (dompdf 3.1.6 / guzzle 7.15.1)
PHP runtime:     8.5.2 (composer.json exige >=8.3) ✓
Secrets no git:  apenas .env.example versionado ✓ (.env/.env.production ignorados)
```

---

## 10. Conclusão executiva

O sistema tem **fundação de engenharia sólida e incomum para PHP puro**: arquitetura em camadas limpa, RBAC real, multi-tenancy desde o dia 1, segredos cifrados por agência, fila de jobs correta e integrações bem encapsuladas. A superfície de SQL injection e XSS está bem coberta.

**Para comercializar, o bloqueio não é funcional — é de hardening de produção.** Três itens são inegociáveis antes de vender: (1) `APP_ENV=production` para não vazar erros, (2) rate limit que não confie em header falsificável, (3) atualizar Guzzle. Depois vêm CSP/HSTS, cifra das credenciais globais, o bug de notificação e as FKs faltantes.

O caminho está detalhado, priorizado e estimado no [PLANO_MESTRE.md](PLANO_MESTRE.md). As skills em `.claude/skills/` dão a qualquer agente o contexto necessário para executar cada item com segurança e manter o padrão do código.
