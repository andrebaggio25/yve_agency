# YVE Agency — Análise de Produto (SWOT por módulo)

> Análise de produto sênior · SWOT + nota 0–10 + plano de ação por módulo
> Data: 2026-10-31 · Ciclo: **2026-10 (ciclo 4)** · Método: skill `yve-analise-produto` (varredura em 5 frentes, achados críticos conferidos no código)
> Anterior: [historico/ANALISE_PRODUTO_2026-07-23.md](historico/ANALISE_PRODUTO_2026-07-23.md) (comparar notas = medir progresso)
> Roteiro consolidado: [PLANO_MESTRE.md](PLANO_MESTRE.md) v4 — **a verdade absoluta**.

**Filosofia da nota (régua fixa):** 9–10 vende e escala sem ressalva · 7–8 MVP fechado, dívidas administradas · 5–6 lacuna que cliente pagante sente · 3–4 não demonstrável · 0–2 quebrado. A nota responde *"eu venderia isso hoje?"*.

---

## 0. Estado dos gates (medido em 2026-10-31)

```
PHPUnit:         244 testes, 536 asserts — verde COM o banco Docker
                 sem o banco: 10 erros (BrandingTest e RepositoryScopeTest não se pulam) e 140 pulados
PHPStan nível 6: 0 erros
composer audit:  5 advisories — cakephp/database (SQLi, crítico), guzzle (2), sodium_compat
PHP:             local 8.5.2 × produção 8.3 — a suíte nunca roda na versão de produção
Commits desde o ciclo 3 (23/07): 9 — galeria com escrita (CONT-06 f1), reagendar post (CONT-07), ajustes de RBAC
```

**Leitura executiva:** o ciclo 3 avaliou o produto em ~7,7. Relido contra o código, a nota cai para **~5,3** nos módulos revisados. A diferença não vem do que foi construído depois. As notas anteriores mediam o que estava escrito e não testavam as fronteiras. A revisão achou **furos de tenant** (cliente, campanha e perfil de outra agência acessíveis), **escalonamento de perfil** entre agências, **recuperação de senha quebrada em produção**, **token do orgânico em texto puro**, um **sync do Drive que pode apagar a galeria** e **automações que ignoram o canal escolhido**. As três frentes que o dono quer fechar estão em 15–40%: a central Meta é um leitor de métricas, as tarefas são um kanban de 4 colunas e o WhatsApp nunca foi exercitado nem alcança a equipe.

---

## 1. Quadro de notas

| Módulo | C3 | **C4** | Situação em uma linha |
|--------|----|--------|----------------------|
| Conteúdo & planificações | 8.5 | **7.5** | Melhor parte do sistema; sem esteira de produção e sem travas de status no plano |
| Portal do Cliente | 8.5 | **7.5** | Sólido; hex solto, JS inline, aprovação item a item contorna o status do plano |
| Drive / Galeria | 8.5 | **6.5** | Upload ótimo; sincronizar pode apagar a galeria num 429; escrita com permissão de leitura |
| Dashboard | 8.0 | **6.5** | "Meu dia" é bom; 3 contadores sem tendência; atrasadas da agência inteira |
| Relatórios | 7.5 | **6.5** | Código bom; sem período, sem envio; link do relatório mensal cai no login |
| Clientes | 8.5 | **6.0** | Furo entre agências; ficha com bloco morto; form expõe 4 de 16 campos |
| Automações & WhatsApp | 8.5 / 6.5 | **5.5** | Fila boa; canais da UI ignorados; equipe sem WhatsApp; nada validado; inbound descartado |
| Financeiro | 8.0 | **5.0** | Sem auditoria, sem validação de valor no servidor, SQLSTATE na tela, zero teste |
| Segurança (transversal) | 8.5 | **5.0** | Base boa; escalonamento de perfil, IDORs entre agências, CVE crítico |
| Admin da Plataforma | 8.0 | **5.0** | SQL dentro da view; zero auditoria; planos sem guarda própria |
| Aprovações (tela interna) | — | **4.5** | Invisível para social media/designer; permissões inexistentes; 403 mudo |
| Central Meta (Ads + orgânico) | 7.5 / 6.5 | **4.0** | Leitor de métricas; Graph v21 fixa; N+1 de API; token orgânico em texto puro |
| Usuários & Perfis | 8.0 | **4.0** | Perfis globais editáveis por qualquer tenant; `role_ids` sem validação |
| Billing SaaS | 6.0 | **4.0** | Limite falha aberto; nada acontece quando o plano vence |
| Tarefas & chat | 6.5 | **3.5** | Kanban simples; comentário não avisa ninguém; "marcar como lida" dá 500 |
| Auth | 8.5 | **3.0** | Recuperação de senha dá 500; token de reset em claro; sem lockout por conta |
| Core / Arquitetura | 9.0 | **8.0** | Camadas limpas e testes de arquitetura; mas `RoutesResolveTest` não pega view inexistente nem aridade |

