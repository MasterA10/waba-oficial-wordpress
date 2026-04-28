# Erros Comuns e Boas Práticas — WhatsApp SaaS Core

Este documento lista os erros técnicos mais frequentes e críticos encontrados durante o desenvolvimento deste plugin. Serve como guia de referência para que a IA e desenvolvedores evitem retrabalho e garantam a estabilidade do sistema.

---

## 1. Infraestrutura e Roteamento (WordPress)

### ❌ Erro: Retorno 404 em URLs customizadas (Webhooks/App)
*   **Causa:** As `rewrite rules` do WordPress não foram atualizadas ou o `flush_rewrite_rules()` foi executado antes do gancho `init`.
*   **Como evitar:** 
    *   Sempre registre regras de reescrita no `init` com prioridade alta (ex: 99).
    *   Implemente uma checagem automática no `init`: se a regra não existir no banco (`get_option('rewrite_rules')`), execute o `flush`.
    *   Suba a versão do plugin (`WAS_VERSION`) para disparar o gatilho de atualização.

### ❌ Erro: Parâmetros do Webhook sumindo (hub.challenge vazio)
*   **Causa:** O redirecionamento canônico do WordPress (`redirect_canonical`) detecta a falta de uma barra no final da URL e redireciona, perdendo os parâmetros `GET` da Meta.
*   **Como evitar:** Desabilite o redirecionamento canônico especificamente para as rotas de webhook usando o filtro `redirect_canonical`.

---

## 2. Segurança e Criptografia

### ❌ Erro: Falha na validação de assinatura (Erro 403)
*   **Causa:** O sistema tentou validar a assinatura `X-Hub-Signature-256` usando o **App Secret criptografado** direto do banco de dados.
*   **Como evitar:** Sempre use `TokenVault::decrypt()` no segredo antes de passá-lo para o validador de assinatura.

### ❌ Erro: Chave de Criptografia Inexistente
*   **Causa:** O sistema tenta criptografar tokens mas a constante `WAS_ENCRYPTION_KEY` não está definida, causando erro fatal.
*   **Como evitar:** No arquivo `Constants.php`, implemente uma lógica que busca a chave no `wp_options`. Se não existir, gera uma aleatória (`bin2hex(random_bytes(16))`) e salva para persistência.

---

## 3. Banco de Dados e Repositórios

### ❌ Erro: "Unknown column" após subir arquivos para o servidor (Produção/Teste)
*   **Causa:** O banco de dados no servidor remoto não possui as colunas novas. O `dbDelta` nativo do WP muitas vezes falha ao aplicar `ALTER TABLE` em colunas existentes.
*   **Como evitar:** 
    *   Use a classe `Migrator.php` para rodar comandos `ALTER TABLE` manuais e condicionais.
    *   Sempre incremente `WAS_DB_VERSION` em `Constants.php` para disparar a atualização.

### ❌ Erro: Falha silenciosa no salvamento (insert_id = 0)
*   **Causa:** Discrepância entre os nomes das colunas no código (ex: `type`) e no esquema real da tabela (ex: `message_type`).
*   **Como evitar:** Consulte sempre o `Installer.php` antes de codar repositórios. Use `$wpdb->last_error` em logs de depuração para ver o motivo real da falha.

### ❌ Erro: Tenant ID nulo em processos de fundo ou CLI
*   **Causa:** O `TenantContext` muitas vezes depende do usuário logado, que não existe em Webhooks ou WP-CLI.
*   **Como evitar:** No `WebhookProcessor`, sempre resolva e defina o `TenantContext` manualmente usando o `phone_number_id` ou `waba_id` recebido antes de chamar qualquer Service.

---

## 4. Gerenciamento de Janela de Atendimento (24h Window)

### ❌ Erro: Expectativa de que o Template abre a janela
*   **Causa:** Confusão com as regras da Meta. Enviar um template **NÃO** abre a janela de 24 horas.
*   **Como evitar:** Apenas mensagens **inbound** (do cliente) abrem a janela. Explique isso claramente ao usuário e use logs verbosos para mostrar o estado da janela.

### ❌ Erro: Discrepância de fuso horário no cálculo da janela
*   **Causa:** O servidor de banco de dados e o servidor PHP podem estar em timezones diferentes, fazendo o cálculo de expiração falhar.
*   **Como evitar:** Use sempre `gmdate()` e `time()` (UTC) para cálculos de janela. Adicione logs verbosos incluindo `time_now_utc` e `raw_expires_at` para depurar no servidor de testes.

### ❌ Erro: Webhooks fora de ordem encurtando a janela
*   **Causa:** Webhooks recebidos com atraso podem processar mensagens antigas por cima de mensagens novas.
*   **Como evitar:** A janela só deve ser renovada se o `timestamp` da mensagem recebida for maior que o `last_customer_message_at` atual.

---

## 5. Frontend e JavaScript (app.js)

### ❌ Erro: Tela branca ao clicar em botões de salvar
*   **Causa:** O formulário está executando o envio padrão do HTML (recarregando a página).
*   **Como evitar:** Adicione `onsubmit="event.preventDefault(); return false;"` nas tags `<form>`.

### ❌ Erro: Erros de sintaxe quebrando o App todo
*   **Causa:** Chaves ou parênteses sobrando após edições.
*   **Como evitar:** Antes de salvar, rode `node --check assets/js/app.js` no terminal.

---

## 6. Click-to-WhatsApp Ads & Referrals

### ❌ Erro: Perda de dados do anúncio (Referral)
*   **Causa:** A estrutura de dados do objeto `referral` é aninhada e complexa.
*   **Como evitar:** Use normalizadores dedicados para extrair `ctwa_clid`, `headline` e `body`. Persista em tabelas de auditoria.

---

## 7. Procedimento de Validação Obrigatório (Checklist)

Antes de finalizar qualquer alteração:

1.  **PHP Lint**: `find . -name "*.php" -not -path "./vendor/*" -exec php -l {} \; | grep -v "No syntax errors"`
2.  **JS Check**: `node --check assets/js/app.js`
3.  **DB Version**: Se mudou o banco, subiu o `WAS_DB_VERSION`?
4.  **Tenant Context**: A lógica funciona em processos de background (Webhooks)?

---

**Regra de Ouro para a IA:**
> "Nunca assuma que o banco de dados está populado ou que os nomes de colunas são óbvios. Valide a sintaxe, verifique os logs de auditoria e sempre considere o contexto multiempresa (Tenant)."
