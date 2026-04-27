<?php
namespace WAS\Meta;

if (!defined('ABSPATH')) {
    exit;
}

class MetaEndpointRegistry {
    public static function resolve(string $operation, array $params = []): string {
        $map = [
            'templates.create' => '/{waba_id}/message_templates',
            'templates.list'   => '/{waba_id}/message_templates',
            'templates.delete_by_name' => '/{waba_id}/message_templates',
            'templates.get'    => '/{template_id}',
            'templates.update' => '/{template_id}',
            'templates.delete_by_id' => '/{template_id}',

            'messages.send'    => '/{phone_number_id}/messages',

            'media.upload'     => '/{phone_number_id}/media',
            'media.get'        => '/{media_id}',
            'media.delete'     => '/{media_id}',
        ];

        if (!isset($map[$operation])) {
            return '';
        }

        $path = $map[$operation];
        foreach ($params as $key => $value) {
            $path = str_replace('{' . $key . '}', (string)$value, $path);
        }

        return $path;
    }
}
