# DEV 03 — Inbox, Contatos, Conversas, Chat e Experiência Operacional

**Responsável:** Dev 03 / Inbox & Messaging Experience

**Missão:** Criar a experiência de atendimento: contacts, conversations, messages, inbox, histórico, campo de resposta, estados vazios, atribuição, tags, filtros e relatórios básicos.

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

- `includes/Inbox/*`
- repositories de contatos, conversas e mensagens
- templates/telas de Inbox
- assets JS/CSS específicos do chat
- endpoints REST de conversas e leitura de mensagens
- componentes de lista, histórico, campo de resposta e status visual


### Não deve editar sem alinhamento

- Enviar mensagem diretamente para a Meta; deve usar `MessageDispatchService` do Dev 02.
- Processar webhook bruto diretamente; deve receber evento já roteado pelo `WebhookEventProcessor`.
- Criar tabelas novas sem solicitar migration ao Dev 01.
- Criar regras legais/compliance fora dos services do Dev 04.


## Contratos públicos que esta frente deve respeitar

- `ContactRepository`: único acesso a contatos.
- `ConversationRepository`: único acesso a conversas.
- `MessageRepository`: único acesso a mensagens.
- `InboundMessageService`: transforma payload normalizado em contato/conversa/mensagem.
- `ConversationQueryService`: lista conversas e histórico para a UI.
- `ConversationAssignmentService`: atribuição de conversas.
- `ConversationTagService`: tags de contatos/conversas.
- `InboxController`: apenas orquestra request/response; não decide regra.


## Handoff esperado ao terminar

- Mensagem inbound simulada cria contato, conversa e mensagem.
- Inbox lista conversas e histórico.
- Campo de resposta chama endpoint central de envio, sem falar direto com Meta.
- Erros e status aparecem no balão.
- Estados vazios e filtros básicos deixam o MVP navegável.
- Atribuição, tags e relatório básico ficam isolados em services próprios.


## Ordem recomendada de execução desta frente

### P0

- [ ] WAS-067 — Criar repository de contatos
- [ ] WAS-068 — Criar repository de conversas
- [ ] WAS-069 — Criar repository de mensagens
- [ ] WAS-070 — Processar mensagem inbound de texto
- [ ] WAS-071 — Evitar duplicidade de mensagem inbound
- [ ] WAS-073 — Criar página Inbox
- [ ] WAS-074 — Criar endpoint de listagem de conversas
- [ ] WAS-075 — Criar endpoint de leitura de uma conversa
- [ ] WAS-076 — Renderizar lista de conversas no Inbox
- [ ] WAS-077 — Renderizar histórico de mensagens
- [ ] WAS-078 — Criar campo de resposta no chat
- [ ] WAS-082 — Exibir erro de envio no chat
- [ ] WAS-127 — Testar recebimento de mensagem inbound simulada
### P1

- [ ] WAS-072 — Processar mensagens inbound de tipo desconhecido
- [ ] WAS-084 — Mostrar status no balão da mensagem
- [ ] WAS-133 — Criar estados vazios estratégicos
### P2

- [ ] WAS-138 — Criar atribuição de conversa
- [ ] WAS-139 — Criar tags de contato/conversa
- [ ] WAS-140 — Criar filtros avançados no Inbox
- [ ] WAS-141 — Criar relatórios básicos

---


## Tasks adicionais — contrato de chat com API oficial abstraída

O Dev 03 não chama a Meta diretamente. A experiência de chat conversa com endpoints internos do SaaS, que por baixo usam `MessageDispatchService` e `MetaApiClient`.

### Contrato interno de envio usado pelo Inbox

```txt
POST /wp-json/was/v1/conversations/{id}/messages
```

Payload interno:

```json
{
  "type": "text",
  "body": "Mensagem do atendente",
  "client_message_id": "uuid-local-opcional"
}
```

O backend deve transformar isso no payload oficial:

```txt
WA_SEND_MESSAGE -> POST /{version}/{PHONE_NUMBER_ID}/messages
```

### WAS-078A — Fazer o campo de resposta usar apenas endpoint interno

**Prioridade:** P0  
**Objetivo:** impedir que a UI do chat conheça token, phone number ID ou URL da Meta.  
**Entrega:** JS do campo de resposta chamando `/conversations/{id}/messages`.

**Critérios de aceite:**

- Nenhum endpoint `graph.facebook.com` aparece no JS.
- Nenhum token aparece no HTML/JS.
- Mensagem otimista pode aparecer no chat, mas status real vem do backend/webhook.
- Erro de envio aparece no balão sem expor erro bruto sensível.

**Depende de:** WAS-078, WAS-080.

---

### WAS-070A — Consumir payload normalizado do webhook, não payload bruto

**Prioridade:** P0  
**Objetivo:** garantir que Inbox não dependa diretamente do formato da Meta.  
**Entrega:** `InboundMessageService` recebe DTO normalizado pelo `WebhookEventProcessor`.

DTO esperado:

```php
[
  'tenant_id' => 1,
  'phone_number_id' => '123456',
  'wa_message_id' => 'wamid.xxx',
  'from' => '5511999999999',
  'profile_name' => 'Cliente',
  'type' => 'text',
  'text_body' => 'Olá',
  'timestamp' => 1710000000,
  'raw_event_id' => 99,
]
```

**Critérios de aceite:**

- `InboundMessageService` não lê `entry[0].changes[0]` diretamente.
- Duplicidade é verificada por `wa_message_id`.
- Contato/conversa/mensagem são criados com `tenant_id` resolvido no backend.

**Depende de:** WAS-063, WAS-064, WAS-070.

---

### WAS-084A — Exibir status vindo da tabela local

**Prioridade:** P1  
**Objetivo:** o chat deve renderizar status local, não consultar a Meta para cada balão.  
**Entrega:** status visual baseado em `was_messages.status`.

**Critérios de aceite:**

- Suporta `created`, `sent`, `delivered`, `read`, `failed`.
- Tooltip de falha usa mensagem sanitizada do backend.
- Não faz polling na Graph API.

**Depende de:** WAS-083A, WAS-084.

---

### WAS-127A — Criar fixture de webhook inbound oficial para teste local

**Prioridade:** P0  
**Objetivo:** permitir testar Inbox sem depender de evento real da Meta a cada alteração.  
**Entrega:** arquivo JSON de fixture simulando webhook de mensagem inbound.

**Critérios de aceite:**

- Fixture fica em `tests/fixtures/meta-webhooks/inbound-text.json`.
- Contém estrutura próxima da Meta: `object`, `entry`, `changes`, `value`, `metadata`, `contacts`, `messages`.
- Teste chama o processador e valida contato, conversa e mensagem criados.

**Depende de:** WAS-060, WAS-070.
---

# Tasks atribuídas

## WAS-067 — Criar repository de contatos

**Prioridade:** P0  
**Objetivo:** centralizar criação/busca de contatos.  
**Entrega:** classe `ContactRepository`.

**Critérios de aceite:**

- Possui método `findOrCreateByWaId`.
- Atualiza `profile_name` quando recebido.
- Sempre filtra por tenant.

**Depende de:** WAS-017.

---

---

## WAS-068 — Criar repository de conversas

**Prioridade:** P0  
**Objetivo:** centralizar criação/busca de conversas.  
**Entrega:** classe `ConversationRepository`.

**Critérios de aceite:**

- Possui método `findOrCreateOpenConversation`.
- Atualiza `last_message_at`.
- Sempre filtra por tenant.

**Depende de:** WAS-019.

---

---

## WAS-069 — Criar repository de mensagens

**Prioridade:** P0  
**Objetivo:** centralizar gravação e leitura de mensagens.  
**Entrega:** classe `MessageRepository`.

