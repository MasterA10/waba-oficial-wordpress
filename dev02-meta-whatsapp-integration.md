# DEV 02 — Meta App, WhatsApp Setup, Webhook, Envio e Mídia

**Responsável:** Dev 02 / Meta & WhatsApp Integration

**Missão:** Implementar a camada oficial de integração com a Meta/WhatsApp: configuração do app, tokens, Embedded Signup, WABA/números, webhook, envio, status, mídia e guards de envio.

Este arquivo é uma divisão operacional do backlog do projeto **WhatsApp SaaS Core no WordPress**.  
A pessoa responsável por este arquivo deve entregar sua frente sem espalhar regra de negócio em telas, controllers ou scripts soltos.

## Regra global de arquitetura

A manutenção fácil depende de **responsabilidade centralizada**. Portanto:

- Controller não contém regra de negócio.
- Template PHP/JS não contém regra de negócio.
- Repository não decide regra de negócio; repository apenas lê e grava dados.
- Toda regra de negócio fica em `Service`, `Policy`, `Guard` ou `Orchestrator`.
- Todo acesso multiempresa passa por `TenantContext` e `TenantGuard`.
- Todo acesso ao banco usa `TableNameResolver` + Repository.
- Toda chamada para Meta/WhatsApp passa por `MetaApiClient`.
- Todo envio passa por `MessageDispatchService`.
- Todo webhook entra por `WebhookController`, salva evento bruto e depois é processado por services.
- Tokens nunca são lidos diretamente fora de `TokenVault`/`TokenService`.
- A UI só conversa com o backend por REST API interna.
- Nenhuma tela pode receber `tenant_id` livre do frontend e confiar nele.



## Contrato oficial Meta Graph API — abstração obrigatória

A integração oficial com a Meta deve ficar levemente abstraída. O time deve codar usando operações internas estáveis; o endpoint real da Meta fica em um único lugar.

### Fonte única de endpoints

Criar/usar apenas estes pontos centrais:

```txt
includes/Meta/MetaEndpointRegistry.php
includes/Meta/MetaApiClient.php
includes/Meta/MetaApiResponse.php
includes/Meta/MetaApiException.php
includes/Meta/MetaApiRequestLogger.php
```

Fluxo obrigatório:

```txt
Controller -> Service/Orchestrator -> MetaApiClient -> MetaEndpointRegistry -> Graph API
```

Proibido:

```txt
Controller chamando wp_remote_post direto
Template PHP chamando endpoint da Meta
JS chamando endpoint da Meta
Service montando URL hardcoded da Meta sem MetaEndpointRegistry
Repository chamando API externa
```

### Configuração mínima da Graph API

```php
define('WAS_META_GRAPH_BASE_URL', 'https://graph.facebook.com');
define('WAS_META_GRAPH_DEFAULT_VERSION', 'v25.0');
```

A versão deve ser lida de `was_meta_apps.graph_version`, com fallback para `WAS_META_GRAPH_DEFAULT_VERSION`. O endpoint real deve ser montado assim:

```txt
{base_url}/{graph_version}/{edge}
```

Exemplo:

```txt
https://graph.facebook.com/v25.0/{PHONE_NUMBER_ID}/messages
```

### Operações internas obrigatórias

```php
MetaApiClient::request('OAUTH_EXCHANGE_CODE', $params, $body, $tokenContext);
MetaApiClient::request('WA_SEND_MESSAGE', $params, $body, $tokenContext);
MetaApiClient::request('WA_UPLOAD_MEDIA', $params, $body, $tokenContext);
MetaApiClient::request('WA_GET_MEDIA_URL', $params, $body, $tokenContext);
MetaApiClient::request('WA_DELETE_MEDIA', $params, $body, $tokenContext);
MetaApiClient::request('WA_GET_PHONE_NUMBER', $params, $body, $tokenContext);
MetaApiClient::request('WA_LIST_WABA_PHONE_NUMBERS', $params, $body, $tokenContext);
MetaApiClient::request('WA_REGISTER_PHONE_NUMBER', $params, $body, $tokenContext);
MetaApiClient::request('WA_SUBSCRIBE_WABA_WEBHOOKS', $params, $body, $tokenContext);
MetaApiClient::request('WA_LIST_WABA_WEBHOOK_SUBSCRIPTIONS', $params, $body, $tokenContext);
MetaApiClient::request('WA_UNSUBSCRIBE_WABA_WEBHOOKS', $params, $body, $tokenContext);
MetaApiClient::request('WA_LIST_TEMPLATES', $params, $body, $tokenContext);
MetaApiClient::request('WA_CREATE_TEMPLATE', $params, $body, $tokenContext);
MetaApiClient::request('WA_GET_TEMPLATE', $params, $body, $tokenContext);
MetaApiClient::request('WA_EDIT_TEMPLATE', $params, $body, $tokenContext);
MetaApiClient::request('WA_DELETE_TEMPLATE', $params, $body, $tokenContext);
```

