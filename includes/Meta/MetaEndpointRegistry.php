<?php
namespace WAS\Meta;

if (!defined('ABSPATH')) {
    exit;
}

class MetaEndpointRegistry {
    public static function resolve(string $operation, array $params = []): string {
        $map = [
            'messages.send' => '/{phone_number_id}/messages',
            'media.upload'  => '/{phone_number_id}/media',
            'media.get'     => '/{media_id}',
            'media.delete'  => '/{media_id}',
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
