<?php

namespace WAS\Compliance;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Gerador de Páginas Legais
 */
class LegalPagesGenerator {
    private static $pages = [
        'privacy-policy'         => 'Política de Privacidade',
        'terms-of-service'      => 'Termos de Serviço',
        'data-deletion'          => 'Exclusão de Dados',
        'acceptable-use-policy' => 'Política de Uso Aceitável',
        'security'               => 'Segurança',
        'contact'                => 'Contato'
    ];

    /**
     * Cria as páginas se não existirem
     */
    public static function generateAll() {
        foreach (self::$pages as $slug => $title) {
            self::createPage($slug, $title);
        }
    }

    private static function createPage($slug, $title) {
        $existing = get_page_by_path($slug);

        if (!$existing) {
            $content = self::getPlaceholderContent($slug);
            
            wp_insert_post([
                'post_title'   => $title,
                'post_name'    => $slug,
                'post_content' => $content,
                'post_status'  => 'publish',
                'post_type'    => 'page'
            ]);
        }
    }

    private static function getPlaceholderContent($slug) {
        // No futuro, isso pode carregar de templates PHP em templates/legal/
        return "Conteúdo para a página $slug. Esta página é necessária para o App Review da Meta.";
    }
}