### Mapa de endpoints oficiais usados pelo MVP

```txt
OAUTH_EXCHANGE_CODE
GET /{version}/oauth/access_token
Uso: trocar o code do Embedded Signup/Facebook Login for Business por access token.
Query: client_id, client_secret, code, redirect_uri quando aplicável.

WA_SEND_MESSAGE
POST /{version}/{PHONE_NUMBER_ID}/messages
Uso: enviar texto, template, mídia, interativo ou outros tipos suportados pela Cloud API.
Permissão relacionada: whatsapp_business_messaging.

WA_UPLOAD_MEDIA
POST /{version}/{PHONE_NUMBER_ID}/media
Uso: subir mídia para envio posterior.
Content-Type: multipart/form-data.

WA_GET_MEDIA_URL
GET /{version}/{MEDIA_ID}?phone_number_id={PHONE_NUMBER_ID}
Uso: recuperar URL temporária de mídia recebida/enviada.

WA_DELETE_MEDIA
DELETE /{version}/{MEDIA_ID}?phone_number_id={PHONE_NUMBER_ID}
Uso: remover mídia da Cloud API quando necessário.

WA_GET_PHONE_NUMBER
GET /{version}/{PHONE_NUMBER_ID}
Uso: recuperar dados do número, nome verificado, display_phone_number e qualidade quando disponível.

WA_LIST_WABA_PHONE_NUMBERS
GET /{version}/{WABA_ID}/phone_numbers
Uso: listar números associados a uma WABA.

WA_REGISTER_PHONE_NUMBER
POST /{version}/{PHONE_NUMBER_ID}/register
Uso: registrar número na Cloud API quando o fluxo exigir.
Body mínimo esperado: messaging_product=whatsapp e pin/certificate quando aplicável ao cenário.

WA_SUBSCRIBE_WABA_WEBHOOKS
POST /{version}/{WABA_ID}/subscribed_apps
Uso: assinar o app nos webhooks da WABA conectada.

WA_LIST_WABA_WEBHOOK_SUBSCRIPTIONS
GET /{version}/{WABA_ID}/subscribed_apps
Uso: validar se o app está inscrito nos webhooks da WABA.

WA_UNSUBSCRIBE_WABA_WEBHOOKS
DELETE /{version}/{WABA_ID}/subscribed_apps
Uso: remover assinatura de webhooks quando desconectar uma conta.

WA_LIST_TEMPLATES
GET /{version}/{WABA_ID}/message_templates
Uso: sincronizar/listar templates oficiais da WABA.
Permissão relacionada: whatsapp_business_management.

WA_CREATE_TEMPLATE
POST /{version}/{WABA_ID}/message_templates
Uso: criar template oficial para aprovação da Meta.
Permissão relacionada: whatsapp_business_management.

WA_GET_TEMPLATE
GET /{version}/{TEMPLATE_ID}
Uso: buscar template específico por ID.

WA_EDIT_TEMPLATE
POST /{version}/{TEMPLATE_ID}
Uso: editar template quando permitido pela Meta.

WA_DELETE_TEMPLATE
DELETE /{version}/{WABA_ID}/message_templates?name={NAME}&hsm_id={TEMPLATE_ID}
Uso: excluir template por nome e, quando possível, por ID para evitar apagar traduções/variações indevidas.
```

### Headers padrão

```txt
Authorization: Bearer {access_token}
Content-Type: application/json
```

Para upload de mídia:

```txt
Authorization: Bearer {access_token}
Content-Type: multipart/form-data
```

### Resposta padronizada interna

`MetaApiClient` deve normalizar todas as respostas em um contrato único:

```php
[
  'success' => true|false,
  'status_code' => 200,
  'operation' => 'WA_SEND_MESSAGE',
  'meta_request_id' => null,
  'data' => [],
  'error' => [
    'code' => null,
    'subcode' => null,
    'message' => null,
    'type' => null,
    'fbtrace_id' => null,
  ],
]
```

Nenhum service deve precisar conhecer a estrutura bruta do erro da Meta.

### Logs obrigatórios de requisições externas

Toda chamada feita por `MetaApiClient` deve registrar, no mínimo:

```txt
tenant_id
operation
method
path sem token
status_code
success
error_code
error_subcode
error_message
duration_ms
created_at
```

Tokens, App Secret, Authorization header e payloads sensíveis nunca devem ser gravados em log.

### Referências oficiais para validação técnica

