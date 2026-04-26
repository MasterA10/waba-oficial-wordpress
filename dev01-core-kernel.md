# DEV 01 — Kernel SaaS, Banco, Tenants, Auth e REST Base

**Responsável:** Dev 01 / Core Platform

**Missão:** Criar a fundação do plugin/SaaS: estrutura do WordPress, tabelas, migrations, tenants, permissões, login, shell visual e contratos centrais usados pelos outros devs.

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

- `whatsapp-saas-core.php`
- `includes/Core/*`
- `includes/Auth/*`
- `includes/Tenants/*`
- `includes/REST/Routes.php`
- `includes/REST/AdminApiController.php` apenas para base/contratos
- `includes/Admin/Menu.php`
- `templates/login.php`
- `templates/dashboard.php`
- `assets/css/*`
- schema/migrations/tabelas do plugin


### Não deve editar sem alinhamento

- `includes/Meta/*`, exceto interfaces combinadas.
- `includes/WhatsApp/*`, exceto contratos compartilhados.
- `includes/Inbox/*`, exceto contratos compartilhados.
- Fluxos de templates, webhook ou envio, salvo para conectar dependency injection/boot.


## Contratos públicos que esta frente deve respeitar

- `TableNameResolver`: único lugar para resolver nomes de tabelas.
- `TenantContext`: único lugar para descobrir tenant atual.
- `TenantGuard`: único lugar para validar acesso de tenant.
- `CapabilityService`: único lugar para checar permissões SaaS.
- `RestPermissionCallback`: callback padrão para rotas privadas.
- `Plugin::boot()`: registra módulos, hooks e rotas, mas não implementa regra de negócio.


## Handoff esperado ao terminar

- Plugin ativa sem erro fatal.
- Todas as tabelas existem.
- Tenants e usuários estão isolados.
- Login customizado e dashboard básico funcionam.
- REST base responde com `/me` e dashboard summary.
- Outros devs conseguem usar `TenantContext`, `TableNameResolver`, repositories base e permission callback.


## Ordem recomendada de execução desta frente

### P0

- [ ] WAS-001 — Criar pasta base do plugin
- [ ] WAS-002 — Criar arquivo principal do plugin
- [ ] WAS-003 — Definir constantes globais do plugin
- [ ] WAS-004 — Criar estrutura de diretórios internos
- [ ] WAS-005 — Criar carregador de classes
- [ ] WAS-006 — Criar classe principal do plugin
- [ ] WAS-007 — Registrar hooks de ativação e desativação
- [ ] WAS-008 — Criar opção de versão do banco
- [ ] WAS-009 — Criar classe de instalação do banco
- [ ] WAS-010 — Criar helper para nomes de tabelas
- [ ] WAS-011 — Criar tabela de tenants
- [ ] WAS-012 — Criar tabela de vínculo usuário/tenant
- [ ] WAS-013 — Criar tabela de configuração do Meta App
- [ ] WAS-014 — Criar tabela de contas WhatsApp Business
- [ ] WAS-015 — Criar tabela de números WhatsApp
- [ ] WAS-016 — Criar tabela de tokens Meta criptografados
- [ ] WAS-017 — Criar tabela de contatos
- [ ] WAS-018 — Criar tabela de opt-ins
- [ ] WAS-019 — Criar tabela de conversas
- [ ] WAS-020 — Criar tabela de mensagens
- [ ] WAS-021 — Criar tabela de histórico de status de mensagem
- [ ] WAS-022 — Criar tabela de templates
- [ ] WAS-024 — Criar tabela de eventos de webhook
- [ ] WAS-025 — Criar tabela de auditoria
- [ ] WAS-026 — Criar tabela de configurações
- [ ] WAS-027 — Validar criação completa das tabelas
- [ ] WAS-028 — Criar capabilities customizadas
- [ ] WAS-029 — Criar roles SaaS internas
- [ ] WAS-030 — Criar serviço de tenant atual
- [ ] WAS-031 — Criar guard de tenant em queries
- [ ] WAS-032 — Criar seed de tenant inicial
- [ ] WAS-033 — Criar repository de tenants
- [ ] WAS-034 — Criar repository de tenant users
- [ ] WAS-035 — Criar página pública de login customizada
- [ ] WAS-036 — Criar redirecionamento pós-login
- [ ] WAS-037 — Criar template base do app
- [ ] WAS-038 — Criar dashboard inicial
- [ ] WAS-039 — Criar menu lateral do SaaS
- [ ] WAS-041 — Criar registrador de rotas REST
- [ ] WAS-042 — Criar permission callback padrão
- [ ] WAS-043 — Criar endpoint `/me`
- [ ] WAS-044 — Criar endpoint de dashboard summary
- [ ] WAS-113 — Implementar nonce nas ações do frontend
- [ ] WAS-114 — Sanitizar inputs de formulários
- [ ] WAS-115 — Escapar outputs nas telas
- [ ] WAS-124 — Testar ativação limpa do plugin
- [ ] WAS-125 — Testar isolamento entre tenants
### P1

