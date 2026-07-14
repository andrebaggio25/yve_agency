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
### FE-01 · Design tokens únicos + build de assets (absorve PERF-01) — `G` 🟠 · ✅ FEITO (2026-07-14)
> **Zero CDN em runtime.** Tailwind CLI (`npm run build`) gera `public/css/app.css` purgado — **60KB** contra os ~3MB que o CDN baixava e **compilava no navegador a cada page load**. Alpine, Chart.js, marked e DOMPurify self-hosted em `public/js/vendor/` (versões pinadas no `package.json`). Assets buildados são **versionados** — o hosting compartilhado não roda build no deploy; helper `asset()` faz cache-busting por mtime.
> **Design system único:** `tailwind.config.js` (paleta, fonte, sombras) + `resources/css/app.css` (`@layer components`: `.card`, `.card-solid`, `.btn-primary/secondary/danger`, `.input-field`, `.label-field`, `.badge`). Os 5 layouts tinham `<style>` próprios e **`.card`/`.btn-primary` divergentes entre painel e portal** — agora é um arquivo só.
> **Acento tokenizado como var CSS `--accent`:** o painel da plataforma vira vermelho só com `data-theme="admin"` no `<body>`, com o mesmo CSS. Isso entrega de graça a base do **white-label por agência (PROD-06)**.
> Travado por `NoRuntimeCdnTest` (nenhuma view pode voltar a usar CDN; assets buildados têm de estar commitados). Purge auditado: nenhuma classe é montada por concatenação, então nada some do build.

### FE-02 · Extrair JS inline das views gigantes — `G` 🟠 · ✅ FEITO (2026-07-14)
> Três módulos em `public/js/`: **`content-editor.js`** (289 l., de `content/show.php` — que caiu de **1.183 → 908** linhas), **`drive-manager.js`** (527 l., de `portal/files.php` — **566 → 280**) e **`approvals.js`** (100 l., de `approvals/show.php` — **423 → 327**). Os valores que vinham do PHP (id do plano, nome do cliente) entram por **`data-*`** no container; **zero PHP dentro de JS**. O JS agora é cacheável pelo navegador, testável fora do PHP e legível.
> **Bug introduzido e corrigido no mesmo dia:** os módulos saíram com `defer`. Scripts `defer` executam na **ordem do documento**, e o Alpine está no `<head>` — ou seja, `Alpine.start()` rodava **antes** de o módulo definir `driveManager()`/`approvalShow()`/`contentShow()`, e os componentes morriam com `ReferenceError`. Em produção: não dava para criar pasta no portal, nem ver preview na aprovação. Corrigido tirando o `defer` (script clássico no body executa durante o parse, antes de qualquer `defer`) e travado por `ScriptLoadOrderTest`. Reproduzido e validado no navegador com Playwright.
> Restam `<script>` inline nas 18 views menores — não bloqueiam nada hoje (o nonce depende do Alpine CSP, ver SEC-10) e migram junto do FE-03 quando essas telas forem tocadas.

### FE-03 · Wrapper padrão de fetch (estados + erros) — `M` 🟠 · ✅ PARCIAL (2026-07-14)
> **Feito:** `public/js/api.js` — injeta `X-CSRF-Token`, valida `response.ok`, timeout (30s padrão), converte erro do servidor em `ApiError` com mensagem legível (e `isNetwork` para distinguir queda de rede de erro de regra), trata resposta não-JSON (HTML de erro 500 não vira mais crash de parse) e 419 (“sessão expirou, atualize a página”). Carregado nos 3 layouts.
> **Migrado:** todo o portal (`portal/files.php` e `portal/plan_show.php`) — que era onde os `catch {}` vazios mais doíam: a galeria ficava em branco sem dizer nada. Agora erro de carregamento mostra mensagem + botão “Tentar de novo”.
> **Falta:** as views do painel (`content/show.php`, `tasks/*`, `clients/files.php`, `settings/whatsapp.php`, …). Elas funcionam hoje; migram junto do **FE-02**, que já vai mexer nesse JS — migrar agora sem cobertura visual seria risco sem ganho.

