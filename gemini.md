# GEMINI.md — Briefing Mestre para Codar o WhatsApp SaaS Core no WordPress

> Este arquivo deve ser usado como contexto principal para a IA que vai codar o projeto.  
> Objetivo: transformar o WordPress em uma plataforma SaaS multiempresa integrada à API oficial do WhatsApp Business Platform, com chat, templates, webhook, logs, compliance e estrutura pronta para App Review da Meta.

---

## 1. Objetivo do Projeto

Criar um plugin WordPress chamado, provisoriamente:

```txt
whatsapp-saas-core
```

O plugin deve transformar o WordPress em um SaaS operacional para WhatsApp Business Platform, permitindo que empresas façam login, conectem uma conta WhatsApp Business oficial, gerenciem número/WABA/templates, enviem mensagens, recebam webhooks, visualizem conversas em uma inbox e tenham páginas legais necessárias para aprovação de permissões da Meta.

O produto **não deve ser tratado como “plugin WordPress” na experiência externa**. Ele deve se comportar como uma plataforma web SaaS.

Posicionamento externo:

```txt
A SaaS platform that allows businesses to connect their official WhatsApp Business Account, manage message templates, configure webhooks, send customer messages, and monitor conversations through the official WhatsApp Business Platform.
```

---

## 2. Resultado Esperado

Ao final da implementação, o sistema deve ter:

```txt
1. Plugin WordPress instalável.
2. Tabelas próprias criadas na ativação.
3. Login customizado estilo SaaS.
4. Sistema multiempresa/tenant.
5. Usuários vinculados a empresas.
6. Permissões internas por papel.
7. Configuração do Meta App.
8. Token vault para tokens criptografados.
9. Endpoint de webhook público.
10. Webhook verificável pela Meta.
11. Recebimento de mensagens/status via webhook.
12. Envio de mensagens pela Cloud API.
13. Envio/listagem/upload de mídia.
14. Gestão de templates oficiais.
15. Inbox com contatos, conversas e mensagens.
16. Logs de API, webhooks e auditoria.
17. Páginas legais públicas.
18. Ambiente demo pronto para gravação de App Review.
```

---

## 3. Filosofia de Arquitetura

A manutenção deve ser fácil. Por isso, o projeto deve seguir **centralização de responsabilidade**.

### Regras obrigatórias

```txt
Controller não contém regra de negócio.
Template PHP não contém regra de negócio.
JavaScript não contém regra de negócio sensível.
Repository não decide regra de negócio; apenas lê/grava dados.
Toda regra fica em Service, Policy, Guard ou Orchestrator.
Todo acesso multiempresa passa por TenantContext e TenantGuard.
Toda chamada Meta/WhatsApp passa por MetaApiClient.
Toda URL da Meta passa por MetaEndpointRegistry.
Todo envio passa por MessageDispatchService.
Todo webhook entra por WebhookController, salva evento bruto e depois processa.
Tokens nunca são expostos ao frontend.
Tokens nunca são lidos fora de TokenVault/TokenService.
Nunca confiar em tenant_id vindo do frontend.
```

### Fluxo padrão de backend

```txt
REST Controller
↓
Request Validator
↓
TenantGuard / PermissionGuard
↓
Service / Orchestrator
↓
Repository ou MetaApiClient
↓
Response DTO
```

### Fluxo proibido

```txt
Template -> wp_remote_post Meta
Controller -> SQL direto com regra de negócio
JavaScript -> Graph API
Repository -> Graph API
Service -> URL hardcoded da Meta
Frontend -> token Meta
Frontend -> tenant_id confiável
```

---

## 4. Estrutura do Plugin

Criar a seguinte estrutura base:

