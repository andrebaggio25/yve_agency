# YVE Agency — Plano Mestre

> Roteiro priorizado de correções, hardening e evolução para comercialização.
> Base: [ANALISE_SISTEMA.md](../ANALISE_SISTEMA.md) · Atualizado: 2026-07-06
> Cada item tem: ID · severidade · esforço (P/M/G) · arquivos · critério de pronto.

Convenção de esforço: **P** = ≤2h · **M** = meio dia a 1 dia · **G** = vários dias.

---

## Marco 0 — Bloqueadores de comercialização (fazer primeiro)

> Sem estes, **não** vá para produção paga. Todos são de segurança/config e de baixo esforço.
>
> **✅ CONCLUÍDO em 2026-07-06** — SEC-01, SEC-02, DEP-01 e BUG-01 corrigidos e validados (42 testes verdes, `composer audit` limpo). Detalhes de cada correção abaixo mantidos para referência.

### SEC-01 · `APP_ENV=production` em produção — `P` 🔴
- **Problema:** `.env.production` está com `APP_ENV=development` → stack traces e erros de SQL vazam ao usuário.
- **Arquivos:** `.env.production`, `public/index.php` (§error reporting), `app/Core/Router.php:147` (`handleError`).
- **Ação:** setar `APP_ENV=production`; confirmar `display_errors=0`/`log_errors=1` no PHP-FPM de produção; garantir que `handleError` nunca inclua trace fora de dev.
- **Pronto quando:** um erro 500 forçado em produção mostra a página `errors/500` genérica, sem trace, e o detalhe aparece só no log.

### SEC-02 · Rate limit à prova de `X-Forwarded-For` — `M` 🔴
- **Problema:** `Request::ip()` confia em `HTTP_X_FORWARDED_FOR`; brute-force de login contorna o limite variando o header.
- **Arquivos:** `app/Core/Request.php:141-146`, `app/Middlewares/RateLimitMiddleware.php`.
- **Ação:** `ip()` retorna `REMOTE_ADDR` por padrão; só honra `X-Forwarded-For` se `REMOTE_ADDR` estiver em `TRUSTED_PROXIES` (nova env). Reduzir login para `maxAttempts=5`, `decaySeconds=60`. Adicionar lock (`flock`) na escrita do arquivo.
- **Pronto quando:** um teste envia 20 logins falhos variando `X-Forwarded-For` e é bloqueado após 5.

### DEP-01 · Atualizar Guzzle/psr7 (3 CVEs) — `P` 🔴
- **Arquivos:** `composer.json`, `composer.lock`.
- **Ação:** `composer update guzzlehttp/guzzle guzzlehttp/psr7` (≥7.12.1 / ≥2.12.1). Rodar `composer audit` e a suíte depois.
- **Pronto quando:** `composer audit` retorna 0 advisories e os 38 testes seguem verdes.

---

## Marco 1 — Hardening de produção

> **✅ CONCLUÍDO em 2026-07-06** — SEC-03, SEC-04, SEC-05, SEC-06 e SEC-07 e SCHEMA-01 implementados e validados (48 testes verdes, PHPStan sem regressão). Ressalvas: (a) a CSP mantém `unsafe-inline`/`unsafe-eval` pois o app usa Tailwind/Alpine por CDN — endurecer para nonce após PERF-01; validar em navegador com `visual-validation`. (b) SEC-06 cobriu a API interna `/api/comentarios` (gap real de cookie de sessão); os endpoints do portal seguem protegidos pelo token na URL — CSRF uniforme no portal fica como follow-up. (c) as 2 migrations novas precisam rodar em produção (`composer migrate`). Detalhes de cada item abaixo.

### SEC-03 · Content-Security-Policy + HSTS — `M` 🟠 · ✅ FEITO
- **Arquivos:** `app/Core/Response.php:91-104`.
- **Ação:** header CSP com allowlist (ou self-host, ver PERF-01); `Strict-Transport-Security` quando HTTPS. Bloquear `unsafe-inline` gradualmente (há inline scripts hoje — coordenar com PERF-02).
- **Pronto quando:** resposta traz CSP e HSTS; o app funciona sem violações no console.