**Critérios de aceite:**

- Possui método `createInbound`.
- Possui método `createOutbound`.
- Possui método `findByWaMessageId`.
- Sempre filtra por tenant.

**Depende de:** WAS-020.

---

---

## WAS-070 — Processar mensagem inbound de texto

**Prioridade:** P0  
**Objetivo:** transformar webhook de mensagem recebida em contato/conversa/mensagem.  
**Entrega:** handler para mensagem inbound do tipo `text`.

**Critérios de aceite:**

- Cria ou atualiza contato.
- Cria ou atualiza conversa aberta.
- Salva mensagem com `direction = inbound`.
- Atualiza preview da conversa.

**Depende de:** WAS-065, WAS-067, WAS-068, WAS-069.

---

---

## WAS-071 — Evitar duplicidade de mensagem inbound

**Prioridade:** P0  
**Objetivo:** impedir mensagens duplicadas quando webhook repetir.  
**Entrega:** validação por `wa_message_id`.

**Critérios de aceite:**

- Se `wa_message_id` já existir, não cria nova mensagem.
- Evento duplicado é marcado como processado sem erro crítico.
- Audit/log registra duplicidade quando necessário.

**Depende de:** WAS-070.

---

---

## WAS-072 — Processar mensagens inbound de tipo desconhecido

**Prioridade:** P1  
**Objetivo:** não quebrar quando chegar formato ainda não tratado.  
**Entrega:** fallback `message_type = unknown`.

**Critérios de aceite:**

- Payload é salvo na mensagem.
- Conversa é atualizada.
- UI mostra aviso “tipo de mensagem não suportado”.

**Depende de:** WAS-070.

---

# EPIC 09 — Inbox e chat

---

## WAS-073 — Criar página Inbox

**Prioridade:** P0  
**Objetivo:** exibir conversas do tenant.  
**Entrega:** página `/app/inbox`.

**Critérios de aceite:**

- Página exige `was_view_inbox`.
- Mostra estado vazio quando não há conversas.
- Usa layout com lista de conversas e área de mensagens.

**Depende de:** WAS-037, WAS-068.

---

---

## WAS-074 — Criar endpoint de listagem de conversas

**Prioridade:** P0  
**Objetivo:** alimentar coluna de conversas.  
**Entrega:** `GET /wp-json/was/v1/conversations`.

**Critérios de aceite:**

- Retorna conversas do tenant atual.
- Ordena por `last_message_at DESC`.
- Não retorna conversas de outro tenant.

**Depende de:** WAS-042, WAS-068.

---

---

## WAS-075 — Criar endpoint de leitura de uma conversa

**Prioridade:** P0  
**Objetivo:** abrir histórico de uma conversa.  
**Entrega:** `GET /wp-json/was/v1/conversations/{id}`.

**Critérios de aceite:**

- Retorna dados da conversa, contato e mensagens.
- Valida que a conversa pertence ao tenant atual.
- Retorna 404/403 adequadamente.

**Depende de:** WAS-042, WAS-069.

---

---

## WAS-076 — Renderizar lista de conversas no Inbox

**Prioridade:** P0  
**Objetivo:** exibir conversas reais na interface.  
**Entrega:** componente/listagem de conversas.

**Critérios de aceite:**

- Mostra nome/telefone do contato.
- Mostra preview da última mensagem.
- Mostra data/hora da última mensagem.

**Depende de:** WAS-073, WAS-074.

---

---

## WAS-077 — Renderizar histórico de mensagens

**Prioridade:** P0  
**Objetivo:** mostrar chat da conversa selecionada.  
**Entrega:** componente de mensagens.

**Critérios de aceite:**

- Diferencia visualmente inbound e outbound.
- Mostra horário da mensagem.
- Mostra status quando disponível.

**Depende de:** WAS-075, WAS-076.

---

---

