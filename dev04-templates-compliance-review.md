# DEV 04 — Templates, Logs, Compliance, Páginas Legais, Demo e App Review

**Responsável:** Dev 04 / Templates, Compliance & Review

**Missão:** Fechar a parte que convence a Meta e protege o produto: templates oficiais, logs, auditoria, páginas legais, opt-in, exportação/exclusão de dados, ambiente demo e checklist de review.

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

- services/repositories de templates
- páginas e endpoints de logs/auditoria
- gerador de páginas legais
- endpoints de compliance de dados
- scripts/checklists de App Review
- tenant/usuário demo
- testes finais de MVP e checklist de aprovação


### Não deve editar sem alinhamento

- Mudar estrutura de tabelas sem pedir migration ao Dev 01.
- Enviar mensagens diretamente; deve usar services do Dev 02.
- Alterar funcionamento do Inbox sem alinhar com Dev 03.
- Colocar regras legais espalhadas nas telas; devem ficar em `ComplianceService`, `LegalPageService` e policies.


## Contratos públicos que esta frente deve respeitar

- `TemplateService`: única regra para criar/sincronizar templates.
- `TemplateRepository`: único acesso à tabela de templates.
- `AuditLogService`: único lugar para registrar ações sensíveis.
- `LegalPageService`: único lugar para criar/atualizar páginas legais.
- `DataPrivacyService`: exportação, anonimização e exclusão de dados.
- `OptInService`: registro e consulta de consentimento.
- `ReviewChecklistService`: material de apoio para App Review.


## Handoff esperado ao terminar

- Templates são listados, criados e sincronizados.
- Logs de webhook e auditoria aparecem no painel.
- Páginas públicas legais existem e estão acessíveis.
- Exportação/exclusão/anonimização de contato funcionam.
- Tenant e usuário demo estão prontos.
- Checklists e textos de justificativa para Meta Review estão finalizados.
- Testes finais do MVP validam as quatro frentes integradas.


## Ordem recomendada de execução desta frente

### P0

- [ ] WAS-086 — Criar repository de templates
- [ ] WAS-087 — Criar página de Templates
- [ ] WAS-088 — Criar endpoint de listagem de templates
- [ ] WAS-089 — Criar formulário de criação de template
- [ ] WAS-090 — Criar serviço de criação de template na Meta
- [ ] WAS-091 — Criar endpoint de criação de template
- [ ] WAS-092 — Exibir template criado na lista
- [ ] WAS-093 — Criar sincronização de templates da Meta
- [ ] WAS-094 — Criar envio de mensagem com template
- [ ] WAS-098 — Criar página de Logs
- [ ] WAS-099 — Criar endpoint de webhook events
- [ ] WAS-100 — Criar endpoint de audit logs
- [ ] WAS-101 — Renderizar logs de webhooks
- [ ] WAS-102 — Renderizar audit logs
- [ ] WAS-103 — Criar gerador de páginas legais
- [ ] WAS-104 — Criar página de Política de Privacidade
- [ ] WAS-105 — Criar página de Termos de Serviço
- [ ] WAS-106 — Criar página de Exclusão de Dados
- [ ] WAS-107 — Criar página de Uso Aceitável
- [ ] WAS-109 — Criar página de Contato
- [ ] WAS-119 — Criar tenant demo para revisão
- [ ] WAS-120 — Criar usuário demo para Meta Review
- [ ] WAS-121 — Criar checklist visual de Messaging Review
- [ ] WAS-122 — Criar checklist visual de Management Review
- [ ] WAS-123 — Criar textos finais de justificativa das permissões
- [ ] WAS-126 — Testar verificação do webhook
- [ ] WAS-128 — Testar envio de mensagem texto
- [ ] WAS-129 — Testar atualização de status por webhook
- [ ] WAS-130 — Testar criação de template
- [ ] WAS-131 — Testar sincronização de templates
- [ ] WAS-132 — Testar páginas legais públicas
### P1

- [ ] WAS-108 — Criar página de Segurança
- [ ] WAS-110 — Criar endpoint de exportação de contato
- [ ] WAS-111 — Criar endpoint de exclusão/anomização de contato
- [ ] WAS-112 — Criar registro manual de opt-in

