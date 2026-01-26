<?php

declare(strict_types=1);

namespace CompetitorKnowledge\AI\Providers;

use CompetitorKnowledge\AI\Contracts\AIProviderInterface;
use CompetitorKnowledge\AI\DTO\AnalysisResult;
use RuntimeException;

/**
 * Class GoogleGeminiProvider
 *
 * Google Gemini implementation of the AIProviderInterface.
 *
 * @package CompetitorKnowledge\AI\Providers
 */
class GoogleGeminiProvider implements AIProviderInterface {

	/**
	 * Google AI Base URL.
	 */
	private const API_URL = 'https://generativelanguage.googleapis.com/v1beta/models/';

	/**
	 * API Key.
	 *
	 * @var string
	 */
	private string $api_key;

	/**
	 * Model Name (e.g., gemini-1.5-pro, gemma-3).
	 *
	 * @var string
	 */
	private string $model;

	/**
	 * GoogleGeminiProvider constructor.
	 *
	 * @param string $api_key The Google API Key.
	 * @param string $model   The model name to use.
	 */
	public function __construct( string $api_key, string $model = 'gemini-1.5-pro' ) {
		$this->api_key = $api_key;
		$this->model   = $model;
	}

	/**
	 * Analyze context using Google Gemini.
	 *
	 * @param string $prompt  The instruction.
	 * @param array  $context The context context.
	 *
	 * @return AnalysisResult
	 * @throws RuntimeException If the API request fails.
	 */
	public function analyze( string $prompt, array $context ): AnalysisResult {
		$url = self::API_URL . $this->model . ':generateContent?key=' . $this->api_key;

		// Construct the prompt with context
		$context_str = wp_json_encode( $context );
		$full_prompt = "Context data: \n" . $context_str . "\n\nInstructions: \n" . $prompt . "\n\nReturn strictly valid minified JSON without markdown formatting.";

		$body = [
			'contents' => [
				[
					'parts' => [
						[ 'text' => $full_prompt ],
					],
				],
			],
			'generationConfig' => [
				'responseMimeType' => 'application/json',
			],
		];

		$response = wp_remote_post(
			$url,
			[
				'headers' => [
					'Content-Type' => 'application/json',
				],
				'body'    => wp_json_encode( $body ),
				'timeout' => 60,
			]
		);

		if ( is_wp_error( $response ) ) {
			throw new RuntimeException( 'Google AI Failed: ' . $response->get_error_message() );
		}

		$status_code = wp_remote_retrieve_response_code( $response );
		$body        = wp_remote_retrieve_body( $response );
		$data        = json_decode( $body, true );

		if ( 200 !== $status_code ) {
			throw new RuntimeException( 'Google AI Error: ' . ( $data['error']['message'] ?? 'Unknown error' ) );
		}

		// Extract content from response
		$content = $data['candidates'][0]['content']['parts'][0]['text'] ?? '';
		$json    = json_decode( $content, true );

		if ( json_last_error() !== JSON_ERROR_NONE ) {
			// Fallback: try to clean markdown code blocks if present (though responseMimeType should prevent this)
			$content = preg_replace( '/^```json|```$/m', '', $content );
			$json    = json_decode( $content, true );
		}

		if ( ! is_array( $json ) ) {
			throw new RuntimeException( 'Failed to parse AI response as JSON.' );
		}

		return new AnalysisResult( $json );
	}
}
