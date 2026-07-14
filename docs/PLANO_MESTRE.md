# YVE Agency — Plano Mestre v2

> **A verdade absoluta do projeto.** Roteiro único e priorizado de correções, melhorias e evolução.
> Base: [ANALISE_PRODUTO.md](ANALISE_PRODUTO.md) (SWOT + notas) e [ANALISE_SISTEMA.md](ANALISE_SISTEMA.md) (fotografia técnica).
> Atualizado: 2026-07-14 · Ciclo: 2026-07 (ciclo 2) · Anterior: [historico/PLANO_MESTRE_2026-07-06.md](historico/PLANO_MESTRE_2026-07-06.md)
> Convenções: esforço **P** ≤2h · **M** = meio dia a 1 dia · **G** = vários dias. Ao fechar este roadmap, arquivar em `docs/historico/` e gerar o próximo com a skill `yve-analise-produto`.

**Estado herdado do ciclo 1 (tudo ✅):** Marco 0 (SEC-01/02, DEP-01, BUG-01) · Marco 1 (SEC-03/04/05/06*/07, SCHEMA-01) · QA-01, QA-02, DRIVE-01, DRIVE-02 fase 1. Gates em 2026-07-14: 77 testes verdes, PHPStan nível 6 = 0 erros, audit limpo. (*SEC-06 parcial — portal virou SEC-08 abaixo.)

---

## Marco A — Fechar o MVP (o que um cliente pagante sente)

### UP-01 · Upload > 256MB: direto browser→Drive (resumável) — `G` 🔴 · ✅ **FEITO E VALIDADO EM PRODUÇÃO (2026-07-14)**
> **O teto de 256MB acabou.** `initiateResumable()` vincula a sessão à origem do app (header `Origin` ← `APP_URL`); endpoints `POST /portal/{token}/drive/upload/session` (devolve a session URI) e `/upload/complete` (valida no Drive que o arquivo está na pasta do cliente via `metaHasParent` antes de registrar — não confia no ID do navegador; idempotente por `drive_file_id`). JS envia chunks de 16MB com `Content-Range` direto à session URI: progresso, ETA, retomada (probe 308) e cancelamento. Relay PHP vira fallback (única via onde `maxBytes` ainda vale). Testes: `DriveDirectUploadTest`.
> **Dois bugs achados no teste em produção, ambos corrigidos:** (a) a CSP do Marco 1 tinha `connect-src 'self'` e o navegador bloqueava os PUTs pro Google — upload ficava mudo em 0% (regressão travada por `ContentSecurityPolicyTest`); (b) nenhuma etapa tinha timeout, então uma falha pendurava a barra em silêncio — agora toda etapa tem timeout e o erro mostra o passo (`[sessao:timeout]`, `[put:HTTP403]`…).
> **Extras entregues junto:** upload liberado para **qualquer tipo de arquivo** (com o proxy `/raw` forçando download de conteúdo não-passivo — anti-XSS, `inlineSafeMime()`); mitigações de iPhone/iCloud (wake lock, pré-leitura do arquivo, aviso ao sair, dica contextual em iOS). Limite remanescente é do **seletor de fotos do iOS** (etapa da Apple, fora do alcance da web) — orientação no produto.
- **Problema:** todo upload passa pelo relay PHP; a Hostinger compartilhada trava `upload_max_filesize`/`post_max_size` em 256M acima do `.user.ini`. Vídeos de cliente maiores que isso falham. Não é limite do Google (resumável aceita 5TB) nem exige migrar de hosting.
- **Arquivos:** `app/Services/GoogleDriveApiService.php` (`initiateResumable` — adicionar header `Origin` = `APP_URL` na iniciação), `app/Controllers/PortalController.php` (novo endpoint JSON `driveUploadSession` que devolve a session URI + registro pós-upload dos metadados), `resources/views/portal/files.php` (JS: PUT em chunks de 8–32MB com `Content-Range`, progresso, retomada em 308), `routes/web.php`.
- **Ação:** (1) iniciar sessão resumável server-side com `Origin`; (2) JS envia chunks direto à session URI (CORS liberado pelo Google para a origem registrada); (3) ao receber 200/201 do último chunk, POST leve ao servidor confirma e grava `drive_files` (validar que o `fileId` está na pasta esperada); (4) manter relay como fallback < 200MB; (5) subir o aviso de limite da UI só quando o fallback for usado.
- **Pronto quando:** vídeo de 1,5GB sobe pelo portal na Hostinger com barra de progresso, aparece na galeria e no Drive; queda de conexão no meio retoma; relay continua funcionando para arquivo pequeno.

