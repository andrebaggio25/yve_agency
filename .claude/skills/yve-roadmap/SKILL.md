---
name: yve-roadmap
description: Use ao pegar qualquer item de correção, melhoria ou dívida técnica do backlog do YVE Agency — dá a um agente o contexto executável (problema, arquivos, correção, critério de pronto) de cada item do Plano Mestre vigente. Aciona em "corrija o UP-01", "pega o próximo item do roadmap", "vamos fazer o sprint 2", "o que falta pra fechar o MVP", "resolva o FE-01".
---

# Roadmap do YVE Agency (ciclo 2 · 2026-07)

Fonte canônica: `docs/PLANO_MESTRE.md` (**a verdade absoluta** — leia o item completo lá antes de executar). Este resumo existe para orientar a escolha. Ao pegar um item, carregue também `yve-arquitetura` + `yve-seguranca` (+ `yve-frontend` se tocar em tela). Feche sempre com `composer test` + `composer analyse` + `composer audit`, e marque ✅ no PLANO_MESTRE com data e uma linha.

## Ciclo 1 (2026-07-06) — tudo concluído ✅
Marco 0 (SEC-01/02, DEP-01, BUG-01) · Marco 1 (SEC-03/04/05/07, SCHEMA-01, SEC-06 parcial) · QA-01/02 · DRIVE-01/02-fase-1. Detalhes: `docs/historico/PLANO_MESTRE_2026-07-06.md`.

## Marco A — Fechar o MVP (prioridade atual)

- **UP-01** 🔴 `G` — upload > 256MB: upload **direto browser→Drive** (sessão resumável iniciada com header `Origin`; JS envia chunks com `Content-Range` à session URI; confirmação grava `drive_files`; relay vira fallback). O teto de 256MB é da Hostinger compartilhada sobre o caminho relay — não é o Google nem exige migrar hosting. Arquivos: `GoogleDriveApiService::initiateResumable`, `PortalController`, `portal/files.php`, `routes/web.php`.
- **FE-01** 🟠 `G` — tokens únicos + build Tailwind CLI (sai do CDN), self-host Alpine (pin) e Chart.js com SRI. Absorve o antigo PERF-01. Destrava SEC-10 (CSP estrita).
- **FE-02** 🟠 `G` — extrair JS inline: `content/show.php` (1.183 l.), `portal/files.php` (566 l.), `approvals/show.php` → módulos em `public/js/`.
- **FE-03** 🟠 `M` — wrapper `public/js/api.js` (X-CSRF-Token, `response.ok`, loading/erro padrão); migrar fetches.
- **SEC-08** 🟠 `M` — CSRF (double-submit) nas mutações do portal (`itemFeedback`, `drive/*`).
- **ARCH-01** 🟡 `P` — tirar o SQL do `DashboardController` (única violação da invariante "controller sem SQL").
- **QA-03** 🟡 `G` — banco PG de teste + 5 testes HTTP ponta a ponta (login/RBAC, aprovação portal, upload mock, fatura, isolamento de tenant).

## Marco B — Confiabilidade
INT-01 validar Evolution/WhatsApp ponta a ponta · INT-02 rate limit de envio · INT-03 validar ClickUp real · OBS-01 `/health` + alerta de job/sync falho · OBS-02 timeline de automações na UI · INFRA-01 worker na VPS + unificar filas `jobs`/`notification_jobs` · INFRA-02 medir PDO persistente vs pooler · INFRA-03 `RETURNING id` padrão · DATA-01 backup/retenção · ADM-01 guard-rail no painel de migrations · SEC-10 CSP com nonce.

## Marco C — Escala comercial
PROD-01 billing SaaS (gateway+trial+dunning) com ARCH-04 (checkLimit centralizado) · PROD-01a PIX/boleto nas faturas de cliente · PROD-08 dashboard acionável · PROD-03 hub 360° do cliente · PROD-06 white-label do portal · UX-04 PDF real (dompdf) · AUTH-01 2FA · ARCH-02 aliases de rota pt/en · ARCH-03 extrair `PortalDriveController`.

## Marco D — Diferenciação
PROD-02 IA→ação com guardrails verificados em código · AI-01 metering de IA por tenant · PROD-04 calendário de conteúdo · PROD-05 duplicar plano · PROD-07 orgânico↔plano · DRIVE-03 sync de adições manuais (exige `drive.readonly` + verificação Google — decisão de produto) · UX-02/03/05/06/07.

## Sequência
Sprint 1 = UP-01 + ARCH-01 + ARCH-03 · Sprint 2 = FE-01 + FE-03 + SEC-08 · Sprint 3 = FE-02 + QA-03 + SEC-10 · Sprint 4 = Marco B · Sprint 5+ = C · 6+ = D.
**MVP fechado = fim do Sprint 3.**

## Como trabalhar um item
1. Ler a entrada completa no `docs/PLANO_MESTRE.md` (problema + arquivos + pronto-quando).
2. Menor mudança que resolve, no padrão do código existente.
3. Teste novo/atualizado (permissão: positivo e negativo).
4. Gates verdes; tela alterada → `visual-validation`.
5. ✅ no PLANO_MESTRE com data + uma linha de como foi resolvido.
