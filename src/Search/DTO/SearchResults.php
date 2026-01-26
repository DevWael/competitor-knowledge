<?php
/**
 * Search Results DTO.
 *
 * @package CompetitorKnowledge
 */

declare(strict_types=1);

namespace CompetitorKnowledge\Search\DTO;

/**
 * Class SearchResults
 *
 * Data Transfer Object for search results.
 *
 * @package CompetitorKnowledge\Search\DTO
 */
class SearchResults {

	/**
	 * List of search result items.
	 *
	 * @var array
	 */
	private array $results;

	/**
	 * SearchResults constructor.
	 *
	 * @param array $results Array of search result items (e.g., ['url' => ..., 'content' => ...]).
	 */
	public function __construct( array $results ) {
		$this->results = $results;
	}

	/**
	 * Get the search results.
	 *
	 * @return array
	 */
	public function get_results(): array {
		return $this->results;
	}

	/**
	 * Check if there are any results.
	 *
	 * @return bool
	 */
	public function is_empty(): bool {
		return empty( $this->results );
	}
}
