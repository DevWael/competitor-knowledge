<?php
/**
 * Z.AI Provider.
 *
 * @package CompetitorKnowledge\AI\Providers
 */

declare(strict_types=1);

namespace CompetitorKnowledge\AI\Providers;

use CompetitorKnowledge\AI\Contracts\AIProviderInterface;
use CompetitorKnowledge\AI\DTO\AnalysisResult;
use RuntimeException;

/**
 * Class ZAIProvider
 *
 * Z.AI implementation of the AIProviderInterface.
 *
 * @package CompetitorKnowledge\AI\Providers
 */
class ZAIProvider implements AIProviderInterface {

	/**
	 * Z.AI API Key.
	 *
	 * @var string
	 */
	private string $api_key;

	/**
	 * Model Name (e.g., glm-4.7).
	 *
	 * @var string
	 */
	private string $model;

	/**
	 * API Endpoint URL.
	 *
	 * @var string
	 */
	private string $endpoint_url;

	/**
	 * ZAIProvider constructor.
	 *
	 * @param string $api_key      The API key.
	 * @param string $model        The model name to use.
	 * @param string $endpoint_url The API endpoint URL.
	 */
	public function __construct( string $api_key, string $model = 'glm-4.7', string $endpoint_url = '' ) {
		$this->api_key      = $api_key;
		$this->model        = $model;
		$this->endpoint_url = $endpoint_url ?: 'https://api.z.ai/api/coding/paas/v4/chat/completions';
	}

	/**
	 * Analyze context using Z.AI.
	 *
	 * @param string               $prompt  The instruction.
	 * @param array<string, mixed> $context The context data.
	 *
	 * @return AnalysisResult
	 * @throws RuntimeException If the API request fails.
	 */
	public function analyze( string $prompt, array $context ): AnalysisResult {
		$url = $this->endpoint_url;

		// Construct the prompt with context.
		$context_str = wp_json_encode( $context );
		$full_prompt = "Context data: \n" . $context_str . "\n\nInstructions: \n" . $prompt . "\n\nReturn strictly valid minified JSON without markdown formatting.";

		$body = array(
			'model'    => $this->model,
			'messages' => array(
				array(
					'role'    => 'user',
					'content' => $full_prompt,
				),
			),
		);

		// Only add response_format if model supports it (some models/billing tiers may not).
		// This can be controlled via filter if needed.
		$use_json_mode = apply_filters( 'ck_zai_use_json_mode', false, $this->model );
		if ( $use_json_mode ) {
			$body['response_format'] = array( 'type' => 'json_object' );
		}

		$json_body = wp_json_encode( $body );
		if ( false === $json_body ) {
			throw new RuntimeException( 'Failed to encode request body.' );
		}

		// Debug logging for troubleshooting.
		error_log( '[CK Z.AI] Request URL: ' . $url ); // phpcs:ignore
		error_log( '[CK Z.AI] Model: ' . $this->model ); // phpcs:ignore
		error_log( '[CK Z.AI] Request body length: ' . strlen( $json_body ) . ' bytes' ); // phpcs:ignore

		$response = wp_remote_post(
			$url,
			array(
				'headers' => array(
					'Content-Type'  => 'application/json',
					'Authorization' => 'Bearer ' . $this->api_key,
				),
				'body'    => $json_body,
				'timeout' => 120,
			)
		);

		// Log response for debugging.
		if ( ! is_wp_error( $response ) ) {
			$status = wp_remote_retrieve_response_code( $response );
			error_log( '[CK Z.AI] Response status: ' . $status ); // phpcs:ignore
		} else {
			error_log( '[CK Z.AI] WP Error: ' . $response->get_error_message() ); // phpcs:ignore
		}

		if ( is_wp_error( $response ) ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception messages are not output to browser.
			throw new RuntimeException( 'Z.AI Failed: ' . $response->get_error_message() );
		}

		$status_code = wp_remote_retrieve_response_code( $response );
		$body        = wp_remote_retrieve_body( $response );
		$data        = json_decode( $body, true );

		if ( 200 !== $status_code ) {
			// Provide detailed error for debugging billing/auth issues.
			$error_msg = $data['error']['message'] ?? $data['message'] ?? 'Unknown error';
			$error_details = sprintf(
				'Z.AI Error (Status %d): %s. Response: %s',
				$status_code,
				$error_msg,
				substr( $body, 0, 500 ) // First 500 chars for debugging.
			);
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception messages are not output to browser.
			throw new RuntimeException( $error_details );
		}

		// Extract content from response.
		$content = $data['choices'][0]['message']['content'] ?? '';
		$json    = json_decode( $content, true );

		if ( json_last_error() !== JSON_ERROR_NONE ) {
			// Fallback: try to clean markdown code blocks.
			$content = preg_replace( '/^```json|```$/m', '', $content );
			$json    = json_decode( trim( $content ), true );
		}

		if ( ! is_array( $json ) ) {
			throw new RuntimeException( 'Failed to parse Z.AI response as JSON.' );
		}

		return new AnalysisResult( $json );
	}
}
