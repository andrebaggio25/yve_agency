# YVE Agency — Análise de Produto (SWOT por módulo)

> Análise de produto sênior · SWOT + nota 0–10 + plano de ação por módulo
> Data: 2026-07-23 · Ciclo: **2026-07 (ciclo 3)** · Método: skill `yve-analise-produto`
> Anterior: [historico/ANALISE_PRODUTO_2026-07-14.md](historico/ANALISE_PRODUTO_2026-07-14.md) (comparar notas = medir progresso)
> Complementa [ANALISE_SISTEMA.md](ANALISE_SISTEMA.md). Roteiro consolidado: [PLANO_MESTRE.md](PLANO_MESTRE.md) — **a verdade absoluta**. Catálogo de automações: [AUTOMACOES.md](AUTOMACOES.md).

**Filosofia da nota (régua fixa):** 9–10 vende e escala sem ressalva · 7–8 MVP fechado, dívidas administradas · 5–6 lacuna que cliente pagante sente · 3–4 não demonstrável · 0–2 quebrado. A nota responde *"eu venderia isso hoje?"*.

---

## 0. Estado dos gates (medido em 2026-07-23)

```
PHPUnit:         188 testes, 417 asserts — 100% verde (banco PG real + smoke de navegador)
PHPStan nível 6: 0 erros
composer audit:  0 advisories (dompdf 3.1.6 / guzzle 7.15.1 atualizados em 23/07)
Commits desde o ciclo 2 (14/07): 40 — Sprints 1–4 concluídos + ciclo Planificações Semanais + PROD-04/05/06/08, UX-02/04, ADM-01, correções de portal (vídeo 9:16, carrossel empilhado)
```

**Leitura executiva:** o MVP fechou e a confiabilidade foi construída. O produto mudou de patamar — média foi de ~7.1 para **~7.7**. O que puxa para baixo agora não é mais fundação: é (a) canais externos nunca validados (WhatsApp/ClickUp), (b) fluxo de mídia do post ainda manual (colar URLs), e (c) módulos de análise (IA, ações, orgânico) que não fecham ciclo. **Decisão de negócio registrada (23/07): billing SaaS dos tenants é manual** — PROD-01 sai do caminho crítico.

---

## 1. Quadro de notas (resumo executivo)

