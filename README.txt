WhatsApp SaaS Core — Official Meta API for WordPress

Transform your WordPress installation into a professional, multi-tenant WhatsApp SaaS platform using the Official Meta WhatsApp Business Platform (Cloud API).

KEY FEATURES

- Multi-Tenancy: Built-in isolation for multiple business accounts (tenants).
- Official Meta Integration: Secure communication with Meta Graph API (v25.0+).
- Unified Inbox: Real-time customer service interface for agents.
- Message Templates: Synchronize and manage official templates with Meta approval tracking.
- Embedded Signup: Seamless onboarding flow for new businesses.
- Compliance Ready: Built-in legal pages and audit logs for Meta App Review.
- Security: AES-256 token encryption via a dedicated Token Vault.

DOCUMENTATION

We have comprehensive technical documentation available in the following files:

- DOCUMENTATION.txt: Overview of architecture, features, and security.
- META_API_GUIDE.txt: Technical details on endpoints, payloads, and webhooks.
- Onboarding Guide (onboard.md): Initial setup instructions (Portuguese).

TECHNOLOGY STACK

- Core: PHP (WordPress Plugin API)
- Database: Custom tables with $wpdb and dbDelta.
- Frontend: Vanilla CSS, Modern JavaScript (Fetch API), and PHP Templates.
- API: Custom WordPress REST API namespace (was/v1).
- Security: Custom Encryption Service for sensitive tokens.

COMPLIANCE & PRIVACY

This project is designed to help businesses pass Meta's App Review. It includes:

- Automated generation of Privacy Policy and Terms of Service.
- Data deletion request handling.
- Audit logging of all administrative actions.
