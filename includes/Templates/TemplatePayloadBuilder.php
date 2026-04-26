<?php

namespace WAS\Templates;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Constrói o payload oficial da Meta a partir de dados amigáveis.
 */
class TemplatePayloadBuilder {

    /**
     * Transforma friendly_payload em meta_payload (components).
     */
    public static function build(array $friendly): array {
        $components = [];

        // 1. Header
        if (!empty($friendly['header']['type']) && $friendly['header']['type'] !== 'NONE') {
            $header = [
                'type'   => 'HEADER',
                'format' => $friendly['header']['type']
            ];

            if ($friendly['header']['type'] === 'TEXT') {
                $header['text'] = self::mapVariables($friendly['header']['text'], $friendly['variables'] ?? []);
                // Add example if variables present
                $vars = self::extractVars($friendly['header']['text']);
                if (!empty($vars)) {
                    $header['example'] = ['header_text' => [self::getExamples($vars, $friendly['variables'])]];
                }
            }

            $components[] = $header;
        }

        // 2. Body (Obrigatório)
        $bodyText = $friendly['body']['text'];
        $bodyVars = self::extractVars($bodyText);
        
        $body = [
            'type' => 'BODY',
            'text' => self::mapVariables($bodyText, $friendly['variables'] ?? [])
        ];

        if (!empty($bodyVars)) {
            $body['example'] = [
                'body_text' => [self::getExamples($bodyVars, $friendly['variables'])]
            ];
        }

        $components[] = $body;

        // 3. Footer
        if (!empty($friendly['footer']['text'])) {
            $components[] = [
                'type' => 'FOOTER',
                'text' => $friendly['footer']['text']
            ];
        }

        // 4. Buttons
        if (!empty($friendly['buttons'])) {
            $buttons = [];
            foreach ($friendly['buttons'] as $btn) {
                $b = [
                    'type' => $btn['type'],
                    'text' => $btn['text']
                ];

                if ($btn['type'] === 'URL') {
                    $b['url'] = self::mapVariables($btn['url'], $friendly['variables'] ?? []);
                    $urlVars = self::extractVars($btn['url']);
                    if (!empty($urlVars)) {
                        $b['example'] = self::getExamples($urlVars, $friendly['variables']);
                    }
                } elseif ($btn['type'] === 'PHONE_NUMBER') {
                    $b['phone_number'] = $btn['phone_number'];
                }

                $buttons[] = $b;
            }

            if (!empty($buttons)) {
                $components[] = [
                    'type'    => 'BUTTONS',
                    'buttons' => $buttons
                ];
            }
        }

        return [
            'name'       => $friendly['name'],
            'language'   => $friendly['language'],
            'category'   => $friendly['category'],
            'components' => $components
        ];
    }

    /**
     * Converte {{nome}} para {{1}}
     */
    private static function mapVariables(string $text, array $mapping): string {
        preg_match_all('/\{\{([^}]+)\}\}/', $text, $matches);
        if (empty($matches[1])) {
            return $text;
        }

        $index = 1;
        $processed = $text;
        $uniqueVars = array_unique($matches[1]);
        
        foreach ($uniqueVars as $varName) {
            $processed = str_replace('{{' . $varName . '}}', '{{' . $index . '}}', $processed);
            $index++;
        }

        return $processed;
    }

    /**
     * Extrai nomes de variáveis de um texto
     */
    private static function extractVars(string $text): array {
        preg_match_all('/\{\{([^}]+)\}\}/', $text, $matches);
        return !empty($matches[1]) ? array_unique($matches[1]) : [];
    }

    /**
     * Busca os valores de exemplo para uma lista de variáveis
     */
    private static function getExamples(array $varNames, array $config): array {
        $examples = [];
        foreach ($varNames as $name) {
            $found = false;
            foreach ($config as $c) {
                if ($c['key'] === $name) {
                    $examples[] = $c['example'];
                    $found = true;
                    break;
                }
            }
            if (!$found) {
                $examples[] = "Exemplo";
            }
        }
        return $examples;
    }
}
