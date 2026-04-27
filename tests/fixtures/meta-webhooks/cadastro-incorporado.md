Sim. O **cadastro incorporado**, ou **WhatsApp Embedded Signup**, precisa ser tratado como um fluxo de **OAuth 2.0 + provisionamento da conta WhatsApp + inscrição em webhooks**.

O ponto mais importante:

> O Embedded Signup **não entrega as informações principais por webhook**.
> Ele retorna `code`, `waba_id` e `phone_number_id` diretamente para o frontend que abriu o popup. Depois seu backend troca o `code` por um token e finaliza a configuração.

A documentação da Meta informa que, quando o cliente conclui o Embedded Signup, são retornados o **WABA ID**, o **business phone number ID** e um **exchangeable token code**. Depois, como Tech Provider, você precisa trocar esse code por token, inscrever a WABA nos webhooks e registrar o número do cliente. ([Desenvolvedores do Facebook][1])

---

# 1. Visão geral do fluxo correto

```txt
Cliente logado no seu SaaS
↓
Clica em “Conectar WhatsApp Business”
↓
Seu backend cria uma sessão interna de onboarding
↓
Frontend abre o popup oficial da Meta via Facebook SDK
↓
Cliente autoriza e conclui o Embedded Signup
↓
Frontend recebe:
- code
- waba_id
- phone_number_id
↓
Frontend envia esses dados para seu backend
↓
Backend troca code por access token
↓
Backend salva token criptografado
↓
Backend salva WABA ID e Phone Number ID
↓
Backend inscreve a WABA nos webhooks
↓
Backend registra o número, se necessário
↓
Backend testa conexão
↓
Cliente vê “WhatsApp conectado”
```

O webhook entra **depois**, para receber mensagens, status de mensagens e atualizações de template. Webhooks da WhatsApp Business Platform são requisições HTTP com payload JSON enviadas para seu endpoint configurado. ([Desenvolvedores do Facebook][2])

---

# 2. Pode ser o mesmo webhook da aplicação?

Sim.

Você pode usar **um único endpoint de webhook para toda a aplicação**, por exemplo:

```txt
https://seudominio.com/was-meta-webhook
```

Esse webhook pode receber:

```txt
mensagens recebidas
status de mensagens
status de templates
eventos da WABA
eventos de teste da Meta
```

A diferença não é criar vários webhooks. A diferença é seu backend saber **classificar o payload recebido**.

Arquitetura recomendada:

```txt
POST /was-meta-webhook
↓
WebhookController
↓
WebhookSecurityService
↓
WebhookEventRepository
↓
WebhookPayloadParser
↓
MessageWebhookService
TemplateStatusWebhookService
AccountWebhookService
```

O webhook é o mesmo. Os services internos mudam conforme o tipo de evento.

---

# 3. O webhook não recebe o `code` do OAuth

Isso é crítico.

O `code` do OAuth/Embedded Signup vem pelo fluxo aberto no navegador, não pelo webhook.

Separação correta:

```txt
Embedded Signup / OAuth
→ retorna code, waba_id, phone_number_id para o frontend

Webhook
→ recebe eventos posteriores: mensagens, status e alterações
```

Então não adianta “esperar o webhook” para receber o WABA ID inicial. Seu frontend precisa capturar esses dados e mandar para seu backend.

---

# 4. Tabelas necessárias

## `wp_was_onboarding_sessions`

Essa tabela controla cada tentativa de conexão.

```sql
CREATE TABLE wp_was_onboarding_sessions (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  tenant_id BIGINT UNSIGNED NOT NULL,
  user_id BIGINT UNSIGNED NOT NULL,

  session_uuid VARCHAR(100) NOT NULL,
  status VARCHAR(50) NOT NULL DEFAULT 'started',

  meta_code TEXT DEFAULT NULL,
  waba_id VARCHAR(100) DEFAULT NULL,
  phone_number_id VARCHAR(100) DEFAULT NULL,
  business_id VARCHAR(100) DEFAULT NULL,

  error_message TEXT DEFAULT NULL,
  raw_session_payload LONGTEXT DEFAULT NULL,

  started_at DATETIME NOT NULL,
  completed_at DATETIME DEFAULT NULL,
  failed_at DATETIME DEFAULT NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,

  PRIMARY KEY (id),
  UNIQUE KEY session_uuid (session_uuid),
  KEY tenant_id (tenant_id),
  KEY user_id (user_id),
  KEY status (status)
);
```

