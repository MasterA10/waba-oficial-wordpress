<?php

namespace WAS\Templates;

use WAS\Compliance\AuditLogger;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Serviço de Sincronização de Templates
 */
class TemplateSyncService {
    private $repository;

    public function __construct(TemplateRepository $repository) {
        $this->repository = $repository;
    }

    /**
     * Sincroniza templates da Meta para o banco local
     */
    public function sync() {
        if (\WAS\Core\Plugin::is_demo_mode()) {
            return $this->sync_demo();
        }

        // 1. Aqui seria a chamada para MetaApiClient::request('WA_LIST_TEMPLATES')
        return 0; // Ainda não implementado real dispatch
    }

    private function sync_demo() {
        $meta_templates = [
            [
                'id' => 'tpl_demo_1',
                'name' => 'confirmacao_pedido',
                'language' => 'pt_BR',
                'category' => 'UTILITY',
                'status' => 'APPROVED',
                'body' => 'Olá! Seu pedido {{1}} foi confirmado e está sendo preparado.'
            ],
            [
                'id' => 'tpl_demo_2',
                'name' => 'oferta_exclusiva',
                'language' => 'pt_BR',
                'category' => 'MARKETING',
                'status' => 'APPROVED',
                'body' => 'Ei! Temos uma oferta especial de {{1}}% para você. Use o cupom {{2}}.'
            ],
            [
                'id' => 'tpl_demo_3',
                'name' => 'codigo_acesso',
                'language' => 'pt_BR',
                'category' => 'AUTHENTICATION',
                'status' => 'APPROVED',
                'body' => 'Seu código de segurança é: {{1}}. Não compartilhe com ninguém.'
            ]
        ];

        foreach ($meta_templates as $tpl) {
            $this->repository->createOrUpdate([
                'meta_template_id' => $tpl['id'],
                'name'             => $tpl['name'],
                'language'         => $tpl['language'],
                'category'         => $tpl['category'],
                'status'           => $tpl['status'],
                'body_text'        => $tpl['body'],
                'updated_at'       => current_time('mysql', 1)
            ]);
        }

        AuditLogger::log('sync_templates', 'waba', 0, ['count' => count($meta_templates), 'mode' => 'demo']);
        return count($meta_templates);
    }
}
