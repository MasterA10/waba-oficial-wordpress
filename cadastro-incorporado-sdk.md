A integração com o **SDK** é necessária **se você quiser usar o Embedded Signup dentro do seu próprio SaaS**, com aquele botão:

```txt
Conectar WhatsApp Business
```

Ela **não é necessária** para enviar mensagens, criar templates, listar templates ou receber webhooks. Para essas partes, você usa só backend + Graph API.

A divisão é esta:

```txt
SDK JavaScript da Meta
→ usado para abrir o cadastro incorporado no navegador

Backend / Graph API
→ usado para trocar code por token, salvar WABA, salvar número, criar templates, enviar mensagens e receber webhooks
```

A documentação oficial do Embedded Signup diz que ele é uma interface incorporada para clientes conectarem/criarem ativos da WhatsApp Business Platform, e a implementação envolve capturar os dados gerados pelo fluxo, como `code`, `waba_id` e `phone_number_id`. ([Desenvolvedores do Facebook][1])

---

## O que é esse SDK?

É o **Facebook JavaScript SDK** carregado na página do seu SaaS onde o cliente vai conectar o WhatsApp.

Exemplo de página:

```txt
https://app.suamarca.com/app/onboarding/whatsapp
```

Nessa página você carrega:

```html
<script
  async
  defer
  crossorigin="anonymous"
  src="https://connect.facebook.net/pt_BR/sdk.js"
></script>
```

E inicializa:

```js
window.fbAsyncInit = function () {
  FB.init({
    appId: WAS_META.appId,
    autoLogAppEvents: true,
    xfbml: true,
    version: WAS_META.graphVersion,
  });
};
```

O SDK serve para abrir o popup/login oficial da Meta usando:

```js
FB.login(...)
```

Guias de implementação do Embedded Signup mostram esse padrão: carregar o Facebook SDK na página onde o botão aparece e chamar o fluxo com o `config_id` da configuração de WhatsApp Embedded Signup. ([Twilio][2])

---

# O SDK é obrigatório?

## Para Embedded Signup dentro do seu SaaS: sim

Se você quer que o cliente clique dentro da sua plataforma e conecte a conta oficial dele, você usa o SDK.

Fluxo:

```txt
Cliente logado no seu SaaS
↓
Clica em “Conectar WhatsApp Business”
↓
SDK abre popup oficial da Meta
↓
Cliente escolhe/cria WABA e número
↓
SDK/callback retorna code
↓
window.message retorna waba_id e phone_number_id
↓
Seu backend troca code por token
```

---

## Para API normal: não

Depois que o cliente conectou, o SDK não entra mais.

Para criar templates:

```txt
POST /{WABA_ID}/message_templates
```

Para enviar mensagens:

```txt
POST /{PHONE_NUMBER_ID}/messages
```

Para mídia:

```txt
POST /{PHONE_NUMBER_ID}/media
```

Para webhook:

```txt
POST /was-meta-webhook
```

Tudo isso é backend.

---

# Existe alternativa sem SDK?

Sim: a Meta tem o **Hosted Embedded Signup**. Ele é uma alternativa para quem não quer adicionar JavaScript no site ou portal do cliente; nesse caso, você usa um link de onboarding hospedado. A própria documentação diz que, se você não quiser implementar Embedded Signup adicionando JavaScript ao seu site ou portal, pode usar um link clicável em vez disso. ([Desenvolvedores do Facebook][3])

Mas para o seu caso, construindo um SaaS com WordPress, eu usaria o SDK, porque ele te dá mais controle:

```txt
sessão interna por cliente
captura de waba_id
captura de phone_number_id
captura de code
vinculação direta ao tenant logado
melhor experiência dentro do painel
```

O Hosted pode ser útil depois se você quiser mandar um link externo por e-mail ou WhatsApp, mas o fluxo dentro do SaaS fica mais limpo com SDK.

---

# Como o fluxo com SDK funciona

## 1. Usuário clica no botão

Na tela:

```txt
/app/onboarding/whatsapp
```

Botão:

```html
<button id="connect-whatsapp">Conectar WhatsApp Business</button>
```

---

## 2. Seu backend cria uma sessão interna

Antes de abrir o popup, chame:

```txt
POST /wp-json/was/v1/onboarding/whatsapp/start
```

Retorno:

```json
{
  "success": true,
  "session_uuid": "ob_123",
  "app_id": "123456789",
  "config_id": "987654321",
  "graph_version": "v25.0"
}
```

Essa sessão é o que amarra o onboarding ao cliente certo.

---

## 3. O SDK abre o Embedded Signup

```js
FB.login(
  function (response) {
    if (!response.authResponse || !response.authResponse.code) {
      console.log("Usuário cancelou ou não retornou code");
      return;
    }

    const code = response.authResponse.code;

    completeOnboarding(code);
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
```

O detalhe crítico é:

```js
response_type: "code";
override_default_response_type: true;
```

Sem isso, você pode não receber o `code` corretamente.

---

## 4. Você escuta o retorno do Embedded Signup

Além do callback do `FB.login`, você precisa escutar o evento `message`.

```js
window.WAS_SIGNUP = {
  waba_id: null,
  phone_number_id: null,
  business_id: null,
  event: null,
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

  window.WAS_SIGNUP.event = data.event;

  if (data.event === "FINISH") {
    window.WAS_SIGNUP.waba_id = data.data.waba_id || null;
    window.WAS_SIGNUP.phone_number_id = data.data.phone_number_id || null;
    window.WAS_SIGNUP.business_id = data.data.business_id || null;
  }

  if (data.event === "CANCEL") {
    console.log("Onboarding cancelado", data.data);
  }

  if (data.event === "ERROR") {
    console.error("Erro no onboarding", data.data);
  }
});
```