---

## 2. Análise por área

### 2.1 Central Meta — 4.0 (~28% da meta)
- **S:** OAuth de Ads com token cifrado; sync de contas/campanhas/conjuntos/anúncios; fluxo de aprovação de 4 ações; insights por IA.
- **W:** token orgânico sem `Secret` (`OrganicAccountRepository.php:90`); `CampaignRepository::listByAccount` sem `agency_id` (`:13`) exposto em `/trafego/contas/{id}/campanhas`; alvo das ações cru do POST (`AdsActionService.php:25-59`); zero `activity_logs`; `updateBudget` sempre `daily_budget`, sem teto (`MetaAdsService.php:324`); Graph v21.0 fixa e `config/services.php` morta; métricas de IG depreciadas e erro engolido como zero (`MetaOrganicService.php:82-113`); 1 chamada de insights por anúncio dentro do request (`AdsSyncService.php:98-122`); paginação que perde dados; `token_expires_at` nunca lido; orgânico exige colar token; ROAS por média aritmética.
- **O:** publicar/agendar o post aprovado direto no IG/FB fecha planejou→publicou→performou, e é o que transforma leitura em central.
- **T:** App Review da Meta (2–6 semanas, pode reprovar) é o caminho crítico; qualquer quebra de versão hoje aparece como zero no dashboard do cliente.
- **Ação:** SEC-13, SEC-14 → META-01…13, TRAF-01, PROD-07.

### 2.2 Tarefas & chat interno — 3.5 (~15% da meta "nível ClickUp")
- **S:** criação automática na aprovação do plano (`ContentPlanService.php:804`); alerta de SLA; kanban com drag-and-drop (o UX-05 já existe).
- **W:** `tasks` com 9 colunas: sem subtarefa, checklist, múltiplos responsáveis, status por agência, hora, recorrência. Uma única visão. `notificationsMarkRead` com aridade errada → 500 (`SettingsController.php:104` × `Router.php:141`). `client_id`/`assigned_to` sem validação e JOINs sem escopo (`TaskRepository.php:21`). O chat não atualiza (`init()` nunca roda) e não notifica ninguém (`InternalCommentService.php:40`). E-mail de atribuição síncrono. Zero teste.
- **O:** ligar tarefa à esteira de produção do post faz da plataforma a ferramenta do dia da equipe, e o ClickUp deixa de ser necessário.
- **Ação:** BUG-06 → TASK-01…07, CHAT-01/02, UX-06.

### 2.3 Automações & WhatsApp (Evolution) — 5.5 (~40% da meta)
- **S:** catálogo de 13 automações, gate em 2 camadas, dedupe com UNIQUE, fila `SKIP LOCKED` com backoff, espaçamento anti-ban, chave cifrada, webhook com token por instância.
- **W:** `automation_rules.channels` só desenha o checkbox (`AutomationService.php:58`); relatório mensal manda link interno (`ClientMonthlyReport.php:30`); 5 templates de e-mail, nenhum de automação; nenhum WhatsApp para equipe; `MESSAGES_UPSERT` descartado (sem APROVADO/PARAR); `normalizePhone` força +55 e destrói JID de grupo; jobs presos em `reserved` nunca voltam; `delay/presence` no lugar de v1; `docs/CRON.md` não cita `/queue/scheduler`; nunca validado (INT-01).
- **O:** o número central da agência mandando o plano, os lembretes e a régua de cobrança é o argumento comercial mais visível do produto.
- **Ação:** BUG-07, INFRA-05 → INT-01, WPP-01…07, AUTO-01/02.

