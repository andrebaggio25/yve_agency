# YVE Agency — Análise de Produto (SWOT por módulo)

> Análise de produto sênior · SWOT + nota 0–10 + plano de ação por módulo
> Data: 2026-07-14 · Ciclo: **2026-07 (ciclo 2)** · Método: skill `yve-analise-produto`
> Complementa a fotografia técnica em [ANALISE_SISTEMA.md](ANALISE_SISTEMA.md). O roteiro consolidado está em [PLANO_MESTRE.md](PLANO_MESTRE.md) — **a verdade absoluta**.

**Filosofia da nota:** 10 = pronto para vender e escalar sem ressalva; 7 = MVP fechado, funciona no dia a dia, dívidas conhecidas; 5 = funciona mas com lacuna que um cliente pagante sentiria; <5 = incompleto ou arriscado. Primeiro fechamos o MVP (corrigir erros/inconsistências), depois evoluímos.

---

## 0. Estado dos gates (medido em 2026-07-14)

```
PHPUnit:         77 testes, 140 asserts — 100% verde
PHPStan nível 6: 0 erros (PHPStan 2.2.5)
composer audit:  0 advisories
Marcos 0, 1 e 2 (parcial) do plano anterior: CONCLUÍDOS
```

O sistema está **tecnicamente apto ao go-live pago** pelo critério do plano anterior. Esta análise muda o foco: de "não vazar dados" para "parecer e funcionar como produto validado".

---

## 1. Quadro de notas (resumo executivo)

| # | Módulo | Nota | Situação em uma linha |
|---|--------|------|----------------------|
| 1 | Core / Arquitetura | **9.0** | Micro-framework limpo, camadas respeitadas, gates verdes |
| 2 | Auth & RBAC | **8.5** | Argon2id, 83 permissões, testes negativos; falta 2FA |
| 3 | Clientes | **8.0** | CRUD robusto, acesso por usuário, pasta Drive por cliente |
| 4 | Usuários & Perfis | **8.0** | RBAC completo com UI; limites de plano aplicados |
| 5 | Segurança (transversal) | **8.0** | Hardening feito; pendem CSRF do portal e CSP estrita |
| 6 | Conteúdo & Aprovações | **7.5** | Fluxo completo e validado por clientes reais; view de 1.183 linhas |
| 7 | Portal do Cliente | **7.5** | Diferencial do produto; controller de 690 linhas, CSRF pendente |
| 8 | Financeiro | **7.5** | Contratos+faturas+pagamentos multi-moeda; sem gateway de cobrança |
| 9 | Automações | **7.5** | 11 regras, matriz por cliente, dedupe; duas filas paralelas |
| 10 | Admin da Plataforma | **7.5** | Tenants, planos, migrations pelo painel; billing manual |
| 11 | Envio de Conteúdos (Drive) | **7.0** | Upload/galeria/lixeira bem-feitos; **teto de 256MB é a dor nº 1** |
| 12 | Tráfego Pago (Meta) | **7.0** | OAuth + sync + métricas; sync manual/cron, sem alertas |
| 13 | Notificações | **7.0** | In-app + bell + e-mail/WhatsApp via automação; duas filas |
| 14 | Relatório Executivo | **7.0** | Consolidado por cliente; sem exportação PDF real |
| 15 | Integração ClickUp | **7.0** | Bidirecional com HMAC; nunca usada em produção |
| 16 | Infra (Queue/Cron) | **7.0** | Fila correta (SKIP LOCKED); depende de cron HTTP |
| 17 | Testes & Qualidade | **7.0** | 77 testes unitários; zero ponta a ponta |
| 18 | Ações em Campanha | **6.5** | Fluxo de aprovação existe; guardrails de IA não amarrados |
| 19 | Orgânico (Instagram) | **6.5** | Sync + métricas; leitura apenas, pouca análise |
| 20 | Tarefas (Kanban) | **6.5** | CRUD + SLA + comentários; sem drag-and-drop, sem recorrência |
| 21 | WhatsApp (Evolution) | **6.5** | Conexão QR + envio; nunca usado, chave global única |
| 22 | Dashboard | **6.0** | Funciona, mas raso — e tem SQL no controller (viola invariante) |
| 23 | IA & Insights | **6.0** | Gera insight e recomendação; não fecha o ciclo com ações |
| 24 | Performance (transversal) | **6.0** | Backend ok; frontend paga imposto de CDN a cada load |
| 25 | Assinatura / Billing SaaS | **5.5** | Limites por plano ok; **não existe cobrança real** |
| 26 | Frontend (transversal) | **5.5** | Consistente no dark theme, mas: Tailwind CDN, JS inline, tokens duplicados em 4 layouts |

