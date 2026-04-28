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
            <div id="was-whatsapp-setup-app">
                <!-- Nova seção de Verificação de Conexão -->
                <div class="was-verify-connection-box" style="margin: 20px 0; padding: 24px; border: 1px solid #e2e8f0; border-radius: 12px; background: #fff; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
                    <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 20px;">
                        <div>
                            <h2 style="margin:0; font-size: 1.25rem;">Diagnóstico de Conexão Oficial</h2>
                            <p class="description">Valide se sua integração com a Meta Cloud API está 100% operacional.</p>
                        </div>
                        <button id="was-btn-check-connection" class="button button-primary">Verificar conexão oficial</button>
                    </div>

                    <div id="was-verify-results" style="display:none;">
                        <div id="was-verify-list" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 16px;">
                            <!-- Preenchido via JS -->
                        </div>
                    </div>
                </div>

                <div class="card" style="max-width: 800px; margin-top: 20px;">
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
