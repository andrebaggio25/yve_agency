# YVE Agency — Plano Mestre v3

> **A verdade absoluta do projeto.** Roteiro único e priorizado de correções, melhorias e evolução.
> Base: [ANALISE_PRODUTO.md](ANALISE_PRODUTO.md) (ciclo 3) · [ANALISE_SISTEMA.md](ANALISE_SISTEMA.md) · [AUTOMACOES.md](AUTOMACOES.md).
> Atualizado: 2026-07-23 · Ciclo: 2026-07 (ciclo 3) · Anterior: [historico/PLANO_MESTRE_2026-07-14.md](historico/PLANO_MESTRE_2026-07-14.md)
> Convenções: esforço **P** ≤2h · **M** = meio dia a 1 dia · **G** = vários dias. IDs nunca são reutilizados. Ao fechar este roadmap, arquivar em `docs/historico/` e rodar a skill `yve-analise-produto`.

**Estado herdado do ciclo 2 (fechado em 23/07 — tudo ✅ salvo o anotado):** Sprints 1–4 completos: UP-01 (upload direto validado em produção) · FE-01/02 · FE-03 (parcial: portal ✅, painel oportunista) · SEC-08 · ARCH-01/03 · QA-03 · INT-02 · OBS-01/02 · INFRA-01/03 · DATA-01 · ADM-01 · SEC-10 (parcial — ver Marco B) · PROD-04/05/06/08 · UX-02/04 · upload de logotipo · ciclo Planificações Semanais (CONT-00…05, RADAR, PORTAL p1). **Pendências herdadas:** INT-01, INT-03, INFRA-02, CONT-AVISOS, CONT-PORTAL p2.

**Gates em 2026-07-23:** 188 testes verdes (banco PG real + smoke Chromium) · PHPStan nível 6 = 0 · audit limpo (dompdf/guzzle atualizados 23/07) · sem migration pendente.

**Decisões de negócio registradas (2026-07-23) — todas as pendências do ciclo decididas pelo dono:**
1. **Billing SaaS é manual** — o dono cobra os tenants por fora; PROD-01 (gateway/trial/dunning) sai do caminho crítico. Reavaliar quando o volume de tenants doer.
2. **Evolution API: o dono hospeda a instância.** INT-01 está **destravado** — falta só executar o roteiro de validação com as credenciais da instância.
3. **ClickUp: validação despriorizada** — não é necessária agora. Tarefas nativas são o caminho padrão por ora; INT-03 (e investimento em ClickUp) fica adormecido. UX-05 (kanban) destravado.
4. **PROD-01a (PIX/boleto na fatura do cliente): sem integração por ora** — a régua de cobrança automática continua; o registro do pagamento segue manual.
5. **Portal do cliente: SEM PIN** — decisão definitiva; o modelo capability-token na URL é o design do produto. SEC-09 encerrado (não entra em plano futuro).

---

## Marco A — Fluxos redondos (o dia a dia da agência sem fricção)

### CONT-06 · Mídia do post sem colar URL: upload no modal + galeria — `G` 🟠 · **redesenhado em 23/07**
> **Problema:** montar um post ainda é colar URLs do Drive na mão — capa, cada foto do carrossel, vídeo. É o último elo manual da esteira e a origem dos bugs recentes de preview.
> **Restrição confirmada no código (23/07):** o escopo OAuth é `drive.file` — o app **só enxerga arquivos que ele mesmo criou**. O que os editores sobem DIRETO na interface do Drive é invisível ao app: o botão "atualizar" (DriveSyncService) reconcilia de verdade contra o Drive (adiciona/remove/renomeia), mas **apenas sobre os arquivos criados pela plataforma**. Um picker só da galeria `drive_files` não mostraria a mídia dos editores — por isso o desenho mudou.
> **Ação (2 frentes, ambas dentro do escopo atual):**
> 1. **Upload direto no modal do post:** botão "Enviar arquivo" na capa/carrossel/vídeo que usa a máquina do UP-01 (browser→Drive resumável, qualquer tamanho) direto para a pasta do cliente, registra em `drive_files` e preenche o link na hora. O editor troca "arrastar pro Drive" por "arrastar pro post" — um passo a menos, e a mídia já nasce vinculada.
> 2. **Upload no painel interno:** a galeria da equipe (`clients/files.php`) hoje é só leitura+sync — ganhar o mesmo upload do portal, para a equipe abastecer a pasta do cliente pela plataforma (aí sim o picker da galeria cobre tudo).
> **Escalação (se o fluxo Drive-nativo dos editores for inegociável):** DRIVE-03 — escopo `drive.readonly` + verificação do Google (processo de semanas; escopo restrito). Só encarar se a mudança de hábito da frente 1 não colar.
> **Arquivos:** `content/show.php`, `public/js/content-editor.js` (+ reuso do JS de upload do UP-01), `ClientFilesController`/`clients/files.php`, `DriveFileRepository`, rotas pt+en com `content.edit`.
> **Pronto quando:** criar um post de carrossel completo sem digitar/colar URL, com arquivos que NÃO existiam antes na plataforma; ordem das fotos = ordem escolhida; teste de feature com escopo por agência/cliente.

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

