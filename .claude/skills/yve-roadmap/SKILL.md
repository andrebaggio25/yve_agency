---
name: yve-roadmap
description: Use ao pegar qualquer item de correção, melhoria ou dívida técnica do backlog do YVE Agency — dá a um agente o contexto executável (problema, arquivos, correção, critério de pronto) de cada item do Plano Mestre vigente. Aciona em "corrija o CONT-06", "pega o próximo item do roadmap", "vamos fazer o sprint 5", "o que falta no ciclo", "resolva o TRAF-01".
---

# Roadmap do YVE Agency (ciclo 3 · 2026-07-23)

Fonte canônica: `docs/PLANO_MESTRE.md` (**a verdade absoluta** — leia o item completo lá antes de executar). Este resumo orienta a escolha. Ao pegar um item, carregue também `yve-arquitetura` + `yve-seguranca` (+ `yve-frontend` se tocar em tela). Feche sempre com `composer test` + `composer analyse` + `composer audit`, e marque ✅ no PLANO_MESTRE com data e uma linha. Automação nova entra no `config/automations.php` **e** no `docs/AUTOMACOES.md`.

## Ciclos anteriores — concluídos ✅
- **Ciclo 1 (2026-07-06):** Marcos 0–1, QA-01/02, DRIVE-01/02. → `docs/historico/PLANO_MESTRE_2026-07-06.md`
- **Ciclo 2 (2026-07-14→23):** Sprints 1–4: UP-01, FE-01/02/03(parcial), SEC-08, ARCH-01/03, QA-03, INT-02, OBS-01/02, INFRA-01/03, DATA-01, ADM-01, PROD-04/05/06/08, UX-02/04, ciclo Planificações Semanais (CONT-00…05, RADAR, PORTAL p1). → `docs/historico/PLANO_MESTRE_2026-07-14.md`

**Decisões de negócio vigentes (23/07):** billing SaaS manual (PROD-01 ⏸️) · PIX/boleto na fatura do cliente sem integração por ora (PROD-01a ⏸️) · ClickUp despriorizado, tarefas nativas são o caminho (INT-03 ⏸️, UX-05 destravado) · **o dono hospeda a Evolution** (INT-01 pronto para executar) · portal sem PIN (SEC-09 encerrado).

## Marco A — Fluxos redondos (prioridade atual · Sprint 5)

- **CONT-06** ✅ fase 1 (23/07) — galeria interna com upload direto (UP-01) + criar pastas + Copiar link (`DriveUploadService` compartilhado, `drive-manager.js` parametrizado); colar URL nos posts segue valendo. **Fase 2 aberta (sem pressa):** picker de mídia dentro do modal do post. Lembrete: `drive.file` NÃO enxerga o que editores sobem direto no Drive — DRIVE-03 é a escalação.
- **AUTO-01** 🟡 `P` — ativação guiada de automações: banner em `/automations` quando tudo inativo + "Ativar kit recomendado" (kit do AUTOMACOES.md; WhatsApp fica fora até INT-01) + `activity_logs`.
- **TRAF-01** 🟠 `M` — automação `traffic.anomaly` (agency, daily): campanha ativa pausada na Meta, CPA ≥ 2× média 7d, sync falho → in-app ao gestor. Padrão `AbstractAutomation`, dedupe por campanha+dia, entra no catálogo + AUTOMACOES.md.
- **QA-04** 🟡 `P` — smoke de navegador com `SMOKE_EMAIL/SMOKE_PASSWORD/PORTAL_TOKEN` para painel e portal não pularem.
- **CONT-AVISOS** 🟠 `M` — catálogo único eventos×canais×idioma + paridade e-mail + opt-out. **Desenhar junto com INT-01.**

## Marco B — Validações e confiabilidade
INT-01 WhatsApp e2e **pronto para executar** (dono hospeda; apontar instância em /settings/whatsapp e rodar OPERACAO.md §4) · INFRA-02 medir PDO persistente × pooler · SEC-10 CSP estrita (adiado — reavaliar ciclo 4). INT-03 ⏸️ por decisão.

## Marco C — Escala comercial
PROD-03 hub 360° · AUTH-01 2FA · ARCH-02 aliases de rota · ARCH-04 gate único de checkLimit · UX-03 convite por e-mail · UX-05 drag-and-drop no kanban (destravado) · UX-06 prefs de notificação · UX-07 seletor de período. PROD-01a ⏸️ por decisão.

## Marco D — Diferenciação
PROD-02 IA→ação com guardrails em código · AI-01 metering de IA · PROD-07 orgânico↔plano (inclui CONT-PORTAL p2) · DRIVE-03 sync fase 2 (decisão de produto).

## Sequência
Sprint 5 = CONT-06 + AUTO-01 + TRAF-01 + QA-04 · Sprint 6 = INT-01 + CONT-AVISOS + INFRA-02 · Sprint 7 = PROD-03 + AUTH-01 + ARCH-02/04 + UX-03/05/06/07 · Sprint 8+ = Marco D.

## Como trabalhar um item
1. Ler a entrada completa no `docs/PLANO_MESTRE.md` (problema + arquivos + pronto-quando).
2. Menor mudança que resolve, no padrão do código existente.
3. Teste novo/atualizado (permissão: positivo e negativo).
4. Gates verdes; tela alterada → `visual-validation`.
5. ✅ no PLANO_MESTRE com data + uma linha de como foi resolvido.