**Média ponderada do produto: ~7.1** — MVP funcional com fundação acima da média; as três âncoras que puxam para baixo são upload (256MB), frontend sem build/tokens e billing sem gateway.

---

## 2. Análise por módulo

Formato: **SWOT compacto** (Forças / Fraquezas / Oportunidades / Ameaças) + plano de ação. IDs referenciam o [PLANO_MESTRE.md](PLANO_MESTRE.md).

### 2.1 Core / Arquitetura — 9.0

- **S:** Router+Pipeline+Container+Repository limpos, `strict_types`, PSR-12; separação Controller→Service→Repository respeitada em ~100% dos módulos; escopo `agency_id` com fail-closed testado.
- **W:** rotas pt+en duplicadas (~200 rotas, manutenção 2×); `insert()` com `lastInsertId()` frágil no PG (parte já usa `RETURNING id`).
- **O:** mapa de aliases de idioma geraria as rotas en automaticamente (corta o arquivo pela metade).
- **T:** cada rota nova esquecida no par pt/en vira link quebrado silencioso.
- **Ação:** ARCH-02 (aliases de rota) · INFRA-03 (`RETURNING id` padrão).

### 2.2 Auth & RBAC — 8.5

- **S:** Argon2id, regeneração de sessão, cookies endurecidos, strict mode; 83 permissões canônicas; testes positivos e negativos de middleware; reset de senha com token.
- **W:** sem 2FA; sem bloqueio de conta após N falhas (só rate limit por IP); sem log de sessões ativas.
- **O:** 2FA TOTP é diferencial barato para vender a agências maiores.
- **T:** credencial vazada de um dono de agência expõe todos os clientes daquela agência.
- **Ação:** AUTH-01 (2FA TOTP, pós-MVP) · AUTH-02 (auditoria de login em `activity_logs` — verificar cobertura).

### 2.3 Clientes — 8.0

- **S:** CRUD completo, busca/filtros corrigidos (commit 8c95a7f), acesso granular por usuário (`client_user_access`), pasta Drive por cliente, portal por token com toggle/regeneração.
- **W:** sem campo de "saúde do cliente" (última entrega, próxima fatura, status geral consolidado na listagem); exclusão CASCADE apaga histórico financeiro junto (correto tecnicamente, mas sem aviso claro do impacto).
- **O:** tela do cliente virar "hub 360°" (conteúdo + financeiro + tráfego + arquivos numa aba só) — os dados já existem.
- **T:** —
- **Ação:** PROD-03 (hub 360° do cliente) · UX-02 (modal de exclusão listar o que será apagado).

### 2.4 Usuários & Perfis — 8.0

- **S:** RBAC com UI de perfis, limite de usuários por plano aplicado no controller, admin de plataforma separado.
- **W:** convite por e-mail? Hoje criação direta com senha — fluxo de convite com link é o padrão de mercado.
- **O:** perfis-modelo prontos (Social Media, Financeiro, Gestor de Tráfego) no seeder aceleram onboarding.
- **Ação:** UX-03 (fluxo de convite por e-mail, pós-MVP).

### 2.5 Conteúdo & Aprovações — 7.5

