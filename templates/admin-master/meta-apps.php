<?php
/**
 * Master Admin Meta Apps Template
 */
if (!defined('ABSPATH')) exit;
?>
<div class="wrap">
    <h1>Gerenciamento de Apps Meta</h1>
    <p class="description">Gerencie as aplicações Meta (Facebook) que sua plataforma utiliza.</p>

    <div class="was-actions-bar" style="margin: 20px 0;">
        <button id="master-btn-add-app" class="button button-primary">+ Novo App Meta</button>
    </div>

    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th>App</th>
                <th>App ID</th>
                <th>Graph Versão</th>
                <th>Config ID</th>
                <th>Ambiente</th>
                <th>Status</th>
                <th>Ações</th>
            </tr>
        </thead>
        <tbody id="master-apps-list">
            <tr><td colspan="7">Carregando apps...</td></tr>
        </tbody>
    </table>
</div>
