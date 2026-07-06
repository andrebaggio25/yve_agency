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

## Marco 1 — Hardening

**SEC-03 — CSP + HSTS** · `M` · `app/Core/Response.php:99-104`. Adicionar `Content-Security-Policy` (allowlist dos CDNs ou self-host) e `Strict-Transport-Security`. Coordenar com PERF-01/02 para permitir CSP estrita.

**SEC-05 — cifrar credenciais globais** · `M` · `PlatformSettingsRepository` + `GlobalSettingsController`. Cifrar `mail_password`, `meta_app_secret`, `openai_api_key`, `anthropic_api_key`, `evolution_api_key` com `Secret`; migration para cifrar valores existentes.

**SEC-06 — CSRF uniforme** · `M` · `routes/web.php` (portal drive + `itemFeedback`) e `/api/comentarios/*` mudam estado sem CSRF. Aplicar CSRF/`X-CSRF-Token`. Corrigir `except` do `CsrfMiddleware` (`/webhooks/` não bate com a rota real `/webhook/`).

**SEC-04 — sanitizar Markdown IA + pin CDN** · `P` · `resources/views/ia/show.php:80`. DOMPurify após `marked.parse` (ou texto); fixar versão do `marked` com SRI.

**SEC-07 — posse de entidade nos comentários** · `P` · `InternalCommentService::add`. Validar que `entity_id` pertence à agência antes de gravar.

**SCHEMA-01 — FKs faltantes Drive/ClickUp** · `M` · nova migration. `drive_files/drive_folders/google_drive_integrations/clickup_*` têm `agency_id`/`client_id` sem FK. Adicionar FKs `ON DELETE CASCADE`; limpar órfãos antes.

## Marco 2 — Qualidade

**QA-01 — zerar PHPStan nível 6** · `M` · `Core/Response.php`+`Controller.php` (`new static()`), `Core/View.php`, `Container.php`, `Request.php`. 22 erros hoje (cosméticos).

**QA-02 — testes de autorização/multi-tenancy** · `G` · `tests/Feature/`. Positivo+negativo por módulo: sem permissão → 403; agência A não lê B; `client_ids` limita visão; portal token inválido/desabilitado.

**PERF-01 — build de assets** · `M` · sair do `cdn.tailwindcss.com`; Tailwind CLI purgado em `public/css`; self-host Alpine/Chart com SRI.

**PERF-02 — extrair JS inline** · `M` · `content/show.php` (1130 l.), `portal/files.php` (566 l.). Mover para `public/js/`; wrapper `fetch` com `response.ok`+loading+erro.

**DRIVE-01 — preview de imagem do Drive na aprovação** · `M` · `portal/plan_show.php`, `content/show.php:719-724`. As imagens usam `drive.google.com/uc?export=view&id=` (`driveImageUrl()`), endpoint que o Google descontinuou para `<img>` → preview em branco. Corrigir: imagens enviadas pelo app → servir pelo proxy próprio (`/portal/{token}/drive/file/{id}/raw`); links colados → `thumbnail?id=ID&sz=w1000` + fallback iframe `/preview`; renderizar imagem do Drive inline (hoje só vira link). Lembrar: escopo `drive.file` só lê o que o app criou.

**DRIVE-02 — sincronizar Drive→sistema** · `G` · metadados em `drive_files`/`drive_folders` não refletem mudanças feitas direto no Drive. **Restrição chave:** escopo `drive.file` só vê arquivos criados pelo app (adição manual no Drive é invisível — exigiria `drive.readonly` + verificação Google). Solução faseada: reconciliação sob demanda + cron via `files.list`/`changes.list` (delta com `startPageToken`); marcar ausente como "removido" na listagem; `changes.watch` só se precisar tempo real. Ver DRIVE-02 no `PLANO_MESTRE.md`.

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
