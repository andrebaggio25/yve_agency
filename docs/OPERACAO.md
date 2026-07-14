# YVE Agency — Manual de Operação

> O que fazer para manter o sistema de pé, e o que fazer quando algo quebra.
> Criado no Marco B (2026-07-14). Complementa [CRON.md](CRON.md) (agendamentos)
> e [PLANO_MESTRE.md](PLANO_MESTRE.md) (roadmap).

---

## 1. Monitoramento (OBS-01)

### Endpoint de saúde

```
GET {APP_URL}/api/health                      → público: {"status": "ok|degraded|error"}
GET {APP_URL}/api/health?token={QUEUE_SECRET} → completo: checks detalhados
```

| status | HTTP | Significa |
|--------|------|-----------|
| `ok` | 200 | Tudo certo |
| `degraded` | 200 | **Serve, mas algo ficou por fazer** — cron parado, job falho ou sync congelado |
| `error` | 503 | Banco inacessível — o app não funciona |

**Configure um monitor externo** (UptimeRobot, Better Stack, cron-job.org — todos têm plano free) apontando para `/api/health` a cada 5 minutos. Ele dispara no 503.

`degraded` responde **200 de propósito**: o app está servindo os clientes. Isso não merece um alarme às 3h da manhã — merece um e-mail, que é o que o item abaixo faz.

### Alertas por e-mail

O `/queue/work` (que já roda pelo cron) verifica a cada execução e envia e-mail quando:
- um ou mais **jobs falharam definitivamente** na última hora (estouraram as tentativas — trabalho que ninguém fez);
- há **contas de anúncio/orgânico sem sincronizar há mais de 48h** (sync quebrado é silencioso: o cliente descobre no relatório errado).

**Para ativar:** defina a chave `alert_email` em `/admin/configuracoes`. Sem ela, o alerta só vai para o log de erro do PHP.

Throttle de **1 alerta por hora**, de propósito: alerta que repete a cada minuto vira ruído e é ignorado — o que dá no mesmo que não ter alerta.

### Onde ver o que foi enviado

`/automations/deliveries` (menu Automações → **Ver entregas**) mostra tudo que saiu por WhatsApp e e-mail nos últimos 30 dias: destinatário, canal, situação e **o motivo do erro** quando falhou. É a primeira tela a abrir quando alguém disser *"não recebi"*.

### Fila (INFRA-01)

Existe **uma** fila: a tabela `jobs`. Notificações, automações e ClickUp passam por ela — mesma reserva concorrente (`SKIP LOCKED`), mesmo backoff, mesmo alerta ao esgotar tentativas.
`notification_jobs` **não é mais fila**: é o registro de entrega que alimenta a tela acima.
Os dois endpoints de cron (`/queue/run` e `/queue/work`) processam a mesma fila — manter os dois configurados é inofensivo.

### O que checar quando o alerta chegar

1. `GET /api/health?token=…` → qual check está `ok: false`.
2. `cron.minutes_ago` alto → o cron da Hostinger parou. Verifique o painel de cron do hosting.
3. `queue.failed > 0` → veja `last_error` na tabela `jobs` (status `failed`).
4. `stale_syncs.count > 0` → provável token da Meta expirado; reconecte a conta em `/trafego/contas`.

---

## 2. Backup e retenção (DATA-01)

### O que o Supabase já faz

O Postgres do Supabase tem **backup automático diário** com retenção conforme o plano (7 dias no Pro; **o free não garante retenção — confirme o seu plano**). Isso cobre desastre de infraestrutura, **não** erro humano recente.

### O que você precisa fazer

| Quando | Ação |
|--------|------|
| **Antes de qualquer migration em produção** | Snapshot manual pelo painel do Supabase (Database → Backups). Toda migration que muda schema é irreversível na prática. |
| **Antes de um rollback** | Idem — e leia o aviso: rollback **apaga colunas e tabelas**. O painel agora exige digitar `REVERTER` (ADM-01). |
| **Mensal** | Confirme que o backup do Supabase está ativo e que você consegue restaurar. Backup que nunca foi testado não é backup. |

### Retenção de dados

- `activity_logs` cresce indefinidamente. Quando passar de alguns milhões de linhas, purgue registros com mais de **12 meses** (é trilha de auditoria; 12 meses cobre o uso real).
- `ad_daily_metrics` cresce por conta × dia. Só vira problema com dezenas de contas — monitorar.

---

## 3. Deploy (SSH — procedimento padrão)

O servidor é Hostinger compartilhada com **SSH habilitado**. O projeto inteiro vive dentro da pasta pública (o `.htaccess` da raiz redireciona tudo para `public/`).

### Antes de subir (na sua máquina)

1. Mexeu em CSS/JS? **`npm run build`** e commite `public/css/app.css` + `public/js/`.
   O servidor **não roda `npm`** — por isso os assets buildados são versionados.
2. `composer test` + `composer analyse` verdes.
3. `git push`.

### No servidor

