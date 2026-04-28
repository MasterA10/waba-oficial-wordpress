<?php
/**
 * Master Admin Tenants Template
 */
if (!defined('ABSPATH')) exit;
?>
<div class="wrap">
    <h1>Gerenciamento de Clientes / Tenants</h1>
    <p class="description">Visualize e gerencie todos os clientes da plataforma.</p>

    <div class="was-actions-bar" style="margin: 20px 0;">
        <button id="master-btn-add-tenant" class="button button-primary">+ Novo Cliente</button>
    </div>

    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th>Cliente</th>
                <th>Slug</th>
                <th>Plano</th>
                <th>Status</th>
                <th>Criado em</th>
                <th>Ações</th>
            </tr>
        </thead>
        <tbody id="master-tenants-list">
            <tr><td colspan="6">Carregando clientes...</td></tr>
        </tbody>
    </table>
</div>
