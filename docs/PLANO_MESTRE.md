# YVE Agency — Plano Mestre v3

> **A verdade absoluta do projeto.** Roteiro único e priorizado de correções, melhorias e evolução.
> Base: [ANALISE_PRODUTO.md](ANALISE_PRODUTO.md) (ciclo 3) · [ANALISE_SISTEMA.md](ANALISE_SISTEMA.md) · [AUTOMACOES.md](AUTOMACOES.md).
> Atualizado: 2026-07-23 · Ciclo: 2026-07 (ciclo 3) · Anterior: [historico/PLANO_MESTRE_2026-07-14.md](historico/PLANO_MESTRE_2026-07-14.md)
> Convenções: esforço **P** ≤2h · **M** = meio dia a 1 dia · **G** = vários dias. IDs nunca são reutilizados. Ao fechar este roadmap, arquivar em `docs/historico/` e rodar a skill `yve-analise-produto`.

**Estado herdado do ciclo 2 (fechado em 23/07 — tudo ✅ salvo o anotado):** Sprints 1–4 completos: UP-01 (upload direto validado em produção) · FE-01/02 · FE-03 (parcial: portal ✅, painel oportunista) · SEC-08 · ARCH-01/03 · QA-03 · INT-02 · OBS-01/02 · INFRA-01/03 · DATA-01 · ADM-01 · SEC-10 (parcial — ver Marco B) · PROD-04/05/06/08 · UX-02/04 · upload de logotipo · ciclo Planificações Semanais (CONT-00…05, RADAR, PORTAL p1). **Pendências herdadas:** INT-01, INT-03, INFRA-02, CONT-AVISOS, CONT-PORTAL p2.

**Gates em 2026-07-23:** 188 testes verdes (banco PG real + smoke Chromium) · PHPStan nível 6 = 0 · audit limpo (dompdf/guzzle atualizados 23/07) · sem migration pendente.

**Decisões de negócio registradas (2026-07-23):**
1. **Billing SaaS é manual** — o dono cobra os tenants por fora; PROD-01 (gateway/trial/dunning) sai do caminho crítico. Reavaliar quando o volume de tenants doer.
2. **Decisões em aberto que bloqueiam itens:** quem hospeda a Evolution + credenciais (→ INT-01); tarefas nativas × ClickUp (→ INT-03); provedor de recebimento Asaas × Mercado Pago (→ PROD-01a).

---

## Marco A — Fluxos redondos (o dia a dia da agência sem fricção)

### CONT-06 · Seletor de mídia do Drive no modal do post — `G` 🟠
> **Problema:** montar um post ainda é colar URLs do Drive na mão — capa, cada foto do carrossel, vídeo. É o último elo manual da esteira de conteúdo e a origem dos bugs recentes de preview (link de pasta vs arquivo, ordem errada, link não público).
> **Ação:** no modal do post (`content/show.php` + `content-editor.js`), botão "Escolher do Drive" que lista os arquivos do cliente **já enviados pelo sistema** (tabela `drive_files`, que o app enxerga por `drive.file`) com miniaturas; selecionar preenche capa/carrossel/vídeo com os links certos, na ordem clicada (arrastar para reordenar). Colar URL continua possível (fallback).
> **Arquivos:** `content/show.php`, `public/js/content-editor.js`, `DriveFileRepository` (listagem por cliente), rota GET nova (pt+en) com permissão `content.edit`.
> **Pronto quando:** criar um post de carrossel completo sem digitar/colar nenhuma URL; ordem das fotos = ordem escolhida; teste de feature cobrindo a listagem escopada por agência/cliente.

### AUTO-01 · Ativação guiada de automações — `P` 🟡
> **Problema:** as 13 automações nascem desligadas e a UI não orienta o que ligar ([AUTOMACOES.md](AUTOMACOES.md) documenta, mas o produto não guia).
> **Ação:** em `/automations`, banner quando tudo está inativo + botão "Ativar kit recomendado" que aplica o kit do AUTOMACOES.md (internas + e-mail; WhatsApp fica de fora até INT-01) e leva à matriz de clientes. Grava `activity_logs`.
> **Pronto quando:** agência nova ativa o kit em 1 clique; teste cobre que o kit não liga canais WhatsApp.

### TRAF-01 · Alertas de anomalia de tráfego pago — `M` 🟠
> **Problema:** o gestor só descobre CPA estourado/campanha pausada/verba esgotada abrindo a tela — o dado já está no banco via sync.
> **Ação:** nova automação `traffic.anomaly` (applies_to=agency, daily) no padrão `AbstractAutomation`: campanha ativa pausada na Meta, CPA do dia ≥ 2× a média de 7 dias, conta com sync falho — aviso in-app ao gestor com link. Entra no catálogo `config/automations.php` e no AUTOMACOES.md.
> **Pronto quando:** handler com teste (caso positivo e caso "sem anomalia"), dedupe por campanha+dia, entrada no catálogo.

### QA-04 · Smoke de navegador cobrindo painel e portal — `P` 🟡
> **Problema:** o smoke só valida login — painel e portal pulam sem `SMOKE_EMAIL/SMOKE_PASSWORD/PORTAL_TOKEN`.
> **Ação:** documentar/semear credenciais de smoke no ambiente local (e no CI se existir) para as 3 pernas rodarem.
> **Pronto quando:** `npm run test:browser` sem nenhum "⏭ pulado".

