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

### ❌ Erro: Falha silenciosa no salvamento (insert_id = 0)
*   **Causa:** Discrepância entre os nomes das colunas no código (ex: `type`) e no esquema real da tabela (ex: `message_type`).
*   **Como evitar:** Consulte sempre o `Installer.php` antes de codar repositórios. Use `$wpdb->last_error` em logs de depuração para ver o motivo real da falha.

### ❌ Erro: Consulta falhando com múltiplos parâmetros no `$wpdb->prepare`
*   **Causa:** Passar um array diretamente para o `prepare` sem o operador de espalhamento (spread operator `...`).
*   **Como evitar:** Use sempre `$wpdb->prepare($query, ...$params)`.

### ❌ Erro: Tenant ID nulo em ambiente de terminal (CLI)
*   **Causa:** O `TenantContext` depende do usuário logado, que não existe no WP-CLI.
*   **Como evitar:** Implemente um fallback para `tenant_id = 1` quando `defined('WP_CLI')` for verdadeiro, apenas para fins de teste e manutenção.

### ❌ Erro: "Unknown column" após subir arquivos para produção
*   **Causa:** O banco de dados no servidor remoto não possui as colunas novas criadas localmente durante o desenvolvimento.
*   **Como evitar:** 
    *   Sempre utilize a lógica de `check_database_update` no `Plugin::boot()`.
    *   Incremente `WAS_DB_VERSION` em `Constants.php` sempre que alterar o schema em `Installer.php`.
    *   Isso forçará a execução do `dbDelta` no servidor assim que os arquivos forem carregados e o plugin inicializado.

---

## 4. Frontend e JavaScript (app.js)

### ❌ Erro: Tela branca ao clicar em botões de salvar
*   **Causa:** O formulário está executando o envio padrão do HTML (recarregando a página) porque o JavaScript não interceptou o evento (geralmente por erro de sintaxe ou cache do JS antigo).
*   **Como evitar:** 
    *   Sempre adicione `onsubmit="event.preventDefault(); return false;"` nas tags `<form>`.
    *   Prefira usar `<button type="button">` em vez de `type="submit"` para botões controlados via AJAX.
    *   Incremente `WAS_VERSION` para quebrar o cache do navegador no servidor de produção.

### ❌ Erro: Telas pararem de carregar (Templates/Logs em branco)
*   **Causa:** Erro de sintaxe no `app.js` (chaves ou parênteses sobrando após edições manuais/ferramentas).
*   **Como evitar:** Antes de salvar qualquer alteração em arquivos JS, rode `node --check assets/js/app.js` no terminal.

### ❌ Erro: "Alterei o código mas no navegador não mudou nada"
*   **Causa:** Cache do navegador mantendo a versão antiga do arquivo `.js` ou `.css`.
*   **Como evitar:** Sempre incremente a constante `WAS_VERSION` e garanta que o `wp_enqueue_script` utilize essa versão como parâmetro de query string.

---

## 5. Integração Meta API

### ❌ Erro: "WABA ID ou Token não configurado" na criação de templates
*   **Causa:** O sistema usou IDs de teste ou o banco de dados não tinha o WABA ID real associado à conta.
*   **Como evitar:** Certifique-se de que a tabela `was_whatsapp_accounts` tenha o WABA ID real e que ele seja carregado no `TemplateMetaService`.

---

## 6. Procedimento de Validação Obrigatório (Checklist de Salvamento)

Antes de finalizar qualquer alteração em arquivos de lógica, **é obrigatório** rodar as seguintes verificações no terminal para evitar que o plugin quebre o site do cliente:

### ✅ Validação PHP (Lint)
Impede erros fatais de sintaxe (como o `unexpected token "..."`).
```bash
# Validar um arquivo específico
php -l includes/Caminho/Arquivo.php

# Validar todos os arquivos do plugin de uma vez
find . -name "*.php" -not -path "./vendor/*" -exec php -l {} \; | grep -v "No syntax errors detected"
```

### ✅ Validação JavaScript (Node.js)
Impede que as telas do painel parem de funcionar.
```bash
# Validar o arquivo principal de scripts
node --check assets/js/app.js

# Validar todos os arquivos .js
find . -name "*.js" -not -path "./node_modules/*" -exec node --check {} \;
```

---

**Regra de Ouro para a IA:**
> "Nunca assuma que o banco de dados está populado ou que os nomes de colunas são óbvios. Valide a sintaxe, verifique os logs de auditoria e sempre considere o contexto multiempresa (Tenant)."