```txt
wp-content/plugins/whatsapp-saas-core/
├── whatsapp-saas-core.php
├── uninstall.php
├── includes/
│   ├── Core/
│   │   ├── Activator.php
│   │   ├── Installer.php
│   │   ├── Migrator.php
│   │   ├── TableNameResolver.php
│   │   ├── Capabilities.php
│   │   └── Plugin.php
│   ├── Auth/
│   │   ├── LoginController.php
│   │   ├── TenantGuard.php
│   │   ├── PermissionGuard.php
│   │   ├── TenantContext.php
│   │   └── SessionService.php
│   ├── Tenants/
│   │   ├── TenantRepository.php
│   │   ├── TenantUserRepository.php
│   │   └── TenantService.php
│   ├── Meta/
│   │   ├── MetaEndpointRegistry.php
│   │   ├── MetaApiClient.php
│   │   ├── MetaApiResponse.php
│   │   ├── MetaApiException.php
│   │   ├── MetaApiRequestLogger.php
│   │   ├── MetaConfigService.php
│   │   ├── TokenVault.php
│   │   ├── TokenService.php
│   │   └── EmbeddedSignupController.php
│   ├── WhatsApp/
│   │   ├── AccountService.php
│   │   ├── PhoneNumberService.php
│   │   ├── MessageDispatchService.php
│   │   ├── MessagePayloadFactory.php
│   │   ├── MediaService.php
│   │   ├── WebhookController.php
│   │   ├── WebhookEventRecorder.php
│   │   ├── WebhookSignatureValidator.php
│   │   └── WebhookProcessor.php
│   ├── Inbox/
│   │   ├── ContactRepository.php
│   │   ├── ConversationRepository.php
│   │   ├── MessageRepository.php
│   │   ├── ConversationService.php
│   │   ├── ContactService.php
│   │   ├── AssignmentService.php
│   │   └── InboxQueryService.php
│   ├── Templates/
│   │   ├── TemplateRepository.php
│   │   ├── TemplateService.php
│   │   ├── TemplateSyncService.php
│   │   └── TemplatePayloadFactory.php
│   ├── Compliance/
│   │   ├── AuditLogger.php
│   │   ├── LegalPagesGenerator.php
│   │   ├── OptInService.php
│   │   ├── DataExportService.php
│   │   └── DataDeletionService.php
│   ├── Admin/
│   │   ├── Menu.php
│   │   ├── AssetLoader.php
│   │   ├── DashboardPage.php
│   │   ├── SettingsPage.php
│   │   ├── InboxPage.php
│   │   ├── TemplatesPage.php
│   │   └── LogsPage.php
│   └── REST/
│       ├── Routes.php
│       ├── AuthApiController.php
│       ├── TenantApiController.php
│       ├── MetaApiController.php
│       ├── WhatsAppApiController.php
│       ├── InboxApiController.php
│       ├── TemplateApiController.php
│       └── ComplianceApiController.php
├── assets/
│   ├── css/admin.css
│   ├── css/app.css
│   ├── js/admin.js
│   └── js/app.js
└── templates/
    ├── app-shell.php
    ├── login.php
    ├── dashboard.php
    ├── settings-whatsapp.php
    ├── inbox.php
    ├── templates.php
    ├── logs.php
    └── legal/
        ├── privacy-policy.php
        ├── terms-of-service.php
        ├── data-deletion.php
        ├── acceptable-use-policy.php
        ├── security.php
        └── contact.php
```

---

## 5. Convenções de Código

### Prefixos

Usar prefixo técnico:

```txt
WAS = WhatsApp SaaS
```

Namespaces PHP recomendados:

```php
WAS\Core
WAS\Auth
WAS\Meta
WAS\WhatsApp
WAS\Inbox
WAS\Templates
WAS\Compliance
WAS\REST
```

### Constantes

```php
define('WAS_PLUGIN_VERSION', '0.1.0');
define('WAS_PLUGIN_FILE', __FILE__);
define('WAS_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('WAS_PLUGIN_URL', plugin_dir_url(__FILE__));
define('WAS_META_GRAPH_BASE_URL', 'https://graph.facebook.com');
define('WAS_META_GRAPH_DEFAULT_VERSION', 'v25.0');
define('WAS_REST_NAMESPACE', 'was/v1');
```

A versão da Graph API deve ser configurável via banco, com fallback para `WAS_META_GRAPH_DEFAULT_VERSION`.

---

## 6. Banco de Dados

Criar tabelas próprias usando `$wpdb->prefix` e `dbDelta()`.

Nunca usar o prefixo fixo `wp_` no código final. Usar `TableNameResolver`.

### Tabelas obrigatórias

```txt
was_tenants
was_tenant_users
was_meta_apps
was_whatsapp_accounts
was_whatsapp_phone_numbers
was_meta_tokens
was_contacts
was_contact_optins
was_conversations
was_messages
was_message_statuses
was_message_templates
was_media
was_webhook_events
was_audit_logs
was_settings
```

### Regra multiempresa obrigatória

Toda tabela operacional deve ter `tenant_id`, exceto tabelas realmente globais como `was_meta_apps`.

