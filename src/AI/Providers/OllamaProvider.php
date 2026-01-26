<?php
/**
 * Ollama AI Provider implementation.
 *
 * @package CompetitorKnowledge
 */

declare(strict_types=1);

namespace CompetitorKnowledge\AI\Providers;

use CompetitorKnowledge\AI\Contracts\AIProviderInterface;
use CompetitorKnowledge\AI\DTO\AnalysisResult;
use RuntimeException;

/**
 * Class OllamaProvider
 *
 * Ollama (Local) implementation of the AIProviderInterface.
 *
 * @package CompetitorKnowledge\AI\Providers
 */
class OllamaProvider implements AIProviderInterface {

	/**
	 * Ollama API Base URL.
	 *
	 * @var string
	 */
	private string $api_url;

	/**
	 * Model Name (e.g., llama3, gemma2).
	 *
	 * @var string
	 */
	private string $model;

	/**
	 * OllamaProvider constructor.
	 *
	 * @param string $api_url The Ollama API URL.
	 * @param string $model   The model name to use.
	 */
	public function __construct( string $api_url = 'http://localhost:11434', string $model = 'llama3' ) {
		$this->api_url = rtrim( $api_url, '/' );
		$this->model   = $model;
	}

	/**
	 * Analyze context using Ollama.
	 *
	 * @param string $prompt  The instruction.
	 * @param array<string, mixed>  $context The context data.
	 *
	 * @return AnalysisResult
	 * @throws RuntimeException If the API request fails.
	 */
	public function analyze( string $prompt, array $context ): AnalysisResult {
		$url = $this->api_url . '/api/generate';

		// Construct the prompt with context.
		$context_str = wp_json_encode( $context );
		$full_prompt = "Context data: \n" . $context_str . "\n\nInstructions: \n" . $prompt . "\n\nReturn strictly valid minified JSON without markdown formatting.";

		$body = array(
			'model'   => $this->model,
			'prompt'  => $full_prompt,
			'format'  => 'json',
			'stream'  => false,
			'options' => array(
				'num_ctx'     => 8192,  // Extended context window
				'temperature' => 0.7,   // Balanced creativity
				'top_p'       => 0.9,   // Nucleus sampling
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
					'Content-Type' => 'application/json',
				),
				'body'    => $json_body,
				'timeout' => 120, // Ollama can be slower.
			)
		);

		if ( is_wp_error( $response ) ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception messages are not output to browser.
			throw new RuntimeException( 'Ollama AI Failed: ' . $response->get_error_message() );
		}

		$status_code = wp_remote_retrieve_response_code( $response );
		$body        = wp_remote_retrieve_body( $response );
		$data        = json_decode( $body, true );

		if ( 200 !== $status_code ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
			throw new RuntimeException( 'Ollama AI Error: ' . ( $data['error'] ?? 'Unknown error' ) );
		}

		// Extract content from response.
		$content = $data['response'] ?? '';
		$json    = json_decode( $content, true );

		if ( json_last_error() !== JSON_ERROR_NONE ) {
			// Fallback: try to clean markdown code blocks.
			$content = preg_replace( '/^```json|```$/m', '', $content );
			$json    = json_decode( trim( $content ), true );
		}

		if ( ! is_array( $json ) ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
			throw new RuntimeException( 'Failed to parse Ollama response as JSON.' );
		}

		return new AnalysisResult( $json );
	}
}
