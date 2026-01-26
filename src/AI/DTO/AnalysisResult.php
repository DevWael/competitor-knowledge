<?php
/**
 * Analysis Result Data Transfer Object.
 *
 * @package CompetitorKnowledge\AI\DTO
 */

declare(strict_types=1);

namespace CompetitorKnowledge\AI\DTO;

/**
 * Class AnalysisResult
 *
 * Data Transfer Object for AI analysis results.
 *
 * @package CompetitorKnowledge\AI\DTO
 */
class AnalysisResult {

	/**
	 * Verified competitor insights.
	 *
	 * @var array<string, mixed>
	 */
	private array $insights;

	/**
	 * AnalysisResult constructor.
	 *
	 * @param array<string, mixed> $insights Structured data returned by the AI.
	 */
	public function __construct( array $insights ) {
		$this->insights = $insights;
	}

	/**
	 * Get the structured insights.
	 *
	 * @return array<string, mixed>
	 */
	public function get_insights(): array {
		return $this->insights;
	}

	/**
	 * Convert the result to an array.
	 *
	 * @return array<string, mixed>
	 */
	public function to_array(): array {
		return $this->insights;
	}
}
