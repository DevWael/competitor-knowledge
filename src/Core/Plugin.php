<?php

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
		$this->register_services();
		$this->register_hooks();
	}

	/**
	 * Register services in the container.
	 */
	private function register_services(): void {
		// Bind Settings
		$this->container->bind( Settings::class, function () {
			return new Settings();
		} );

		// Bind Search Provider
		$this->container->bind( \CompetitorKnowledge\Search\Contracts\SearchProviderInterface::class, function () {
			$options = get_option( Settings::OPTION_NAME );
			$api_key = $options['tavily_api_key'] ?? '';
			return new \CompetitorKnowledge\Search\Providers\TavilyProvider( $api_key );
		} );

		// Bind AI Provider
		$this->container->bind( \CompetitorKnowledge\AI\Contracts\AIProviderInterface::class, function () {
			$options  = get_option( Settings::OPTION_NAME );
			$provider = $options['ai_provider'] ?? 'google';
			$api_key  = $options['google_api_key'] ?? '';
			// $ollama_url = $options['ollama_url'] ?? '';

			if ( 'ollama' === $provider ) {
				// TODO: Implement OllamaProvider
				throw new \RuntimeException( 'Ollama provider not yet implemented.' );
			}

			// Default to Google
			return new \CompetitorKnowledge\AI\Providers\GoogleGeminiProvider( $api_key );
		} );

		// Bind Analyzer
		$this->container->bind( \CompetitorKnowledge\Analysis\Analyzer::class, function ( $c ) {
			return new \CompetitorKnowledge\Analysis\Analyzer(
				$c->get( \CompetitorKnowledge\Search\Contracts\SearchProviderInterface::class ),
				$c->get( \CompetitorKnowledge\AI\Contracts\AIProviderInterface::class ),
				new \CompetitorKnowledge\Data\AnalysisRepository()
			);
		} );
	}

	/**
	 * Register WP hooks.
	 */
	private function register_hooks(): void {
		// Register CPT
		add_action( 'init', [ $this, 'register_cpt' ] );

		// Register Admin UI (Settings, Metaboxes, Ajax)
		if ( is_admin() ) {
			( new Settings() )->init();
			( new Metaboxes() )->init();
			( new Ajax() )->init();
		}

		// Register Job Handler
		\CompetitorKnowledge\Analysis\Jobs\AnalysisJob::init();

		// Assets
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
	}

	/**
	 * Register Custom Post Types.
	 */
	public function register_cpt(): void {
		( new AnalysisCPT() )->register();
	}

	/**
	 * Enqueue Admin Assets.
	 */
	public function enqueue_assets(): void {
		wp_enqueue_script(
			'ck-admin-js',
			COMPETITOR_KNOWLEDGE_URL . 'assets/js/admin.min.js',
			[ 'jquery' ],
			COMPETITOR_KNOWLEDGE_VERSION,
			true
		);

		wp_localize_script( 'ck-admin-js', 'ck_vars', [
			'ajax_url'     => admin_url( 'admin-ajax.php' ),
			'nonce'        => wp_create_nonce( 'ck_nonce' ),
			'running_text' => __( 'Starting...', 'competitor-knowledge' ),
			'btn_text'     => __( 'Run New Analysis', 'competitor-knowledge' ),
		] );
	}
}
