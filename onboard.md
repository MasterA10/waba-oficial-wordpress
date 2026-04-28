Sim. Para o usuário conectar a API oficial dentro da sua plataforma, você não deve criar apenas um “link solto”. O ideal é inserir um botão de **onboarding oficial via Meta Embedded Signup** dentro do painel do SaaS.

Na prática, o fluxo será:

```txt
Usuário logado na sua plataforma
↓
Clica em “Conectar WhatsApp Business”
↓
Abre popup oficial da Meta
↓
Usuário escolhe/cria Business Portfolio, WABA e número
↓
Meta retorna code + WABA ID + Phone Number ID
↓
Seu backend troca o code por um business token
↓
Seu backend salva WABA, número e token criptografado
↓
Seu backend registra o número, se necessário
↓
Seu backend inscreve a WABA nos webhooks
↓
Plataforma fica conectada
```

O Embedded Signup é justamente o fluxo oficial para que clientes façam onboarding para WhatsApp Business Platform diretamente dentro da sua aplicação. Ao final, a Meta retorna dados como **WABA ID**, **business phone number ID** e um **exchangeable token code** para a janela que iniciou o fluxo. ([Facebook Developers][1])

---

# 1. Onde colocar isso no SaaS

Crie uma tela:

```txt
/app/settings/whatsapp
```

Com um card assim:

```txt
Conectar WhatsApp Business

Conecte sua conta oficial do WhatsApp Business para enviar mensagens,
gerenciar templates e receber conversas diretamente na plataforma.

[ Conectar com Facebook ]
```

Depois de conectado, a tela muda para:

```txt
WhatsApp conectado

Conta WABA: 123456789
Número: +55 24 99999-9999
Phone Number ID: 987654321
Status: conectado

[ Testar envio ] [ Sincronizar templates ] [ Desconectar ]
```

---

# 2. O que configurar primeiro na Meta

Antes do botão funcionar, você precisa configurar o app no Meta Developers.

No app da Meta:

```txt
1. Criar app do tipo Business.
2. Adicionar produto WhatsApp.
3. Adicionar Facebook Login for Business.
4. Criar uma Configuration para WhatsApp Embedded Signup.
5. Copiar o Configuration ID.
6. Configurar domínio permitido do JavaScript SDK.
7. Configurar OAuth Redirect URI.
8. Usar HTTPS obrigatoriamente.
9. Colocar Privacy Policy URL.
10. Solicitar as permissões whatsapp_business_management e whatsapp_business_messaging.
```

No fluxo de Tech Provider, a configuração de Login for Business deve usar a variação **WhatsApp Embedded Signup** e gerar um **Configuration ID**, que será usado no botão dentro da sua aplicação. ([Twilio][2])

Também é importante ativar no Facebook Login for Business:

```txt
Client OAuth Login
Web OAuth Login
Enforce HTTPS
Embedded Browser OAuth Login
Use Strict Mode for Redirect URIs
Login with JavaScript SDK
```

A documentação técnica citada pela Twilio reforça que os domínios do app precisam estar em HTTPS e adicionados nos campos de OAuth e JavaScript SDK. ([Twilio][2])

---

# 3. Configuração que você deve salvar no WordPress

Na tabela ou settings do plugin, salve:

```txt
meta_app_id
meta_app_secret_encrypted
meta_graph_version = v25.0
meta_embedded_signup_config_id
meta_webhook_verify_token
meta_webhook_callback_url
```

A versão atual mais recente da Graph API é `v25.0`, segundo o changelog oficial da Meta. ([Facebook Developers][3])

Exemplo de registro em `wp_was_settings`:

```txt
meta_app_id = 123456789
meta_graph_version = v25.0
meta_embedded_signup_config_id = 987654321
meta_webhook_verify_token = token_seguro_gerado_pelo_sistema
```

---

# 4. Frontend: botão de onboarding

Na página `/app/settings/whatsapp`, carregue o Facebook SDK apenas nessa tela.

```html
<div id="fb-root"></div>

<script>
  window.fbAsyncInit = function () {
    FB.init({
      appId: WAS_META.appId,
      autoLogAppEvents: true,
      xfbml: true,
      version: WAS_META.graphVersion,
    });
  };
</script>

<script
  async
  defer
  crossorigin="anonymous"
  src="https://connect.facebook.net/pt_BR/sdk.js"
></script>

<button id="was-connect-whatsapp">Conectar WhatsApp Business</button>
```

No WordPress, você injeta `WAS_META` com `wp_localize_script()`:

