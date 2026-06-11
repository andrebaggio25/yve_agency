# Integração Evolution API — Conexão, Criação de Instâncias e QR Code

Este documento descreve **exatamente** como o YVE CRM integra com a [Evolution API v2](https://doc.evolution-api.com/) para WhatsApp (Baileys): credenciais globais, criação automática de instâncias por tenant, exibição do QR Code, verificação de conexão e configuração de webhook.

Use este guia como referência para replicar o mesmo fluxo em outro projeto.

---

## Índice

1. [Visão geral da arquitetura](#1-visão-geral-da-arquitetura)
2. [Pré-requisitos e configuração global](#2-pré-requisitos-e-configuração-global)
3. [Cliente HTTP (como toda requisição é feita)](#3-cliente-http-como-toda-requisição-é-feita)
4. [Criação automática de instância](#4-criação-automática-de-instância)
5. [QR Code e conexão do WhatsApp](#5-qr-code-e-conexão-do-whatsapp)
6. [Verificação de status da conexão](#6-verificação-de-status-da-conexão)
7. [Webhook (recebimento de eventos)](#7-webhook-recebimento-de-eventos)
8. [Endpoints internos do CRM (API REST)](#8-endpoints-internos-do-crm-api-rest)
9. [Modelo de dados (banco)](#9-modelo-de-dados-banco)
10. [Fluxo no frontend](#10-fluxo-no-frontend)
11. [Exemplos cURL prontos para copiar](#11-exemplos-curl-prontos-para-copiar)
12. [Armadilhas conhecidas e boas práticas](#12-armadilhas-conhecidas-e-boas-práticas)
13. [Arquivos de referência no projeto](#13-arquivos-de-referência-no-projeto)

---

## 1. Visão geral da arquitetura

```
┌─────────────────┐     credenciais globais      ┌──────────────────┐
│  Superadmin     │ ─────────────────────────────► │  Evolution API   │
│  (URL + API Key)│                                │  (servidor)      │
└─────────────────┘                                └────────▲─────────┘
                                                              │
┌─────────────────┐   POST /instance/create                   │
│  Tenant         │ ──────────────────────────────────────────┤
│  (1 instância)  │   GET  /instance/connect  (QR)            │
└────────┬────────┘   GET  /instance/connectionState          │
         │            POST /webhook/set                        │
         │ webhook POST /webhook/evolution/{token}             │
         ▼                                                     │
┌─────────────────┐                                            │
│  YVE CRM        │ ◄──────────────────────────────────────────┘
│  (backend)      │
└─────────────────┘
```

**Conceitos importantes:**

| Conceito | Descrição |
|----------|-----------|
| **Credenciais globais** | Uma única `api_url` + `api_key` para todo o sistema (configuradas pelo superadmin ou `.env`) |
| **Instância por tenant** | Cada cliente/tenant tem **no máximo 1** instância WhatsApp |
| **Nome da instância** | Gerado automaticamente: `{slug-do-tenant}-yve` (ex: `yve-beauty-yve`) |
| **Webhook token** | Token único por instância, usado na URL do webhook para identificar o tenant |

---

## 2. Pré-requisitos e configuração global

### 2.1 Variáveis de ambiente (fallback)

No `.env`:

```env
EVOLUTION_API_URL=https://sua-evolution-api.com
EVOLUTION_API_KEY=sua-api-key-global
```

### 2.2 Configuração no banco (prioridade)

O superadmin salva em `tenants.settings_json` (registro `id = 0`, configurações do sistema):

```json
{
  "evolution_enabled": true,
  "evolution_default_api_url": "https://sua-evolution-api.com",
  "evolution_global_api_key": "sua-api-key-global"
}
```

### 2.3 Lógica de resolução das credenciais

```php
// Ordem de prioridade:
$apiUrl = $settings['evolution_default_api_url'] ?? Env::get('EVOLUTION_API_URL', '');
$apiKey = $settings['evolution_global_api_key'] ?? Env::get('EVOLUTION_API_KEY', '');

// Habilitado se:
// - evolution_enabled !== false no banco, E
// - URL e Key não estão vazios
```

**Antes de criar instância**, o backend valida:

1. `evolution_enabled === true`
2. `api_url` e `api_key` preenchidos
3. Tenant existe
4. Ainda **não** existe instância para aquele tenant

---

## 3. Cliente HTTP (como toda requisição é feita)

Todas as chamadas passam por `EvolutionApiService::request()`.

### 3.1 Headers obrigatórios

```http
apikey: {EVOLUTION_API_KEY}
Accept: application/json
Content-Type: application/json   # apenas em POST com body
```

### 3.2 Formato de resposta padronizado

Cada método retorna:

```php
[
    'ok'   => bool,   // true se HTTP 2xx
    'http' => int,    // código HTTP
    'body' => mixed,  // JSON decodificado (array ou null)
    'raw'  => string, // resposta bruta
]
```

### 3.3 Implementação base (cURL)

```php
$headers = [
    'apikey: ' . $apiKey,
    'Accept: application/json',
];
if ($jsonBody !== null) {
    $headers[] = 'Content-Type: application/json';
}

curl_setopt_array($ch, [
    CURLOPT_CUSTOMREQUEST => $method,      // GET, POST, DELETE
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 30,                 // 120s para mídia
    CURLOPT_HTTPHEADER => $headers,
]);

if ($jsonBody !== null && $method === 'POST') {
    curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonBody);
}
```

> **Dica para outro projeto:** encapsule isso em um único client HTTP e reutilize em todos os endpoints Evolution.

---

## 4. Criação automática de instância

### 4.1 Fluxo completo (passo a passo)

```
Usuário clica "Ativar WhatsApp"
        │
        ▼
POST /api/settings/whatsapp/instances
        │
        ├─► 1. Valida credenciais globais
        ├─► 2. Gera instanceName = "{slug}-yve"
        ├─► 3. Gera webhook_token = bin2hex(random_bytes(16))
        ├─► 4. INSERT em whatsapp_instances (status: pending)
        ├─► 5. POST Evolution /instance/create
        │       └─ falhou? DELETE rollback no banco
        ├─► 6. POST Evolution /webhook/set (se URL válida)
        └─► 7. Retorna instância criada
```

### 4.2 Geração do nome da instância

```php
$baseName = !empty($tenant['slug']) ? $tenant['slug'] : 'tenant' . $tenantId;
$instanceName = $baseName . '-yve';
// Exemplos:
// slug "yve-beauty"  → "yve-beauty-yve"
// slug vazio, id=3   → "tenant3-yve"
```

### 4.3 Endpoint Evolution: criar instância

| Campo | Valor |
|-------|-------|
| **Método** | `POST` |
| **URL** | `{BASE_URL}/instance/create` |
| **Body** | JSON abaixo |

```json
{
  "instanceName": "yve-beauty-yve",
  "integration": "WHATSAPP-BAILEYS",
  "qrcode": true
}
```

**Campos obrigatórios (testados no projeto):**

- `instanceName` — identificador único na Evolution
- `integration` — **obrigatório**; valor usado: `"WHATSAPP-BAILEYS"`
- `qrcode` — `true` para habilitar geração de QR na conexão

**Webhook na criação — NÃO enviar inicialmente:**

O projeto **não** envia `webhook` no `POST /instance/create` porque isso causava erro `400 - Invalid url property`. O webhook é configurado **depois**, em chamada separada (`/webhook/set`).

```php
// ❌ Evitar na criação (pode falhar):
$data['webhook'] = $webhookUrl;
$data['webhook_by_events'] = true;
$data['events'] = ['MESSAGES_UPSERT', 'CONNECTION_UPDATE'];

// ✅ Criar sem webhook:
$createRes = $evo->createInstance($apiUrl, $apiKey, $instanceName, null);
```

### 4.4 Registro no banco local (antes da Evolution)

```php
TenantAwareDatabase::insert('whatsapp_instances', [
    'name'            => $tenant['name'] ?? 'Principal',
    'instance_name'   => $instanceName,
    'api_url'         => $global['api_url'],
    'api_key'         => $global['api_key'],
    'status'          => 'pending',
    'phone_connected' => false,
    'webhook_token'   => $token,  // bin2hex(random_bytes(16))
]);
```

### 4.5 Rollback em caso de falha

Se `POST /instance/create` retornar erro, o registro local é removido:

```php
if (!$createRes['ok']) {
    TenantAwareDatabase::query(
        'DELETE FROM whatsapp_instances WHERE id = :id AND tenant_id = :tid',
        [':id' => $id, ':tid' => $tenantId]
    );
    // retorna erro ao frontend
}
```

---

## 5. QR Code e conexão do WhatsApp

### 5.1 Fluxo no frontend

```
Usuário clica "Conectar"
        │
        ▼
GET /api/settings/whatsapp/instances/{id}/qr-code
        │
        ├─► Backend chama Evolution GET /instance/connect/{instanceName}
        ├─► Extrai QR da resposta
        └─► Frontend exibe imagem + inicia polling a cada 3s
                │
                ▼
        GET /api/settings/whatsapp/instances/{id}/check-status
                │
                └─► Quando connected=true → alerta e atualiza tela
```

### 5.2 Endpoint Evolution: obter QR Code

| Campo | Valor |
|-------|-------|
| **Método** | `GET` |
| **URL** | `{BASE_URL}/instance/connect/{instanceName}` |
| **Body** | nenhum |

> O mesmo endpoint `/instance/connect` inicia/reinicia o processo de pareamento e devolve o QR atual.

### 5.3 Campos de resposta do QR (Evolution v2)

A API pode retornar o QR em formatos diferentes. O projeto tenta nesta ordem:

```php
$qrCode = $res['body']['base64']    // imagem pronta (data URI ou base64)
       ?? $res['body']['code']      // string do QR (texto)
       ?? $res['body']['qrcode']    // fallback legado
       ?? null;

$pairingCode = $res['body']['pairingCode'] ?? null;  // código numérico alternativo
```

### 5.4 Exibição no frontend

```javascript
// Se qr_code já vem como data URI ou URL utilizável:
qrContainer.innerHTML = `<img src="${data.qr_code}" alt="QR Code" class="h-48 w-48">`;

// pairing_code (opcional) — pareamento por código no celular
if (data.pairing_code) {
    pairingCode.textContent = data.pairing_code;
}
```

### 5.5 Polling de conexão

Após exibir o QR, o frontend verifica o status a cada **3 segundos** por até **5 minutos**:

```javascript
setInterval(async () => {
    const r = await API.get(`/api/settings/whatsapp/instances/${instanceId}/check-status`);
    if (r.data.connected) {
        clearInterval(pollingInterval);
        alert('WhatsApp conectado com sucesso!');
        loadWhatsAppStatus();
    }
}, 3000);

// Timeout de segurança: 300000ms (5 min)
```

---

## 6. Verificação de status da conexão

### 6.1 Endpoint Evolution: estado da conexão

| Campo | Valor |
|-------|-------|
| **Método** | `GET` |
| **URL** | `{BASE_URL}/instance/connectionState/{instanceName}` |

### 6.2 Interpretação do estado

```php
// Evolution retorna state em body.instance.state ou body.state
$state = $stateRes['body']['instance']['state']
      ?? $stateRes['body']['state']
      ?? 'unknown';

$isConnected = in_array(strtolower($state), ['open', 'connected']);
```

| `state` (Evolution) | Status no banco | Significado |
|---------------------|-----------------|-------------|
| `open` / `connected` | `connected` | WhatsApp pareado e ativo |
| `close` | `disconnected` | Desconectado |
| outros | `pending` | Aguardando QR ou reconexão |

### 6.3 Obter número conectado (quando conectado)

| Campo | Valor |
|-------|-------|
| **Método** | `GET` |
| **URL** | `{BASE_URL}/instance/fetchInstances?instanceName={instanceName}` |

```php
$instanceInfo = $infoRes['body'][0];  // array de instâncias
$ownerJid = $instanceInfo['ownerJid'];  // ex: "554191788844@s.whatsapp.net"

// Remove sufixo para salvar só os dígitos:
$phoneNumber = str_replace('@s.whatsapp.net', '', $ownerJid);
```

### 6.4 Atualização no banco

```php
TenantAwareDatabase::update('whatsapp_instances', [
    'status'          => $isConnected ? 'connected' : ($state === 'close' ? 'disconnected' : 'pending'),
    'phone_connected' => $isConnected,
    'phone_number'    => $phoneNumber,  // quando disponível
    'updated_at'      => date('Y-m-d H:i:s'),
], 'id = :id', [':id' => $id]);
```

### 6.5 Desconectar (logout)

| Campo | Valor |
|-------|-------|
| **Método** | `DELETE` |
| **URL** | `{BASE_URL}/instance/logout/{instanceName}` |

Após sucesso, atualiza `status = disconnected` e `phone_connected = false`.

---

## 7. Webhook (recebimento de eventos)

### 7.1 URL do webhook por instância

Construída automaticamente com o host da requisição:

```
https://{HTTP_HOST}/webhook/evolution/{webhook_token}
```

Exemplo: `https://crm.exemplo.com/webhook/evolution/a1b2c3d4e5f6...`

### 7.2 Endpoint Evolution: configurar webhook

| Campo | Valor |
|-------|-------|
| **Método** | `POST` |
| **URL** | `{BASE_URL}/webhook/set/{instanceName}` |

```json
{
  "webhook": {
    "enabled": true,
    "url": "https://crm.exemplo.com/webhook/evolution/TOKEN_DA_INSTANCIA",
    "byEvents": false,
    "base64": false,
    "events": [
      "MESSAGES_UPSERT",
      "MESSAGES_UPDATE",
      "MESSAGES_DELETE",
      "CONNECTION_UPDATE",
      "QRCODE_UPDATED",
      "PRESENCE_UPDATE",
      "CONTACTS_UPSERT",
      "CONTACTS_UPDATE",
      "CHATS_UPSERT",
      "CHATS_UPDATE"
    ]
  }
}
```

### 7.3 Quando o webhook é configurado

1. **Automaticamente** após criar a instância (se `HTTP_HOST` estiver disponível)
2. **Manualmente** pelo botão "Configurar Webhook" no painel do tenant

### 7.4 Recebimento no backend

```
POST /webhook/evolution/{token}
```

1. Busca instância pelo `webhook_token`
2. Identifica `tenant_id` e `whatsapp_instance_id`
3. Processa evento via `WebhookProcessor`
4. **Sempre responde 200** (mesmo com erro interno) para evitar reenvio em loop pela Evolution

---

## 8. Endpoints internos do CRM (API REST)

Base: `/api/settings/whatsapp`

| Método | Rota | Ação |
|--------|------|------|
| `GET` | `/instances` | Lista instâncias do tenant + status global |
| `POST` | `/instances` | **Cria instância automaticamente** |
| `GET` | `/instances/{id}/qr-code` | **Obtém QR Code** |
| `GET` | `/instances/{id}/check-status` | Verifica conexão e atualiza banco |
| `GET` | `/instances/{id}/connection` | Retorna resposta bruta da Evolution |
| `POST` | `/instances/{id}/disconnect` | Logout na Evolution |
| `POST` | `/instances/{id}/configure-webhook` | Configura webhook manualmente |

Webhook público (sem auth de sessão):

| Método | Rota |
|--------|------|
| `POST` | `/webhook/evolution/{token}` |

---

## 9. Modelo de dados (banco)

Tabela `whatsapp_instances`:

```sql
CREATE TABLE whatsapp_instances (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT UNSIGNED NOT NULL,
    name VARCHAR(120) NOT NULL DEFAULT 'Principal',
    instance_name VARCHAR(120) NOT NULL,      -- nome na Evolution API
    api_url VARCHAR(512) NOT NULL DEFAULT '',
    api_key VARCHAR(512) NOT NULL DEFAULT '',
    status ENUM('connected','disconnected','pending') NOT NULL DEFAULT 'pending',
    phone_number VARCHAR(40) NULL,
    phone_connected TINYINT(1) NOT NULL DEFAULT 0,
    webhook_token VARCHAR(64) NOT NULL UNIQUE,
    settings_json JSON NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
```

**Regra de negócio:** 1 instância por `tenant_id` (validado antes do INSERT).

---

## 10. Fluxo no frontend

Arquivo: `public/assets/js/whatsapp-settings.js`

### Estados da tela

| Condição | O que o usuário vê |
|----------|-------------------|
| Global desabilitado/não configurado | Mensagem para contatar administrador |
| Global OK, sem instância | Card "Ativar WhatsApp" |
| Instância existe, desconectado | Botão "Conectar" → QR Code |
| Instância conectada | Número formatado + botão "Desconectar" |

### Sequência típica do usuário

```
1. Abre /settings/whatsapp
2. Clica "Ativar WhatsApp"
   → POST /api/settings/whatsapp/instances
3. Clica "Conectar"
   → GET .../qr-code → exibe QR
   → polling GET .../check-status a cada 3s
4. Escaneia QR no celular
   → polling detecta connected=true
   → tela atualiza com número conectado
```

---

## 11. Exemplos cURL prontos para copiar

Substitua as variáveis:

```bash
BASE_URL="https://sua-evolution-api.com"
API_KEY="sua-api-key"
INSTANCE_NAME="meu-tenant-yve"
WEBHOOK_URL="https://seu-app.com/webhook/evolution/TOKEN_UNICO"
```

### Criar instância

```bash
curl -X POST "${BASE_URL}/instance/create" \
  -H "apikey: ${API_KEY}" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "instanceName": "'"${INSTANCE_NAME}"'",
    "integration": "WHATSAPP-BAILEYS",
    "qrcode": true
  }'
```

### Obter QR Code (iniciar conexão)

```bash
curl -X GET "${BASE_URL}/instance/connect/${INSTANCE_NAME}" \
  -H "apikey: ${API_KEY}" \
  -H "Accept: application/json"
```

### Verificar estado da conexão

```bash
curl -X GET "${BASE_URL}/instance/connectionState/${INSTANCE_NAME}" \
  -H "apikey: ${API_KEY}" \
  -H "Accept: application/json"
```

### Buscar informações da instância (número conectado)

```bash
curl -X GET "${BASE_URL}/instance/fetchInstances?instanceName=${INSTANCE_NAME}" \
  -H "apikey: ${API_KEY}" \
  -H "Accept: application/json"
```

### Configurar webhook

```bash
curl -X POST "${BASE_URL}/webhook/set/${INSTANCE_NAME}" \
  -H "apikey: ${API_KEY}" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "webhook": {
      "enabled": true,
      "url": "'"${WEBHOOK_URL}"'",
      "byEvents": false,
      "base64": false,
      "events": [
        "MESSAGES_UPSERT",
        "CONNECTION_UPDATE",
        "QRCODE_UPDATED"
      ]
    }
  }'
```

### Desconectar instância

```bash
curl -X DELETE "${BASE_URL}/instance/logout/${INSTANCE_NAME}" \
  -H "apikey: ${API_KEY}" \
  -H "Accept: application/json"
```

---

## 12. Armadilhas conhecidas e boas práticas

### ❌ Erros comuns

| Problema | Causa | Solução |
|----------|-------|---------|
| `400 Invalid url property` | Enviar `webhook` no `POST /instance/create` com URL inválida | Criar instância **sem** webhook; configurar depois com `/webhook/set` |
| Instância criada no banco mas não na Evolution | Falha silenciosa | Sempre fazer rollback (DELETE) se Evolution retornar erro |
| QR não aparece | Resposta em campo diferente | Tentar `base64`, `code` e `qrcode` na resposta |
| Webhook não chega | URL não acessível publicamente | Evolution precisa alcançar `https://seu-dominio/webhook/...` |
| `integration` ausente | Campo obrigatório na v2 | Sempre enviar `"integration": "WHATSAPP-BAILEYS"` |

### ✅ Boas práticas

1. **Credenciais globais** — uma API Key para todas as instâncias; cada tenant só tem seu `instanceName`
2. **Nome único** — use slug/ID do tenant no nome para evitar colisão
3. **Token de webhook por instância** — identifica tenant sem expor IDs internos
4. **Polling após QR** — não confie só no webhook `CONNECTION_UPDATE` para UX imediata
5. **Sempre responder 200 no webhook** — evita reenvio infinito de eventos
6. **Logs detalhados** — o projeto loga request/response HTTP para debug
7. **Timeout maior para mídia** — 120s em endpoints de upload/decrypt

---

## 13. Arquivos de referência no projeto

| Arquivo | Responsabilidade |
|---------|-----------------|
| `app/Services/WhatsApp/EvolutionApiService.php` | Cliente HTTP — todos os endpoints Evolution |
| `app/Controllers/WhatsAppInstanceController.php` | Criação, QR, status, webhook, disconnect |
| `app/Controllers/WebhookController.php` | Recepção de eventos Evolution |
| `app/Controllers/SuperAdminSettingsController.php` | Config global (URL, Key, enabled) |
| `public/assets/js/whatsapp-settings.js` | UI: ativar, QR, polling, desconectar |
| `config/routes.php` | Rotas REST e webhook |
| `database/migrations/015_whatsapp_chat_automation_bulk.php` | Tabela `whatsapp_instances` |
| `database/migrations/017_alter_whatsapp_instances_add_phone_connected.php` | Coluna `phone_connected` |

---

## Resumo rápido para outro projeto

```text
1. Configure EVOLUTION_API_URL + EVOLUTION_API_KEY (global)
2. POST /instance/create  →  instanceName + integration + qrcode:true
3. POST /webhook/set      →  URL pública do seu backend
4. GET  /instance/connect →  exibir QR (base64 ou code)
5. GET  /instance/connectionState → polling até state = open
6. GET  /instance/fetchInstances  → obter ownerJid (número)
7. Receba eventos em POST /webhook/evolution/{token}
```

Com esses 7 passos você replica o mesmo fluxo do YVE CRM em qualquer stack (Node, Python, Laravel, etc.) — basta manter os mesmos endpoints e payloads da Evolution API v2.
