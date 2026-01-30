<?php
/**
 * Core Plugin Bootstrap.
 *
 * @package CompetitorKnowledge\Core
 */

declare(strict_types=1);

namespace CompetitorKnowledge\Core;

use CompetitorKnowledge\Data\AnalysisCPT;
use CompetitorKnowledge\Admin\Settings;
use CompetitorKnowledge\Admin\Metaboxes;
use CompetitorKnowledge\Admin\Ajax;

/**
 * Class Plugin
 *
 * Core Plugin Bootstrap Class.
 *
 * @package CompetitorKnowledge\Core
 */
class Plugin {

	/**
	 * Container instance.
	 *
	 * @var Container
	 */
	private Container $container;

	/**
	 * Plugin constructor.
	 */
	public function __construct() {
		$this->container = Container::get_instance();
	}

	/**
	 * Run the plugin.
	 */
	public function run(): void {
		/**
		 * Fires before the plugin services are registered.
		 *
		 * @since 1.0.0
		 *
		 * @param Container $container The service container instance.
		 */
		do_action( 'ck_before_init', $this->container );

		$this->register_services();
		$this->register_hooks();

		/**
		 * Fires after the plugin is fully initialized.
		 *
		 * @since 1.0.0
		 *
		 * @param Container $container The service container instance.
		 */
		do_action( 'ck_after_init', $this->container );
	}

	/**
	 * Register services in the container.
	 */
	private function register_services(): void {
		// Bind Settings.
		$this->container->bind(
			Settings::class,
			function () {
				return new Settings();
			}
		);

		// Bind Search Provider.
		$this->container->bind(
			\CompetitorKnowledge\Search\Contracts\SearchProviderInterface::class,
			function () {
				$options          = get_option( Settings::OPTION_NAME );
				$search_provider  = $options['search_provider'] ?? 'tavily';
				$tavily_api_key   = Settings::get_decrypted( 'tavily_api_key' );
				$brave_api_key    = Settings::get_decrypted( 'brave_api_key' );

				if ( 'brave' === $search_provider ) {
					$provider = new \CompetitorKnowledge\Search\Providers\BraveProvider( $brave_api_key );
				} else {
					// Default to Tavily.
					$provider = new \CompetitorKnowledge\Search\Providers\TavilyProvider( $tavily_api_key );
				}

				/**
				 * Filters the search provider instance.
				 *
				 * Allows third-party plugins to replace or decorate the search provider.
				 *
				 * @since 1.0.0
				 *
				 * @param \CompetitorKnowledge\Search\Contracts\SearchProviderInterface $provider The search provider.
				 */
				return apply_filters( 'ck_search_provider', $provider );
			}
		);

		// Bind AI Provider.
		$this->container->bind(
			\CompetitorKnowledge\AI\Contracts\AIProviderInterface::class,
			function () {
				$options        = get_option( Settings::OPTION_NAME );
				$provider_type  = $options['ai_provider'] ?? 'google';
				$google_key     = Settings::get_decrypted( 'google_api_key' );
				$openrouter_key = Settings::get_decrypted( 'openrouter_api_key' );
				$zai_key        = Settings::get_decrypted( 'zai_api_key' );
				$ollama_url     = $options['ollama_url'] ?? 'http://localhost:11434';
				$model_name     = $options['model_name'] ?? 'gemini-2.0-flash-exp';

				/**
				 * Filters the AI model name used for analysis.
				 *
				 * @since 1.0.0
				 *
				 * @param string $model_name   The model name.
				 * @param string $provider_type The provider type (google, ollama, openrouter, zai).
				 */
				$model_name = apply_filters( 'ck_ai_model_name', $model_name, $provider_type );

				if ( 'ollama' === $provider_type ) {
					$provider = new \CompetitorKnowledge\AI\Providers\OllamaProvider( $ollama_url, $model_name );
				} elseif ( 'openrouter' === $provider_type ) {
					$provider = new \CompetitorKnowledge\AI\Providers\OpenRouterProvider( $openrouter_key, $model_name );
				} elseif ( 'zai' === $provider_type ) {
					$provider = new \CompetitorKnowledge\AI\Providers\ZAIProvider( $zai_key, $model_name );
				} else {
					// Default to Google.
					$provider = new \CompetitorKnowledge\AI\Providers\GoogleGeminiProvider( $google_key, $model_name );
				}

				/**
				 * Filters the AI provider instance.
				 *
				 * Allows third-party plugins to replace or decorate the AI provider.
				 *
				 * @since 1.0.0
				 *
				 * @param \CompetitorKnowledge\AI\Contracts\AIProviderInterface $provider      The AI provider.
				 * @param string                                                $provider_type The provider type.
				 * @param string                                                $model_name    The model name.
				 */
				return apply_filters( 'ck_ai_provider', $provider, $provider_type, $model_name );
			}
		);

		// Bind PromptBuilder.
		$this->container->bind(
			\CompetitorKnowledge\Analysis\PromptBuilder::class,
			function () {
				$options         = get_option( Settings::OPTION_NAME );
				$model_name      = $options['model_name'] ?? 'gemini-2.0-flash-exp';
				
				// Map individual module checkboxes to enabled modules array.
				$enabled_modules = array();
				if ( ! empty( $options['intelligence_module_pricing'] ) ) {
					$enabled_modules[] = 'pricing';
				}
				if ( ! empty( $options['intelligence_module_catalog'] ) ) {
					$enabled_modules[] = 'catalog';
				}
				if ( ! empty( $options['intelligence_module_marketing'] ) ) {
					$enabled_modules[] = 'marketing';
				}

				/**
				 * Filters the enabled intelligence modules.
				 *
				 * @since 1.0.0
				 *
				 * @param array<string> $enabled_modules The enabled modules array.
				 */
				$enabled_modules = apply_filters( 'ck_intelligence_modules', $enabled_modules );

				return new \CompetitorKnowledge\Analysis\PromptBuilder( $model_name, $enabled_modules );
			}
		);

		// Bind Analyzer.
		$this->container->bind(
			\CompetitorKnowledge\Analysis\Analyzer::class,
			function ( $c ) {
				return new \CompetitorKnowledge\Analysis\Analyzer(
					$c->get( \CompetitorKnowledge\Search\Contracts\SearchProviderInterface::class ),
					$c->get( \CompetitorKnowledge\AI\Contracts\AIProviderInterface::class ),
					new \CompetitorKnowledge\Data\AnalysisRepository(),
					new \CompetitorKnowledge\Data\PriceHistoryRepository(),
					$c->get( \CompetitorKnowledge\Analysis\PromptBuilder::class )
				);
			}
		);

		/**
		 * Fires after core services are registered.
		 *
		 * Allows third-party plugins to register additional services.
		 *
		 * @since 1.0.0
		 *
		 * @param Container $container The service container instance.
		 */
		do_action( 'ck_services_registered', $this->container );
	}