Aqui acontece uma coisa importante:

```txt
FB.login callback
→ entrega o code

window.message
→ entrega waba_id e phone_number_id
```

Eles podem chegar em momentos ligeiramente diferentes. Então seu frontend deve guardar os dados temporariamente e só chamar `/complete` quando tiver:

```txt
code
waba_id
phone_number_id
session_uuid
```

---

# Exemplo completo do frontend

```js
let onboardingSessionUuid = null;
let oauthCode = null;

const signupData = {
  waba_id: null,
  phone_number_id: null,
  business_id: null,
};

async function startOnboarding() {
  const res = await fetch("/wp-json/was/v1/onboarding/whatsapp/start", {
    method: "POST",
    headers: {
      "Content-Type": "application/json",
      "X-WP-Nonce": WAS_META.nonce,
    },
  });

  const data = await res.json();

  onboardingSessionUuid = data.session_uuid;

  FB.login(
    function (response) {
      if (!response.authResponse || !response.authResponse.code) {
        cancelOnboarding("missing_code");
        return;
      }

      oauthCode = response.authResponse.code;

      tryCompleteOnboarding();
    },
    {
      config_id: data.config_id,
      response_type: "code",
      override_default_response_type: true,
      extras: {
        setup: {},
        sessionInfoVersion: "3",
      },
    },
  );
}

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

  if (data.event === "FINISH") {
    signupData.waba_id = data.data.waba_id || null;
    signupData.phone_number_id = data.data.phone_number_id || null;
    signupData.business_id = data.data.business_id || null;

    tryCompleteOnboarding();
  }

  if (data.event === "CANCEL") {
    cancelOnboarding("user_cancelled");
  }

  if (data.event === "ERROR") {
    cancelOnboarding("meta_error");
  }
});

async function tryCompleteOnboarding() {
  if (
    !onboardingSessionUuid ||
    !oauthCode ||
    !signupData.waba_id ||
    !signupData.phone_number_id
  ) {
    return;
  }

  const res = await fetch("/wp-json/was/v1/onboarding/whatsapp/complete", {
    method: "POST",
    headers: {
      "Content-Type": "application/json",
      "X-WP-Nonce": WAS_META.nonce,
    },
    body: JSON.stringify({
      session_uuid: onboardingSessionUuid,
      code: oauthCode,
      waba_id: signupData.waba_id,
      phone_number_id: signupData.phone_number_id,
      business_id: signupData.business_id,
    }),
  });

  const result = await res.json();

  if (result.success) {
    window.location.href = "/app/settings/whatsapp?connected=1";
  } else {
    alert(result.message || "Não foi possível conectar.");
  }
}
```

---

# O que o backend faz depois do SDK

O SDK termina quando você recebe os dados e manda para o backend.

Depois disso, tudo é servidor:

```txt
POST /onboarding/whatsapp/complete
↓
troca code por token
↓
salva token criptografado
↓
salva WABA ID
↓
salva Phone Number ID
↓
inscreve WABA no webhook
↓
testa conexão
```

Troca do code:

```txt
GET /oauth/access_token
```

Com:

```txt
client_id
client_secret
code
```

O fluxo de Tech Provider da Meta orienta trocar o token code recebido pelo Embedded Signup por um business token para operar a conta conectada. ([Desenvolvedores do Facebook][1])

---

# O que configurar na Meta para o SDK funcionar

No app da Meta, você precisa ter:

```txt
App ID
Produto WhatsApp
Facebook Login for Business
Configuration ID do WhatsApp Embedded Signup
Domínio autorizado para JavaScript SDK
OAuth Redirect URI
HTTPS válido
Privacy Policy URL
```

No seu WordPress/SaaS, você salva:

```txt
meta_app_id
meta_app_secret
meta_config_id
meta_graph_version
webhook_verify_token
```

O frontend recebe só:

```txt
app_id
config_id
graph_version
nonce
```

O frontend **nunca** recebe:

```txt
app_secret
access_token
```

---

# Então, é desnecessário?

Depende do caminho.

## Caminho recomendado para seu SaaS

Use SDK.

Motivo:

```txt
melhor controle do tenant
menos fricção
onboarding dentro do painel
captura direta do WABA ID
captura direta do Phone Number ID
experiência mais profissional
```

## Caminho alternativo

Use Hosted Embedded Signup/link.

Motivo:

```txt
menos código no frontend
bom para convite externo
útil para fluxo por e-mail
```

Mas você perde um pouco da experiência integrada.

---

## Resumo direto

O SDK é necessário só para esta parte:

```txt
abrir o cadastro incorporado da Meta dentro da sua plataforma
```

Ele não substitui seu backend.

A arquitetura correta é:

```txt
Facebook JavaScript SDK
→ abre popup e captura code + dados do signup

Seu backend WordPress
→ troca code por token
→ salva WABA e número
→ inscreve webhook
→ cria templates
→ envia mensagens
→ recebe eventos
```

Para o seu projeto, eu não pularia o SDK. Ele é o caminho mais natural para transformar o WordPress em SaaS com onboarding oficial dentro do painel.

[1]: https://developers.facebook.com/documentation/business-messaging/whatsapp/embedded-signup/implementation/?utm_source=chatgpt.com "Implementation - Meta for Developers - Facebook"
[2]: https://www.twilio.com/docs/whatsapp/isv/tech-provider-program/integration-guide?utm_source=chatgpt.com "WhatsApp Tech Provider program integration guide"
[3]: https://developers.facebook.com/documentation/business-messaging/whatsapp/embedded-signup/hosted-es/?utm_source=chatgpt.com "Hosted Embedded Signup - Meta for Developers - Facebook"
