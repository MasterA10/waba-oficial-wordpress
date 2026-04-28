<?php
/**
 * Master Admin Phones Template
 */
if (!defined('ABSPATH')) exit;
?>
<div class="wrap">
    <h1>Gerenciamento de Números WhatsApp</h1>
    <p class="description">Visualize todos os números de telefone registrados na plataforma.</p>

    <table class="wp-list-table widefat fixed striped" style="margin-top: 20px;">
        <thead>
            <tr>
                <th>Tenant</th>
                <th>Phone ID</th>
                <th>Número</th>
                <th>Nome Verificado</th>
                <th>Status</th>
                <th>Qualidade</th>
                <th>Padrão</th>
                <th>Ações</th>
            </tr>
        </thead>
        <tbody id="master-phones-list">
            <tr><td colspan="8">Carregando números...</td></tr>
        </tbody>
    </table>
</div>