### SEC-08 · CSRF nos endpoints de mutação do portal — `M` 🟠 · ✅ FEITO (2026-07-14)
> **Risco fechado:** `itemFeedback` e os 6 endpoints de Drive do portal mutavam estado só com o capability-token da URL. Como esse token é compartilhado por e-mail/WhatsApp e viaja na URL, qualquer página hostil podia forjar um POST e **aprovar um plano ou apagar os arquivos do cliente** em nome dele.
> **Solução (mais simples que o planejado):** o double-submit cookie era desnecessário — o portal **já tem sessão PHP anônima** (é dela que o `planApprove` tira o CSRF hoje). Bastou aplicar o `CsrfMiddleware` existente às 8 rotas, expor a `<meta name="csrf-token">` no layout do portal e deixar o `api.js` (FE-03) enviar o header. Menos código novo, mecanismo já testado.
> **Cuidado embutido:** os PUTs da sessão resumável vão para o **Google** (cross-origin) e **não** levam o header — vazar o CSRF para terceiros seria um bug de segurança. Coberto na simulação do fluxo.
> Travado por `MutationRoutesHaveCsrfTest`, que varre TODAS as rotas: qualquer rota nova de mutação sem CSRF quebra a suíte (webhooks e crons isentos, pois autenticam por HMAC/segredo próprio). Achado de brinde: **nenhuma outra rota do app estava sem CSRF**.

### ARCH-01 · Tirar SQL dos controllers — `M` 🟡 · ✅ FEITO (2026-07-14)
> **Correção do diagnóstico:** a análise dizia "única violação (Dashboard)" — **errado**. O grep encontrou SQL cru em **9 controllers**: Dashboard, Report, FinancialReport, Task, Settings, WhatsApp, Queue, Admin\Tenant e Admin\PlatformUser (este último com `PDO` como propriedade).
> **Feito:** 5 repositórios novos (`DashboardRepository`, `AgencyRepository`, `JobRepository`, `FinancialReportRepository`, `ExecutiveReportRepository`, `PlatformUserRepository`) + `TenantService` (provisionamento de tenant com admin, em transação — era regra de negócio no controller). Semântica preservada (inclusive o caso "cliente sem conta de anúncio → seção some" vs "sem métricas no período → zeros"). **`app/Controllers` não referencia mais `Database`, `PDO` nem `->prepare(`** — travado pelo teste de arquitetura `ControllerHasNoSqlTest`, que falha se alguém reintroduzir. 88 testes verdes, PHPStan 0.

### QA-03 · Testes HTTP ponta a ponta dos fluxos críticos — `G` 🟡 · ✅ FEITO (2026-07-14)
> **Banco de teste real:** `docker-compose.test.yml` (Postgres 16, porta 55432) + `composer db:test` (migrations reais). SQLite não serviria — o schema usa `JSONB`, `FILTER`, `SKIP LOCKED`, `TIMESTAMPTZ`; o teste passaria mentindo. **Guarda-corpo duplo:** `phinx.test.php` e o bootstrap **abortam se o host não for local** — teste jamais pode truncar produção (o `phinx.php` normal lê o `.env`, que aponta pro Supabase: armadilha real que encontrei ao montar isto).
> **10 testes de feature** dirigindo o app de verdade (rota → middlewares → controller → banco): sem sessão → login; sem permissão → 403; com permissão → lista; **usuário da agência A não vê nem acessa cliente da B** (listagem e IDOR direto); tenant não entra no `/admin`; portal com token inválido/desativado → 403; portal abre só o cliente dono do token; **mutação do portal sem CSRF → 419** (trava o SEC-08 por HTTP).
> **Mudança de arquitetura necessária:** os guards do `Auth` faziam `send(); exit;` — matavam o processo (e o PHPUnit). Agora lançam `HttpException`, que o `Router::handle()` converte em Response. Mesmo resultado ao usuário, fluxo íntegro e testável. `Router::handle()` (devolve a Response) separado do `dispatch()` (envia).
> **Bug latente encontrado:** `partials/nav.php` declarava função global — o segundo render no mesmo processo morria com "Cannot redeclare". Em produção não aparecia (1 request = 1 processo); guardado com `function_exists`.
> **Smoke test de navegador** (`npm run test:browser`, Playwright): abre as telas num Chromium real com a CSP real e **falha se houver erro de console ou se nenhum componente Alpine inicializar**. É o teste que teria pego sozinho os dois bugs que escaparam (CSP sem `unsafe-eval` e módulo com `defer`) — PHPUnit e PHPStan não os veem, porque o PHP responde 200 nos dois casos.

