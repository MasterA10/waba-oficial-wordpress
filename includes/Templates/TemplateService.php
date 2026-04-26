<?php

namespace WAS\Templates;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Serviço de Templates (Orquestrador)
 */
class TemplateService {
    private $repository;

    public function __construct(TemplateRepository $repository) {
        $this->repository = $repository;
    }

    /**
     * Lista templates locais do tenant
     */
    public function listTemplates($status = null) {
        return $this->repository->listByTenant($status);
    }

    /**
     * Cria um novo template (Local + Meta)
     */
    public function createTemplate(array $data) {
        // 1. Validar e Sanitizar Nome
        $data['name'] = strtolower(preg_replace('/[^a-z0-9_]/', '', $data['name']));
        
        // 2. Salvar Localmente primeiro como 'pending'
        $template_id = $this->repository->createOrUpdate([
            'name'            => $data['name'],
            'language'        => $data['language'],
            'category'        => $data['category'],
            'status'          => 'PENDING',
            'components_json' => json_encode([
                ['type' => 'BODY', 'text' => $data['body_text']]
            ])
        ]);

        // 3. Auditoria
        \WAS\Compliance\AuditLogger::log('create_template', 'template', $template_id, ['name' => $data['name']]);

        // 4. Aqui seria a chamada para MetaApiClient (Dev 02)
        // Por enquanto, simulamos sucesso ou erro
        
        return $template_id;
    }

    /**
     * Envia mensagem usando template
     */
    public function sendTemplateMessage($to, $template_id, $components = []) {
        $template = $this->repository->find($template_id);
        if (!$template || ($template->status !== 'APPROVED' && $template->status !== 'approved')) {
            throw new \Exception("Template não encontrado ou não aprovado.");
        }

        // Auditoria
        \WAS\Compliance\AuditLogger::log('send_template', 'contact', 0, ['template_name' => $template->name, 'to' => $to]);

        // Aqui chamaria o MessageDispatchService (Dev 02)
        // return $dispatchService->sendTemplate($to, $template, $components);
        return true;
    }
}