	/**
	 * Register WP hooks.
	 */
	private function register_hooks(): void {
		// Register CPT.
		add_action( 'init', array( $this, 'register_cpt' ) );

		// Register Admin UI (Settings, Metaboxes, Ajax).
		if ( is_admin() ) {
			( new Settings() )->init();
			( new Metaboxes() )->init();
			( new Ajax() )->init();
			( new \CompetitorKnowledge\Admin\BulkActions() )->init();
			( new \CompetitorKnowledge\Admin\ComparisonMatrix() )->init();
		}

		// Register Job Handler.
		\CompetitorKnowledge\Analysis\Jobs\AnalysisJob::init();
		\CompetitorKnowledge\Analysis\Jobs\ScheduledAnalysisJob::init();

		// Register Step Jobs for progress tracking.
		\CompetitorKnowledge\Analysis\Jobs\SearchStepJob::init();
		\CompetitorKnowledge\Analysis\Jobs\AIAnalysisStepJob::init();
		\CompetitorKnowledge\Analysis\Jobs\SaveResultsStepJob::init();

		// Register Auto Re-analysis hooks.
		( new \CompetitorKnowledge\Analysis\AutoReanalysis() )->init();

		// Assets.
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );

		/**
		 * Fires after core hooks are registered.
		 *
		 * Allows third-party plugins to add their own hooks.
		 *
		 * @since 1.0.0
		 */
		do_action( 'ck_hooks_registered' );
	}

	/**
	 * Register Custom Post Types.
	 */
	public function register_cpt(): void {
		( new AnalysisCPT() )->register();

		/**
		 * Fires after the analysis CPT is registered.
		 *
		 * Allows third-party plugins to register related taxonomies or post types.
		 *
		 * @since 1.0.0
		 */
		do_action( 'ck_cpt_registered' );
	}

	/**
	 * Enqueue Admin Assets.
	 */
	public function enqueue_assets(): void {
		// Chart.js for Price History.
		wp_enqueue_script( 'chart-js', 'https://cdn.jsdelivr.net/npm/chart.js', array(), '4.4.1', true );
		// Chart.js Date Adapter (required for 'time' scale).
		wp_enqueue_script( 'chart-js-adapter', 'https://cdn.jsdelivr.net/npm/chartjs-adapter-date-fns/dist/chartjs-adapter-date-fns.bundle.min.js', array( 'chart-js' ), '3.0.0', true );

		wp_enqueue_script(
			'ck-admin-js',
			COMPETITOR_KNOWLEDGE_URL . 'assets/js/admin.min.js',
			array( 'jquery' ),
			COMPETITOR_KNOWLEDGE_VERSION,
			true
		);

		/**
		 * Filters the localized script data for admin JS.
		 *
		 * @since 1.0.0
		 *
		 * @param array<string, mixed> $data The localized data array.
		 */
		$localized_data = apply_filters(
			'ck_admin_script_data',
			array(
				'ajax_url'     => admin_url( 'admin-ajax.php' ),
				'nonce'        => wp_create_nonce( 'ck_nonce' ),
				'running_text' => __( 'Starting...', 'competitor-knowledge' ),
				'btn_text'     => __( 'Run New Analysis', 'competitor-knowledge' ),
			)
		);

		wp_localize_script( 'ck-admin-js', 'ck_vars', $localized_data );

		/**
		 * Fires after admin assets are enqueued.
		 *
		 * Allows third-party plugins to enqueue additional assets.
		 *
		 * @since 1.0.0
		 */
		do_action( 'ck_admin_assets_enqueued' );
	}
}
