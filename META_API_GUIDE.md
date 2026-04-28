# Meta API Integration Guide — WhatsApp SaaS Core

This guide provides a detailed technical overview of how the platform interacts with the Meta Graph API to power the WhatsApp SaaS experience.

## 1. Graph API Configuration

The platform uses the official Meta Cloud API.

- **Base URL**: `https://graph.facebook.com`
- **Default Version**: `v25.0` (Configurable via `was_meta_apps` table)

All requests are mediated by the `MetaApiClient`, which ensures proper header management and logging.

---

## 2. Authentication & Security

### 2.1 The Token Flow

The platform supports multiple token levels:

1. **User Access Token**: Used during the initial OAuth/Embedded Signup flow.
2. **System User Access Token**: A long-lived token used for server-to-server communication (highly recommended for production).
3. **WABA Token**: Specific access tokens for managing a WhatsApp Business Account.

### 2.2 Token Vault

Tokens are stored in the `was_meta_tokens` table.

- **At Rest**: Encrypted using AES-256.
- **In Transit**: Decrypted only within the `TokenService` at the moment of the request.
- **Sanitization**: All internal API logs automatically strip tokens to prevent exposure.

---

## 3. Core Operations (The Registry)

The `MetaEndpointRegistry` defines the following internal operations mapped to Meta Graph edges:

| Operation             | Method | Endpoint                       | Purpose                                    |
| :-------------------- | :----- | :----------------------------- | :----------------------------------------- |
| `OAUTH_EXCHANGE_CODE` | `GET`  | `/oauth/access_token`          | Exchange Embedded Signup code for a token. |
| `WA_SEND_MESSAGE`     | `POST` | `/{PHONE_NUMBER_ID}/messages`  | Send text, media, or templates.            |
| `WA_UPLOAD_MEDIA`     | `POST` | `/{PHONE_NUMBER_ID}/media`     | Upload files for messaging.                |
| `WA_LIST_TEMPLATES`   | `GET`  | `/{WABA_ID}/message_templates` | Sync templates from Meta.                  |
| `WA_CREATE_TEMPLATE`  | `POST` | `/{WABA_ID}/message_templates` | Create and submit templates for approval.  |
| `WA_GET_WABA`         | `GET`  | `/{WABA_ID}`                   | Retrieve WABA details and status.          |
| `WA_SUBSCRIBE_APPS`   | `POST` | `/{WABA_ID}/subscribed_apps`   | Subscribe the app to WABA webhooks.        |

---

## 4. Webhook System

The platform acts as a Webhook listener for the `whatsapp_business_account` object.

### 4.1 Verification (GET)

Meta sends a challenge to verify the endpoint ownership. The platform validates the `hub.verify_token` against the one saved in the `was_meta_apps` configuration and returns the `hub.challenge`.

### 4.2 Event Receipt (POST)

When a message is received or a status changes, Meta sends a JSON payload.
**Payload Examples handled by the system:**

#### Inbound Message

```json
{
  "object": "whatsapp_business_account",
  "entry": [
    {
      "id": "{WABA_ID}",
      "changes": [
        {
          "value": {
            "messaging_product": "whatsapp",
            "metadata": {
              "display_phone_number": "...",
              "phone_number_id": "{PHONE_NUMBER_ID}"
            },
            "contacts": [
              { "profile": { "name": "John Doe" }, "wa_id": "5511999999999" }
            ],
            "messages": [
              {
                "from": "5511999999999",
                "id": "...",
                "text": { "body": "Hello!" },
                "type": "text"
              }
            ]
          },
          "field": "messages"
        }
      ]
    }
  ]
}
```

---

## 5. Embedded Signup Flow

The platform implements the **Embedded Signup** to allow a seamless onboarding experience for new tenants:

1. **Frontend**: The user clicks the "Connect WhatsApp" button, opening the Meta Signup popup.
2. **Facebook Login**: The user selects their business and WABA.
3. **Code Exchange**: Meta returns a `code` to the frontend, which is sent to our `REST` endpoint.
4. **Backend**: The `MetaApiClient` exchanges the `code` for an access token via `/oauth/access_token`.
5. **Asset Discovery**: The system automatically calls `GET /{WABA_ID}` and `GET /{WABA_ID}/phone_numbers` to register the connected assets in our database.
6. **Webhook Subscription**: The system calls `POST /{WABA_ID}/subscribed_apps` to ensure real-time events are delivered to our platform.

---

## 6. App Review Implementation

To pass Meta's App Review, the platform ensures the following:

- **Privacy Policy**: Tailored to explain how WhatsApp data is handled.
- **Data Deletion**: Clear instructions for users to request data removal.
- **Security Protocols**: Usage of encrypted tokens and HTTPS for all endpoints.