```php
wp_localize_script('was-whatsapp-setup', 'WAS_META', [
    'appId'        => $app_id,
    'graphVersion' => 'v25.0',
    'configId'     => $config_id,
    'restUrl'      => esc_url_raw(rest_url('was/v1/meta/embedded-signup/complete')),
    'nonce'        => wp_create_nonce('wp_rest'),
]);
```

---

# 5. Capturar WABA ID e Phone Number ID

Além do callback do `FB.login`, você deve escutar o `message event`, porque o Embedded Signup retorna informações da sessão para a janela que abriu o popup.

```js
let wasSignupSession = {
  waba_id: null,
  phone_number_id: null,
  event: null,
};

window.addEventListener("message", function (event) {
  if (
    event.origin !== "https://www.facebook.com" &&
    event.origin !== "https://web.facebook.com"
  ) {
    return;
  }

  try {
    const data = JSON.parse(event.data);

    if (data.type === "WA_EMBEDDED_SIGNUP") {
      wasSignupSession.event = data.event;

      if (data.event === "FINISH") {
        wasSignupSession.waba_id = data.data.waba_id;
        wasSignupSession.phone_number_id = data.data.phone_number_id;
      }

      if (data.event === "CANCEL") {
        console.warn("Usuário cancelou o onboarding:", data.data);
      }

      if (data.event === "ERROR") {
        console.error("Erro no onboarding:", data.data);
      }
    }
  } catch (error) {
    // Ignora mensagens que não são JSON do Embedded Signup
  }
});
```

Essa parte é crítica: **capture os dados na hora**. Se você perder o callback, o usuário terá que refazer o fluxo.

---

# 6. Abrir o onboarding com `FB.login`

O botão chama:

```js
document
  .getElementById("was-connect-whatsapp")
  .addEventListener("click", function () {
    launchWhatsAppOnboarding();
  });

function launchWhatsAppOnboarding() {
  FB.login(
    function (response) {
      if (!response.authResponse || !response.authResponse.code) {
        alert("Conexão cancelada ou não autorizada.");
        return;
      }

      const code = response.authResponse.code;

      fetch(WAS_META.restUrl, {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
          "X-WP-Nonce": WAS_META.nonce,
        },
        body: JSON.stringify({
          code: code,
          waba_id: wasSignupSession.waba_id,
          phone_number_id: wasSignupSession.phone_number_id,
          event: wasSignupSession.event,
        }),
      })
        .then((res) => res.json())
        .then((data) => {
          if (data.success) {
            window.location.href = "/app/settings/whatsapp?connected=1";
          } else {
            alert(data.message || "Não foi possível concluir a conexão.");
          }
        })
        .catch(() => {
          alert("Erro ao conectar com o servidor.");
        });
    },
    {
      config_id: WAS_META.configId,
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

A documentação de integração do Embedded Signup mostra o uso do botão “Login with Facebook”, o carregamento do Facebook SDK, o `FB.login()` e o uso do `Configuration ID` para abrir o popup de onboarding. ([Twilio][2])

---

# 7. Backend: endpoint que conclui a conexão

Crie a rota:

```txt
POST /wp-json/was/v1/meta/embedded-signup/complete
```

Ela recebe:

```json
{
  "code": "AQ...",
  "waba_id": "123456789",
  "phone_number_id": "987654321",
  "event": "FINISH"
}
```

Essa rota deve fazer:

```txt
1. Validar usuário logado.
2. Descobrir tenant atual.
3. Validar code, waba_id e phone_number_id.
4. Trocar code por business token.
5. Salvar token criptografado.
6. Salvar WABA.
7. Salvar Phone Number ID.
8. Registrar número, se necessário.
9. Inscrever WABA nos webhooks.
10. Testar conexão com a Meta.
11. Retornar sucesso.
```

---

# 8. Trocar o code por token

Crie um service central:

```php
MetaOAuthService::exchangeCodeForBusinessToken($code)
```

Por baixo ele chama:

```txt
GET /oauth/access_token
```

Exemplo:

```php
public function exchange_code_for_token(string $code): array
{
    $url = sprintf(
        'https://graph.facebook.com/%s/oauth/access_token',
        $this->graph_version
    );

    $response = wp_remote_get(add_query_arg([
        'client_id'     => $this->app_id,
        'client_secret' => $this->app_secret,
        'code'          => $code,
    ], $url));

    if (is_wp_error($response)) {
        throw new Exception($response->get_error_message());
    }

    $body = json_decode(wp_remote_retrieve_body($response), true);

    if (isset($body['error'])) {
        throw new Exception($body['error']['message'] ?? 'Erro ao trocar code por token.');
    }

    return $body;
}
```

A Meta documenta que, no onboarding como Tech Provider, o token code retornado pelo Embedded Signup deve ser trocado por um **business token** usando o endpoint `GET /oauth/access_token`. ([Facebook Developers][4])

---

# 9. Salvar a conexão no banco

Depois da troca do token, grave:

## `wp_was_meta_tokens`

```txt
tenant_id
whatsapp_account_id
token_type = business_integration_system_user
access_token_encrypted
scopes
expires_at
status = active
```

## `wp_was_whatsapp_accounts`

```txt
tenant_id
waba_id
status = connected
connected_at
```

## `wp_was_whatsapp_phone_numbers`

```txt
tenant_id
whatsapp_account_id
phone_number_id
status = active
is_default = 1
```

Nunca grave token puro.

---

# 10. Registrar o número, se necessário

Depois do onboarding, pode ser necessário registrar o número para uso com Cloud API.

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

A documentação oficial da Meta diz que um número precisa estar registrado antes de enviar e receber mensagens pela Cloud API, e que a registration API usa o ID do número de telefone. ([Facebook Developers][5])

No SaaS, você pode criar um passo visual:

```txt
Finalizar registro do número