```txt
Graph API latest version:
https://developers.facebook.com/docs/graph-api/

Facebook Login for Business / OAuth code exchange:
https://developers.facebook.com/documentation/facebook-login/facebook-login-for-business

Embedded Signup overview:
https://developers.facebook.com/documentation/business-messaging/whatsapp/embedded-signup/overview/

Embedded Signup implementation:
https://developers.facebook.com/documentation/business-messaging/whatsapp/embedded-signup/implementation/

WhatsApp Message API:
https://developers.facebook.com/documentation/business-messaging/whatsapp/reference/whatsapp-business-phone-number/message-api

WhatsApp Media API:
https://developers.facebook.com/documentation/business-messaging/whatsapp/reference/media/media-api

WhatsApp Phone Number API:
https://developers.facebook.com/documentation/business-messaging/whatsapp/reference/whatsapp-business-phone-number/phone-number-api

WhatsApp Register API:
https://developers.facebook.com/documentation/business-messaging/whatsapp/reference/whatsapp-business-phone-number/register-api

WhatsApp Template API:
https://developers.facebook.com/documentation/business-messaging/whatsapp/reference/whatsapp-business-account/template-api

WhatsApp Template Management:
https://developers.facebook.com/documentation/business-messaging/whatsapp/templates/template-management/

WhatsApp Subscribed Apps API / Webhooks:
https://developers.facebook.com/documentation/business-messaging/whatsapp/reference/whatsapp-business-account/subscribed-apps-api

WhatsApp Webhooks overview:
https://developers.facebook.com/documentation/business-messaging/whatsapp/webhooks/overview/
```

## Fronteira de responsabilidade

### Pode editar/criar

- `includes/Meta/*`
- `includes/WhatsApp/*`
- `includes/REST/PublicApiController.php`
- rotas REST de Meta, Webhook, WhatsApp, envio e mídia
- telas/componentes de `WhatsApp Setup`
- services de envio, assinatura, token, rate limit e health check


### Não deve editar sem alinhamento

- Criar novas tabelas diretamente sem pedir ao Dev 01.
- Regras de renderização do Inbox que pertencem ao Dev 03.
- Regras de template que pertencem ao Dev 04, exceto envio de mensagem com template quando houver contrato pronto.
- Páginas legais e textos de review.


## Contratos públicos que esta frente deve respeitar

- `MetaApiClient`: única porta de saída para Graph API.
- `TokenVault`/`TokenService`: único acesso a tokens.
- `WhatsAppConnectionService`: resolve WABA/número conectado.
- `MessageDispatchService`: único lugar para enviar mensagens.
- `WebhookController`: recebe eventos públicos.
- `WebhookEventProcessor`: processa eventos pendentes e chama services específicos.
- `WebhookSignatureValidator`: único lugar para validar assinatura.
- `MessageSendingPolicy`: bloqueia envio sem conexão, sem permissão ou acima do rate limit.


## Handoff esperado ao terminar

- Tela de Setup mostra WABA/número/status de conexão.
- Webhook GET valida challenge.
- Webhook POST salva payload bruto e resolve tenant.
- Envio de texto pela API oficial funciona via service central.
- Status de mensagem é atualizado por webhook.
- Mídia tem service e endpoints mínimos.
- Health check da integração mostra conexão, webhook e últimos erros da Meta.


## Ordem recomendada de execução desta frente

### P0

- [ ] WAS-045 — Criar serviço de criptografia
- [ ] WAS-046 — Criar tela de configuração Meta App
- [ ] WAS-047 — Salvar configuração Meta App
- [ ] WAS-048 — Criar repository de Meta App
- [ ] WAS-049 — Criar cliente HTTP base para Graph API
- [ ] WAS-050 — Criar página WhatsApp Setup
- [ ] WAS-051 — Criar componente visual de conexão
- [ ] WAS-052 — Criar endpoint para salvar assets do Embedded Signup
- [ ] WAS-053 — Criar repository de WhatsApp Accounts
- [ ] WAS-054 — Criar repository de Phone Numbers
- [ ] WAS-055 — Mostrar WABA conectada na tela Setup
- [ ] WAS-056 — Mostrar número conectado na tela Setup
- [ ] WAS-058 — Criar controller público de webhook
- [ ] WAS-059 — Criar rota GET de verificação do webhook
- [ ] WAS-060 — Criar rota POST de recebimento do webhook
- [ ] WAS-061 — Salvar evento bruto do webhook
- [ ] WAS-062 — Validar assinatura do webhook
- [ ] WAS-063 — Extrair WABA ID e Phone Number ID do payload
- [ ] WAS-064 — Resolver tenant pelo Phone Number ID
- [ ] WAS-065 — Criar processador de eventos pendentes
- [ ] WAS-079 — Criar serviço de envio de mensagem
- [ ] WAS-080 — Criar endpoint de envio de texto
- [ ] WAS-081 — Salvar mensagem outbound após envio
- [ ] WAS-083 — Atualizar status por webhook
- [ ] WAS-085 — Criar audit log de envio de mensagem
- [ ] WAS-117 — Bloquear envio sem WhatsApp conectado
- [ ] WAS-118 — Bloquear envio por usuário sem permissão
### P1

