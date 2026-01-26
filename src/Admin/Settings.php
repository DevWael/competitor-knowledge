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
		add_action( 'admin_menu', array( $this, 'add_menu_page' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_filter( 'pre_update_option_' . self::OPTION_NAME, array( $this, 'encrypt_api_keys' ) );
	}

	/**
	 * Encrypt API keys before saving.
	 *
	 * @param array $new_value The new option value.
	 *
	 * @return array
	 */
	public function encrypt_api_keys( $new_value ): array {
		if ( ! is_array( $new_value ) ) {
			return $new_value;
		}

		// Encrypt sensitive fields
		$sensitive_fields = array( 'google_api_key', 'tavily_api_key' );

		foreach ( $sensitive_fields as $field ) {
			if ( ! empty( $new_value[ $field ] ) ) {
				// Only encrypt if not already encrypted
				if ( ! \CompetitorKnowledge\Core\Encryption::is_encrypted( $new_value[ $field ] ) ) {
					$new_value[ $field ] = \CompetitorKnowledge\Core\Encryption::encrypt( $new_value[ $field ] );
				}
			}
		}

		return $new_value;
	}

	/**
	 * Get a decrypted option value.
	 *
	 * @param string $key     The option key.
	 * @param string $default Default value.
	 *
	 * @return string
	 */
	public static function get_decrypted( string $key, string $default = '' ): string {
		$options = get_option( self::OPTION_NAME );
		$value   = $options[ $key ] ?? $default;

		return \CompetitorKnowledge\Core\Encryption::decrypt( $value );
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
			array( $this, 'render_settings_page' )
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
			array( $this, 'render_field_ai_provider' ),
			'competitor-knowledge',
			'ck_general_section'
		);

		add_settings_field(
			'google_api_key',
			__( 'Google Only: API Key', 'competitor-knowledge' ),
			array( $this, 'render_field_text' ),
			'competitor-knowledge',
			'ck_general_section',
			array(
				'id'    => 'google_api_key',
				'class' => 'ck-field-google',
			)
		);

		add_settings_field(
			'ollama_url',
			__( 'Ollama Only: API URL', 'competitor-knowledge' ),
			array( $this, 'render_field_text' ),
			'competitor-knowledge',
			'ck_general_section',
			array(
				'id'      => 'ollama_url',
				'default' => 'http://localhost:11434',
				'class'   => 'ck-field-ollama',
			)
		);

		add_settings_field(
			'openrouter_api_key',
			__( 'OpenRouter Only: API Key', 'competitor-knowledge' ),
			array( $this, 'render_field_text' ),
			'competitor-knowledge',
			'ck_general_section',
			array(
				'id'    => 'openrouter_api_key',
				'class' => 'ck-field-openrouter',
			)
		);

		add_settings_field(
			'model_name',
			__( 'Model Name', 'competitor-knowledge' ),
			array( $this, 'render_field_text' ),
			'competitor-knowledge',
			'ck_general_section',
			array(
				'id'          => 'model_name',
				'default'     => 'gemini-2.0-flash-exp',
				'description' => __( 'Google: gemini-2.0-flash-exp | Ollama: llama3 | OpenRouter: anthropic/claude-3.5-sonnet', 'competitor-knowledge' ),
			)
		);

		add_settings_field(
			'tavily_api_key',
			__( 'Tavily Search API Key', 'competitor-knowledge' ),
			array( $this, 'render_field_text' ),
			'competitor-knowledge',
			'ck_general_section',
			array( 'id' => 'tavily_api_key' )
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
			array( $this, 'render_field_text' ),
			'competitor-knowledge',
			'ck_notification_section',
			array(
				'id'      => 'notification_email',
				'default' => get_option( 'admin_email' ),
			)
		);

		add_settings_field(
			'price_drop_threshold',
			__( 'Price Drop Threshold (%)', 'competitor-knowledge' ),
			array( $this, 'render_field_number' ),
			'competitor-knowledge',
			'ck_notification_section',
			array(
				'id'      => 'price_drop_threshold',
				'default' => '10',
			)
		);

		// Scheduled Analysis Settings
		add_settings_section(
			'ck_scheduled_section',
			__( 'Scheduled Analysis', 'competitor-knowledge' ),
			'__return_empty_string',
			'competitor-knowledge'
		);

		add_settings_field(
			'scheduled_analysis_enabled',
			__( 'Enable Scheduled Analysis', 'competitor-knowledge' ),
			array( $this, 'render_field_checkbox' ),
			'competitor-knowledge',
			'ck_scheduled_section',
			array( 'id' => 'scheduled_analysis_enabled' )
		);

		add_settings_field(
			'scheduled_analysis_frequency',
			__( 'Frequency', 'competitor-knowledge' ),
			array( $this, 'render_field_select' ),
			'competitor-knowledge',
			'ck_scheduled_section',
			array(
				'id'      => 'scheduled_analysis_frequency',
				'options' => array(
					'daily'   => __( 'Daily', 'competitor-knowledge' ),
					'weekly'  => __( 'Weekly', 'competitor-knowledge' ),
					'monthly' => __( 'Monthly', 'competitor-knowledge' ),
				),
			)
		);
	}

	/**
	 * Render a checkbox field.
	 *
	 * @param array $args Field arguments.
	 */
	public function render_field_checkbox( array $args ): void {
		$options = get_option( self::OPTION_NAME );
		$id      = $args['id'];
		$value   = $options[ $id ] ?? false;
		?>
		<input type="checkbox" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[<?php echo esc_attr( $id ); ?>]" value="1" <?php checked( $value, 1 ); ?>>
		<?php
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
	 * Render a select field.
	 *
	 * @param array $args Field arguments.
	 */
	public function render_field_select( array $args ): void {
		$options        = get_option( self::OPTION_NAME );
		$id             = $args['id'];
		$default        = $args['default'] ?? '';
		$value          = $options[ $id ] ?? $default;
		$select_options = $args['options'] ?? array();
		?>
		<select name="<?php echo esc_attr( self::OPTION_NAME ); ?>[<?php echo esc_attr( $id ); ?>]">
			<?php foreach ( $select_options as $option_value => $option_label ) : ?>
				<option value="<?php echo esc_attr( $option_value ); ?>" <?php selected( $value, $option_value ); ?>>
					<?php echo esc_html( $option_label ); ?>
				</option>
			<?php endforeach; ?>
		</select>
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
		<select name="<?php echo esc_attr( self::OPTION_NAME ); ?>[ai_provider]" id="ck-ai-provider">
			<option value="google" <?php selected( $value, 'google' ); ?>>Google Gemini</option>
			<option value="ollama" <?php selected( $value, 'ollama' ); ?>>Ollama (Local)</option>
			<option value="openrouter" <?php selected( $value, 'openrouter' ); ?>>OpenRouter</option>
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
		$options     = get_option( self::OPTION_NAME );
		$id          = $args['id'];
		$default     = $args['default'] ?? '';
		$value       = $options[ $id ] ?? $default;
		$class       = $args['class'] ?? '';
		$description = $args['description'] ?? '';
		?>
		<input type="text" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[<?php echo esc_attr( $id ); ?>]" value="<?php echo esc_attr( $value ); ?>" class="regular-text <?php echo esc_attr( $class ); ?>">
		<?php if ( $description ) : ?>
			<p class="description"><?php echo esc_html( $description ); ?></p>
		<?php endif; ?>
		<?php
	}
}
