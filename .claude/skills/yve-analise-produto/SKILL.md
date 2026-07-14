---
name: yve-analise-produto
description: Use ao rodar uma análise de produto completa do YVE Agency (SWOT + nota 0–10 por módulo + plano mestre) ou ao fechar um ciclo de roadmap e abrir o próximo. Padroniza o método, o formato dos documentos, a régua de notas e o arquivamento em docs/historico/. Aciona em "faça a análise do sistema", "fechar o roadmap e abrir o próximo ciclo", "reavaliar os módulos", "atualizar o plano mestre", "auditoria de produto".
---

# Análise de produto padronizada (o ritual de ciclo)

Este é o processo que gera e renova a **verdade absoluta** do projeto. Um ciclo = análise → plano mestre → execução dos sprints → arquivamento → próxima análise. O ciclo 2 (2026-07-14) é o modelo de referência.

## Quando rodar

- Ao **fechar um roadmap** (itens do PLANO_MESTRE concluídos ou o restante conscientemente adiado).
- Antes de uma decisão grande (precificação, migração de infra, feature estruturante).
- No máximo a cada ~3 meses, mesmo sem gatilho (deriva acumula).

## Passo a passo

### 1. Medir o estado (nunca pular)
```
composer test · composer analyse · composer audit
git log --oneline -15         # o que mudou desde o último ciclo
wc -l nos controllers/views    # onde a complexidade cresceu
```
Registrar os números no topo da análise. Verificar quais itens do PLANO_MESTRE anterior estão de fato ✅ (confirmar no código, não confiar no doc).

### 2. Varrer módulo a módulo
Enumerar módulos por `routes/web.php` (não de memória). Para cada um, com chapéu de **desenvolvedor de produto sênior**:
- Funcionalidade: o que faz bem, o que está inconsistente, o que falta para um cliente pagante.
- Código: controller/service/view — violações das invariantes (`yve-arquitetura`), tamanho, dívidas.
- **SWOT compacto**: Forças / Fraquezas / Oportunidades / Ameaças — bullets curtos e específicos (nada de "código bom").
- Transversais em seções próprias: backend, frontend, segurança, performance, testes, integrações.

### 3. Validar integrações
Regra: **integração não exercitada = quebrada até prova em contrário.** Roteiro mínimo por integração: conectar do zero → executar a operação principal → provocar 1 erro (token inválido/expirado) → conferir o webhook (se houver) → registrar o resultado. Integração que nunca rodou em produção ganha item INT-* no plano.

### 4. Dar as notas (régua fixa — não recalibrar por ciclo)
- **9–10** pronto para vender e escalar sem ressalva
- **7–8** MVP fechado; funciona no dia a dia; dívidas conhecidas e administradas
- **5–6** funciona, mas com lacuna que um cliente pagante sente
- **3–4** incompleto ou arriscado; não demonstrável
- **0–2** quebrado/inexistente

A nota responde: *"eu venderia isso hoje?"* — não *"o código é bonito?"*.

### 5. Plano de ação por módulo → Plano Mestre
Cada fraqueza/ameaça relevante vira item com **ID estável** (prefixos: SEC, BUG, QA, PERF, FE, ARCH, INFRA, OBS, DATA, INT, UP, DRIVE, PROD, UX, AUTH, AI, ADM + número sequencial que **nunca é reutilizado** entre ciclos). Cada item: problema → arquivos → ação → esforço (P/M/G) → **pronto quando** (critério objetivo e testável).

Prioridade sempre nesta ordem:
1. **Fechar o MVP** — corrigir erros, inconsistências e dores que usuário pagante sente.
2. **Confiabilidade** — observabilidade, validação de integrações, infra.
3. **Escala comercial** — billing, onboarding, white-label.
4. **Diferenciação** — features novas.

### 6. Escrever os documentos (nomes fixos)
- `docs/ANALISE_PRODUTO.md` — SWOT + notas + quadro-resumo (este ciclo).
- `docs/PLANO_MESTRE.md` — **a verdade absoluta**: marcos, sprints, definição de pronto. Herda no topo um resumo do que o ciclo anterior concluiu.
- `docs/ANALISE_SISTEMA.md` — atualizar a fotografia técnica só no que mudou.
- Atualizar `CLAUDE.md` (índice de docs/skills) e a skill `yve-roadmap` (que espelha o plano de forma resumida para agentes).

### 7. Arquivar o ciclo anterior
```
git mv docs/PLANO_MESTRE.md docs/historico/PLANO_MESTRE_AAAA-MM-DD.md   # data do ciclo que fecha
git mv docs/ANALISE_PRODUTO.md docs/historico/ANALISE_PRODUTO_AAAA-MM-DD.md
```
Nunca apagar histórico; os arquivos antigos são a régua de progresso entre ciclos (comparar as notas!).

## Durante a execução do ciclo

- Item concluído → marcar ✅ no PLANO_MESTRE **na hora**, com data e uma linha de como foi resolvido (o padrão do ciclo 1 funcionou bem).
- Item novo descoberto no meio → entra no PLANO_MESTRE com ID novo, no marco certo — não em doc paralelo.
- Nunca criar um segundo doc de plano. Um projeto, uma verdade.

## Anti-padrões

- Analisar de memória em vez de ler código/rodar gates.
- Nota "política" (inflar módulo que acabou de ser feito).
- SWOT genérico que serviria para qualquer sistema.
- Item de plano sem "pronto quando" testável.
- Fechar ciclo sem arquivar (o histórico é o que prova evolução).