- [ ] WAS-023 — Criar tabela de mídia
- [ ] WAS-040 — Criar assets CSS base
### P2

- [ ] WAS-137 — Criar gestão de usuários do tenant
- [ ] WAS-142 — Criar billing/plans interno

---


## Tasks adicionais — contrato técnico para API oficial

Estas tasks garantem que os outros devs não precisem hardcodar endpoints da Meta.

### WAS-049A — Criar `MetaEndpointRegistry`

**Prioridade:** P0  
**Objetivo:** centralizar o mapa de operações internas para endpoints oficiais da Graph API.  
**Entrega:** classe `includes/Meta/MetaEndpointRegistry.php` criada pelo Dev 01 como contrato/base, mesmo que o Dev 02 complete a implementação.

**Critérios de aceite:**

- Possui método `resolve(string $operation, array $params): MetaEndpoint`.
- Resolve método HTTP, path e query params.
- Não faz requisição HTTP.
- Suporta pelo menos as operações: `WA_SEND_MESSAGE`, `WA_UPLOAD_MEDIA`, `WA_LIST_TEMPLATES`, `WA_CREATE_TEMPLATE`, `WA_SUBSCRIBE_WABA_WEBHOOKS`, `OAUTH_EXCHANGE_CODE`.
- Gera erro controlado para operação desconhecida.

**Depende de:** WAS-005, WAS-006.

---

### WAS-049B — Criar contrato `MetaApiResponse`

**Prioridade:** P0  
**Objetivo:** padronizar a resposta interna das chamadas para a Meta.  
**Entrega:** classe/value object `MetaApiResponse`.

**Critérios de aceite:**

- Armazena `success`, `status_code`, `operation`, `data` e `error`.
- Possui factory para sucesso e erro.
- Não expõe token ou segredo.
- Pode ser serializada para log seguro.

**Depende de:** WAS-005.

---

### WAS-049C — Criar tabela opcional de logs de API externa

**Prioridade:** P1  
**Objetivo:** permitir auditoria e debug das chamadas feitas à Meta sem vazar dados sensíveis.  
**Entrega:** tabela `wp_was_meta_api_logs` ou decisão documentada de usar `wp_was_audit_logs` com `action=meta_api_request`.

**Critérios de aceite:**

- Guarda operação, tenant, status, erro e duração.
- Não guarda Authorization header.
- Não guarda App Secret.
- Não guarda corpo completo quando houver dados pessoais; usa resumo/sanitização.

**Depende de:** WAS-025.

---

### WAS-049D — Criar configuração global de versão Graph API

**Prioridade:** P0  
**Objetivo:** evitar versão hardcoded espalhada no projeto.  
**Entrega:** constante fallback + campo configurável em `was_meta_apps.graph_version`.

**Critérios de aceite:**

- Default atual: `v25.0`.
- Pode ser alterado no painel de configuração Meta App.
- `MetaEndpointRegistry` recebe a versão por parâmetro/contexto.
- Nenhuma classe de envio/template/webhook define versão diretamente.

