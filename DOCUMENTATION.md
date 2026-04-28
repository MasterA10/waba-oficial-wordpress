# WhatsApp SaaS Core — Technical Documentation

## 1. Introduction

**WhatsApp SaaS Core** is a robust, multi-tenant platform built on WordPress that transforms the CMS into a full-scale SaaS for managing WhatsApp Business Accounts (WABA) via the Official Meta Cloud API.

Designed for scalability and security, the platform allows multiple businesses (tenants) to connect their official WhatsApp numbers, manage message templates, interact with customers through a real-time inbox, and maintain compliance with Meta's official policies.

---

## 2. Core Architecture

The platform follows a strict decoupled architecture to ensure maintainability and data isolation.

### 2.1 Multi-Tenancy (Tenant Isolation)

The system uses a custom multi-tenant engine:

- **Tenant Isolation**: Every database record (contacts, messages, templates) is linked to a `tenant_id`.
- **Tenant Context**: The `TenantContext` service identifies the current tenant based on the authenticated user session.
- **Tenant Guard**: All data queries and API actions are forced through a guard that appends the `tenant_id` filter, preventing data leakage between businesses.

### 2.2 User Roles & Permissions

The platform defines specific SaaS roles that extend standard WordPress identities:

- **Platform Owner**: Full access to global settings and Meta App configuration.
- **Tenant Admin**: Full management of a specific business/tenant.
- **Agent**: Can manage conversations and send messages.
- **Viewer**: Read-only access to conversation history and logs.

---

## 3. Meta API Integration

Integration with Meta's Graph API is handled through a centralized abstraction layer.

### 3.1 MetaApiClient & EndpointRegistry

To avoid hardcoded URLs and ensure consistency across the codebase:

- **MetaEndpointRegistry**: Maps internal operations (e.g., `WA_SEND_MESSAGE`) to specific Graph API versioned endpoints.
- **MetaApiClient**: The sole interface for outbound requests. It handles request signing, authorization headers, and automatic logging.

### 3.2 Token Vault (Security)

Sensitive tokens (System User Access Tokens, WABA Tokens) are protected by a **Token Vault**:

- **AES-256 Encryption**: Tokens are encrypted before storage using a server-side secret key.
- **Masked Logs**: Internal logs never contain plain-text tokens or secrets.
- **Session Protection**: Access tokens are retrieved and decrypted only at the moment of the API call.

---

## 4. WhatsApp Features

### 4.1 Official Messaging (Cloud API)

- **Text & Media**: Full support for free-form text and media (images, videos, documents) within the 24-hour service window.
- **Template Messaging**: Support for sending official approved templates outside the service window.
- **Message Dispatch**: A centralized service handles the delivery logic and stores outbound message status.

### 4.2 Unified Inbox

A high-performance real-time chat interface:

- **Conversation Management**: Automatically groups messages into conversations based on contact identity.
- **Agent Assignment**: Allows routing conversations to specific team members.
- **Status Tracking**: Real-time delivery status (Sent, Delivered, Read, Failed) updated via webhooks.

### 4.3 Official Template Management

- **Sync System**: Periodically fetches and caches approved templates from Meta.
- **Creation Flow**: Submit new templates for Meta approval directly from the dashboard.
- **Dynamic Payloads**: `TemplatePayloadFactory` handles the complex JSON construction for templates with variables.

---

## 5. Webhook Infrastructure

The platform uses a "Record-First, Process-Later" strategy for webhooks to ensure 100% reliability.

1. **Verification**: Responds to Meta's `hub.challenge` to verify the endpoint.
2. **Persistence**: Every incoming POST event is saved as raw JSON in `was_webhook_events`.
3. **Processing**: An asynchronous processor (triggered by cron or real-time event) parses the payload, identifies the tenant via `phone_number_id`, and updates the corresponding record (message, contact, or status).
4. **Signature Validation**: All incoming requests are validated using the Meta App Secret to ensure they originated from Meta.

---

## 6. Compliance & Legal

To comply with Meta's strict App Review requirements, the platform includes a built-in **Compliance Suite**:

- **Auto-Generated Legal Pages**: Pre-filled Privacy Policy, Terms of Service, and Data Deletion instructions tailored for WhatsApp usage.
- **Audit Logging**: Comprehensive logs of all administrative actions and security-sensitive events.
- **Opt-in Management**: Tools to track and record customer consent before initiating outbound messages.

---

## 7. REST API (SaaS Internal)

The platform exposes a secure REST API under the `/wp-json/was/v1` namespace.

### Authentication

Private routes are protected by:

1. **WP Nonces**: For browser-based UI requests.
2. **Session Context**: Validates the user-tenant relationship.
3. **Capability Check**: Ensures the user has the specific permission (e.g., `was_send_messages`).

### Key Endpoint Groups

- `GET /me`: Identity and current tenant context.
- `GET/POST /conversations`: Chat operations.
- `GET/POST /templates`: Template management.
- `POST /whatsapp/connect`: Embedded Signup asset registration.

---

## 8. Meta App Review Guide

The platform is optimized for approval of `whatsapp_business_messaging` and `whatsapp_business_management` permissions.

### Demonstration Environment

- **Demo Mode**: Can be toggled to show pre-configured data for review videos.
- **Health Check**: A dedicated page to verify WABA connection and Webhook status for the reviewer.

### Key Permission Justifications

- **whatsapp_business_messaging**: Used to allow businesses to send/receive messages and receive delivery notifications.
- **whatsapp_business_management**: Used to allow businesses to manage their WABA assets, numbers, and message templates within our dashboard.

---

## 9. Developer Guidelines

1. **Centralized Logic**: Business logic must reside in `Services`, never in `Controllers` or `Templates`.
2. **Tenant First**: Always use `TenantContext` to fetch data. Never query global tables without a tenant filter.
3. **No Direct Graph Calls**: Use `MetaApiClient` for all communication with Meta.
4. **Sanitization**: Escape all outputs using standard WordPress functions (`esc_html`, `esc_attr`).