### CONT-AVISOS · Catálogo único de avisos — `M` 🟠 · **desenhar junto com INT-01**
> Catálogo único de eventos × canais (whatsapp/email/inapp) × idioma; e-mail com paridade do WhatsApp; opt-out do cliente ("PARAR"). Herda o desenho da matriz de automações como fonte única. Bloqueado com INT-01 (validar o canal antes de padronizá-lo).

---

## Marco B — Validações pendentes (bloqueadas em decisão do dono, não em código)

- **INT-01 · Validar Evolution/WhatsApp ponta a ponta** — `M` 🟠 ⏳ · roteiro pronto em [OPERACAO.md §4](OPERACAO.md) (5 passos, incluindo provocar erro e conferir o alerta OBS-01). **Bloqueio: decidir quem hospeda a instância + fornecer credenciais.** Ao concluir, liberar canal WhatsApp no AUTO-01 e desenhar CONT-AVISOS.
- **INT-03 · Validar ClickUp com workspace real** — `M` 🟡 ⏳ · roteiro pronto (inclui teste de conflito). **Bloqueio: decisão de produto tarefas nativas × ClickUp** — investir nos dois é desperdício. A decisão também destrava/enterra UX-05 (drag-and-drop do kanban).
- **INFRA-02 · Medir PDO persistente vs pooler Supabase** — `P` 🟡 · medir sob carga leve; desligar `ATTR_PERSISTENT` se houver saturação.
- **SEC-10 · CSP estrita (fechamento)** — `G` 🟡 · **adiado de novo conscientemente (23/07):** exige migrar ao build `@alpinejs/csp` e reescrever expressões de todas as views; custo alto, ganho moderado (script-src já é `'self'`). Reavaliar no ciclo 4.

---

## Marco C — Escala comercial

- **PROD-01 · Billing SaaS real** — `G` ⏸️ **DESPRIORIZADO POR DECISÃO DE NEGÓCIO (2026-07-23):** cobrança dos tenants é manual. Reavaliar quando o volume doer. **ARCH-04** segue válido à parte.
- **PROD-01a · Recebimento de faturas de clientes (PIX/boleto)** — `G` 🟠 · agora é **o** gateway que importa: o tenant recebendo dos clientes dele. Asaas ou Mercado Pago na fatura + recibo automático + baixa automática via webhook. **Decisão pendente: provedor.**
- **PROD-03 · Hub 360° do cliente** — `M` 🟡 · abas conteúdo/financeiro/tráfego/arquivos na ficha (dados já existem).
- **AUTH-01 · 2FA TOTP** — `M` 🟡 · pré-requisito comum de agência maior; subiu de prioridade no ciclo 3.
- **ARCH-02 · Unificar rotas pt/en por mapa de aliases** — `M` 🟡 · corta `routes/web.php` pela metade.
- **ARCH-04 · Gate único de `checkLimit`** — `P` 🟡 · hoje controller a controller; fácil esquecer num módulo novo.
- **UX-03 · Convite de usuário por e-mail** — `M` 🟡 · **UX-06 · Preferências de notificação** — `M` 🟡 · **UX-07 · Seletor de período no relatório** — `P` 🟡.

---

## Marco D — Diferenciação (pós-fluxos)

- **PROD-02 · IA→Ação com guardrails** — `G` · recomendação gera `ads_action` pré-preenchida; `ai_safety_rules` verificadas **em código** antes de executar na Meta.
- **AI-01 · Metering de IA por agência** — `M` · tokens/custo por tenant, insumo de precificação.
- **PROD-07 · Vincular post orgânico ↔ item de plano** — `G` · fecha planejou→postou→performou. **Inclui CONT-PORTAL parte 2** (portal exibir a data/link de publicação do item).
- **UX-05 · Drag-and-drop no kanban** — `M` · só se a decisão INT-03 mantiver tarefas nativas.
- **DRIVE-03 · Sync fase 2 (adições manuais no Drive)** — `G` · exige `drive.readonly` + verificação Google — decisão de produto pendente.

---

## Sequenciamento sugerido

| Sprint | Foco | Itens |
|--------|------|-------|
| **5** | Fluxos redondos | CONT-06 · AUTO-01 · TRAF-01 · QA-04 |
| **6** | Canais validados (assim que as decisões saírem) | INT-01 · CONT-AVISOS · INT-03 · INFRA-02 |
| **7** | Escala comercial | PROD-01a · PROD-03 · AUTH-01 · ARCH-02/04 |
| **8+** | Diferenciação | Marco D |

**As 4 decisões que destravam o plano** (todas do dono, nenhuma de código): hospedagem da Evolution (INT-01) · tarefas × ClickUp (INT-03) · provedor PIX/boleto (PROD-01a) · PIN opcional do portal (SEC-09 — sem pressa).

---

## Definição de pronto global (por item)

- [ ] Migrations reversíveis aplicam e revertem sem erro.
- [ ] Rota nova: auth + permissão + (se de cliente) `ClientAccessMiddleware` + CSRF em mutação — nos pares pt **e** en.
- [ ] Regra nova tem teste (permissão: positivo **e** negativo).
- [ ] `composer test` + `composer analyse` verdes; `composer audit` limpo.
- [ ] Ação sensível grava `activity_logs`; segredo novo cifrado.
- [ ] Saída de template com `e()`; `innerHTML` só com sanitização; `fetch` via `api.js`.
- [ ] Tela nova/alterada: tokens do design system, 4 estados, validada com `visual-validation`.
- [ ] Automação nova: entrada no `config/automations.php` **e** no [AUTOMACOES.md](AUTOMACOES.md).
- [ ] Item concluído: marcar ✅ **neste arquivo** com data e uma linha de como foi resolvido.