Toda query de dados de cliente deve filtrar por `tenant_id` vindo de `TenantContext`, nunca do frontend.

### Padrão de timestamps

Usar:

```txt
created_at datetime NOT NULL
updated_at datetime NULL quando aplicável
```

Datas devem ser salvas em UTC usando funções WordPress adequadas.

---

## 7. Autenticação, Tenants e Permissões

### WordPress como base de usuário

Usar `wp_users` para login e identidade.

Criar tabela `was_tenant_users` para vincular usuário a empresa.

### Papéis internos

```txt
platform_owner
tenant_admin
manager
agent
viewer
compliance
```

### Capabilities internas

```txt
was_access_app
was_manage_tenant
was_manage_whatsapp
was_manage_templates
was_send_messages
was_view_inbox
was_assign_conversations
was_view_logs
was_manage_billing
was_manage_compliance
```

### Regra de acesso

Antes de qualquer ação privada:

```txt
1. Verificar login WordPress.
2. Resolver tenant atual.
3. Verificar vínculo usuário/tenant.
4. Verificar role/capability.
5. Executar ação.
```

---

## 8. REST API Interna do SaaS

Namespace:

```txt
/wp-json/was/v1
```

Todas as rotas privadas devem ter `permission_callback`.

### Rotas públicas

```txt
GET  /wp-json/was/v1/meta/webhook
POST /wp-json/was/v1/meta/webhook
GET  /wp-json/was/v1/legal/data-deletion
POST /wp-json/was/v1/legal/data-deletion
```

### Rotas autenticadas

```txt
GET  /wp-json/was/v1/me
GET  /wp-json/was/v1/tenants/current
POST /wp-json/was/v1/tenants
GET  /wp-json/was/v1/dashboard
```

### Rotas Meta/WhatsApp

```txt
GET  /wp-json/was/v1/meta/config
POST /wp-json/was/v1/meta/config
POST /wp-json/was/v1/meta/embedded-signup/exchange-code
POST /wp-json/was/v1/meta/embedded-signup/save-assets
POST /wp-json/was/v1/meta/disconnect
GET  /wp-json/was/v1/whatsapp/accounts
GET  /wp-json/was/v1/whatsapp/phone-numbers
POST /wp-json/was/v1/whatsapp/phone-numbers/default
```

### Rotas de chat

```txt
GET  /wp-json/was/v1/conversations
GET  /wp-json/was/v1/conversations/{id}
POST /wp-json/was/v1/conversations/{id}/messages
POST /wp-json/was/v1/messages/send-text
POST /wp-json/was/v1/messages/send-template
POST /wp-json/was/v1/messages/send-media
```

### Rotas de templates

```txt
GET    /wp-json/was/v1/templates
POST   /wp-json/was/v1/templates
GET    /wp-json/was/v1/templates/{id}
PUT    /wp-json/was/v1/templates/{id}
DELETE /wp-json/was/v1/templates/{id}
POST   /wp-json/was/v1/templates/sync
```

### Rotas de compliance/logs

```txt
GET  /wp-json/was/v1/audit-logs
GET  /wp-json/was/v1/webhook-events
POST /wp-json/was/v1/contacts/{id}/opt-in
POST /wp-json/was/v1/contacts/{id}/export
POST /wp-json/was/v1/contacts/{id}/delete
```

---

## 9. Abstração Oficial da Meta Graph API

Toda integração com a Meta deve passar por:

```txt
MetaApiClient
MetaEndpointRegistry
MetaApiRequestLogger
```

### Objetivo

O time deve chamar operações internas estáveis, e não endpoints soltos.

Exemplo:

```php
$metaApiClient->call('WA_SEND_TEXT_MESSAGE', [
    'phone_number_id' => $phoneNumberId,
    'access_token' => $token,
    'payload' => $payload,
]);
```

O `MetaEndpointRegistry` resolve isso para:

```txt
POST /{PHONE_NUMBER_ID}/messages
```

### Operações internas obrigatórias

```txt
WA_SEND_MESSAGE
WA_UPLOAD_MEDIA
WA_GET_MEDIA_URL
WA_DELETE_MEDIA
WA_LIST_TEMPLATES
WA_CREATE_TEMPLATE
WA_DELETE_TEMPLATE
WA_GET_WABA
WA_GET_PHONE_NUMBERS
WA_REGISTER_PHONE_NUMBER
WA_SUBSCRIBE_WABA_WEBHOOKS
WA_UNSUBSCRIBE_WABA_WEBHOOKS
WA_GET_SUBSCRIBED_APPS
META_EXCHANGE_CODE_FOR_TOKEN
META_DEBUG_TOKEN
META_REFRESH_TOKEN
```

