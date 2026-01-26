<?php
/**
 * OpenRouter AI Provider.
 *
 * @package CompetitorKnowledge\AI\Providers
 */

declare(strict_types=1);

namespace CompetitorKnowledge\AI\Providers;

use CompetitorKnowledge\AI\Contracts\AIProviderInterface;
use CompetitorKnowledge\AI\DTO\AnalysisResult;
use RuntimeException;

/**
 * Class OpenRouterProvider
 *
 * OpenRouter implementation of the AIProviderInterface.
 *
 * @package CompetitorKnowledge\AI\Providers
 */
class OpenRouterProvider implements AIProviderInterface {

	/**
	 * OpenRouter API Key.
	 *
	 * @var string
	 */
	private string $api_key;

	/**
	 * Model Name (e.g., anthropic/claude-3.5-sonnet).
	 *
	 * @var string
	 */
	private string $model;

	/**
	 * OpenRouterProvider constructor.
	 *
	 * @param string $api_key The API key.
	 * @param string $model   The model name to use.
	 */
	public function __construct( string $api_key, string $model = 'anthropic/claude-3.5-sonnet' ) {
		$this->api_key = $api_key;
		$this->model   = $model;
	}

	/**
	 * Analyze context using OpenRouter.
	 *
	 * @param string $prompt  The instruction.
	 * @param array  $context The context data.
	 *
	 * @return AnalysisResult
	 * @throws RuntimeException If the API request fails.
	 */
	public function analyze( string $prompt, array $context ): AnalysisResult {
		$url = 'https://openrouter.ai/api/v1/chat/completions';

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

		$response = wp_remote_post(
			$url,
			array(
				'headers' => array(
					'Content-Type'  => 'application/json',
					'Authorization' => 'Bearer ' . $this->api_key,
					'HTTP-Referer'  => home_url(),
				),
				'body'    => wp_json_encode( $body ),
				'timeout' => 60,
			)
		);

		if ( is_wp_error( $response ) ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception messages are not output to browser.
			throw new RuntimeException( 'OpenRouter AI Failed: ' . $response->get_error_message() );
		}

		$status_code = wp_remote_retrieve_response_code( $response );
		$body        = wp_remote_retrieve_body( $response );
		$data        = json_decode( $body, true );

		if ( 200 !== $status_code ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception messages are not output to browser.
			throw new RuntimeException( 'OpenRouter AI Error: ' . ( $data['error']['message'] ?? 'Unknown error' ) );
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
			throw new RuntimeException( 'Failed to parse OpenRouter response as JSON.' );
		}

		return new AnalysisResult( $json );
	}
}