---


## Tasks adicionais — templates oficiais, compliance e App Review

Esta frente precisa provar para a Meta que a plataforma gerencia ativos oficiais da WABA, principalmente templates. Toda operação externa deve usar `TemplateService -> MetaApiClient`.

### Contrato oficial de templates

```txt
WA_LIST_TEMPLATES
GET /{version}/{WABA_ID}/message_templates

WA_CREATE_TEMPLATE
POST /{version}/{WABA_ID}/message_templates

WA_GET_TEMPLATE
GET /{version}/{TEMPLATE_ID}

WA_EDIT_TEMPLATE
POST /{version}/{TEMPLATE_ID}

WA_DELETE_TEMPLATE
DELETE /{version}/{WABA_ID}/message_templates?name={NAME}&hsm_id={TEMPLATE_ID}
```

### Payload base para criar template utilitário simples

```json
{
  "name": "order_update_test",
  "language": "pt_BR",
  "category": "UTILITY",
  "components": [
    {
      "type": "BODY",
      "text": "Olá {{1}}, seu pedido {{2}} foi atualizado."
    }
  ]
}
```

### Payload base para enviar template

O envio de template usa o mesmo endpoint de mensagens:

```txt
WA_SEND_MESSAGE
POST /{version}/{PHONE_NUMBER_ID}/messages
```

```json
{
  "messaging_product": "whatsapp",
  "to": "5511999999999",
  "type": "template",
  "template": {
    "name": "order_update_test",
    "language": {
      "code": "pt_BR"
    },
    "components": [
      {
        "type": "body",
        "parameters": [
          { "type": "text", "text": "Alex" },
          { "type": "text", "text": "#1234" }
        ]
      }
    ]
  }
}
```

### WAS-090A — Implementar criação oficial de template via Meta

**Prioridade:** P0  
**Objetivo:** criar template real na WABA conectada usando a API oficial.  
**Endpoint oficial:** `POST /{WABA_ID}/message_templates`.  
**Operação interna:** `WA_CREATE_TEMPLATE`.

**Critérios de aceite:**

- `TemplateService` monta payload oficial.
- `TemplateRepository` salva versão local como `pending` ou status retornado.
- UI não envia JSON bruto livre para a Meta; usa campos controlados.
- Erros da Meta são normalizados para UI.

**Depende de:** WAS-086, WAS-089, WAS-049E.

---

### WAS-093A — Implementar sincronização oficial de templates

**Prioridade:** P0  
**Objetivo:** buscar templates existentes na WABA e atualizar banco local.  
**Endpoint oficial:** `GET /{WABA_ID}/message_templates`.  
**Operação interna:** `WA_LIST_TEMPLATES`.

**Critérios de aceite:**

- Sincroniza `name`, `language`, `category`, `status`, `components_json` e `meta_template_id` quando disponível.
- Suporta paginação quando a Meta retornar `paging.next`/cursors.
- Não duplica template por `tenant_id + name + language`.

**Depende de:** WAS-086, WAS-093.

---

### WAS-094A — Enviar mensagem com template aprovado

**Prioridade:** P0  
**Objetivo:** permitir que o SaaS envie mensagem template pela API oficial.  
**Endpoint oficial:** `POST /{PHONE_NUMBER_ID}/messages`.  
**Operação interna:** `WA_SEND_MESSAGE` com `type=template`.

**Critérios de aceite:**

- Só permite template local com status `APPROVED`/`approved`.
- Usa `MessageDispatchService`, não chamada direta à Meta.
- Salva outbound em `was_messages` com `template_id`.
- Útil para vídeo do App Review de `whatsapp_business_messaging`.

**Depende de:** WAS-094, WAS-079A.

---

### WAS-091A — Criar endpoint interno para criação de template controlado

**Prioridade:** P0  
**Objetivo:** expor criação de template ao painel sem deixar a UI controlar payload bruto.  
**Endpoint interno:** `POST /wp-json/was/v1/templates`.

Payload interno esperado:

```json
{
  "name": "order_update_test",
  "language": "pt_BR",
  "category": "UTILITY",
  "body_text": "Olá {{1}}, seu pedido {{2}} foi atualizado.",
  "sample_values": ["Alex", "#1234"]
}
```

**Critérios de aceite:**

- Sanitiza nome para padrão aceito: minúsculas, números e underscores.
- Valida categoria permitida.
- Valida variáveis sequenciais `{{1}}`, `{{2}}`.
- Converte payload interno para payload oficial no `TemplateService`.

**Depende de:** WAS-091, WAS-090A.

---

### WAS-121A — Criar roteiro técnico de App Review com endpoints usados

**Prioridade:** P0  
**Objetivo:** documentar exatamente quais endpoints serão demonstrados no vídeo de revisão.  
**Entrega:** seção/checklist na página interna de Review.

**Critérios de aceite:**

- Para `whatsapp_business_messaging`, mostra: `POST /{PHONE_NUMBER_ID}/messages`.
- Para `whatsapp_business_management`, mostra: `GET/POST /{WABA_ID}/message_templates` e `POST /{WABA_ID}/subscribed_apps` quando aplicável.
- Checklist mostra telas internas correspondentes: Setup, Inbox/Send Message, Templates, Logs.
- Não menciona WordPress/plugin para o revisor; posiciona como SaaS.

**Depende de:** WAS-121, WAS-122, WAS-123.
---

# Tasks atribuídas

## WAS-086 — Criar repository de templates

**Prioridade:** P0  
**Objetivo:** centralizar templates locais.  
**Entrega:** classe `TemplateRepository`.

**Critérios de aceite:**

- Possui métodos `createOrUpdate`, `listByTenant`, `find`.
- Sempre filtra por tenant.
- Usa queries preparadas.

**Depende de:** WAS-022.

---

---

## WAS-087 — Criar página de Templates

**Prioridade:** P0  
**Objetivo:** demonstrar gestão de templates no App Review.  
**Entrega:** página `/app/templates`.

**Critérios de aceite:**

- Página exige `was_manage_templates`.
- Mostra lista vazia quando não há templates.
- Possui botão “Create Template”.

**Depende de:** WAS-037, WAS-086.

---

---

## WAS-088 — Criar endpoint de listagem de templates

**Prioridade:** P0  
**Objetivo:** alimentar a tela de templates.  
**Entrega:** `GET /wp-json/was/v1/templates`.

**Critérios de aceite:**

- Retorna templates do tenant atual.
- Permite filtro por status.
- Não retorna templates de outro tenant.

**Depende de:** WAS-042, WAS-086.

---

---

## WAS-089 — Criar formulário de criação de template

**Prioridade:** P0  
**Objetivo:** permitir criar template pela interface.  
**Entrega:** modal/página com campos mínimos.

**Critérios de aceite:**

- Campos: nome, idioma, categoria, corpo da mensagem.
- Valida nome em formato aceito internamente.
- Não envia ainda para Meta nesta task.

**Depende de:** WAS-087.

---

---

## WAS-090 — Criar serviço de criação de template na Meta

**Prioridade:** P0  
**Objetivo:** enviar novo template para a Graph API.  
**Entrega:** método em `TemplateService`.

**Critérios de aceite:**

- Monta payload de template básico.
- Usa WABA conectada do tenant.
- Retorna sucesso/erro padronizado.

**Depende de:** WAS-049, WAS-053, WAS-086.

---

---

## WAS-091 — Criar endpoint de criação de template

**Prioridade:** P0  
**Objetivo:** criar template via SaaS.  
**Entrega:** `POST /wp-json/was/v1/templates`.

**Critérios de aceite:**

- Exige `was_manage_templates`.
- Valida campos obrigatórios.
- Chama serviço da Meta.
- Salva template localmente.

**Depende de:** WAS-042, WAS-090.

---

---

## WAS-092 — Exibir template criado na lista

**Prioridade:** P0  
**Objetivo:** completar demonstração visual para App Review.  
**Entrega:** template aparece na tela após criação.

**Critérios de aceite:**

- Lista mostra nome, idioma, categoria e status.
- Status inicial é salvo.
- Erros aparecem de forma amigável.

**Depende de:** WAS-088, WAS-091.