## Marco B — Validações e confiabilidade

- **INT-01 · Validar Evolution/WhatsApp ponta a ponta** — `M` 🟠 **PRONTO PARA EXECUTAR** · hospedagem decidida (o dono hospeda a instância — 23/07). Roteiro pronto em [OPERACAO.md §4](OPERACAO.md) (5 passos, incluindo provocar erro e conferir o alerta OBS-01). **Falta:** apontar a instância no `/settings/whatsapp` da agência e rodar o roteiro. Ao concluir, liberar o canal WhatsApp no AUTO-01 e desenhar CONT-AVISOS.
- **INT-03 · Validar ClickUp** — ⏸️ **DESPRIORIZADO POR DECISÃO (2026-07-23):** validação desnecessária no momento; tarefas nativas são o caminho padrão. Reativar só se algum tenant exigir ClickUp.
- **INFRA-02 · Medir PDO persistente vs pooler Supabase** — `P` 🟡 · medir sob carga leve; desligar `ATTR_PERSISTENT` se houver saturação.
- **SEC-10 · CSP estrita (fechamento)** — `G` 🟡 · **adiado de novo conscientemente (23/07):** exige migrar ao build `@alpinejs/csp` e reescrever expressões de todas as views; custo alto, ganho moderado (script-src já é `'self'`). Reavaliar no ciclo 4.

---

## Marco C — Escala comercial

- **PROD-01 · Billing SaaS real** — `G` ⏸️ **DESPRIORIZADO POR DECISÃO DE NEGÓCIO (2026-07-23):** cobrança dos tenants é manual. Reavaliar quando o volume doer. **ARCH-04** segue válido à parte.
- **PROD-01a · Recebimento de faturas de clientes (PIX/boleto)** — ⏸️ **DESPRIORIZADO POR DECISÃO (2026-07-23):** sem gateway por ora; a régua de cobrança automatiza o aviso e a baixa do pagamento segue manual. Reavaliar quando o volume de faturas doer.
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
- **UX-05 · Drag-and-drop no kanban** — `M` · destravado (23/07): tarefas nativas são o caminho padrão.
- **DRIVE-03 · Sync fase 2 (adições manuais no Drive)** — `G` · exige `drive.readonly` + verificação Google — decisão de produto pendente.

---

## Sequenciamento sugerido

| Sprint | Foco | Itens |
|--------|------|-------|
| **5** | Fluxos redondos | CONT-06 · AUTO-01 · TRAF-01 · QA-04 |
| **6** | Canal WhatsApp validado | INT-01 · CONT-AVISOS · INFRA-02 |
| **7** | Escala comercial | PROD-03 · AUTH-01 · ARCH-02/04 · UX-03/05/06/07 |
| **8+** | Diferenciação | Marco D |

**Nenhuma decisão pendente:** as 4 decisões do ciclo foram tomadas em 23/07 (ver bloco no topo). O único insumo externo que falta é **apontar a instância Evolution hospedada pelo dono** no `/settings/whatsapp` para executar o INT-01.

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
