<?php
/**
 * Main Analyzer class for competitor analysis.
 *
 * @package CompetitorKnowledge
 */

declare(strict_types=1);

namespace CompetitorKnowledge\Analysis;

use CompetitorKnowledge\AI\Contracts\AIProviderInterface;
use CompetitorKnowledge\Search\Contracts\SearchProviderInterface;
use CompetitorKnowledge\Data\AnalysisRepository;
use CompetitorKnowledge\Data\PriceHistoryRepository;
use Exception;
use WC_Product;

/**
 * Class Analyzer
 *
 * Coordinates the analysis process: Search -> AI Analysis -> Save.
 *
 * @package CompetitorKnowledge\Analysis
 */
class Analyzer {

	/**
	 * Search Provider.
	 *
	 * @var SearchProviderInterface
	 */
	private SearchProviderInterface $search_provider;

	/**
	 * AI Provider.
	 *
	 * @var AIProviderInterface
	 */
	private AIProviderInterface $ai_provider;

	/**
	 * Repository.
	 *
	 * @var AnalysisRepository
	 */
	private AnalysisRepository $repository;

	/**
	 * Price History Repository.
	 *
	 * @var PriceHistoryRepository
	 */
	private PriceHistoryRepository $price_history;

	/**
	 * Prompt Builder.
	 *
	 * @var PromptBuilder
	 */
	private PromptBuilder $prompt_builder;

	/**
	 * Analyzer constructor.
	 *
	 * @param SearchProviderInterface $search_provider Search service.
	 * @param AIProviderInterface     $ai_provider     AI service.
	 * @param AnalysisRepository      $repository      Data repository.
	 * @param PriceHistoryRepository  $price_history   Price History repository.
	 * @param PromptBuilder           $prompt_builder  Prompt builder service.
	 */
	public function __construct(
		SearchProviderInterface $search_provider,
		AIProviderInterface $ai_provider,
		AnalysisRepository $repository,
		PriceHistoryRepository $price_history,
		PromptBuilder $prompt_builder
	) {
		$this->search_provider = $search_provider;
		$this->ai_provider     = $ai_provider;
		$this->repository      = $repository;
		$this->price_history   = $price_history;
		$this->prompt_builder  = $prompt_builder;
	}

