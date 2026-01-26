<?php
/**
 * Search Provider Interface.
 *
 * @package CompetitorKnowledge\Search\Contracts
 */

declare(strict_types=1);

namespace CompetitorKnowledge\Search\Contracts;

use CompetitorKnowledge\Search\DTO\SearchResults;

/**
 * Interface SearchProviderInterface
 *
 * Defines the contract for search providers to find competitor data.
 *
 * @package CompetitorKnowledge\Search\Contracts
 */
interface SearchProviderInterface {

	/**
	 * Perform a search query.
	 *
	 * @param string $query The search query string.
	 * @param int    $limit Optional. Number of results to return. Default 5.
	 *
	 * @return SearchResults The standardized search results.
	 *
	 * @throws \RuntimeException If the search fails.
	 */
	public function search( string $query, int $limit = 5 ): SearchResults;
}