---

---

## WAS-093 — Criar sincronização de templates da Meta

**Prioridade:** P0  
**Objetivo:** buscar templates existentes na WABA.  
**Entrega:** botão/endpoint `POST /wp-json/was/v1/templates/sync`.

**Critérios de aceite:**

- Busca templates da WABA conectada.
- Atualiza/cria registros locais.
- Gera audit log `sync_templates`.

**Depende de:** WAS-049, WAS-086.

---

---

## WAS-094 — Criar envio de mensagem com template

**Prioridade:** P0  
**Objetivo:** permitir mensagens fora do texto livre usando template.  
**Entrega:** `POST /wp-json/was/v1/messages/send-template`.

**Critérios de aceite:**

- Exige `was_send_messages`.
- Valida template pertence ao tenant.
- Chama Cloud API com payload de template.
- Salva mensagem outbound como `message_type = template`.

**Depende de:** WAS-079, WAS-086, WAS-091.

---

# EPIC 12 — Mídia

---

## WAS-098 — Criar página de Logs

**Prioridade:** P0  
**Objetivo:** exibir eventos técnicos necessários para suporte e App Review.  
**Entrega:** página `/app/logs`.

**Critérios de aceite:**

- Página exige `was_view_logs`.
- Possui abas: Webhooks, Mensagens, Auditoria, Erros.
- Estado vazio é amigável.

**Depende de:** WAS-037, WAS-039.

---

---

## WAS-099 — Criar endpoint de webhook events

**Prioridade:** P0  
**Objetivo:** listar webhooks recebidos.  
**Entrega:** `GET /wp-json/was/v1/webhook-events`.

**Critérios de aceite:**

- Filtra por tenant atual quando houver tenant no evento.
- Permite filtro por status.
- Não expõe payload completo por padrão.

**Depende de:** WAS-024, WAS-042.

---

---

## WAS-100 — Criar endpoint de audit logs

**Prioridade:** P0  
**Objetivo:** listar ações sensíveis.  
**Entrega:** `GET /wp-json/was/v1/audit-logs`.

**Critérios de aceite:**

- Exige permissão de logs/compliance.
- Filtra por tenant atual.
- Permite filtro por action.

**Depende de:** WAS-025, WAS-042.

---

---

## WAS-101 — Renderizar logs de webhooks

**Prioridade:** P0  
**Objetivo:** mostrar eventos recebidos pela Meta.  
**Entrega:** tabela de webhooks na tela Logs.

**Critérios de aceite:**

- Mostra data, tipo, status, assinatura e tenant.
- Permite abrir resumo do payload.
- Não mostra token/secret.

**Depende de:** WAS-098, WAS-099.

---

---

## WAS-102 — Renderizar audit logs

**Prioridade:** P0  
**Objetivo:** mostrar trilha de auditoria.  
**Entrega:** tabela de auditoria.

**Critérios de aceite:**

- Mostra data, usuário, ação e entidade.
- Filtra por action.
- Não mostra metadata sensível integral por padrão.

**Depende de:** WAS-098, WAS-100.

---

# EPIC 14 — Páginas legais e aprovação Meta

---

## WAS-103 — Criar gerador de páginas legais

**Prioridade:** P0  
**Objetivo:** criar páginas públicas necessárias para App Review.  
**Entrega:** classe `LegalPagesGenerator`.

**Critérios de aceite:**

- Cria páginas apenas se ainda não existirem.
- Salva slugs previsíveis.
- Não sobrescreve conteúdo editado manualmente.

**Depende de:** WAS-007.

---

---

## WAS-104 — Criar página de Política de Privacidade

**Prioridade:** P0  
**Objetivo:** fornecer URL exigida pela Meta.  
**Entrega:** página `/privacy-policy`.

**Critérios de aceite:**

- Explica coleta de dados, mensagens, tokens, webhooks e contatos.
- Informa canal de contato.
- Link é público e acessível sem login.

**Depende de:** WAS-103.

---

---

## WAS-105 — Criar página de Termos de Serviço

**Prioridade:** P0  
**Objetivo:** definir regras de uso da plataforma.  
**Entrega:** página `/terms-of-service`.

