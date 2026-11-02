# YVE Agency — Plano Mestre v4

> **A verdade absoluta do projeto.** Roteiro único e priorizado de correções, melhorias e evolução.
> Base: [ANALISE_PRODUTO.md](ANALISE_PRODUTO.md) (ciclo 4) · [ANALISE_SISTEMA.md](ANALISE_SISTEMA.md) · [AUTOMACOES.md](AUTOMACOES.md).
> Atualizado: 2026-10-31 · Ciclo: 2026-10 (ciclo 4) · Anterior: [historico/PLANO_MESTRE_2026-07-23.md](historico/PLANO_MESTRE_2026-07-23.md)
> Convenções: esforço em **horas** (1 dev sênior com IA, incluindo testes e migration). **% feito** = quanto já existe no código hoje. **Núcleo** = fecha a meta do ciclo; o resto pode esperar. IDs nunca são reutilizados.

**Status: 🟢 VIGENTE — aprovado pelo dono em 2026-11-01.** As 14 decisões estão registradas no fim deste arquivo. Execução começou pela Fase 0.

**Meta do ciclo (pedido do dono, 31/10):** (1) terminar a integração Meta e ter uma central de gestão; (2) tarefas nível ClickUp com automações, lembretes e chat interno; (3) automações pela Evolution com o número central da agência — lembretes para a equipe, planificações e avisos para os clientes; (4) varredura total do que está cru.

**Estado herdado do ciclo 3:** CONT-06 fase 1 ✅ (23/07) · CONT-07 ✅ (03/09) · galeria com mover/renomear/excluir/seleção múltipla ✅ · RBAC: financeiro fora dos papéis de conteúdo ✅. **Não executados:** AUTO-01, TRAF-01, QA-04, CONT-AVISOS, INT-01, INFRA-02, todo o Marco C/D — absorvidos abaixo.

**Gates em 2026-10-31:** 244 testes / 536 asserts verdes **com** o banco Docker (sem ele: 10 erros) · PHPStan nível 6 = 0 · `composer audit` = **5 advisories (1 crítico)**.

**Totais revisados em 01/11 (após as decisões):** 77 entregas, ~928 h no total (incluindo INFRA-04 suspenso, que não conta no núcleo). Mudanças sobre os 838 h originais: FE-06 de 6 h para 40 h (D14: painel inteiro em 3 idiomas), AI-02 novo (18 h), META-03 e META-07 com +4 h cada (D2: rodar em fatias no cron da Hostinger), 8 itens novos achados na execução da F0 (30 h). Visão interativa: artifact "Plano YVE Agency ciclo 4".

---

## F0 — Blindagem · 115 h · 11% feito

> Furos de segurança e bugs que já estão em produção. Tem vazamento entre agências, escalonamento de perfil e telas quebradas hoje. Nada novo entra antes disto.

| ID | Entrega | Horas | % feito | Núcleo | Depende de |
|----|---------|------:|--------:|:------:|------------|
| ✅ SEC-11 | Fronteira de tenant nos clientes e no conteúdo | 12 | 30% | ● | — |
| ✅ SEC-12 | Fechar o escalonamento de perfis (RBAC) | 8 | 10% | ● | Decisão D10 |
| ✅ SEC-13 | Escopo de agência em tráfego e tarefas | 6 | 15% | ● | — |
| ✅ SEC-14 | Cifrar o token do Instagram/Facebook orgânico | 3 | 0% | ● | — |
| ✅ BUG-02 | Recuperação de senha (hoje dá erro 500) | 5 | 0% | ● | — |
| ✅ BUG-03 | Sincronizar o Drive pode apagar a galeria | 10 | 0% | ● | — |
| ✅ BUG-04 | Tela interna de Aprovações funcionando | 10 | 20% | ● | SEC-11 |
| ✅ BUG-05 | Máquina de estados do plano à prova | 10 | 40% | ● | — |
| ✅ BUG-06 | Tarefas e notificações: bugs vivos | 6 | 0% | ● | — |
| ✅ BUG-07 | Automações respeitarem o canal escolhido | 4 | 0% | ● | — |
| ✅ SEC-15 | Trilha de auditoria onde falta | 10 | 5% | ● | — |
| ✅ SEC-16 | Rigor no dinheiro | 8 | 0% | ● | — |
| ✅ SEC-17 | Dependências com CVE e PHP de produção | 3 | 0% | ● | — |
| ✅ SEC-18 | Login e portal endurecidos | 6 | 0% | ● | — |
| ✅ QA-05 | Suíte que não mente | 14 | 5% | ● | — |
| SEC-19 | Agregados de dinheiro respeitam o alcance do usuário | 4 | 0% | ○ | Decisão do dono |
| SEC-20 | Permissão própria para excluir insight de IA | 1 | 0% | ○ | — |
| SEC-21 | Arquivos do Drive públicos por link | 6 | 0% | ● | Decisão do dono |
| SEC-22 | Pagamento simultâneo, moeda e fatura cancelada no formulário | 3 | 0% | ○ | — |
| BUG-08 | Saída para plano aprovado e plano rejeitado | 6 | 0% | ● | Decisão do dono |
| BUG-09 | Dois planos do mesmo cliente na mesma semana | 3 | 0% | ○ | — |
| BUG-10 | Envio manual do plano sem link do portal | 2 | 0% | ● | — |
| FE-07 | Mensagens de erro cruas restantes | 5 | 0% | ○ | — |

### SEC-11 · Fronteira de tenant nos clientes e no conteúdo — `12h` · 30% · núcleo
> ✅ **FEITO (2026-11-01):** `canAccessClient` passou a exigir a agência dona do cliente (cache por requisição, platform admin ainda passa), grant/revoke usam o `agency_id` e validam o `user_id` do POST, conteúdo ganhou guarda de cliente em plano, post e no `?client_id=` da lista e do calendário, o `client_id` do POST de fatura e contrato é validado, `loadClientIds` só carrega vínculo da própria agência (fechava o 200 em `/aprovacoes/{plano de outra agência}`), a revalidação do `AuthMiddleware` recarrega os vínculos — revogar acesso passou a valer em até 60 s, não só no próximo login — e o portal do cliente agora exige `clients.edit`. `TenantBoundaryTest` e `DefaultRolesReachTest` (+ casos novos em `AuthTest` e `MiddlewareAuthorizationTest`). Na revisão (01/11) o financeiro também fechou na **leitura**: `/faturas`, `/contratos` e `/pagamentos` só listam cliente alcançado (`Auth::reachableClientIds` + filtro `client_ids` no repositório) e fatura/contrato/pagamento de cliente fora do alcance responde 403 em abrir, editar, PDF e excluir — antes o seletor escondia o cliente e a URL entregava o dado dele (o papel padrão `client_financial` via a cobrança da agência inteira). O seletor de cliente da fatura e do contrato ganhou opção vazia e mantém o cliente do registro aberto mesmo arquivado: sem isso, salvar reatribuía a fatura ao primeiro cliente da lista, calado. Observações: a negativa de cliente de outra agência responde **403** (ClientAccessMiddleware), não 404; a **migration 30** devolve `clients.view_all` ao papel Gestor de Tráfego — sem ela, esse papel fica com o módulo de Conteúdo vazio; **perfil criado pelo tenant** com `content.view` e sem `clients.view_all` fica sem alcance e a lista de Conteúdo agora explica o que pedir ao administrador (`content.no_client_reach`) — backfill em massa desses perfis ficou de fora de propósito (daria a usuário de cliente o conteúdo de toda a agência). Fora do item: `/financeiro` e o Dashboard ainda somam dinheiro por agência, sem recorte de alcance.
> **Ação:** canAccessClient passa a exigir a agência; grant/revoke de acesso usam o agency_id que hoje recebem e ignoram; ClientAccessMiddleware nas rotas de conteúdo que faltam; ?client_id= de filtro validado.
> **Arquivos:** Support/Auth.php, ClientService, ClientRepository, ContentPlanController, routes/web.php
> **Pronto quando:** Teste positivo e negativo por rota: usuário da agência A recebe 404 em cliente, plano e acesso da agência B.

### SEC-12 · Fechar o escalonamento de perfis (RBAC) — `8h` · 10% · núcleo
> ✅ **FEITO (2026-11-01):** Perfil do sistema só leitura (D10), `role_id` validado contra a agência, `Auth::canGrant` impede conceder o que não tem, ninguém altera/exclui usuário com mais permissões (fechava troca de senha do admin). De brinde: o formulário de usuário não trocava o perfil de verdade e a lista mostrava sempre 0 permissões. `RoleEscalationTest`.
> **Ação:** Perfis do sistema (agency_id NULL) ficam imutáveis pelo tenant; role_ids do POST validados contra a agência; ninguém concede permissão que não tem. Migration clona perfis por agência se for a decisão.
> **Arquivos:** RoleRepository, RoleService, UserService, UserRepository, migration
> **Pronto quando:** Admin da agência A não altera perfil global nem se promove por ID; testes cobrem os 3 vetores.

### SEC-13 · Escopo de agência em tráfego e tarefas — `6h` · 15% · núcleo
> ✅ **FEITO (2026-11-01):** `campaigns`, `ad_sets` e `ads` não têm `agency_id` (a agência vem pela conta de anúncios), e o id chegava cru da URL e do POST: `listByAccount` passou a exigir a agência pelo JOIN com `ad_accounts`, `/trafego/contas/{id}/campanhas` de outra agência responde **404** em vez de lista vazia, e toda ação de anúncio (manual ou vinda da IA) valida conta + campanha/conjunto/anúncio dentro dessa conta antes de gravar — a execução na Meta relê o alvo pela conta da solicitação, então linha antiga com alvo alheio não vira chamada na API. Dois achados de brinde na mesma área: `/ia/recomendacoes?account_id=` mandava as métricas da conta de outra agência para a IA e devolvia o nome das campanhas dela na tela (agora recusa antes de chamar a IA), e a lista/detalhe de ações só mostra o nome do alvo quando ele está mesmo na conta. Nas tarefas, `client_id` e `assigned_to` do POST passaram a ser validados contra a agência (criar e editar), os JOINs de `clients`/`users` do kanban, do detalhe, do "Meu dia" e do relatório executivo carregam a agência da própria tarefa, e o `UPDATE tasks` do push do ClickUp ganhou `agency_id`. `TrafficTaskScopeTest` (11 casos, cada negativa com a positiva ao lado; verificados um a um desfazendo a correção). Decisões tomadas: a tarefa valida **agência**, não alcance de cliente (`Auth::canAccessClient`) — o módulo de tarefas inteiro não é recortado por cliente hoje e o seletor lista a agência toda; recorte por alcance aqui é decisão de produto, fica para o dono. Na revisão (01/11) os três achados procederam e foram corrigidos: (a) o JOIN com `ad_accounts` da lista e do detalhe de ações também casa a agência (`LEFT JOIN … AND a.agency_id = aa.agency_id`) e os subselects do alvo se apoiam nesse `a.id` — a blindagem anterior só cobria conta própria + campanha alheia, então linha legada apontando para a **conta** de outra agência ainda exibia o nome da conta e o da campanha dela; o JOIN é à esquerda de propósito, para a linha legada continuar listada (e rejeitável) e o contador de pendentes seguir batendo com a tela; (b) o tráfego ganhou o mesmo tratamento das tarefas: `client_id` do POST validado contra a agência em `AdsAccountController::oauthSave` e `store` — antes de qualquer chamada à Meta, `ads.client_out_of_agency` em pt/en/es — e os JOINs de `clients` do tráfego carregam a agência (`AdAccountRepository`, `CampaignRepository`, `AdMetricsRepository`, `AiInsightRepository`, que de quebra fecha o nome da conta no `/ia`); sem isso, trocar o `<select>` do OAuth punha o nome do cliente da agência B na lista de contas, no painel de tráfego e no insight gerado; (c) o seletor de cliente da tarefa passou a manter o cliente **arquivado** da tarefa aberta (mesma correção que fatura e contrato receberam no SEC-11): sem a opção dele o navegador caía em "— Sem cliente —" e salvar só a prioridade desfazia o vínculo, calado e sem volta pela interface. 5 casos novos em `TrafficTaskScopeTest` (16 no total), cada um verificado desfazendo a correção. De brinde: `metricsPerCampaignForAgency` não devolvia `cpc`/`cpm`, que `trafego/index.php` já lia — as duas colunas do painel vinham vazias. Fora do item: os JOINs sem agência que sobram em conteúdo, financeiro e orgânico (`ContentPlanRepository`, `InvoiceRepository`, `OrganicAccountRepository`…) — mesma classe de problema, outro item. Na 2ª revisão (01/11) os três achados procederam e foram corrigidos: (a) `action_type` não tinha allowlist nenhuma — o `<select>` da tela era a única barreira, a coluna é `varchar(60)` sem CHECK e as duas views imprimiam o valor do banco como rótulo **sem `e()`**, com o CSP do app em `script-src 'unsafe-inline'`: XSS armazenado que dispara na sessão de quem abre /trafego/acoes, inclusive o `agency_admin` que aprova, com o token CSRF da página à mão (e fatiável entre linhas, driblando o limite da coluna); agora `AdsActionService::ACTION_TYPES` recusa tipo fora do catálogo na ação manual **e** na vinda da IA (`ads.action_type_invalid` em pt/en/es) e o fallback do rótulo passa por `e()` em `trafego/actions.php` e `action_show.php` — a linha legada continua listada, escapada; (b) mesma classe no módulo de IA: `ai_insights.type` vinha cru do POST (`buildPrompt` tem `default`, coluna `varchar(50)` sem CHECK) e saía sem `e()` em `ia/index.php` e `ia/show.php` — agora `AiInsightService::INSIGHT_TYPES` é validado no controller **antes** da checagem de IA configurada (tipo inventado não chega a gastar chamada) e os dois fallbacks escapam; de quebra o rótulo da sugestão em `ia/recommendations.php` (vem do JSON do modelo) e o `campaigns.status` do painel (`trafego/index.php`, vem do sync) também ganharam `e()`; (c) `saveRecommendations` gravava em `ads_actions` exigindo só `ai_insights.view` — um perfil "Analista" que o admin cria achando que é leitura enfileirava solicitação para o Gestor de Tráfego aprovar e executar na Meta, sem nunca ter tido `ads_actions.request`; agora cobra `ai.recommend_ads_action` + `ads_actions.request`, e as duas permissões **mortas** do catálogo foram acordadas — `/ia/gerar` exige `ai.generate_report` e `/ia/recomendacoes` exige `ai.recommend_ads_action` (as duas rotas queimam a chave de API da plataforma); os botões correspondentes ficam escondidos para quem não alcança. Sem migration: as duas permissões estão no papel Gestor de Tráfego desde o primeiro seeder e o `agency_admin` tem `__ALL__`. 8 casos novos em `TrafficTaskScopeTest` (24 no total), cada um verificado desfazendo a correção. **Atenção ao deploy:** perfil criado pelo tenant com só `ai_insights.view` perde gerar insight e recomendações — o administrador da agência precisa marcar as permissões de IA no perfil. Fora do item: `DELETE /ia/{id}` ainda exclui insight com `ai_insights.view` (não existe `ai_insights.delete` no catálogo — permissão nova + migration, fica para o SEC-15/SEC-18).
> **Ação:** CampaignRepository::listByAccount com agency_id; alvo das ações de anúncio (conta/campanha/conjunto/anúncio) validado; client_id/assigned_to da tarefa validados e JOINs escopados; UPDATE do ClickUp com agency_id.
> **Arquivos:** CampaignRepository, AdsActionService, AiInsightController, TaskController, TaskRepository, ClickUpIntegrationRepository
> **Pronto quando:** /trafego/contas/{id}/campanhas de outra agência dá 404; tarefa não exibe nome de cliente/usuário de outra agência.

