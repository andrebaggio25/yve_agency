# Plano de Desenvolvimento — Central de Automação da Agência (PHP Puro)

> **Stack definido:** PHP 8.3+ puro (sem Laravel), Composer apenas para bibliotecas auxiliares, PostgreSQL, arquitetura MVC própria com camada de Services e Repositories.
>
> Este documento é o **plano de execução** do projeto. Ele parte da especificação funcional original e a transforma em um roteiro técnico acionável, corrigindo as lacunas da spec inicial (ausência de foreign keys/índices, fila de jobs, CSRF, container de injeção de dependências, criptografia de tokens, testes e critérios de aceite).

---

## 0. Decisões de arquitetura (o que muda em relação à spec original)

A spec original é excelente como visão de produto, mas como base de engenharia precisa destas decisões fechadas antes da primeira linha de código:

| Tema | Decisão | Motivo |
|------|---------|--------|
| Banco | **PostgreSQL 15+** | Suporte nativo a `JSONB`, `gen_random_uuid()`, índices parciais e melhor concorrência para os jobs. |
| Chave primária | `BIGINT` identity nas tabelas internas; tokens públicos via UUID/coluna `public_token` | Performance interna + URLs públicas não sequenciais (relatório por link). |
| Migrations | **Phinx** (`robmorgan/phinx`) | Versionamento de schema reprodutível; a spec lista `CREATE TABLE` solto, sem FK/índice. |
| Roteamento | Router próprio com **pipeline de middlewares** | Mantém o "PHP puro", mas com middleware encadeável (auth → permissão → acesso ao cliente → controller). |
| Injeção de dependência | **Container PSR-11 mínimo próprio** | Evita `new` espalhado; Services e Repositories resolvidos por construtor. Testável. |
| Templates | PHP nativo com layout + partials + função `e()` (escape) obrigatória | Sem engine externa; segurança por escaping consistente. |
| Fila/Jobs | **Fila em tabela (`jobs`) + worker via cron** | PHP puro não tem Horizon. Um worker simples processa jobs com retry/backoff. |
| Segredos | `.env` + **criptografia simétrica (libsodium)** para tokens de API armazenados | Token Meta/OpenAI/Evolution nunca em texto puro no banco. |
| Multi-tenant | `agency_id` em todas as tabelas + **escopo global automático** no Repository base | Garante isolamento desde o dia 1 sem depender de o dev lembrar do `WHERE`. |
| Frontend | HTML + Tailwind (CDN no início, build depois) + Alpine.js + Chart.js | Conforme spec; leve e sem pipeline pesado no MVP. |
| Testes | **PHPUnit** para Services e regras de permissão | A camada de autorização é crítica e precisa de testes desde o início. |
| Padrão de código | PSR-12 + `php-cs-fixer` + análise estática **PHPStan (nível 6+)** | Qualidade sem framework para segurar a mão. |

---

## 1. Stack e dependências

### Runtime
- PHP **8.3+** (CLI + FPM)
- PostgreSQL **15+**
- Servidor: Nginx + PHP-FPM (produção) / `php -S` (desenvolvimento)
- Cron do sistema operacional para o worker de jobs e schedulers

### Composer
```bash
composer require vlucas/phpdotenv        # .env
composer require guzzlehttp/guzzle       # HTTP client (Meta, OpenAI, Evolution)
composer require phpmailer/phpmailer     # e-mail SMTP
composer require monolog/monolog         # logs estruturados
composer require firebase/php-jwt        # tokens (relatório público, API futura)
composer require respect/validation      # validação de input
composer require robmorgan/phinx         # migrations + seeders
composer require nesbot/carbon           # datas/timezone

composer require --dev phpunit/phpunit
composer require --dev phpstan/phpstan
composer require --dev friendsofphp/php-cs-fixer
```

### Integrações externas (por fase)
- Meta Marketing API + Instagram Graph API (Fase 6/9)
- Google Drive (embed público no MVP; API na fase avançada)
- Evolution API — WhatsApp (Fase 3)
- OpenAI / provedor de IA (Fase 4)
- SMTP via PHPMailer (Fase 3)

---

## 2. Estrutura de pastas