- [ ] WAS-057 — Criar ação de desconectar WhatsApp
- [ ] WAS-066 — Criar WP-Cron para processar webhooks pendentes
- [ ] WAS-095 — Criar service de mídia
- [ ] WAS-096 — Criar endpoint de envio de mídia
- [ ] WAS-097 — Processar inbound de mídia como referência
- [ ] WAS-116 — Criar rate limit de envio de mensagens
- [ ] WAS-134 — Criar badges de status de conexão
- [ ] WAS-135 — Criar página interna de saúde da integração
- [ ] WAS-136 — Criar mensagens amigáveis para erros da Meta

---


## Tasks adicionais — implementação oficial Meta/WhatsApp

Estas tasks tornam a frente do Dev 02 responsável por transformar o contrato abstrato em requisições reais para a Graph API.

### WAS-049E — Implementar `MetaApiClient` com `wp_remote_request`

**Prioridade:** P0  
**Objetivo:** executar chamadas oficiais à Graph API usando o contrato central.  
**Entrega:** `includes/Meta/MetaApiClient.php`.

**Critérios de aceite:**

- Recebe `operation`, `params`, `body` e `tokenContext`.
- Usa `MetaEndpointRegistry` para montar método/path/query.
- Usa `TokenService` para resolver token quando aplicável.
- Adiciona `Authorization: Bearer` no servidor, nunca no frontend.
- Normaliza sucesso/erro em `MetaApiResponse`.
- Registra log seguro via `MetaApiRequestLogger`.

**Depende de:** WAS-045, WAS-049A, WAS-049B.

---

### WAS-049F — Implementar troca de code do Embedded Signup

**Prioridade:** P0  
**Objetivo:** trocar o code retornado pelo Embedded Signup/Facebook Login for Business por token no backend.  
**Endpoint oficial:** `GET /{version}/oauth/access_token`.  
**Operação interna:** `OAUTH_EXCHANGE_CODE`.

**Critérios de aceite:**

- Recebe `code` apenas no backend.
- Usa `client_id`, `client_secret`, `code` e `redirect_uri` quando aplicável.
- Salva token criptografado em `was_meta_tokens`.
- Nunca retorna access token completo para o frontend.
- Registra audit log `connect_whatsapp_token_exchanged`.

**Depende de:** WAS-045, WAS-047, WAS-052.

---

### WAS-052A — Salvar assets do Embedded Signup com validação oficial

**Prioridade:** P0  
**Objetivo:** salvar `waba_id`, `phone_number_id` e token após o retorno do Embedded Signup.  
**Endpoints relacionados:** `GET /{PHONE_NUMBER_ID}`, `GET /{WABA_ID}/phone_numbers`.  
**Operações internas:** `WA_GET_PHONE_NUMBER`, `WA_LIST_WABA_PHONE_NUMBERS`.

**Critérios de aceite:**

- Recebe `waba_id`, `phone_number_id` e `code` do frontend.
- Troca o code no backend.
- Valida se o número pertence à WABA informada.
- Cria/atualiza `was_whatsapp_accounts`.
- Cria/atualiza `was_whatsapp_phone_numbers`.
- Define número default se for o primeiro do tenant.

**Depende de:** WAS-049F, WAS-053, WAS-054.

---

### WAS-052B — Implementar registro de número quando necessário

**Prioridade:** P1  
**Objetivo:** permitir registrar número para uso na Cloud API quando o fluxo exigir.  
**Endpoint oficial:** `POST /{PHONE_NUMBER_ID}/register`.  
**Operação interna:** `WA_REGISTER_PHONE_NUMBER`.

**Body esperado:**

```json
{
  "messaging_product": "whatsapp",
  "pin": "123456"
}
```

**Critérios de aceite:**

- PIN nunca é salvo em texto puro.
- Suporta cenário em que o número já está registrado.
- Erros da Meta são traduzidos para mensagens amigáveis no painel.
- Audit log `register_phone_number` criado.

**Depende de:** WAS-052A, WAS-136.

---

### WAS-060A — Assinar app nos webhooks da WABA

**Prioridade:** P0  
**Objetivo:** garantir que a WABA conectada envie eventos para o app.  
**Endpoint oficial:** `POST /{WABA_ID}/subscribed_apps`.  
**Operação interna:** `WA_SUBSCRIBE_WABA_WEBHOOKS`.

**Critérios de aceite:**

- Após salvar assets, chama assinatura da WABA.
- Sucesso esperado: `{ "success": true }`.
- Registra o resultado em logs.
- Se falhar, Setup mostra alerta claro: conta conectada, mas webhook não assinado.

**Depende de:** WAS-052A, WAS-060.

---

### WAS-060B — Validar assinatura ativa de webhooks

