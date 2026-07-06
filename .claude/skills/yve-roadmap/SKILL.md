---
name: yve-roadmap
description: Use ao pegar qualquer item de correção, hardening ou dívida técnica do YVE Agency para preparação de comercialização — dá a um agente o contexto completo (arquivo, causa, correção, critério de pronto) de cada achado da auditoria. Aciona em "corrija o SEC-02", "resolva o bug de notificação", "vamos fazer o hardening de produção", "o que falta pra comercializar", "pega o próximo item do roadmap".
---

# Roadmap de correções do YVE Agency (para execução por agentes)

Este é o backlog operacional derivado da auditoria. Fonte canônica e detalhada: `docs/ANALISE_SISTEMA.md` e `docs/PLANO_MESTRE.md`. Ao pegar um item, carregue também `yve-arquitetura` e `yve-seguranca`. Sempre feche com `composer test` + `composer analyse` + `composer audit`.

## Como trabalhar um item
1. Leia a entrada abaixo (causa + arquivos + correção + pronto-quando).
2. Faça a menor mudança que resolve, no padrão do código existente.
3. Adicione/atualize teste (positivo e negativo se for permissão).
4. Rode os gates. Não introduza erro novo no PHPStan.
5. Se mexeu em tela, valide com a skill `visual-validation`.

## Marco 0 — Bloqueadores (nada vai pra produção paga com estes abertos)

**SEC-01 — `.env.production` em modo development** · `P`
`.env.production` tem `APP_ENV=development` → erros/traces vazam. `public/index.php` liga `display_errors` em dev; `Router::handleError` inclui trace em dev.
→ Setar `APP_ENV=production`; confirmar `display_errors=0`/`log_errors=1`. **Pronto:** erro 500 em prod mostra só `errors/500`, detalhe no log.

**SEC-02 — rate limit burlável por `X-Forwarded-For`** · `M`
`app/Core/Request.php:141` confia no header; `RateLimitMiddleware` chaveia por IP → brute-force contorna variando o header.
→ `ip()` usa `REMOTE_ADDR`; honrar `X-Forwarded-For` só se vier de `TRUSTED_PROXIES`. Login `maxAttempts=5/60s`. `flock` na escrita. **Pronto:** 20 logins falhos variando o header bloqueiam em 5.

**DEP-01 — CVEs guzzle/psr7** · `P`
`composer audit`: 3 advisories médios.
→ `composer update guzzlehttp/guzzle guzzlehttp/psr7`. **Pronto:** `composer audit` limpo, 38 testes verdes.

**BUG-01 — notificação in-app sem `action_url`** · `P`
`app/Services/NotificationService.php:89`: `compact()` com nomes snake_case, mas variáveis são camelCase → `action_url` nunca salvo.
→ Montar array explícito (`'action_url' => $actionUrl`, `'agency_id' => $agencyId`, …). **Pronto:** clicar na notificação leva ao destino; PHPStan sem "undefined variable".

## Marco 1 — Hardening (✅ concluído em 2026-07-06)

**SEC-03 — CSP + HSTS** · ✅ FEITO · `Response::send()` envia `Content-Security-Policy` (allowlist dos CDNs; ainda com `unsafe-inline`/`unsafe-eval` por causa do Tailwind/Alpine CDN) e `Strict-Transport-Security` sob HTTPS. Endurecer para nonce após self-host (PERF-01). Validar no navegador com `visual-validation`.

**SEC-05 — cifrar credenciais globais** · ✅ FEITO · `PlatformSettingsRepository` cifra/decifra transparente as chaves sensíveis via `Secret`; migration `20260706000024` cifra valores existentes (idempotente).

**SEC-06 — CSRF uniforme** · ✅ PARCIAL · `/api/comentarios` POST agora exige `X-CSRF-Token` (gap real de cookie de sessão); `CsrfMiddleware::except` esvaziado. **Follow-up:** endpoints do portal (`itemFeedback`, `drive/*`) seguem só com o token da URL — CSRF no portal fica pendente.

