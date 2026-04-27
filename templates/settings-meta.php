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
                        <th scope="row"><label for="webhook_url">Callback URL</label></th>
                        <td>
                            <input type="text" id="webhook_url" value="Carregando..." class="large-text" readonly onclick="this.select();">
                            <p class="description">Copie esta URL e cole no campo "URL de retorno de chamada" no painel da Meta Developers.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="verify_token">Webhook Verify Token</label></th>
                        <td>
                            <div style="display: flex; gap: 10px; align-items: center;">
                                <input name="verify_token" type="text" id="verify_token" value="" class="regular-text">
                                <button type="button" id="was-generate-token" class="button">Gerar Novo</button>
                            </div>
                            <script>
                                document.getElementById('was-generate-token').addEventListener('click', function() {
                                    const randomToken = Array.from(crypto.getRandomValues(new Uint8Array(16)))
                                        .map(b => b.toString(16).padStart(2, '0'))
                                        .join('');
                                    document.getElementById('verify_token').value = randomToken;
                                });
                            </script>
                            <p class="description">Token usado para validar o webhook. Cole este valor no campo "Verificar token" na Meta.</p>
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