### SEC-05 · Cifrar credenciais globais em `platform_settings` — `M` 🟠
- **Arquivos:** `app/Repositories/PlatformSettingsRepository.php`, `app/Controllers/Admin/GlobalSettingsController.php`, consumidores (`MetaAdsService`, `AiInsightService`, `EvolutionApiService`, `EmailService`).
- **Ação:** cifrar chaves sensíveis (`mail_password`, `meta_app_secret`, `openai_api_key`, `anthropic_api_key`, `evolution_api_key`) com `Secret` na escrita e decifrar na leitura. Migration para cifrar valores existentes (usar `bin/encrypt_tokens.php` como referência).
- **Pronto quando:** um `SELECT` em `platform_settings` mostra as chaves cifradas e as integrações continuam funcionando.

### SEC-06 · CSRF uniforme em ações de estado — `M` 🟠
- **Arquivos:** `routes/web.php` (portal drive + itemFeedback), `app/Middlewares/CsrfMiddleware.php`, `/api/comentarios/*`.
- **Ação:** aplicar CSRF (ou double-submit) em todo POST/PUT/DELETE que muda estado. Para APIs JSON autenticadas por sessão, exigir `X-CSRF-Token`. Corrigir a lista `except` (`/webhooks/` não bate com a rota real `/webhook/`).
- **Pronto quando:** todo endpoint de mutação valida CSRF; webhooks (com HMAC/token próprio) seguem isentos por caminho correto.

### SEC-04 · Sanitizar Markdown da IA + pin de CDN — `P` 🟠
- **Arquivos:** `resources/views/ia/show.php:76-81`.
- **Ação:** DOMPurify após `marked.parse`, ou render como texto; fixar versão do `marked` com hash SRI.
- **Pronto quando:** um insight com `<img onerror>` embutido não executa script.

### SEC-07 · Validar posse de entidade nos comentários — `P` 🟠
- **Arquivos:** `app/Services/InternalCommentService.php`, `app/Repositories/InternalCommentRepository.php`.
- **Ação:** antes de gravar, confirmar que `entity_id` do tipo pertence à agência.
- **Pronto quando:** comentar numa tarefa de outra agência retorna 404/403.

### SCHEMA-01 · FKs faltantes (Drive/ClickUp) — `M` 🟠
- **Arquivos:** nova migration; `database/migrations/20260612000020..23`, `20260612000014`.
- **Ação:** adicionar FKs de `agency_id`/`client_id`/`folder_id` com `ON DELETE CASCADE`. Limpar órfãos antes de aplicar.
- **Pronto quando:** excluir um cliente remove suas pastas/arquivos/integrações em cascata; migration reverte sem erro.

---

## Marco 2 — Correção de bugs e qualidade

### BUG-01 · Notificações in-app sem `action_url` — `P` 🟠
- **Arquivos:** `app/Services/NotificationService.php:87-93`.
- **Ação:** montar o array explicitamente (`'action_url' => $actionUrl`, etc.), removendo o `compact()` com nomes errados.
- **Pronto quando:** clicar numa notificação leva ao destino; PHPStan não acusa variável indefinida.

### QA-01 · Zerar PHPStan nível 6 — `M` 🟡 · ✅ FEITO (2026-07-06)
> `Container`/`Request`/`Response` marcadas `final` (resolve `new static()`); `Container` usa `self::`; `View::render` lê os statics via acessores tipados (o include muta por efeito colateral que o PHPStan não rastreia). Bug real corrigido de brinde: `ReportController` chamava `Response::withError()` (inexistente) — agora flash + redirect. Removido código morto (5 props injetadas sem uso, `videoTypes()`, ternário sempre-verdadeiro). `phpstan.neon` migrado da opção deprecada. **`composer analyse` = [OK] No errors.**

### QA-02 · Testes de autorização e multi-tenancy — `G` 🟠 · ✅ FEITO (2026-07-06)
> `tests/Unit/MiddlewareAuthorizationTest.php` (11 casos): positivo+negativo para `AuthMiddleware` (401/302/passa), `PermissionMiddleware` (403/passa), `ClientAccessMiddleware` (403/passa/`view_all` bypassa) e `PlatformAdminMiddleware` (403/redirect/passa). `tests/Unit/RepositoryScopeTest.php` (3 casos): `agencyScope()` filtra por tenant, é `1=1` para platform admin, e **falha fechado** (throw) sem agência. Falha se alguém quebrar o enforcement. **62 testes verdes.** Follow-up possível: testes HTTP ponta a ponta (exigem schema em banco de teste — hoje as migrations são PG-only).

### PERF-01 · Build de assets (sair do Tailwind CDN) — `M` 🟡
- **Ação:** pipeline mínimo (Tailwind CLI) gerando CSS purgado em `public/css/app.css`; self-host de Alpine/Chart.js com SRI. Remove dependência de CDN em runtime e destrava CSP estrita.
- **Pronto quando:** nenhuma tag `<script src="cdn...">` em runtime; página carrega CSS local.

