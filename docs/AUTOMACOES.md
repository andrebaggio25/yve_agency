# YVE Agency — Catálogo de Automações

> **Para que serve este doc:** listar TODAS as automações que o sistema tem hoje, como cada uma funciona, para quem fala (equipe interna ou cliente), por qual canal, e o que é preciso para ativar. Revisar este doc = decidir o que liga para cada agência/cliente.
> Fonte de verdade no código: `config/automations.php` (catálogo) + `app/Automations/*` (handlers). Atualizado: 2026-11-01 (BUG-07).

## Como o motor funciona (2 minutos de contexto)

1. **Catálogo fixo** (`config/automations.php`): 13 automações. Nenhuma roda por padrão — **tudo nasce desligado**.
2. **Ativação em 2 camadas:**
   - **Camada 1 — agência:** o super_admin liga a automação em **`/automations`** (toggle + horário + canais).
   - **Camada 2 — cliente** (só para as marcadas *por cliente*): opt-in individual na **matriz `/automations/clients`** ou na ficha do cliente. Sem opt-in, aquele cliente não recebe nada — ausência = desligado.
3. **Dois tipos de gatilho:**
   - **Agendadas** (`schedule`): o cron (`/queue/scheduler`, Hostinger a cada 1–5 min) varre as regras ativas e roda no horário configurado.
   - **Por evento** (`event`): disparam na hora, dentro da ação que as causa (ex.: cliente aprovou o plano).
4. **Idempotência:** toda automação grava um `dedupe_key` no log — **nunca envia a mesma mensagem duas vezes** (ex.: lembrete do dia 3 da fatura X só sai uma vez, mesmo se o cron rodar 10 vezes).
5. **Canais:** WhatsApp (Evolution API, com **espaçamento de 8s entre envios** por agência — ritmo humano, anti-ban), E-mail (SMTP, templates i18n) e In-app (sino de notificações). O idioma da mensagem ao cliente segue o idioma do cliente. **Canal desmarcado na regra não entrega** (BUG-07): o envio não acontece e a timeline mostra a linha como *Não enviado*, com o motivo. O canal barra o **aviso**, não o trabalho: automação que também faz algo dentro do sistema (nº 3 gerar fatura, nº 5 rascunho da semana seguinte, nº 9 tarefas de produção) continua fazendo com todos os canais desmarcados — **para desligar a automação, use o toggle dela.** Além disso, canal barrado não consome o passo: o `dedupe_key` fica livre e a rodada seguinte entrega — desde que a consulta daquela automação ainda devolva a linha (o lembrete D-3 da fatura, por exemplo, só existe no dia D-3; marcar o canal no dia seguinte não recupera aquele aviso).
6. **Auditoria:** toda entrega (ou falha, com motivo) aparece na **timeline `/automations/deliveries`** — responde "a cliente diz que não recebeu" sem abrir o banco.

> ⚠️ **Pré-requisito dos canais externos:** WhatsApp exige a instância Evolution conectada (QR code em `/settings/whatsapp`) — **ainda não validada em produção (INT-01)**. As automações in-app funcionam sem nada disso.
>
> ⚠️ **O canal "E-mail" ainda não entrega nada** (é o item **WPP-01**): ele existe no catálogo e no checkbox, mas nenhuma automação tem caminho de envio por e-mail. Marcar só "E-mail" numa automação que fala com a cliente (nº 1, 2, 4, 6, 12) não entrega nada — a timeline mostra a linha do WhatsApp como *Não enviado*. Recomendação até o WPP-01: ativar primeiro as in-app (que não dependem de nada) e as internas; as de cliente, com **WhatsApp** marcado, depois da validação INT-01.

---

## Automações voltadas ao CLIENTE (opt-in por cliente)

Estas falam com o cliente da agência. Exigem: agência ativou **e** cliente marcado na matriz.

### 1. Lembrete de fatura a vencer — `billing.invoice_due_reminder`
- **O que faz:** avisa o cliente **3 dias antes** do vencimento de fatura em aberto (status `sent`/`partial`).
- **Quando roda:** diária, padrão 08:00.
- **Canais sugeridos:** WhatsApp + e-mail.
- **Detalhe:** 1 aviso por fatura (dedupe `invoice:{id}:due3`).
- **Valor prático:** reduz atraso sem constranger — o aviso chega antes de virar cobrança.

