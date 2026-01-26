<?php

declare(strict_types=1);

namespace CompetitorKnowledge\Admin;

/**
 * Class Settings
 *
 * Handles the plugin settings page.
 *
 * @package CompetitorKnowledge\Admin
 */
class Settings {

	/**
	 * Option Name.
	 */
	public const OPTION_NAME = 'ck_settings';

	/**
	 * Initialize the settings.
	 */
	public function init(): void {
		add_action( 'admin_menu', [ $this, 'add_menu_page' ] );
		add_action( 'admin_init', [ $this, 'register_settings' ] );
	}

	/**
	 * Add the menu page.
	 */
	public function add_menu_page(): void {
		add_options_page(
			__( 'Competitor Knowledge', 'competitor-knowledge' ),
			__( 'Competitor Knowledge', 'competitor-knowledge' ),
			'manage_options',
			'competitor-knowledge',
			[ $this, 'render_settings_page' ]
		);
	}

	/**
	 * Register settings and fields.
	 */
	public function register_settings(): void {
		register_setting( 'competitor_knowledge_options', self::OPTION_NAME );

		add_settings_section(
			'ck_general_section',
			__( 'General Settings', 'competitor-knowledge' ),
			'__return_empty_string', // Valid callback
			'competitor-knowledge'
		);

		add_settings_field(
			'ai_provider',
			__( 'AI Provider', 'competitor-knowledge' ),
			[ $this, 'render_field_ai_provider' ],
			'competitor-knowledge',
			'ck_general_section'
		);

		add_settings_field(
			'google_api_key',
			__( 'Google Only: API Key', 'competitor-knowledge' ),
			[ $this, 'render_field_text' ],
			'competitor-knowledge',
			'ck_general_section',
			[ 'id' => 'google_api_key' ]
		);

		add_settings_field(
			'ollama_url',
			__( 'Ollama Only: API URL', 'competitor-knowledge' ),
			[ $this, 'render_field_text' ],
			'competitor-knowledge',
			'ck_general_section',
			[
				'id'      => 'ollama_url',
				'default' => 'http://localhost:11434',
			]
		);

		add_settings_field(
			'tavily_api_key',
			__( 'Tavily Search API Key', 'competitor-knowledge' ),
			[ $this, 'render_field_text' ],
			'competitor-knowledge',
			'ck_general_section',
			[ 'id' => 'tavily_api_key' ]
		);

		// Notification Settings
		add_settings_section(
			'ck_notification_section',
			__( 'Notifications', 'competitor-knowledge' ),
			'__return_empty_string',
			'competitor-knowledge'
		);

		add_settings_field(
			'notification_email',
			__( 'Notification Email', 'competitor-knowledge' ),
			[ $this, 'render_field_text' ],
			'competitor-knowledge',
			'ck_notification_section',
			[ 
				'id'      => 'notification_email',
				'default' => get_option( 'admin_email' ),
			]
		);

		add_settings_field(
			'price_drop_threshold',
			__( 'Price Drop Threshold (%)', 'competitor-knowledge' ),
			[ $this, 'render_field_number' ],
			'competitor-knowledge',
			'ck_notification_section',
			[ 
				'id'      => 'price_drop_threshold',
				'default' => '10',
			]
		);
	}

	/**
	 * Render a number field.
	 *
	 * @param array $args Field arguments.
	 */
	public function render_field_number( array $args ): void {
		$options = get_option( self::OPTION_NAME );
		$id      = $args['id'];
		$default = $args['default'] ?? '0';
		$value   = $options[ $id ] ?? $default;
		?>
		<input type="number" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[<?php echo esc_attr( $id ); ?>]" value="<?php echo esc_attr( $value ); ?>" class="small-text" min="0" max="100">
		<?php
	}

	/**
	 * Render the settings page.
	 */
	public function render_settings_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		?>
		<div class="wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
			<form action="options.php" method="post">
				<?php
				settings_fields( 'competitor_knowledge_options' );
				do_settings_sections( 'competitor-knowledge' );
				submit_button();
				?>
			</form>
		</div>
		<?php
	}

	/**
	 * Render AI Provider select field.
	 */
	public function render_field_ai_provider(): void {
		$options = get_option( self::OPTION_NAME );
		$value   = $options['ai_provider'] ?? 'google';
		?>
		<select name="<?php echo esc_attr( self::OPTION_NAME ); ?>[ai_provider]">
			<option value="google" <?php selected( $value, 'google' ); ?>>Google Gemini</option>
			<option value="ollama" <?php selected( $value, 'ollama' ); ?>>Ollama (Local)</option>
		</select>
		<p class="description"><?php esc_html_e( 'Select the AI provider to use for analysis.', 'competitor-knowledge' ); ?></p>
		<?php
	}

	/**
	 * Render a text field.
	 *
	 * @param array $args Field arguments.
	 */
	public function render_field_text( array $args ): void {
		$options = get_option( self::OPTION_NAME );
		$id      = $args['id'];
		$default = $args['default'] ?? '';
		$value   = $options[ $id ] ?? $default;
		?>
		<input type="text" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[<?php echo esc_attr( $id ); ?>]" value="<?php echo esc_attr( $value ); ?>" class="regular-text">
		<?php
	}
}