```txt
/app
  /Core                      # o "micro-framework" próprio
    Router.php               # registro de rotas + match
    Route.php
    Request.php              # wrapper de $_SERVER/$_GET/$_POST/$_FILES
    Response.php             # json(), view(), redirect(), withStatus()
    Container.php            # DI container PSR-11 mínimo
    Pipeline.php             # execução encadeada de middlewares
    Controller.php           # base: acesso a view(), request, response
    Model.php                # base opcional (mapeamento simples)
    Repository.php           # base: PDO + escopo de agency_id
    Database.php             # factory de conexão PDO (singleton)
    View.php                 # render de templates + layout
    Validator.php            # wrapper do Respect\Validation
    Env.php                  # acesso tipado às variáveis de ambiente
    Crypto.php               # encrypt()/decrypt() de segredos (libsodium)
    Logger.php               # factory Monolog por canal

  /Controllers               # 1 método público por rota; sem regra de negócio
  /Services                  # regra de negócio (AuthService, ContentPlanService, ...)
  /Repositories              # SQL isolado; herdam Core\Repository
  /Models                    # DTOs/entidades simples
  /Middlewares
    AuthMiddleware.php
    PermissionMiddleware.php
    ClientAccessMiddleware.php
    CsrfMiddleware.php
    RateLimitMiddleware.php
  /Integrations
    /Meta /GoogleDrive /Evolution /OpenAI /Mailer
  /Jobs                      # cada job: handle(array $payload)
  /Policies                  # regras de autorização por recurso (testáveis)
  /Support                   # helpers: dates, currency, drive, str, response

/config
  app.php  database.php  services.php  permissions.php  mail.php

/database
  /migrations                # Phinx
  /seeders                   # roles, permissions, currencies, super_admin

/public
  index.php                  # front controller (único ponto de entrada)
  /assets  /css  /js  /img

/resources/views             # templates PHP (separado do código de app)
  /layouts  /auth  /dashboard  /clients  /content  /approvals  /reports
  /partials  /emails

/routes
  web.php  api.php

/storage
  /logs  /uploads  /cache  /exports  /framework (sessões em arquivo, se usado)

/tests
  /Unit  /Feature

/bin
  worker.php                 # consome a fila de jobs
  scheduler.php              # dispara automações agendadas (chamado pelo cron)

.env  .env.example  composer.json  phpstan.neon  phpunit.xml  README.md
```

**Diferença-chave vs. spec original:** as `Views` saem de dentro de `/app` para `/resources/views` (separação template/código), e entram quatro pastas novas — `/Core`, `/Policies`, `/Support` e `/bin` — que são o que torna o "PHP puro" sustentável.

---

## 3. Fluxo de requisição

```txt
public/index.php
  ↓ carrega autoload, .env, config, sessão segura
  ↓ resolve Container (registra Services/Repositories)
Router::dispatch(method, uri)
  ↓ encontra rota + lista de middlewares
Pipeline:
  CsrfMiddleware (em POST/PUT/DELETE)
  → RateLimitMiddleware (login)
  → AuthMiddleware
  → PermissionMiddleware('modulo.acao')
  → ClientAccessMiddleware(clientId)   # quando a rota é de cliente
  → Controller::acao(Request): Response
       ↓ chama Service (regra de negócio)
            ↓ chama Repository (SQL + escopo agency_id)
                 ↓ PDO (prepared statements)
  → Response (view | json | redirect) → navegador
```

### Regras invioláveis
1. Controller não tem SQL nem regra de negócio — só orquestra Service e devolve Response.
2. Toda query passa por Repository com **prepared statements**. Zero concatenação de SQL.
3. Toda saída em template usa `e()`/escape. Zero `echo` cru de dado do usuário.
4. Permissão sensível **sempre validada no backend** (middleware/Policy), nunca só na view.
5. Toda ação sensível grava em `activity_logs`.
6. Credenciais só via `.env`/`Crypto`. Nunca no código nem no front.

---

## 4. Banco de dados — princípios

A spec original define bem as tabelas, mas falta o essencial de integridade. O schema real deve seguir estas regras (aplicadas via Phinx):

- **Foreign keys explícitas** com `ON DELETE` definido (`RESTRICT` para dados de negócio, `CASCADE` para dependentes como `content_plan_items`).
- **Índices** em toda FK e em colunas de filtro frequente: `agency_id`, `client_id`, `status`, `(client_id, week_start)`, `next_run_at`, etc.
- **`agency_id` NOT NULL** em todas as tabelas multi-tenant; o Repository base injeta o filtro automaticamente.
- **Timestamps** `created_at`/`updated_at` em todas as tabelas; `updated_at` mantido por trigger ou pela aplicação.
- **Colunas financeiras** seguem o padrão multi-moeda obrigatório:
  `amount`, `currency_code`, `exchange_rate_to_base`, `base_currency_amount`.
