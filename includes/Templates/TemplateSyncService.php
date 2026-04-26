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
        // 1. Aqui seria a chamada para MetaApiClient::request('WA_LIST_TEMPLATES')
        // Por enquanto simulamos uma resposta da Meta
        $meta_templates = [
            [
                'name' => 'hello_world',
                'language' => 'en_US',
                'category' => 'UTILITY',
                'status' => 'APPROVED',
                'id' => '123456789'
            ]
        ];

        foreach ($meta_templates as $meta_data) {
            $this->repository->createOrUpdate([
                'meta_template_id' => $meta_data['id'],
                'name'             => $meta_data['name'],
                'language'         => $meta_data['language'],
                'category'         => $meta_data['category'],
                'status'           => $meta_data['status'],
                'components_json'  => json_encode([]) // Seria preenchido com dados reais
            ]);
        }

        AuditLogger::log('sync_templates', 'waba', 0, ['count' => count($meta_templates)]);

        return count($meta_templates);
    }
}