- **S:** fluxo ponta a ponta real (plano → itens → enviar → cliente aprova/pede revisão → tarefas automáticas); preview no formato do Instagram com proporções corretas (commit 32eb0d0 — feedback de clientes reais incorporado); aprovação automática do plano quando todos os itens aprovados.
- **W:** `content/show.php` tem **1.183 linhas** com JS inline — qualquer mudança é arqueologia; sem calendário visual de publicação; sem duplicação de plano/item.
- **O:** visão calendário (mês) dos itens é o que social media espera de ferramenta paga; duplicar plano do mês anterior economiza o trabalho mais repetitivo do usuário.
- **T:** a view gigante é onde nascem os próximos bugs de UI (histórico recente confirma).
- **Ação:** FE-02 (extrair JS de `content/show.php` — prioridade nº 1 do frontend) · PROD-04 (calendário) · PROD-05 (duplicar plano).

### 2.6 Portal do Cliente — 7.5

- **S:** é o **diferencial competitivo** — cliente aprova sem criar conta; dashboard com tráfego/orgânico; faturas e contratos visíveis; i18n pelo idioma do cliente; capability-token com regeneração.
- **W:** `PortalController` com 690 linhas mistura 4 domínios; endpoints de mutação do portal ainda sem CSRF (protegidos só pelo token da URL); token na URL aparece em histórico do navegador/logs de proxy.
- **O:** branding por agência (logo/cor) no portal — white-label básico vende plano superior.
- **T:** link do portal encaminhado pelo cliente a terceiros dá acesso total (aprovar, enviar arquivo, ver fatura). Mitigável com PIN opcional ou expiração.
- **Ação:** SEC-08 (CSRF/double-submit no portal) · ARCH-03 (extrair `PortalDriveController`) · PROD-06 (white-label portal) · SEC-09 (PIN opcional do portal, decisão de produto).

### 2.7 Envio de Conteúdos / Google Drive — 7.0

- **S:** arquitetura correta e incomum: escopo `drive.file`, arquivos privados servidos por proxy autenticado com Range, lixeira+restore, reconciliação Drive→sistema (botão + cron), galeria espelho na agência.
- **W:** **UP-01 — o teto de 256MB.** Diagnóstico completo: o código relay envia o multipart pro PHP e daí pro Drive. O `.user.ini` pede 1024M, mas a Hostinger compartilhada **trava `upload_max_filesize`/`post_max_size` em 256M** no nível do servidor — `PortalController::maxUploadBytes()` lê o valor efetivo e a UI bloqueia em 256MB. **Não é limite da API do Google** (upload resumável aceita até 5TB) **nem exige migrar de hosting**: o serviço já implementa `initiateResumable()` (o comentário do código até descreve o browser enviando direto pra session URI), mas **o JS nunca usa esse caminho** — todo upload passa pelo relay.
- **O:** upload direto browser→Drive (iniciar sessão resumável com header `Origin` = APP_URL; o JS faz PUT em chunks de 8–32MB direto na session URI, com progresso e retomada). O PHP sai do caminho dos bytes → limite da Hostinger irrelevante, suporta vídeo de qualquer tamanho, e ainda libera o servidor. Relay atual vira fallback para arquivos pequenos.
- **T:** vídeos de cliente são o coração do fluxo de conteúdo — cada upload que falha em 256MB corrói a confiança no produto.
- **Ação:** **UP-01 (blocking de MVP)** · DRIVE-03 (fase 2 do sync — adição manual, exige escopo `drive.readonly` + verificação Google, decisão de produto).

### 2.8 Financeiro (Contratos / Faturas / Pagamentos / Relatórios) — 7.5