	/**
	 * Process an analysis request.
	 *
	 * @param int $analysis_id The ID of the analysis post.
	 *
	 * @throws Exception If processing fails.
	 */
	public function process( int $analysis_id ): void {
		// 1. Get Analysis & Product
		$product_id = $this->repository->get_target_product_id( $analysis_id );
		$product    = wc_get_product( $product_id );

		if ( ! $product ) {
			$this->repository->update_status( $analysis_id, 'failed' );
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
			throw new Exception( "Target product #{$product_id} not found." );
		}

		$this->repository->update_status( $analysis_id, 'processing' );

		/**
		 * Fires before the analysis process starts.
		 *
		 * @since 1.0.0
		 *
		 * @param int         $analysis_id The analysis post ID.
		 * @param int         $product_id  The target product ID.
		 * @param \WC_Product $product     The WooCommerce product object.
		 */
		do_action( 'ck_before_analysis', $analysis_id, $product_id, $product );

		try {
			// 2. Prepare Context (Product Data)
			$product_data = $this->get_product_data( $product );

			/**
			 * Filters the product data used for analysis.
			 *
			 * @since 1.0.0
			 *
			 * @param array<string, mixed> $product_data The product data array.
			 * @param \WC_Product          $product      The WooCommerce product object.
			 * @param int                  $analysis_id  The analysis post ID.
			 */
			$product_data = apply_filters( 'ck_analysis_product_data', $product_data, $product, $analysis_id );

			$query = sprintf( 'Buy %s %s online price specs', $product->get_name(), $product->get_sku() );

			/**
			 * Filters the search query used to find competitors.
			 *
			 * @since 1.0.0
			 *
			 * @param string      $query       The search query string.
			 * @param \WC_Product $product     The WooCommerce product object.
			 * @param int         $analysis_id The analysis post ID.
			 */
			$query = apply_filters( 'ck_search_query', $query, $product, $analysis_id );

			/**
			 * Filters the number of search results to retrieve.
			 *
			 * @since 1.0.0
			 *
			 * @param int         $limit       The number of results to fetch. Default 5.
			 * @param \WC_Product $product     The WooCommerce product object.
			 * @param int         $analysis_id The analysis post ID.
			 */
			$search_limit = apply_filters( 'ck_search_results_limit', 5, $product, $analysis_id );

			// 3. Search for Competitors
			$search_results = $this->search_provider->search( $query, $search_limit );

			/**
			 * Fires after search results are retrieved.
			 *
			 * @since 1.0.0
			 *
			 * @param \CompetitorKnowledge\Search\DTO\SearchResults $search_results The search results object.
			 * @param string                                        $query          The search query used.
			 * @param int                                           $analysis_id    The analysis post ID.
			 */
			do_action( 'ck_after_search', $search_results, $query, $analysis_id );

			if ( $search_results->is_empty() ) {
				throw new Exception( 'No search results found.' );
			}

			// 4. AI Analysis
			$context = array(
				'my_product' => $product_data,
				'web_search' => $search_results->get_results(),
			);

			/**
			 * Filters the context data passed to the AI provider.
			 *
			 * @since 1.0.0
			 *
			 * @param array<string, mixed> $context      The context data array.
			 * @param \WC_Product          $product      The WooCommerce product object.
			 * @param int                  $analysis_id  The analysis post ID.
			 */
			$context = apply_filters( 'ck_ai_context', $context, $product, $analysis_id );

			$prompt = $this->build_analysis_prompt();

			/**
			 * Filters the prompt sent to the AI provider.
			 *
			 * @since 1.0.0
			 *
			 * @param string               $prompt       The AI prompt string.
			 * @param array<string, mixed> $context      The context data array.
			 * @param \WC_Product          $product      The WooCommerce product object.
			 * @param int                  $analysis_id  The analysis post ID.
			 */
			$prompt = apply_filters( 'ck_ai_prompt', $prompt, $context, $product, $analysis_id );

			/**
			 * Fires before the AI analysis is performed.
			 *
			 * @since 1.0.0
			 *
			 * @param string               $prompt       The AI prompt string.
			 * @param array<string, mixed> $context      The context data array.
			 * @param int                  $analysis_id  The analysis post ID.
			 */
			do_action( 'ck_before_ai_analysis', $prompt, $context, $analysis_id );

			$analysis_result = $this->ai_provider->analyze( $prompt, $context );

			/**
			 * Filters the analysis result from the AI provider.
			 *
			 * @since 1.0.0
			 *
			 * @param \CompetitorKnowledge\AI\DTO\AnalysisResult $analysis_result The analysis result object.
			 * @param \WC_Product                                $product         The WooCommerce product object.
			 * @param int                                        $analysis_id     The analysis post ID.
			 */
			$analysis_result = apply_filters( 'ck_analysis_result', $analysis_result, $product, $analysis_id );

			/**
			 * Fires after AI analysis is completed.
			 *
			 * @since 1.0.0
			 *
			 * @param \CompetitorKnowledge\AI\DTO\AnalysisResult $analysis_result The analysis result object.
			 * @param int                                        $analysis_id     The analysis post ID.
			 */
			do_action( 'ck_after_ai_analysis', $analysis_result, $analysis_id );

			// 5. Store results.
			$result_data = $analysis_result->to_array();

			/**
			 * Filters the analysis data before saving to database.
			 *
			 * @since 1.0.0
			 *
			 * @param array<string, mixed> $result_data  The result data to save.
			 * @param int                  $analysis_id  The analysis post ID.
			 * @param int                  $product_id   The target product ID.
			 */
			$result_data = apply_filters( 'ck_before_save_results', $result_data, $analysis_id, $product_id );

			$this->repository->save_results( $analysis_id, $result_data );

			/**
			 * Fires after analysis results are saved.
			 *
			 * @since 1.0.0
			 *
			 * @param int                  $analysis_id The analysis post ID.
			 * @param array<string, mixed> $result_data The saved result data.
			 * @param int                  $product_id  The target product ID.
			 */
			do_action( 'ck_after_save_results', $analysis_id, $result_data, $product_id );

			// 6. Log Price History.
			$insights = $analysis_result->get_insights();
			if ( ! empty( $insights['competitors'] ) ) {
				/**
				 * Filters the competitors list before processing price history.
				 *
				 * @since 1.0.0
				 *
				 * @param array<int, array<string, mixed>> $competitors  The competitors array.
				 * @param int                              $analysis_id  The analysis post ID.
				 * @param int                              $product_id   The target product ID.
				 */
				$competitors = apply_filters( 'ck_competitors_for_price_history', $insights['competitors'], $analysis_id, $product_id );

				foreach ( $competitors as $comp ) {
					if ( isset( $comp['price'], $comp['name'] ) ) {
						// Clean price string to float.
						$price    = (float) preg_replace( '/[^0-9.]/', '', (string) $comp['price'] );
						$currency = $comp['currency'] ?? 'USD';

						/**
						 * Filters the competitor price data before recording.
						 *
						 * @since 1.0.0
						 *
						 * @param array<string, mixed> $price_data   The price data array with 'price' and 'currency'.
						 * @param array<string, mixed> $comp         The raw competitor data.
						 * @param int                  $product_id   The target product ID.
						 * @param int                  $analysis_id  The analysis post ID.
						 */
						$price_data = apply_filters(
							'ck_price_record_data',
							array(
								'price'    => $price,
								'currency' => $currency,
							),
							$comp,
							$product_id,
							$analysis_id
						);

						/**
						 * Fires before a price history record is added.
						 *
						 * @since 1.0.0
						 *
						 * @param int                  $product_id      The target product ID.
						 * @param int                  $analysis_id     The analysis post ID.
						 * @param string               $competitor_name The competitor name.
						 * @param array<string, mixed> $price_data      The price data array.
						 */
						do_action( 'ck_before_add_price_record', $product_id, $analysis_id, $comp['name'], $price_data );

						$this->price_history->add_record(
							$product_id,
							$analysis_id,
							$comp['name'],
							$price_data['price'],
							$price_data['currency']
						);

						/**
						 * Fires after a price history record is added.
						 *
						 * @since 1.0.0
						 *
						 * @param int                  $product_id      The target product ID.
						 * @param int                  $analysis_id     The analysis post ID.
						 * @param string               $competitor_name The competitor name.
						 * @param array<string, mixed> $price_data      The price data array.
						 */
						do_action( 'ck_after_add_price_record', $product_id, $analysis_id, $comp['name'], $price_data );

						// 7. Check for Notification
						$this->check_price_alert( $product, $comp['name'], $price_data['price'], $analysis_id );
					}
				}
			}

			/**
			 * Fires after the analysis process completes successfully.
			 *
			 * @since 1.0.0
			 *
			 * @param int         $analysis_id The analysis post ID.
			 * @param int         $product_id  The target product ID.
			 * @param \WC_Product $product     The WooCommerce product object.
			 */
			do_action( 'ck_after_analysis', $analysis_id, $product_id, $product );
		} catch ( Exception $e ) {
			$this->repository->update_status( $analysis_id, 'failed' );

			/**
			 * Fires when an analysis fails.
			 *
			 * @since 1.0.0
			 *
			 * @param int        $analysis_id The analysis post ID.
			 * @param int        $product_id  The target product ID.
			 * @param \Exception $e           The exception that was thrown.
			 */
			do_action( 'ck_analysis_failed', $analysis_id, $product_id, $e );

			// Log error via Action Scheduler or WP Log.
			error_log( 'Analysis Failed: ' . $e->getMessage() ); // phpcs:ignore
			throw $e;
		}
	}