### 2. Lembrete de fatura vencida — `billing.invoice_overdue`
- **O que faz:** cobra a fatura não paga em **degraus: 1, 7, 14 e 30 dias** de atraso. Para de cobrar quando a fatura é paga/cancelada.
- **Quando roda:** diária, padrão 09:00.
- **Canais sugeridos:** WhatsApp + e-mail.
- **Detalhe:** 1 mensagem por degrau (não bombardeia o cliente todo dia).
- **Valor prático:** é a régua de cobrança automática — o que planilha nenhuma faz sozinha.

### 3. Gerar fatura recorrente — `billing.recurring_invoice`
- **O que faz:** para cada **contrato recorrente ativo**, gera a fatura do período — mensal, trimestral, semestral ou anual (calculado a partir da data de início do contrato). A fatura nasce com o valor do contrato.
- **Quando roda:** diária, padrão 06:00 (gera no mês certo; idempotente por contrato+período — jamais duplica no mesmo ciclo).
- **Canais:** nenhum, na prática — ela **não manda mensagem**: a fatura passa a existir no sistema e é a agência que cobra. O checkbox de canal desta automação não é consultado pelo motor, então desmarcá-lo **não** para a emissão: para isso, desligue o toggle dela.
- **Valor prático:** o faturamento do mês inteiro sai sem ninguém abrir o sistema. **É a automação que sustenta a decisão de cobrança manual** — o sistema gera, você só acompanha o recebimento.

### 4. Lembrete de aprovação de conteúdo — `content.approval_reminder`
- **O que faz:** cobra o cliente quando um plano está **enviado e sem resposta**, em degraus de **2, 4 e 7 dias** desde o envio. A mensagem leva o **link público do portal** (o cliente aprova sem login). Como a nº 6, cliente sem portal ligado — ou sem o link gerado — é pulado, com a linha *Não enviado* e o motivo na timeline: a mensagem é só o link. O passo não é consumido, então, gerado o acesso, o degrau seguinte cobra.
- **Quando roda:** diária, padrão 10:00.
- **Canais sugeridos:** WhatsApp + e-mail.
- **Valor prático:** aprovação parada trava a produção da semana — esta é a automação que destrava o fluxo de conteúdo.

### 5. Criar plano da próxima semana ao aprovar — `content.approved_create_next_plan`
- **O que faz:** no momento em que o cliente **aprova** o plano da semana, nasce o **rascunho da semana seguinte** — pelo modelo semanal do cliente (se houver) ou pela estrutura do plano aprovado. Se a próxima semana já tem plano criado manualmente, não duplica. Equipe avisada in-app.
- **Gatilho:** evento (na aprovação), sem agendamento.
- **Canais:** in-app. Desmarcar "No sistema" cala o aviso, **não** a automação: o rascunho continua nascendo (para desligar a automação, use o toggle dela).
- **Valor prático:** a esteira de planejamento nunca zera — aprovou, a próxima já existe.

### 6. Relatório mensal ao cliente — `report.client_monthly`
- **O que faz:** no início do mês, envia a cada cliente ativo (com opt-in) o **link do painel dele no portal** (conteúdo + financeiro + tráfego + orgânico dos últimos 30 dias). Cliente sem portal ligado — ou sem o link gerado, que é como o cliente nasce quando a automação de onboarding está desligada — é pulado, com a linha *Não enviado* na timeline dizendo o motivo: a mensagem é só o link.
- **Quando roda:** mensal, padrão dia 1 às 08:00.
- **Canais:** WhatsApp (e-mail entra no WPP-01).
- **Valor prático:** prestação de contas automática — o cliente sente o serviço mesmo no mês em que não pediu nada.

---

## Automações voltadas à EQUIPE (toggle único da agência)

Estas não falam com cliente — organizam a operação interna. Basta a agência ativar.

### 7. Marcar faturas vencidas — `billing.mark_overdue`
- **O que faz:** rotina interna: vira `overdue` toda fatura em aberto que passou do vencimento. **Não envia nada a ninguém** — mantém o status correto (e alimenta o "Meu dia", os KPIs e a automação nº 2).
- **Quando roda:** diária, padrão 00:30.
- **Recomendação:** **ligar sempre.** Sem ela, "fatura vencida" não existe como status confiável.

