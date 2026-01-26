<?php

declare(strict_types=1);

namespace CompetitorKnowledge\AI\Contracts;

use CompetitorKnowledge\AI\DTO\AnalysisResult;

/**
 * Interface AIProviderInterface
 *
 * Defines the contract for AI providers to analyze competitor data.
 *
 * @package CompetitorKnowledge\AI\Contracts
 */
interface AIProviderInterface {

	/**
	 * Analyze the provided context and return structured insights.
	 *
	 * @param string $prompt  The instruction prompt for the AI.
	 * @param array  $context The context data (e.g., search results, product details).
	 *
	 * @return AnalysisResult The structured analysis result.
	 *
	 * @throws \RuntimeException If the analysis fails.
	 */
	public function analyze( string $prompt, array $context ): AnalysisResult;
}