```bash
ssh -p 65002 uXXXXXXXX@IP          # dados em hPanel → Avançado → Acesso SSH
cd ~/domains/agency.yvebeauty.com/public_html

git pull origin main

# --no-dev é OBRIGATÓRIO: sem ele você instala PHPStan/PHPUnit/CS-Fixer
# (~40 MB de ferramenta de desenvolvimento) num servidor de produção.
composer install --no-dev --optimize-autoloader

php vendor/bin/phinx migrate        # ou pelo painel: /admin/migrations
```

> **PHP:** o projeto exige **8.3+**. O `php` padrão do SSH pode ser mais antigo que o do site.
> Confira com `php -v`; se preciso, use o binário completo (`ls /usr/bin/php*`).

> **`.env`:** está no `.gitignore` — o `git pull` não o toca. Um `head -3 .env` depois do pull dá paz de espírito.

### Conferir que subiu de pé

```bash
curl -s "https://agency.yvebeauty.com/api/health?token=SEU_QUEUE_SECRET"
```
Espera-se `"status": "ok"` (ou `degraded`, se o cron ainda não rodou desde o deploy).

Depois, no navegador: **hard refresh** (Ctrl+Shift+R) — CSS e JS têm cache longo.

### Quando o deploy exige atenção extra

| Mudou | Faça |
|-------|------|
| `composer.lock` (dependência nova) | `composer install --no-dev` — **sempre**; foi o que quase mordeu com o dompdf |
| `database/migrations/` | rodar a migration (e **snapshot do banco antes**, se ela altera schema) |
| `resources/css/` ou `tailwind.config.js` | `npm run build` **local** e commitar `public/css/app.css` |
| `.env.example` | conferir se falta variável nova no `.env` de produção |

---

## 4. Validação de integrações (INT-01 / INT-03)

> **Regra: integração não exercitada = integração quebrada até prova em contrário.**
> WhatsApp (Evolution) e ClickUp têm código pronto e **nunca rodaram em produção**.
> Não anuncie como feature antes de rodar o roteiro abaixo e anexar o resultado aqui.

### WhatsApp (Evolution API) — INT-01

| # | Passo | Resultado esperado |
|---|-------|--------------------|
| 1 | Configure `evolution_api_key` e a URL base em `/admin/configuracoes` | Salvo (a chave fica cifrada) |
| 2 | `/configuracoes/whatsapp` → "Ativar" → leia o QR com o celular | Status vira "conectado" |
| 3 | Dispare uma automação com canal WhatsApp (ex.: lembrete de fatura com vencimento próximo) e rode `/queue/work` | Mensagem chega no celular do destinatário |
| 4 | **Provoque um erro:** desconecte a instância e rode a automação de novo | O job falha, registra `last_error`, e o **alerta por e-mail** dispara (OBS-01) |
| 5 | Envie uma mensagem **para** o número conectado | O webhook `/webhook/evolution/{token}` recebe (confira em `activity_logs`) |

**Decisão pendente:** quem hospeda a instância Evolution? É um serviço auto-hospedado — se ele cai, o WhatsApp para e **ninguém é avisado** (a menos que o alerta de job falho pegue). Documente aqui o endereço, quem administra e como reiniciar.

**Proteção já ativa (INT-02):** os envios de WhatsApp saem **espaçados em 8s** por agência (~7 msg/min). Uma automação para 20 clientes leva ~2,5 min em vez de disparar tudo de uma vez — que é o padrão que faz o WhatsApp banir o número.

### ClickUp — INT-03

| # | Passo | Resultado esperado |
|---|-------|--------------------|
| 1 | `/integrations/clickup` → cole o token de um workspace real | Conectado (token cifrado) |
| 2 | Crie uma tarefa no YVE | Aparece no ClickUp |
| 3 | Mude o status no ClickUp | O webhook chega e o status muda no YVE |
| 4 | Mude o status nos **dois lados** quase ao mesmo tempo | **Observe qual vence.** Não há resolução de conflito — se o resultado for surpreendente, é bug a registrar |
| 5 | Revogue o token no ClickUp e mexa numa tarefa | Falha tratada, sem erro cru na tela |

**Decisão de produto pendente:** as tarefas nativas e o ClickUp competem. Investir nos dois é desperdício — decida qual é o caminho e registre no PLANO_MESTRE.

---

## 5. Segredos

| Segredo | Onde vive | Rotacionar se |
|---------|-----------|---------------|
| `APP_KEY` | `.env` | **Nunca** (é a chave que decifra os tokens de integração — trocar quebra todas) |
| `QUEUE_SECRET` | `.env` | Suspeita de vazamento (protege os endpoints de cron e o `/health` detalhado) |
| Tokens de integração (Meta, Drive, ClickUp) | Banco, **cifrados** com `APP_KEY` | Ao desconectar/reconectar a conta |
| Chaves globais (SMTP, IA, Evolution) | `platform_settings`, **cifradas** | Vazamento |

**Senha padrão do seeder:** se o platform admin ainda usa `platform123!`, **troque agora** — está no repositório público.