**SEC-04 — sanitizar Markdown IA** · ✅ FEITO · `ia/show.php` usa DOMPurify após `marked.parse`; versões dos CDNs fixadas; `json_encode` com flags `JSON_HEX_*`. (SRI ainda não — precisa dos hashes.)

**SEC-07 — posse de entidade nos comentários** · ✅ FEITO · `InternalCommentRepository::entityBelongsToAgency()` valida tenant antes de ler/gravar comentário.

**SCHEMA-01 — FKs faltantes Drive/ClickUp** · ✅ FEITO · migration `20260706000025` limpa órfãos e adiciona FKs `ON DELETE CASCADE` (drive_folders/drive_files agency+client, google_drive_integrations e clickup_integrations agency). Rodar `composer migrate` em produção.

## Marco 2 — Qualidade

**QA-01 — zerar PHPStan nível 6** · ✅ FEITO · `Container`/`Request`/`Response` `final`; `View::render` via acessores tipados; bug real `Response::withError()` no `ReportController` corrigido; código morto removido; `phpstan.neon` migrado. `composer analyse` = [OK].

**QA-02 — testes de autorização/multi-tenancy** · ✅ FEITO · `MiddlewareAuthorizationTest` (Auth/Permission/ClientAccess/PlatformAdmin, positivo+negativo) + `RepositoryScopeTest` (`agencyScope` por tenant / `1=1` platform admin / throw sem agência). 62 testes verdes. Follow-up: HTTP ponta a ponta exige schema em banco de teste (migrations são PG-only).

**PERF-01 — build de assets** · `M` · sair do `cdn.tailwindcss.com`; Tailwind CLI purgado em `public/css`; self-host Alpine/Chart com SRI.

**PERF-02 — extrair JS inline** · `M` · `content/show.php` (1130 l.), `portal/files.php` (566 l.). Mover para `public/js/`; wrapper `fetch` com `response.ok`+loading+erro.

**DRIVE-01 — preview de imagem do Drive na aprovação** · ✅ FEITO · `GoogleDriveService::imageSrc()` (estático) converte link do Drive → `thumbnail?id=ID&sz=w1600`; aplicado em `portal/plan_show.php` e no JS `driveImageUrl()` de `content/show.php`. Teste: `DriveImageSrcTest`. Requisito: arquivo compartilhado "qualquer um com o link".

**DRIVE-02 — sincronizar Drive→sistema** · ✅ FASE 1 FEITA · `DriveSyncService` (reconciliação recursiva) + `GoogleDriveApiService::listFolder()` + botão na galeria (`/clientes/{id}/conteudos/sync`) + cron `/queue/sync-drive`. Reflete delete/rename/move de arquivos criados pelo app. **Fase 2 pendente (decisão de produto):** detectar adição manual no Drive exige escopo `drive.readonly` + verificação Google. Opcional real-time: `changes.watch`.

## Marco 3 — Robustez

- **INFRA-01** worker resiliente (supervisord) + unificar filas `jobs`/`notification_jobs`.
- **INFRA-02** reavaliar `ATTR_PERSISTENT` do PDO com o pooler do Supabase.
- **INFRA-03** padronizar `insert()` em `RETURNING id`.
- **OBS-01** observabilidade: alertas de job falho, `/health`.
- **DATA-01** backup/retenção documentados.

## Marco 4 — Produto (pós go-live)
Continuidade das fases parciais do `PLANO.md`: comunicação (Fase 3), IA de tráfego + ações assistidas com guardrails (7–8), criativos com IA (11), SaaS self-service + billing real + limites centralizados (12), unificação de idioma de rotas.

## Sequência
Sprint 1 = Marco 0 · Sprint 2 = Marco 1 · Sprint 3 = Marco 2 · Sprint 4 = Marco 3 · Sprint 5+ = Marco 4.
**Go-live pago = fim do Sprint 2.**
