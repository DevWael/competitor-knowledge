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
	 * Get competitor data.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function get_competitors(): array {
		return $this->insights['competitors'] ?? array();
	}

	/**
	 * Get content analysis data.
	 *
	 * @return array<string, mixed>
	 */
	public function get_content_analysis(): array {
		return $this->insights['content_analysis'] ?? array();
	}

	/**
	 * Get sentiment analysis data.
	 *
	 * @return array<string, mixed>
	 */
	public function get_sentiment_analysis(): array {
		return $this->insights['sentiment_analysis'] ?? array();
	}

	/**
	 * Get strategic advice.
	 *
	 * @return array<string, mixed>
	 */
	public function get_strategy(): array {
		return $this->insights['strategy'] ?? array();
	}

	/**
	 * Get pricing intelligence data.
	 *
	 * @return array<string, mixed>
	 */
	public function get_pricing_intelligence(): array {
		return $this->insights['pricing_intelligence'] ?? array();
	}

	/**
	 * Get catalog intelligence data.
	 *
	 * @return array<string, mixed>
	 */
	public function get_catalog_intelligence(): array {
		return $this->insights['catalog_intelligence'] ?? array();
	}

	/**
	 * Get marketing intelligence data.
	 *
	 * @return array<string, mixed>
	 */
	public function get_marketing_intelligence(): array {
		return $this->insights['marketing_intelligence'] ?? array();
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