Estados possíveis:

```txt
started
popup_opened
finished_frontend
token_exchanged
webhook_subscribed
phone_registered
connected
failed
cancelled
```

---

## Tabelas que serão preenchidas depois

```txt
wp_was_whatsapp_accounts
→ salva waba_id

wp_was_whatsapp_phone_numbers
→ salva phone_number_id

wp_was_meta_tokens
→ salva access token criptografado

wp_was_webhook_events
→ salva eventos recebidos depois
```

---

# 5. Rotas internas do WordPress

## Criar sessão de onboarding

```txt
POST /wp-json/was/v1/onboarding/whatsapp/start
```

Retorno:

```json
{
  "success": true,
  "session_uuid": "ob_8f7d2d3a",
  "app_id": "123456789",
  "config_id": "987654321",
  "graph_version": "v25.0"
}
```

Essa rota:

```txt
valida usuário logado
descobre tenant atual
cria onboarding_session
retorna dados públicos necessários ao frontend
```

---

## Finalizar onboarding

```txt
POST /wp-json/was/v1/onboarding/whatsapp/complete
```

Payload recebido do frontend:

```json
{
  "session_uuid": "ob_8f7d2d3a",
  "code": "AQ...",
  "waba_id": "123456789",
  "phone_number_id": "987654321",
  "business_id": "555555555"
}
```

Essa rota:

```txt
valida sessão
valida tenant
troca code por token
salva token
salva WABA
salva número
inscreve WABA nos webhooks
registra número, se necessário
testa conexão
marca sessão como connected
```

---

## Cancelar onboarding

```txt
POST /wp-json/was/v1/onboarding/whatsapp/cancel
```

Payload:

```json
{
  "session_uuid": "ob_8f7d2d3a",
  "reason": "user_cancelled"
}
```

---

# 6. Frontend: abrir o Embedded Signup

Na página:

```txt
/app/onboarding/whatsapp
```

Você carrega o Facebook SDK e chama `FB.login`.

Exemplo:

```js
async function startWhatsAppOnboarding() {
  const startResponse = await fetch(
    "/wp-json/was/v1/onboarding/whatsapp/start",
    {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
        "X-WP-Nonce": WAS.nonce,
      },
    },
  );

  const startData = await startResponse.json();

  const sessionUuid = startData.session_uuid;

  FB.login(
    function (response) {
      if (!response.authResponse || !response.authResponse.code) {
        cancelOnboarding(sessionUuid, "missing_code");
        return;
      }

      const code = response.authResponse.code;

      completeOnboarding({
        session_uuid: sessionUuid,
        code: code,
        waba_id: window.WAS_EMBEDDED_SIGNUP.waba_id,
        phone_number_id: window.WAS_EMBEDDED_SIGNUP.phone_number_id,
        business_id: window.WAS_EMBEDDED_SIGNUP.business_id,
      });
    },
    {
      config_id: startData.config_id,
      response_type: "code",
      override_default_response_type: true,
      extras: {
        setup: {},
        sessionInfoVersion: "3",
      },
    },
  );
}
```

---

# 7. Capturar `waba_id` e `phone_number_id`

Você precisa escutar mensagens do popup da Meta.