### 2.4 Conteúdo, Aprovações, Portal — 7.5 / 4.5 / 7.5
- **S:** semana seg–dom, modelo por cliente, reagendar, radar, portal com 4 estados e `api.js`, 58 testes no conteúdo.
- **W:** `/aprovacoes` lê `$_SESSION['client_ids']` (`ApprovalController.php:29`) e consulta `approvals.approved|changes_requested|rejected`, que não existem; `approvals.js` com escopo Alpine errado e API do Alpine v2. Post pode ser adicionado/excluído em plano aprovado; item aprovado no portal aprova plano em rascunho (`ContentPlanService.php:705-718`); trocar a semana trava a edição dos posts. Sem estágio de produção (`ITEM_STATUSES` só reflete a opinião do cliente). Rotas de leitura sem `ClientAccessMiddleware`.
- **Ação:** SEC-11, BUG-04, BUG-05, WPP-07, TASK-06, FE-04/05.

### 2.5 Clientes, Drive, Dashboard — 6.0 / 6.5 / 6.5
- **W:** `canAccessClient` libera qualquer agência com `view_all` (`Auth.php:127`); `revokeAccess/grantAccess` ignoram a agência (`ClientService.php:233-243`); `listFolder` faz `break` em qualquer não-200 e a reconciliação apaga a galeria (`GoogleDriveApiService.php:289` + `DriveSyncService.php:118`); escrita no Drive com `clients.view`; todo upload público por link; ficha com 4 consultas descartadas e bloco morto; validação só de `name`; dashboard sem tendência.
- **Ação:** SEC-11, BUG-03, PROD-03, CONT-08, PROD-09, DRIVE-04.

### 2.6 Financeiro, Auth, RBAC, Admin, Billing
- **W:** views `auth/forgot-password` e `auth/reset-password` não existem (`AuthController.php:55,70`); perfis globais (`agency_id NULL`) editáveis por qualquer tenant (`RoleRepository.php:24-31`); `role_ids` sem validação (`UserService.php:79`); ninguém é impedido de conceder o que não tem; zero `activity_logs` em dinheiro e plataforma; valor/status sem validação no servidor (`PaymentService.php:36`); `$e->getMessage()` na tela; redirect pelo Referer; `ATTR_PERSISTENT` com pooler; SQL dentro de `admin/index.php`; Monolog sem chamador; cookie sem `Secure` atrás do proxy; 30 handlers sem par pt/en.
- **Ação:** BUG-02, SEC-12, SEC-15…18, QA-05, INFRA-02, OBS-03, ADM-02, ARCH-02/04, AUTH-01.

---

## 3. Integrações — parecer

| Integração | Parecer | Pendência para "confiável" |
|-----------|---------|---------------------------|
| **Google Drive** | Upload validado em produção; sync perigoso | BUG-03 (reconciliação segura) |
| **Meta Ads** | Leitura ok, escrita sem trava nem log, versão vencida | META-01…05, SEC-13 |
| **Instagram/FB orgânico** | Token em texto puro, colado à mão, métricas depreciadas | SEC-14, META-02, META-06 |
| **Evolution/WhatsApp** | Código bom na fila; nunca exercitado; canais ignorados | BUG-07, INT-01, WPP-* |
| **ClickUp** | Adormecido, mas ainda enfileira job a cada tarefa salva | TASK-07 (desligar) |
| **SMTP** | Funciona; só 5 templates | WPP-01 |
| **OpenAI/Anthropic** | Funciona; `max_tokens` fixo, modelo padrão inexistente | META-05 (guardrails) |

---

## 4. Priorização

1. **Blindagem (F0):** furos de tenant e RBAC, senha, token, Drive, aprovações, canais, dinheiro, CVE, suíte confiável.
2. **Infra (F1):** worker na VPS, pooler, fila robusta, logs.
3. **Central de WhatsApp (F2)** → **Tarefas e chat (F3)** → **Central Meta (F4)**, com o App Review da Meta aberto já na F0.
4. **Operação redonda (F5).**

Detalhe, horas, % feito e critérios de pronto: [PLANO_MESTRE.md](PLANO_MESTRE.md).
