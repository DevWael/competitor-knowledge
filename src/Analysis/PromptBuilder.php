<?php
/**
 * Prompt Builder for AI Analysis.
 *
 * Optimizes prompts for small LLMs and modular intelligence.
 *
 * @package CompetitorKnowledge\Analysis
 */

declare(strict_types=1);

namespace CompetitorKnowledge\Analysis;

/**
 * Class PromptBuilder
 *
 * Builds optimized prompts for AI analysis, with special handling for small LLMs.
 *
 * @package CompetitorKnowledge\Analysis
 */
class PromptBuilder
{

    /**
     * Small LLM model patterns.
     *
     * @var array<string>
     */
    private const SMALL_MODEL_PATTERNS = array(
        'gemma',
        'glm-4',
        'llama2-7b',
        'llama-7b',
        'phi',
        'mistral-7b',
        'qwen-7b',
    );

    /**
     * Current model name.
     *
     * @var string
     */
    private string $model_name;

    /**
     * Enabled intelligence modules.
     *
     * @var array<string>
     */
    private array $enabled_modules;

    /**
     * PromptBuilder constructor.
     *
     * @param string        $model_name      The AI model name.
     * @param array<string> $enabled_modules Enabled intelligence modules.
     */
    public function __construct(string $model_name = '', array $enabled_modules = array())
    {
        $this->model_name = $model_name;
        $this->enabled_modules = $enabled_modules;
    }

    /**
     * Build the complete analysis prompt.
     *
     * @return string The optimized prompt.
     */
    public function build(): string
    {
        if ($this->is_small_model()) {
            return $this->build_small_llm_prompt();
        }

        return $this->build_standard_prompt();
    }