	/**
	 * Extract relevant product data.
	 *
	 * @param WC_Product $product The product.
	 *
	 * @return array<string, mixed>
	 */
	private function get_product_data( WC_Product $product ): array {
		$data = array(
			'name'  => $product->get_name(),
			'sku'   => $product->get_sku(),
			'price' => $product->get_price(),
			'desc'  => wp_strip_all_tags( $product->get_short_description() ),
		);

		/**
		 * Filters the raw product data extracted from WooCommerce product.
		 *
		 * @since 1.0.0
		 *
		 * @param array<string, mixed> $data    The product data array.
		 * @param \WC_Product          $product The WooCommerce product object.
		 */
		return apply_filters( 'ck_get_product_data', $data, $product );
	}

	/**
	 * Build the AI analysis prompt.
	 *
	 * This method generates the prompt that instructs the AI to perform
	 * competitor analysis, content gap analysis, sentiment analysis, and
	 * provide strategic advice.
	 *
	 * @return string The analysis prompt.
	 */
	protected function build_analysis_prompt(): string {
		$prompt = $this->prompt_builder->build();

		/**
		 * Filters the base analysis prompt before it's sent to the AI.
		 *
		 * @since 1.0.0
		 *
		 * @param string $prompt The base prompt string.
		 */
		return apply_filters( 'ck_analysis_base_prompt', $prompt );
	}