### FE-01 · Design tokens únicos + build de assets (absorve PERF-01) — `G` 🟠
- **Problema:** Tailwind via CDN em produção (recompila no browser, trava CSP em `unsafe-inline/eval`); `tailwind.config` + `<style>` duplicados nos 4 layouts; Alpine sem pin em `app`/`admin`; nada de SRI.
- **Ação:** Tailwind CLI com um `tailwind.config.js` único (cores da marca, gray-925/950, fonte) gerando `public/css/app.css` purgado; extrair o CSS custom dos layouts para camada `@layer components` (`.card`, `.btn-primary`, `.input-field`, …); self-host Alpine (pin) e Chart.js com SRI; os 4 layouts passam a incluir os mesmos assets locais.
- **Pronto quando:** zero `<script src="https://cdn...">` em runtime; trocar a cor de acento = editar 1 arquivo; CSP sem `unsafe-eval` (SEC-10 na sequência); Lighthouse sem flash de estilo.

### FE-02 · Extrair JS inline das views gigantes — `G` 🟠
- **Arquivos:** `resources/views/content/show.php` (1.183 l.), `portal/files.php` (566 l.), `approvals/show.php` (423 l.).
- **Ação:** mover para módulos em `public/js/` (ex.: `content-editor.js`, `drive-manager.js`); dados do PHP entram por `data-*`/`json_encode` com flags `JSON_HEX_*`; view fica < 400 linhas.
- **Pronto quando:** as três views só têm markup + include do módulo; nenhuma regressão nos fluxos (validar com `visual-validation`).

### FE-03 · Wrapper padrão de fetch (estados + erros) — `M` 🟠
- **Ação:** `public/js/api.js` único: injeta `X-CSRF-Token`, checa `response.ok`, timeout, e devolve erro tipado; padrão de UI para loading/vazio/erro/sucesso documentado na skill `yve-frontend`. Migrar os `fetch` existentes.
- **Pronto quando:** nenhum `fetch()` cru nas views; erro de rede mostra feedback visível (não `catch {}` silencioso).

### SEC-08 · CSRF nos endpoints de mutação do portal — `M` 🟠
- **Problema:** `itemFeedback` e `/portal/{token}/drive/*` mutam estado só com o token da URL (follow-up do SEC-06).
- **Ação:** double-submit cookie no portal (cookie + header `X-Portal-CSRF` verificados com `hash_equals`); aproveitar e mover mutações de "GET-like POST" para o wrapper FE-03.
- **Pronto quando:** POST cross-site forjado contra o portal falha; fluxos de aprovação e upload seguem funcionando.

### ARCH-01 · Tirar SQL dos controllers — `M` 🟡 · ✅ FEITO (2026-07-14)
> **Correção do diagnóstico:** a análise dizia "única violação (Dashboard)" — **errado**. O grep encontrou SQL cru em **9 controllers**: Dashboard, Report, FinancialReport, Task, Settings, WhatsApp, Queue, Admin\Tenant e Admin\PlatformUser (este último com `PDO` como propriedade).
> **Feito:** 5 repositórios novos (`DashboardRepository`, `AgencyRepository`, `JobRepository`, `FinancialReportRepository`, `ExecutiveReportRepository`, `PlatformUserRepository`) + `TenantService` (provisionamento de tenant com admin, em transação — era regra de negócio no controller). Semântica preservada (inclusive o caso "cliente sem conta de anúncio → seção some" vs "sem métricas no período → zeros"). **`app/Controllers` não referencia mais `Database`, `PDO` nem `->prepare(`** — travado pelo teste de arquitetura `ControllerHasNoSqlTest`, que falha se alguém reintroduzir. 88 testes verdes, PHPStan 0.

### QA-03 · Testes HTTP ponta a ponta dos fluxos críticos — `G` 🟡
- **Ação:** banco PG de teste (schema via Phinx) + 5 testes: login+RBAC, aprovação pelo portal, upload (mock do Drive), criação de fatura, isolamento multi-tenant via HTTP.
- **Pronto quando:** `composer test` cobre os cinco; CI-able.

---

## Marco B — Confiabilidade (o que já existe passa a ser monitorado e validado)

- **INT-01 · Validar Evolution/WhatsApp ponta a ponta** — `M` 🟠 · staging com instância real: conectar QR, enviar automação, receber webhook; documentar operação da instância (quem hospeda, como reinicia) em `docs/EVOLUTION_API_INTEGRACAO.md`. **Pronto:** roteiro da skill `yve-analise-produto` §validação-de-integração executado e anexado.
- **INT-02 · Rate limit de envio de WhatsApp por instância** — `P` 🟡 · evitar ban de número (fila já permite espaçar).
- **INT-03 · Validar ClickUp com workspace real** — `M` 🟡 · sync bidirecional + webhook + conflito de edição; decidir papel (tarefas nativas vs ClickUp-first) e registrar a decisão aqui.
- **OBS-01 · Observabilidade mínima** — `M` 🟠 · endpoint `/health` (DB, fila, último cron); alerta (e-mail ao admin) quando job falha `max_attempts` ou sync de conta Meta/orgânico falha 3× seguidas.
- **OBS-02 · Timeline de automações/entregas na UI** — `M` 🟡 · o que foi enviado, pra quem, por qual canal, com que resultado.
- **INFRA-01 · Worker resiliente + unificar filas** — `M` 🟠 · mover `bin/worker.php` para a VPS (supervisord) mantendo cron HTTP como fallback; fundir `notification_jobs` na tabela `jobs`.
- **INFRA-02 · Medir PDO persistente vs pooler Supabase** — `P` 🟡 · desligar `ATTR_PERSISTENT` se houver saturação de conexões.
- **INFRA-03 · Padronizar `insert()` com `RETURNING id`** — `P` 🟡.
- **DATA-01 · Backup e retenção documentados** — `P` 🟡 · política do Supabase + retenção de `activity_logs`.
- **ADM-01 · Guard-rail no painel de migrations** — `P` 🟡 · aviso forte + registrar dump/backup antes de `rollback` em produção.
- **SEC-10 · CSP estrita (nonce, sem unsafe-\*)** — `M` 🟡 · destravado por FE-01/FE-02.