**Prioridade:** P1  
**Objetivo:** mostrar no Setup se a WABA está inscrita no app.  
**Endpoint oficial:** `GET /{WABA_ID}/subscribed_apps`.  
**Operação interna:** `WA_LIST_WABA_WEBHOOK_SUBSCRIPTIONS`.

**Critérios de aceite:**

- Health check exibe `webhook_subscribed=true|false`.
- Botão “Reassinar webhook” aparece se a assinatura não existir.
- Não quebra se a API responder lista vazia.

**Depende de:** WAS-060A, WAS-135.

---

### WAS-079A — Implementar envio oficial de texto

**Prioridade:** P0  
**Objetivo:** enviar texto livre pela Cloud API quando permitido pela janela de atendimento.  
**Endpoint oficial:** `POST /{PHONE_NUMBER_ID}/messages`.  
**Operação interna:** `WA_SEND_MESSAGE`.

**Payload mínimo:**

```json
{
  "messaging_product": "whatsapp",
  "recipient_type": "individual",
  "to": "5511999999999",
  "type": "text",
  "text": {
    "preview_url": false,
    "body": "Olá, esta é uma mensagem de teste pela API oficial."
  }
}
```

**Critérios de aceite:**

- Envio passa apenas por `MessageDispatchService`.
- Retorno salva `messages[0].id` como `wa_message_id`.
- Bloqueia envio sem tenant, token, phone number ou permissão.
- Log não grava token nem telefone completo quando modo privacy estiver ativo.

**Depende de:** WAS-079, WAS-080, WAS-117, WAS-118.

---

### WAS-095A — Implementar upload oficial de mídia

**Prioridade:** P1  
**Objetivo:** subir mídia para a Cloud API antes de envio.  
**Endpoint oficial:** `POST /{PHONE_NUMBER_ID}/media`.  
**Operação interna:** `WA_UPLOAD_MEDIA`.

**Critérios de aceite:**

- Usa `multipart/form-data`.
- Salva `meta_media_id` em `was_media`.
- Valida MIME type permitido.
- Não aceita upload por usuário sem permissão.

**Depende de:** WAS-095.

---

### WAS-083A — Normalizar status de mensagem vindo do webhook

**Prioridade:** P0  
**Objetivo:** converter payloads da Meta em status interno consistente.  
**Entrada:** webhook `messages/statuses`.  
**Saída:** `created`, `sent`, `delivered`, `read`, `failed`.

**Critérios de aceite:**

- Atualiza `was_messages.status`.
- Insere linha em `was_message_statuses`.
- Evita duplicidade por `wa_message_id + status + timestamp` quando possível.
- Erros da Meta ficam em `error_code` e `error_message`.

**Depende de:** WAS-083.
---

# Tasks atribuídas

## WAS-045 — Criar serviço de criptografia

**Prioridade:** P0  
**Objetivo:** criptografar secrets e tokens antes de salvar.  
**Entrega:** classe `EncryptionService`.

**Critérios de aceite:**

- Possui métodos `encrypt` e `decrypt`.
- Usa chave definida em constante/config segura.
- Retorna erro controlado se chave não existir.

**Depende de:** WAS-006.

---

---

## WAS-046 — Criar tela de configuração Meta App

**Prioridade:** P0  
**Objetivo:** permitir cadastrar App ID, App Secret, Graph Version e Verify Token.  
**Entrega:** página `/app/settings/meta` ou aba dentro de `/app/settings/whatsapp`.

**Critérios de aceite:**

- Campos aparecem apenas para `platform_owner`.
- App Secret não é exibido depois de salvo.
- Verify Token pode ser gerado automaticamente.

**Depende de:** WAS-037, WAS-045.

---

---

## WAS-047 — Salvar configuração Meta App

**Prioridade:** P0  
**Objetivo:** persistir dados do app Meta com segurança.  
**Entrega:** handler para salvar em `was_meta_apps`.

**Critérios de aceite:**

- App Secret é salvo criptografado.
- Inputs são sanitizados.
- Ação gera audit log `save_meta_config`.

**Depende de:** WAS-013, WAS-045, WAS-046.

---

---

## WAS-048 — Criar repository de Meta App

**Prioridade:** P0  
**Objetivo:** centralizar leitura/escrita de configuração Meta.  
**Entrega:** classe `MetaAppRepository`.

**Critérios de aceite:**

- Possui método para obter app ativo.
- Possui método para salvar/atualizar app.
- Não expõe secret descriptografado por padrão.

**Depende de:** WAS-013, WAS-045.

---

---

## WAS-049 — Criar cliente HTTP base para Graph API

**Prioridade:** P0  
**Objetivo:** padronizar chamadas para Meta.  
**Entrega:** classe `MetaApiClient`.

**Critérios de aceite:**