**Depende de:** WAS-013, WAS-046.
---

# Tasks atribuídas

## WAS-001 — Criar pasta base do plugin

**Prioridade:** P0  
**Objetivo:** iniciar o plugin dentro da estrutura padrão do WordPress.  
**Entrega:** pasta `wp-content/plugins/whatsapp-saas-core/` criada.

**Critérios de aceite:**

- A pasta existe no ambiente local.
- O nome da pasta é `whatsapp-saas-core`.
- Nenhum código funcional ainda é necessário.

**Depende de:** nenhuma.

---

---

## WAS-002 — Criar arquivo principal do plugin

**Prioridade:** P0  
**Objetivo:** permitir que o WordPress reconheça o plugin.  
**Entrega:** arquivo `whatsapp-saas-core.php` com header do plugin.

**Critérios de aceite:**

- Plugin aparece na tela de plugins do WordPress.
- Plugin pode ser ativado sem erro fatal.
- Header possui nome, descrição, versão e autor.

**Depende de:** WAS-001.

---

---

## WAS-003 — Definir constantes globais do plugin

**Prioridade:** P0  
**Objetivo:** centralizar caminhos, URLs e versão do plugin.  
**Entrega:** constantes como `WAS_VERSION`, `WAS_PLUGIN_FILE`, `WAS_PLUGIN_DIR`, `WAS_PLUGIN_URL`.

**Critérios de aceite:**

- As constantes são carregadas apenas se não estiverem definidas.
- Caminhos apontam corretamente para a pasta do plugin.
- Nenhuma constante conflita com outros plugins.

**Depende de:** WAS-002.

---

---

## WAS-004 — Criar estrutura de diretórios internos

**Prioridade:** P0  
**Objetivo:** organizar o plugin por responsabilidade.  
**Entrega:** diretórios `includes`, `assets`, `templates` e subpastas iniciais.

**Critérios de aceite:**

- Existem as pastas `includes/Core`, `includes/Auth`, `includes/Tenants`, `includes/Meta`, `includes/WhatsApp`, `includes/Inbox`, `includes/Admin`, `includes/REST`.
- Existem as pastas `assets/css`, `assets/js` e `templates`.

**Depende de:** WAS-001.

---

---

## WAS-005 — Criar carregador de classes

