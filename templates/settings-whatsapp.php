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
            <p>Você precisa configurar o <strong>Meta App ID</strong> antes de conectar uma conta WhatsApp. Vá em <a href="<?php echo \WAS\Core\URLService::get_meta_settings_url(); ?>">Configurações Meta</a>.</p>
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

                <!-- Nova seção de Verificação de Conexão -->
                <div class="was-verify-connection-box" style="margin: 20px 0; padding: 15px; border: 1px solid #ddd; border-radius: 6px; background: #f9f9f9;">
                    <h3>Diagnóstico de Conexão</h3>
                    <p class="description">Execute um teste completo para validar se sua integração com a Meta está 100% operacional.</p>
                    <button id="was-btn-check-connection" class="button">Verificar conexão oficial</button>
                    
                    <div id="was-verify-results" style="display:none; margin-top: 15px;">
                        <ul id="was-verify-list" style="list-style: none; padding: 0;">
                            <!-- Preenchido via JS -->
                        </ul>
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

        // Nota: A lógica de estado (checkStatus) e botões agora é gerenciada pelo app.js
        </script>
    <?php endif; ?>
</div>