- **S:** modelo de dados correto (DECIMAL, multi-moeda com taxa e valor-base), contratos→faturas→pagamentos encadeados, fatura recorrente + lembrete + vencida automáticos, PDF por view de impressão, envio por e-mail.
- **W:** sem integração com gateway/PIX — a agência registra pagamento na mão; "PDF" é print view (depende do usuário imprimir para PDF); sem conciliação.
- **O:** link de pagamento PIX/boleto na fatura (Mercado Pago/Asaas) fecha o ciclo e é argumento de venda forte no Brasil; recibo automático pós-pagamento.
- **T:** planilha continua sendo o concorrente — sem cobrança integrada, o financeiro do sistema é só espelho.
- **Ação:** PROD-01a (gateway de recebimento para faturas de clientes — avaliar Asaas/Mercado Pago) · UX-04 (gerar PDF de verdade — dompdf — para fatura/contrato).

### 2.9 Tráfego Pago (Meta) — 7.0

- **S:** OAuth completo, tokens cifrados, sync de campanhas/adsets/ads/métricas, tratamento de token expirado, limite de contas por plano.
- **W:** métricas dependem de sync manual ou cron; sem comparativo de período na UI; sem alertas (CPA estourou, campanha pausou).
- **O:** alerta proativo via automação já existente (motor pronto) — "CPA subiu 40% em 24h" é valor imediato percebido.
- **T:** Meta muda API com frequência; sem monitorar erros de sync, contas quebram silenciosamente.
- **Ação:** TRAF-01 (alertas de anomalia via automations) · OBS-01 (alertar sync falho).

### 2.10 Ações em Campanha — 6.5

- **S:** fluxo criar→aprovar→executar com trilha; execução chama a Meta de verdade.
- **W:** desconectado da IA (recomendações não geram ações pré-preenchidas); `ai_safety_rules` existem no schema mas **não são verificadas em código** antes de executar.
- **O:** amarrar recomendação→ação com guardrails é a Fase 7–8 do roadmap original e o "wow" do produto.
- **T:** executar mudança em campanha de cliente sem guardrail codificado é risco reputacional.
- **Ação:** PROD-02 (recomendação IA → ação com guardrails verificados em código).

### 2.11 Orgânico (Instagram) — 6.5

- **S:** conexão por conta, sync de métricas/posts, visão por conta.
- **W:** leitura passiva — sem análise (melhor horário, formato que performa), sem relação com os planos de conteúdo publicados.
- **O:** cruzar item aprovado no plano ↔ post publicado ↔ métricas fecha o ciclo "planejou→postou→performou" que nenhuma planilha faz.
- **Ação:** PROD-07 (vincular post orgânico ao item do plano, pós-MVP).

### 2.12 IA & Insights — 6.0

- **S:** geração multi-provedor (OpenAI/Anthropic) com fallback; recomendações persistidas; markdown sanitizado (DOMPurify).
- **W:** chave de API é global da plataforma (custo do dono do SaaS, sem medição por tenant); insight é texto solto — não vira ação nem tarefa; sem histórico de qualidade (o insight ajudou?).
- **O:** medir uso por agência (tokens/custo) para precificar; encadear insight→ação sugerida→execução com aprovação (ver 2.10).
- **T:** custo de IA cresce linear com tenants sem contabilização por plano.
- **Ação:** AI-01 (metering de uso de IA por agência) · PROD-02 (fechar ciclo com ações).

### 2.13 Tarefas (Kanban) — 6.5

- **S:** CRUD + status + SLA com automação de atraso + comentários internos + criação automática pós-aprovação de plano.
- **W:** kanban sem drag-and-drop (muda status por botão); sem recorrência; sem responsável múltiplo; sem filtro salvo.
- **O:** ClickUp já integrado — decidir se tarefas nativas evoluem ou se o ClickUp é o caminho "pro" (não investir nos dois).
- **Ação:** UX-05 (drag-and-drop no kanban) · decisão de produto: tarefas nativas vs ClickUp-first.

### 2.14 Automações — 7.5

