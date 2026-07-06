# YVE Agency — Guia do Sistema

> Versão: Junho 2026 · PHP 8.3 · Supabase PostgreSQL · Tailwind + Alpine.js

---

## Índice

1. [Usuários padrão e credenciais](#1-usuários-padrão-e-credenciais)
2. [Pós-primeiro-login: o que fazer antes de testar](#2-pós-primeiro-login-o-que-fazer-antes-de-testar)
3. [Dois níveis de acesso: Platform vs Tenant](#3-dois-níveis-de-acesso-platform-vs-tenant)
4. [Perfis (Roles) e suas permissões](#4-perfis-roles-e-suas-permissões)
5. [Planos de Assinatura e limites](#5-planos-de-assinatura-e-limites)
6. [Módulos — passo a passo de uso](#6-módulos--passo-a-passo-de-uso)
   - [Dashboard](#61-dashboard)
   - [Clientes](#62-clientes)
   - [Planos de Conteúdo](#63-planos-de-conteúdo)
   - [Aprovações](#64-aprovações)
   - [Portal do Cliente](#65-portal-do-cliente)
   - [Financeiro (Contratos, Faturas, Pagamentos)](#66-financeiro)
   - [Tráfego Pago](#67-tráfego-pago)
   - [Orgânico](#68-orgânico)
   - [Tarefas](#69-tarefas)
   - [IA & Insights](#610-ia--insights)
   - [Ações em Campanhas](#611-ações-em-campanhas)
   - [WhatsApp](#612-whatsapp)
   - [Configurações da Agência](#613-configurações-da-agência)
   - [Relatório Executivo](#614-relatório-executivo)
   - [Usuários e Perfis de Acesso](#615-usuários-e-perfis-de-acesso)
7. [Área do Platform Admin (`/admin`)](#7-área-do-platform-admin-admin)
8. [Cron Jobs e Queue](#8-cron-jobs-e-queue)
9. [Variáveis de ambiente importantes](#9-variáveis-de-ambiente-importantes)
10. [Fluxo de teste recomendado (do zero)](#10-fluxo-de-teste-recomendado-do-zero)

---

## 1. Usuários padrão e credenciais

Dois usuários são criados automaticamente pelo seeder (`vendor/bin/phinx seed:run`):

| Tipo | E-mail | Senha padrão | Nível |
|------|--------|--------------|-------|
| **Platform Admin** | `platform@yveagency.com` | `platform123!` | Acesso global (`/admin/*`) |
| **Super Admin (tenant)** | `admin@yveagency.com` | `admin123!` | Acesso total à agência padrão |

> **⚠ Altere as senhas após o primeiro login.** O super_admin mora dentro da agência "YVE Agency" (agency_id = 1). O platform admin não pertence a nenhuma agência.

### Como trocar a senha do super_admin

1. Faça login em `/login` com `admin@yveagency.com` / `admin123!`
2. Acesse `/usuarios` → clique no seu usuário → **Editar**
3. Preencha os campos "Nova senha" e "Confirmar senha"
4. Salve — a nova senha passa a valer imediatamente

### Como trocar a senha do platform admin

1. Faça login em `/login` com `platform@yveagency.com` / `platform123!`
2. Acesse `/admin/usuarios` → clique no usuário → **Editar**
3. Siga o mesmo procedimento acima

---

## 2. Pós-primeiro-login: o que fazer antes de testar

Sequência recomendada antes de qualquer teste real:

```
1. [Platform Admin] /admin/planos         → criar pelo menos 1 plano (ex: "Free", 5 clientes, 2 usuários)
2. [Platform Admin] /admin/assinaturas    → atribuir o plano à agência YVE Agency
3. [Platform Admin] /admin/configuracoes  → configurar Evolution API (WhatsApp), Meta App e IA
4. [Super Admin]    /configuracoes        → preencher dados da agência (nome, CNPJ, logo, e-mail)
5. [Super Admin]    /clientes/novo        → criar ao menos 1 cliente
6. [Super Admin]    /usuarios/novo        → criar 1 usuário com perfil diferente (ex: Social Media)
```

Sem o passo 1–2, o sistema vai bloquear a criação de clientes e usuários com mensagem de limite de plano.

---

## 3. Dois níveis de acesso: Platform vs Tenant

```
┌─────────────────────────────────────────────────────────┐
│  PLATFORM ADMIN  →  /admin/*                            │
│  (platform@yveagency.com)                               │
│  - Gerencia todas as agências (tenants)                 │
│  - Cria/edita planos de assinatura                      │
│  - Atribui planos às agências                           │
│  - Configura credenciais globais (WhatsApp, Meta, IA)   │
│  - Nunca acessa o painel de uma agência específica      │
└─────────────────────────────────────────────────────────┘
         ↓ cria e gerencia
┌─────────────────────────────────────────────────────────┐
│  TENANT (Agência)  →  /dashboard, /clientes, etc.       │
│  (admin@yveagency.com e demais usuários)                │
│  - Trabalha dentro do escopo da sua agência             │
│  - Dados sempre isolados por agency_id                  │
│  - Não vê dados de outras agências                      │
└─────────────────────────────────────────────────────────┘
         ↓ portal público
┌─────────────────────────────────────────────────────────┐
│  PORTAL DO CLIENTE  →  /portal/{token}                  │
│  - Sem login — acesso via token único por cliente       │
│  - Vê seus planos de conteúdo, faturas e contratos      │
│  - Pode aprovar/solicitar revisão nos planos            │
└─────────────────────────────────────────────────────────┘
```

---

## 4. Perfis (Roles) e suas permissões

Os perfis abaixo são criados automaticamente pelo seeder:

| Slug | Nome | O que pode fazer |
|------|------|-----------------|
| `super_admin` | Super Admin | **Tudo** — todas as permissões |
| `agency_admin` | Admin da Agência | **Tudo** — equivalente ao super_admin dentro da agência |
| `traffic_manager` | Gestor de Tráfego | Dashboard, clientes (próprios), métricas de anúncios e orgânico, ações em campanha, IA, tarefas |
| `social_media` | Social Media | Dashboard, clientes (próprios), planos de conteúdo (criar/editar/enviar), aprovações, orgânico, tarefas, WhatsApp/e-mail (leitura) |
| `designer` | Designer | Dashboard, clientes (próprios), visualizar planos e aprovações, tarefas |
| `financial` | Financeiro | Dashboard, contratos, faturas, pagamentos, relatórios financeiros |
| `client_admin` | Cliente — Admin | Dashboard, ver e aprovar planos de conteúdo |
| `client_approver` | Cliente — Aprovador | Ver e aprovar planos de conteúdo |
| `client_financial` | Cliente — Financeiro | Ver faturas, contratos e pagamentos |

> Os perfis do sistema têm `agency_id = NULL` (globais). Você pode criar perfis personalizados por agência em `/usuarios/perfis/novo`.

---

## 5. Planos de Assinatura e limites

Os limites são verificados nos controllers antes de criar recursos. Sem um plano ativo, criações serão bloqueadas.

### Recursos com limite de plano

| Recurso | Chave interna | Onde é verificado |
|---------|--------------|------------------|
| Clientes | `clients` | `ClientController::store()` |
| Usuários | `users` | `UserController::store()` |
| Contas de anúncio (Meta) | `meta_accounts` | `AdsAccountController::store()` |
| Contas orgânicas | `organic_accounts` | `OrganicController::connect()` |

### Como criar um plano no admin

1. Acesse `/admin/planos/novo`
2. Preencha nome, preço, período (monthly/yearly)
3. Defina os limites por recurso (0 = ilimitado)
4. Salve e depois atribua em `/admin/assinaturas`

---

## 6. Módulos — passo a passo de uso

### 6.1 Dashboard

**URL:** `/` ou `/dashboard`  
**Permissão:** `dashboard.view`

Exibe KPIs consolidados da agência: clientes ativos, faturas em aberto, tarefas pendentes, receita do mês. Gráfico de receita mensal (Chart.js). Filtro por período (since/until).

---

### 6.2 Clientes

**URLs:** `/clientes` | `/clientes/novo` | `/clientes/{id}`  
**Permissões:** `clients.view`, `clients.create`, `clients.edit`, `clients.delete`

**Criar um cliente:**
1. `/clientes/novo` → preencher nome (obrigatório), dados fiscais, segmento, moeda
2. Após salvar, o cliente aparece na listagem (paginada, busca por nome/e-mail)

**Acesso de usuários ao cliente:**
- Em `/clientes/{id}/acesso` você define quais usuários da agência podem ver este cliente
- Usuários com `clients.view_all` veem todos; os demais só veem clientes que têm acesso explícito

**Portal do cliente (controle):**
- Na página do cliente existe o bloco "Portal do Cliente"
- Ativar/desativar o portal: botão "Ativar Portal"
- Regenerar token: botão "Regenerar Link" — o link antigo para de funcionar imediatamente
- O link do portal é: `http://localhost:8000/portal/{token}`

---

### 6.3 Planos de Conteúdo

**URLs:** `/conteudo` | `/conteudo/novo`  
**Permissões:** `content.view`, `content.create`, `content.edit`, `content.send_to_approval`

**Fluxo completo:**
```
Rascunho → Enviar para aprovação → Aprovado / Em revisão → Publicado
```

1. `/conteudo/novo` → selecionar cliente, título, semana (início/fim), notas
2. Na página do plano (`/conteudo/{id}`) adicionar itens: data de publicação, tipo (feed/reels/story/etc.), título, legenda, roteiro, CTA, link do Drive
3. Quando pronto: botão "Enviar para Aprovação" → status muda para `pending_approval`
4. O cliente recebe o plano no portal para aprovar ou solicitar revisão
5. Ao aprovar: status → `approved`

**Tipos de conteúdo disponíveis:**  
`feed`, `reels`, `story`, `carousel`, `tiktok`, `youtube`, `email`, `blog`

---

### 6.4 Aprovações

**URLs:** `/aprovacoes` | `/aprovacoes/{planId}`  
**Permissões:** `approvals.view`, `approvals.comment`, `approvals.approve`

Interface interna da agência para acompanhar o status de aprovação dos planos. Os usuários com perfil `client_admin` ou `client_approver` usam esta tela para dar feedback nos itens individualmente (aprovado, mudanças solicitadas, comentário).

> **Atenção:** o fluxo de aprovação do cliente final é pelo **Portal** (`/portal/{token}`), não por esta rota.

---

### 6.5 Portal do Cliente

**URL:** `/portal/{token}`  
**Acesso:** público (sem login) via token único

O token é gerado automaticamente ao criar o cliente. Para encontrá-lo:
1. Acesse `/clientes/{id}` → bloco "Portal do Cliente"
2. Copie o link completo ou clique em "Abrir Portal"

**O que o cliente vê no portal:**
- KPIs: planos pendentes, planos aprovados, faturas em aberto, faturas pagas
- Métricas de tráfego pago (últimos 30 dias) — se tiver contas conectadas
- Métricas orgânicas (últimos 30 dias) — se tiver contas conectadas
- Planos de conteúdo com botões Aprovar / Solicitar Revisão
- Faturas em aberto com valor e vencimento
- Contratos ativos

**Para testar o portal:**
1. Crie um cliente
2. Em `/clientes/{id}`: ative o portal e copie o link
3. Abra o link em aba anônima (sem estar logado)

---

### 6.6 Financeiro

**Permissões:** `contracts.view`, `invoices.view`, `payments.view`

#### Contratos

**URL:** `/contratos`

1. `/contratos/novo` → selecionar cliente, título, valor mensal/total, datas de início/fim, cláusulas
2. Enviar para assinatura (muda status para `sent`)
3. Ao assinar: status → `signed`
4. Contratos ativos alimentam o seletor no formulário de fatura

#### Faturas

**URL:** `/faturas`

1. `/faturas/nova` → selecionar cliente, vincular contrato (opcional), título, vencimento
2. Adicionar itens (descrição, quantidade, valor unitário) — totais calculados automaticamente com desconto e imposto
3. Status: `draft` → `sent` (botão Enviar) → `paid` (ao registrar pagamento total) / `overdue` (cron automático)
4. `/faturas/{id}/pdf` → visualização para impressão/PDF no navegador

**Atenção ao vencimento:** um cron marca automaticamente como `overdue` as faturas `sent` com `due_date < hoje`. Sem o cron rodando, marque manualmente ou use a ação na listagem.

#### Pagamentos

**URL:** `/pagamentos`

Registra entradas parciais ou totais em uma fatura.  
`/pagamentos/novo?invoice_id={id}` → preencher valor, data, método (pix/ted/cartão/boleto), referência.  
A fatura atualiza `amount_paid` automaticamente e muda status para `partial` ou `paid`.

---

### 6.7 Tráfego Pago

**URLs:** `/trafego` | `/trafego/contas`  
**Permissões:** `ads_metrics.view`

#### Conectar conta de anúncio

**Opção 1 — OAuth (recomendado):**
1. O Platform Admin precisa configurar `meta_app_id` e `meta_app_secret` em `/admin/configuracoes`
2. Acesse `/trafego/contas/nova` → botão "Conectar com Facebook"
3. Autorize o app → selecione a conta de anúncio → salve

**Opção 2 — Manual:**
1. `/trafego/contas/nova` → preencher plataforma, ID da conta, nome, moeda, access token
2. Clique em "Salvar"

#### Sincronizar métricas

- Botão "Sincronizar" na listagem de contas (`/trafego/contas`)
- Ou via cron: `GET /queue/sync-ads?token={QUEUE_SECRET}`

#### Visualizar dados

- `/trafego` → dashboard geral com gráficos de spend, impressões, cliques, ROAS por conta/período
- `/trafego/campanhas/{id}` → detalhamento de uma campanha
- Filtros por período (since/until) e conta

---

### 6.8 Orgânico

**URLs:** `/organico` | `/organico/contas`  
**Permissões:** `organic_metrics.view`

#### Conectar conta

1. `/organico/conectar` → selecionar plataforma (instagram/facebook/tiktok/youtube/linkedin), cliente, preencher dados manuais (page_id, access_token)
2. Após conectar, a conta aparece em `/organico/contas`

#### Sincronizar

- Botão "Sincronizar" por conta
- Ou via cron: `GET /queue/sync-organic?token={QUEUE_SECRET}`

#### Métricas disponíveis

Alcance, impressões, engajamento (likes + comentários + compartilhamentos + salvamentos), seguidores ganhos, top posts por alcance/engajamento.

---

### 6.9 Tarefas

**URL:** `/tarefas`  
**Permissões:** `tasks.view`, `tasks.create`, `tasks.edit`

**Criar tarefa:**
1. `/tarefas/nova` → título (obrigatório), cliente (opcional), responsável (opcional), prioridade, data de entrega, descrição
2. A tarefa aparece no board kanban em `/tarefas`

**Board Kanban:**
- 4 colunas: A Fazer / Em Andamento / Revisão / Concluído
- Filtros por status, cliente, responsável, prioridade

**Notificação automática:**  
Ao criar ou atualizar uma tarefa com `assigned_to` diferente do usuário atual, o sistema dispara notificação in-app + e-mail para o responsável (requer SMTP configurado).

---

### 6.10 IA & Insights

**URLs:** `/ia` | `/ia/gerar`  
**Permissões:** `ai_insights.view`, `ai.generate_report`

**Pré-requisito:** configurar `openai_api_key` ou `anthropic_api_key` em `/admin/configuracoes`.

**Gerar insight:**
1. `/ia/gerar` → selecionar cliente, período, tipo de análise
2. O sistema envia os dados de métricas para a IA e retorna um relatório textual
3. Salvo automaticamente e acessível em `/ia/{id}`

**Recomendações:**
- `/ia/recomendacoes` → lista recomendações geradas automaticamente (ações sugeridas nas campanhas)

---

### 6.11 Ações em Campanhas

**URLs:** `/trafego/acoes`  
**Permissões:** `ads_actions.view`, `ads_actions.request`, `ads_actions.approve`, `ads_actions.execute`

Fluxo de aprovação para mudanças nas campanhas (aumentar orçamento, pausar, etc.):

```
Solicitado → Aprovado → Executado
        ↓
     Rejeitado
```

1. `/trafego/acoes/nova` → selecionar conta, campanha, tipo de ação, justificativa
2. Um aprovador (com `ads_actions.approve`) acessa `/trafego/acoes/{id}` e aprova/rejeita
3. Aprovado → quem tem `ads_actions.execute` clica em "Executar" para aplicar na conta real

---

### 6.12 WhatsApp

**URLs:** `/configuracoes/whatsapp`  
**Permissões:** `whatsapp.manage`

**Pré-requisito:** Evolution API configurada em `/admin/configuracoes` (URL + API Key).

1. `/configuracoes/whatsapp` → botão "Ativar instância"
2. O sistema cria a instância via Evolution API
3. Acesse `/configuracoes/whatsapp/qr` para escanear o QR Code
4. Após conexão: status muda para "connected"
5. Configure o webhook para receber mensagens: botão "Configurar Webhook"

> O sistema usa a Evolution API para envio/recebimento de mensagens. Sem ela configurada, este módulo não funciona.

---

### 6.13 Configurações da Agência

**URL:** `/configuracoes`  
**Permissões:** `settings.edit`

Campos disponíveis:
- **Dados da agência:** nome, razão social, CNPJ, e-mail, telefone, site
- **Logo:** URL da imagem
- **Preferências:** fuso horário, idioma padrão (pt/en/es)

> Atenção: após o seeder, os campos `email`, `phone`, `website`, `logo_url`, `language` só existem na tabela após a migration `20260610000012_extend_agencies`. Rode `vendor/bin/phinx migrate` se ainda não rodou.

---

### 6.14 Relatório Executivo

**URL:** `/relatorio-executivo`  
**Permissão:** `dashboard.view`

Dashboard consolidado com todos os clientes:
- Receita total, recebida e pendente
- Gráfico de receita mensal (12 meses)
- KPIs de conteúdo, tarefas, orgânico e tráfego pago
- Tabela de resumo por cliente com link para relatório individual
- Top 10 campanhas por spend

**Relatório por cliente (PDF):**
- Link "Relatório PDF →" na tabela de clientes
- Ou `/relatorio-executivo/cliente/{id}?since=YYYY-MM-DD&until=YYYY-MM-DD`
- O navegador renderiza uma página otimizada para impressão — use Ctrl+P / Cmd+P e "Salvar como PDF"

---

### 6.15 Usuários e Perfis de Acesso

**URLs:** `/usuarios` | `/usuarios/novo` | `/usuarios/perfis`  
**Permissões:** `users.view`, `users.create`, `roles.view`

**Criar usuário:**
1. `/usuarios/novo` → nome, e-mail, senha, perfil (role)
2. O usuário recebe acesso apenas aos clientes vinculados explicitamente (a menos que seu perfil tenha `clients.view_all`)

**Recuperação de senha:**
- O usuário acessa `/esqueci-senha` e recebe e-mail com link (válido por 1 hora)
- Requer SMTP configurado. Sem SMTP, gere o token manualmente no banco.

**Criar perfil personalizado:**
1. `/usuarios/perfis/novo` → nome, slug, selecionar permissões
2. Perfis criados aqui têm `agency_id` da agência atual (não são globais)

---

## 7. Área do Platform Admin (`/admin`)

Acessível apenas com `platform@yveagency.com` (ou qualquer user com `is_platform_admin = true`).

| URL | Função |
|-----|--------|
| `/admin` | Dashboard: total de agências, usuários, MRR |
| `/admin/tenants` | Listar, criar, editar, suspender agências |
| `/admin/tenants/criar` | Nova agência (cria agência + super_admin automaticamente) |
| `/admin/usuarios` | Ver todos os usuários de todas as agências |
| `/admin/planos` | CRUD de planos de assinatura |
| `/admin/planos/novo` | Criar plano com limites por recurso |
| `/admin/assinaturas` | Atribuir planos às agências |
| `/admin/configuracoes` | Configurações globais (veja abaixo) |

### Configurações globais (`/admin/configuracoes`)

| Seção | Campos |
|-------|--------|
| **Evolution API** | URL, API Key, Ativar/Desativar |
| **E-mail SMTP** | Host, porta, usuário, remetente, nome, criptografia |
| **Meta Ads (Facebook)** | App ID, App Secret |
| **IA** | Provedor (openai/anthropic), Modelo, API Keys |

> Os campos de API Key são mascarados (`••••••••`) após salvar. Para alterar, basta digitar o novo valor. Deixar em branco mantém o valor atual.

---

## 8. Cron Jobs e Queue

Os endpoints de cron são **públicos** mas protegidos por token secreto (`QUEUE_SECRET` no `.env`).

| Endpoint | Função | Frequência recomendada |
|----------|--------|----------------------|
| `GET /queue/run?token={QUEUE_SECRET}` | Processa fila de notificações pendentes | A cada 1–5 minutos |
| `GET /queue/sync-ads?token={QUEUE_SECRET}` | Sincroniza métricas de todas as contas de anúncio ativas | 1× por dia (madrugada) |
| `GET /queue/sync-organic?token={QUEUE_SECRET}` | Sincroniza métricas orgânicas de todas as contas ativas | 1× por dia (madrugada) |
| `GET /queue/sync-drive?token={QUEUE_SECRET}` | Reconcilia as galerias de conteúdo com o Google Drive (reflete arquivos apagados/renomeados direto no Drive) | 1× por dia ou a cada poucas horas |

> **Sobre o sync do Drive:** o escopo OAuth usado é `drive.file`, que só enxerga arquivos criados pelo próprio sistema. A sincronização reflete exclusões/renomeações desses arquivos; arquivos adicionados **manualmente** na interface do Google Drive não aparecem (exigiria escopo mais amplo com verificação do Google). Há também um botão **"Sincronizar"** na galeria de cada cliente (`/clientes/{id}/conteudos`) para reconciliação sob demanda.

Exemplo de crontab:
```cron
*/5 * * * * curl -s "http://localhost:8000/queue/run?token=SEU_TOKEN" > /dev/null
0 3 * * * curl -s "http://localhost:8000/queue/sync-ads?token=SEU_TOKEN" > /dev/null
0 4 * * * curl -s "http://localhost:8000/queue/sync-organic?token=SEU_TOKEN" > /dev/null
0 */6 * * * curl -s "http://localhost:8000/queue/sync-drive?token=SEU_TOKEN" > /dev/null
```

> O `QUEUE_SECRET` está no `.env`. Nunca exponha esse valor publicamente. Consulte `docs/CRON.md` para detalhes adicionais.

---

## 9. Variáveis de ambiente importantes

| Variável | Descrição | Obrigatória |
|----------|-----------|-------------|
| `APP_URL` | URL base da aplicação (sem barra final) | Sim |
| `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS` | Credenciais Supabase | Sim |
| `QUEUE_SECRET` | Token para os endpoints de cron | Sim |
| `MAIL_HOST`, `MAIL_USERNAME`, `MAIL_PASSWORD` | SMTP para e-mails transacionais | Para e-mail funcionar |
| `AI_PROVIDER`, `AI_MODEL` | Provider padrão de IA (`openai` ou `anthropic`) | Para IA funcionar |

> Salvar estas credenciais também em `/admin/configuracoes` sobrescreve os valores do `.env` para Meta Ads, Evolution API e IA.

---

## 10. Fluxo de teste recomendado (do zero)

```
┌─ SETUP ───────────────────────────────────────────────────┐
│ 1. vendor/bin/phinx migrate                               │
│ 2. vendor/bin/phinx seed:run                              │
│ 3. php -S localhost:8000 -t public/                       │
└───────────────────────────────────────────────────────────┘

┌─ PLATFORM ADMIN ──────────────────────────────────────────┐
│ 4. Login: platform@yveagency.com / platform123!           │
│ 5. /admin/planos/novo → "Starter" (10 clientes, 5 users)  │
│ 6. /admin/assinaturas → atribuir "Starter" à YVE Agency   │
│ 7. /admin/configuracoes → preencher SMTP (Mailtrap ok)    │
│ 8. Logout                                                 │
└───────────────────────────────────────────────────────────┘

┌─ SUPER ADMIN ─────────────────────────────────────────────┐
│ 9.  Login: admin@yveagency.com / admin123!                │
│ 10. /configuracoes → preencher dados da agência           │
│ 11. /clientes/novo → "Acme Ltda" (cliente de teste)       │
│ 12. /usuarios/novo → "Maria Silva" (perfil: Social Media) │
│ 13. /contratos/novo → contrato para Acme Ltda             │
│ 14. /faturas/nova → fatura para Acme Ltda                 │
│ 15. /faturas/{id}/enviar → muda para "sent"               │
│ 16. /pagamentos/novo → registrar pagamento parcial        │
│ 17. /conteudo/novo → plano semana atual para Acme Ltda    │
│ 18. /conteudo/{id} → adicionar 3 itens de conteúdo        │
│ 19. /conteudo/{id}/enviar → enviar para aprovação         │
│ 20. /tarefas/nova → tarefa para Maria Silva               │
│ 21. /clientes/{id} → ativar portal + copiar link          │
└───────────────────────────────────────────────────────────┘

┌─ PORTAL DO CLIENTE ───────────────────────────────────────┐
│ 22. Abrir link do portal em aba anônima                   │
│ 23. Ver métricas, planos e faturas                        │
│ 24. Clicar "Aprovar" no plano de conteúdo                 │
│ 25. Voltar ao painel → /conteudo/{id} → status "approved" │
└───────────────────────────────────────────────────────────┘

┌─ USUÁRIO SOCIAL MEDIA ────────────────────────────────────┐
│ 26. Logout → Login com maria@... (criada no passo 12)     │
│ 27. Verificar que só vê conteúdo e tarefas                │
│ 28. /clientes → só vê Acme Ltda (se o acesso foi dado)    │
│ 29. Verificar que /financeiro redireciona (sem permissão) │
└───────────────────────────────────────────────────────────┘

┌─ RELATÓRIO EXECUTIVO ─────────────────────────────────────┐
│ 30. Logout → Login como super admin                       │
│ 31. /relatorio-executivo → ver dashboard consolidado      │
│ 32. Clicar "Relatório PDF →" → Ctrl+P → Salvar como PDF   │
└───────────────────────────────────────────────────────────┘
```

---

## Dicas de troubleshooting

| Sintoma | Causa provável | Solução |
|---------|---------------|---------|
| "Limite de clientes atingido" | Sem plano ativo | `/admin/assinaturas` → atribuir plano |
| Portal retorna 404 | Token inválido ou portal desativado | Verificar em `/clientes/{id}` se portal está ativo |
| E-mail não chega | SMTP não configurado | `/admin/configuracoes` → seção E-mail |
| Métricas de anúncio zeradas | Sync não rodou | `GET /queue/sync-ads?token=...` manualmente |
| "Não foi possível enviar o plano" | Plano sem itens ou status inválido | Adicionar ao menos 1 item antes de enviar |
| Senha reset não chega | SMTP inativo | Configurar SMTP ou resetar direto no banco |
| WhatsApp "desconectado" | QR code expirado | `/configuracoes/whatsapp/qr` → escanear novamente |
