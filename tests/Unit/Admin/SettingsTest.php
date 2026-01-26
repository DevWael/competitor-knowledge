<?php

declare(strict_types=1);

namespace CompetitorKnowledge\Tests\Unit\Admin;

use CompetitorKnowledge\Admin\Settings;
use PHPUnit\Framework\TestCase;
use Brain\Monkey;
use Mockery;

class SettingsTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
	}

	protected function tearDown(): void {
		Mockery::close();
		Monkey\tearDown();
		parent::tearDown();
	}

	public function test_option_name_constant_is_defined() {
		$this->assertEquals( 'ck_settings', Settings::OPTION_NAME );
	}

	public function test_init_registers_hooks() {
		Monkey\Functions\expect( 'add_action' )
			->twice();

		Monkey\Functions\expect( 'add_filter' )
			->once();

		$settings = new Settings();
		$settings->init();

		$this->assertTrue( true );
	}

	public function test_encrypt_api_keys_handles_empty_array() {
		$settings = new Settings();
		$result   = $settings->encrypt_api_keys( array() );

		$this->assertEquals( array(), $result );
	}

	public function test_encrypt_api_keys_encrypts_google_api_key() {
		$settings = new Settings();

		$new_value = array(
			'google_api_key' => 'my-secret-key',
			'other_field'    => 'value',
		);

		$result = $settings->encrypt_api_keys( $new_value );

		$this->assertNotEquals( 'my-secret-key', $result['google_api_key'] );
		$this->assertEquals( 'value', $result['other_field'] );
	}

	public function test_encrypt_api_keys_encrypts_tavily_api_key() {
		$settings = new Settings();

		$new_value = array(
			'tavily_api_key' => 'tavily-secret',
		);

		$result = $settings->encrypt_api_keys( $new_value );

		$this->assertNotEquals( 'tavily-secret', $result['tavily_api_key'] );
	}

	public function test_encrypt_api_keys_skips_already_encrypted() {
		$settings = new Settings();

		$encrypted = \CompetitorKnowledge\Core\Encryption::encrypt( 'my-secret' );

		$new_value = array(
			'google_api_key' => $encrypted,
		);

		$result = $settings->encrypt_api_keys( $new_value );

		$this->assertEquals( $encrypted, $result['google_api_key'] );
	}

	public function test_encrypt_api_keys_skips_empty_fields() {
		$settings = new Settings();

		$new_value = array(
			'google_api_key' => '',
			'tavily_api_key' => null,
		);

		$result = $settings->encrypt_api_keys( $new_value );

		$this->assertEquals( '', $result['google_api_key'] );
	}

	public function test_get_decrypted_returns_decrypted_value() {
		$encrypted = \CompetitorKnowledge\Core\Encryption::encrypt( 'my-api-key' );

		Monkey\Functions\expect( 'get_option' )
			->once()
			->with( Settings::OPTION_NAME )
			->andReturn( array( 'google_api_key' => $encrypted ) );

		$result = Settings::get_decrypted( 'google_api_key' );

		$this->assertEquals( 'my-api-key', $result );
	}

	public function test_get_decrypted_returns_default_when_missing() {
		Monkey\Functions\expect( 'get_option' )
			->once()
			->with( Settings::OPTION_NAME )
			->andReturn( array() );

		$result = Settings::get_decrypted( 'missing_key', 'default-value' );

		$this->assertEquals( 'default-value', $result );
	}

	public function test_add_menu_page_calls_add_options_page() {
		Monkey\Functions\expect( 'add_options_page' )
			->once()
			->with(
				Mockery::type( 'string' ),
				Mockery::type( 'string' ),
				'manage_options',
				'competitor-knowledge',
				Mockery::type( 'array' )
			);

		$settings = new Settings();
		$settings->add_menu_page();

		$this->assertTrue( true );
	}

	public function test_register_settings_registers_setting() {
		Monkey\Functions\expect( 'register_setting' )
			->once();

		Monkey\Functions\expect( 'add_settings_section' )
			->times( 3 );

		Monkey\Functions\expect( 'add_settings_field' )
			->times( 10 );

		Monkey\Functions\expect( 'get_option' )
			->andReturn( 'admin@example.com' );

		$settings = new Settings();
		$settings->register_settings();

		$this->assertTrue( true );
	}

	public function test_render_field_checkbox_outputs_html() {
		Monkey\Functions\expect( 'get_option' )
			->once()
			->andReturn( array( 'test_checkbox' => '1' ) );

		Monkey\Functions\stubs(
			array(
				'esc_attr' => function ( $text ) {
					return $text;
				},
				'checked'  => function ( $a, $b ) {
					if ( $a == $b ) {
						echo 'checked';
					}
				},
			)
		);

		$settings = new Settings();

		ob_start();
		$settings->render_field_checkbox( array( 'id' => 'test_checkbox' ) );
		$output = ob_get_clean();

		$this->assertStringContainsString( 'type="checkbox"', $output );
		$this->assertStringContainsString( 'test_checkbox', $output );
	}

	public function test_render_field_number_outputs_html() {
		Monkey\Functions\expect( 'get_option' )
			->once()
			->andReturn( array( 'threshold' => '15' ) );

		Monkey\Functions\stubs(
			array(
				'esc_attr' => function ( $text ) {
					return $text;
				},
			)
		);

		$settings = new Settings();

		ob_start();
		$settings->render_field_number( array( 'id' => 'threshold', 'default' => '10' ) );
		$output = ob_get_clean();

		$this->assertStringContainsString( 'type="number"', $output );
		$this->assertStringContainsString( '15', $output );
	}

	public function test_render_field_select_outputs_html() {
		Monkey\Functions\expect( 'get_option' )
			->once()
			->andReturn( array( 'frequency' => 'weekly' ) );

		Monkey\Functions\stubs(
			array(
				'esc_attr' => function ( $text ) {
					return $text;
				},
				'esc_html' => function ( $text ) {
					return $text;
				},
				'selected' => function ( $a, $b ) {
					if ( $a === $b ) {
						echo 'selected';
					}
				},
			)
		);

		$settings = new Settings();

		ob_start();
		$settings->render_field_select(
			array(
				'id'      => 'frequency',
				'options' => array(
					'daily'  => 'Daily',
					'weekly' => 'Weekly',
				),
			)
		);
		$output = ob_get_clean();

		$this->assertStringContainsString( '<select', $output );
		$this->assertStringContainsString( 'Daily', $output );
		$this->assertStringContainsString( 'Weekly', $output );
	}

	public function test_render_field_text_outputs_html() {
		Monkey\Functions\expect( 'get_option' )
			->once()
			->andReturn( array( 'api_key' => 'test-key' ) );

		Monkey\Functions\stubs(
			array(
				'esc_attr' => function ( $text ) {
					return $text;
				},
				'esc_html' => function ( $text ) {
					return $text;
				},
			)
		);

		$settings = new Settings();

		ob_start();
		$settings->render_field_text( array( 'id' => 'api_key' ) );
		$output = ob_get_clean();

		$this->assertStringContainsString( 'type="text"', $output );
		$this->assertStringContainsString( 'test-key', $output );
	}

	public function test_render_field_text_with_description() {
		Monkey\Functions\expect( 'get_option' )
			->once()
			->andReturn( array() );

		Monkey\Functions\stubs(
			array(
				'esc_attr' => function ( $text ) {
					return $text;
				},
				'esc_html' => function ( $text ) {
					return $text;
				},
			)
		);

		$settings = new Settings();

		ob_start();
		$settings->render_field_text(
			array(
				'id'          => 'model',
				'default'     => 'gemini',
				'description' => 'Choose model',
			)
		);
		$output = ob_get_clean();

		$this->assertStringContainsString( 'gemini', $output );
		$this->assertStringContainsString( 'Choose model', $output );
		$this->assertStringContainsString( 'description', $output );
	}

	public function test_render_field_ai_provider_outputs_select() {
		Monkey\Functions\expect( 'get_option' )
			->once()
			->andReturn( array( 'ai_provider' => 'ollama' ) );

		Monkey\Functions\expect( 'apply_filters' )
			->once()
			->with( 'ck_ai_provider_options', Mockery::type( 'array' ) )
			->andReturnUsing( function( $hook, $providers ) {
				return $providers;
			} );

		Monkey\Functions\stubs(
			array(
				'esc_attr'   => function ( $text ) {
					return $text;
				},
				'esc_html'   => function ( $text ) {
					return $text;
				},
				'selected'   => function ( $a, $b ) {
					if ( $a === $b ) {
						echo 'selected';
					}
				},
				'esc_html_e' => function ( $text ) {
					echo $text;
				},
			)
		);

		$settings = new Settings();

		ob_start();
		$settings->render_field_ai_provider();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'Google Gemini', $output );
		$this->assertStringContainsString( 'Ollama', $output );
		$this->assertStringContainsString( 'OpenRouter', $output );
	}

	public function test_render_settings_page_returns_early_without_capability() {
		Monkey\Functions\expect( 'current_user_can' )
			->once()
			->with( 'manage_options' )
			->andReturn( false );

		$settings = new Settings();

		ob_start();
		$settings->render_settings_page();
		$output = ob_get_clean();

		$this->assertEmpty( $output );
	}

	public function test_render_settings_page_outputs_form() {
		Monkey\Functions\expect( 'current_user_can' )
			->once()
			->with( 'manage_options' )
			->andReturn( true );

		Monkey\Functions\stubs(
			array(
				'esc_html'             => function ( $text ) {
					return $text;
				},
				'get_admin_page_title' => function () {
					return 'Settings';
				},
				'settings_fields'      => function () {},
				'do_settings_sections' => function () {},
				'submit_button'        => function () {
					echo '<button>Save</button>';
				},
			)
		);

		$settings = new Settings();

		ob_start();
		$settings->render_settings_page();
		$output = ob_get_clean();

		$this->assertStringContainsString( '<form', $output );
		$this->assertStringContainsString( 'options.php', $output );
	}
}