**Prioridade:** P0  
**Objetivo:** carregar classes do plugin sem `require` espalhado.  
**Entrega:** autoloader simples para namespace `WAS\`.

**Critérios de aceite:**

- Classes dentro de `includes` são carregadas automaticamente.
- O carregador não quebra caso a classe não exista.
- O plugin ativa sem erro fatal.

**Depende de:** WAS-004.

---

---

## WAS-006 — Criar classe principal do plugin

**Prioridade:** P0  
**Objetivo:** centralizar inicialização do sistema.  
**Entrega:** classe `WAS\Core\Plugin` com método `boot()`.

**Critérios de aceite:**

- `boot()` é chamado no arquivo principal.
- A classe carrega hooks básicos do WordPress.
- Nenhuma regra de negócio é implementada nessa task.

**Depende de:** WAS-005.

---

---

## WAS-007 — Registrar hooks de ativação e desativação

**Prioridade:** P0  
**Objetivo:** preparar execução de instalação e limpeza leve.  
**Entrega:** `register_activation_hook` e `register_deactivation_hook` conectados.

**Critérios de aceite:**

- Ativar o plugin chama classe de ativação.
- Desativar o plugin chama classe de desativação.
- Nenhuma tabela é criada ainda.

**Depende de:** WAS-006.

---

---

## WAS-008 — Criar opção de versão do banco

**Prioridade:** P0  
**Objetivo:** permitir controle futuro de migrations.  
**Entrega:** option `was_db_version` salva no WordPress.

**Critérios de aceite:**

- Ao ativar o plugin, a option é criada.
- A versão inicial é compatível com `WAS_VERSION` ou `1.0.0`.
- A option pode ser lida pelo instalador.

**Depende de:** WAS-007.

---

# EPIC 01 — Installer e banco de dados

---

## WAS-009 — Criar classe de instalação do banco

**Prioridade:** P0  
**Objetivo:** centralizar criação de tabelas.  
**Entrega:** classe `WAS\Core\Installer`.

**Critérios de aceite:**

- Classe possui método `install()`.
- Classe acessa `$wpdb` corretamente.
- Nenhuma tabela é criada nessa task.

**Depende de:** WAS-008.

---

---

## WAS-010 — Criar helper para nomes de tabelas

**Prioridade:** P0  
**Objetivo:** evitar strings duplicadas para tabelas.  
**Entrega:** método/helper que retorna nomes com `$wpdb->prefix`.

**Critérios de aceite:**

- Retorna `{$wpdb->prefix}was_tenants` e demais nomes.
- Pode ser reutilizado por repositories.
- Não usa prefixo fixo `wp_`.

**Depende de:** WAS-009.

---

---

## WAS-011 — Criar tabela de tenants

**Prioridade:** P0  
**Objetivo:** armazenar empresas/clientes do SaaS.  
**Entrega:** tabela `was_tenants` criada via `dbDelta()`.

**Critérios de aceite:**

- Tabela possui `id`, `name`, `slug`, `status`, `plan`, `created_at`, `updated_at`.
- Existe índice único para `slug`.
- Tabela é criada ao ativar o plugin.

**Depende de:** WAS-010.

---

---

## WAS-012 — Criar tabela de vínculo usuário/tenant

**Prioridade:** P0  
**Objetivo:** ligar usuários do WordPress a empresas do SaaS.  
**Entrega:** tabela `was_tenant_users`.

**Critérios de aceite:**

- Tabela possui `tenant_id`, `user_id`, `role`, `status`.
- Existe índice único para `tenant_id + user_id`.
- Usuário pode pertencer a mais de um tenant.

**Depende de:** WAS-011.

---

---

## WAS-013 — Criar tabela de configuração do Meta App

**Prioridade:** P0  
**Objetivo:** armazenar dados do app Meta usado pelo SaaS.  
**Entrega:** tabela `was_meta_apps`.

**Critérios de aceite:**

- Tabela possui `app_id`, `app_secret_encrypted`, `graph_version`, `webhook_verify_token`, `status`.
- Não há campo para app secret em texto puro.
- Existe índice para `status`.

**Depende de:** WAS-010.

---

---

## WAS-014 — Criar tabela de contas WhatsApp Business

**Prioridade:** P0  
**Objetivo:** armazenar WABAs conectadas.  
**Entrega:** tabela `was_whatsapp_accounts`.

**Critérios de aceite:**

- Tabela possui `tenant_id`, `meta_business_id`, `waba_id`, `name`, `status`.
- Existe índice único para `tenant_id + waba_id`.
- Existe índice para `waba_id`.

**Depende de:** WAS-011.

---

---

## WAS-015 — Criar tabela de números WhatsApp

**Prioridade:** P0  
**Objetivo:** armazenar números conectados à WABA.  
**Entrega:** tabela `was_whatsapp_phone_numbers`.

**Critérios de aceite:**

- Tabela possui `tenant_id`, `whatsapp_account_id`, `phone_number_id`, `display_phone_number`, `verified_name`, `status`, `is_default`.
- `phone_number_id` é único.
- Existe índice para `tenant_id`.

**Depende de:** WAS-014.

---

---

## WAS-016 — Criar tabela de tokens Meta criptografados

**Prioridade:** P0  
**Objetivo:** armazenar tokens de acesso com segurança.  
**Entrega:** tabela `was_meta_tokens`.

**Critérios de aceite:**

- Tabela possui `access_token_encrypted`.
- Tabela possui `scopes`, `expires_at`, `status`.
- Nenhum campo de token em texto puro existe.

**Depende de:** WAS-014.

---

---

## WAS-017 — Criar tabela de contatos

**Prioridade:** P0  
**Objetivo:** armazenar contatos finais do WhatsApp.  
**Entrega:** tabela `was_contacts`.

**Critérios de aceite:**

- Tabela possui `tenant_id`, `wa_id`, `phone`, `profile_name`, `opt_in_status`.
- Existe índice único para `tenant_id + wa_id`.
- Existe índice para `phone`.

**Depende de:** WAS-011.

---

---

## WAS-018 — Criar tabela de opt-ins

**Prioridade:** P0  
**Objetivo:** registrar prova de consentimento dos contatos.  
**Entrega:** tabela `was_contact_optins`.

**Critérios de aceite:**

- Tabela possui `tenant_id`, `contact_id`, `source`, `consent_text`, `status`, `created_at`.
- Existe índice para `contact_id`.
- Existe índice para `status`.

**Depende de:** WAS-017.

---

---

## WAS-019 — Criar tabela de conversas

**Prioridade:** P0  
**Objetivo:** agrupar mensagens em um chat.  
**Entrega:** tabela `was_conversations`.

**Critérios de aceite:**

- Tabela possui `tenant_id`, `contact_id`, `phone_number_id`, `assigned_user_id`, `status`, `last_message_at`.
- Existe índice para `tenant_id`.
- Existe índice para `status`.
- Existe índice para `last_message_at`.

**Depende de:** WAS-017, WAS-015.

---

---

## WAS-020 — Criar tabela de mensagens

**Prioridade:** P0  
**Objetivo:** armazenar mensagens inbound/outbound do chat.  
**Entrega:** tabela `was_messages`.

**Critérios de aceite:**

- Tabela possui `tenant_id`, `conversation_id`, `direction`, `message_type`, `wa_message_id`, `text_body`, `status`, `raw_payload`.
- Existe índice único para `wa_message_id`.
- Existe índice para `conversation_id`.
- Existe índice para `created_at`.

**Depende de:** WAS-019.

---

---

## WAS-021 — Criar tabela de histórico de status de mensagem

**Prioridade:** P0  
**Objetivo:** registrar eventos de enviada, entregue, lida e erro.  
**Entrega:** tabela `was_message_statuses`.

**Critérios de aceite:**

- Tabela possui `wa_message_id`, `status`, `raw_payload`, `created_at`.
- Existe índice para `wa_message_id`.
- Existe índice para `status`.

**Depende de:** WAS-020.

---

---

## WAS-022 — Criar tabela de templates

**Prioridade:** P0  
**Objetivo:** armazenar templates oficiais do WhatsApp.  
**Entrega:** tabela `was_message_templates`.

**Critérios de aceite:**

- Tabela possui `tenant_id`, `whatsapp_account_id`, `meta_template_id`, `name`, `language`, `category`, `status`, `components_json`.
- Existe índice único para `tenant_id + name + language`.
- Existe índice para `status`.

**Depende de:** WAS-014.

---

---

## WAS-023 — Criar tabela de mídia

**Prioridade:** P1  
**Objetivo:** armazenar referência de mídias enviadas/recebidas.  
**Entrega:** tabela `was_media`.

**Critérios de aceite:**

- Tabela possui `tenant_id`, `meta_media_id`, `wp_attachment_id`, `mime_type`, `filename`, `direction`, `status`.
- Existe índice para `meta_media_id`.
- Existe índice para `tenant_id`.

**Depende de:** WAS-011.

---

---

## WAS-024 — Criar tabela de eventos de webhook

**Prioridade:** P0  
**Objetivo:** salvar payloads crus antes do processamento.  
**Entrega:** tabela `was_webhook_events`.

**Critérios de aceite:**

- Tabela possui `payload`, `processing_status`, `signature_valid`, `received_at`, `processed_at`.
- Existe índice para `processing_status`.
- Existe índice para `received_at`.

**Depende de:** WAS-011.

---

---

## WAS-025 — Criar tabela de auditoria

**Prioridade:** P0  
**Objetivo:** registrar ações sensíveis da plataforma.  
**Entrega:** tabela `was_audit_logs`.

**Critérios de aceite:**

- Tabela possui `tenant_id`, `user_id`, `action`, `entity_type`, `entity_id`, `metadata`, `created_at`.
- Existe índice para `action`.
- Existe índice para `created_at`.

**Depende de:** WAS-011.

---

---

## WAS-026 — Criar tabela de configurações

**Prioridade:** P0  
**Objetivo:** armazenar configurações globais e por tenant.  
**Entrega:** tabela `was_settings`.

**Critérios de aceite:**

- Tabela possui `tenant_id`, `setting_key`, `setting_value`, `autoload`.
- Existe índice único para `tenant_id + setting_key`.
- Aceita `tenant_id` nulo para configurações globais.

**Depende de:** WAS-011.

---

---

## WAS-027 — Validar criação completa das tabelas

**Prioridade:** P0  
**Objetivo:** garantir que todas as tabelas essenciais são criadas.  
**Entrega:** rotina de validação no instalador.

**Critérios de aceite:**

- Após ativar o plugin, todas as tabelas P0 existem.
- Se uma tabela estiver ausente, logar aviso administrativo.
- Não quebrar o WordPress em caso de falha parcial.

**Depende de:** WAS-011 até WAS-026.

---

# EPIC 02 — Roles, capabilities e isolamento multiempresa

---

## WAS-028 — Criar capabilities customizadas

**Prioridade:** P0  
**Objetivo:** controlar acesso às áreas do SaaS.  
**Entrega:** capabilities `was_access_app`, `was_manage_tenant`, `was_manage_whatsapp`, `was_manage_templates`, `was_send_messages`, `was_view_inbox`, `was_view_logs`.

**Critérios de aceite:**

- Capabilities são criadas na ativação.
- Administrador WordPress recebe todas as capabilities.
- Nenhuma página privada abre sem capability adequada.

**Depende de:** WAS-007.

---

---

## WAS-029 — Criar roles SaaS internas

**Prioridade:** P0  
**Objetivo:** padronizar papéis do produto.  
**Entrega:** roles internas `platform_owner`, `tenant_admin`, `manager`, `agent`, `viewer`, `compliance`.

**Critérios de aceite:**

- Cada role tem conjunto mínimo de capabilities.
- Role `agent` pode ver inbox e enviar mensagens.
- Role `viewer` não pode enviar mensagens.

**Depende de:** WAS-028.

---

---

## WAS-030 — Criar serviço de tenant atual

**Prioridade:** P0  
**Objetivo:** descobrir o tenant ativo do usuário logado.  
**Entrega:** classe `TenantContext`.

**Critérios de aceite:**

- Retorna tenant atual para usuário logado.
- Retorna erro controlado se usuário não tem tenant.
- Nunca aceita `tenant_id` arbitrário do frontend sem validar vínculo.

**Depende de:** WAS-012.

---

---

## WAS-031 — Criar guard de tenant em queries

**Prioridade:** P0  
**Objetivo:** impedir vazamento de dados entre empresas.  
**Entrega:** classe `TenantGuard`.

**Critérios de aceite:**

- Verifica se usuário pertence ao tenant.
- Bloqueia acesso a recursos de outro tenant.
- Retorna erro HTTP 403 nos endpoints REST privados.

**Depende de:** WAS-030.

---

---

## WAS-032 — Criar seed de tenant inicial

**Prioridade:** P0  
**Objetivo:** permitir uso inicial após ativação.  
**Entrega:** tenant inicial criado para o administrador.

**Critérios de aceite:**

- Ao ativar o plugin, se não houver tenant, criar um tenant demo/admin.
- Usuário administrador atual é vinculado como `platform_owner`.
- Não duplica tenant em reativações.

**Depende de:** WAS-011, WAS-012.

---

---

## WAS-033 — Criar repository de tenants

**Prioridade:** P0  
**Objetivo:** centralizar operações de tenant.  
**Entrega:** classe `TenantRepository`.

**Critérios de aceite:**

- Possui métodos `find`, `findBySlug`, `create`, `updateStatus`.
- Todas as queries usam `$wpdb->prepare`.
- Não contém lógica de tela.

**Depende de:** WAS-011.

---

---

## WAS-034 — Criar repository de tenant users

**Prioridade:** P0  
**Objetivo:** centralizar vínculos usuário/empresa.  
**Entrega:** classe `TenantUserRepository`.

**Critérios de aceite:**

- Possui métodos `attachUser`, `detachUser`, `getUserTenants`, `userBelongsToTenant`.
- Não cria usuário WordPress.
- Usa `$wpdb->prepare`.

**Depende de:** WAS-012.

---

# EPIC 03 — Login e shell SaaS

---

## WAS-035 — Criar página pública de login customizada

**Prioridade:** P0  
**Objetivo:** esconder experiência padrão de `/wp-admin`.  
**Entrega:** página `/app/login` com formulário de login.

**Critérios de aceite:**

- Usuário consegue fazer login com e-mail/senha WordPress.
- Login inválido mostra erro amigável.
- Rodapé possui links para Termos e Privacidade.

**Depende de:** WAS-028.

---

---

## WAS-036 — Criar redirecionamento pós-login

**Prioridade:** P0  
**Objetivo:** levar usuários SaaS ao dashboard.  
**Entrega:** redirecionamento para `/app/dashboard`.

**Critérios de aceite:**

- Login pela página customizada redireciona para dashboard.
- Usuário sem tenant recebe mensagem de acesso pendente.
- Administrador continua podendo acessar `/wp-admin`.

**Depende de:** WAS-035, WAS-030.

---

---

## WAS-037 — Criar template base do app

**Prioridade:** P0  
**Objetivo:** padronizar layout do SaaS.  
**Entrega:** template com sidebar, header e área de conteúdo.

**Critérios de aceite:**

- Header mostra usuário logado.
- Sidebar mostra links principais.
- Área de conteúdo é reutilizável por outras páginas.

**Depende de:** WAS-036.

---

---

## WAS-038 — Criar dashboard inicial

**Prioridade:** P0  
**Objetivo:** dar entrada visual ao SaaS.  
**Entrega:** página `/app/dashboard`.

**Critérios de aceite:**

- Dashboard exige login.
- Dashboard exige tenant ativo.
- Mostra cards vazios/iniciais: Conta WhatsApp, Número Ativo, Mensagens Hoje, Conversas Abertas, Templates.

**Depende de:** WAS-037.

---

---

## WAS-039 — Criar menu lateral do SaaS

**Prioridade:** P0  
**Objetivo:** permitir navegação entre módulos.  
**Entrega:** menu com Dashboard, Inbox, Templates, WhatsApp Setup, Logs, Settings.

**Critérios de aceite:**

- Links respeitam capabilities.
- Usuário sem permissão não vê item restrito.
- Menu destaca página atual.

**Depende de:** WAS-037.

---

---

## WAS-040 — Criar assets CSS base

**Prioridade:** P1  
**Objetivo:** dar aparência de SaaS e não de wp-admin.  
**Entrega:** arquivo CSS do app.

**Critérios de aceite:**

- Login e dashboard possuem layout limpo.
- CSS é carregado apenas nas páginas do SaaS.
- Não afeta tema público do site.

**Depende de:** WAS-037.

---

# EPIC 04 — REST API interna

---

## WAS-041 — Criar registrador de rotas REST

**Prioridade:** P0  
**Objetivo:** centralizar endpoints internos.  
**Entrega:** classe `REST\Routes`.

**Critérios de aceite:**

- Namespace `was/v1` registrado.
- Classe é chamada no hook `rest_api_init`.
- Nenhum endpoint funcional ainda é necessário.

**Depende de:** WAS-006.

---

---

## WAS-042 — Criar permission callback padrão

**Prioridade:** P0  
**Objetivo:** proteger endpoints privados.  
**Entrega:** função/classe que valida login, capability e tenant.

**Critérios de aceite:**

- Usuário não logado recebe 401.
- Usuário sem capability recebe 403.
- Usuário sem tenant recebe 403.

**Depende de:** WAS-031, WAS-041.

---

---

## WAS-043 — Criar endpoint `/me`

**Prioridade:** P0  
**Objetivo:** retornar usuário logado e tenant atual para o frontend.  
**Entrega:** `GET /wp-json/was/v1/me`.

**Critérios de aceite:**

- Retorna ID, nome, e-mail e capabilities úteis.
- Retorna tenant atual.
- Não retorna dados sensíveis.

**Depende de:** WAS-042.

---

---

## WAS-044 — Criar endpoint de dashboard summary

**Prioridade:** P0  
**Objetivo:** alimentar cards do dashboard.  
**Entrega:** `GET /wp-json/was/v1/dashboard`.

**Critérios de aceite:**

- Retorna contadores básicos.
- Filtra tudo por tenant atual.
- Funciona mesmo sem conta WhatsApp conectada.

**Depende de:** WAS-042, WAS-038.

---

# EPIC 05 — Criptografia e configuração Meta

---

## WAS-113 — Implementar nonce nas ações do frontend

**Prioridade:** P0  
**Objetivo:** proteger ações autenticadas contra CSRF.  
**Entrega:** nonce enviado em requests privados.

**Critérios de aceite:**

- Endpoints privados validam nonce quando aplicável.
- Requests sem nonce válido falham.
- Login não quebra.

**Depende de:** WAS-041, WAS-042.

---

---

## WAS-114 — Sanitizar inputs de formulários

**Prioridade:** P0  
**Objetivo:** reduzir risco de dados maliciosos.  
**Entrega:** sanitização nos handlers principais.

**Critérios de aceite:**

- Configurações Meta são sanitizadas.
- Templates são sanitizados.
- Mensagens são validadas sem quebrar caracteres legítimos.

**Depende de:** WAS-047, WAS-080, WAS-091.

---

---

## WAS-115 — Escapar outputs nas telas

**Prioridade:** P0  
**Objetivo:** evitar XSS na interface.  
**Entrega:** escape nos templates PHP.

**Critérios de aceite:**

- Dados do usuário usam `esc_html`, `esc_attr` ou equivalente.
- Payload bruto não é renderizado sem escape.
- Mensagens recebidas são exibidas com segurança.

**Depende de:** WAS-037, WAS-077, WAS-101.

---

---

## WAS-124 — Testar ativação limpa do plugin

**Prioridade:** P0  
**Objetivo:** validar instalação do zero.  
**Entrega:** teste manual documentado.

**Critérios de aceite:**

- Plugin ativa sem erro fatal.
- Tabelas são criadas.
- Tenant inicial é criado.
- Dashboard abre.

**Depende de:** WAS-027, WAS-032, WAS-038.

---

---

## WAS-125 — Testar isolamento entre tenants

**Prioridade:** P0  
**Objetivo:** garantir que uma empresa não vê dados da outra.  
**Entrega:** teste manual com dois tenants.

**Critérios de aceite:**

- Usuário do tenant A não vê conversas do tenant B.
- Endpoints REST bloqueiam acesso cruzado.
- Queries principais usam tenant atual.

**Depende de:** WAS-031, WAS-074, WAS-075.

---

---

## WAS-137 — Criar gestão de usuários do tenant

**Prioridade:** P2  
**Objetivo:** permitir que tenant admin convide equipe.  
**Entrega:** tela de usuários.

**Critérios de aceite:**

- Tenant admin lista usuários da empresa.
- Pode convidar novo usuário.
- Pode alterar role interna.

**Depende de:** WAS-034.

---

---

## WAS-142 — Criar billing/plans interno

**Prioridade:** P2  
**Objetivo:** preparar monetização SaaS.  
**Entrega:** estrutura de planos e limites.

**Critérios de aceite:**

- Tenant possui plano.
- Limites podem ser consultados.
- Nenhum bloqueio comercial afeta MVP sem configuração.

**Depende de:** WAS-011, WAS-026.

---