### PERF-02 · Extrair JS inline das views grandes — `M` 🟡
- **Arquivos:** `resources/views/content/show.php`, `portal/files.php`, `clients/files.php`.
- **Ação:** mover para módulos em `public/js/`; padronizar wrapper `fetch` com checagem de `response.ok`, estado de loading e erro.
- **Pronto quando:** views grandes < 400 linhas; um helper único de fetch trata erro/loading.

### DRIVE-01 · Preview de imagem do Drive na aprovação do cliente — `M` 🟠 · ✅ FEITO (2026-07-06)
> Helper `GoogleDriveService::imageSrc()` converte links do Drive para o endpoint `thumbnail` (funciona em `<img>`); aplicado em `portal/plan_show.php` (capa, carrossel e imagem do Drive agora com preview inline) e na função JS `driveImageUrl()` de `content/show.php`. Coberto por `tests/Unit/DriveImageSrcTest.php`. Pré-requisito operacional: o arquivo do Drive precisa estar compartilhado "qualquer um com o link".
- **Problema (confirmado):** na página que o cliente usa para aprovar a planificação (`portal/plan_show.php`) e na edição do item (`content/show.php`), as imagens (`cover_url`, `images[]`) são hotlinkadas do Google via `https://drive.google.com/uc?export=view&id=…` (montado por `driveImageUrl()` em `content/show.php:719-724`). **O Google descontinuou esse endpoint para `<img>`** — devolve uma página HTML de aviso/consentimento em vez dos bytes, então o `onerror` esconde a imagem e o preview fica em branco. Imagem do Drive em `drive_url` ainda cai no ramo "ver arquivo no Drive" (só link, sem preview inline — `plan_show.php:177`).
- **Causa raiz secundária:** o app usa escopo OAuth `drive.file` — só enxerga arquivos que ele mesmo criou. Link colado manualmente pela equipe aponta para arquivo que o app não lê pela API; só renderiza se estiver compartilhado "qualquer um com o link".
- **Correção:**
  1. Imagens **enviadas pelo app** (existem em `drive_files`): referenciar pelo **proxy próprio** já existente (`/portal/{token}/drive/file/{id}/raw`; agência `/clientes/{id}/conteudos/file/{fileId}/raw`) em vez do link do Google — streaming autenticado, confiável e privado. Ideal: ligar o seletor de imagem do item ao upload do app, guardando `drive_file_id`.
  2. Links **colados manualmente**: trocar `uc?export=view&id=` por `https://drive.google.com/thumbnail?id=ID&sz=w1000` (funciona para arquivo com link público) + iframe `/preview` como fallback; instruir a compartilhar "qualquer um com o link".
  3. Renderizar imagem do Drive (`file_type === 'image'`) com preview inline no `plan_show.php`, não só link.
- **Pronto quando:** item com imagem enviada pelo portal mostra o preview no card de aprovação (desktop e mobile); imagem via link público renderiza; nenhum `<img>` quebrado.

### DRIVE-02 · Sincronizar alterações feitas direto no Drive → sistema — `G` 🟠 · ✅ FASE 1 FEITA (2026-07-06)
> Reconciliação implementada: `DriveSyncService` (recursivo) + `GoogleDriveApiService::listFolder()` + botão "Sincronizar" na galeria da agência (`/clientes/{id}/conteudos/sync`) + cron `/queue/sync-drive`. Reflete delete/rename/move de arquivos **criados pelo app**. **Ainda pendente** (fase 2, decisão de produto): detectar arquivos adicionados **manualmente** no Drive exige escopo `drive.readonly` + verificação do Google — ver abaixo.
- **Problema:** o sistema grava metadados em `drive_files`/`drive_folders` no upload. Se alguém **mexe direto no Google Drive** (adiciona, apaga, renomeia, move), o banco não sabe — galeria dessincroniza (arquivo fantasma ou novo invisível). Hoje só há limpeza reativa de órfão no 404 do proxy (`PortalController::driveFileRaw`).
- **Restrição de arquitetura decisiva:** o escopo `drive.file` **só expõe arquivos criados pelo próprio app**. Arquivos que o cliente **adiciona manualmente na interface do Drive são invisíveis** para a API. Logo: detectar delete/rename/move do que o app enviou → viável com `drive.file`; detectar adição manual → exige escopo `drive.readonly`/`drive` + verificação/CASA do Google (custo/prazo — decisão de produto).
- **Solução recomendada (faseada):**
  1. **Reconciliação sob demanda + agendada** (sem trocar escopo): serviço que lista a pasta via `files.list` (`'{folderId}' in parents and trashed=false`) e reconcilia com o banco — insere novo, remove sumido, atualiza nome. Botão "Sincronizar" na galeria + cron (`/queue/sync-drive`). Usar `changes.list` com `startPageToken` por agência para delta eficiente.
  2. **Endurecer a listagem:** marcar item ausente no Drive como "removido" em vez de mostrar quebrado (estende o padrão órfão-em-404 para a lista).
  3. **Push opcional (real-time):** `changes.watch` (canal de webhook, renovar ~7 dias) — só se precisar de tempo real.
  4. **Se incluir adições manuais:** planejar upgrade de escopo `drive.readonly` + verificação Google (item separado, impacto de compliance).