- **Tokens de integração** (`client_integrations`, `meta_accounts`) guardados **criptografados** (coluna `*_encrypted`), nunca em texto puro.
- **JSONB** para `metadata`, `payload`, `api_response` (permite consulta indexada).
- **Enums por CHECK** ou tabela de domínio para `status` (lista canônica na Seção 21 da spec).

### Tabelas (mesma lista da spec, agrupadas por domínio)

- **Núcleo/tenant:** `agencies`, `users`, `roles`, `permissions`, `role_permissions`, `user_roles`
- **Clientes:** `clients`, `client_contacts`, `client_addresses`, `client_user_access`, `client_marketing_profiles`, `client_financial_profiles`, `client_integrations`
- **Conteúdo:** `content_plans`, `content_plan_items`, `content_assets`, `content_feedbacks`
- **Tráfego:** `meta_accounts`, `meta_campaigns`, `meta_adsets`, `meta_ads`, `meta_daily_metrics`
- **Orgânico:** `organic_profiles`, `organic_daily_metrics`
- **IA:** `ai_agents`, `ai_reports`, `ai_recommendations`, `ai_actions`, `ai_action_approvals`, `ai_safety_rules`
- **Automação/comunicação:** `automation_rules`, `automation_logs`, `whatsapp_instances`, `whatsapp_messages`, `email_templates`, `email_logs`, `jobs` *(nova — fila)*
- **Financeiro:** `currencies`, `exchange_rates`, `invoices`, `invoice_items`, `payments`, `contracts`
- **Operação:** `tasks`, `activity_logs`
- **Criativos:** `creative_library`, `brand_guidelines`, `creative_generations`, `creative_generation_reviews`

> Os `CREATE TABLE` detalhados da spec (Seções 8.2–8.22) entram como migrations Phinx, acrescidos das FKs e índices acima.

### Tabela nova: `jobs` (fila)
```txt
id, agency_id, queue, payload(JSONB), available_at, reserved_at,
attempts, max_attempts, status(pending|reserved|done|failed),
last_error, created_at, updated_at
```

---

## 5. Segurança (camada obrigatória desde o MVP)

A spec lista regras de segurança na Seção 19; aqui está como implementá-las concretamente:

| Risco | Implementação |
|-------|---------------|
| Senha | `password_hash(PASSWORD_ARGON2ID)` + `password_verify`. Nunca texto puro. |
| Sessão | Cookie `HttpOnly`, `Secure`, `SameSite=Lax`; regenerar id no login; timeout idle. |
| CSRF | Token por sessão; `CsrfMiddleware` valida em todo POST/PUT/DELETE. |
| SQL Injection | Exclusivamente prepared statements via PDO no Repository. |
| XSS | `e()` em toda saída; CSP básica via header. |
| Brute force login | `RateLimitMiddleware` + bloqueio temporário após N falhas. |
| Tokens de API | Criptografados com libsodium (`Core\Crypto`); chave mestra no `.env`. |
| Autorização | RBAC validado em middleware **e** Policy; isolamento por `agency_id` e `client_user_access`. |
| Auditoria | `activity_logs` em toda ação sensível (login, financeiro, contrato, aprovação, ação Meta, mudança de permissão, ação de IA). |
| Headers | `X-Frame-Options`, `X-Content-Type-Options`, `Referrer-Policy`, HSTS em produção. |

### Modelo de autorização (RBAC + escopo de cliente)
No login, carrega-se em sessão: `user`, lista de `permissions` (slugs achatados dos roles) e `client_ids` acessíveis. A classe `Auth` (Seção 11 da spec) é o ponto único de checagem, complementada por Policies para regras compostas. Toda rota de cliente exige os 5 passos: logado → role ativo → permissão do módulo → acesso ao cliente → permissão da ação → log.

---

## 6. Fila de jobs e agendamento (substitui "cron simples" da spec)

PHP puro não tem worker gerenciado, então o plano define explicitamente:

- **`bin/worker.php`** — loop que reserva jobs `pending`, executa o handler, marca `done`/`failed` com **retry e backoff exponencial** (`attempts`/`max_attempts`). Rodado por `supervisord` ou um cron que o mantém vivo.
- **`bin/scheduler.php`** — chamado pelo cron a cada minuto; lê `automation_rules` com `next_run_at <= now()`, enfileira o job correspondente e recalcula `next_run_at`.
- Jobs implementam uma interface `handle(array $payload): void` e são idempotentes.