| # | Módulo | C2 | **C3** | Situação em uma linha |
|---|--------|----|--------|----------------------|
| 1 | Core / Arquitetura | 9.0 | **9.0** | Camadas limpas + testes de arquitetura travando regressão |
| 2 | Conteúdo & Aprovações | 7.5 | **8.5** | Semana seg–dom, modelos, auto-criação, radar — o fluxo real da agência |
| 3 | Portal do Cliente | 7.5 | **8.5** | CSRF ✅, white-label ✅, calendário, carrossel empilhado, vídeo 9:16 |
| 4 | Drive / Upload | 7.0 | **8.5** | Upload direto browser→Drive validado em produção — teto de 256MB morto |
| 5 | Testes & Qualidade | 7.0 | **8.5** | 188 testes com banco real + smoke de Chromium que pega o que PHPUnit não vê |
| 6 | Auth & RBAC | 8.5 | **8.5** | Sólido; falta 2FA para vender a agência grande |
| 7 | Clientes | 8.0 | **8.5** | Arquivar revoga portal ✅; falta o hub 360° |
| 8 | Segurança (transversal) | 8.0 | **8.5** | SEC-08 ✅, upload endurecido; resta CSP estrita (decisão de ciclo) |
| 9 | Automações | 7.5 | **8.5** | Fila única, timeline de entregas, rate limit anti-ban, catálogo documentado |
| 10 | Dashboard | 6.0 | **8.0** | "Meu dia" acionável — aprovação parada, fatura vencendo, tarefa atrasada, sync quebrado |
| 11 | Financeiro | 7.5 | **8.0** | PDF real anexado no e-mail ✅; recebimento segue manual (PROD-01a) |
| 12 | Infra (Queue/Cron) | 7.0 | **8.0** | Fila única SKIP LOCKED + /api/health + heartbeat + alerta por e-mail |
| 13 | Usuários & Perfis | 8.0 | **8.0** | Estável; convite por e-mail ainda pendente |
| 14 | Admin da Plataforma | 7.5 | **8.0** | Guard-rail de rollback ✅; billing manual por decisão de negócio |
| 15 | Tráfego Pago (Meta) | 7.0 | **7.5** | Sync vigiado (health + Meu dia); faltam alertas de anomalia (TRAF-01) |
| 16 | Notificações | 7.0 | **7.5** | Fila única, entrega com motivo de erro; falta paridade e-mail e prefs |
| 17 | Relatório Executivo | 7.0 | **7.5** | PDF real ✅; falta seletor de período |
| 18 | Frontend (transversal) | 5.5 | **7.5** | Build purgado 60KB, tokens únicos, JS extraído das 3 views gigantes |
| 19 | Performance (transversal) | 6.0 | **7.5** | CDN eliminado (~3MB → 60KB); resta medir pooler (INFRA-02) |
| 20 | ClickUp | 7.0 | **7.0** | Código pronto; **nunca validado** — e decisão tarefas vs ClickUp pendente |
| 21 | Tarefas (Kanban) | 6.5 | **6.5** | Criação automática ok; sem drag-and-drop, sem recorrência |
| 22 | WhatsApp (Evolution) | 6.5 | **6.5** | Rate limit ✅; **nunca validado ponta a ponta** (INT-01) |
| 23 | Ações em Campanha | 6.5 | **6.5** | Guardrails de IA seguem só no schema, não no código |
| 24 | Orgânico (Instagram) | 6.5 | **6.5** | Leitura passiva; não fecha planejou→postou→performou |
| 25 | Billing SaaS | 5.5 | **6.0** | **Decisão de negócio: manual.** Limites por plano aplicados; gate disperso (ARCH-04) |
| 26 | IA & Insights | 6.0 | **6.0** | Texto solto; não vira ação, não é medido por tenant |

**Média ponderada: ~7.7** (era ~7.1). As três âncoras atuais: canais não validados (INT-01/03), mídia do post por copiar/colar URL (CONT-06), e o trio análise (IA/ações/orgânico) parado desde o ciclo 1.

---

## 2. Análise por módulo (SWOT compacto + ação)

### 2.1 Core / Arquitetura — 9.0
- **S:** Router+Pipeline+Container+Repository disciplinados; `HttpException` tornou guards testáveis; testes de arquitetura (`ControllerHasNoSqlTest`, `RoutesResolveTest`, `MutationRoutesHaveCsrfTest`, `NoRuntimeCdnTest`, `ScriptLoadOrderTest`) impedem regressão estrutural.
- **W:** rotas pt+en duplicadas à mão (~200 pares); `ContentPlanService` chegou a 795 linhas (maior service).
- **O:** ARCH-02 (aliases) corta `routes/web.php` pela metade.
- **T:** rota nova esquecida no par pt/en = link quebrado silencioso (mitigado pelo `RoutesResolveTest`, que valida handler mas não o par).
- **Ação:** ARCH-02 · observar o tamanho do `ContentPlanService` (extrair `ContentTemplateService` se passar de ~900 l.).

### 2.2 Conteúdo & Aprovações — 8.5 (7.5 ↑)
- **S:** o módulo agora espelha o fluxo real: semana seg–dom com snapping, visão Semana de 7 colunas, calendário mensal, modelo semanal por cliente, auto-criação na aprovação, radar de cliente sem pauta, duplicar preservando dias; status `sent` canônico; link de aprovação corrigido para o portal público.
- **W:** montar o post ainda é **colar URLs do Drive** (capa, carrossel foto a foto, vídeo) — o elo manual e sujeito a erro do fluxo; `content/show.php` segue com 1.151 linhas (JS já extraído, HTML gigante).
- **O:** **CONT-06** — seletor de mídia do Drive no modal (escolher dos arquivos já enviados, ordem controlada, zero URL na mão) fecha o último elo manual da esteira.
- **T:** a view gigante continua sendo onde bugs de UI nascem (série vídeo/carrossel de 22–23/07 confirma).
- **Ação:** **CONT-06 (prioridade nº 1 do ciclo)** · CONT-PORTAL parte 2 (exibir publicado — com PROD-07).

