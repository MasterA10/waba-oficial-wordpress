<?php
namespace WAS\Templates;

use RuntimeException;

if (!defined('ABSPATH')) {
    exit;
}

final class TemplatePayloadBuilder
{
    public function build(array $friendly): array
    {
        $components = [];
        $variableMap = [];

        if (!empty($friendly['header']) && $this->shouldAddHeader($friendly['header'])) {
            $components[] = $this->buildHeader($friendly['header']);
        }

        $bodyResult = $this->buildBody($friendly['body'] ?? []);
        $components[] = $bodyResult['component'];
        $variableMap = $bodyResult['variable_map'];

        if (!empty(trim($friendly['footer']['text'] ?? ''))) {
            $components[] = [
                'type' => 'FOOTER',
                'text' => trim($friendly['footer']['text']),
            ];
        }

        if (!empty($friendly['buttons']) && is_array($friendly['buttons'])) {
            $buttons = $this->buildButtons($friendly['buttons'], $variableMap);

            if (!empty($buttons)) {
                $components[] = [
                    'type' => 'BUTTONS',
                    'buttons' => $buttons,
                ];
            }
        }

        $metaPayload = [
            'name' => $this->normalizeName($friendly['name'] ?? ''),
            'category' => strtoupper($friendly['category'] ?? ''),
            'language' => $friendly['language'] ?? 'pt_BR',
            'components' => $components,
        ];

        return [
            'meta_payload' => $metaPayload,
            'variable_map' => $variableMap,
        ];
    }

    private function shouldAddHeader(array $header): bool
    {
        $type = strtoupper($header['type'] ?? 'NONE');

        if ($type === 'NONE') {
            return false;
        }

        if ($type === 'TEXT') {
            return trim((string) ($header['text'] ?? '')) !== '';
        }

        if (in_array($type, ['IMAGE', 'VIDEO', 'DOCUMENT'], true)) {
            return !empty($header['media_handle']);
        }

        return false;
    }

    private function buildHeader(array $header): array
    {
        $type = strtoupper($header['type'] ?? 'NONE');

        if ($type === 'TEXT') {
            return [
                'type' => 'HEADER',
                'format' => 'TEXT',
                'text' => trim($header['text']),
            ];
        }

        if (in_array($type, ['IMAGE', 'VIDEO', 'DOCUMENT'], true)) {
            return [
                'type' => 'HEADER',
                'format' => $type,
                'example' => [
                    'header_handle' => [
                        $header['media_handle'],
                    ],
                ],
            ];
        }

        throw new RuntimeException('Header inválido.');
    }

    private function buildBody(array $body): array
    {
        $text = trim((string) ($body['text'] ?? ''));

        if ($text === '') {
            throw new RuntimeException('O corpo do template é obrigatório.');
        }

        $examples = [];

        foreach (($body['variables'] ?? []) as $variable) {
            $key = $variable['key'] ?? '';
            $value = $variable['example'] ?? '';

            if ($key !== '') {
                $examples[$key] = $value;
            }
        }

        $parsed = $this->parseVariables($text, $examples);

        $component = [
            'type' => 'BODY',
            'text' => $parsed['text'],
        ];

        if (!empty($parsed['examples'])) {
            $component['example'] = [
                'body_text' => [
                    $parsed['examples'],
                ],
            ];
        }

        return [
            'component' => $component,
            'variable_map' => $parsed['variable_map'],
        ];
    }

    private function parseVariables(string $text, array $examples): array
    {
        preg_match_all('/{{\s*([a-zA-Z0-9_]+)\s*}}/', $text, $matches);

        $names = array_values(array_unique($matches[1] ?? []));

        $variableMap = [];
        $exampleValues = [];
        $metaText = $text;

        foreach ($names as $index => $name) {
            $position = $index + 1;

            if (!isset($examples[$name]) || trim((string) $examples[$name]) === '') {
                throw new RuntimeException("A variável {{$name}} precisa de exemplo.");
            }

            $variableMap[(string) $position] = $name;
            $exampleValues[] = (string) $examples[$name];

            $metaText = preg_replace(
                '/{{\s*' . preg_quote($name, '/') . '\s*}}/',
                '{{' . $position . '}}',
                $metaText
            );
        }

        return [
            'text' => $metaText,
            'variable_map' => $variableMap,
            'examples' => $exampleValues,
        ];
    }

    private function buildButtons(array $buttons, array $variableMap): array
    {
        $result = [];

        foreach ($buttons as $button) {
            $type = strtoupper($button['type'] ?? '');

            if ($type === 'QUICK_REPLY') {
                if (!empty(trim($button['text'] ?? ''))) {
                    $result[] = [
                        'type' => 'QUICK_REPLY',
                        'text' => trim($button['text']),
                    ];
                }
            }

            if ($type === 'PHONE_NUMBER') {
                $result[] = [
                    'type' => 'PHONE_NUMBER',
                    'text' => trim($button['text']),
                    'phone_number' => trim($button['phone_number']),
                ];
            }

            if ($type === 'URL') {
                $url = $this->convertUrlVariables($button['url'], $variableMap);

                $payload = [
                    'type' => 'URL',
                    'text' => trim($button['text']),
                    'url' => $url,
                ];

                if (str_contains($url, '{{') && !empty($button['example'])) {
                    $payload['example'] = [
                        (string) $button['example'],
                    ];
                }

                $result[] = $payload;
            }

            if ($type === 'COPY_CODE') {
                $result[] = [
                    'type' => 'COPY_CODE',
                    'example' => (string) $button['example'],
                ];
            }
        }

        return $result;
    }

    private function convertUrlVariables(string $url, array $variableMap): string
    {
        foreach ($variableMap as $position => $name) {
            $url = preg_replace(
                '/{{\s*' . preg_quote($name, '/') . '\s*}}/',
                '{{' . $position . '}}',
                $url
            );
        }

        return $url;
    }

    private function normalizeName(string $name): string
    {
        if (function_exists('remove_accents')) {
            $name = strtolower(remove_accents($name));
        } else {
            $name = strtolower($name);
        }
        $name = preg_replace('/[^a-z0-9_]+/', '_', $name);
        $name = trim($name, '_');

        if ($name === '') {
            throw new RuntimeException('Nome do template inválido.');
        }

        return $name;
    }
}