Jobs previstos (da spec): `SyncMetaMetricsJob`, `SyncOrganicMetricsJob`, `GenerateDailyAIReportJob`, `GenerateWeeklyAIReportJob`, `SendWhatsappMessageJob`, `SendEmailJob`, `GenerateInvoiceJob`.

Cron mínimo:
```cron
* * * * * php /caminho/bin/scheduler.php >> storage/logs/scheduler.log 2>&1
@reboot  php /caminho/bin/worker.php
```

---

## 7. Integração de IA com guardrails

Princípio da spec mantido: **observa → analisa → recomenda → humano aprova → executa → loga → mede.** Nunca autonomia total no início (começar nível 1–2).

Implementação:
- `OpenAIClient` isolado em `/Integrations/OpenAI`, com timeout, retry e **log de prompt+resposta** (custo e auditoria).
- `AIAnalysisService` monta o contexto (métricas, perfil de marca, `ai_safety_rules`) e gera `ai_recommendations` com `reason` e `risk_level` obrigatórios.
- Toda ação que altera algo externo vira `ai_actions` com `status` (`pending|approved|rejected|executed|failed`) — só executa após aprovação humana e dentro dos limites de `ai_safety_rules` (ex.: `max_budget_change_percent`, `can_pause_ads`, `requires_human_approval`).
- Guardrails verificados em código **antes** de chamar a API da Meta, não confiando no prompt.

---

## 8. Roadmap de execução

Mantém as 12 fases da spec, mas cada uma agora tem **critério de aceite** (definição de pronto). Fundações (Fase 0) são novas e destravam tudo.

### Fase 0 — Fundação técnica *(novo — pré-requisito)*
Core (Router, Container, Pipeline, Request/Response, View, Database, Crypto, Logger), `.env`, autoload PSR-4, Phinx configurado, PHPUnit + PHPStan rodando, layout base com Tailwind, helper `e()`.
**Pronto quando:** uma rota de teste passa por um middleware, renderiza uma view e um teste unitário roda no CI local.

### Fase 1 — Base do sistema (MVP-core)
Migrations do núcleo + clientes; seeders (roles, permissions, currencies, super_admin); login/logout/sessão; CRUD de usuários, roles, permissions; `Auth`/`PermissionService`; `activity_logs`; CRUD completo de clientes (internacional: país, moeda, idioma, timezone, fiscal, contatos, marketing); `client_user_access`.
**Pronto quando:** um social_media só enxerga os clientes vinculados; um financeiro não acessa planificação; toda ação sensível aparece em `activity_logs`. Testes de permissão verdes.

### Fase 2 — Operação de conteúdo
`content_plans` + `content_plan_items`; parser de link do Google Drive (extrai `file_id`, detecta tipo, gera embed/preview); módulo de aprovação/feedback (`content_feedbacks`); **área do cliente** com acesso restrito às próprias planificações.
**Pronto quando:** equipe cria planificação semanal, cliente aprovador aprova/pede ajuste pela própria área, histórico de status registrado.

### Fase 3 — Comunicação
`EvolutionClient` (WhatsApp) + `MailerClient` (PHPMailer); `email_templates`/`email_logs`/`whatsapp_messages`; jobs `SendEmail`/`SendWhatsapp` na fila; notificações internas em eventos (aprovação, ajuste solicitado).
**Pronto quando:** ao enviar planificação, o cliente recebe WhatsApp + e-mail via fila, com log de sucesso/falha e retry.

### Fase 4 — IA inicial
`OpenAIClient`; `AIAnalysisService`; resumo de planificação; geração de mensagem de envio ao cliente; relatório manual; `ai_reports` com log de prompt/resposta. Autonomia nível 1–2.
**Pronto quando:** a IA gera um resumo da semana e uma mensagem de envio, ambos auditáveis.

### Fase 5 — Dashboards e relatórios
Dashboard geral da agência + dashboard por cliente (abas); relatório semanal; **relatório público por link** `/d/relatorio/{token}` (JWT/`public_token`, sem dados internos).
**Pronto quando:** gestor abre o dashboard com indicadores reais e gera um link público de relatório.

### Fase 6 — Meta Ads (leitura)
`MetaAuth` (token criptografado) + `MetaInsights`; `meta_accounts/campaigns/adsets/ads`; `SyncMetaMetricsJob` salvando snapshot diário em `meta_daily_metrics`; dashboard de tráfego.
**Pronto quando:** métricas (invest., leads, CPL, CTR, CPA, ROAS, freq.) sincronizam diariamente e aparecem no dashboard.