---

## Marco C — Escala comercial

- **PROD-01 · Billing SaaS real** — `G` 🔴 (para escalar) · gateway de assinatura (avaliar Stripe vs Mercado Pago), trial 14 dias, dunning, tela de upgrade; **ARCH-04** junto: centralizar `checkLimit` num gate único (hoje controller a controller).
- **PROD-01a · Recebimento de faturas de clientes (PIX/boleto)** — `G` 🟠 · Asaas/Mercado Pago na fatura do módulo financeiro; recibo automático.
- **PROD-08 · Dashboard acionável ("meu dia")** — `M` 🟠 · aprovações paradas, faturas vencendo, tarefas atrasadas, syncs quebrados (depende de ARCH-01).
- **PROD-03 · Hub 360° do cliente** — `M` 🟡 · abas conteúdo/financeiro/tráfego/arquivos na tela do cliente.
- **PROD-06 · White-label do portal** — `M` 🟡 · logo + cor por agência (viável após FE-01 tokenizar).
- **UX-04 · PDF real (dompdf) para fatura/contrato/relatório** — `M` 🟡.
- **AUTH-01 · 2FA TOTP** — `M` 🟡.
- **ARCH-02 · Unificar rotas pt/en por mapa de aliases** — `M` 🟡 · corta `routes/web.php` pela metade.
- **ARCH-03 · Extrair `PortalDriveController`** — `P` 🟡 · natural durante UP-01.

---

## Marco D — Diferenciação (pós-escala)

- **PROD-02 · IA→Ação com guardrails** — `G` · recomendação gera `ads_action` pré-preenchida; `ai_safety_rules` verificadas **em código** antes de executar na Meta. (Fases 7–8 do [PLANO_FASES.md](PLANO_FASES.md).)
- **AI-01 · Metering de IA por agência** — `M` · tokens/custo por tenant, insumo de precificação.
- **PROD-04 · Calendário de conteúdo** — `M` · visão mês dos itens de plano.
- **PROD-05 · Duplicar plano/itens** — `P`.
- **PROD-07 · Vincular post orgânico ↔ item de plano** — `G` · fecha o ciclo planejou→postou→performou.
- **UX-02/03/05/06/07** — exclusão informativa, convite por e-mail, drag-and-drop no kanban, preferências de notificação, seletor de período.
- **DRIVE-03 · Sync fase 2 (adições manuais no Drive)** — `G` · exige escopo `drive.readonly` + verificação Google — decisão de produto pendente.

---

## Sequenciamento sugerido

| Sprint | Foco | Itens |
|--------|------|-------|
| **1** | Dor nº 1 + base do frontend | UP-01 · ARCH-01 · ARCH-03 |
| **2** | Frontend vira produto | FE-01 · FE-03 · SEC-08 |
| **3** | JS sustentável + rede de segurança | FE-02 · QA-03 · SEC-10 |
| **4** | Confiabilidade | INT-01/02/03 · OBS-01/02 · INFRA-01/02/03 · DATA-01 · ADM-01 |
| **5+** | Escala comercial | PROD-01(a) · PROD-08 · PROD-03 · PROD-06 · UX-04 · AUTH-01 · ARCH-02 |
| **6+** | Diferenciação | Marco D |

**Marco de "MVP fechado" = fim do Sprint 3.** A partir daí o produto aguenta demo de venda e uso diário sem ressalva técnica.

---

## Definição de pronto global (por item)

- [ ] Migrations reversíveis aplicam e revertem sem erro.
- [ ] Rota nova: auth + permissão + (se de cliente) `ClientAccessMiddleware` + CSRF em mutação — nos pares pt **e** en.
- [ ] Regra nova tem teste (permissão: positivo **e** negativo).
- [ ] `composer test` + `composer analyse` verdes; `composer audit` limpo.
- [ ] Ação sensível grava `activity_logs`; segredo novo cifrado.
- [ ] Saída de template com `e()`; `innerHTML` só com sanitização; `fetch` via wrapper (pós FE-03).
- [ ] Tela nova/alterada: tokens do design system (nada hardcoded), 4 estados (loading/vazio/erro/sucesso), validada com `visual-validation`.
- [ ] Item concluído: marcar ✅ **neste arquivo** com data e uma linha de como foi resolvido.