- **S:** motor genérico com 11 regras reais (lembrete de fatura, escalonamento de aprovação, SLA, digest, onboarding, relatório mensal), matriz liga/desliga por cliente, dedupe idempotente, backoff na fila.
- **W:** duas filas paralelas (`jobs` + `notification_jobs`); sem visão "o que foi enviado pra quem" (log de entregas por canal).
- **O:** automações são o argumento de retenção do SaaS — expor o histórico de execução na UI aumenta valor percebido.
- **Ação:** INFRA-01 (unificar filas) · OBS-02 (timeline de execuções de automação na UI).

### 2.15 WhatsApp (Evolution) — 6.5

- **S:** conexão por QR, instância por agência, webhook validado, envio nos canais das automações.
- **W:** **nunca usado em produção** — precisa de rodada de validação real; Evolution API é dependência auto-hospedada (quem opera? o dono do SaaS); chave global única.
- **O:** confirmar entrega/leitura no log de automação; templates de mensagem por agência.
- **T:** WhatsApp banindo número da agência por spam — precisa de rate limit de envio e opt-out.
- **Ação:** INT-01 (rodada de validação ponta a ponta da Evolution em staging + doc de operação) · INT-02 (rate limit de envio por número).

### 2.16 ClickUp — 7.0

- **S:** sync bidirecional, webhook com HMAC, token cifrado por agência.
- **W:** nunca usado em produção; mapeamento de status fixo?; sem UI de conflito (mudou nos dois lados).
- **Ação:** INT-03 (rodada de validação ponta a ponta do ClickUp com workspace real).

### 2.17 Notificações — 7.0

- **S:** bell in-app com contador, marcar lidas, `action_url` corrigido (BUG-01), canais e-mail/WhatsApp via automação.
- **W:** duas filas (de novo); sem preferências por usuário (quero e-mail mas não in-app).
- **Ação:** INFRA-01 · UX-06 (preferências de notificação por usuário, pós-MVP).

### 2.18 Dashboard — 6.0

- **S:** carrega rápido, mostra planos recentes + resumo financeiro condicionado a permissão.
- **W:** **SQL direto no controller** (`DashboardController` monta PDO na mão — única violação da invariante nº 1 encontrada); métricas rasas (contadores); não é acionável (o que preciso fazer hoje?).
- **O:** dashboard orientado a ação: aprovações paradas, faturas vencendo, tarefas atrasadas, sync quebrado — tudo já existe no banco.
- **Ação:** ARCH-01 (mover SQL para repository) · PROD-08 (dashboard acionável "meu dia").

### 2.19 Relatório Executivo — 7.0

- **S:** consolida conteúdo+financeiro+tráfego+orgânico por cliente; base do relatório mensal automático.
- **W:** exportação é print view; sem período customizável na UI.
- **Ação:** UX-04 (PDF real) · UX-07 (seletor de período).

### 2.20 Admin da Plataforma — 7.5

- **S:** tenants, usuários globais, planos/assinaturas, config global cifrada (SEC-05), painel de migrations com CSRF+middleware (resolve a dor real de rodar schema sem CLI na Hostinger).
- **W:** assinaturas administradas na mão (sem gateway — ver 2.21); painel de migrations sem backup automático pré-run (um rollback errado em produção é irreversível).
- **Ação:** ADM-01 (aviso forte + dump lógico antes de migrate/rollback pelo painel) · PROD-01 (billing SaaS).

### 2.21 Assinatura / Billing SaaS — 5.5

- **S:** planos com limites (users/clients/meta/organic) aplicados no backend; tela de uso para o tenant.
- **W:** **não existe cobrança** — sem gateway, sem trial, sem dunning; limites checados controller a controller (fácil esquecer num módulo novo).
- **O:** Stripe/Mercado Pago assinaturas + trial de 14 dias = pré-requisito de escala comercial.
- **T:** vender no manual (Pix + planilha) funciona pra 5 agências, não pra 50.
- **Ação:** PROD-01 (gateway de assinatura + trial + dunning) · ARCH-04 (centralizar `checkLimit` em middleware/gate único).