## WAS-078 — Criar campo de resposta no chat

**Prioridade:** P0  
**Objetivo:** permitir digitar mensagem outbound.  
**Entrega:** textarea/input e botão enviar.

**Critérios de aceite:**

- Campo aparece apenas para quem tem `was_send_messages`.
- Botão fica bloqueado sem conversa selecionada.
- Campo impede envio vazio.

**Depende de:** WAS-077.

---

# EPIC 10 — Envio de mensagens

---

## WAS-082 — Exibir erro de envio no chat

**Prioridade:** P0  
**Objetivo:** tornar falhas visíveis para o operador.  
**Entrega:** feedback visual quando a Cloud API retornar erro.

**Critérios de aceite:**

- Mostra mensagem amigável.
- Salva `error_code` e `error_message`.
- Não cria mensagem como enviada se API falhar.

**Depende de:** WAS-081.

---

---

## WAS-084 — Mostrar status no balão da mensagem

**Prioridade:** P1  
**Objetivo:** dar retorno visual ao atendente.  
**Entrega:** status no componente de mensagem outbound.

**Critérios de aceite:**

- Mostra enviada, entregue, lida ou erro.
- Atualiza ao recarregar conversa.
- Não exibe status em mensagens inbound.

**Depende de:** WAS-083, WAS-077.

---

---

## WAS-127 — Testar recebimento de mensagem inbound simulada

**Prioridade:** P0  
**Objetivo:** validar fluxo webhook → contato → conversa → mensagem.  
**Entrega:** teste com payload exemplo.

**Critérios de aceite:**

- Evento bruto é salvo.
- Contato é criado.
- Conversa é criada.
- Mensagem aparece no Inbox.

**Depende de:** WAS-070, WAS-073.

---

---

## WAS-133 — Criar estados vazios estratégicos

**Prioridade:** P1  
**Objetivo:** orientar usuário em telas sem dados.  
**Entrega:** textos e CTAs em dashboard, inbox, templates e setup.

**Critérios de aceite:**

- Inbox vazio orienta conectar WhatsApp ou aguardar mensagens.
- Templates vazio orienta criar primeiro template.
- Setup vazio orienta conectar conta WhatsApp Business.

**Depende de:** WAS-038, WAS-050, WAS-073, WAS-087.

---

---

## WAS-138 — Criar atribuição de conversa

**Prioridade:** P2  
**Objetivo:** permitir distribuir atendimento.  
**Entrega:** ação de atribuir conversa a atendente.

**Critérios de aceite:**

- Manager pode atribuir conversa.
- Agent vê conversas atribuídas.
- Audit log registra alteração.

**Depende de:** WAS-068, WAS-073.

---

---

## WAS-139 — Criar tags de contato/conversa

**Prioridade:** P2  
**Objetivo:** organizar atendimento.  
**Entrega:** sistema simples de tags.

**Critérios de aceite:**

- Tags podem ser adicionadas ao contato.
- Tags aparecem na conversa.
- Filtro por tag funciona.

**Depende de:** WAS-017, WAS-073.

---

---

## WAS-140 — Criar filtros avançados no Inbox

**Prioridade:** P2  
**Objetivo:** melhorar operação diária.  
**Entrega:** filtros por status, atendente, tag e data.

**Critérios de aceite:**

- Filtros alteram lista de conversas.
- Filtros respeitam tenant atual.
- Estado vazio aparece quando nada é encontrado.

**Depende de:** WAS-074, WAS-139.

---

---

## WAS-141 — Criar relatórios básicos

**Prioridade:** P2  
**Objetivo:** mostrar performance da operação.  
**Entrega:** página de relatórios.

**Critérios de aceite:**

- Mostra mensagens enviadas/recebidas por período.
- Mostra conversas abertas/fechadas.
- Mostra erros de envio.

**Depende de:** WAS-020, WAS-021, WAS-019.

---