### Fase 7 — IA para tráfego
Análise de campanhas; detecção de alertas; `ai_recommendations` com justificativa; criação de tarefas; aprovação humana; histórico de otimizações.
**Pronto quando:** a IA recomenda uma otimização (sem executar) e o gestor aprova/rejeita, ficando registrado.

### Fase 8 — Ações assistidas na Meta
`MetaCampaignManager`; pausar anúncio/alterar orçamento/ativar — **só com aprovação** e dentro de `ai_safety_rules`; `ai_actions` com execução e `api_response`; medição do resultado pós-mudança.
**Pronto quando:** uma ação aprovada altera a campanha na Meta, guarda a resposta da API e registra o efeito.

### Fase 9 — Orgânico
Instagram Graph API; `organic_profiles`/`organic_daily_metrics`; ranking de conteúdo; sugestões de impulsionamento.
**Pronto quando:** métricas orgânicas diárias persistem e o dashboard orgânico mostra melhores conteúdos.

### Fase 10 — Financeiro
`currencies`/`exchange_rates`; `contracts`/`invoices`/`invoice_items`/`payments`; recorrência; lembretes automáticos (fila); relatórios financeiros multi-moeda consolidados na moeda base.
**Pronto quando:** uma fatura recorrente é gerada, convertida para a moeda base e o lembrete de vencimento é enviado automaticamente.

### Fase 11 — Criativos com IA
`brand_guidelines`; `creative_library`; geração de headline/copy/conceito; `creative_generations` + revisão interna; envio ao cliente.
**Pronto quando:** a IA gera um criativo respeitando as diretrizes de marca e passa por aprovação interna.

### Fase 12 — SaaS multiagência
Onboarding de agência; planos/assinaturas; limites por agência; white label; billing. (O `agency_id` já existe desde a Fase 1, então é evolução, não refatoração.)
**Pronto quando:** uma segunda agência opera isolada da primeira, com seu próprio plano e limites.

---

## 9. Ordem de implementação (sprints sugeridos)

| Sprint | Entrega | Fases |
|--------|---------|-------|
| 1 | Fundação Core + auth + RBAC | 0–1 |
| 2 | CRUD clientes + acesso por cliente | 1 |
| 3 | Planificação + Drive embed | 2 |
| 4 | Aprovação + área do cliente | 2 |
| 5 | WhatsApp + e-mail + fila | 3 |
| 6 | IA resumo/mensagem + dashboards | 4–5 |
| 7+ | Meta Ads leitura → IA tráfego → ações → orgânico → financeiro → criativos → SaaS | 6–12 |

**MVP entregável = Sprints 1–5** (resolve a dor operacional: cadastrar clientes, planejar, aprovar, comunicar — exatamente o "MVP 1" da spec).

---

## 10. Definição de pronto global (qualidade)

Toda entrega de fase só é considerada concluída se:
- [ ] Migrations reversíveis aplicam e revertem sem erro.
- [ ] Rotas novas passam por auth + permissão + (quando aplicável) acesso ao cliente.
- [ ] Services com regra de negócio têm teste PHPUnit.
- [ ] Regras de permissão da fase têm teste de autorização (positivo e negativo).
- [ ] PHPStan nível 6+ sem erros novos; código em PSR-12.
- [ ] Ações sensíveis gravam `activity_logs`.
- [ ] Nenhum segredo no código; tokens armazenados criptografados.
- [ ] Nenhuma saída de template sem escape.

---

## 11. Primeiros comandos (kickoff)

```bash
# 1. Dependências
composer init && composer require <libs da seção 1>

# 2. Configuração
cp .env.example .env        # DB, APP_KEY (chave libsodium), SMTP, etc.

# 3. Banco
vendor/bin/phinx migrate    # cria o schema
vendor/bin/phinx seed:run   # roles, permissions, currencies, super_admin

# 4. Rodar local
php -S localhost:8000 -t public

# 5. Qualidade
vendor/bin/phpstan analyse
vendor/bin/phpunit
```

---

## 12. Resumo

Sistema construído em **PHP puro, mas com engenharia de produto profissional**: um Core próprio mínimo (router + container + pipeline) que sustenta MVC + Services + Repositories, segurança e RBAC desde o dia 1, `agency_id` em tudo para o futuro SaaS, fila de jobs real para automações, e IA sempre sob o princípio *observa → recomenda → humano aprova → executa → loga → mede*.

A prioridade de valor é: **estrutura segura → clientes → permissões → planificação → aprovação → comunicação → IA → dashboards → Meta Ads → financeiro → criativos.** Começa como central interna da agência e termina como plataforma multiagência — sem refatoração estrutural no caminho.