### Mapa de endpoints Meta

Usar base:

```txt
https://graph.facebook.com/{GRAPH_VERSION}
```

Mapa:

```txt
WA_SEND_MESSAGE
POST /{PHONE_NUMBER_ID}/messages

WA_UPLOAD_MEDIA
POST /{PHONE_NUMBER_ID}/media

WA_GET_MEDIA_URL
GET /{MEDIA_ID}

WA_DELETE_MEDIA
DELETE /{MEDIA_ID}

WA_LIST_TEMPLATES
GET /{WABA_ID}/message_templates

WA_CREATE_TEMPLATE
POST /{WABA_ID}/message_templates

WA_DELETE_TEMPLATE
DELETE /{WABA_ID}/message_templates

WA_GET_WABA
GET /{WABA_ID}

WA_GET_PHONE_NUMBERS
GET /{WABA_ID}/phone_numbers

WA_REGISTER_PHONE_NUMBER
POST /{PHONE_NUMBER_ID}/register

WA_SUBSCRIBE_WABA_WEBHOOKS
POST /{WABA_ID}/subscribed_apps

WA_UNSUBSCRIBE_WABA_WEBHOOKS
DELETE /{WABA_ID}/subscribed_apps

WA_GET_SUBSCRIBED_APPS
GET /{WABA_ID}/subscribed_apps

META_EXCHANGE_CODE_FOR_TOKEN
GET /oauth/access_token

META_DEBUG_TOKEN
GET /debug_token
```

### Headers padrão

```txt
Authorization: Bearer {ACCESS_TOKEN}
Content-Type: application/json
```

Para upload de mídia, usar multipart/form-data quando necessário.

### Logging obrigatório

Cada request para Meta deve salvar log com:

```txt
tenant_id
operation
method
endpoint sem token
request_payload sanitizado
response_status
response_body sanitizado
error_code
error_message
duration_ms
created_at
```

Nunca salvar token em logs.

---

## 10. Payloads Mínimos de Teste

### Enviar mensagem de texto

Operação interna:

```txt
WA_SEND_MESSAGE
```

Endpoint real:

```txt
POST /{PHONE_NUMBER_ID}/messages
```

Payload:

```json
{
  "messaging_product": "whatsapp",
  "to": "5511999999999",
  "type": "text",
  "text": {
    "preview_url": false,
    "body": "Mensagem de teste enviada pela plataforma."
  }
}
```

### Enviar template

Operação interna:

```txt
WA_SEND_MESSAGE
```

Endpoint real:

```txt
POST /{PHONE_NUMBER_ID}/messages
```

Payload:

```json
{
  "messaging_product": "whatsapp",
  "to": "5511999999999",
  "type": "template",
  "template": {
    "name": "hello_world",
    "language": {
      "code": "en_US"
    }
  }
}
```

### Criar template simples

Operação interna:

```txt
WA_CREATE_TEMPLATE
```

Endpoint real:

```txt
POST /{WABA_ID}/message_templates
```

Payload:

```json
{
  "name": "order_update_test",
  "language": "pt_BR",
  "category": "UTILITY",
  "components": [
    {
      "type": "BODY",
      "text": "Olá {{1}}, seu pedido {{2}} recebeu uma atualização."
    }
  ]
}
```

### Upload de mídia

Operação interna:

```txt
WA_UPLOAD_MEDIA
```

Endpoint real:

```txt
POST /{PHONE_NUMBER_ID}/media
```

Campos mínimos:

```txt
messaging_product=whatsapp
file=@arquivo
```

---

## 11. Webhook

### Endpoint público

```txt
GET  /wp-json/was/v1/meta/webhook
POST /wp-json/was/v1/meta/webhook
```

### Verificação GET

Validar:

```txt
hub.mode
hub.verify_token
hub.challenge
```

Se `hub.verify_token` for igual ao token salvo, retornar `hub.challenge`.

### Recebimento POST

Fluxo obrigatório:

