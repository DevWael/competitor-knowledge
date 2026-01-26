<?php
/**
 * Tavily Search Provider.
 *
 * @package CompetitorKnowledge\Search\Providers
 */

declare(strict_types=1);

namespace CompetitorKnowledge\Search\Providers;

use CompetitorKnowledge\Search\Contracts\SearchProviderInterface;
use CompetitorKnowledge\Search\DTO\SearchResults;
use RuntimeException;

/**
 * Class TavilyProvider
 *
 * Tavily implementation of the SearchProviderInterface.
 *
 * @package CompetitorKnowledge\Search\Providers
 */
class TavilyProvider implements SearchProviderInterface {

	/**
	 * Tavily API Base URL.
	 */
	private const API_URL = 'https://api.tavily.com/search';

	/**
	 * API Key.
	 *
	 * @var string
	 */
	private string $api_key;

	/**
	 * TavilyProvider constructor.
	 *
	 * @param string $api_key The Tavily API key.
	 */
	public function __construct( string $api_key ) {
		$this->api_key = $api_key;
	}

	/**
	 * Perform a search query using Tavily API.
	 *
	 * @param string $query The search query.
	 * @param int    $limit Number of results.
	 *
	 * @return SearchResults
	 * @throws RuntimeException If the API request fails.
	 */
	public function search( string $query, int $limit = 5 ): SearchResults {
		$body = array(
			'api_key'             => $this->api_key,
			'query'               => $query,
			'search_depth'        => 'advanced',
			'include_answer'      => false,
			'include_images'      => true,
			'include_raw_content' => true,
			'max_results'         => $limit,
		);

		$json_body = wp_json_encode( $body );
		if ( false === $json_body ) {
			throw new RuntimeException( 'Failed to encode request body.' );
		}

		$response = wp_remote_post(
			self::API_URL,
			array(
				'headers' => array(
					'Content-Type' => 'application/json',
				),
				'body'    => $json_body,
				'timeout' => 30,
			)
		);

		if ( is_wp_error( $response ) ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception messages are not output to browser.
			throw new RuntimeException( 'Tavily Search Failed: ' . $response->get_error_message() );
		}

		$status_code = wp_remote_retrieve_response_code( $response );
		$body        = wp_remote_retrieve_body( $response );
		$data        = json_decode( $body, true );

		if ( 200 !== $status_code || ! isset( $data['results'] ) ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception messages are not output to browser.
			throw new RuntimeException( 'Tavily Search Error: ' . ( $data['error'] ?? 'Unknown error' ) );
		}

		return new SearchResults( $data['results'] );
	}
}