```js
window.WAS_EMBEDDED_SIGNUP = {
  event: null,
  waba_id: null,
  phone_number_id: null,
  business_id: null,
};

window.addEventListener("message", function (event) {
  if (
    event.origin !== "https://www.facebook.com" &&
    event.origin !== "https://web.facebook.com"
  ) {
    return;
  }

  let data;

  try {
    data = JSON.parse(event.data);
  } catch (e) {
    return;
  }

  if (data.type !== "WA_EMBEDDED_SIGNUP") {
    return;
  }

  window.WAS_EMBEDDED_SIGNUP.event = data.event;

  if (data.event === "FINISH") {
    window.WAS_EMBEDDED_SIGNUP.waba_id = data.data.waba_id || null;
    window.WAS_EMBEDDED_SIGNUP.phone_number_id =
      data.data.phone_number_id || null;
    window.WAS_EMBEDDED_SIGNUP.business_id = data.data.business_id || null;
  }

  if (data.event === "CANCEL") {
    console.warn("Embedded Signup cancelado:", data.data);
  }

  if (data.event === "ERROR") {
    console.error("Embedded Signup erro:", data.data);
  }
});
```

Essa captura é uma das partes mais importantes. O Embedded Signup retorna os dados de conclusão para a janela que iniciou o fluxo, e seu frontend precisa enviá-los ao backend. ([Desenvolvedores do Facebook][1])

---

# 8. Backend: trocar `code` por token

No backend, crie:

```php
final class MetaOAuthService
{
    public function exchangeCodeForToken(string $code): array
    {
        $url = sprintf(
            'https://graph.facebook.com/%s/oauth/access_token',
            $this->graphVersion
        );

        $response = wp_remote_get(add_query_arg([
            'client_id' => $this->appId,
            'client_secret' => $this->appSecret,
            'code' => $code,
        ], $url), [
            'timeout' => 30,
        ]);

        $body = json_decode(wp_remote_retrieve_body($response), true);

        if (is_wp_error($response)) {
            throw new RuntimeException($response->get_error_message());
        }

        if (isset($body['error'])) {
            throw new RuntimeException($body['error']['message'] ?? 'Erro ao trocar code por token.');
        }

        if (empty($body['access_token'])) {
            throw new RuntimeException('A Meta não retornou access_token.');
        }

        return $body;
    }
}
```

O resultado esperado é:

```json
{
  "access_token": "EAAG...",
  "token_type": "bearer"
}
```

Você salva apenas:

```txt
EAAG...
```

Não salve:

```txt
Bearer EAAG...
```

Não salve:

```txt
code
```

Não salve o JSON inteiro.

---

# 9. Fluxo completo do endpoint `/complete`

```php
final class WhatsAppOnboardingController
{
    public function complete(WP_REST_Request $request): WP_REST_Response
    {
        $tenantId = TenantContext::currentTenantId();
        $userId = get_current_user_id();

        $sessionUuid = sanitize_text_field($request->get_param('session_uuid'));
        $code = sanitize_text_field($request->get_param('code'));
        $wabaId = sanitize_text_field($request->get_param('waba_id'));
        $phoneNumberId = sanitize_text_field($request->get_param('phone_number_id'));
        $businessId = sanitize_text_field($request->get_param('business_id'));

        $session = $this->onboardingSessionRepository->findForTenant($sessionUuid, $tenantId);

        if (!$session) {
            throw new RuntimeException('Sessão de onboarding inválida.');
        }

        if (!$code || !$wabaId || !$phoneNumberId) {
            throw new RuntimeException('Dados incompletos do Embedded Signup.');
        }

        $this->onboardingSessionRepository->markFinishedFrontend($sessionUuid, [
            'code' => $code,
            'waba_id' => $wabaId,
            'phone_number_id' => $phoneNumberId,
            'business_id' => $businessId,
        ]);

        $tokenResponse = $this->metaOAuthService->exchangeCodeForToken($code);

        $accessToken = $tokenResponse['access_token'];

        $whatsappAccountId = $this->whatsappAccountService->upsertConnectedAccount(
            $tenantId,
            $businessId,
            $wabaId
        );

        $this->phoneNumberService->upsertConnectedPhoneNumber(
            $tenantId,
            $whatsappAccountId,
            $phoneNumberId
        );

        $this->tokenService->storeEncryptedToken(
            $tenantId,
            $whatsappAccountId,
            $accessToken,
            $tokenResponse
        );

        $this->webhookSubscriptionService->subscribeWaba(
            $tenantId,
            $wabaId,
            $accessToken
        );

        $this->onboardingSessionRepository->markConnected($sessionUuid);

        return new WP_REST_Response([
            'success' => true,
            'waba_id' => $wabaId,
            'phone_number_id' => $phoneNumberId,
            'status' => 'connected',
        ], 200);
    }
}
```