- Usa `wp_remote_get`, `wp_remote_post` ou equivalente.
- Monta URL com versão da Graph API configurada.
- Trata erros HTTP de forma padronizada.

**Depende de:** WAS-048.

---

# EPIC 06 — WhatsApp Setup e Embedded Signup

---

## WAS-050 — Criar página WhatsApp Setup

**Prioridade:** P0  
**Objetivo:** concentrar conexão da conta WhatsApp Business.  
**Entrega:** página `/app/settings/whatsapp`.

**Critérios de aceite:**

- Página exige `was_manage_whatsapp`.
- Mostra estado: não conectado/conectado/erro.
- Mostra URL pública do webhook.

**Depende de:** WAS-037, WAS-039.

---

---

## WAS-051 — Criar componente visual de conexão

**Prioridade:** P0  
**Objetivo:** mostrar botão de conexão com Meta.  
**Entrega:** card “Connect WhatsApp Business Account”.

**Critérios de aceite:**

- Botão aparece quando não há WABA conectada.
- Botão fica desabilitado se Meta App não estiver configurado.
- Texto explica que a conexão usa API oficial.

**Depende de:** WAS-050, WAS-047.

---

---

## WAS-052 — Criar endpoint para salvar assets do Embedded Signup

**Prioridade:** P0  
**Objetivo:** receber `waba_id`, `phone_number_id` e dados retornados pelo fluxo.  
**Entrega:** `POST /wp-json/was/v1/meta/embedded-signup/save-assets`.

**Critérios de aceite:**

- Endpoint exige login e `was_manage_whatsapp`.
- Salva WABA em `was_whatsapp_accounts`.
- Salva número em `was_whatsapp_phone_numbers`.
- Vincula tudo ao tenant atual.

**Depende de:** WAS-014, WAS-015, WAS-042.

---

---

## WAS-053 — Criar repository de WhatsApp Accounts

**Prioridade:** P0  
**Objetivo:** centralizar operações de WABA.  
**Entrega:** classe `WhatsAppAccountRepository`.

**Critérios de aceite:**

- Possui métodos `findByWabaId`, `createOrUpdate`, `getByTenant`.
- Sempre filtra por tenant quando aplicável.
- Usa queries preparadas.

**Depende de:** WAS-014.

---

---

## WAS-054 — Criar repository de Phone Numbers

**Prioridade:** P0  
**Objetivo:** centralizar operações de números WhatsApp.  
**Entrega:** classe `PhoneNumberRepository`.

**Critérios de aceite:**

- Possui métodos `findByPhoneNumberId`, `createOrUpdate`, `getDefaultByTenant`.
- Garante apenas um número default por tenant.
- Sempre filtra por tenant quando aplicável.

**Depende de:** WAS-015.

---

---

## WAS-055 — Mostrar WABA conectada na tela Setup

**Prioridade:** P0  
**Objetivo:** exibir ativos conectados para o usuário.  
**Entrega:** card com WABA ID, nome e status.

**Critérios de aceite:**

- Mostra dados reais salvos.
- Não mostra tokens.
- Se não houver conexão, mostra estado vazio.

**Depende de:** WAS-052, WAS-053.

---

---

## WAS-056 — Mostrar número conectado na tela Setup

**Prioridade:** P0  
**Objetivo:** exibir phone number usado no envio de mensagens.  
**Entrega:** card com `phone_number_id`, número exibido e status.

**Critérios de aceite:**

- Mostra número default do tenant.
- Mostra aviso se não houver número default.
- Não permite editar ID manualmente sem permissão.

**Depende de:** WAS-052, WAS-054.

---

---

## WAS-057 — Criar ação de desconectar WhatsApp

**Prioridade:** P1  
**Objetivo:** permitir remover conexão do tenant.  
**Entrega:** botão “Disconnect”.

**Critérios de aceite:**

- Altera status da WABA para `disconnected`.
- Altera status dos números para `inactive`.
- Gera audit log `disconnect_whatsapp`.
- Não apaga mensagens históricas.

**Depende de:** WAS-055, WAS-056.

---

# EPIC 07 — Webhook

---

## WAS-058 — Criar controller público de webhook

**Prioridade:** P0  
**Objetivo:** receber chamadas públicas da Meta.  
**Entrega:** classe `WebhookController`.

**Critérios de aceite:**

- Controller possui método para GET de verificação.
- Controller possui método para POST de eventos.
- Rotas são públicas, mas validadas internamente.

**Depende de:** WAS-041, WAS-048.

---

---

## WAS-059 — Criar rota GET de verificação do webhook

**Prioridade:** P0  
**Objetivo:** permitir validação do endpoint pela Meta.  
**Entrega:** `GET /wp-json/was/v1/meta/webhook`.

**Critérios de aceite:**

- Valida `hub.mode`.
- Valida `hub.verify_token` contra configuração salva.
- Retorna `hub.challenge` quando válido.
- Retorna 403 quando inválido.

