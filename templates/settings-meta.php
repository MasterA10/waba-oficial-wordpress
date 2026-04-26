<?php
/**
 * Template para Configuração do Meta App
 */
if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="wrap">
    <h1>Configurações do Meta App</h1>
    <hr>

    <div id="was-meta-settings-app">
        <div class="card" style="max-width: 800px; margin-top: 20px;">
            <h2>Credenciais do Aplicativo</h2>
            <p class="description">Configure aqui as credenciais do seu aplicativo Meta (Facebook) para habilitar a integração com o WhatsApp.</p>
            
            <form id="was-meta-config-form">
                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="app_id">App ID</label></th>
                        <td>
                            <input name="app_id" type="text" id="app_id" value="" class="regular-text" required>
                            <p class="description">O ID do seu aplicativo no <a href="https://developers.facebook.com/apps/" target="_blank">Meta for Developers</a>.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="app_secret">App Secret</label></th>
                        <td>
                            <input name="app_secret" type="password" id="app_secret" value="" class="regular-text">
                            <p class="description">O segredo do seu aplicativo. Será salvo criptografado.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="graph_version">Graph API Version</label></th>
                        <td>
                            <input name="graph_version" type="text" id="graph_version" value="v25.0" class="small-text">
                            <p class="description">Versão da Graph API (ex: v25.0).</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="verify_token">Webhook Verify Token</label></th>
                        <td>
                            <div style="display: flex; gap: 10px; align-items: center;">
                                <input name="verify_token" type="text" id="verify_token" value="" class="regular-text">
                                <button type="button" id="was-generate-token" class="button">Gerar</button>
                            </div>
                            <p class="description">Token usado para validar o webhook na configuração da Meta.</p>
                        </td>
                    </tr>
                </table>

                <p class="submit">
                    <button type="submit" name="submit" id="submit" class="button button-primary">Salvar Configurações</button>
                    <span id="was-save-status" style="margin-left: 10px;"></span>
                </p>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('was-meta-config-form');
    const status = document.getElementById('was-save-status');
    const generateBtn = document.getElementById('was-generate-token');
    
    const restBase = '<?php echo esc_url_raw(rest_url(WAS_REST_NAMESPACE . '/meta/config')); ?>';
    const nonce = '<?php echo wp_create_nonce('wp_rest'); ?>';

    // Carregar configurações atuais
    fetch(restBase, {
        headers: { 'X-WP-Nonce': nonce }
    })
    .then(response => response.json())
    .then(data => {
        if (data) {
            if (data.app_id) document.getElementById('app_id').value = data.app_id;
            if (data.app_secret) document.getElementById('app_secret').value = data.app_secret;
            if (data.graph_version) document.getElementById('graph_version').value = data.graph_version;
            if (data.verify_token) document.getElementById('verify_token').value = data.verify_token;
        }
    });

    // Gerar token aleatório
    generateBtn.addEventListener('click', function() {
        const charset = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789";
        let token = "";
        for (let i = 0; i < 32; i++) {
            token += charset.charAt(Math.floor(Math.random() * charset.length));
        }
        document.getElementById('verify_token').value = token;
    });

    // Salvar configurações
    form.addEventListener('submit', function(e) {
        e.preventDefault();
        status.textContent = 'Salvando...';
        status.style.color = 'inherit';

        const formData = {
            app_id: document.getElementById('app_id').value,
            app_secret: document.getElementById('app_secret').value,
            graph_version: document.getElementById('graph_version').value,
            verify_token: document.getElementById('verify_token').value
        };

        fetch(restBase, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-WP-Nonce': nonce
            },
            body: JSON.stringify(formData)
        })
        .then(response => response.json())
        .then(data => {
            status.textContent = 'Salvo com sucesso!';
            status.style.color = 'green';
            setTimeout(() => { status.textContent = ''; }, 3000);
        })
        .catch(err => {
            status.textContent = 'Erro ao salvar.';
            status.style.color = 'red';
        });
    });
});
</script>
