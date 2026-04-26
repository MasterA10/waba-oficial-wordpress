# Especificação Técnica — WhatsApp SaaS Core

Este documento descreve a arquitetura, funcionalidades e decisões de design tomadas no desenvolvimento do plugin **WhatsApp SaaS Core**. O objetivo é servir como guia para continuidade do projeto sem comprometer a integridade do sistema.

---

## 1. Visão Geral
O projeto transforma o WordPress em um sistema SaaS multilocatário (SaaS) para atendimento via WhatsApp usando a API Oficial da Meta. O sistema opera em dois ambientes:
1.  **WP-Admin:** Configuração técnica pelo administrador do site.
2.  **SaaS Shell (`/app/`):** Interface limpa e dedicada para os usuários finais (atendentes/gestores).

---

## 2. Arquitetura Base
### 2.1 Namespaces e Autoloading
O plugin utiliza o namespace `WAS` e segue o padrão **PSR-4**.
-   **Raiz dos arquivos:** `includes/`
-   **Autoloader:** Implementado em `includes/Core/Autoloader.php`.
-   **Instanciação:** Centralizada na classe `WAS\Core\Plugin`.

### 2.2 Isolamento Multi-tenant
A segurança dos dados é garantida por dois pilares:
1.  **`TenantContext`:** Gerencia qual empresa está ativa na sessão do usuário.
2.  **`TenantGuard`:** Bloqueia acessos indevidos. Todas as consultas SQL devem filtrar obrigatoriamente por `tenant_id`.

---

## 3. Banco de Dados (Schema)
O sistema utiliza 16+ tabelas customizadas com o prefixo `wp_was_`.
-   **`was_tenants`:** Empresas cadastradas.
-   **`was_tenant_users`:** Vínculo de usuários WP com empresas.
-   **`was_message_templates`:** Armazena os modelos oficiais. Campos chave: `friendly_payload` (JSON para o editor), `meta_payload` (JSON para a API) e `body_text`.
-   **`was_messages`:** Histórico de chat. Utiliza `raw_payload` para armazenar a estrutura de templates enviados para renderização posterior.
-   **`was_meta_api_logs`:** Registro técnico de todas as chamadas HTTP para o Facebook.

---

## 4. Modo de Demonstração (Sandbox)
O projeto possui uma **Inteligência de Autodetecção de Modo Demo**.
-   **Ativação:** Se o sistema não detectar um `App ID` ou `App Secret` reais (ou se os valores forem "mock", "test", "12345"), o método `Plugin::is_demo_mode()` retorna `true`.
-   **Impacto no Envio:** O `MessageDispatchService` intercepta a chamada e retorna sucesso imediato com IDs fictícios.
-   **Impacto nos Templates:** Novos templates são aprovados automaticamente (`APPROVED`) e a sincronização gera dados de exemplo.

---

## 5. Front-end e UX
### 5.1 SaaS Shell e Roteamento
-   **Rota:** `/app/` gerenciada via `add_rewrite_rule`.
-   **Template:** `templates/app-shell.php` atua como o container principal.
-   **Asset Loader:** O JavaScript (`app.js`) é inicializado de forma resiliente, funcionando tanto no Admin quanto no SaaS através da detecção de elementos no DOM.

### 5.2 Inbox (Chat)
-   **Visual:** Estilização Premium baseada no WhatsApp Web (`assets/css/app.css`).
-   **Renderização:** Suporta mensagens de texto puro e **Cards de Templates Oficiais** (com cabeçalho, corpo, rodapé e ícones dinâmicos nos botões).
-   **Variáveis:** O sistema de envio detecta variáveis `{{variavel}}` e abre um formulário de preenchimento com preview em tempo real.

### 5.3 Wizard de Templates
Um construtor visual em 5 etapas que abstrai a complexidade do JSON da Meta para o usuário final.

---

## 6. API REST
Namespace: `wp-json/was/v1`
-   **`/me`:** Dados do usuário e tenant atual.
-   **`/dashboard`:** Estatísticas em tempo real.
-   **`/conversations`:** Listagem e histórico de chat.
-   **`/templates`:** Gestão completa e sincronização.
-   **Segurança:** Todos os endpoints privados utilizam `Routes::check_auth()` para validar login e acesso ao tenant.

---

## 7. Notas para o Futuro (Não Refatorar)
-   **Não altere os nomes das colunas** de `message_type` e `text_body` nas tabelas de mensagens; o front-end depende exatamente desses nomes para a renderização do chat.
-   **Mantenha o fallback de variáveis** no `TemplateApiController`. Ele é o que permite que o sistema funcione com links dinâmicos no modo demo.
-   **A classe `TokenVault`** deve sempre ser usada para recuperar tokens, pois ela garante a descriptografia correta.

---
**Desenvolvido por:** Gemini CLI Agent
**Status:** MVP Funcional & Navegável
