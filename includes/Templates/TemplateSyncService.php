<?php
namespace WAS\Templates;

if (!defined('ABSPATH')) {
    exit;
}

class TemplateSyncService {
    private $repository;
    private $meta_service;

    public function __construct() {
        $this->repository = new TemplateRepository();
        $this->meta_service = new TemplateMetaService();
    }

    /**
     * Sincroniza templates da Meta para o banco local.
     */
    public function sync($tenant_id) {
        $response = $this->meta_service->list_from_meta($tenant_id);

        if (!$response['success']) {
            return $response;
        }

        $templates = $response['data'] ?? [];
        $synced_count = 0;

        foreach ($templates as $meta_tpl) {
            $existing = $this->repository->get_by_name_lang($meta_tpl['name'], $meta_tpl['language']);

            $data = [
                'meta_template_id' => $meta_tpl['id'],
                'status'           => $meta_tpl['status'],
                'category'         => $meta_tpl['category'],
                'rejection_reason' => $meta_tpl['rejected_reason'] ?? null,
                'meta_payload'     => json_encode($meta_tpl),
                'updated_at'       => current_time('mysql', 1)
            ];

            // Extrair corpo e componentes para o banco local
            foreach ($meta_tpl['components'] as $comp) {
                if ($comp['type'] === 'BODY') {
                    $data['body_text'] = $comp['text'];
                } elseif ($comp['type'] === 'HEADER') {
                    $data['header_type'] = $comp['format'];
                    $data['header_text'] = $comp['text'] ?? null;
                } elseif ($comp['type'] === 'FOOTER') {
                    $data['footer_text'] = $comp['text'];
                } elseif ($comp['type'] === 'BUTTONS') {
                    $data['buttons_json'] = json_encode($comp['buttons']);
                }
            }

            if ($existing) {
                $this->repository->update($existing->id, $data);
            } else {
                $data['name'] = $meta_tpl['name'];
                $data['language'] = $meta_tpl['language'];
                $this->repository->create($data);
            }
            $synced_count++;
        }

        return ['success' => true, 'count' => $synced_count];
    }
}