---

# 10. Inscrever a WABA nos webhooks

Depois de salvar o token, seu backend precisa chamar:

```txt
POST https://graph.facebook.com/{VERSION}/{WABA_ID}/subscribed_apps
```

A Meta documenta o endpoint `POST /<WABA_ID>/subscribed_apps` para inscrever seu app nos webhooks da WABA do cliente. ([Desenvolvedores do Facebook][3])

Service:

```php
final class WebhookSubscriptionService
{
    public function subscribeWaba(int $tenantId, string $wabaId, string $accessToken): array
    {
        $response = $this->metaApiClient->postJson(
            'waba.subscribe_webhooks',
            ['waba_id' => $wabaId],
            [],
            $accessToken
        );

        $this->logger->info('WABA inscrita nos webhooks', [
            'tenant_id' => $tenantId,
            'waba_id' => $wabaId,
            'response' => $response,
        ]);

        return $response;
    }
}
```

Registry:

```php
'waba.subscribe_webhooks' => '/{waba_id}/subscribed_apps',
```

Resposta esperada:

```json
{
  "success": true
}
```

---

# 11. Preciso criar um webhook diferente para cada cliente?

Não.

Você configura **um webhook no app Meta**:

```txt
https://seudominio.com/was-meta-webhook
```

E depois, para cada cliente que passa pelo Embedded Signup, você faz:

```txt
POST /{WABA_ID}/subscribed_apps
```

Isso diz para a Meta:

> “Envie eventos dessa WABA também para o webhook do meu app.”

Então a lógica fica:

```txt
Webhook único da aplicação
↓
Várias WABAs inscritas
↓
Payload chega com dados da WABA/número
↓
Sistema identifica tenant pelo phone_number_id ou waba_id
```

---

# 12. Como identificar o cliente quando o webhook chegar

Quando chegar um webhook, você precisa extrair:

```txt
waba_id
phone_number_id
```

Depois buscar no banco:

```sql
SELECT tenant_id
FROM wp_was_whatsapp_phone_numbers
WHERE phone_number_id = 'PHONE_NUMBER_ID_RECEBIDO'
LIMIT 1;
```

Fallback:

```sql
SELECT tenant_id
FROM wp_was_whatsapp_accounts
WHERE waba_id = 'WABA_ID_RECEBIDO'
LIMIT 1;
```

Fluxo:

```txt
Webhook chega
↓
extrai phone_number_id
↓
busca tenant pelo phone_number_id
↓
processa evento dentro do tenant correto
```

---

# 13. Registro do número

Após o token e a inscrição em webhooks, o fluxo de Tech Provider da Meta também prevê registrar o número do cliente para uso com Cloud API. ([Desenvolvedores do Facebook][4])

Endpoint:

```txt
POST /{PHONE_NUMBER_ID}/register
```

Payload:

```json
{
  "messaging_product": "whatsapp",
  "pin": "123456"
}
```

Na interface, você pode ter um passo:

```txt
Finalizar ativação do número

Digite o PIN de 6 dígitos da verificação em duas etapas do WhatsApp Business.

[ PIN ] [ Registrar número ]
```

No MVP, você pode tratar assim:

```txt
Se o número já estiver pronto → segue conexão.
Se a Meta exigir registro/PIN → mostra etapa para finalizar.
```

---

# 14. Tela ideal no SaaS

Página:

```txt
/app/onboarding/whatsapp
```

Estados da tela:

## Não iniciado

```txt
Conectar WhatsApp Business

Conecte sua conta oficial para enviar mensagens, criar templates e receber conversas.

[ Conectar com Facebook ]
```