### 2.3 Portal do Cliente — 8.5 (7.5 ↑)
- **S:** diferencial competitivo consolidado: CSRF em toda mutação (SEC-08), white-label com a cor da agência (PROD-06), calendário mensal de consulta, "Sua semana" no dashboard, aprovação por item com link direto, carrossel empilhado na ordem de publicação, vídeo Reels/Story em quadro 9:16, upload de qualquer tamanho.
- **W:** item aprovado não mostra quando foi **publicado** (parte 2 pendente).
- **O:** portal é a tela de venda — cada polimento aqui é argumento comercial direto.
- **T:** link encaminhado a terceiros dá acesso total — **design confirmado pelo dono (23/07): sem PIN, capability-token é o modelo do produto** (SEC-09 encerrado). Mitigação existente: regenerar o token revoga o link antigo.
- **Ação:** CONT-PORTAL p2.

### 2.4 Drive / Upload — 8.5 (7.0 ↑)
- **S:** UP-01 entregue e validado em produção: browser→Drive resumável (chunks 16MB, progresso, retomada, timeout por etapa), qualquer tipo de arquivo com proxy anti-XSS, mitigações iOS; galeria, lixeira, reconciliação.
- **W:** adição manual direto no Drive não sincroniza e **nunca vai aparecer no "atualizar"** — o escopo `drive.file` só enxerga o que o app criou (ressalva documentada no `DriveSyncService`); os editores sobem direto no Drive, então a galeria `drive_files` não representa a mídia real dos clientes.
- **Ação:** CONT-06 redesenhado (upload no modal do post + upload no painel interno — a mídia passa a entrar pela plataforma) · DRIVE-03 (`drive.readonly` + verificação Google) fica como escalação se o hábito Drive-nativo dos editores não mudar.

### 2.5 Testes & Qualidade — 8.5 (7.0 ↑)
- **S:** 188 testes/417 asserts com banco PG real (JSONB/SKIP LOCKED de verdade), guarda-corpo anti-produção, smoke de Chromium com CSP real (pega classe de bug invisível ao PHPUnit), testes de arquitetura.
- **W:** browser smoke só cobre login/console (painel e portal pulam sem credenciais de smoke); JS extraído ainda sem teste unitário próprio.
- **Ação:** QA-04 (novo): SMOKE_EMAIL/PASSWORD + PORTAL_TOKEN no ambiente local para o smoke navegar painel e portal.

### 2.6 Auth & RBAC — 8.5 (=)
- **S:** Argon2id, sessão endurecida, 83 permissões, testes positivos+negativos, reset por token.
- **W:** sem 2FA; sem bloqueio pós-N falhas (só rate limit IP); sem lista de sessões ativas.
- **Ação:** AUTH-01 (2FA TOTP) — subiu de prioridade: é pré-requisito comum em compliance de agência maior.

### 2.7 Clientes — 8.5 (8.0 ↑)
- **S:** arquivar revoga portal na hora (2 camadas) e mostra impacto real; reativação; acesso granular por usuário; pasta Drive por cliente.
- **W:** ficha do cliente ainda fragmentada — financeiro/tráfego/conteúdo em telas separadas.
- **Ação:** PROD-03 (hub 360° — os dados todos já existem).

### 2.8 Segurança (transversal) — 8.5 (8.0 ↑)
- **S:** CSRF universal (varredura automatizada), upload com MIME por conteúdo + SVG recusado + pasta sem execução, secrets cifrados, `script-src 'self'`, logo/branding validados na escrita e na emissão.
- **W:** `unsafe-eval`/`unsafe-inline` na CSP (exigência do Alpine padrão — documentada em teste); sem 2FA.
- **Ação:** SEC-10 (migrar a `@alpinejs/csp` — **manter adiado**: custo alto/ganho moderado; reavaliar no ciclo 4) · AUTH-01.