### SEC-14 · Cifrar o token do Instagram/Facebook orgânico — `3h` · 0% · núcleo
> ✅ **FEITO (2026-11-01):** Repositório cifra na escrita e decifra na leitura; migration 29 cifra o que existe e é reversível; leitura tolera texto puro, então deploy e migration podem ocorrer em qualquer ordem. `OrganicTokenEncryptionTest`.
> **Ação:** OrganicAccountRepository grava com Secret::encrypt (como o Ads já faz) e migration cifra os tokens existentes em produção.
> **Arquivos:** OrganicAccountRepository, OrganicSyncService, migration nova
> **Pronto quando:** SELECT access_token em organic_accounts não mostra token legível; sync segue funcionando.

### BUG-02 · Recuperação de senha (hoje dá erro 500) — `5h` · 0% · núcleo
> ✅ **FEITO (2026-11-01):** As 2 telas criadas nos 3 idiomas, token guardado só como hash, rate limit nas rotas. Achado no teste: o link já nascia vencido (PHP em São Paulo × banco em UTC), validade agora calculada no SQL. `PasswordResetTest`.
> **Ação:** Criar as views auth/forgot-password e auth/reset-password, guardar o token com hash, rate limit nas 4 rotas e invalidar sessões no reset.
> **Arquivos:** resources/views/auth/*, AuthService, UserRepository, routes/web.php
> **Pronto quando:** Fluxo esqueci → e-mail → nova senha funciona ponta a ponta com teste de feature.

### BUG-03 · Sincronizar o Drive pode apagar a galeria — `10h` · 0% · núcleo
> ✅ **FEITO (2026-11-01):** `listFolder` passou a **lançar erro** em vez de devolver lista vazia em resposta diferente de 200 (o `break` silencioso fazia o sync entender "apagaram tudo no Drive" e limpar `drive_files`), com retry curto em 429/5xx (2 novas tentativas, 0,7 s no pior caso — é request web, não cron), e o sync ganhou a trava "remoto vazio com banco cheio = não apagar": pasta que volta vazia do Google com conteúdo no banco (conta Google trocada, escopo `drive.file` perdido, resposta parcial) é ignorada, responde **409** dizendo quantas pastas ficaram de fora e o que foi aplicado no resto (`drive.list_failed`/`drive.sync_skipped`/`drive.sync_changes` em pt/en/es), e no cron `syncAll` conta `skipped` em vez de esvaziar a galeria calado; a tela recarrega a listagem depois de um sync com erro, porque ele pode ter parado no meio. A escrita na galeria (criar pasta, enviar, mover, renomear, excluir arquivo e pasta, restaurar) **e o próprio Sincronizar** passaram a exigir `content.edit` além de `clients.view` — `clients.view` é leitura e bastava para apagar pasta de cliente, inclusive pelo papel Gestor de Tráfego — e a tela esconde as ações de escrita de quem não tem a permissão. Sem migration e sem permissão nova: `content.edit` já existe no catálogo e está no papel Social Media. `DriveListFolderRetryTest` (unitário, com dublê de HTTP) e `DriveSyncTest` (10 casos de feature com o Google trocado por fake no container, inclusive o 429 ponta a ponta do critério do item), cada correção verificada desfazendo-a. Na revisão (01/11) os dois achados procederam: a trava abortava o sync do **cliente inteiro** e voltava a abortar em todo clique (o registro fantasma continua no banco), então uma pasta legitimamente esvaziada no Drive congelava o Sincronizar daquele cliente e a ordem da listagem do Google decidia quais mudanças nunca seriam refletidas — agora o skip é **por pasta**: a suspeita fica de fora e as outras seguem reconciliando; e `removed` contava 1 por **pasta** purgada, não as linhas que a purga apagou, então pasta que sai do Drive com 40 arquivos reportava "1 removido(s)" na tela e no total do cron, escondendo justamente a perda em massa que a trava existe para tornar visível — `purgeFolderTree` devolve pasta + descendentes (`DriveFileRepository::deleteByFolder` passou a devolver `rowCount`) e o caso acima reporta 41. 2 casos novos em `DriveSyncTest` (pasta suspeita ignorada com as demais reconciliadas, contagem de linhas da subárvore), ambos verificados vermelhos antes da correção. **Atenção ao deploy:** os papéis **Designer** e **Gestor de Tráfego** não têm `content.edit` — eles continuam vendo e baixando a galeria, mas param de enviar/excluir; se o designer sobe material, o administrador da agência precisa marcar "Editar planificação" no perfil dele (decisão de produto, não migration). Fora do item: `DriveUploadService::setAnyoneReader` segue tornando todo arquivo enviado **público por link** no Drive (decisão do dono) e o sync não grava `activity_logs` do que removeu (fica no SEC-15). Na 2ª revisão (01/11) os dois achados procederam: (a) a reconciliação é incremental e **sem transação**, então falha de listagem numa subpasta subia a exceção de dentro de `syncClient` e enterrava os contadores — a tela recebia "Nada foi alterado na galeria" depois de já ter apagado linhas na raiz, e o cron contabilizava `removed: 0` com `errors: 1`; agora `syncClient` captura a falha e devolve `reason = 'list_failed'` **com** `added/removed/renamed`, a tela mostra o erro seguido de "antes de parar já havia aplicado…" (`drive.sync_applied`/`drive.sync_failed` em pt/en/es, `drive.list_failed` reescrito para não afirmar que nada mudou) e `syncAll` soma o que o cliente aplicou antes de falhar, conta `errors` e lista `failed`; (b) a trava por pasta só devolvia um contador, sem dizer **qual** pasta ficou de fora — num cliente com 10 meses de pastas não havia como agir, e a mensagem culpava a integração mesmo na causa legítima (alguém apagou os arquivos direto no Drive mantendo a pasta); agora a recursão carrega o caminho, a resposta traz `skipped_folders` (`/Setembro`, `/` na raiz), o cron prefixa com `#id` do cliente e o texto orienta as duas causas. 5 casos novos em `DriveSyncTest` (falha no meio reportando a remoção já gravada, o mesmo no `syncAll`, trava e falha na mesma resposta, caminho da pasta ignorada na raiz e no cron) mais o caminho `/Setembro` conferido no caso que já existia — os 6 verificados vermelhos antes da correção. Fica em aberto: a galeria não tem **exclusão em lote** (só mover em lote), então limpar os registros fantasmas de uma pasta grande é arquivo por arquivo.
> **Ação:** listFolder lança erro em vez de parar em silêncio; retry em 429; trava 'Drive vazio e banco cheio = abortar'; permissão própria de escrita/exclusão no Drive (hoje clients.view basta para apagar pasta).
> **Arquivos:** GoogleDriveApiService, DriveSyncService, ClientFilesController, config/permissions.php
> **Pronto quando:** Teste com Drive respondendo 429 não remove nenhuma linha de drive_files; perfil só-leitura recebe 403 ao excluir.

### BUG-04 · Tela interna de Aprovações funcionando — `10h` · 20% · núcleo
> ✅ **FEITO (2026-11-01):** A lista e as mutações saíram de `$_SESSION['client_ids']` para o alcance real (`Auth::reachableClientIds`/`canAccessClient`, plano resolvido pela agência e fora do rascunho), o feedback do item passou a exigir permissão que existe (`approvals.approve|comment|reject` em vez de `approvals.approved|changes_requested|rejected`, que não estão no catálogo), o `approvals.js` foi reescrito sobre o `api.js` com o estado do formulário no escopo certo (botão desabilita, nada de envio duplo) e toast de erro visível por evento (a API do Alpine v2 nunca mostrava nada), a tela ganhou estado vazio de posts, explicação para perfil sem alcance de cliente, textos via `t()` em pt/en/es e acento por token. A **migration 31** dá `approvals.comment`+`approvals.approve` ao papel Social Media e `approvals.comment` ao Designer — sem ela a tela abre, mas em leitura. `ApprovalScreenTest` (21 casos: lista, aprovar, pedir ajuste, reprovar, tipo inválido, item de outro plano, perfil sem permissão, cliente fora do alcance e outra agência). Validado num Chromium real contra o banco de teste local: zero erro de console, 7 componentes Alpine iniciados, feedback e "Aprovar Tudo" gravando (`activity_logs` incluído) e 375px sem rolagem horizontal. Observações: fora do alcance responde **404** (mesma régua da tela de Conteúdo), não 403; a tela segue sem botão de **reprovar** (só aprovar, revisão e comentar) — a rota aceita e está testada; o feedback escrito pela equipe grava `source = 'agency'` (ver 2ª revisão). Na revisão (01/11) dois achados procederam: o `approvalItem()` do `approvals.js` declarava quatro parâmetros e descartava o 5º que a view sempre passou (`$defaultType`), então o estado nascia em `type: 'comment'` — perfil com `approvals.approve` e **sem** `approvals.comment` abria o formulário, enviava e levava 403 no único tipo que o controller recusa, justamente o 403 que o item veio consertar; e `ContentPlanService::requestRevision` gravava a nota de revisão em cima de `content_plans.notes`, que é o MESMO campo do "Observações para a equipe" do formulário do plano (o modal não exige texto: nota vazia apagava as observações do gestor, sem aviso) — agora a nota é **acrescentada** com cabeçalho datado (`content.revision_note_header` em pt/en/es) e nota vazia não toca no campo, o que também fecha o mesmo apagamento vindo do portal. `ApprovalItemDefaultTypeTest` (lado JS) + 3 casos novos em `ApprovalScreenTest` (tipo pré-selecionado na view, revisão sem nota, revisão acrescentando). Revalidado num Chromium real (Playwright, HTML da tela renderizado contra o banco de teste): tipo inicial `approved`, chip "Aprovar" já destacado, botão de envio verde, POST com `feedback_type=approved`, três chamadas seguidas = **um** POST e zero erro de console. Na 2ª revisão (01/11) os três achados procederam e foram corrigidos: (a) o tipo pré-selecionado passou a ser **'comment'** sempre que o perfil pode comentar — pegar o primeiro chip deixava `approved` armado para quem aprova, e o botão genérico "Enviar feedback" aprovava o post (aprovar o último fecha o plano e manda a confirmação de aprovação ao cliente); perfil que só aprova continua nascendo em `approved`; (b) o feedback da tela interna grava `source = 'agency'` e o portal do cliente passou a listar só o que veio dele (`getFeedbacks(clientVisibleOnly: true)`, linha antiga sem origem continua visível) — gravado como `'client'` com o `client_id` do plano, a anotação interna ("refazer a capa") aparecia no portal assinada com o nome do próprio cliente, e o template do portal agora lê `user_name` antes de `client_name`; (c) `feedback()` recusa plano já **aprovado** com 409 (`approval.plan_closed` em pt/en/es) — a regra existia só na view, então uma aba aberta de antes da aprovação punha um post em revisão dentro de um plano "Aprovado" (selo aprovado com "0/1 aprovados"). 3 casos novos em `ApprovalScreenTest` (padrão 'comment' para quem aprova e comenta, comentário interno fora do portal, plano aprovado não recebe feedback). Revalidado num Chromium real: `type` inicial `comment`, chip "Comentar" destacado, chip "Aprovar" ainda troca o tipo, botão vira "Enviando..." desabilitado, toast de sucesso, três cliques = **um** POST com `feedback_type=comment`, zero erro de console e 375px sem rolagem horizontal. Fica em aberto (BUG-05): POST de "Aprovar Tudo"/"Pedir Revisão" em plano já aprovado ainda é aceito e re-dispara a notificação ao cliente.
> **Ação:** Trocar $_SESSION['client_ids'] por Auth::canAccessClient (hoje social_media e designer veem 'Tudo em dia'); corrigir as permissões inexistentes; reescrever approvals.js sobre api.js com o escopo Alpine certo e erro visível.
> **Arquivos:** ApprovalController, public/js/approvals.js, resources/views/approvals/*
> **Pronto quando:** Social media aprova e pede ajuste pela tela sem 403; botão desabilita durante o envio; testes de feature da tela.

### BUG-05 · Máquina de estados do plano à prova — `10h` · 40% · núcleo
> ✅ **FEITO (2026-11-01):** As transições do plano passaram a viver no service: plano **aprovado** não recebe post novo nem perde post (422 com motivo, e a tela de Conteúdo deixou de oferecer "Adicionar Post"/"Excluir", explicando por quê), `approvePlan` e `requestRevision` só aceitam plano **na mesa da cliente** (`sent`/`pending_approval`) — então aprovar item a item no portal não fecha mais plano em revisão, e "Aprovar Tudo"/"Pedir Revisão" em plano já aprovado não regrava `approved_at` nem re-dispara a notificação —, e trocar a semana do plano **leva os posts junto, cada um no mesmo dia da semana** (antes todo post ficava fora da semana nova e `assertDateInPlanWeek` recusava qualquer edição deles); `update` deixou de confirmar "Plano atualizado." quando nada foi gravado. `tests/Feature/ContentPlanStateMachineTest.php` (15 casos, cada negativo com o positivo da mesma rota) + 4 casos novos em `tests/Unit/ContentPlanApprovalTest.php`. Observações: o achado de que a via item a item aprovava plano em **rascunho** não procedia (`findByIdForClient` filtra `status != 'draft'`, e `PortalApprovalTest` já travava isso), e o `update` já devolvia 404 desde SEC-11 (agora com teste próprio). Fora do item: editar o **conteúdo** de um post (legenda, capa, status) em plano aprovado continua permitido — só adicionar/excluir fechou; plano `rejected` segue sem saída (`send` só reenvia `draft`/`revision`) e plano aprovado agora não tem caminho de reabertura (ver decisões ao dono); dois planos do mesmo cliente na mesma semana continuam possíveis (`update` e `create` não checam colisão), o que deixa `findByClientWeek` ambíguo no reagendamento. Na revisão (01/11) os **cinco achados procederam** (dois eram o mesmo defeito) e foram corrigidos: (a) `update()` não olhava o status — com os posts passando a ser arrastados pela semana, trocar `week_start` num plano **aprovado** re-datava em silêncio tudo o que a cliente fechou (status `approved` e `approved_at` intactos, sem aviso a ela); agora a semana de plano aprovado não muda de data (422 `content.plan_closed_week` em pt/en/es, capturado no controller e mostrado como erro na tela), **título e notas seguem editáveis**, e a tela de editar nem oferece o campo da semana (mostra a data travada e explica); (b) `rescheduleItem` só checava o status do plano de DESTINO, então **Reagendar** era a porta de saída do `deleteItem` bloqueado: o post saía da semana aprovada (selo "Aprovado" com zero post) e aparecia aprovado num rascunho que a cliente nunca viu — agora a troca de plano é recusada também na ORIGEM aprovada (`content.plan_closed_reschedule`), **remanejar a data dentro da própria semana aprovada continua valendo**, o modal avisa antes de confirmar e o texto de `content.plan_closed_items` deixou de instruir "reagende o post" (manda criar o post na semana seguinte); (c) `approvePlan` gravava só a linha do plano e deixava os posts em rascunho — selo "Aprovado" ao lado de "0/3 aprovados" em Conteúdo e Aprovações e cada post "Pendente" no portal, sem como reconciliar (feedback de item em plano aprovado responde 409); aprovar o plano agora aprova **todos** os posts dele (`ContentPlanRepository::approveAllItems`, contagem no `activity_logs`), inclusive os que estavam em revisão: "Aprovar plano"/"Aprovar Tudo" é a decisão de quem clica e o histórico de feedback fica onde está; (d) o guard de plano fechado da tela interna não existia no **portal**: `PortalController::itemFeedback` só barrava rascunho, e a view que esconde o formulário não é re-renderizada (o envio é fetch, e `submitted` nunca vira true) — aprovar o último post fechava o plano e o clique seguinte ("pedir alteração") punha um post em revisão dentro de um plano "Aprovado"; agora responde **409** (`approval.plan_closed`) e a resposta de sucesso traz `plan_closed`, com a página recarregando quando o envio fecha o plano. 11 casos novos em `tests/Feature/ContentPlanStateMachineTest.php` (semana aprovada não muda de data, plano aprovado ainda renomeia, tela de editar sem o campo da semana, post não sai da semana aprovada, remanejar dentro dela, "Aprovar Tudo" e "Aprovar plano" do portal aprovando os posts, portal recusando feedback em plano aprovado, flag `plan_closed`) + 4 em `tests/Unit/ContentPlanApprovalTest.php`, todos verificados **vermelhos** antes da correção. Fora do item: `PortalWeekTest` falhava sozinho depois das 21h de Brasília porque semeava o post com `CURRENT_DATE` (Postgres em UTC) contra a semana que o portal calcula em PHP — corrigido no próprio teste, sem mexer no app. Na 3ª revisão (01/11) **quatro dos seis achados procederam** (dois pares descreviam o mesmo defeito) e dois pontos acima foram **corrigidos/revertidos**, porque a rodada anterior trocou uma mentira por uma perda: (e) `approveAllItems` aprovava todo post com `status <> 'approved'`, então "Aprovar plano" (portal) e "Aprovar Tudo" (interno) **apagavam em silêncio o pedido de alteração que a cliente registrou** no post — o chip virava "Aprovado", a contagem dizia 3/3, o designer perdia o único sinal de que a capa tem de ser refeita, a tarefa de produção nascia sem o pedido e ela não conseguia repetir o recado (feedback de item em plano aprovado = 409, `requestRevision` recusa plano aprovado); agora é `ContentPlanRepository::approvePendingItems` e só o post em `draft` vai com o plano — a semana fecha como "Aprovado — 2/3 aprovados", que é a verdade (semana fechada, um post a refazer); (f) `updateItem` gravava o `status` cru do POST e o menu "Status" do card continuava na tela em plano aprovado: marcar "Aprovado" na mão num plano enviado fazia a contagem fechar a semana no primeiro "Aprovar" da cliente (o sistema afirmava que ela aprovou posts que nunca viu) e devolver post para rascunho em semana fechada recriava a contradição que (c) havia resolvido, sem reconciliação possível; agora a edição livre **nunca** grava 'approved' (`content.item_status_approval_only` — aprovar é do portal ou da tela de Aprovações, que registra autor e data) nem muda status em plano aprovado (`content.item_status_plan_closed`), o menu perdeu a opção "Aprovado" e desaparece em semana fechada, e a recusa virou toast em vez de silêncio; (g) **revertido o guard de ORIGEM aprovada do `rescheduleItem`**: como (c) passou a aprovar os posts junto com o plano, o estado normal depois da cliente aprovar é plano+posts aprovados — exatamente onde o guard recusava mover o post, tirando do ar o fluxo que o CONT-07 existe para resolver ("a cliente aprovou e pediu para publicar semana que vem"), deixando a semana aprovada sem nenhuma saída e devolvendo o retrabalho manual de recriar o post; Reagendar voltou a levar o post inteiro para outra semana (o movimento fica no `activity_logs`; quem recusa continua sendo a semana de **DESTINO** aprovada), `content.plan_closed_items` voltou a apontar Reagendar, `content.plan_closed_reschedule` saiu dos três idiomas e o aviso falso saiu do modal; (h) plano em **revisão** com todos os posts aprovados pela cliente não fecha (por desenho: espera o reenvio) e ninguém era avisado — a tela de Aprovações agora diz que ela já aprovou todos os posts e que falta reenviar o plano (`approval.revision_all_approved` em pt/en/es). Não procedeu: não há outro caminho de UI que mude o status do post (o modal de edição não envia `status`), e editar o **conteúdo** do post em semana aprovada (legenda, capa, mídia) continua permitido de propósito — a peça pronta chega depois da aprovação. 8 casos novos e 3 reescritos em `tests/Feature/ContentPlanStateMachineTest.php` (pedido de alteração sobrevive ao "Aprovar plano" e ao "Aprovar Tudo", post pendente ainda é aprovado em bloco, menu de status não aprova mas ainda pede revisão, semana aprovada não devolve post para rascunho mas ainda edita a legenda, tela sem o menu de status em semana fechada e sem "Aprovado" fora dela, post sai da semana aprovada para outra semana mantendo o status, aviso do plano em revisão) + 1 novo e 3 ajustados em `tests/Unit/ContentPlanItemUpdateTest.php`/`ContentPlanApprovalTest.php`, todos verificados **vermelhos** antes da correção; `docs/GUIA_SISTEMA.md` ganhou o quadro do que a semana aprovada aceita e recusa. Fica em aberto: não existe "reabrir semana" — post em revisão dentro de plano aprovado não tem como ser reaprovado pela interface, e o aviso "reabra aquele plano" do modal de reagendamento aponta para algo que não existe; plano `rejected` segue sem saída; excluir plano já enviado ao cliente segue permitido, como antes; a cobrança automática (`ApprovalReminder`) e as notificações só olham plano 'sent' — o feedback de item da cliente não dispara `item.feedback_added` (ninguém emite o evento), o que cai em BUG-06/BUG-07.
> **Ação:** Bloquear adicionar/excluir post em plano aprovado; item aprovado no portal não aprova plano em rascunho/revisão; trocar a semana do plano reposiciona os posts; update devolve 404.
> **Arquivos:** ContentPlanService, ContentPlanController
> **Pronto quando:** O sistema nunca mostra como aprovado um post que a cliente não viu; testes por transição.

### BUG-06 · Tarefas e notificações: bugs vivos — `6h` · 0% · núcleo
> ✅ **FEITO (2026-11-01):** "Marcar notificação como lida" voltou a funcionar — o handler pedia `(Request, int $id)` e o Router chama com um argumento só (500 em toda notificação clicada), agora o `{id}` sai de `$request->param()`; o sino parou de engolir erro (`catch {}` → mensagem na própria gaveta, contador só desce quando o servidor confirma), marca como lida **antes** de navegar (a navegação cancelava o POST) e deixou de subir **dois** `setInterval` de 30 s — `x-init="init()"` era a segunda chamada, porque o Alpine já executa o `init()` do objeto de `x-data` (medido em Chromium 3.14.1: rodava 2×). Nas tarefas, `status`, `priority`, `title` e `due_date` passaram a ser validados no `store`/`update` (status fora do vocabulário tirava a tarefa do kanban para sempre; título vazio virava card sem nome; data fora do ISO estourava o Postgres com 500), os contadores do quadro saem das tarefas **já filtradas** (antes vinham de um COUNT por agência, sem relação com os cards na tela), tarefa com status desconhecido cai na coluna "A fazer" em vez de desaparecer, o detalhe mostra o valor cru num selo neutro no lugar de "Undefined array key" e o chat da tarefa deixou de carimbar "Invalid Date" no Safari/iOS. Testes: `tests/Feature/NotificationBellTest.php` (7 casos), `tests/Feature/TaskBoardTest.php` (13 casos), `tests/Unit/FrontEndJsGuardsTest.php` (guarda de `new Date` sem `T` e de `x-init="init()"` em componente com timer) e o teste de aridade de **todas as 372 rotas** em `tests/Unit/RoutesResolveTest.php` — todos verificados **vermelhos** antes da correção (10 dos 13 de tarefas falhavam, com 4 warnings de "Undefined array key"). Observação: o achado de que o **chat da tarefa não atualiza** porque `init()` nunca roda **não procede** — o Alpine chama o `init()` do `x-data` sozinho (verificado em navegador), o `setInterval` do chat sempre existiu; o defeito real ali era só a data no Safari. Ficou de fora: as duas implementações de `planChat` seguem duplicadas (CHAT-01, como combinado); o arrastar do kanban continua com `fetch` cru e falha calada quando o status é recusado (FE-02); `update()` de tarefa não devolve 404 para id inexistente (a gravação é escopada por agência, então não vaza nada); e `tasks/index.php`/`tasks/show.php` seguem com texto hardcoded em pt (FE-06) — só as mensagens novas nasceram com `t()` nos 3 idiomas. Na revisão (01/11) os **três achados procederam** (dois descreviam o mesmo defeito) e foram corrigidos: (a) o menu "Mover para" do detalhe é um `<form method="POST">` nativo apontando para `/tarefas/{id}/status`, que respondia sempre `Response::json()` — o único ouvinte global de submit (`partials/form_loading.php`) apenas bloqueia duplo envio e põe spinner, não faz fetch —, então o navegador saía da tarefa e mostrava `{"success":true}` numa página em branco, e a recusa chegava como `{"error":…}` (a mensagem traduzida nova nunca aparecia como aviso); agora quem não pede JSON (`wantsJson()`) volta para `/tarefas/{id}` com a mensagem no flash (`tasks.status_updated` em pt/en/es) e o arrastar do kanban, que manda `X-Requested-With`, segue recebendo JSON 200/422; (b) o selo de **prioridade** do card do quadro ainda lia `$priorityLabels[$t['priority']]` sem `??` — o status do quadro e os dois selos do detalhe já estavam blindados, a prioridade do quadro tinha ficado fora: dois warnings "Undefined array key" por card e selo sem rótulo nem cor; (c) efeito colateral do mesmo buraco em `tasks/edit.php` — com valor fora do vocabulário nenhuma `<option>` ficava `selected`, o navegador marcava a primeira (prioridade virava "Urgente") e salvar só o título regravava o valor calado; agora o valor da linha entra na lista como opção marcada (`tasks.legacy_value`) e `campoInvalido` aceita **preservar** o que já está na linha, nunca um valor novo (os rótulos do `<select>` passaram por `e()`, agora que um deles vem do banco). 5 casos novos em `tests/Feature/TaskBoardTest.php` (18 no total; o teste do arrastar passou a mandar o header de ajax, como o `fetch` real faz), todos verificados **vermelhos** antes da correção. Fora do item: o arrastar do kanban continua com `fetch` cru e falha calada quando o status é recusado (FE-02). Na **segunda revisão** (01/11) os dois achados descreviam o mesmo defeito, e ele **procedia**: o `activate()` novo dava `preventDefault()` e só navegava se o POST de leitura voltasse 200, então clicar numa notificação com sessão expirada (401 `Unauthenticated`), 500 ou rede ruim não levava a lugar nenhum — até 30 s de gaveta aberta sem reação nenhuma (o `api` tem timeout de 30 s e o item não tem estado de "enviando") e, no fim, o texto cru do middleware na gaveta; antes do BUG-06 o `<a>` navegava na hora. Agora o `<a>` volta a navegar na hora e o POST vai com `keepalive` (opção nova no `api.js`), que era o único motivo de esperar a resposta — a navegação cancelava a marcação e a notificação voltava no poll; 401/419 **numa ação do usuário** manda para `/login` em vez de mostrar "Unauthenticated" (o poll de 30 s segue apenas avisando, para não arrancar a página de quem está preenchendo um formulário). 5 casos em `tests/Unit/NotificationBellClickTest.php`, que extrai o JS do sino da própria view e o executa **de verdade** no node com `api`/`window` dublados — 4 deles verificados **vermelhos** na implementação anterior (o 5º é a guarda de que erro de servidor continua aparecendo na gaveta e não desloga ninguém). Ficou de fora: em navegador sem `keepalive` (Firefox < 133) a navegação ainda pode cancelar o POST e a notificação reaparece no poll — o comportamento antigo, nunca um clique morto.
> **Ação:** Marcar notificação como lida dá 500 (aridade do método); chat da tarefa não atualiza (init nunca roda); status/prioridade/título sem validação (tarefa some do kanban); contadores ignoram filtro; data quebra no Safari.
> **Arquivos:** SettingsController, TaskController, tasks/show.php, tasks/index.php, layouts/app.php
> **Pronto quando:** Sino zera e continua zerado após o poll; teste de aridade de todas as rotas passa.

### BUG-07 · Automações respeitarem o canal escolhido — `4h` · 0% · núcleo
> ✅ **FEITO (2026-11-01):** `AutomationService::isChannelActive` passou a ser consultado antes de cada entrega de automação — WhatsApp desmarcado não gera envio nem job na fila, e a linha fica em `/automations/deliveries` como "Não enviado" com o motivo (`notification_jobs.status = 'skipped'`); o canal "no sistema" desmarcado também deixa de criar o aviso in-app (contrato expirando, tarefa atrasada, escalonamento, resumo do dia). O relatório mensal mandava `/executive-report?client=X`, rota interna que joga a cliente na tela de login: agora vai o link público do portal dela (`ClientService::portalUrl`), e cliente com portal desligado é pulado — mensagem que é só um link não vale nada sem o link. `tests/Feature/AutomationChannelTest.php` (16 casos, cada negativa com a positiva ao lado) + 2 casos em `tests/Unit/PortalPlanUrlTest.php`; todas as negativas verificadas **vermelhas** antes da correção (desligando o gate, o relê do checkbox e o link antigo). Dois achados de brinde na mesma área: desmarcar **todos** os canais não sobrevivia à releitura (`[] ?: $def['channels']` remarcava o catálogo na tela, e o motor passaria a obedecer esse valor), e o POST de `/automations/{key}` não tinha allowlist — canal inventado era salvo e desligaria tudo, calado. O catálogo do relatório mensal dizia `['email']` enquanto o handler sempre mandou WhatsApp: a tela mentia sobre o canal, então o catálogo passou a `['whatsapp','email']` (paridade de e-mail é o WPP-01) e a **migration 32** reescreve a linha legada `["email"]` dessas regras, senão o relatório que hoje sai pararia de sair no deploy. **Atenção ao deploy:** entre o deploy e a migration (painel `/admin/migrations`), o relatório mensal aparece como "Não enviado" até a migration rodar ou a agência marcar WhatsApp na tela; nada mais depende dela. Fora do item: nenhuma automação entrega por e-mail hoje, então o gate de e-mail só ganha efeito com o WPP-01; o canal in-app bloqueado **não** deixa registro (não existe log de entrega in-app — só WhatsApp/e-mail têm linha na timeline); `notify_whatsapp = false` no cliente continua barrando em silêncio, como antes; e os avisos que não são do catálogo (plano enviado, plano aprovado, tarefa atribuída) seguem sem gate de canal — preferência da equipe é o UX-06. Na revisão (01/11) os **três achados procederam** e foram corrigidos: (a) a entrega barrada pelo canal **queimava a idempotência** — `whatsAppToClient` gravava o `skipped` e voltava normalmente, então `AbstractAutomation::once()` escrevia o `automation_log` com `status='done'` como se tivesse entregue, e religar o canal nunca reenviava (`shouldRun` olha o `dedupe_key`, não o status): a agência corrigia a regra e continuava sem receber nada; agora o `once()` só marca o que de fato saiu — canal desmarcado deixa o `dedupe_key` livre, então religar o canal volta a entregar **enquanto a consulta daquela automação ainda devolver a linha** (ressalva (iii) da 2ª revisão abaixo; a entrega feita continua trancada, com teste provando as duas coisas); (b) a automação `content.approved_create_next_plan` entregava o aviso in-app por um evento **não mapeado** como automação (`plan.auto_created`, emitido em `maybeCreateNextPlan`), sem chave e sem gate — era o único checkbox "No sistema" que continuava mentindo: desmarcá-lo não calava o sino; agora o aviso passa pelo `inAppToPermission` com a chave da automação, e o canal barra o **aviso**, nunca a criação do rascunho (desligar a automação é o toggle dela, e `AUTOMACOES.md` passou a dizer isso); (c) o guard `report_url === null` do relatório mensal criava uma não-entrega **sem rastro nenhum** (nem `notification_jobs` nem `automation_log`, porque o `continue` vem antes do `notifyEvent`), num estado que é o normal e não a exceção: `ClientService::create` não gera `portal_token` e a cliente nasce com `portal_enabled = TRUE` e token nulo, então o suporte não tinha como responder por que ela não recebeu; agora a linha aparece na timeline como "Não enviado" com o motivo **próprio** (`NotificationService::skipClientDelivery`, `last_error = 'portal_off'`, texto em pt/en/es mandando gerar o acesso na ficha da cliente) e a tela deixou de carimbar "canal desligado" em toda linha pulada. 5 casos novos em `tests/Feature/AutomationChannelTest.php` (22 no total), cada negativa com a positiva ao lado, todos verificados **vermelhos** antes da correção. **Correção da nota de deploy acima:** o relatório do mês barrado não volta sozinho nem depois da migration — `next_run_at` já foi para o dia 1 do mês seguinte e não existe "rodar agora" na tela; o que a correção garante é que a rodada seguinte entregue e que nada fique gravado como enviado sem ter saído. Fora do item: `ClientService::maybeOnboard` segue gravando 'done' no log mesmo com o canal desmarcado (o gatilho é o cadastro do cliente, que nunca repete — a linha é inerte, e a mensagem de boas-vindas já aparece como "Não enviado"); com canal **e** portal desligados a timeline mostra o motivo do portal (os dois são verdade); e o aviso in-app bloqueado continua sem linha na timeline, porque não existe registro de entrega in-app. Na 2ª revisão (01/11) os **três achados procederam** (um de código, dois de documentação) e foram corrigidos: (i) a automação irmã do relatório mensal ficou com exatamente o defeito que este item resolveu — `content.approval_reminder` mandava o WhatsApp com o **link vazio** quando a cliente não tinha `portal_token` (o estado normal de quem foi cadastrada com o onboarding desligado) ou estava com o portal desligado (o link responde 403): `portalPlanUrl` devolvia `null`, `null ?? ''` virava `''` e a cobrança chegava como "🔗 " e nada depois — pior, o `once()` gravava `status='done'` e o passo d2/d4/d7 era consumido por uma mensagem inútil; agora o `ApprovalReminder` usa a mesma guarda do relatório mensal (`skipClientDelivery`, `last_error = 'portal_off'`, linha na timeline com o motivo) e **não consome o passo**, então, gerado o acesso, o degrau seguinte cobra; (ii) `AUTOMACOES.md` afirmava que "desmarcar todos os canais equivale a desligar a automação" — falso para as automações que fazem trabalho além de avisar (`RecurringInvoice` chama `once()` **sem canal**, e `maybeCreateNextPlan`/`maybeCreateProductionTasks` gravam `markRan` por conta própria: a fatura recorrente, o rascunho da semana e as tarefas continuam nascendo), e contradizia o próprio texto da automação nº 5 40 linhas abaixo; junto, a recomendação "ativar primeiro com e-mail/in-app" virou armadilha depois do gate, porque **nenhuma** automação entrega por e-mail hoje (WPP-01) — o doc agora diz que o canal barra o aviso e não o trabalho (toggle é que desliga), que o canal "E-mail" não entrega nada, e o kit de ativação deixou de recomendar e-mail; (iii) a promessa de (a) estava maior do que a correção: liberar o `dedupe_key` só reentrega **se a janela da automação ainda devolver a linha** — o lembrete D-3 existe apenas no dia D-3, o relatório mensal muda de período, o `ApprovalReminder` só volta no degrau seguinte (d4/d7) e o `/queue/scheduler` joga o `next_run_at` da regra diária para o dia seguinte; só `content.approval_escalation` (dedupe sem data) reentrega na rodada seguinte. O texto de (a) foi corrigido e o limite virou teste (`test_religar_o_canal_no_dia_seguinte_nao_recupera_o_lembrete`): a garantia real é que nada fique gravado como enviado sem ter saído. 4 casos novos em `tests/Feature/AutomationChannelTest.php` (26 no total); as duas negativas de (i) foram verificadas **vermelhas** antes da correção, com a positiva do mesmo fluxo ao lado. Fora do item: `plan.sent_for_approval` (o envio manual do plano) manda o mesmo `approval_url` e segue sem a guarda — não é automação do catálogo, não tem gate de canal e cai no UX-06/WPP-01; `client.onboarding` gera o token antes de mandar, mas não checa `portal_enabled`.
> **Ação:** O motor passa a ler automation_rules.channels (hoje desmarcar WhatsApp não impede o envio); relatório mensal deixa de mandar link interno que cai no login.
> **Arquivos:** AutomationService, NotificationService, Automations/ClientMonthlyReport
> **Pronto quando:** Teste: canal desmarcado não gera entrega; link do relatório abre no portal sem login.

### SEC-15 · Trilha de auditoria onde falta — `10h` · 5% · núcleo
> ✅ **FEITO (2026-11-01):** 48 pontos de escrita que mudavam dado sem deixar rastro passaram a gravar `activity_logs` — financeiro inteiro (fatura criada/editada/excluída/marcada como enviada/mandada por e-mail, pagamento registrado e removido, contrato criado/editado/excluído), tarefas (criar, editar, mover no kanban, excluir), conexão e desconexão de conta Meta e de conta orgânica (**sem o token**, que segue cifrado), as ações de anúncio de ponta a ponta (solicitada manual ou pela IA, aprovada, rejeitada, **executada na Meta** — pausar campanha e trocar orçamento era a ação mais sensível do app e não tinha linha nenhuma; a tentativa que falha também é registrada, sem o texto do erro da API, que já fica em `ads_actions.error_message`), insight de IA gerado e excluído, configurações da agência, o admin da plataforma (tenant criado/editado/excluído, usuário criado/editado, senha redefinida, reset enviado, status trocado, configurações globais — só **quais** chaves foram gravadas, nunca os valores — plano criado/editado e assinatura trocada) e a escrita do cliente no Drive pelo portal (enviar, mover, excluir, renomear, restaurar). `ActivityLogger::log` ganhou um `$agencyId` explícito no fim da assinatura para os dois casos em que a sessão não responde de quem é a ação: o **portal** não tem usuário logado nem `agency_id` (a linha caía em `agency_id = 0` e desaparecia da trilha da agência dona do cliente — agora vai cliente + agência + IP, com `user_id` nulo de propósito) e o **admin da plataforma** não tem agência própria, mas a ação é sobre um tenant, então a linha é gravada na trilha dele. De brinde: ligar/desligar o portal pelo formulário de edição do cliente (`ClientService::setPortalAccess`) criava ou destruía o `portal_token` calado — o `client_updated` não diz que o link do portal nasceu ou morreu — e agora grava `portal_access_granted`/`portal_access_revoked`, só quando muda de fato. Segurança: `Admin/SubscriptionPlanController` era o **único** controller de `/admin` sem guarda no método (dependia só do grupo em `routes/web.php:70`) — os 8 métodos ganharam `Auth::requirePlatformAdmin()`, com teste que chama o método direto, fora do middleware. `tests/Unit/AuditTrailCoverageTest.php` é o teste de arquitetura pedido: catálogo de 73 ações sensíveis (classe::método → ação, as 25 que já existiam incluídas) que falha se a chamada sair do método, se o método for renomeado sem atualizar a lista, ou se alguma chamada de log passar a carregar token/senha/chave (varredura do argumento de cada `ActivityLogger::log`, contando parênteses — o rótulo `password_reset` é legítimo, o valor `$password` não). `tests/Feature/AuditTrailTest.php` (17 casos) prova a linha **chegando ao banco** com agência/cliente/usuário certos, cada positiva com a negativa ao lado (fatura recusada, pagamento acima do saldo, status inválido e tarefa de outra agência não deixam rastro; token de portal inválido não grava nada); as três garantias centrais foram verificadas vermelhas desfazendo a correção. Sem migration. Fora do item: **nenhuma tela lê `activity_logs` ainda** — a trilha existe no banco e não tem onde ser consultada (é o OBS-03, que depende deste item); `BillingService::cancelSubscription` ficou sem log por não ter chamador nenhum (código morto); `/ia/recomendacoes` chama a IA num GET e gasta a chave da plataforma sem registro (as ações salvas a partir dela são logadas uma a uma); criar pasta no Drive segue sem log nos dois lados (painel e portal), igual a antes; e o `ActivityLogger` ainda lê `REMOTE_ADDR` direto em vez de `Request::ip()`, então atrás de proxy confiável o IP da trilha é o do proxy (`TRUSTED_PROXIES` é respeitado no resto do app). Na revisão (02/11) os quatro achados distintos dos revisores procederam e foram corrigidos: (a) o `user_agent` ia **cru** do header para o INSERT e o `catch (\Throwable)` do próprio `ActivityLogger` engolia a falha — `User-Agent: Mozilla \xC3\x28` faz o Postgres recusar o parâmetro (SQLSTATE 22021, o mesmo que o `FinanceRigorTest` já provava neste banco), então a ação acontecia **sem linha nenhuma** na trilha, nos 75 pontos de uma vez: quem quisesse agir sem rastro só precisava escolher o próprio User-Agent; agora o header passa por `utf8Safe` (`mb_check_encoding`/`mb_convert_encoding`) e o `json_encode` do metadata usa `JSON_INVALID_UTF8_SUBSTITUTE` (antes devolvia `false` no primeiro byte inválido e a linha nascia com o metadata vazio — a linha sobrevivia, o conteúdo não); (b) as três ações de aprovação do portal — `content_plan.approved`, `content_plan.revision_requested` e `approval.feedback_added` — gravavam **sem** o `$agencyId` explícito que este item criou justamente para o portal, então a aprovação da semana pela cliente (a ação de portal mais relevante do produto), o pedido de alteração e cada feedback de post caíam em `agency_id = 0`, enquanto a escrita do mesmo cliente no Drive, pelo mesmo portal, caía na agência certa — trilha incoerente entre duas telas do mesmo portal, e o OBS-03 (que depende deste item) filtra por `agency_id`; `approvePlan` e `requestRevision` já tinham a agência na variável local e `addFeedback` ganhou um `?int $agencyId = null` no fim da assinatura, passado por nome em `PortalController::itemFeedback` (pela tela interna a sessão continua respondendo); (c) `TaskController::update` gravava `task.updated` sem a guarda `if ($prevTask)` dos dois métodos irmãos criados no mesmo item (`updateStatus` e `destroy`) — `TaskRepository::updateTask` é escopado por `agency_id` e é no-op em tarefa inexistente ou de outra agência, mas o log era escrito de qualquer forma, com `status_before` nulo: a trilha afirmava uma edição que o banco nunca recebeu, com o título escolhido por quem chamou; (d) o upload pelo **painel** (`upload` e `uploadComplete`) seguia sem trilha enquanto o mesmo envio pelo portal gravava `drive.file_uploaded`, e `drive_files` não tem coluna de autor (só `uploaded_via`): "quem subiu esse arquivo para o cliente?" tinha resposta quando a origem era a cliente e não tinha quando era a equipe — as duas entradas foram para o catálogo do teste de arquitetura, que passou a 75 ações. 8 casos novos em `tests/Feature/AuditTrailTest.php` (25 no total), cada um verificado vermelho antes da correção (a positiva da edição de tarefa e a do upload pelo painel acompanham as negativas, para o "não gravou" não passar por 404). Também fora do item: criar pasta no Drive segue sem log nos dois lados, como antes; e `approvePlan` grava a linha mesmo quando o UPDATE não afeta linha nenhuma (corrida de dois "Aprovar tudo" simultâneos — a régua de status barra o caso repetido, não o concorrente). Na 2ª revisão (02/11) o único achado procedeu e foi corrigido: a correção (b) da revisão anterior parou no meio da pilha de chamada — `approvePlan` passou a gravar `content_plan.approved` na agência dona e, no mesmo request, chama `maybeCreateNextPlan`, que gravava `content_plan.auto_created` **sem** o `$agencyId`, e no caminho sem modelo semanal chama `duplicate`, que gravava `content_plan.duplicated` também sem ele: com a automação `content.approved_create_next_plan` ativa e o opt-in da cliente, o clique em "Aprovar tudo" deixava a aprovação na agência certa e o plano nascido dela em `agency_id = 0` — "quem criou o plano da semana seguinte?" sem resposta na trilha da agência dona, e o OBS-03, que depende deste item, filtra por `agency_id`; de quebra `content_plan.duplicated` levava `user_id = created_by` do plano de origem, creditando ao gestor uma criação disparada pelo clique da cliente (e `user_id = 0`, que não é usuário nenhum, quando o plano de origem não tinha autor), então `duplicate` ganhou um `bool $byAutomation = false` no fim da assinatura: pela automação a linha vai com `user_id` nulo e `automated: true` no metadata, e pelo painel continua creditando quem clicou. 2 casos novos em `tests/Feature/AuditTrailTest.php` (27 no total) — o plano criado pela automação do portal, com as duas linhas na agência dona e sem ator, e a positiva do mesmo log pelo painel (`/conteudo/{id}/duplicar` credita o usuário da sessão) —, cada metade da correção verificada vermelha desfazendo-a. Também fora do item: a automação irmã `content.approved_create_tasks` cria as tarefas de produção direto pelo repositório, sem linha nenhuma de trilha (`task.created` só existe no `TaskController`) — mesma classe de problema, não levantada pelos revisores.
> **Ação:** ActivityLogger em fatura, pagamento, contrato, admin da plataforma (tenant, plano, usuário), ações de anúncio, conexão Meta, tarefas, portal (regenerar token, ligar/desligar) e escrita no Drive.
> **Arquivos:** InvoiceService, PaymentService, ContractService, Admin/*, AdsActionService, TaskController, PortalController, PortalDriveController
> **Pronto quando:** Teste de arquitetura lista as ações sensíveis e falha se alguma não grava activity_logs.

### SEC-16 · Rigor no dinheiro — `8h` · 0% · núcleo
> ✅ **FEITO (2026-11-01):** dinheiro do formulário passa por `Support\Money` (novo): "1.234,56" virava **1,23** no `(float) str_replace(',', '.', …)` e texto virava 0, então desconto, valor de contrato e pagamento entravam mil vezes menores ou zerados, calados. Agora a fatura exige título, aceita só `draft|sent|cancelled` no POST (as derivadas `paid`/`partial`/`overdue` vêm de pagamento e vencimento — um POST cru criava fatura já **paga** com `amount_paid = 0`), recusa desconto maior que o subtotal **antes** do INSERT (não há transação: recusar no meio deixaria fatura sem item, e `recalcTotals` gravava total negativo que o painel somava no faturado) e confere a faixa de `DECIMAL(12,2)`. O pagamento exige valor > 0, no máximo o **saldo** (somado dos pagamentos, não da coluna `amount_paid`), forma por allowlist, e as comparações de saldo são em centavos (`Money::cmp`) com o saldo arredondado a 2 casas. `$e->getMessage()` saiu da tela nos 3 controllers: excluir fatura com pagamento mostrava `SQLSTATE[23503] … payments_invoice_id_fkey` ao usuário e agora explica o que fazer (`finance.invoice_has_payments`), o resto vira texto amigável via `t()` com o detalhe no `error_log` (`Controller::withFailure`, nova `Core\ValidationException` = "esta mensagem é para a tela"); `HttpException` é relançada para o 403/404 do guard continuar valendo. Remover pagamento volta para `/faturas/{id}` em vez do `Referer` (redirect aberto), `markOverdueAll` virou **um** UPDATE escopado por agência (era um por fatura a cada carga de `/financeiro` e `/faturas`) e `findByClient` de fatura e contrato passou a **exigir** a agência (o portal chama com o cliente resolvido pelo token; a query multi-tenant não pode depender de quem chama). `FinanceRigorTest` (13 casos de feature, cada correção verificada desfazendo-a) e `MoneyTest` (16 casos unitários). De brinde: a tela de editar fatura mantém a situação atual no `<select>` — sem a opção, o navegador marcava "Rascunho" e salvar só o título devolvia fatura **paga** para rascunho; e a coluna "Emissão" do portal lia `issue_date`, coluna que `invoices` nunca teve (warning em toda linha, data vazia) — agora mostra a criação. Sem migration. Fora do item: `currency_code` segue sem allowlist; o financeiro não grava `activity_logs` (SEC-15); `Controller::back()` e os controllers de tráfego, IA e Drive ainda jogam `$e->getMessage()` na tela; e dois pagamentos simultâneos ainda podem passar do saldo (sem `SELECT … FOR UPDATE`). Na revisão (02/11) os três achados dos revisores procederam e foram corrigidos: (a) `sanitizeItems` não conferia o **tamanho da descrição** (`invoice_items.description` é `varchar(500)` e o campo da tela não tinha `maxlength`) e `syncItems` apaga todos os itens antes de reinserir, sem transação — 600 caracteres num item estouravam o INSERT no meio do laço (SQLSTATE 22001) **depois** do DELETE dos antigos, e como `updateById` grava `subtotal`/`total` zerados e quem conserta é o `recalcTotals` da última linha, a fatura ficava com os itens pela metade e total 0,00 enquanto a tela dizia "não foi possível salvar" (mensagem afirmando o contrário do que aconteceu); agora a descrição acima de 500 caracteres é recusada antes de gravar (`finance.item_description_too_long` em pt/en/es + `maxlength` nos dois formulários) e criar/editar fatura roda dentro de `InvoiceRepository::transaction` (mesma forma de `ContentPlanRepository::moveItemToPlan`), então erro de banco no meio dos itens desfaz tudo; (b) a situação virou **derivada** neste item, mas `update` não a re-derivava: fatura paga que ganhava item seguia "Paga" com saldo em aberto e o devido sumia do `pending_total` do painel (que só soma `sent|overdue|partial`) — `refreshStatus`, que estava sem chamador nenhum, agora roda no fim do `update`, dentro da transação, e devolve `partial` (e `paid` quando o total volta ao que já foi pago); (c) `PaymentService::record` não olhava a situação da fatura — a régua existia só na view (`faturas/show.php` esconde o botão fora de `sent|overdue|partial`), e `POST /pagamentos` numa fatura **cancelada** era aceito com `refreshInvoiceStatus` gravando `paid` em cima: a cancelada, que `summaryByAgency` deixa fora do faturado e do recebido, ressuscitava somando nos dois; agora cancelada e rascunho são recusadas com mensagem própria (`finance.payment_invoice_cancelled` e `finance.payment_invoice_not_sent` em pt/en/es) e `paid` cai na mensagem de saldo. 4 casos novos em `FinanceRigorTest` (17 no total), cada um verificado desfazendo a correção. Também fora do item: `GET /pagamentos/novo?invoice_id=` ainda monta o formulário para fatura cancelada ou em rascunho (a recusa é no POST); `refreshStatus` não devolve para `sent` a fatura `paid` com `amount_paid = 0` (linha legada — no caminho do pagamento quem faz isso é `PaymentService::delete`); e **decisão do dono**: baixar o total da fatura abaixo do que já foi pago continua permitido (a situação é re-derivada para `paid`, mas o recebido passa a ser maior que o faturado) — recusar a edição nesse caso é escolha de produto. Na 2ª revisão (02/11) os dois achados distintos procederam e foram corrigidos: (a) a chamada nova ao `refreshStatus` no fim do `update` tratava `cancelled` como situação **derivável** — gravava `cancelled`, relia `amount_paid > 0` e devolvia `partial`/`paid` na linha seguinte, dentro da mesma transação: nenhuma fatura com pagamento podia mais ser cancelada (e a tela dizia "Fatura atualizada."), e a cancelada legada com pagamento voltava para o faturado e o recebido do painel ao salvar qualquer campo — é regressão deste item, porque antes o `refreshStatus` não tinha chamador nenhum; agora `cancelled` é **terminal** na re-derivação, aqui e em `PaymentService::refreshInvoiceStatus` (sem o segundo guarda, remover um dos pagamentos que sobraram na cancelada a devolvia para `partial` — buraco que a própria correção (a) abria, porque cancelar fatura com pagamento passou a funcionar), e reabrir a fatura na edição (`sent`) volta a derivar a situação do saldo, como a mensagem `finance.payment_invoice_cancelled` promete; (b) `contract_id` era o único campo do formulário de fatura ainda cru e a FK só exige que o contrato **exista**: um POST com o id de um contrato de outra agência vinculava a fatura a ele, e os dois JOINs de `contracts` (`listByAgency`, `findWithDetails`) não casavam agência — o título do contrato alheio saía na tela e no PDF que `sendByEmail` anexa ao e-mail do cliente; agora o contrato passa por `ContractRepository::findWithClient` escopado (`finance.contract_out_of_agency` em pt/en/es) e os JOINs casam `co.agency_id = i.agency_id` (a linha legada continua listada, sem o título alheio). 3 casos novos em `FinanceRigorTest` (20 no total), cada um verificado desfazendo a correção. Também fora do item: o `<select>` de contrato da edição só lista contrato **ativo** do cliente, então editar fatura ligada a contrato expirado (ou alheio, legado) reenvia vazio e desfaz o vínculo sem avisar — mesma classe da correção de cliente arquivado do SEC-11, decisão de produto.
> **Ação:** Validar no servidor valor (>0, ≤ saldo), desconto e status por allowlist; trocar $e->getMessage() (SQLSTATE na tela) por erro amigável; remover o redirect pelo Referer.
> **Arquivos:** InvoiceService, PaymentService, ContractService, os 4 controllers financeiros
> **Pronto quando:** POST cru com pagamento negativo, acima do total ou status inventado é recusado; testes de feature do financeiro.

### SEC-17 · Dependências com CVE e PHP de produção — `3h` · 0% · núcleo
> ✅ **FEITO (2026-11-02):** `composer audit` limpo (cakephp/database 5.4.4, guzzle 7.16.1, sodium_compat 2.5.2) e a suíte **verde em PHP 8.3.36**: 561 testes, 1978 asserções, **zero pulado** — exatamente o mesmo resultado do 8.5 local. Rodada num container descartável (`php:8.3-cli` + `pdo_pgsql`/`pgsql`, `gd` com jpeg/freetype/webp para o `imagecreatetruecolor` do `BrandingTest`, `zip` e `nodejs` para os 5 casos do `NotificationBellClickTest`, que executam o JS do sino de verdade e se auto-skipam sem `node` no PATH), com o repositório montado como volume e **nada alterado no projeto**. O `composer.lock` já fixa `platform.php = 8.3.0`, então `audit` e `analyse` também avaliam contra 8.3. **Atenção:** a guarda do `tests/bootstrap.php` é mais estrita que a do `phinx.test.php` — aceita só `127.0.0.1`/`localhost` (não `db`/`postgres`) e os valores do arquivo `.env.testing` sobrescrevem `$_ENV`, então **passar `DB_HOST` por variável de ambiente não tem efeito** no phpunit: o jeito de rodar em container sem editar arquivo nem contornar guarda é dar ao container um caminho de loopback até o banco local (aqui, `--network host` no Docker Desktop, que faz `127.0.0.1:55432` cair no `yve_test_db` publicado — o host continua sendo local de verdade, a guarda é satisfeita e não burlada). Receita: `docker run --rm --network host -v <repo>:/app -w /app <imagem> php vendor/bin/phpunit`.
> **Ação:** Atualizar cakephp/database (SQLi crítico), guzzle e sodium_compat; rodar a suíte em PHP 8.3 (local está em 8.5).
> **Arquivos:** composer.json, composer.lock
> **Pronto quando:** composer audit sem advisory; suíte verde em 8.3.

### SEC-18 · Login e portal endurecidos — `6h` · 0% · núcleo
> ✅ **FEITO (2026-11-01):** fechado o que faltava — as rotas públicas do portal ganharam `PortalRateLimitMiddleware` (variante do limitador do login, que virou extensível: `bucket()` e `tooManyRequests()`), com cesta por **IP + token** em vez de por path (trocar de rota não dá cota nova) e teto só nas **mutações**: 600/60 s no envio de arquivos (cada arquivo são 2 POSTs nossos, 2 em paralelo — os bytes vão direto do navegador ao Drive) e 60/60 s nas demais (aprovar, feedback, pasta, renomear, excluir, mover — ritmo humano); a **leitura não entra na cota**, incluindo o proxy `/drive/file/{id}/raw`, chamado uma vez por miniatura: teto ali quebraria a galeria em silêncio (a miniatura some no `onerror`) — ou seja, a **banda** do portal segue sem cota, que é limite de volume (cota por cliente/dia) e não de taxa, fica para outro item. O estouro fala o idioma do cliente (tela `portal/rate_limited.php` + `portal.rate_limited.*` em pt/en/es, D14) e em JSON devolve a mensagem traduzida com `retry_after`, em vez da tela de 429 do login com link para `/login`. De brinde, o `portal_token` passou a sair com `e()` em todo `href`/`action` do portal (plan_show, index, plans, plans_calendar e o layout): o token é hex gerado pela aplicação, então não havia XSS alcançável, mas era a invariante 4 quebrada em 25 pontos. `PortalRateLimitTest` (8 casos, cada negativa com a positiva ao lado — inclusive a regressão do limitador do login em 5 tentativas —, verificados um a um desfazendo a correção). Sem migration. Antes disso, no mesmo item: bloqueio por conta (em arquivo, sem migration, para nunca travar o painel de migrations), resposta igual para e-mail inexistente, cookie `Secure` atrás do proxy, queda da sessão ao trocar senha ou desativar a conta, hash fora do arquivo de sessão e senha gerada fora do flash.
> **Ação:** Bloqueio por conta após N falhas, mesma resposta para e-mail inexistente/inativo, cookie Secure atrás do proxy, rate limit nas rotas públicas do portal, senha fora do flash do admin.
> **Arquivos:** AuthService, public/index.php, routes/web.php, Admin/TenantController
> **Pronto quando:** Testes de lockout e de rate limit do portal; cookie de sessão sai com Secure em produção.

### QA-05 · Suíte que não mente — `14h` · 5% · núcleo
> ✅ **FEITO (2026-11-01):** `docker stop yve_test_db && composer test` agora termina em **0 erro** (eram 10): o `tearDown` do `BrandingTest` só limpa a pasta temporária com `isset` (sem banco o `setUp` pula antes de criar a propriedade tipada) e a subclasse anônima do `RepositoryScopeTest` tem construtor vazio — o escopo por agência depende só da sessão, não da conexão. `ViewsResolveTest` (novo) resolve no disco **toda** view citada em `app/` (129 referências: `view()`, `Response::view()`, `View::render()`, `fromView()`) mais todo `view_partial`/`view_layout` citado nas views, com a guarda de que a varredura achou algo — era o furo que deixou duas views inexistentes chegarem à main, e o teste de aridade do BUG-06 já estava na suíte (`RoutesResolveTest::test_every_route_handler_accepts_a_single_argument`), confirmado. `LoginLogoutTest` (novo, 8 casos) fecha a lacuna de login/logout pela rota real: senha certa abre a sessão com as permissões vindas do papel no banco, sem hash na sessão e com `activity_logs`; senha errada, falta de CSRF (419) e visitante no logout não mexem na sessão; `?redirect_after_login` respeitado — cada negativa com a positiva ao lado. QA-04: `npm run test:browser` virou um comando só (`tests/browser/smoke.sh`) que semeia login + portal descartáveis no banco de teste (`tests/browser/seed.php`, idempotente, lê só o `.env.testing` e aborta em host não-local), sobe o `php -S` nesse banco e roda as **8 telas sem nenhum pulado** — sem credencial, o `smoke.mjs` agora **falha** em vez de abrir só o `/login` e dizer "✅ nenhum erro". Documentado em `docs/OPERACAO.md` §6. **Achado perigoso no caminho:** no SAPI web do `php -S`, `$_ENV` nasce vazio, o dotenv imutável não vê as variáveis exportadas e grava as do `.env` — o servidor local sobe contra o **Supabase de produção** mesmo com `DB_HOST=127.0.0.1` exportado; o smoke sobe com `-d variables_order=EGPCS` e só abre o navegador depois de um login de conferência por HTTP. Cobertura conferida (já existia): financeiro `FinanceRigorTest` (20) + `MoneyTest`, aprovações `ApprovalScreenTest` (27) + `PortalApprovalTest`, RBAC `RoleEscalationTest`/`MiddlewareAuthorizationTest`/`DefaultRolesReachTest`, Drive `DriveSyncTest` (17), auth `PasswordResetTest`/`AuthorizationTest`. Suíte: 557 testes verdes com banco, 0 erro sem banco. Fora do item: sem banco, testes que chamam o `ActivityLogger` ainda imprimem "ActivityLogger failed: …" no stderr (o logger falha aberto de propósito — é ruído, não erro), e o `NotificationBellClickTest` pula 1 caso quando o `node` não está no PATH. Na revisão (02/11) dos três achados, **um não procedeu, dois procederam**: (a) os dois revisores afirmaram que tela respondendo 500/403/404 entrava como ✅ — **não procede**: reproduzido contra um servidor-dublê (500, 403, 404, redirect e 200, um por tela), o Chromium loga "Failed to load resource: … 500" para o documento principal com erro e as três já davam ❌ pelo listener de console; (b) o **redirect**, porém, era furo real e passava calado — redirect não gera erro de console nenhum, então `/tarefas` respondendo 302 → `/login` (sessão caída) imprimia ✅ tendo validado só o formulário de login; `check()` agora guarda a resposta do `page.goto`, exige 2xx e compara a URL final com a pedida (conferir o status ficou como rede: tira o gate da dependência de um comportamento de console não documentado e diz *qual* status veio); (c) o vazamento dos workers procedeu e já estava **acontecendo na máquina** — 4 órfãos da rodada anterior segurando a 8099 (ppid 1), e duas rodadas seguidas devolveram 8 ✅ servidas pelos **mesmos PIDs da primeira**, porque o `php -S` novo morria com "Address already in use" e o script só perguntava "alguém responde?". Agora o `smoke.sh` mata o grupo de processos (`set -m` + `kill -- -$SERVER_PID`), recusa começar com a porta ocupada (listando os PIDs e o `kill` pronto), confere `kill -0 "$SERVER_PID"` dentro do readiness e põe `--max-time` nos probes — sem o timeout, um processo que aceita a conexão e nunca responde travava o script para sempre (verificado prendendo a porta com um socket em listen). `BrowserSmokeGuardsTest` (4 casos, os 4 conferidos falhando contra as versões anteriores). Gates: 561 testes verdes com banco, **0 erro** sem banco (`docker stop yve_test_db`), PHPStan 0, e `npm run test:browser` verde duas vezes seguidas sem deixar órfão. Fora do item: a lista `IGNORE` do `smoke.mjs` testa a regex contra `msg.text()`, mas a URL do recurso vem em `msg.location()` — o filtro de ruído de favicon não filtra nada; é inofensivo hoje (os layouts declaram `rel="icon"`, então o Chromium não pede `/favicon.ico`, e o efeito seria falso **vermelho**, não falso verde).
> **Ação:** Testes pulam limpo sem Postgres (hoje 10 dão erro); teste que resolve toda view referenciada e a aridade de todo handler; testes de financeiro, auth, RBAC, aprovações e sync do Drive. Inclui o QA-04 (smoke com credenciais).
> **Arquivos:** tests/Feature/*, tests/Unit/*, FeatureTestCase
> **Pronto quando:** composer test sem banco: 0 erros; npm run test:browser sem nenhum pulado.

### Achados da execução da F0 (2026-11-02)
> Os 15 itens originais estão ✅: 561 testes em PHP 8.3 e 8.5, PHPStan zerado, audit limpo. Estes ficaram fora do critério deles e entram como itens novos.
> **SEC-19 · `4h`:** cards de `/financeiro`, `/financeiro/relatorios` e KPIs do dashboard somam a agência inteira, mesmo para quem não alcança todos os clientes. **Pronto quando:** perfil sem `clients.view_all` vê só a soma dos seus clientes, ou o dono decide que quem tem permissão de financeiro vê o total.
> **SEC-20 · `1h`:** `DELETE /ia/{id}` apaga insight só com `ai_insights.view`. **Pronto quando:** exige permissão própria, com teste negativo.
> **SEC-21 · `6h`:** `DriveUploadService.php:433` deixa todo arquivo enviado público por link, para o preview nativo funcionar. **Pronto quando:** o dono decide manter ou trocar por proxy autenticado (`streamResponse` já existe).
> **SEC-22 · `3h`:** dois pagamentos simultâneos passam do saldo (falta `FOR UPDATE`), `/pagamentos/novo` monta o formulário para fatura cancelada, `currency_code` sem allowlist. **Pronto quando:** teste cobre os três.
> **BUG-08 · `6h`:** plano aprovado não reabre, então post em revisão dentro dele não tem saída; plano `rejected` também não. **Pronto quando:** o dono decide entre "reabrir semana" e duplicar o plano, e o fluxo escolhido tem teste.
> **BUG-09 · `3h`:** `create`/`update` aceitam dois planos do mesmo cliente na mesma semana, o que deixa o reagendamento ambíguo. **Pronto quando:** a colisão é recusada com mensagem.
> **BUG-10 · `2h`:** `plan.sent_for_approval` manda link vazio a cliente sem token; `client.onboarding` manda link de portal desligado. **Pronto quando:** teste cobre os dois.
> **FE-07 · `5h`:** negativa de alcance aparece como "Forbidden" em inglês em ~9 pontos do financeiro; `Controller::back()`, tráfego, IA e Drive ainda mostram `$e->getMessage()`. **Pronto quando:** erro traduzido em todos, sem texto cru.

---

## F1 — Infra de base · 25 h · 14% feito

> Worker na VPS, pooler, fila e logs. Lembrete com hora exata, sync pesado da Meta e publicação agendada não cabem no cron HTTP da Hostinger.

| ID | Entrega | Horas | % feito | Núcleo | Depende de |
|----|---------|------:|--------:|:------:|------------|
| INFRA-04 | ⏸️ Worker da fila na VPS | 10 | 0% | ○ | Suspenso pela D2 |
| INFRA-02 | Conexão persistente × pooler do Supabase | 4 | 0% | ● | — |
| INFRA-05 | Fila robusta | 5 | 60% | ● | — |
| OBS-03 | Logs que existem | 6 | 10% | ● | SEC-15 |

### INFRA-04 · ⏸️ Worker da fila na VPS — `10h` · 0% · **suspenso pela D2 (01/11)**
> **Decisão:** o dono optou por manter só o cron da Hostinger e pediu explicação do que o worker faria. Nada será feito na VPS. Reabrir apenas se o dono pedir.
> **Ação:** Container no EasyPanel da VPS de Paris rodando o consumidor de jobs a cada minuto contra o mesmo banco. A Hostinger segue servindo o app; o cron HTTP vira fallback.
> **Arquivos:** QueueController (extrair runner CLI), bin/worker.php, Dockerfile, docs/OPERACAO.md · **Externo:** VPS Paris
> **Pronto quando:** Job agendado para 10:00 sai entre 10:00 e 10:01; /api/health mostra o heartbeat do worker.

### INFRA-02 · Conexão persistente × pooler do Supabase — `4h` · 0% · núcleo
> **Ação:** Desligar ATTR_PERSISTENT ou apontar para a porta de session pooling; medir latência antes e depois.
> **Arquivos:** app/Core/Database.php, docs/OPERACAO.md
> **Pronto quando:** Sem 'prepared statement already exists' em carga de 20 req simultâneas; número registrado no doc.

### INFRA-05 · Fila robusta — `5h` · 60% · núcleo
> **Ação:** Reaper de jobs presos em reserved; orçamento de tempo por requisição; queda da instância WhatsApp no /api/health com alerta; docs/CRON.md corrigido (hoje não cita /queue/scheduler).
> **Arquivos:** JobRepository, QueueController, HealthService, docs/CRON.md
> **Pronto quando:** Job morto no meio volta a pending em 10 min; instância desconectada aparece como falha no health.

### OBS-03 · Logs que existem — `6h` · 10% · núcleo
> **Ação:** Router::handleError grava no Monolog com request-id (hoje o Logger tem zero chamadores); tela de leitura do activity_logs por agência.
> **Arquivos:** Core/Router, Core/Logger, helpers.php, nova view de auditoria
> **Pronto quando:** Todo 500 em produção tem uma linha de log com request-id; admin da agência filtra a auditoria por usuário e ação.

---

## F2 — Central de WhatsApp · 105 h · 12% feito

> Evolution com o número central da agência, avisos para equipe e clientes. É o canal que faz as automações chegarem em alguém. Hoje nunca foi validado e ignora os canais escolhidos.

| ID | Entrega | Horas | % feito | Núcleo | Depende de |
|----|---------|------:|--------:|:------:|------------|
| INT-01 | Validar a Evolution ponta a ponta | 6 | 20% | ● | — |
| WPP-01 | Paridade de e-mail e fallback | 10 | 25% | ● | BUG-07 |
| WPP-02 | WhatsApp para a equipe | 6 | 0% | ● | INT-01, UX-06 |
| UX-06 | Centro de notificações e preferências | 16 | 20% | ● | BUG-06 |
| WPP-07 | Enviar a planificação ao cliente | 12 | 20% | ● | INT-01 |
| WPP-03 | Respostas do cliente: APROVADO e PARAR | 12 | 10% | ● | INT-01 |
| WPP-04 | Telefone internacional, contatos e grupos | 7 | 15% | ● | WPP-01, Decisão D8 |
| WPP-05 | Mensagens editáveis por agência | 9 | 10% | ○ | WPP-01 |
| WPP-06 | Mídia no WhatsApp | 8 | 0% | ○ | INT-01 |
| AUTO-01 | Kit recomendado em 1 clique | 3 | 0% | ● | INT-01 |
| AUTO-02 | Automações parametrizáveis | 16 | 0% | ○ | WPP-05, Decisão D7 |

### INT-01 · Validar a Evolution ponta a ponta — `6h` · 20% · núcleo
> **Ação:** Rodar o roteiro do OPERACAO.md §4 na instância do dono; botão 'enviar mensagem de teste'; payload v2 corrigido (delay/presence no topo); teste de contrato com cURL mockado.
> **Arquivos:** EvolutionApiService, WhatsAppController, settings/whatsapp.php · **Externo:** Instância Evolution do dono
> **Pronto quando:** Mensagem de teste chega no celular; erro provocado aparece na timeline e no alerta.

### WPP-01 · Paridade de e-mail e fallback — `10h` · 25% · núcleo
> **Ação:** 5 templates de e-mail faltantes (fatura a vencer/vencida, lembrete de aprovação, relatório, boas-vindas); e-mail como fallback quando o WhatsApp está desconectado; respeitar notify_email do cliente.
> **Arquivos:** NotificationService, EmailService, resources/views/emails/*
> **Pronto quando:** Cada automação de cliente entrega por e-mail com o WhatsApp desligado; teste por evento.

### WPP-02 · WhatsApp para a equipe — `6h` · 0% · núcleo
> **Ação:** users.whatsapp + opt-in; resumo diário, tarefa atrasada, aprovação escalada, menção e contrato vencendo passam a sair também pelo número central para quem optou.
> **Arquivos:** migration, UserRepository, NotificationService, tela de perfil
> **Pronto quando:** Membro com opt-in recebe o resumo diário no WhatsApp; sem opt-in, só in-app.

### UX-06 · Centro de notificações e preferências — `16h` · 20% · núcleo
> **Ação:** Página /notificacoes com histórico, lidas/não lidas e filtro; tabela de preferências usuário × evento × canal respeitada por todo o NotificationService.
> **Arquivos:** SettingsController, NotificationService, nova view, migration
> **Pronto quando:** Usuário desliga 'tarefa atrasada no WhatsApp' e deixa de receber; teste cobre o filtro.

### WPP-07 · Enviar a planificação ao cliente — `12h` · 20% · núcleo
> **Ação:** Botão enviar/reenviar plano por WhatsApp e e-mail com o link do portal; aviso à equipe quando o cliente comenta; aprovar tudo em lote no portal; histórico de aprovação visível.
> **Arquivos:** ContentPlanService, NotificationService, content/show.php, portal/plan_show.php
> **Pronto quando:** Do plano em rascunho até a cliente aprovar pelo celular sem ninguém copiar link à mão.

### WPP-03 · Respostas do cliente: APROVADO e PARAR — `12h` · 10% · núcleo
> **Ação:** Tratar MESSAGES_UPSERT do webhook: log das mensagens, opt-out 'PARAR' consultado antes de cada envio, 'APROVADO' aprova o plano pendente daquele cliente com trilha.
> **Arquivos:** WebhookController, NotificationService, ContentPlanService, 2 migrations
> **Pronto quando:** Cliente responde PARAR e não recebe mais; responde APROVADO e o plano muda de status com activity_log.

### WPP-04 · Telefone internacional, contatos e grupos — `7h` · 15% · núcleo
> **Ação:** DDI por cliente (hoje todo número vira +55), JID de grupo preservado; destinatário por tipo de contato (aprovador recebe conteúdo, financeiro recebe fatura).
> **Arquivos:** EvolutionApiService, NotificationService, migration
> **Pronto quando:** Número de Portugal e grupo @g.us recebem corretamente; testes de normalização.

### WPP-05 · Mensagens editáveis por agência — `9h` · 10%
> **Ação:** Tabela message_templates (evento, idioma, texto) com fallback no padrão do código, tela com pré-visualização e lista de variáveis permitidas.
> **Arquivos:** NotificationService, migration, controller e view novos
> **Pronto quando:** Agência troca o texto do lembrete de aprovação e o próximo envio usa o texto novo.

### WPP-06 · Mídia no WhatsApp — `8h` · 0%
> **Ação:** sendMedia da Evolution: PDF da fatura na régua de cobrança e imagem de capa no envio do plano.
> **Arquivos:** EvolutionApiService, NotificationService
> **Pronto quando:** Lembrete de fatura chega com o PDF anexado.

### AUTO-01 · Kit recomendado em 1 clique — `3h` · 0% · núcleo
> **Ação:** Banner em /automations quando tudo está desligado e botão que liga o kit do AUTOMACOES.md, agora incluindo WhatsApp.
> **Arquivos:** AutomationController, automations/index.php
> **Pronto quando:** Agência nova ativa o kit em 1 clique; activity_log registra.

### AUTO-02 · Automações parametrizáveis — `16h` · 0%
> **Ação:** Expor o que hoje é fixo no código (degraus dos lembretes, dias de antecedência, horário, público, canal, mensagem) em automation_rules.params, validado por schema do catálogo.
> **Arquivos:** config/automations.php, AutomationService, automations/*, migration
> **Pronto quando:** Agência muda o lembrete de fatura de 3 para 5 dias antes sem deploy.

---

## F3 — Tarefas e chat · 185 h · 13% feito

> Tarefas nível ClickUp, lembretes, automações por evento, chat interno. Hoje é um kanban de 4 colunas com comentário que não avisa ninguém.

| ID | Entrega | Horas | % feito | Núcleo | Depende de |
|----|---------|------:|--------:|:------:|------------|
| TASK-01 | Modelo de dados de tarefa completo | 18 | 0% | ● | BUG-06, Decisão D4 |
| TASK-02 | Detalhe da tarefa como central | 26 | 10% | ● | TASK-01 |
| TASK-03 | Visões: lista, kanban, calendário, minhas tarefas | 26 | 25% | ● | TASK-01 |
| TASK-04 | Lembretes de tarefa multicanal | 14 | 30% | ● | TASK-01, UX-06, INFRA-05 |
| TASK-05 | Automações de tarefa e modelos | 22 | 10% | ● | TASK-01, TASK-04 |
| TASK-06 | Esteira de produção do post | 28 | 15% | ● | BUG-05, TASK-05, Decisão D5 |
| CHAT-01 | Chat de tarefa e post | 22 | 20% | ● | BUG-06, TASK-01, Decisão D6 |
| CHAT-02 | Canais e mensagens diretas da equipe | 26 | 0% | ○ | CHAT-01 |
| TASK-07 | Desligar o ClickUp | 3 | 0% | ● | — |

### TASK-01 · Modelo de dados de tarefa completo — `18h` · 0% · núcleo
> **Ação:** tasks ganha parent_id, position, start_at, due_at com hora, estimativa e status por agência; tabelas de responsáveis, observadores, checklist, tags, histórico, lembretes e recorrência; FKs com ON DELETE em comentários e notificações.
> **Arquivos:** database/migrations, TaskRepository, novo TaskService
> **Pronto quando:** Migrations reversíveis; tarefas antigas migradas sem perda.

### TASK-02 · Detalhe da tarefa como central — `26h` · 10% · núcleo
> **Ação:** Subtarefas, checklist, vários responsáveis e observadores, descrição rica sanitizada, anexos ligados ao Drive do cliente, aba de histórico.
> **Arquivos:** TaskController, TaskService, tasks/show.php
> **Pronto quando:** Uma tarefa de Reels com 4 subtarefas e checklist anda de ponta a ponta sem sair da tela.

### TASK-03 · Visões: lista, kanban, calendário, minhas tarefas — `26h` · 25% · núcleo
> **Ação:** Lista com colunas e ordenação; kanban com ordem salva e colunas = status da agência (drag-and-drop já existe); calendário; 'Minhas tarefas'; filtros salvos; ações em lote.
> **Arquivos:** TaskController, routes/web.php, resources/views/tasks/*
> **Pronto quando:** Ordem do kanban persiste ao recarregar; filtro salvo reabre igual; 'Meu dia' mostra só as minhas.

### TASK-04 · Lembretes de tarefa multicanal — `14h` · 30% · núcleo
> **Ação:** Vence em 24h e em 1h, lembrete com data e hora escolhidas, atrasada; in-app, e-mail e WhatsApp respeitando as preferências e o espaçamento anti-ban.
> **Arquivos:** config/automations.php, app/Automations/*, jobs
> **Pronto quando:** Lembrete marcado para 15:30 sai na primeira execução do cron depois das 15:30 (precisão = intervalo do cron da Hostinger, D2).

### TASK-05 · Automações de tarefa e modelos — `22h` · 10% · núcleo
> **Ação:** Regras 'quando status muda para X → atribuir, avisar, criar subtarefa'; modelos de tarefa por tipo de conteúdo no lugar do maybeCreateProductionTasks fixo no código.
> **Arquivos:** AutomationService, task_templates, ContentPlanService
> **Pronto quando:** Aprovar um Reels cria as subtarefas do modelo 'Reels' com responsáveis e prazos.

### TASK-06 · Esteira de produção do post — `28h` · 15% · núcleo
> **Ação:** Estágio de produção separado do status de aprovação (roteiro, gravação, edição, pronto, agendado, publicado), prazo e responsável por post gerando tarefa na hora e aparecendo no 'Meu dia'.
> **Arquivos:** migration, ContentPlanService, ContentPlanRepository, content/show.php, TaskRepository
> **Pronto quando:** A equipe move o post de 'edição' para 'pronto' e o responsável seguinte é avisado.

### CHAT-01 · Chat de tarefa e post — `22h` · 20% · núcleo
> **Ação:** @menções com autocomplete e notificação, editar/excluir, reações, não lidas por conversa, atualização a cada 8s com a aba ativa (polling incremental, cabe na Hostinger). Uma implementação só, hoje são duas.
> **Arquivos:** InternalComment*, internal_comment_reads, NotificationService, public/js
> **Pronto quando:** @Ana recebe aviso em até 10s e o contador de não lidas zera quando ela abre.

### CHAT-02 · Canais e mensagens diretas da equipe — `26h` · 0%
> **Ação:** Canal por cliente e DM entre membros, não lidas e menções, reaproveitando o polling do CHAT-01.
> **Arquivos:** channels, channel_messages, channel_reads, nova tela
> **Pronto quando:** Equipe conversa sobre o cliente X sem sair da plataforma; escopo por agência testado.

### TASK-07 · Desligar o ClickUp — `3h` · 0% · núcleo
> **Ação:** Feature flag que tira rotas e o enfileiramento a cada salvamento de tarefa; service e tabelas ficam preservados para um importador futuro.
> **Arquivos:** TaskController, routes/web.php, config
> **Pronto quando:** Salvar tarefa não consulta nem enfileira nada do ClickUp.

---

## F4 — Central Meta · 248 h · 16% feito

> Ads, orgânico, publicação, webhooks, leads e comentários. Hoje é um leitor de métricas. Não publica, não recebe webhook, não gere token, não audita ação.

| ID | Entrega | Horas | % feito | Núcleo | Depende de |
|----|---------|------:|--------:|:------:|------------|
| META-01 | Camada única da Graph API | 12 | 25% | ● | — |
| META-02 | Conexão Meta de verdade | 24 | 25% | ● | META-01, Decisão D1 |
| META-03 | Sync em fila, incremental e sem N+1 | 24 | 30% | ● | META-01, INFRA-05 |
| META-04 | Painel de Ads completo | 16 | 40% | ● | META-03 |
| META-05 | Ações em campanha com trava e auditoria | 14 | 35% | ● | META-01, SEC-15, Decisão D11 |
| TRAF-01 | Alertas de anomalia de tráfego | 10 | 10% | ● | META-03, WPP-02 |
| AI-02 | Linhas editoriais com IA | 18 | 10% | ● | META-06 |
| META-06 | Orgânico corrigido | 14 | 40% | ● | META-01 |
| META-07 | Publicar e agendar no Instagram e Facebook | 32 | 0% | ● | META-02, INFRA-05, TASK-06 |
| PROD-07 | Planejou → publicou → performou | 10 | 0% | ● | META-07 |
| META-08 | Webhooks da Meta | 12 | 0% | ● | META-02 |
| META-12 | Relatório Meta para o cliente | 10 | 55% | ● | META-04, META-06 |
| META-13 | Testes da integração Meta | 16 | 5% | ● | META-01 |
| META-09 | Lead Ads | 18 | 0% | ○ | META-08, WPP-02 |
| META-10 | Comentários e menções | 20 | 0% | ○ | META-08 |
| META-11 | Inbox de mensagens diretas | 24 | 0% | ○ | META-08 |

### META-01 · Camada única da Graph API — `12h` · 25% · núcleo
> **Ação:** Cliente único: versão por config (hoje v21.0 fixa e fora da janela), retry com backoff, erros tipados (17/190/80004), leitura do rate limit, paginação por paging.next.
> **Arquivos:** novo app/Support/Graph*, MetaAdsService, MetaOrganicService, config/services.php
> **Pronto quando:** Trocar a versão é 1 linha de config; erro da Meta nunca vira zero no dashboard.

### META-02 · Conexão Meta de verdade — `24h` · 25% · núcleo
> **Ação:** Facebook Login for Business com escolha de contas, Pages e Instagram numa tela; orgânico por OAuth (hoje é colar token); verificação diária do token, alerta de expiração e revogação; callbacks de deauthorize e exclusão de dados.
> **Arquivos:** AdsAccountController, OrganicController, novo MetaConnectService · **Externo:** App Review da Meta
> **Pronto quando:** Cliente conecta em 3 cliques; token a 7 dias de vencer gera aviso no 'Meu dia'.

### META-03 · Sync em fila, incremental e sem N+1 — `24h` · 30% · núcleo
> **Ação:** Job por conta e nível, insights em lote por nível (hoje 1 chamada por anúncio), upsert em lote, cursor salvo e retomada.
> **Arquivos:** AdsSyncService, OrganicSyncService, app/Jobs, AdMetricsRepository
> **Pronto quando:** Conta com 300 anúncios sincroniza 30 dias em menos de 20 chamadas, em fatias que cabem em cada execução do cron da Hostinger, retomando de onde parou (D2).

### META-04 · Painel de Ads completo — `16h` · 40% · núcleo
> **Ação:** Nível de conta, recortes por idade/gênero/posicionamento, comparativo de período, filtro de conta funcionando, ROAS e CPA calculados certo, grade de criativos.
> **Arquivos:** TrafficController, AdMetricsRepository, views/trafego
> **Pronto quando:** ROAS bate com o Gerenciador de Anúncios no mesmo período.

### META-05 · Ações em campanha com trava e auditoria — `14h` · 35% · núcleo
> **Ação:** Regras de segurança em código (teto de variação de orçamento, respeita orçamento vitalício e CBO), simulação antes de executar, desfazer, resposta da API guardada. Fecha o PROD-02.
> **Arquivos:** AdsActionService, MetaAdsService, migration
> **Pronto quando:** IA propõe +80% de orçamento e o sistema bloqueia acima do teto; toda execução tem log.

### TRAF-01 · Alertas de anomalia de tráfego — `10h` · 10% · núcleo
> **Ação:** Automação traffic.anomaly: CPA do dia ≥ 2× a média de 7 dias, campanha pausada na Meta, sync falho, token a vencer; aviso ao gestor por in-app e WhatsApp.
> **Arquivos:** app/Automations, config/automations.php, HealthRepository
> **Pronto quando:** Teste com caso positivo e 'sem anomalia'; 1 aviso por campanha por dia.

### AI-02 · Linhas editoriais com IA — `18h` · 10% · núcleo
> **Pedido do dono na D5 (01/11).** **Ação:** gerar linhas editoriais por cliente com IA a partir dos resultados (desempenho dos posts, formatos e temas que performaram), do histórico de planos e do briefing; a equipe revisa, guarda e transforma em rascunhos de post no plano semanal.
> **Arquivos:** AiInsightService, ContentPlanService, OrganicMetricsRepository, migration, content/*
> **Pronto quando:** a partir dos dados de um cliente, a IA sugere linhas com a justificativa em números, e um clique cria os rascunhos no plano da semana. Sem dados suficientes, avisa em vez de inventar.

### META-06 · Orgânico corrigido — `14h` · 40% · núcleo
> **Ação:** Métricas atuais do Instagram (views, total_interactions; impressions saiu), Reels e Stories, insights de Page certos, erro visível em vez de zero.
> **Arquivos:** MetaOrganicService, OrganicSyncService, views/organico
> **Pronto quando:** Números batem com o app do Instagram; falha de API aparece como falha.

### META-07 · Publicar e agendar no Instagram e Facebook — `32h` · 0% · núcleo
> **Ação:** Post aprovado é publicado ou agendado direto da plataforma: imagem, carrossel, Reels, Stories e post de Page; status no item; retry; limite de 50 por 24h.
> **Arquivos:** novo MetaPublishService + Job, ContentPlanService, content/show.php · **Externo:** App Review + mídia em URL pública
> **Pronto quando:** Carrossel aprovado agendado para 18:00 é publicado na primeira execução do cron depois das 18:00 e o item vira 'publicado'; processamento de Reels acompanhado entre execuções (D2).

### PROD-07 · Planejou → publicou → performou — `10h` · 0% · núcleo
> **Ação:** Item do plano guarda o id da mídia publicada; tela e portal mostram data, link e desempenho de cada post. Inclui o CONT-PORTAL parte 2.
> **Arquivos:** migration, ContentPlanRepository, OrganicMetricsRepository, portal
> **Pronto quando:** Cliente vê no portal o alcance do post que aprovou.

### META-08 · Webhooks da Meta — `12h` · 0% · núcleo
> **Ação:** Endpoint com hub.challenge, assinatura X-Hub-Signature-256, fila de eventos e assinatura por campo (comentários, menções, leads).
> **Arquivos:** WebhookController, routes/web.php, migration · **Externo:** App em modo Live
> **Pronto quando:** Evento de teste do painel da Meta entra na fila com assinatura válida; assinatura falsa é recusada.

### META-12 · Relatório Meta para o cliente — `10h` · 55% · núcleo
> **Ação:** Portal e PDF com Ads e orgânico juntos e comparativo com o período anterior (UX-07 entra aqui).
> **Arquivos:** ReportController, ExecutiveReportRepository, views
> **Pronto quando:** Relatório do mês com seletor de período chega ao cliente pelo portal.

### META-13 · Testes da integração Meta — `16h` · 5% · núcleo
> **Ação:** Fake da Graph (handler do Guzzle) e testes de sync, paginação, erro, autorização negativa e publicação.
> **Arquivos:** tests/Unit, tests/Feature
> **Pronto quando:** Suíte cobre os caminhos de erro sem chamar a Meta de verdade.

### META-09 · Lead Ads — `18h` · 0%
> **Ação:** Formulários, recebimento por webhook leadgen, tabela de leads por cliente e encaminhamento por WhatsApp e e-mail.
> **Arquivos:** novo LeadController/Service, migration · **Externo:** App Review leads_retrieval
> **Pronto quando:** Lead novo chega ao WhatsApp do cliente em menos de 1 minuto.

### META-10 · Comentários e menções — `20h` · 0%
> **Ação:** Listar, responder, ocultar e excluir comentários de IG e Page a partir da plataforma, alimentado pelo webhook.
> **Arquivos:** novo controller/service, migration · **Externo:** App Review manage_comments
> **Pronto quando:** Social media responde um comentário sem abrir o Instagram.

### META-11 · Inbox de mensagens diretas — `24h` · 0%
> **Ação:** DMs do Instagram e Messenger com a janela de 24h da Meta.
> **Arquivos:** novo módulo · **Externo:** App Review manage_messages
> **Pronto quando:** DM recebida aparece na inbox e a resposta chega ao usuário.

---

## F5 — Operação redonda · 160 h · 19% feito

> Ficha do cliente, dashboard, galeria, frontend, admin. Polimento que o cliente pagante sente no dia a dia.

| ID | Entrega | Horas | % feito | Núcleo | Depende de |
|----|---------|------:|--------:|:------:|------------|
| PROD-03 | Ficha 360 do cliente | 18 | 25% | ● | SEC-11 |
| CONT-08 | Cadastro de cliente completo | 14 | 20% | ● | — |
| PROD-09 | Dashboard com indicadores de verdade | 16 | 35% | ● | TASK-06 |
| FE-04 | Todo fetch pelo api.js | 10 | 40% | ● | BUG-04 |
| FE-05 | Tokens, galeria única e acessibilidade | 14 | 50% | ● | — |
| DRIVE-04 | Galeria utilizável | 14 | 20% | ○ | FE-05 |
| CONT-06 | Escolher mídia da galeria no post (fase 2) | 12 | 0% | ○ | FE-05 |
| CONT-09 | Versões do criativo | 20 | 0% | ○ | TASK-06, Decisão D12 |
| ADM-02 | Painel da plataforma de verdade | 8 | 20% | ○ | SEC-15 |
| ARCH-02 | Rotas pt/en unificadas | 8 | 0% | ○ | Decisão D13 |
| AUTH-01 | Verificação em duas etapas | 10 | 0% | ○ | BUG-02, SEC-18 |
| UX-03 | Convite de usuário por e-mail | 6 | 0% | ○ | BUG-02 |
| ARCH-04 | Limites do plano num ponto só | 4 | 10% | ○ | — |
| FE-06 | Painel inteiro em pt, en e es | 40 | 10% | ● | — |

### PROD-03 · Ficha 360 do cliente — `18h` · 25% · núcleo
> **Ação:** Abas conteúdo, financeiro, tráfego, arquivos e tarefas usando as 4 consultas que hoje são feitas e descartadas; linha do tempo; checklist de onboarding.
> **Arquivos:** ClientController, clients/show.php
> **Pronto quando:** Tudo de um cliente numa tela, cada aba respeitando a permissão do módulo.

### CONT-08 · Cadastro de cliente completo — `14h` · 20% · núcleo
> **Ação:** Os 16 campos que o backend já aceita, contatos (tabela existe, tela não), validação com erro por campo, máscara de CNPJ e telefone, duplicata, formulário que não perde o que foi digitado.
> **Arquivos:** ClientService, clients/create.php, clients/edit.php
> **Pronto quando:** CNPJ inválido mostra erro no campo e mantém o resto preenchido.

### PROD-09 · Dashboard com indicadores de verdade — `16h` · 35% · núcleo
> **Ação:** Tendência contra o período anterior, posts publicados no mês, receita e inadimplência, saúde por cliente, carga do time, 2 gráficos e seletor de período.
> **Arquivos:** DashboardRepository, dashboard/index.php
> **Pronto quando:** Dono vê em 10s se o mês está melhor ou pior que o anterior.

### FE-04 · Todo fetch pelo api.js — `10h` · 40% · núcleo
> **Ação:** Eliminar os fetch crus e os catch vazios de content-editor.js, approvals.js e drive-manager.js; erro visível com tentar de novo; confirmações que dizem o impacto.
> **Arquivos:** public/js/*
> **Pronto quando:** Teste de arquitetura falha se surgir fetch fora do api.js.

### FE-05 · Tokens, galeria única e acessibilidade — `14h` · 50% · núcleo
> **Ação:** Zero hex solto nas views, JS inline do portal extraído, galerias do painel e do portal numa partial só (~820 linhas duplicadas), ações de arquivo alcançáveis no celular, modais com role=dialog.
> **Arquivos:** views de content, portal, clients, approvals
> **Pronto quando:** Teste de arquitetura sem hex em views; ações de arquivo funcionam no toque.

### DRIVE-04 · Galeria utilizável — `14h` · 20%
> **Ação:** Busca por nome, filtro por tipo e data, download em lote, indicador de espaço usado (a função existe e nunca é chamada).
> **Arquivos:** drive-manager.js, partial da galeria
> **Pronto quando:** Achar o vídeo de março do cliente em 2 cliques.

### CONT-06 · Escolher mídia da galeria no post (fase 2) — `12h` · 0%
> **Ação:** Seletor de mídia dentro do modal do post preenchendo capa, carrossel e vídeo na ordem, sem copiar link.
> **Arquivos:** content/show.php, content-editor.js
> **Pronto quando:** Carrossel completo montado sem colar URL.

### CONT-09 · Versões do criativo — `20h` · 0%
> **Ação:** v1, v2, v3 por post com histórico e comparação; reprovou, sobe a v2 sem perder o feedback anterior.
> **Arquivos:** migration, DriveFileRepository, content/show.php, portal/plan_show.php
> **Pronto quando:** Cliente vê o que mudou entre a v1 e a v2.

### ADM-02 · Painel da plataforma de verdade — `8h` · 20%
> **Ação:** SQL fora da view (hoje está no template), guarda explícita nos 8 métodos de planos, indicadores de receita mensal, tenants e planos.
> **Arquivos:** admin/index.php, Admin/*, novo PlatformDashboardRepository
> **Pronto quando:** Teste de arquitetura também varre views atrás de SQL.

### ARCH-02 · Rotas pt/en unificadas — `8h` · 0%
> **Ação:** Uma URL canônica com aliases e redirecionamento 301; corrigir os 30 handlers sem par e as 3 URLs da tela de perfis.
> **Arquivos:** routes/web.php, Router
> **Pronto quando:** Teste garante par pt/en para toda rota.

### AUTH-01 · Verificação em duas etapas — `10h` · 0%
> **Ação:** 2FA por aplicativo autenticador, com códigos de recuperação; obrigatório por agência se ela quiser.
> **Arquivos:** AuthService, UserRepository, views/auth, migration
> **Pronto quando:** Login com 2FA ligado exige o código; teste de feature.

### UX-03 · Convite de usuário por e-mail — `6h` · 0%
> **Ação:** Admin convida pelo e-mail; a pessoa define a própria senha pelo link.
> **Arquivos:** UserController, UserService, EmailService
> **Pronto quando:** Ninguém mais recebe senha por fora.

### ARCH-04 · Limites do plano num ponto só — `4h` · 10%
> **Ação:** Gate único de checkLimit (hoje falha aberto e está em 4 lugares) e comportamento definido quando o plano vence.
> **Arquivos:** BillingService, middlewares
> **Pronto quando:** Módulo novo herda o limite sem lembrar de chamar nada.

### FE-06 · Painel inteiro em pt, en e es — `40h` · 10% · núcleo
> **Reestimado em 01/11 (D14):** medido nas views: conteúdo, tráfego, tarefas, orgânico, automações, faturas, settings e outras somam ~9 mil linhas com zero `t()`. Portal, usuários e clientes já traduzem. **Regra a partir de agora:** toda tela nova nasce com `t()` nos 3 idiomas.
> **Ação:** Trocar textos fixos em português por t() nos 3 idiomas (se a decisão for vender em pt, en e es).
> **Arquivos:** views de financeiro, faturas, contratos, pagamentos, admin; lang/*
> **Pronto quando:** Agência em espanhol não vê texto em português.

---

## Decisões do dono (respondidas em 2026-11-01)

| # | Decisão | Resposta | Afeta |
|---|---------|----------|-------|
| D1 | App da Meta | **Um app da YVE para todas as agências (Tech Provider).** Exige App Review e verificação de negócio, que dependem do dono. | META-02, META-07, META-08 |
| D2 | Worker na VPS de Paris | **Não: manter só o cron da Hostinger** (dono pediu explicação do que seria). INFRA-04 suspenso; META-03, META-07 e TASK-04 adaptados para rodar em fatias, com precisão igual ao intervalo do cron. | INFRA-04, TASK-04, META-03, META-07 |
| D3 | Número de WhatsApp | **Um número por agência.** | Fase 2 |
| D4 | Status de tarefa | **Personalizáveis por agência, com 5 padrões semeados.** | TASK-01, TASK-03, TASK-05 |
| D5 | Esteira do post | **Roteiro, gravação, edição, pronto, agendado, publicado.** Observação do dono: gerar linhas editoriais com IA baseadas em resultados (vira AI-02). | TASK-06, AI-02 |
| D6 | Chat interno | **Atualização a cada 8s (cabe na Hostinger).** | CHAT-01, CHAT-02 |
| D7 | Automações | **Catálogo parametrizável.** | AUTO-02 |
| D8 | Grupos de WhatsApp | **Sim para conteúdo; financeiro nunca.** | WPP-04 |
| D9 | Mídia para a Meta | **Link temporário a partir do Drive.** | META-07 |
| D10 | Perfis | **Fixos do sistema (só leitura) + perfis próprios da agência.** | SEC-12 |
| D11 | Orçamento | **Teto de 30% ao dia, sempre com aprovação humana.** | META-05 |
| D12 | Versões do criativo | **Adiar até a esteira de produção rodar.** | CONT-09 |
| D13 | Rotas pt/en | **Unificar com redirecionamento 301.** | ARCH-02 |
| D14 | Idiomas | **Vender em pt, en e es.** FE-06 passa a núcleo e cresce para 40 h. | FE-06 |

## Fora deste ciclo

- SEC-10 CSP estrita: segue adiado (custo alto, script-src já é 'self').
- PROD-01 e PROD-01a (billing SaaS e PIX/boleto): manual por decisão de 23/07.
- AI-01 metering de IA e DRIVE-03 (drive.readonly): depois deste ciclo.
- INT-03 ClickUp: substituído pelo TASK-07 (desligar) e por um importador futuro se algum tenant pedir.
- UX-05 drag-and-drop no kanban: já existe no código; falta só salvar a ordem (entra no TASK-03).
- UX-07 seletor de período no relatório: entra no META-12.

## Sequenciamento

| Ordem | Fase | Observação |
|-------|------|------------|
| 1 | **F0 Blindagem** | Sem exceção antes de qualquer feature. Em paralelo, com o dono: verificação de negócio e App Review da Meta (D1). |
| 2 | **F1 Infra** | Sem worker na VPS (D2): pooler, fila robusta e logs. |
| 3 | **F2 Central de WhatsApp** | INT-01 primeiro: nada de WhatsApp é padronizado antes de validar o canal. |
| 4 | **F3 Tarefas e chat** | Depende de D4 (status) e D6 (chat). |
| 5 | **F4 Central Meta** | Publicação, webhooks e leads dependem do App Review aprovado. |
| 6 | **F5 Operação redonda** | Itens ○ podem ser intercalados quando uma fase travar em dependência externa. |

---

## Definição de pronto global (por item)

- [ ] Migrations reversíveis aplicam e revertem sem erro.
- [ ] Rota nova: auth + permissão + (se de cliente) `ClientAccessMiddleware` + CSRF em mutação — nos pares pt **e** en.
- [ ] Regra nova tem teste (permissão: positivo **e** negativo; **escopo de agência: negativo obrigatório**).
- [ ] `composer test` (com e sem banco) + `composer analyse` verdes; `composer audit` limpo.
- [ ] Ação sensível grava `activity_logs`; segredo novo cifrado.
- [ ] Saída de template com `e()`; `innerHTML` só com sanitização; `fetch` via `api.js`.
- [ ] Tela nova/alterada: tokens do design system, 4 estados, validada com `visual-validation`.
- [ ] Automação nova: entrada no `config/automations.php` **e** no [AUTOMACOES.md](AUTOMACOES.md).
- [ ] Item concluído: marcar ✅ **neste arquivo** com data e uma linha de como foi resolvido.