## Popup aberto

```txt
Aguardando conclusão na Meta...

Finalize a configuração na janela aberta.
```

## Finalizando no backend

```txt
Conexão recebida.

Estamos configurando sua conta, token, número e webhook.
```

## Conectado

```txt
WhatsApp conectado com sucesso.

WABA ID: 123456789
Phone Number ID: 987654321
Webhook: ativo
Token: ativo

[ Enviar mensagem de teste ]
[ Sincronizar templates ]
```

## Erro

```txt
Não foi possível conectar sua conta.

Motivo:
Token inválido / WABA ausente / Número não retornado / Falha ao inscrever webhook

[ Tentar novamente ]
```

---

# 15. Serviços centrais necessários

```txt
OnboardingSessionService
EmbeddedSignupController
MetaOAuthService
WhatsAppAccountService
PhoneNumberService
TokenService
WebhookSubscriptionService
PhoneRegistrationService
MetaApiClient
MetaEndpointRegistry
```

Responsabilidade:

```txt
OnboardingSessionService
→ cria e controla sessão interna

EmbeddedSignupController
→ recebe start, complete, cancel

MetaOAuthService
→ troca code por token

WhatsAppAccountService
→ salva WABA

PhoneNumberService
→ salva phone_number_id

TokenService
→ criptografa e recupera token

WebhookSubscriptionService
→ chama /{WABA_ID}/subscribed_apps

PhoneRegistrationService
→ registra número se necessário

MetaApiClient
→ único lugar que chama Graph API
```

---

# 16. Regras críticas para não dar erro

```txt
Nunca salvar o code como access token.
Nunca chamar a Meta com token criptografado.
Nunca salvar “Bearer EAAG...” no banco.
Nunca depender de webhook para receber o WABA ID inicial.
Nunca deixar onboarding sem session_uuid.
Nunca salvar WABA sem tenant_id.
Nunca salvar número sem tenant_id.
Nunca processar webhook sem descobrir tenant pelo phone_number_id.
Nunca expor app_secret no frontend.
```

---

# 17. Checklist de configuração na Meta

No Meta Developers, você precisa ter:

```txt
App do tipo Business
Produto WhatsApp adicionado
Facebook Login for Business configurado
Configuration ID do Embedded Signup
Domínio autorizado para JavaScript SDK
OAuth Redirect URI válido
Webhook callback URL configurado
Verify Token configurado
Permissões solicitadas:
- whatsapp_business_management
- whatsapp_business_messaging
```

O Embedded Signup usa uma configuração do Facebook Login for Business/WhatsApp Embedded Signup, normalmente identificada por um **Configuration ID**, que é passado no `FB.login`. Guias de implementação de Tech Provider mostram esse fluxo com botão dentro da aplicação e uso do `config_id`. ([Twilio][5])

---

# 18. Plano de implementação em tasks

## Fase 1 — Configuração base

```txt
ONB-001 Criar tela /app/onboarding/whatsapp.
ONB-002 Criar tabela wp_was_onboarding_sessions.
ONB-003 Criar OnboardingSessionRepository.
ONB-004 Criar rota POST /onboarding/whatsapp/start.
ONB-005 Criar rota POST /onboarding/whatsapp/complete.
ONB-006 Criar rota POST /onboarding/whatsapp/cancel.
ONB-007 Criar settings para app_id, app_secret, config_id e graph_version.
```

## Fase 2 — Frontend Embedded Signup

```txt
ONB-101 Carregar Facebook SDK apenas na tela de onboarding.
ONB-102 Injetar app_id, config_id, graph_version e nonce via wp_localize_script.
ONB-103 Criar botão “Conectar com Facebook”.
ONB-104 Criar chamada para /start antes de abrir o popup.
ONB-105 Criar listener window.addEventListener('message').
ONB-106 Capturar evento WA_EMBEDDED_SIGNUP FINISH.
ONB-107 Capturar waba_id e phone_number_id.
ONB-108 Capturar code pelo callback do FB.login.
ONB-109 Enviar code + waba_id + phone_number_id para /complete.
```

