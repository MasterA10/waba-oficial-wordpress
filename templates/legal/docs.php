<?php
/**
 * Template for Documentation Page - Placeholder
 */
if (!defined('ABSPATH')) {
    exit;
}
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php _e('Documentação - Dominai Cloud', 'whatsapp-saas-core'); ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #2563eb;
            --primary-soft: #eff6ff;
            --text-main: #1e293b;
            --text-muted: #64748b;
            --bg-app: #f8fafc;
            --card-bg: #ffffff;
            --border-color: #e2e8f0;
        }
        
        body {
            font-family: 'Inter', -apple-system, system-ui, sans-serif;
            line-height: 1.7;
            color: var(--text-main);
            background-color: var(--bg-app);
            margin: 0;
            padding: 0;
            -webkit-font-smoothing: antialiased;
        }

        .docs-wrapper {
            max-width: 1000px;
            margin: 60px auto;
            padding: 0 20px;
            display: grid;
            grid-template-columns: 280px 1fr;
            gap: 40px;
        }

        .docs-sidebar {
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 16px;
            padding: 30px;
            height: fit-content;
            position: sticky;
            top: 40px;
        }

        .docs-content {
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 16px;
            padding: 60px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
        }

        .sidebar-nav h4 {
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: var(--text-muted);
            margin-bottom: 16px;
        }

        .sidebar-nav ul {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .sidebar-nav li {
            margin-bottom: 8px;
        }

        .sidebar-nav a {
            text-decoration: none;
            color: var(--text-main);
            font-size: 0.9375rem;
            font-weight: 500;
            display: block;
            padding: 8px 12px;
            border-radius: 6px;
            transition: all 0.2s;
        }

        .sidebar-nav a:hover {
            background: var(--primary-soft);
            color: var(--primary);
        }

        .badge {
            display: inline-block;
            background: #fef3c7;
            color: #92400e;
            padding: 4px 12px;
            border-radius: 99px;
            font-size: 0.75rem;
            font-weight: 600;
            margin-bottom: 16px;
        }

        header h1 {
            font-size: 2.25rem;
            font-weight: 800;
            color: #0f172a;
            margin: 0 0 16px 0;
        }

        .placeholder-message {
            background: var(--bg-app);
            border: 2px dashed var(--border-color);
            padding: 60px;
            border-radius: 12px;
            text-align: center;
            margin-top: 40px;
        }

        .placeholder-icon {
            font-size: 48px;
            margin-bottom: 20px;
        }

        @media (max-width: 900px) {
            .docs-wrapper { grid-template-columns: 1fr; }
            .docs-sidebar { display: none; }
        }
    </style>
</head>
<body>

<div class="docs-wrapper">
    <aside class="docs-sidebar">
        <nav class="sidebar-nav">
            <h4>Primeiros Passos</h4>
            <ul>
                <li><a href="#">Introdução</a></li>
                <li><a href="#">Instalação</a></li>
                <li><a href="#">Configuração Inicial</a></li>
            </ul>
            <br>
            <h4>Integrações</h4>
            <ul>
                <li><a href="#">Meta App Setup</a></li>
                <li><a href="#">Embedded Signup</a></li>
                <li><a href="#">Webhooks</a></li>
            </ul>
        </nav>
    </aside>

    <main class="docs-content">
        <header>
            <span class="badge">Em breve</span>
            <h1>Documentação Técnica</h1>
            <p>Tudo o que você precisa para dominar a API Oficial do WhatsApp com Dominai Cloud.</p>
        </header>

        <div class="placeholder-message">
            <div class="placeholder-icon">🚧</div>
            <h3>Conteúdo em Construção</h3>
            <p>Estamos preparando um material completo com tutoriais passo a passo, exemplos de código e melhores práticas para sua operação.</p>
            <p style="color: var(--text-muted); font-size: 0.875rem; margin-top: 20px;">
                Precisa de ajuda imediata? <a href="<?php echo home_url('/support'); ?>" style="color: var(--primary); font-weight: 600;">Fale com o Suporte</a>
            </p>
        </div>
    </main>
</div>

</body>
</html>
