# Configuração do Cron — Fila de Notificações

## Como funciona

O sistema usa uma fila em banco (`notification_jobs`) para enviar WhatsApp e e-mails de forma assíncrona. Um endpoint HTTP processa a fila e deve ser chamado periodicamente pelo seu cron externo.

## Endpoint

```
GET {APP_URL}/queue/run?token={QUEUE_SECRET}
```

O token está no arquivo `.env` como `QUEUE_SECRET`. A URL completa é exibida na própria tela de configuração do WhatsApp (`/configuracoes/whatsapp`).

## Resposta esperada

```json
{
  "ok": true,
  "processed": 3
}
```

Em caso de token inválido: HTTP 403.

## Frequência recomendada

**A cada 1 minuto** é o ideal para notificações em tempo hábil. Mínimo aceitável: a cada 5 minutos.

## Exemplos de configuração

### Crontab tradicional (Linux/Mac)

```cron
* * * * * curl -s "{APP_URL}/queue/run?token={QUEUE_SECRET}" > /dev/null 2>&1
```

### EasyCron / cron-job.org / Make (Integromat)

- URL: `{APP_URL}/queue/run?token={QUEUE_SECRET}`
- Método: GET
- Intervalo: 1 minuto

### GitHub Actions (schedule)

```yaml
on:
  schedule:
    - cron: '* * * * *'  # a cada minuto (GitHub executa no mínimo a cada 5min)
jobs:
  queue:
    runs-on: ubuntu-latest
    steps:
      - run: curl -s "${{ secrets.APP_URL }}/queue/run?token=${{ secrets.QUEUE_SECRET }}"
```

### Coolify / Railway / Render (cron jobs internos)

Configure um job com o comando:
```sh
curl -s "${APP_URL}/queue/run?token=${QUEUE_SECRET}"
```

## Variáveis de ambiente necessárias

| Variável | Descrição |
|---|---|
| `APP_URL` | URL pública da aplicação (ex: `https://app.yveagency.com`) |
| `QUEUE_SECRET` | Token secreto, gerado no `.env` — não commitar |
| `EVOLUTION_BASE_URL` | URL da instância Evolution API |
| `EVOLUTION_API_KEY` | Chave da Evolution API |
| `EVOLUTION_INSTANCE` | Nome da instância padrão (fallback se não configurado no banco) |
| `MAIL_*` | Credenciais PHPMailer (SMTP host, port, user, pass, from) |

## Segurança

- O endpoint `/queue/run` está **fora do grupo de autenticação** (não requer login).
- A validação é feita com `hash_equals()` (proteção contra timing attacks).
- Nunca expor o `QUEUE_SECRET` em logs, repositório ou URLs públicas.
- Rotacionar o secret via `.env` se houver suspeita de vazamento.
