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
        
        // 2. Converte Friendly para Meta
        $metaPayload = TemplatePayloadBuilder::build($data);

        // 3. Salvar Localmente primeiro como 'draft'
        $template_id = $this->repository->createOrUpdate([
            'name'             => $data['name'],
            'language'         => $data['language'],
            'category'         => $data['category'],
            'status'           => 'PENDING',
            'friendly_payload' => json_encode($data),
            'meta_payload'     => json_encode($metaPayload),
            'body_text'        => $data['body']['text'],
            'header_type'      => $data['header']['type'] ?? 'NONE',
            'footer_text'      => $data['footer']['text'] ?? '',
            'buttons_json'     => json_encode($data['buttons'] ?? [])
        ]);

        // 4. Auditoria
        \WAS\Compliance\AuditLogger::log('create_template', 'template', $template_id, ['name' => $data['name']]);

        // 5. Aqui seria a chamada real para MetaApiClient (Dev 02)
        // MetaApiClient::request('WA_CREATE_TEMPLATE', ['waba_id' => ...], $metaPayload);
        
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