## Fase 3 — OAuth/token

```txt
ONB-201 Criar MetaOAuthService.
ONB-202 Implementar troca code → access_token.
ONB-203 Validar que access_token existe.
ONB-204 Criar TokenEncryptionService.
ONB-205 Salvar token puro criptografado.
ONB-206 Criar debug seguro com prefixo e tamanho do token, sem logar token inteiro.
```

## Fase 4 — Persistência da WABA

```txt
ONB-301 Criar WhatsAppAccountService::upsertConnectedAccount.
ONB-302 Salvar waba_id em wp_was_whatsapp_accounts.
ONB-303 Criar PhoneNumberService::upsertConnectedPhoneNumber.
ONB-304 Salvar phone_number_id em wp_was_whatsapp_phone_numbers.
ONB-305 Marcar número como default do tenant.
```

## Fase 5 — Webhooks

```txt
ONB-401 Criar WebhookSubscriptionService.
ONB-402 Adicionar endpoint registry waba.subscribe_webhooks.
ONB-403 Chamar POST /{WABA_ID}/subscribed_apps após salvar token.
ONB-404 Salvar status webhook_subscribed na sessão.
ONB-405 Garantir que o webhook único identifica tenant por phone_number_id.
```

## Fase 6 — Registro/teste

```txt
ONB-501 Criar PhoneRegistrationService.
ONB-502 Implementar POST /{PHONE_NUMBER_ID}/register quando necessário.
ONB-503 Criar tela para PIN se a Meta exigir.
ONB-504 Criar teste de conexão com WABA.
ONB-505 Criar botão “Sincronizar templates” após conexão.
ONB-506 Criar botão “Enviar mensagem de teste”.
```

---

# 19. Fluxo final recomendado

```txt
/app/onboarding/whatsapp
↓
POST /onboarding/whatsapp/start
↓
cria session_uuid
↓
FB.login com config_id
↓
Meta popup
↓
listener recebe waba_id + phone_number_id
↓
callback recebe code
↓
POST /onboarding/whatsapp/complete
↓
exchange code → token
↓
salva token criptografado
↓
salva WABA
↓
salva Phone Number ID
↓
POST /{WABA_ID}/subscribed_apps
↓
opcional: POST /{PHONE_NUMBER_ID}/register
↓
status connected
```

---

## Resumo direto

O cadastro incorporado deve funcionar assim:

```txt
Mesmo botão para todos os clientes
Mesma configuração Meta
Sessão interna diferente por cliente
Frontend recebe code, waba_id e phone_number_id
Backend troca code por token
Backend salva tudo no tenant correto
Backend inscreve a WABA no webhook
Webhook único recebe eventos de todas as WABAs
Sistema identifica o cliente pelo phone_number_id
```

A frase-chave para seu time é:

> O Embedded Signup entrega a conexão inicial pelo OAuth no frontend/backend. O webhook só entra depois, para eventos da conta conectada.

[1]: https://developers.facebook.com/documentation/business-messaging/whatsapp/embedded-signup/implementation/?utm_source=chatgpt.com "Implementation - Meta for Developers - Facebook"
[2]: https://developers.facebook.com/documentation/business-messaging/whatsapp/webhooks/overview/?utm_source=chatgpt.com "Webhooks - Meta for Developers - Facebook"
[3]: https://developers.facebook.com/documentation/business-messaging/whatsapp/solution-providers/manage-webhooks/?utm_source=chatgpt.com "Managing Webhooks - Meta for Developers - Facebook"
[4]: https://developers.facebook.com/documentation/business-messaging/whatsapp/embedded-signup/onboarding-customers-as-a-tech-provider/?utm_source=chatgpt.com "Onboarding business customers as a Tech Provider or Tech ..."
[5]: https://www.twilio.com/docs/whatsapp/isv/tech-provider-program/integration-guide?utm_source=chatgpt.com "WhatsApp Tech Provider program integration guide"