    /**
     * Check if the current model is a small LLM.
     *
     * @return bool True if small model detected.
     */
    private function is_small_model(): bool
    {
        $model_lower = strtolower($this->model_name);

        foreach (self::SMALL_MODEL_PATTERNS as $pattern) {
            if (strpos($model_lower, $pattern) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Build optimized prompt for small LLMs.
     *
     * Uses chain-of-thought and few-shot examples.
     *
     * @return string The optimized prompt.
     */
    private function build_small_llm_prompt(): string
    {
        $prompt = "# Task: Competitor Analysis\n\n";

        // Chain-of-thought introduction.
        $prompt .= "Let's analyze competitors step by step:\n\n";

        // Step 1: Find competitors.
        $prompt .= "## Step 1: Identify Competitors\n";
        $prompt .= "Look through the search results and find products matching our product.\n";
        $prompt .= "For each competitor, extract:\n";
        $prompt .= "- Name\n- URL\n- Price (number only)\n- Currency (USD, EUR, etc.)\n- Stock status (in_stock, out_of_stock, or unknown)\n\n";

        // Few-shot example.
        $prompt .= "Example:\n";
        $prompt .= "If you see \"Buy Widget Pro for \$99.99 at TechStore\", extract:\n";
        $prompt .= "{\n";
        $prompt .= "  \"name\": \"TechStore\",\n";
        $prompt .= "  \"url\": \"https://techstore.com/widget-pro\",\n";
        $prompt .= "  \"price\": \"99.99\",\n";
        $prompt .= "  \"currency\": \"USD\",\n";
        $prompt .= "  \"stock_status\": \"in_stock\"\n";
        $prompt .= "}\n\n";

        // Step 2: Content analysis.
        $prompt .= "## Step 2: Content Analysis\n";
        $prompt .= "Compare my product description with competitor descriptions.\n";
        $prompt .= "Find keywords they use that I don't.\n\n";

        // Step 3: Sentiment.
        $prompt .= "## Step 3: Sentiment Analysis\n";
        $prompt .= "Look for competitor weaknesses or complaints in reviews.\n\n";

        // Step 4: Strategy.
        $prompt .= "## Step 4: Strategic Advice\n";
        $prompt .= "Based on the comparison, suggest pricing and positioning.\n\n";

        // Add module-specific prompts.
        $prompt .= $this->build_module_prompts();

        // JSON structure.
        $prompt .= "## Output Format\n";
        $prompt .= "Return ONLY valid JSON (no markdown, no code blocks):\n";
        $prompt .= $this->build_json_structure();

        return $prompt;
    }

    /**
     * Build standard prompt for larger models.
     *
     * @return string The standard prompt.
     */
    private function build_standard_prompt(): string
    {
        $prompt = <<<'PROMPT'
Analyze the search results to find competitors selling the same product.
Compare prices, specifications, and availability.

Also perform:
1. Content Gap Analysis: Compare the tone and identify keywords present in competitor descriptions but missing in mine.
2. Sentiment Analysis: Identify common competitor weaknesses or complaints based on reviews/snippets.
3. Strategic Advice: Provide pricing and positioning advice based on the comparison.

PROMPT;

        // Add module-specific prompts.
        $prompt .= "\n" . $this->build_module_prompts();

        $prompt .= "\nReturn a strictly valid JSON (no markdown) with this structure:\n";
        $prompt .= $this->build_json_structure();

        return $prompt;
    }

    /**
     * Build module-specific prompt sections.
     *
     * @return string Module prompts.
     */
    private function build_module_prompts(): string
    {
        $module_prompts = '';

        if (in_array('pricing', $this->enabled_modules, true)) {
            $module_prompts .= $this->build_pricing_intelligence_prompt();
        }

        if (in_array('catalog', $this->enabled_modules, true)) {
            $module_prompts .= $this->build_catalog_intelligence_prompt();
        }

        if (in_array('marketing', $this->enabled_modules, true)) {
            $module_prompts .= $this->build_marketing_intelligence_prompt();
        }

        return $module_prompts;
    }

    /**
     * Build pricing intelligence prompt.
     *
     * @return string Pricing prompt section.
     */
    private function build_pricing_intelligence_prompt(): string
    {
        return <<<'PRICING'

## Pricing Intelligence
Analyze competitor pricing strategies:
- Price distribution (min, max, average)
- Discount patterns
- Bundle offerings
- Price positioning (premium, mid-range, budget)

PRICING;
    }

    /**
     * Build catalog intelligence prompt.
     *
     * @return string Catalog prompt section.
     */
    private function build_catalog_intelligence_prompt(): string
    {
        return <<<'CATALOG'

## Product Catalog Intelligence
Analyze competitor product offerings:
- Product variants and options
- Feature comparisons
- Unique selling propositions
- Product line breadth

CATALOG;
    }

    /**
     * Build marketing intelligence prompt.
     *
     * @return string Marketing prompt section.
     */
    private function build_marketing_intelligence_prompt(): string
    {
        return <<<'MARKETING'

## Marketing & Positioning Intelligence
Analyze competitor marketing strategies:
- Messaging and value propositions
- Target audience indicators
- Brand positioning
- Promotional tactics

MARKETING;
    }

    /**
     * Build JSON structure based on enabled modules.
     *
     * @return string JSON structure definition.
     */
    private function build_json_structure(): string
    {
        $structure = <<<'JSON'
{
  "competitors": [
    {
      "name": "Competitor Name",
      "url": "https://example.com",
      "price": "99.99",
      "currency": "USD",
      "stock_status": "in_stock|out_of_stock|unknown",
      "comparison_notes": "Brief comparison notes"
    }
  ],
  "content_analysis": {
    "my_tone": "Tone description",
    "competitor_tone": "Tone description",
    "missing_keywords": ["keyword1", "keyword2"],
    "improvement_suggestion": "Suggested improvement text"
  },
  "sentiment_analysis": {
    "competitor_weaknesses": ["weakness1", "weakness2"],
    "market_gaps": ["gap1", "gap2"]
  },
  "strategy": {
    "pricing_advice": "Pricing recommendation",
    "action_items": ["action1", "action2"]
  }
JSON;

        // Add module-specific fields.
        if (in_array('pricing', $this->enabled_modules, true)) {
            $structure .= <<<'PRICING'
,
  "pricing_intelligence": {
    "price_distribution": {
      "min": "0.00",
      "max": "0.00",
      "average": "0.00"
    },
    "discount_patterns": ["pattern1", "pattern2"],
    "positioning": "premium|mid-range|budget"
  }
PRICING;
        }

        if (in_array('catalog', $this->enabled_modules, true)) {
            $structure .= <<<'CATALOG'
,
  "catalog_intelligence": {
    "variants": ["variant1", "variant2"],
    "unique_features": ["feature1", "feature2"],
    "product_line_breadth": "narrow|moderate|extensive"
  }
CATALOG;
        }

        if (in_array('marketing', $this->enabled_modules, true)) {
            $structure .= <<<'MARKETING'
,
  "marketing_intelligence": {
    "messaging": "Key messaging themes",
    "target_audience": "Audience description",
    "brand_positioning": "Positioning description",
    "promotional_tactics": ["tactic1", "tactic2"]
  }
MARKETING;
        }

        $structure .= "\n}";

        return $structure;
    }
}
