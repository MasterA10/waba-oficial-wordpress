<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - WhatsApp SaaS Core</title>
    <link rel="stylesheet" href="<?php echo WAS_PLUGIN_URL; ?>assets/css/app.css">
    <?php wp_head(); ?>
</head>
<body class="was-login-body">
    <div class="was-login-container">
        <div class="was-login-box">
            <h1>WABA SaaS</h1>
            <p>Faça login para acessar sua conta</p>

            <?php if (!empty($errors)): ?>
                <div class="was-errors">
                    <?php foreach($errors as $error): ?>
                        <p><?php echo esc_html($error); ?></p>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <form action="" method="post">
                <?php wp_nonce_field('was_login_action', 'was_login_nonce'); ?>
                <div class="was-form-group">
                    <label for="log">E-mail ou Usuário</label>
                    <input type="text" name="log" id="log" required>
                </div>
                <div class="was-form-group">
                    <label for="pwd">Senha</label>
                    <input type="password" name="pwd" id="pwd" required>
                </div>
                <div class="was-form-group checkbox">
                    <input type="checkbox" name="rememberme" id="rememberme">
                    <label for="rememberme">Lembrar-me</label>
                </div>
                <button type="submit" class="was-btn">Entrar</button>
            </form>
        </div>
        <div class="was-login-footer">
            <a href="<?php echo home_url('/privacy-policy'); ?>">Privacidade</a>
            <a href="<?php echo home_url('/terms-of-service'); ?>">Termos de Uso</a>
        </div>
    </div>
    <?php wp_footer(); ?>
</body>
</html>