```txt
1. Receber payload bruto.
2. Validar assinatura quando disponível.
3. Salvar payload bruto em was_webhook_events.
4. Responder rápido para a Meta.
5. Processar evento por WebhookProcessor.
6. Identificar waba_id e phone_number_id.
7. Resolver tenant.
8. Classificar evento.
9. Atualizar contatos, conversas, mensagens ou status.
10. Marcar evento como processed ou failed.
```

### Regra crítica

```txt
Webhook primeiro salva, depois processa.
```

Não perder evento por erro de processamento.

---

## 12. Fluxo de Chat

### Mensagem recebida

```txt
Webhook POST
↓
WebhookEventRecorder salva payload bruto
↓
WebhookProcessor identifica evento inbound
↓
PhoneNumberService resolve tenant pelo phone_number_id
↓
ContactService encontra/cria contato
↓
ConversationService encontra/cria conversa
↓
MessageRepository salva mensagem inbound
↓
ConversationRepository atualiza last_message_at
↓
Inbox exibe conversa
```

### Mensagem enviada pelo atendente

```txt
Usuário escreve na Inbox
↓
InboxApiController recebe request
↓
TenantGuard valida tenant
↓
PermissionGuard valida was_send_messages
↓
MessageDispatchService valida janela/política mínima
↓
MessagePayloadFactory cria payload
↓
MetaApiClient envia para /messages
↓
MessageRepository salva outbound
↓
Webhook de status atualiza enviada/entregue/lida/erro
```

---

## 13. Templates

### Responsabilidade

Templates devem ser centralizados em:

```txt
TemplateService
TemplateSyncService
TemplatePayloadFactory
TemplateRepository
```

### Funcionalidades obrigatórias

```txt
1. Listar templates locais.
2. Sincronizar templates da Meta.
3. Criar template via Meta.
4. Salvar template local após criação.
5. Exibir status.
6. Exibir motivo de rejeição quando existir.
7. Enviar mensagem usando template.
```

### Regra

A tela de templates não deve montar payload direto. Ela chama endpoint interno; o backend monta o payload no `TemplatePayloadFactory`.

---

## 14. Inbox

### Layout mínimo

```txt
Coluna esquerda: lista de conversas.
Centro: mensagens da conversa.
Direita: dados do contato.
```

### Ações mínimas

```txt
Responder texto.
Enviar template.
Enviar mídia.
Atribuir conversa.
Fechar/reabrir conversa.
Adicionar tag.
Filtrar por status.
Filtrar por responsável.
```

### Estados mínimos

```txt
Sem conversas.
Carregando.
Erro ao carregar.
Mensagem enviando.
Mensagem enviada.
Mensagem com erro.
Webhook desconectado.
Número não conectado.
```

---

## 15. Páginas SaaS

Criar páginas públicas/privadas automaticamente na ativação ou via gerador.

### Páginas privadas

```txt
/app/login
/app/dashboard
/app/settings/whatsapp
/app/inbox
/app/templates
/app/logs
```

### Páginas públicas legais

```txt
/privacy-policy
/terms-of-service
/data-deletion
/acceptable-use-policy
/security
/contact
```

### Conteúdo mínimo das páginas legais

Privacy Policy deve explicar:

```txt
Dados coletados.
Uso de dados de WhatsApp.
Uso de tokens.
Processamento de mensagens.
Webhooks.
Compartilhamento de dados.
Exclusão de dados.
Contato de suporte.
```

Terms of Service deve explicar:

```txt
Responsabilidade do cliente.
Uso oficial da API.
Proibição de spam.
Necessidade de consentimento.
Suspensão por abuso.
Limitações do serviço.
```

Data Deletion deve explicar:

```txt
Como solicitar exclusão.
Quais dados são apagados.
Prazo de processamento.
Contato de suporte.
```

Acceptable Use deve proibir:

```txt
Spam.
Phishing.
Fraude.
Listas compradas.
Mensagens sem consentimento.
Conteúdo ilegal.
Abuso da plataforma.
Violação das políticas do WhatsApp Business.
```

---

## 16. Segurança

Implementar desde o começo:

```txt
Sanitização de input.
Escape de output.
Nonces em formulários.
permission_callback em rotas REST privadas.
Rate limit básico para envio.
Criptografia de tokens.
Logs sem tokens.
Validação de tenant em toda ação.
Validação de assinatura do webhook.
Bloqueio de envio sem número conectado.
Bloqueio de envio sem permissão.
Auditoria de ações críticas.
```

### TokenVault

Responsável exclusivo por:

```txt
encrypt(token)
decrypt(token)
rotateKey quando necessário
mask(token) para logs/telas
```

A chave de criptografia deve vir de constante/env/config segura, não do banco.

---

## 17. App Review da Meta

O MVP precisa permitir gravar dois vídeos de aprovação.

### Vídeo para `whatsapp_business_messaging`

O sistema deve permitir mostrar:

```txt
1. Login no SaaS.
2. Empresa de teste selecionada.
3. Número WhatsApp conectado.
4. Tela Inbox ou Send Message.
5. Envio de mensagem.
6. Mensagem chegando no WhatsApp.
7. Log/status no painel.
```

### Vídeo para `whatsapp_business_management`

O sistema deve permitir mostrar:

```txt
1. Login no SaaS.
2. Tela WhatsApp Setup.
3. WABA conectada.
4. Phone Number ID conectado.
5. Tela Message Templates.
6. Criação ou sincronização de template.
7. Template listado no painel.
```

### Justificativas sugeridas

`whatsapp_business_messaging`:

```txt
We need whatsapp_business_messaging to allow businesses connected to our SaaS platform to send WhatsApp messages, receive incoming customer messages, and receive message delivery status webhooks through the official WhatsApp Business Platform.
```

`whatsapp_business_management`:

```txt
We need whatsapp_business_management to allow businesses to connect and manage their WhatsApp Business assets inside our SaaS platform, including WhatsApp Business Accounts, phone numbers, message templates, and webhook configuration.
```

---

## 18. Ordem de Implementação Recomendada

### Fase 1 — Fundação

```txt
1. Criar plugin base.
2. Criar autoload/namespaces.
3. Criar Installer/Migrator/dbDelta.
4. Criar TableNameResolver.
5. Criar tabelas principais.
6. Criar capabilities.
7. Criar TenantContext.
8. Criar TenantGuard.
9. Criar login customizado.
10. Criar app shell.
```

### Fase 2 — Meta e Webhook

```txt
11. Criar MetaEndpointRegistry.
12. Criar MetaApiClient.
13. Criar MetaApiRequestLogger.
14. Criar TokenVault.
15. Criar MetaConfigService.
16. Criar webhook GET de verificação.
17. Criar webhook POST de recebimento.
18. Criar WebhookEventRecorder.
19. Criar WebhookProcessor básico.
```

### Fase 3 — WhatsApp Messaging

```txt
20. Criar AccountService.
21. Criar PhoneNumberService.
22. Criar MessagePayloadFactory.
23. Criar MessageDispatchService.
24. Implementar envio de texto.
25. Implementar status de mensagem.
26. Implementar mídia.
```

### Fase 4 — Inbox

```txt
27. Criar ContactRepository/Service.
28. Criar ConversationRepository/Service.
29. Criar MessageRepository.
30. Criar InboxQueryService.
31. Criar tela Inbox.
32. Criar envio por conversa.
33. Criar filtros básicos.
```

### Fase 5 — Templates e Compliance

```txt
34. Criar TemplateRepository.
35. Criar TemplatePayloadFactory.
36. Criar TemplateService.
37. Criar TemplateSyncService.
38. Criar tela Templates.
39. Criar logs.
40. Criar auditoria.
41. Criar opt-in.
42. Criar páginas legais.
43. Criar ambiente demo.
44. Criar checklist App Review.
```

---

## 19. Divisão entre 4 Devs

### Dev 01 — Core Kernel

Responsável por:

```txt
Plugin base.
Installer/Migrator.
Tabelas.
Tenants.
Usuários.
Capabilities.
TenantContext.
TenantGuard.
REST base.
Login customizado.
App shell.
```

Entrega crítica:

```txt
Outros devs conseguem usar repositories, TenantContext, PermissionGuard e rotas REST sem criar infraestrutura paralela.
```

### Dev 02 — Meta/WhatsApp

Responsável por:

```txt
MetaEndpointRegistry.
MetaApiClient.
TokenVault.
Meta config.
Embedded Signup base.
Webhook GET/POST.
Webhook processor.
AccountService.
PhoneNumberService.
MessageDispatchService.
MediaService.
```

Entrega crítica:

```txt
O sistema consegue conectar ativos, receber webhook e enviar mensagem real pela Graph API.
```

### Dev 03 — Inbox/Chat

Responsável por:

```txt
Contacts.
Conversations.
Messages.
Inbox.
Filtros.
Atribuição.
Tags.
Experiência operacional de atendimento.
```