### 2.9 Automações — 8.5 (7.5 ↑)
- **S:** fila única com SKIP LOCKED + backoff + alerta ao esgotar; timeline `/automations/deliveries` com motivo de erro; rate limit WhatsApp 8s (anti-ban); 13 automações catalogadas e agora **documentadas em [AUTOMACOES.md](AUTOMACOES.md)**; opt-in em 2 camadas; dedupe idempotente.
- **W:** tudo nasce desligado e a UI não guia a ativação (que kit ligar primeiro?); zero automação de tráfego pago; e-mail sem paridade com WhatsApp em vários eventos.
- **O:** AUTO-01 (kit recomendado em 1 clique) transforma o catálogo em onboarding; TRAF-01 é o alerta de maior valor percebido ainda não escrito.
- **Ação:** AUTO-01 · TRAF-01 · CONT-AVISOS (com INT-01).

### 2.10 Dashboard — 8.0 (6.0 ↑)
- **S:** "Meu dia" acionável: aprovações paradas, faturas vencendo/vencidas com valor, tarefas atrasadas, syncs quebrados — cada linha com link e permissão por bloco; "Tudo em dia" quando limpo.
- **W:** sem visão de tendência (semana/mês); KPIs de topo seguem contadores simples.
- **Ação:** nada urgente — deixar o uso real pedir a próxima iteração.

### 2.11 Financeiro — 8.0 (7.5 ↑)
- **S:** contratos→faturas→pagamentos multi-moeda; recorrência automática idempotente; régua de cobrança em degraus; PDF real anexado no e-mail; DECIMAL em tudo.
- **W:** recebimento manual (sem PIX/boleto no nível tenant→cliente); sem conciliação.
- **O:** PROD-01a (Asaas/Mercado Pago na fatura) fecharia o ciclo — **mas foi despriorizado por decisão do dono (23/07)**: régua automática avisa, baixa segue manual.
- **Ação:** nenhuma neste ciclo; reavaliar PROD-01a quando o volume de faturas doer.

### 2.12 Infra — 8.0 (7.0 ↑)
- **S:** fila única vigiada, `/api/health` real (banco, cron parado, jobs falhos, sync congelado), heartbeat, alerta e-mail com throttle, backup documentado.
- **W:** INFRA-02 (PDO persistente × pooler Supabase) nunca medido; latência atada ao cron HTTP (aceito e justificado — automações são daily).
- **Ação:** INFRA-02 (P — medir e decidir).

### 2.13–2.14 Usuários & Perfis — 8.0 (=) · Admin da Plataforma — 8.0 (7.5 ↑)
- Usuários: estável; falta UX-03 (convite por e-mail). Admin: guard-rail `REVERTER` no rollback ✅; tenants/planos ok; billing manual (decisão).
- **Ação:** UX-03 (pós-fluxos) · nada no admin.

### 2.15 Tráfego Pago — 7.5 (7.0 ↑)
- **S:** OAuth+cifra+sync ok; sync agora vigiado (health + Meu dia).
- **W:** nenhum alerta de *negócio* (CPA subiu, campanha pausou, verba estourou); sem comparativo de período.
- **Ação:** **TRAF-01** — automação de anomalias (o motor está pronto; é escrever o handler).

### 2.16 Notificações — 7.5 (7.0 ↑)
- **S:** fila única; entrega com motivo; bell in-app ok.
- **W:** eventos sem paridade de canal (e-mail cobre menos que WhatsApp); sem preferências por usuário.
- **Ação:** CONT-AVISOS (catálogo único eventos×canais×idioma) · UX-06.

### 2.17 Relatório Executivo — 7.5 (7.0 ↑) · 2.18 Frontend — 7.5 (5.5 ↑) · 2.19 Performance — 7.5 (6.0 ↑)
- Relatório: PDF real ✅; falta UX-07 (período customizável). Frontend: build purgado 60KB, tokens únicos com `--accent`, JS das 3 views gigantes extraído; restam `<script>` inline em 18 views menores e `api.js` só no portal (migração oportunista via FE-03). Performance: CDN morto; resta INFRA-02 e N+1 do portal sob volume.
- **Ação:** UX-07 · FE-03 oportunista · INFRA-02.

