---
name: yve-seguranca
description: Use ao revisar, corrigir ou adicionar código no YVE Agency sob a ótica de segurança, e como checklist obrigatório antes de qualquer merge que vá para produção paga. Cobre as invariantes específicas deste app — escopo agency_id, RBAC no backend, CSRF, escape de saída, cifra de segredos, confiança em headers, isolamento de portal. Aciona em "revise a segurança disso", "isso está seguro?", "pode ir pra produção?", "auditar antes do merge", ou ao tocar em auth, upload, integração ou query.
---

# Segurança do YVE Agency (invariantes + checklist)

Este app é multi-tenant e vai ser comercializado. O modelo de ameaça inclui um usuário de uma agência tentando ver dados de outra, um cliente do portal abusando do token, e input hostil em toda superfície. A base é boa (ver `yve-arquitetura`), mas há itens abertos rastreados em `yve-roadmap`. Use a régua: **blocking** = corrigir antes do merge · **important** = risco real · **polish** = robustez.

## As 8 invariantes (quebrar qualquer uma é blocking)

1. **Isolamento por `agency_id`.** Toda query multi-tenant filtra por agência (escopo automático do `Repository` ou `$agencyId` explícito). Nunca `SELECT ... WHERE id = :id` sem a agência numa tabela de tenant. Teste: um usuário de A não pode ler/editar recurso de B.
2. **RBAC no backend.** `Auth::requirePermission('modulo.acao')` no início do método do controller. Esconder o botão na view **não** é proteção. Rota de cliente exige `ClientAccessMiddleware`/`Auth::requireClientAccess`.
3. **Prepared statements sempre.** PDO com `bind`. Zero interpolação de input em SQL. Identificador dinâmico só por allowlist explícita.
4. **Escape na saída.** `e()` (= `htmlspecialchars(ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8')`) em todo dado em HTML/atributo. Em `<script>`, `json_encode` com `JSON_HEX_TAG|JSON_HEX_AMP`. **Nunca** `innerHTML` com dado sem sanitizar (DOMPurify) — há um caso aberto em `ia/show.php` (SEC-04).
5. **CSRF em toda mutação.** POST/PUT/DELETE levam `CsrfMiddleware`. Tokens comparados com `hash_equals`. APIs JSON autenticadas por sessão exigem `X-CSRF-Token`. Pendência conhecida: mutações do **portal** ainda dependem só do token da URL (SEC-08) — toda rota de mutação nova no portal deve nascer com double-submit.
6. **Segredos cifrados.** Token de integração por agência → `Core\Secret::encrypt`; chaves globais de `platform_settings` cifradas de forma transparente (SEC-05 ✅). Nada de credencial hardcoded. `.env`/`.env.production` nunca versionados (só `.env.example`).
7. **Não confie em headers de cliente.** `Host`, `X-Forwarded-For`, `Referer`, `User-Agent` são forjáveis. `Request::ip()` já usa `REMOTE_ADDR` e só honra `X-Forwarded-For` vindo de `TRUSTED_PROXIES` (SEC-02 ✅) — não regrida isso.
8. **Erros não vazam.** Produção com `APP_ENV=production`, `display_errors=0`, `log_errors=1`. Stack trace só no log, nunca na resposta (SEC-01 ✅).

## Checklist de merge (rode antes de aprovar/pushar)

```
[ ] Query nova filtra por agency_id (ou usa findByIdAndAgency)?
[ ] Controller novo tem Auth::requirePermission no topo de cada método?
[ ] Rota de cliente tem ClientAccessMiddleware?
[ ] POST/PUT/DELETE têm CsrfMiddleware (ou X-CSRF-Token em API)?
[ ] Toda saída de dado do usuário passa por e()? Nenhum innerHTML cru?
[ ] Segredo novo cifrado com Secret e fora do git?
[ ] Ação sensível grava activity_logs?
[ ] Upload: valida MIME por conteúdo (finfo), tamanho, is_uploaded_file; não executa o arquivo?
[ ] Nada de X-Forwarded-For/Host em decisão de segurança sem proxy confiável?
[ ] composer test verde · composer analyse sem erro novo · composer audit limpo?
```

## Padrões corretos já no código (imite-os)

- **Auth:** `password_hash(PASSWORD_ARGON2ID)`, `session_regenerate_id(true)` no login, cookie `HttpOnly`/`Secure`/`SameSite=Lax`, `session.use_strict_mode=1` (ver `public/index.php` e `Support/Auth.php`).
- **Webhook:** valide origem — HMAC-SHA256 com `hash_equals` (ver `ClickUpWebhookController`) ou token + nome de instância (`WebhookController`).
- **Cron/Queue:** protegido por `QUEUE_SECRET` comparado com `hash_equals` (`QueueController::guard`).
- **Drive privado:** arquivos nunca públicos por padrão; servidos por proxy autenticado com streaming (ver `GoogleDriveApiService::streamResponse` + `PortalController::driveFileRaw`).

## Uploads (portal e Drive)

Ao mexer em upload: confirme `is_uploaded_file($tmp)`, limite de tamanho (`maxUploadBytes`), e que o arquivo vai para o Drive (não é executável no servidor). Não confie em `$file['type']` (vem do browser) para decisão de segurança — valide por conteúdo se for gate.

## Antes de dizer "pode ir pra produção"

Os bloqueadores do ciclo 1 (Marcos 0 e 1) estão **concluídos** — o hardening base existe. Pendências de segurança vigentes (ver `docs/PLANO_MESTRE.md`): **SEC-08** (CSRF do portal), **SEC-10** (CSP estrita, destravada por FE-01) e AUTH-01 (2FA, pós-MVP). Nenhuma delas re-bloqueia o go-live, mas rota nova de portal sem CSRF ou CDN novo sem SRI **é regressão — não aprove**. Para revisão profunda de PHP/JS, acione a skill global `php-js-review`; para supply chain e segredos vazados, `dependency-audit`.