Entrega crítica:

```txt
O usuário consegue ver conversas, ler histórico e responder pelo painel.
```

### Dev 04 — Templates/Compliance/App Review

Responsável por:

```txt
Templates.
Template sync.
Logs.
Auditoria.
Opt-in.
Exportação/exclusão.
Páginas legais.
Ambiente demo.
App Review checklist.
```

Entrega crítica:

```txt
O produto consegue demonstrar whatsapp_business_management e tem páginas legais para submissão.
```

---

## 20. Definition of Done Global

Uma task só está pronta quando:

```txt
1. Código está no módulo correto.
2. Não existe regra de negócio em controller/template/JS.
3. Todas as queries respeitam tenant_id.
4. Endpoints privados têm permission_callback.
5. Inputs são sanitizados.
6. Outputs são escapados.
7. Tokens não aparecem no frontend nem em logs.
8. Erros retornam WP_Error ou resposta padronizada.
9. Logs relevantes são criados.
10. Critérios de aceite foram testados manualmente.
11. Não há endpoint Meta hardcoded fora do MetaEndpointRegistry.
12. Não há wp_remote_get/post fora do MetaApiClient para chamadas Meta.
```

---

## 21. Critérios de Aceite do MVP

O MVP está viável quando:

```txt
1. É possível instalar e ativar o plugin.
2. As tabelas são criadas sem erro.
3. É possível criar tenant.
4. É possível vincular usuário ao tenant.
5. É possível fazer login customizado.
6. É possível configurar App ID/App Secret/Graph version.
7. É possível salvar token de forma criptografada.
8. É possível verificar webhook pela Meta.
9. É possível receber webhook e salvar evento bruto.
10. É possível enviar mensagem de texto pela Cloud API.
11. É possível receber mensagem e criar conversa.
12. É possível responder pela Inbox.
13. É possível listar/criar template.
14. É possível ver logs de API/webhook.
15. Páginas legais existem e são públicas.
16. Demo consegue gravar vídeos de App Review.
```

---

## 22. Referências Oficiais para Consulta

Usar como base técnica:

```txt
Meta Graph API:
https://developers.facebook.com/docs/graph-api/

WhatsApp Cloud API — Messages API:
https://developers.facebook.com/documentation/business-messaging/whatsapp/reference/whatsapp-business-phone-number/message-api

WhatsApp Cloud API — Media API:
https://developers.facebook.com/documentation/business-messaging/whatsapp/reference/media/media-api

WhatsApp Business Account — Message Templates:
https://developers.facebook.com/docs/graph-api/reference/whats-app-business-account/message_templates/

WhatsApp Webhooks Overview:
https://developers.facebook.com/documentation/business-messaging/whatsapp/webhooks/overview/

Create Webhook Endpoint:
https://developers.facebook.com/documentation/business-messaging/whatsapp/webhooks/create-webhook-endpoint/

Manage WABA Webhooks / subscribed_apps:
https://developers.facebook.com/documentation/business-messaging/whatsapp/solution-providers/manage-webhooks/

Embedded Signup:
https://developers.facebook.com/documentation/business-messaging/whatsapp/embedded-signup/overview/

WordPress REST API — Custom Endpoints:
https://developer.wordpress.org/rest-api/extending-the-rest-api/adding-custom-endpoints/

WordPress register_rest_route:
https://developer.wordpress.org/reference/functions/register_rest_route/

WordPress Creating Tables with Plugins:
https://developer.wordpress.org/plugins/creating-tables-with-plugins/

WordPress dbDelta:
https://developer.wordpress.org/reference/functions/dbdelta/
```

---

## 23. Instrução Final para a IA que Vai Codar

Ao codar, não tente fazer tudo de uma vez.

Siga esta abordagem:

```txt
1. Escolha uma task atômica.
2. Leia este GEMINI.md.
3. Identifique o módulo responsável.
4. Implemente somente o necessário.
5. Não duplique regra de negócio.
6. Não crie endpoint externo fora do MetaEndpointRegistry.
7. Não acesse token fora do TokenVault/TokenService.
8. Não confie em tenant_id vindo do frontend.
9. Faça a task passar pelo Definition of Done.
10. Só então avance para a próxima task.
```

O objetivo não é apenas funcionar.  
O objetivo é funcionar com arquitetura limpa, multiempresa segura, integração oficial e pronto para aprovação da Meta.
