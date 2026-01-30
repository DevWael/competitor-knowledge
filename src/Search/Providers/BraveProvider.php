<?php
/**
 * Brave Search Provider.
 *
 * @package CompetitorKnowledge\Search\Providers
 */

declare(strict_types=1);

namespace CompetitorKnowledge\Search\Providers;

use CompetitorKnowledge\Search\Contracts\SearchProviderInterface;
use CompetitorKnowledge\Search\DTO\SearchResults;
use RuntimeException;

/**
 * Class BraveProvider
 *
 * Brave Search implementation of the SearchProviderInterface.
 *
 * @package CompetitorKnowledge\Search\Providers
 */
class BraveProvider implements SearchProviderInterface {

	/**
	 * Brave Search API Base URL.
	 */
	private const API_URL = 'https://api.search.brave.com/res/v1/web/search';

	/**
	 * API Key.
	 *
	 * @var string
	 */
	private string $api_key;

	/**
	 * BraveProvider constructor.
	 *
	 * @param string $api_key The Brave Search API key.
	 */
	public function __construct( string $api_key ) {
		$this->api_key = $api_key;
	}

	/**
	 * Perform a search query using Brave Search API.
	 *
	 * @param string $query The search query.
	 * @param int    $limit Number of results.
	 *
	 * @return SearchResults
	 * @throws RuntimeException If the API request fails.
	 */
	public function search( string $query, int $limit = 5 ): SearchResults {
		$url = add_query_arg(
			array(
				'q'     => rawurlencode( $query ),
				'count' => $limit,
			),
			self::API_URL
		);

		$response = wp_remote_get(
			$url,
			array(
				'headers' => array(
					'Accept'          => 'application/json',
					'X-Subscription-Token' => $this->api_key,
				),
				'timeout' => 30,
			)
		);

		if ( is_wp_error( $response ) ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception messages are not output to browser.
			throw new RuntimeException( 'Brave Search Failed: ' . $response->get_error_message() );
		}

		$status_code = wp_remote_retrieve_response_code( $response );
		$body        = wp_remote_retrieve_body( $response );
		$data        = json_decode( $body, true );

		if ( 200 !== $status_code || ! isset( $data['web']['results'] ) ) {
			$error_message = $data['message'] ?? $data['error'] ?? 'Unknown error';
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception messages are not output to browser.
			throw new RuntimeException( 'Brave Search Error: ' . $error_message );
		}

		// Normalize Brave results to match Tavily format.
		$normalized_results = $this->normalize_results( $data['web']['results'] );

		return new SearchResults( $normalized_results );
	}

	/**
	 * Normalize Brave Search results to match expected format.
	 *
	 * @param array<int, array<string, mixed>> $brave_results Raw Brave results.
	 *
	 * @return array<int, array<string, mixed>> Normalized results.
	 */
	private function normalize_results( array $brave_results ): array {
		$normalized = array();

		foreach ( $brave_results as $result ) {
			$normalized[] = array(
				'url'     => $result['url'] ?? '',
				'title'   => $result['title'] ?? '',
				'content' => $result['description'] ?? '',
				'score'   => 1.0, // Brave doesn't provide relevance scores.
			);
		}

		return $normalized;
	}
}
