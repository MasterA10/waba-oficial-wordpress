<?php
/**
 * WhatsAppInboundMessageNormalizer class.
 *
 * @package WAS\WhatsApp
 */

namespace WAS\WhatsApp;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Service for normalizing inbound WhatsApp messages of various types.
 */
class WhatsAppInboundMessageNormalizer {

	/**
	 * Normalize a message object from the webhook.
	 *
	 * @param array $message The raw message object.
	 * @return array
	 */
	public function normalize( array $message ) {
		$type = $message['type'] ?? 'unknown';

		switch ( $type ) {
			case 'text':
				return $this->normalizeText( $message );
			case 'button':
				return $this->normalizeButton( $message );
			case 'interactive':
				return $this->normalizeInteractive( $message );
			case 'image':
			case 'audio':
			case 'video':
			case 'document':
			case 'sticker':
				return $this->normalizeMedia( $message, $type );
			case 'reaction':
				return $this->normalizeReaction( $message );
			default:
				return $this->normalizeUnsupported( $message );
		}
	}

	private function normalizeText( array $message ) {
		return [
			'message_type'           => 'text',
			'text_body'              => $message['text']['body'] ?? '',
			'reply_to_wa_message_id' => $message['context']['id'] ?? $message['context']['message_id'] ?? null,
			'context_payload'        => isset( $message['context'] ) ? wp_json_encode( $message['context'] ) : null,
			'raw_payload'            => wp_json_encode( $message ),
		];
	}

	private function normalizeButton( array $message ) {
		$text    = $message['button']['text'] ?? '';
		$payload = $message['button']['payload'] ?? '';

		return [
			'message_type'           => 'button',
			'text_body'              => $text ?: ( $payload ?: 'Resposta de botão' ),
			'button_text'            => $text,
			'button_payload'         => $payload,
			'reply_to_wa_message_id' => $message['context']['id'] ?? $message['context']['message_id'] ?? null,
			'context_payload'        => isset( $message['context'] ) ? wp_json_encode( $message['context'] ) : null,
			'raw_payload'            => wp_json_encode( $message ),
		];
	}

	private function normalizeInteractive( array $message ) {
		$interactive     = $message['interactive'] ?? [];
		$interactiveType = $interactive['type'] ?? null;

		if ( 'button_reply' === $interactiveType ) {
			$reply = $interactive['button_reply'] ?? [];

			return [
				'message_type'           => 'interactive',
				'interactive_type'       => 'button_reply',
				'interactive_id'         => $reply['id'] ?? null,
				'interactive_title'      => $reply['title'] ?? null,
				'text_body'              => $reply['title'] ?? 'Resposta de botão',
				'reply_to_wa_message_id' => $message['context']['id'] ?? $message['context']['message_id'] ?? null,
				'context_payload'        => isset( $message['context'] ) ? wp_json_encode( $message['context'] ) : null,
				'raw_payload'            => wp_json_encode( $message ),
			];
		}

		if ( 'list_reply' === $interactiveType ) {
			$reply = $interactive['list_reply'] ?? [];

			return [
				'message_type'            => 'interactive',
				'interactive_type'        => 'list_reply',
				'interactive_id'          => $reply['id'] ?? null,
				'interactive_title'       => $reply['title'] ?? null,
				'interactive_description' => $reply['description'] ?? null,
				'text_body'               => $reply['title'] ?? 'Resposta de lista',
				'reply_to_wa_message_id'  => $message['context']['id'] ?? $message['context']['message_id'] ?? null,
				'context_payload'         => isset( $message['context'] ) ? wp_json_encode( $message['context'] ) : null,
				'raw_payload'             => wp_json_encode( $message ),
			];
		}

		return [
			'message_type'           => 'interactive',
			'interactive_type'       => $interactiveType,
			'text_body'              => 'Mensagem interativa',
			'reply_to_wa_message_id' => $message['context']['id'] ?? $message['context']['message_id'] ?? null,
			'context_payload'        => isset( $message['context'] ) ? wp_json_encode( $message['context'] ) : null,
			'raw_payload'            => wp_json_encode( $message ),
		];
	}

	private function normalizeMedia( array $message, $type ) {
		return [
			'message_type'           => $type,
			'text_body'              => $message[ $type ]['caption'] ?? $message[ $type ]['filename'] ?? null,
			'meta_media_id'          => $message[ $type ]['id'] ?? null,
			'mime_type'              => $message[ $type ]['mime_type'] ?? null,
			'sha256'                 => $message[ $type ]['sha256'] ?? null,
			'reply_to_wa_message_id' => $message['context']['id'] ?? $message['context']['message_id'] ?? null,
			'context_payload'        => isset( $message['context'] ) ? wp_json_encode( $message['context'] ) : null,
			'raw_payload'            => wp_json_encode( $message ),
		];
	}

	private function normalizeReaction( array $message ) {
		$reaction = $message['reaction'] ?? [];

		return [
			'message_type'           => 'reaction',
			'text_body'              => $reaction['emoji'] ?? 'Reação',
			'reply_to_wa_message_id' => $reaction['message_id'] ?? null,
			'raw_payload'            => wp_json_encode( $message ),
		];
	}

	private function normalizeUnsupported( array $message ) {
		return [
			'message_type' => 'unsupported',
			'text_body'    => 'Tipo de mensagem ainda não suportado: ' . ( $message['type'] ?? 'unknown' ),
			'raw_payload'  => wp_json_encode( $message ),
		];
	}
}