---

## Marco B — Confiabilidade (o que já existe passa a ser monitorado e validado)

- **INT-01 · Validar Evolution/WhatsApp ponta a ponta** — `M` 🟠 · ⏳ **roteiro pronto, execução depende de credenciais reais** → [OPERACAO.md §4](OPERACAO.md). 5 passos incluindo **provocar um erro** (desconectar a instância e conferir que o alerta do OBS-01 dispara). Decisão pendente: quem hospeda a instância Evolution — se ela cai, o WhatsApp para.
- **INT-02 · Rate limit de envio de WhatsApp** — `P` 🟡 · ✅ FEITO (2026-07-14) · uma automação para 20 clientes tentava os 20 envios o mais rápido possível — **exatamente o padrão que o WhatsApp pune com ban**, e o número banido é o telefone da agência, não um recurso descartável. Agora os envios se enfileiram **espaçados em 8s** por agência (~7 msg/min, ritmo humano), usando o `available_at` da fila. E-mail não é adiado. Coberto por teste.
- **INT-03 · Validar ClickUp com workspace real** — `M` 🟡 · ⏳ **roteiro pronto** → [OPERACAO.md §4](OPERACAO.md), incluindo o teste de **conflito** (mudar status nos dois lados ao mesmo tempo — não há resolução de conflito no código). Decisão de produto pendente: tarefas nativas **ou** ClickUp; investir nos dois é desperdício.
- **OBS-01 · Observabilidade mínima** — `M` 🟠 · ✅ FEITO (2026-07-14) · `/api/health` real: checa banco, **cron parado** (heartbeat gravado pelo `/queue/work`), jobs falhos e syncs congelados (>48h). Público responde só `status` (não vaza infra); detalhes exigem `QUEUE_SECRET`. `503` em `error` (dispara o monitor externo); `degraded` responde 200 de propósito — o app está servindo, e disso cuida o **alerta por e-mail** (throttle de 1/hora; alerta que repete vira ruído e é ignorado, o que dá no mesmo que não ter alerta). Configurar `alert_email` em `/admin/configuracoes`. 4 testes de feature.
- **OBS-02 · Timeline de entregas na UI** — `M` 🟡 · ✅ FEITO (2026-07-14) · `/automations/deliveries`: o que saiu, pra quem, por qual canal, com que resultado — **com o motivo do erro** quando falha (é o que transforma "falhou" em algo acionável). Antes, responder *"a cliente diz que não recebeu"* exigia consultar o banco na mão. Também é argumento de venda: automação que ninguém vê acontecer parece que não existe. Escopo por agência testado (uma agência não vê a entrega da outra).
- **INFRA-01 · Unificar as duas filas** — `M` 🟠 · ✅ FEITO (2026-07-14) · **O buraco era maior do que "confusão":** `notification_jobs` era uma fila primitiva (sem `SKIP LOCKED` — dois workers podiam mandar a mesma mensagem duas vezes) e, sobretudo, **fora do alerta do OBS-01**: a fila que mais importa ao cliente era a única não vigiada, e um lembrete de fatura que morresse não avisava ninguém. Agora envio é um job comum na fila `jobs` (`SendNotificationJob`): mesma reserva concorrente, mesmo backoff, e ao esgotar tentativas **dispara o alerta**. `notification_jobs` vira **registro de entrega** (fonte da timeline do OBS-02). `/queue/run` (cron já configurado) segue funcionando — agora processa a fila única e **resgata entregas órfãs** do legado (idempotente: não reenvia o que já foi).
  > **Migrar o worker para a VPS: DESCARTADO (2026-07-14), e o item anterior estava mal calibrado.** O argumento era latência — mas **as 11 automações são `daily`/`monthly`** (lembrete de fatura, digest, relatório mensal). Um lembrete que sai às 9h01 em vez de 9h00 não tem consequência: a unidade relevante é o dia, não o segundo. O cron HTTP da Hostinger (1–5 min) já atende com folga. Migrar traria custo operacional real (administrar servidor, mais uma peça para cair em silêncio, `.env` de produção num segundo lugar) por um ganho que ninguém percebe. **Reavaliar só se** aparecer necessidade de tempo real, ou se a Hostinger passar a matar o `/queue/work` por timeout sob volume — `bin/worker.php` já existe e a migração é rápida no dia em que fizer sentido.