Digite o PIN de 6 dígitos configurado na verificação em duas etapas da conta WhatsApp Business.

[ ______ ] [ Registrar número ]
```

Backend:

```php
public function register_phone_number(string $phone_number_id, string $pin, string $token): array
{
    return $this->meta_api_client->post(
        "phone.register",
        [
            'phone_number_id' => $phone_number_id
        ],
        [
            'messaging_product' => 'whatsapp',
            'pin' => $pin
        ],
        $token
    );
}
```

---

# 11. Inscrever a WABA nos webhooks

Esse passo é obrigatório para receber mensagens e status.

Endpoint:

```txt
POST /{WABA_ID}/subscribed_apps
```

A Meta documenta o endpoint `POST /<WABA_ID>/subscribed_apps` para inscrever seu app nos webhooks da WABA do cliente. ([Facebook Developers][6])

Service:

```php
public function subscribe_waba_to_webhooks(string $waba_id, string $token): array
{
    return $this->meta_api_client->post(
        'waba.subscribe_webhooks',
        [
            'waba_id' => $waba_id
        ],
        [],
        $token
    );
}
```

Resposta esperada:

```json
{
  "success": true
}
```

---

# 12. Abstração correta dos endpoints

Não deixe endpoint da Meta espalhado pelo código.

Crie:

```php
final class MetaEndpointRegistry
{
    public static function resolve(string $operation, array $params = []): string
    {
        $map = [
            'oauth.exchange_code' => '/oauth/access_token',

            'phone.register' => '/{phone_number_id}/register',
            'phone.get' => '/{phone_number_id}',

            'waba.subscribe_webhooks' => '/{waba_id}/subscribed_apps',
            'waba.templates.list' => '/{waba_id}/message_templates',
            'waba.templates.create' => '/{waba_id}/message_templates',

            'messages.send' => '/{phone_number_id}/messages',
            'media.upload' => '/{phone_number_id}/media',
        ];

        if (!isset($map[$operation])) {
            throw new InvalidArgumentException("Operação Meta não registrada: {$operation}");
        }

        $path = $map[$operation];

        foreach ($params as $key => $value) {
            $path = str_replace('{' . $key . '}', rawurlencode($value), $path);
        }

        return $path;
    }
}
```

E um client único:

```php
final class MetaApiClient
{
    public function post(string $operation, array $path_params, array $body, string $access_token): array
    {
        $path = MetaEndpointRegistry::resolve($operation, $path_params);

        $url = sprintf(
            'https://graph.facebook.com/%s%s',
            $this->graph_version,
            $path
        );

        $response = wp_remote_post($url, [
            'headers' => [
                'Authorization' => 'Bearer ' . $access_token,
                'Content-Type'  => 'application/json',
            ],
            'body' => wp_json_encode($body),
            'timeout' => 30,
        ]);

        return $this->parse_response($response);
    }

    public function get(string $operation, array $path_params, array $query, string $access_token): array
    {
        $path = MetaEndpointRegistry::resolve($operation, $path_params);

        $url = add_query_arg($query, sprintf(
            'https://graph.facebook.com/%s%s',
            $this->graph_version,
            $path
        ));

        $response = wp_remote_get($url, [
            'headers' => [
                'Authorization' => 'Bearer ' . $access_token,
            ],
            'timeout' => 30,
        ]);

        return $this->parse_response($response);
    }

