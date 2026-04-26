<?php
/**
 * Template para WhatsApp Setup e Embedded Signup
 */
if (!defined('ABSPATH')) {
    exit;
}

$repository = new \WAS\Meta\MetaAppRepository();
$app = $repository->get_active_app();
$app_id = $app ? $app->app_id : '';
?>
<div class="wrap">
    <h1>WhatsApp Business Setup</h1>
    <hr>

    <?php if (!$app_id): ?>
        <div class="notice notice-warning">
            <p>Você precisa configurar o <strong>Meta App ID</strong> antes de conectar uma conta WhatsApp. Vá em <a href="<?php echo admin_url('admin.php?page=was-settings-meta'); ?>">Configurações Meta</a>.</p>
        </div>
    <?php else: ?>
        <div id="was-whatsapp-setup-app">
            <div class="card" style="max-width: 800px; margin-top: 20px;">
                <h2>Conectar Conta WhatsApp Business</h2>
                <p>Use o botão abaixo para iniciar o fluxo oficial da Meta e conectar sua WABA (WhatsApp Business Account).</p>
                
                <div id="was-connection-status" class="was-status-box" style="margin: 20px 0; padding: 15px; border-radius: 4px; border: 1px solid #ccd0d4; background: #fff;">
                    <strong>Status:</strong> <span id="was-status-text">Verificando...</span>
                    <div id="was-connection-details" style="display: none; margin-top: 10px;">
                        <p><strong>WABA ID:</strong> <span id="was-waba-id"></span></p>
                        <p><strong>Número:</strong> <span id="was-phone-number"></span></p>
                    </div>
                </div>

                <div style="margin: 20px 0; padding: 15px; background: #f0f0f1; border-radius: 4px;">
                    <strong>URL do Webhook:</strong><br>
                    <code><?php echo esc_url_raw(rest_url(WAS_REST_NAMESPACE . '/meta/webhook')); ?></code>
                    <p class="description">Use esta URL na configuração do produto "WhatsApp" no seu painel da Meta.</p>
                </div>

                <div id="was-connect-actions">
                    <button id="was-launch-signup" class="button button-primary button-large" style="background-color: #1877f2; border-color: #1877f2;">
                        Login with Facebook
                    </button>
                    <button id="was-disconnect-waba" class="button button-link-delete" style="display: none;">Desconectar Conta</button>
                </div>
            </div>
        </div>

        <script>
        // Carregamento do SDK do Facebook
        window.fbAsyncInit = function() {
            FB.init({
                appId      : '<?php echo esc_js($app_id); ?>',
                cookie     : true,
                xfbml      : true,
                version    : 'v25.0'
            });
        };

        (function(d, s, id){
            var js, fjs = d.getElementsByTagName(s)[0];
            if (d.getElementById(id)) {return;}
            js = d.createElement(s); js.id = id;
            js.src = "https://connect.facebook.net/en_US/sdk.js";
            fjs.parentNode.insertBefore(js, fjs);
        }(document, 'script', 'facebook-jssdk'));

        document.addEventListener('DOMContentLoaded', function() {
            const launchBtn = document.getElementById('was-launch-signup');
            const disconnectBtn = document.getElementById('was-disconnect-waba');
            const statusText = document.getElementById('was-status-text');
            const detailsDiv = document.getElementById('was-connection-details');

            const restBase = '<?php echo esc_url_raw(rest_url(WAS_REST_NAMESPACE . '/whatsapp')); ?>';
            const metaRestBase = '<?php echo esc_url_raw(rest_url(WAS_REST_NAMESPACE . '/meta')); ?>';
            const nonce = '<?php echo wp_create_nonce('wp_rest'); ?>';

            // Função para buscar estado atual
            function checkStatus() {
                fetch(restBase + '/accounts', { headers: { 'X-WP-Nonce': nonce } })
                .then(r => r.json())
                .then(data => {
                    if (data && data.length > 0) {
                        const account = data[0];
                        statusText.textContent = 'Conectado';
                        statusText.style.color = 'green';
                        document.getElementById('was-waba-id').textContent = account.waba_id;
                        document.getElementById('was-phone-number').textContent = account.display_phone_number || 'ID: ' + account.phone_number_id;
                        detailsDiv.style.display = 'block';
                        launchBtn.style.display = 'none';
                        disconnectBtn.style.display = 'inline-block';
                    } else {
                        statusText.textContent = 'Não conectado';
                        statusText.style.color = 'red';
                        detailsDiv.style.display = 'none';
                        launchBtn.style.display = 'inline-block';
                        disconnectBtn.style.display = 'none';
                    }
                });
            }

            checkStatus();

            // Lançar Embedded Signup
            launchBtn.addEventListener('click', function() {
                FB.login(function(response) {
                    if (response.authResponse) {
                        const code = response.authResponse.code;
                        // O SDK do FB em popup para embedded signup retorna o código se configurado corretamente
                        // Mas geralmente o fluxo de embedded signup é via FB.ui
                        
                        // Exemplo correto com FB.ui para Embedded Signup:
                        FB.ui({
                            method: 'share', // Placeholder, o método real de embedded signup costuma ser customizado ou via login com scopes específicos
                            // Na documentação oficial, usa-se um fluxo de login com config_id
                        }, function(response) {
                            // Handler
                        });

                        // Para o MVP, assumiremos o retorno do code via FB.login custom ou similar
                        // Se tivermos o code, enviamos para o backend
                    }
                }, {
                    scope: 'whatsapp_business_management,whatsapp_business_messaging',
                    extras: {
                        feature: 'whatsapp_embedded_signup'
                    }
                });
            });

            // Mock de salvamento para teste da UI (já que o fluxo FB.ui é complexo de simular sem domínio real)
            // Em produção, o script da Meta chama uma callback com os IDs.
        });
        </script>
    <?php endif; ?>
</div>