- **Pronto quando:** "Sincronizar" reflete no sistema arquivos/pastas criados pelo app que foram apagados/renomeados no Drive; cron mantém consistência; a listagem não exibe arquivo fantasma.

---

## Marco 3 — Robustez de plataforma

- **INFRA-01 · Worker de fila resiliente** (`M`): hoje a fila roda por cron HTTP (`/queue/work`). Documentar/prover `bin/worker.php` como serviço (supervisord) para latência menor; manter o modo HTTP como fallback. Unificar `jobs` e `notification_jobs`.
- **INFRA-02 · Reavaliar PDO persistente** (`P`): medir conexões com o pooler do Supabase; desligar `ATTR_PERSISTENT` se houver saturação.
- **INFRA-03 · Padronizar `insert()` com `RETURNING id`** (`P`): remover dependência de `lastInsertId()` sem sequência.
- **OBS-01 · Observabilidade** (`M`): centralizar logs (já há Monolog), alertas de job falho, painel de saúde `/health`. Rastrear erros de integração.
- **DATA-01 · Backup e retenção** (`P`): política de backup do Supabase documentada; retenção de `activity_logs`.

---

## Marco 4 — Evolução de produto (pós-comercialização)

Continuidade das fases do [PLANO_FASES.md](../PLANO_FASES.md) que seguem parciais:

- **Fase 3 (Comunicação):** consolidar templates de e-mail/WhatsApp, logs de entrega e retry visível.
- **Fase 7–8 (IA de tráfego + ações assistidas):** amarrar `ai_recommendations` → `ads_actions` com guardrails de `ai_safety_rules` verificados em código antes de chamar a Meta.
- **Fase 11 (Criativos com IA):** `brand_guidelines` + geração de copy/conceito com revisão interna.
- **Fase 12 (SaaS):** onboarding self-service de agência, billing real (gateway), limites por plano aplicados de forma centralizada (hoje checados controller a controller), white-label.
- **UX:** unificar idioma canônico de rotas (reduzir duplicação pt/en); design system consistente (há tokens repetidos em CSS inline no layout).

---

## Sequenciamento sugerido (sprints)

| Sprint | Foco | Itens |
|--------|------|-------|
| **1** | Bloqueadores | SEC-01, SEC-02, DEP-01, BUG-01 |
| **2** | Hardening | SEC-03, SEC-05, SEC-06, SEC-04, SEC-07, SCHEMA-01 |
| **3** | Qualidade + Drive | QA-01, QA-02, PERF-01, PERF-02, DRIVE-01, DRIVE-02 |
| **4** | Robustez | INFRA-01..03, OBS-01, DATA-01 |
| **5+** | Produto | Marco 4 |

**Go-live pago = fim do Sprint 2** (bloqueadores + hardening resolvidos, com QA-02 idealmente já iniciado).

---

## Definição de pronto global (por item)

- [ ] Migrations reversíveis aplicam e revertem sem erro.
- [ ] Rota nova passa por auth + permissão + (se de cliente) `ClientAccessMiddleware`.
- [ ] Regra de negócio nova tem teste PHPUnit; permissão nova tem teste positivo **e** negativo.
- [ ] `composer analyse` (PHPStan) sem erro novo; `composer audit` limpo.
- [ ] Ação sensível grava em `activity_logs`.
- [ ] Nenhum segredo no código; segredos novos cifrados em repouso.
- [ ] Nenhuma saída de template sem `e()`; nenhuma `innerHTML` com dado sem sanitização.