    private function parse_response($response): array
    {
        if (is_wp_error($response)) {
            throw new Exception($response->get_error_message());
        }

        $status = wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);

        if ($status >= 400 || isset($body['error'])) {
            throw new Exception($body['error']['message'] ?? 'Erro na Meta API.');
        }

        return $body ?: [];
    }
}
```

---

# 13. O que mostrar para o usuário durante o onboarding

A experiência ideal:

## Estado 1 — Não conectado

```txt
Conecte sua conta oficial do WhatsApp Business

Você será redirecionado para a Meta para escolher ou criar sua conta comercial,
conectar um número e autorizar nossa plataforma.

[ Conectar com Facebook ]
```

## Estado 2 — Processando

```txt
Conexão em andamento...

Estamos salvando sua conta WhatsApp Business, configurando o número
e ativando os webhooks.
```

## Estado 3 — Precisa de PIN

```txt
Número encontrado

Para finalizar a ativação da Cloud API, informe o PIN de 6 dígitos
da verificação em duas etapas.

[ PIN ] [ Finalizar ativação ]
```

## Estado 4 — Conectado

```txt
WhatsApp Business conectado com sucesso

WABA ID: 123456789
Phone Number ID: 987654321
Webhook: ativo
Templates: sincronizados

[ Enviar mensagem de teste ]
```

---

# 14. Payload final salvo no seu SaaS

Depois de tudo concluído, o registro local deve ficar assim:

```json
{
  "tenant_id": 1,
  "waba_id": "123456789",
  "phone_number_id": "987654321",
  "status": "connected",
  "webhook_status": "subscribed",
  "token_status": "active",
  "connected_at": "2026-04-26 15:00:00"
}
```

---

# 15. Fluxo técnico final

```txt
Botão “Conectar com Facebook”
↓
FB.login com config_id
↓
Meta Embedded Signup abre popup
↓
Usuário finaliza cadastro
↓
Frontend captura:
- code
- waba_id
- phone_number_id
↓
Frontend envia para:
POST /wp-json/was/v1/meta/embedded-signup/complete
↓
Backend troca code por business token:
GET /oauth/access_token
↓
Backend salva token criptografado
↓
Backend salva WABA e Phone Number ID
↓
Backend registra número:
POST /{PHONE_NUMBER_ID}/register
↓
Backend ativa webhooks:
POST /{WABA_ID}/subscribed_apps
↓
Backend testa conexão
↓
Usuário vê “WhatsApp conectado”
```

---

## Resumo direto

O “link de onboard” no seu app deve ser um **botão de Embedded Signup**, não um link comum.

Você precisa de:

```txt
Meta App ID
Configuration ID do Facebook Login for Business
App Secret no backend
Graph API version
Página HTTPS autorizada
Callback REST no WordPress
Webhook público configurado
```

E seu backend deve centralizar tudo em:

```txt
EmbeddedSignupController
MetaOAuthService
MetaApiClient
MetaEndpointRegistry
WhatsAppAccountService
WebhookSubscriptionService
PhoneNumberRegistrationService
```

A experiência para o cliente fica simples:

```txt
Clicar → entrar com Facebook → escolher empresa/número → autorizar → pronto.
```

Por baixo, seu sistema resolve token, WABA, número, webhook e registro oficial da API..

[1]: https://developers.facebook.com/documentation/business-messaging/whatsapp/embedded-signup/overview/?utm_source=chatgpt.com "Embedded Signup - Meta for Developers - Facebook"
[2]: https://www.twilio.com/docs/whatsapp/isv/tech-provider-program/integration-guide "WhatsApp Tech Provider program integration guide | Twilio"
[3]: https://developers.facebook.com/docs/graph-api/changelog/?utm_source=chatgpt.com "Changelog - Graph API - Documentation"
[4]: https://developers.facebook.com/documentation/business-messaging/whatsapp/embedded-signup/onboarding-customers-as-a-tech-provider/?utm_source=chatgpt.com "Onboarding business customers as a Tech Provider or Tech ..."
[5]: https://developers.facebook.com/documentation/business-messaging/whatsapp/business-phone-numbers/registration/?utm_source=chatgpt.com "Register a Business Phone Number - Meta for Developers"
[6]: https://developers.facebook.com/documentation/business-messaging/whatsapp/solution-providers/manage-webhooks/?utm_source=chatgpt.com "Managing Webhooks - Meta for Developers - Facebook"
