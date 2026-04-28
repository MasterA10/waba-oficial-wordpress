<?php
/**
 * Master Admin Templates Template
 */
if (!defined('ABSPATH')) exit;
?>
<div class="wrap">
    <h1>Gerenciamento de Templates Globais</h1>
    <p class="description">Visualize todos os modelos de mensagens de todos os clientes.</p>

    <table class="wp-list-table widefat fixed striped" style="margin-top: 20px;">
        <thead>
            <tr>
                <th>Cliente</th>
                <th>Nome do Template</th>
                <th>Categoria</th>
                <th>Idioma</th>
                <th>Status</th>
                <th>Ações</th>
            </tr>
        </thead>
        <tbody id="master-templates-list">
            <tr><td colspan="6">Carregando templates...</td></tr>
        </tbody>
    </table>
</div>
