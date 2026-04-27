<?php
namespace WAS\Templates;

use WAS\Meta\MetaApiClient;
use WAS\Meta\TokenService;
use WAS\WhatsApp\AccountService;

if (!defined('ABSPATH')) {
    exit;
}

class TemplateMetaService {
    private $api_client;

    public function __construct() {
        $this->api_client = new MetaApiClient();
    }

    public function list(string $wabaId, string $token): array {
        return $this->api_client->get(
            'templates.list',
            ['waba_id' => $wabaId],
            ['fields' => 'id,name,status,category,language,components,rejected_reason'],
            $token
        );
    }

    public function get(string $templateId, string $token): array {
        return $this->api_client->get(
            'templates.get',
            ['template_id' => $templateId],
            ['fields' => 'id,name,status,category,language,components,rejected_reason'],
            $token
        );
    }

    public function create(string $wabaId, array $metaPayload, string $token): array {
        return $this->api_client->postJson(
            'templates.create',
            ['waba_id' => $wabaId],
            $metaPayload,
            $token
        );
    }

    public function update(string $templateId, array $metaPayload, string $token): array {
        return $this->api_client->postJson(
            'templates.update',
            ['template_id' => $templateId],
            $metaPayload,
            $token
        );
    }

    public function deleteByName(string $wabaId, string $name, string $token): array {
        return $this->api_client->delete(
            'templates.delete_by_name',
            ['waba_id' => $wabaId],
            ['name' => $name],
            $token
        );
    }

    public function deleteById(string $templateId, string $token): array {
        return $this->api_client->delete(
            'templates.delete_by_id',
            ['template_id' => $templateId],
            [],
            $token
        );
    }
}
