---
name: yve-roadmap
description: Use ao pegar qualquer item de correção, melhoria ou dívida técnica do backlog do YVE Agency — dá a um agente o contexto executável (problema, arquivos, correção, critério de pronto) de cada item do Plano Mestre vigente. Aciona em "corrija o CONT-06", "pega o próximo item do roadmap", "vamos fazer o sprint 5", "o que falta no ciclo", "resolva o TRAF-01".
---

# Roadmap do YVE Agency (ciclo 4 · 2026-11-01)

Fonte canônica: `docs/PLANO_MESTRE.md` v4 (**a verdade absoluta** — leia o item completo lá antes de executar). Este resumo orienta a escolha. Ao pegar um item, carregue também `yve-arquitetura` + `yve-seguranca` (+ `yve-frontend` se tocar em tela). Feche sempre com `composer test` + `composer analyse` + `composer audit`, e marque ✅ no PLANO_MESTRE com data e uma linha. Automação nova entra no `config/automations.php` **e** no `docs/AUTOMACOES.md`.

## Ciclos anteriores
Ciclos 1 a 3 arquivados em `docs/historico/`.

**Decisões vigentes:** billing SaaS manual · sem PIX/boleto por ora · portal sem PIN · **as 14 decisões de 01/11 no fim do PLANO_MESTRE**, das quais as que mudam código: um app Meta da YVE (Tech Provider) · **sem worker na VPS, só cron da Hostinger: trabalho pesado roda em fatias retomáveis** · um número de WhatsApp por agência · status de tarefa por agência · chat por polling de 8s · catálogo parametrizável (sem builder livre) · grupos só para conteúdo · perfis fixos do sistema + próprios da agência · **toda tela nova com `t()` em pt, en e es**.

## Fases (nesta ordem)
- **F0 Blindagem:** SEC-11…18, BUG-02…07, QA-05. Nada novo antes disto.
- **F1 Infra:** INFRA-02, INFRA-05, OBS-03 (INFRA-04 ⏸️).
- **F2 Central de WhatsApp:** INT-01 primeiro; WPP-01…07, UX-06, AUTO-01/02.
- **F3 Tarefas e chat:** TASK-01…07, CHAT-01/02.
- **F4 Central Meta:** META-01…13, TRAF-01, PROD-07, AI-02. Publicação, webhooks e leads dependem do App Review.
- **F5 Operação redonda:** PROD-03, PROD-09, CONT-06 f2, CONT-08/09, FE-04/05/06, DRIVE-04, ADM-02, ARCH-02/04, AUTH-01, UX-03.

## Como trabalhar um item
1. Ler a entrada completa no `docs/PLANO_MESTRE.md` (problema + arquivos + pronto-quando).
2. Menor mudança que resolve, no padrão do código existente.
3. Teste novo/atualizado (permissão: positivo e negativo).
4. Gates verdes; tela alterada → `visual-validation`.
5. ✅ no PLANO_MESTRE com data + uma linha de como foi resolvido.