- **INFRA-02 · Medir PDO persistente vs pooler Supabase** — `P` 🟡 · desligar `ATTR_PERSISTENT` se houver saturação de conexões.
- **INFRA-03 · Padronizar `insert()` com `RETURNING id`** — `P` 🟡 · ✅ FEITO (2026-07-14) · `lastInsertId()` sem nome de sequência é frágil no PG (pode devolver ID errado/vazio atrás do pooler do Supabase — e um ID errado vira registro órfão ou vínculo apontando para a linha de outro). Agora `RETURNING id`. Coberto por `RepositoryInsertTest`.
- **DATA-01 · Backup e retenção documentados** — `P` 🟡 · ✅ FEITO (2026-07-14) · [OPERACAO.md](OPERACAO.md): backup do Supabase (cobre desastre, **não** erro humano — snapshot manual antes de toda migration), retenção de `activity_logs` (12 meses) e o lembrete que importa: *backup que nunca foi testado não é backup*.
- **ADM-01 · Guard-rail no painel de migrations** — `P` 🟡 · ✅ FEITO (2026-07-14) · o rollback só tinha um `confirm()` de navegador — frontend, burlável e fácil de clicar sem ler, para a **única ação verdadeiramente destrutiva do painel** (pode apagar colunas/tabelas com dado de cliente, sem desfazer). Agora o **servidor** exige a palavra `REVERTER` digitada; tentativa sem confirmação é bloqueada e registrada em `activity_logs`.
- **SEC-10 · CSP estrita** — `G` 🟡 · ⚠️ **PARCIAL — e um erro meu, registrado (2026-07-14)**
  - **Entregue:** com o self-host do FE-01, `script-src` não tem mais **nenhuma origem externa** (`'self'` apenas). Ganho real: CDN comprometido não executa nada aqui.
  - **Regressão que eu introduzi e corrigi:** ao endurecer a CSP, removi `'unsafe-eval'`. **O Alpine.js compila `x-data`/`@click`/`x-text` com `new AsyncFunction()` — sem `unsafe-eval` ele morre e a UI inteira (menus, modais, upload) para**, com `EvalError` no console. Descoberto ao testar a CSP real num navegador (Playwright), não por leitura de código. `'unsafe-eval'` foi restaurado e agora é **exigido** pelo `ContentSecurityPolicyTest` — com o porquê no teste, para ninguém "melhorar" a CSP de novo e quebrar tudo.
  - **Para fechar de verdade** (fora do escopo deste ciclo): migrar para o build **`@alpinejs/csp`**, que não usa `eval` — mas proíbe expressão inline (`@click="n++"` vira `@click="increment()"`), exigindo reescrever as expressões de **todas** as views. Só então caem `unsafe-eval` e `unsafe-inline` (com nonce). É trabalho de ciclo próprio, com ganho de segurança moderado — decidir na próxima análise.

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
- **ARCH-03 · Extrair `PortalDriveController`** — `P` 🟡 · ✅ FEITO (2026-07-14) · `PortalController` 750→277 linhas (dashboard, planos, feedback, faturas, contratos); o CRUD de Drive + os dois caminhos de upload viraram `PortalDriveController` (574 l., coeso). As 10 rotas do portal foram remapeadas e um teste novo (`RoutesResolveTest`) valida que **toda rota do app aponta para um handler existente** — extração/rename de controller que esqueça a rota agora quebra a suíte.

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
| **1** ✅ | Dor nº 1 + base do frontend | ~~UP-01 · ARCH-01 · ARCH-03~~ **concluído 2026-07-14** |
| **2** ✅ | Frontend vira produto | ~~FE-01 · SEC-10 (parcial) · FE-03 (parcial) · SEC-08~~ **concluído 2026-07-14** |
| **3** ✅ | JS sustentável + rede de segurança | ~~FE-02 · QA-03~~ **concluído 2026-07-14** |
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