**Critérios de aceite:**

- Proíbe spam, phishing, listas compradas e abuso.
- Explica responsabilidade do cliente sobre consentimento.
- Link é público e acessível sem login.

**Depende de:** WAS-103.

---

---

## WAS-106 — Criar página de Exclusão de Dados

**Prioridade:** P0  
**Objetivo:** atender exigência de remoção de dados.  
**Entrega:** página `/data-deletion`.

**Critérios de aceite:**

- Explica como solicitar exclusão.
- Informa e-mail ou formulário de contato.
- Explica prazo de processamento.

**Depende de:** WAS-103.

---

---

## WAS-107 — Criar página de Uso Aceitável

**Prioridade:** P0  
**Objetivo:** demonstrar compliance contra abuso.  
**Entrega:** página `/acceptable-use-policy`.

**Critérios de aceite:**

- Proíbe envio sem consentimento.
- Proíbe automação abusiva.
- Proíbe conteúdo ilegal/enganoso.

**Depende de:** WAS-103.

---

---

## WAS-108 — Criar página de Segurança

**Prioridade:** P1  
**Objetivo:** reforçar confiança da plataforma.  
**Entrega:** página `/security`.

**Critérios de aceite:**

- Explica criptografia de tokens.
- Explica controle de acesso.
- Explica logs de auditoria.

**Depende de:** WAS-103.

---

---

## WAS-109 — Criar página de Contato

**Prioridade:** P0  
**Objetivo:** fornecer canal oficial para suporte/review.  
**Entrega:** página `/contact`.

**Critérios de aceite:**

- Exibe e-mail de suporte.
- Exibe nome comercial da plataforma.
- Link é público.

**Depende de:** WAS-103.

---

# EPIC 15 — Compliance de dados

---

## WAS-110 — Criar endpoint de exportação de contato

**Prioridade:** P1  
**Objetivo:** permitir exportar dados de um contato.  
**Entrega:** `POST /wp-json/was/v1/contacts/{id}/export`.

**Critérios de aceite:**

- Exige permissão de compliance.
- Valida contato pertence ao tenant.
- Retorna dados principais, opt-ins e mensagens relacionadas.

**Depende de:** WAS-017, WAS-018, WAS-020, WAS-042.

---

---

## WAS-111 — Criar endpoint de exclusão/anomização de contato

**Prioridade:** P1  
**Objetivo:** permitir atendimento a solicitação de exclusão.  
**Entrega:** `POST /wp-json/was/v1/contacts/{id}/delete`.

**Critérios de aceite:**

- Exige permissão de compliance.
- Valida contato pertence ao tenant.
- Anonimiza ou remove dados pessoais conforme configuração.
- Gera audit log `delete_contact_data`.

**Depende de:** WAS-017, WAS-025, WAS-042.

---

---

## WAS-112 — Criar registro manual de opt-in

**Prioridade:** P1  
**Objetivo:** permitir que empresa registre consentimento de contato.  
**Entrega:** `POST /wp-json/was/v1/contacts/{id}/opt-in`.

**Critérios de aceite:**

- Exige permissão adequada.
- Salva origem, texto de consentimento e data.
- Atualiza `opt_in_status` do contato.

**Depende de:** WAS-018, WAS-042.

---

# EPIC 16 — Segurança operacional

---

## WAS-119 — Criar tenant demo para revisão

**Prioridade:** P0  
**Objetivo:** preparar ambiente limpo para gravar vídeo e revisor testar.  
**Entrega:** tenant `demo-review`.

**Critérios de aceite:**

- Tenant possui nome claro.
- Usuário demo é vinculado.
- Ambiente não usa dados reais de clientes.

**Depende de:** WAS-011, WAS-012.

---

---

## WAS-120 — Criar usuário demo para Meta Review

**Prioridade:** P0  
**Objetivo:** fornecer acesso seguro ao revisor se necessário.  
**Entrega:** usuário com role limitada.

**Critérios de aceite:**

- Usuário consegue acessar dashboard, setup, templates e inbox.
- Usuário não acessa configurações sensíveis globais.
- Senha pode ser alterada antes do envio.

