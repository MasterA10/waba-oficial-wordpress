<?php
namespace WAS\Templates;

if (!defined('ABSPATH')) {
    exit;
}

class TemplatePayloadBuilder {
    private $parser;

    public function __construct() {
        $this->parser = new TemplateVariableParser();
    }

    /**
     * Transforma friendly_payload em meta_payload oficial.
     */
    public function build(array $friendly): array {
        $components = [];

        // 1. HEADER
        if (!empty($friendly['header']) && $friendly['header']['type'] !== 'NONE') {
            $components[] = $this->build_header($friendly['header']);
        }

        // 2. BODY (Obrigatório)
        $body = $this->build_body($friendly['body']);
        $components[] = $body['component'];

        // 3. FOOTER
        if (!empty($friendly['footer']['text'])) {
            $components[] = [
                'type' => 'FOOTER',
                'text' => $friendly['footer']['text'],
            ];
        }

        // 4. BUTTONS
        if (!empty($friendly['buttons'])) {
            $components[] = [
                'type' => 'BUTTONS',
                'buttons' => $this->build_buttons($friendly['buttons'], $body['variable_map']),
            ];
        }

        return [
            'name' => $friendly['name'],
            'category' => $friendly['category'],
            'language' => $friendly['language'],
            'components' => $components,
            'variable_map' => $body['variable_map'] // Guardamos para o banco
        ];
    }

    private function build_header(array $header): array {
        if ($header['type'] === 'TEXT') {
            return [
                'type' => 'HEADER',
                'format' => 'TEXT',
                'text' => $header['text'],
            ];
        }
        // Futuro: Adicionar IMAGE, VIDEO, DOCUMENT
        return [];
    }

    private function build_body(array $body_data): array {
        $examples = [];
        foreach ($body_data['variables'] ?? [] as $var) {
            $examples[$var['key']] = $var['example'] ?? '';
        }

        $parsed = $this->parser->parse($body_data['text'], $examples);

        $component = [
            'type' => 'BODY',
            'text' => $parsed['meta_text'],
        ];

        if (!empty($parsed['example_values'])) {
            $component['example'] = [
                'body_text' => [ $parsed['example_values'] ]
            ];
        }

        return [
            'component' => $component,
            'variable_map' => $parsed['variable_map']
        ];
    }

    private function build_buttons(array $buttons, array $var_map): array {
        $meta_buttons = [];
        foreach ($buttons as $btn) {
            $type = strtoupper($btn['type']);
            if ($type === 'QUICK_REPLY') {
                $meta_buttons[] = ['type' => 'QUICK_REPLY', 'text' => $btn['text']];
            } elseif ($type === 'PHONE_NUMBER') {
                $meta_buttons[] = ['type' => 'PHONE_NUMBER', 'text' => $btn['text'], 'phone_number' => $btn['phone_number']];
            } elseif ($type === 'URL') {
                $url = $btn['url'];
                // Converte variáveis da URL se existirem no mapa do body
                foreach ($var_map as $pos => $name) {
                    $url = str_replace("{{{$name}}}", "{{{$pos}}}", $url);
                }
                $payload = ['type' => 'URL', 'text' => $btn['text'], 'url' => $url];
                if (!empty($btn['example'])) {
                    $payload['example'] = [$btn['example']];
                }
                $meta_buttons[] = $payload;
            }
        }
        return $meta_buttons;
    }
}