### 8. Escalonar aprovação parada — `content.approval_escalation`
- **O que faz:** quando um plano segue sem aprovação **5+ dias** após o envio, avisa a equipe **in-app** (uma vez por plano). É o complemento interno da automação nº 4: o cliente foi cobrado; agora o gestor sabe que precisa ligar.
- **Quando roda:** diária, padrão 11:00.

### 9. Criar tarefas ao aprovar plano — `content.approved_create_tasks`
- **O que faz:** quando o cliente aprova o plano, cria **automaticamente as tarefas de produção** dos itens aprovados para a equipe (com aviso in-app).
- **Gatilho:** evento (na aprovação).
- **Valor prático:** aprovação vira produção sem ninguém transcrever posts para o kanban.

### 10. Alerta de contrato expirando — `contract.expiring`
- **O que faz:** avisa a equipe (in-app) quando um contrato ativo vence em **30, 15, 7 e 1 dia(s)**.
- **Quando roda:** diária, padrão 07:00.
- **Valor prático:** renovação deixa de depender de memória — 30 dias é tempo de renegociar.

### 11. Alerta de tarefa atrasada — `task.sla_overdue`
- **O que faz:** avisa o responsável (in-app) quando uma tarefa passa do prazo, em degraus de **1, 3 e 7 dias** de atraso.
- **Quando roda:** diária, padrão 07:30.

### 12. Onboarding automático de cliente — `client.onboarding`
- **O que faz:** ao **cadastrar um cliente novo**, garante o token do portal e envia a **mensagem de boas-vindas com o link do portal** (por WhatsApp; e-mail é o WPP-01).
- **Gatilho:** evento (no cadastro).
- **Valor prático:** primeira impressão profissional sem trabalho manual — o cliente recebe o acesso no minuto 1.

### 13. Resumo diário da equipe — `digest.team_daily`
- **O que faz:** um resumo in-app por dia: tarefas que vencem hoje, planos aguardando aprovação e faturas em aberto.
- **Quando roda:** diária, padrão 07:00.
- **Nota:** com o dashboard "Meu dia" (PROD-08, 23/07), o digest virou o *push* e o dashboard o *pull* da mesma informação — os dois convivem; se incomodar, desligue o digest.

---

## Kit recomendado de ativação (sugestão para revisão)

| Prioridade | Automação | Por quê |
|------------|-----------|---------|
| **Liga já (sem risco)** | 7 · marcar vencidas | Rotina interna, sem mensagem |
| | 9 · tarefas ao aprovar · 5 · próxima semana ao aprovar | Só criam trabalho interno — o coração da esteira de conteúdo |
| | 8, 10, 11, 13 · alertas internos in-app | Ninguém de fora recebe nada |
| | 3 · fatura recorrente | Gera a fatura e **não fala com a cliente** — sustenta a cobrança manual dos contratos |
| **Após validar WhatsApp (INT-01)** | 1 e 2 · régua de cobrança | É o canal que entrega hoje (e-mail é o WPP-01) |
| | 4 · lembrete de aprovação | O canal certo desta é WhatsApp — é onde o cliente responde |
| | 6 · relatório mensal | Valor percebido alto, custo zero — exige o link do portal gerado |
| | 12 · onboarding | Boas-vindas com o acesso ao portal no minuto 1 |

**Passo a passo de ativação:** `/automations` → ligar o toggle da automação (+ ajustar horário/canais se quiser) → para as 6 de cliente, abrir `/automations/clients` e marcar cada cliente que deve receber → acompanhar em `/automations/deliveries` se as entregas estão saindo (com motivo do erro quando falham).

## O que ainda NÃO existe (para não confundir na revisão)

- **Alertas de tráfego pago** (CPA estourou, campanha pausada, sync quebrado → avisar gestor) — motor pronto, automação não escrita (TRAF-01 no Plano Mestre).
- **Régua por e-mail com paridade total do WhatsApp** e catálogo único de eventos×canais×idioma — é o item **CONT-AVISOS** (desenhar junto com a validação INT-01).
- **Preferências de notificação por usuário** (quero e-mail, não quero in-app) — UX-06.
- **Automação de opt-out do cliente** ("PARAR" no WhatsApp) — anotado como parte do INT-01/CONT-AVISOS.