**Depende de:** WAS-119, WAS-029.

---

---

## WAS-121 — Criar checklist visual de Messaging Review

**Prioridade:** P0  
**Objetivo:** garantir que vídeo de `whatsapp_business_messaging` tenha tudo.  
**Entrega:** checklist interno.

**Critérios de aceite:**

- Inclui login, número conectado, envio de mensagem, recebimento no WhatsApp e log.
- Checklist fica acessível para equipe interna.
- Não aparece para usuários finais.

**Depende de:** WAS-038, WAS-050, WAS-073, WAS-080, WAS-098.

---

---

## WAS-122 — Criar checklist visual de Management Review

**Prioridade:** P0  
**Objetivo:** garantir que vídeo de `whatsapp_business_management` tenha tudo.  
**Entrega:** checklist interno.

**Critérios de aceite:**

- Inclui login, WABA conectada, número conectado, tela de templates e criação/sync de template.
- Checklist fica acessível para equipe interna.
- Não aparece para usuários finais.

**Depende de:** WAS-050, WAS-087, WAS-091, WAS-093.

---

---

## WAS-123 — Criar textos finais de justificativa das permissões

**Prioridade:** P0  
**Objetivo:** preparar copy para App Review.  
**Entrega:** arquivo interno com justificativas.

**Critérios de aceite:**

- Texto para `whatsapp_business_messaging` existe em inglês.
- Texto para `whatsapp_business_management` existe em inglês.
- Texto evita termos como disparo em massa, scraping ou WhatsApp Web.

**Depende de:** WAS-121, WAS-122.

---

# EPIC 18 — Testes mínimos de MVP

---

## WAS-126 — Testar verificação do webhook

**Prioridade:** P0  
**Objetivo:** validar endpoint GET da Meta.  
**Entrega:** teste com query params simulados.

**Critérios de aceite:**

- Token correto retorna challenge.
- Token errado retorna 403.
- Resultado é registrado quando necessário.

**Depende de:** WAS-059.

---

---

## WAS-128 — Testar envio de mensagem texto

**Prioridade:** P0  
**Objetivo:** validar envio pela API oficial.  
**Entrega:** envio real em ambiente de teste.

**Critérios de aceite:**

- Mensagem é enviada pela Cloud API.
- Mensagem aparece no WhatsApp de destino.
- Mensagem aparece no Inbox como outbound.
- Log registra tentativa.

**Depende de:** WAS-080, WAS-081, WAS-085.

---

---

## WAS-129 — Testar atualização de status por webhook

**Prioridade:** P0  
**Objetivo:** validar entregue/lido/falha.  
**Entrega:** teste com payload de status.

**Critérios de aceite:**

- Status é salvo em `was_message_statuses`.
- Mensagem original é atualizada.
- UI mostra status atualizado após reload.

**Depende de:** WAS-083, WAS-084.

---

---

## WAS-130 — Testar criação de template

**Prioridade:** P0  
**Objetivo:** validar permissão de management na prática.  
**Entrega:** criação real ou em ambiente de teste.

**Critérios de aceite:**

- Template é enviado para Meta.
- Template é salvo localmente.
- Template aparece na lista.
- Erros são exibidos claramente.

**Depende de:** WAS-091, WAS-092.

---

---

## WAS-131 — Testar sincronização de templates

**Prioridade:** P0  
**Objetivo:** validar leitura dos templates da WABA.  
**Entrega:** sync manual.

**Critérios de aceite:**

- Templates existentes são importados.
- Status local é atualizado.
- Duplicatas não são criadas.

**Depende de:** WAS-093.

---

---

## WAS-132 — Testar páginas legais públicas

**Prioridade:** P0  
**Objetivo:** garantir que URLs exigidas existem.  
**Entrega:** validação manual das páginas.

**Critérios de aceite:**

- `/privacy-policy` abre sem login.
- `/terms-of-service` abre sem login.
- `/data-deletion` abre sem login.
- `/acceptable-use-policy` abre sem login.
- `/contact` abre sem login.

**Depende de:** WAS-104, WAS-105, WAS-106, WAS-107, WAS-109.

---

# EPIC 19 — Refinamento para MVP navegável
