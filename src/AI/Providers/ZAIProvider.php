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
	 * ZAIProvider constructor.
	 *
	 * @param string $api_key The API key.
	 * @param string $model   The model name to use.
	 */
	public function __construct( string $api_key, string $model = 'glm-4.7' ) {
		$this->api_key = $api_key;
		$this->model   = $model;
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
		$url = 'https://api.z.ai/api/paas/v4/chat/completions';

		// Construct the prompt with context.
		$context_str = wp_json_encode( $context );
		$full_prompt = "Context data: \n" . $context_str . "\n\nInstructions: \n" . $prompt . "\n\nReturn strictly valid minified JSON without markdown formatting.";

		$body = array(
			'model'           => $this->model,
			'messages'        => array(
				array(
					'role'    => 'user',
					'content' => $full_prompt,
				),
			),
			'response_format' => array(
				'type' => 'json_object',
			),
		);

		$json_body = wp_json_encode( $body );
		if ( false === $json_body ) {
			throw new RuntimeException( 'Failed to encode request body.' );
		}

		$response = wp_remote_post(
			$url,
			array(
				'headers' => array(
					'Content-Type'  => 'application/json',
					'Authorization' => 'Bearer ' . $this->api_key,
				),
				'body'    => $json_body,
				'timeout' => 60,
			)
		);

		if ( is_wp_error( $response ) ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception messages are not output to browser.
			throw new RuntimeException( 'Z.AI Failed: ' . $response->get_error_message() );
		}

		$status_code = wp_remote_retrieve_response_code( $response );
		$body        = wp_remote_retrieve_body( $response );
		$data        = json_decode( $body, true );

		if ( 200 !== $status_code ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception messages are not output to browser.
			throw new RuntimeException( 'Z.AI Error: ' . ( $data['error']['message'] ?? 'Unknown error' ) );
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