**Depende de:** WAS-058, WAS-047.

---

---

## WAS-060 — Criar rota POST de recebimento do webhook

**Prioridade:** P0  
**Objetivo:** receber eventos da Meta.  
**Entrega:** `POST /wp-json/was/v1/meta/webhook`.

**Critérios de aceite:**

- Recebe JSON bruto.
- Responde rápido com HTTP 200 em caso de payload válido.
- Não processa lógica pesada antes de salvar o evento.

**Depende de:** WAS-058.

---

---

## WAS-061 — Salvar evento bruto do webhook

**Prioridade:** P0  
**Objetivo:** evitar perda de dados antes do processamento.  
**Entrega:** gravação em `was_webhook_events`.

**Critérios de aceite:**

- Payload completo é salvo.
- `processing_status` inicia como `pending`.
- `received_at` é preenchido.

**Depende de:** WAS-024, WAS-060.

---

---

## WAS-062 — Validar assinatura do webhook

**Prioridade:** P0  
**Objetivo:** aumentar segurança do endpoint público.  
**Entrega:** verificação de assinatura usando app secret.

**Critérios de aceite:**

- Campo `signature_valid` é salvo no evento.
- Payload com assinatura inválida é marcado como inválido.
- Política de rejeição/aceite é configurável.

**Depende de:** WAS-045, WAS-047, WAS-061.

---

---

## WAS-063 — Extrair WABA ID e Phone Number ID do payload

**Prioridade:** P0  
**Objetivo:** identificar a empresa dona do evento.  
**Entrega:** parser básico de payload WhatsApp.

**Critérios de aceite:**

- Extrai `waba_id` quando disponível.
- Extrai `phone_number_id` quando disponível.
- Atualiza o registro em `was_webhook_events`.

**Depende de:** WAS-061.

---

---

## WAS-064 — Resolver tenant pelo Phone Number ID

**Prioridade:** P0  
**Objetivo:** associar evento recebido ao tenant correto.  
**Entrega:** lookup pelo `phone_number_id` salvo.

**Critérios de aceite:**

- Evento recebe `tenant_id` quando número é conhecido.
- Evento permanece pendente/erro quando número é desconhecido.
- Nenhum tenant é inferido por dados do frontend.

**Depende de:** WAS-054, WAS-063.

---

---

## WAS-065 — Criar processador de eventos pendentes

**Prioridade:** P0  
**Objetivo:** processar webhooks salvos.  
**Entrega:** classe `WebhookEventProcessor`.

**Critérios de aceite:**

- Busca eventos `pending`.
- Processa um evento por vez.
- Marca como `processed` ou `failed`.

**Depende de:** WAS-061, WAS-064.

---

---

## WAS-066 — Criar WP-Cron para processar webhooks pendentes

**Prioridade:** P1  
**Objetivo:** processar eventos mesmo quando o POST só salvou o payload.  
**Entrega:** cron interno do WordPress.

**Critérios de aceite:**

- Cron roda em intervalo definido.
- Processa quantidade limitada por execução.
- Não duplica processamento.

**Depende de:** WAS-065.

---

# EPIC 08 — Contatos, conversas e mensagens inbound

---

## WAS-079 — Criar serviço de envio de mensagem

**Prioridade:** P0  
**Objetivo:** centralizar envio pela Cloud API.  
**Entrega:** classe `MessageService`.

**Critérios de aceite:**

- Recebe tenant, phone number, destino e corpo.
- Chama `MetaApiClient`.
- Retorna sucesso/erro padronizado.

**Depende de:** WAS-049, WAS-054.

---

---

## WAS-080 — Criar endpoint de envio de texto

**Prioridade:** P0  
**Objetivo:** permitir enviar mensagem pelo chat.  
**Entrega:** `POST /wp-json/was/v1/messages/send-text`.

**Critérios de aceite:**

- Exige login e `was_send_messages`.
- Valida conversa pertence ao tenant.
- Valida texto não vazio.
- Chama `MessageService`.

**Depende de:** WAS-042, WAS-079.

---

---

## WAS-081 — Salvar mensagem outbound após envio

**Prioridade:** P0  
**Objetivo:** registrar mensagem enviada na conversa.  
**Entrega:** gravação em `was_messages` com `direction = outbound`.

**Critérios de aceite:**

- Salva `wa_message_id` retornado pela API quando disponível.
- Salva status inicial `sent` ou `api_error`.
- Atualiza preview da conversa.

**Depende de:** WAS-080, WAS-069.

---

---

## WAS-083 — Atualizar status por webhook

**Prioridade:** P0  
**Objetivo:** refletir status entregue/lido/falha.  
**Entrega:** handler de status de mensagem.

**Critérios de aceite:**