### 2.20 ClickUp — 7.0 (=) · 2.22 WhatsApp — 6.5 (=)
- Ambos com código pronto e **zero uso em produção**. Regra do método: integração não exercitada = quebrada até prova em contrário.
- **Decisões tomadas (23/07):** o dono hospeda a Evolution → **INT-01 pronto para executar** (falta apontar a instância e rodar o roteiro de [OPERACAO.md §4](OPERACAO.md)); ClickUp **despriorizado** (INT-03 ⏸️ — tarefas nativas são o caminho padrão).
- **Ação:** executar INT-01 no Sprint 6; ClickUp adormecido até algum tenant exigir.

### 2.21 Tarefas — 6.5 (=)
- **S:** criação automática pós-aprovação + SLA + comentários. **W:** sem drag-and-drop, sem recorrência; decisão vs ClickUp pendente trava investimento.
- **Ação:** decidir INT-03 primeiro; depois UX-05 (drag-and-drop) se tarefas nativas vencerem.

### 2.23 Ações em Campanha — 6.5 (=) · 2.26 IA & Insights — 6.0 (=) · 2.24 Orgânico — 6.5 (=)
- O trio "análise" está congelado desde o ciclo 1: insight não vira ação, `ai_safety_rules` não são verificadas em código, orgânico não cruza com plano.
- **O:** é o Marco D inteiro (PROD-02 + AI-01 + PROD-07) — o "wow" do produto quando os fluxos operacionais estiverem redondos.
- **Ação:** manter no Marco D; não começar antes de CONT-06/INT-01 fecharem.

### 2.25 Billing SaaS — 6.0 (5.5 ↑ por decisão)
- **Decisão de negócio (23/07): cobrança dos tenants é manual.** O sistema aplica limites por plano; o dono fatura por fora. A nota sobe porque a lacuna deixou de ser "falta feature" e virou "modelo operacional escolhido".
- **W remanescente:** `checkLimit` disperso controller a controller (ARCH-04).
- **Ação:** ARCH-04 (P) · reavaliar gateway quando o nº de tenants doer.

---

## 3. Integrações — parecer consolidado

| Integração | Parecer | Pendência para "confiável" |
|-----------|---------|---------------------------|
| **Google Drive** | Validada em produção (upload direto, proxy, reconciliação) | DRIVE-03 (fase 2) é decisão de produto |
| **Meta Ads** | Sólida e agora vigiada (health/Meu dia) | TRAF-01 (alertas de negócio) |
| **Instagram Orgânico** | Funcional, vigiada | PROD-07 fecha o ciclo com o plano |
| **Evolution/WhatsApp** | Código pronto + rate limit; **nunca exercitada** | **INT-01 pronto para executar** — dono hospeda a instância (decisão 23/07); falta apontar e rodar o roteiro |
| **ClickUp** | Código pronto; nunca usada | ⏸️ despriorizada (decisão 23/07) — tarefas nativas são o caminho |
| **OpenAI/Anthropic** | Funcional com fallback | AI-01 (metering) antes de escalar |
| **SMTP** | Funcional; entregas visíveis na timeline | Paridade de eventos (CONT-AVISOS) |

---

## 4. Priorização mestre (visão de produto)

1. **Fluxos redondos (o ciclo atual):** CONT-06 (mídia sem copiar/colar) → AUTO-01 (ativação guiada) → TRAF-01 (alerta de tráfego) → QA-04.
2. **Canal WhatsApp:** INT-01 (dono hospeda a Evolution — pronto para executar) + CONT-AVISOS. (ClickUp ⏸️ por decisão.)
3. **Escala comercial:** PROD-03 (hub 360°) → AUTH-01 (2FA) → ARCH-02/04 → UX-03/05/06/07. (PROD-01a ⏸️ por decisão.)
4. **Diferenciação (Marco D):** PROD-02 → AI-01 → PROD-07 → DRIVE-03.

Sequência detalhada, esforços e critérios de pronto: [PLANO_MESTRE.md](PLANO_MESTRE.md).