### 2.22 Infra (Queue/Cron/Deploy) — 7.0

- **S:** fila com `FOR UPDATE SKIP LOCKED` + backoff; endpoints cron com `QUEUE_SECRET`/`hash_equals`; scheduler ok.
- **W:** latência atada ao cron HTTP da Hostinger; PDO persistente + pooler do Supabase não medido; sem `/health`.
- **O:** você tem uma VPS — mover **o worker** (não o site) pra ela já resolve latência de fila sem migração total.
- **Ação:** INFRA-01/02 · OBS-01 (`/health` + alerta de job falho).

### 2.23 Testes & Qualidade — 7.0

- **S:** 77 testes verdes, cobertura de autorização/escopo (o que mais importa aqui), PHPStan nível 6 zerado com PHPStan 2.x, audit limpo.
- **W:** zero teste ponta a ponta HTTP (migrations PG-only travam banco de teste); zero teste de JS (todo inline); fluxos críticos (aprovação do portal, upload) sem rede de segurança automatizada.
- **Ação:** QA-03 (banco de teste PG + 5 testes HTTP dos fluxos críticos) · FE-02 destrava testabilidade do JS.

### 2.24 Frontend (transversal) — 5.5

- **S:** dark theme consistente e com identidade (violeta #8b5cf6), Alpine bem usado nos fluxos ricos, i18n real em 3 idiomas, correções orientadas a feedback de cliente real.
- **W (em ordem de dor):**
  1. **Tailwind via CDN em produção** nos 4 layouts — recompila no browser a cada page load, sem purge, e trava a CSP em `unsafe-inline`/`unsafe-eval` (PERF-01).
  2. **Tokens duplicados**: cada layout repete `tailwind.config` inline + blocos `<style>` próprios — mudar a cor da marca hoje é editar 4 arquivos (FE-01).
  3. **JS inline gigante** (`content/show.php` 1.183 l., `portal/files.php` 566 l.) sem reuso nem teste (FE-02).
  4. **`fetch` sem padrão** — vários pontos sem `response.ok`, erro silencioso, sem estado de loading uniforme (FE-03).
  5. Alpine sem pin de versão em `app`/`admin` (`@3.x.x`) — atualização do CDN pode quebrar produção num dia qualquer (parte de PERF-01).
- **O:** um passo de build (Tailwind CLI) + um `design-tokens.css`/preset único + módulos JS em `public/js/` transformam "template admin" em "produto": CSP estrita, load 2–3× mais rápido, marca trocável num arquivo (base para white-label PROD-06).
- **T:** primeira impressão em demo de venda é o frontend; flash de estilo do CDN e dropdown quebrado custam negócio.
- **Ação:** **FE-01 + PERF-01 juntos (um sprint)** · FE-02 · FE-03. Guia permanente na skill `yve-frontend`.

### 2.25 Performance (transversal) — 6.0

- **S:** prepared statements, índices corretos, paginação real, defer nos scripts, dns-prefetch.
- **W:** Tailwind CDN é o maior custo de load hoje (compila no cliente); N+1 no portal (loop de contas); OFFSET em tabelas que vão crescer (métricas de ads).
- **Ação:** PERF-01 (resolve ~70% do problema percebido) · PERF-03 (agregar resumo do portal em 1 query quando houver volume).

### 2.26 Segurança (transversal) — 8.0

- **S:** Marcos 0 e 1 concluídos e verificados: erros não vazam, rate limit não confia em header, Guzzle atualizado, CSP+HSTS, credenciais globais cifradas, FKs completas, posse de entidade validada, testes de autorização.
- **W:** CSRF do portal pendente (SEC-08); CSP ainda permissiva (`unsafe-inline`/`eval`, destravada por PERF-01); SRI ausente nos CDNs; sem 2FA.
- **T:** o modelo de token-na-URL do portal é design aceito, mas cada nova rota de mutação no portal precisa lembrar disso (checklist da skill `yve-seguranca`).
- **Ação:** SEC-08 · PERF-01→SEC-10 (CSP estrita com nonce) · AUTH-01 (2FA, pós-MVP).

---

## 3. Integrações — parecer consolidado

| Integração | Parecer | Pendência para "confiável em produção" |
|-----------|---------|----------------------------------------|
| **Google Drive** | Melhor integração do sistema (privacidade por proxy, reconciliação) | UP-01 (upload direto resumável) — o resto está pronto |
| **Meta Ads** | Sólida (OAuth, cifra, expiração tratada) | OBS-01: alertar quando sync de uma conta falhar repetidamente |
| **Instagram Orgânico** | Funcional | Mesma observabilidade de sync |
| **Evolution/WhatsApp** | Código pronto, **nunca exercitada de verdade** | INT-01: validação ponta a ponta em staging + doc de operação da instância |
| **ClickUp** | Código pronto (bidirecional+HMAC), nunca usada | INT-03: validação com workspace real; decidir papel vs tarefas nativas |
| **OpenAI/Anthropic** | Funcional com fallback | AI-01: metering por tenant antes de escalar |
| **SMTP** | Funcional, templates i18n | Log de entrega visível (OBS-02) |

Regra geral: **integração não exercitada = integração quebrada até prova em contrário.** Antes de anunciar WhatsApp/ClickUp como feature, rodar o roteiro de validação (agora padronizado na skill `yve-analise-produto`).

---

## 4. Upload de 256MB — diagnóstico definitivo (UP-01)

**Onde NÃO está o limite:** Google Drive API (resumável, até 5TB) · seu código de streaming (usa `fopen`+stream, não carrega na memória) · `.user.ini` (pede 1024M corretamente).

**Onde ESTÁ:** dois pontos somados —
1. **Hostinger compartilhada trava `upload_max_filesize`/`post_max_size` em 256M** no servidor, acima do que o `.user.ini` pede. `PortalController::maxUploadBytes()` lê o valor efetivo (256M) e a UI bloqueia arquivos maiores — comportamento correto do código refletindo o teto do hosting.
2. **O frontend só usa o caminho relay** (browser→PHP→Drive). O método `initiateResumable()` que permitiria browser→Drive direto existe no backend, mas o JS de `portal/files.php` nunca o chama — todo byte passa pelo PHP e herda o teto.

**Correção (sem migrar hosting):** upload direto resumável — iniciar a sessão no servidor enviando header `Origin: <APP_URL>` (o Google então aceita PUTs CORS do browser), expor endpoint que devolve a session URI, e o JS enviar o arquivo em chunks (8–32MB, múltiplos de 256KB) com `Content-Range`, barra de progresso e retomada. PHP fica fora do caminho dos bytes → limite da Hostinger deixa de existir para upload. Relay atual permanece como fallback (< 200MB).

**Sobre migrar para a VPS:** não é necessário para o upload. Vale considerar VPS por outros motivos (worker de fila residente, latência), começando por mover **só o worker** (INFRA-01) sem migrar o site.

---

## 5. Priorização mestre (visão de produto)

1. **Fechar o MVP (o que um cliente pagante sente):** UP-01 (upload) → FE-01+PERF-01 (build+tokens) → FE-02/03 (JS) → SEC-08 → ARCH-01 → QA-03.
2. **Confiabilidade do que já existe:** INT-01/03 (validar WhatsApp/ClickUp) → OBS-01/02 → INFRA-01/02/03.
3. **Escalar comercialmente:** PROD-01 (billing real) → PROD-08 (dashboard acionável) → PROD-03 (hub do cliente) → PROD-06 (white-label).
4. **Diferenciar:** PROD-02 (IA→ação com guardrails) → PROD-04 (calendário) → PROD-07 (orgânico↔plano).

Sequência detalhada, esforços e critérios de pronto: [PLANO_MESTRE.md](PLANO_MESTRE.md).