- Cria registro em `was_message_statuses`.
- Atualiza status da mensagem em `was_messages`.
- Não quebra se mensagem original não for encontrada.

**Depende de:** WAS-021, WAS-065, WAS-069.

---

---

## WAS-085 — Criar audit log de envio de mensagem

**Prioridade:** P0  
**Objetivo:** registrar ação sensível do operador.  
**Entrega:** audit log `send_message`.

**Critérios de aceite:**

- Registra usuário, tenant, conversa e mensagem.
- Não salva conteúdo sensível em excesso no metadata.
- É criado apenas em tentativa real de envio.

**Depende de:** WAS-025, WAS-080.

---

# EPIC 11 — Templates oficiais

---

## WAS-095 — Criar service de mídia

**Prioridade:** P1  
**Objetivo:** centralizar upload/download de mídia.  
**Entrega:** classe `MediaService`.

**Critérios de aceite:**

- Possui método para upload para Meta.
- Possui método para salvar referência local.
- Não implementa UI nessa task.

**Depende de:** WAS-023, WAS-049.

---

---

## WAS-096 — Criar endpoint de envio de mídia

**Prioridade:** P1  
**Objetivo:** permitir envio de imagem/documento.  
**Entrega:** `POST /wp-json/was/v1/messages/send-media`.

**Critérios de aceite:**

- Exige `was_send_messages`.
- Valida arquivo permitido.
- Faz upload para Meta.
- Envia mensagem com media id.

**Depende de:** WAS-095, WAS-080.

---

---

## WAS-097 — Processar inbound de mídia como referência

**Prioridade:** P1  
**Objetivo:** registrar mensagem recebida com mídia sem baixar arquivo imediatamente.  
**Entrega:** handler inbound para image/document/audio/video.

**Critérios de aceite:**

- Salva `meta_media_id` em `was_media`.
- Cria mensagem inbound relacionada.
- UI mostra tipo de mídia recebida.

**Depende de:** WAS-095, WAS-070.

---

# EPIC 13 — Logs, auditoria e telas técnicas

---

## WAS-116 — Criar rate limit de envio de mensagens

**Prioridade:** P1  
**Objetivo:** evitar abuso e loops acidentais.  
**Entrega:** limitador por tenant/usuário.

**Critérios de aceite:**

- Bloqueia volume excessivo por janela de tempo.
- Retorna erro amigável.
- Gera audit log ou log técnico quando bloquear.

**Depende de:** WAS-080, WAS-025.

---

---

## WAS-117 — Bloquear envio sem WhatsApp conectado

**Prioridade:** P0  
**Objetivo:** evitar erro de API por falta de configuração.  
**Entrega:** validação antes de enviar mensagem.

**Critérios de aceite:**

- Se não houver número default, envio é bloqueado.
- UI mostra instrução para conectar WhatsApp.
- Nenhuma chamada externa é feita sem configuração.

**Depende de:** WAS-054, WAS-080.

---

---

## WAS-118 — Bloquear envio por usuário sem permissão

**Prioridade:** P0  
**Objetivo:** garantir controle de acesso.  
**Entrega:** validação rígida em endpoint e UI.

**Critérios de aceite:**

- Usuário sem `was_send_messages` não vê campo de envio.
- Mesmo se chamar endpoint manualmente, recebe 403.
- Gera audit log opcional de tentativa negada.

**Depende de:** WAS-028, WAS-080.

---

# EPIC 17 — Ambiente de demonstração para App Review

---

## WAS-134 — Criar badges de status de conexão

**Prioridade:** P1  
**Objetivo:** facilitar leitura operacional da plataforma.  
**Entrega:** badges conectado/desconectado/erro.

**Critérios de aceite:**

- Dashboard mostra status de conexão.
- Setup mostra status da WABA e número.
- Badges usam texto claro.

**Depende de:** WAS-038, WAS-055, WAS-056.

---

---

## WAS-135 — Criar página interna de saúde da integração

**Prioridade:** P1  
**Objetivo:** diagnosticar rapidamente problemas de configuração.  
**Entrega:** tela ou card “Integration Health”.

**Critérios de aceite:**

- Verifica Meta App configurado.
- Verifica WABA conectada.
- Verifica número default.
- Verifica webhook URL.
- Verifica templates sincronizados.

**Depende de:** WAS-050, WAS-098.

---

---

## WAS-136 — Criar mensagens amigáveis para erros da Meta

**Prioridade:** P1  
**Objetivo:** evitar que operadores vejam erro técnico cru.  
**Entrega:** mapper de erros comuns.

**Critérios de aceite:**

- Erros de token aparecem como problema de conexão.
- Erros de permissão aparecem como permissão ausente.
- Erros de template aparecem com contexto útil.

**Depende de:** WAS-049, WAS-082, WAS-091.

---

# EPIC 20 — Backlog pós-MVP