	/**
	 * Check and send price alert.
	 *
	 * @param WC_Product $product          The product.
	 * @param string     $competitor_name  Competitor name.
	 * @param float      $competitor_price Competitor price.
	 * @param int        $analysis_id      The analysis post ID.
	 */
	private function check_price_alert( WC_Product $product, string $competitor_name, float $competitor_price, int $analysis_id = 0 ): void {
		$options   = get_option( \CompetitorKnowledge\Admin\Settings::OPTION_NAME );
		$email     = $options['notification_email'] ?? '';
		$threshold = (float) ( $options['price_drop_threshold'] ?? 10 );
		$my_price  = (float) $product->get_price();

		/**
		 * Filters the price alert threshold percentage.
		 *
		 * @since 1.0.0
		 *
		 * @param float       $threshold        The threshold percentage.
		 * @param \WC_Product $product          The WooCommerce product object.
		 * @param string      $competitor_name  The competitor name.
		 * @param float       $competitor_price The competitor price.
		 */
		$threshold = apply_filters( 'ck_price_alert_threshold', $threshold, $product, $competitor_name, $competitor_price );

		/**
		 * Filters the notification email address.
		 *
		 * @since 1.0.0
		 *
		 * @param string      $email            The email address.
		 * @param \WC_Product $product          The WooCommerce product object.
		 * @param string      $competitor_name  The competitor name.
		 * @param float       $competitor_price The competitor price.
		 */
		$email = apply_filters( 'ck_price_alert_email', $email, $product, $competitor_name, $competitor_price );

		if ( ! $email || $my_price <= 0 ) {
			return;
		}

		$diff_percent = ( ( $my_price - $competitor_price ) / $my_price ) * 100;

		/**
		 * Filters whether a price alert should be sent.
		 *
		 * @since 1.0.0
		 *
		 * @param bool        $should_send      Whether to send the alert. Default is based on threshold.
		 * @param float       $diff_percent     The price difference percentage.
		 * @param float       $threshold        The configured threshold.
		 * @param \WC_Product $product          The WooCommerce product object.
		 * @param string      $competitor_name  The competitor name.
		 * @param float       $competitor_price The competitor price.
		 */
		$should_send = apply_filters(
			'ck_should_send_price_alert',
			$diff_percent >= $threshold,
			$diff_percent,
			$threshold,
			$product,
			$competitor_name,
			$competitor_price
		);

		if ( $should_send ) {
			$subject = sprintf(
				/* translators: 1: Product name, 2: Competitor name */
				__( 'Price Alert: %1$s is cheaper at %2$s', 'competitor-knowledge' ),
				$product->get_name(),
				$competitor_name
			);

			/**
			 * Filters the price alert email subject.
			 *
			 * @since 1.0.0
			 *
			 * @param string      $subject          The email subject.
			 * @param \WC_Product $product          The WooCommerce product object.
			 * @param string      $competitor_name  The competitor name.
			 * @param float       $diff_percent     The price difference percentage.
			 */
			$subject = apply_filters( 'ck_price_alert_subject', $subject, $product, $competitor_name, $diff_percent );

			/* translators: 1: Product name, 2: Product price, 3: Competitor name, 4: Competitor price, 5: Price difference percentage */
			$message = sprintf(
				__( "Alert!\n\nYour Product: %1\$s\nYour Price: %2\$s\n\nCompetitor: %3\$s\nCompetitor Price: %4\$s\nDifference: %5\$s%%\n\nLogin to view details.", 'competitor-knowledge' ),
				$product->get_name(),
				$my_price,
				$competitor_name,
				$competitor_price,
				round( $diff_percent, 2 )
			);

			/**
			 * Filters the price alert email message.
			 *
			 * @since 1.0.0
			 *
			 * @param string      $message          The email message.
			 * @param \WC_Product $product          The WooCommerce product object.
			 * @param string      $competitor_name  The competitor name.
			 * @param float       $competitor_price The competitor price.
			 * @param float       $diff_percent     The price difference percentage.
			 */
			$message = apply_filters( 'ck_price_alert_message', $message, $product, $competitor_name, $competitor_price, $diff_percent );

			/**
			 * Fires before a price alert email is sent.
			 *
			 * @since 1.0.0
			 *
			 * @param string      $email            The recipient email.
			 * @param string      $subject          The email subject.
			 * @param string      $message          The email message.
			 * @param \WC_Product $product          The WooCommerce product object.
			 * @param string      $competitor_name  The competitor name.
			 * @param float       $competitor_price The competitor price.
			 * @param int         $analysis_id      The analysis post ID.
			 */
			do_action( 'ck_before_price_alert', $email, $subject, $message, $product, $competitor_name, $competitor_price, $analysis_id );

			wp_mail( $email, $subject, $message );

			/**
			 * Fires after a price alert email is sent.
			 *
			 * @since 1.0.0
			 *
			 * @param string      $email            The recipient email.
			 * @param string      $subject          The email subject.
			 * @param string      $message          The email message.
			 * @param \WC_Product $product          The WooCommerce product object.
			 * @param string      $competitor_name  The competitor name.
			 * @param float       $competitor_price The competitor price.
			 * @param int         $analysis_id      The analysis post ID.
			 */
			do_action( 'ck_after_price_alert', $email, $subject, $message, $product, $competitor_name, $competitor_price, $analysis_id );
		}
	}
}
