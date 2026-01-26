<?php

declare(strict_types=1);

namespace CompetitorKnowledge\Tests\Unit\AI\Providers;

use CompetitorKnowledge\AI\Providers\OpenRouterProvider;
use CompetitorKnowledge\AI\DTO\AnalysisResult;
use RuntimeException;
use PHPUnit\Framework\TestCase;
use Brain\Monkey;
use Mockery;

class OpenRouterProviderTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
	}

	protected function tearDown(): void {
		Mockery::close();
		Monkey\tearDown();
		parent::tearDown();
	}

	public function test_constructor_sets_api_key_and_model() {
		$api_key = 'test-api-key';
		$model   = 'anthropic/claude-3.5-sonnet';

		$provider = new OpenRouterProvider( $api_key, $model );

		$this->assertInstanceOf( OpenRouterProvider::class, $provider );
	}

	public function test_constructor_uses_default_model() {
		$provider = new OpenRouterProvider( 'test-api-key' );

		$this->assertInstanceOf( OpenRouterProvider::class, $provider );
	}

	public function test_analyze_returns_analysis_result_on_success() {
		$provider = new OpenRouterProvider( 'test-api-key' );
		$prompt   = 'Analyze competitors';
		$context  = array( 'my_product' => array( 'name' => 'Product A' ) );

		$ai_response = array(
			'competitors' => array(
				array(
					'name'  => 'Competitor A',
					'price' => 99.99,
				),
			),
		);

		$openrouter_response = array(
			'choices' => array(
				array(
					'message' => array(
						'content' => json_encode( $ai_response ),
					),
				),
			),
		);

		Monkey\Functions\expect( 'wp_json_encode' )
			->andReturnUsing( function ( $data ) {
				return json_encode( $data );
			} );

		Monkey\Functions\expect( 'home_url' )
			->once()
			->andReturn( 'https://example.com' );

		Monkey\Functions\expect( 'wp_remote_post' )
			->once()
			->andReturn(
				array(
					'response' => array( 'code' => 200 ),
					'body'     => json_encode( $openrouter_response ),
				)
			);

		Monkey\Functions\expect( 'is_wp_error' )
			->once()
			->andReturn( false );

		Monkey\Functions\expect( 'wp_remote_retrieve_response_code' )
			->once()
			->andReturn( 200 );

		Monkey\Functions\expect( 'wp_remote_retrieve_body' )
			->once()
			->andReturn( json_encode( $openrouter_response ) );

		$result = $provider->analyze( $prompt, $context );

		$this->assertInstanceOf( AnalysisResult::class, $result );
		$this->assertEquals( $ai_response, $result->get_insights() );
	}

	public function test_analyze_throws_exception_on_wp_error() {
		$provider = new OpenRouterProvider( 'test-api-key' );
		$prompt   = 'Test prompt';
		$context  = array();

		$wp_error = Mockery::mock( 'WP_Error' );
		$wp_error->shouldReceive( 'get_error_message' )
			->andReturn( 'Connection failed' );

		Monkey\Functions\expect( 'wp_json_encode' )
			->andReturnUsing( function ( $data ) {
				return json_encode( $data );
			} );

		Monkey\Functions\expect( 'home_url' )
			->once()
			->andReturn( 'https://example.com' );

		Monkey\Functions\expect( 'wp_remote_post' )
			->once()
			->andReturn( $wp_error );

		Monkey\Functions\expect( 'is_wp_error' )
			->once()
			->with( $wp_error )
			->andReturn( true );

		$this->expectException( RuntimeException::class );
		$this->expectExceptionMessage( 'OpenRouter AI Failed: Connection failed' );

		$provider->analyze( $prompt, $context );
	}

	public function test_analyze_throws_exception_on_non_200_status() {
		$provider = new OpenRouterProvider( 'test-api-key' );
		$prompt   = 'Test prompt';
		$context  = array();

		$error_response = array(
			'error' => array(
				'message' => 'Invalid API key',
			),
		);

		Monkey\Functions\expect( 'wp_json_encode' )
			->andReturnUsing( function ( $data ) {
				return json_encode( $data );
			} );

		Monkey\Functions\expect( 'home_url' )
			->once()
			->andReturn( 'https://example.com' );

		Monkey\Functions\expect( 'wp_remote_post' )
			->once()
			->andReturn(
				array(
					'response' => array( 'code' => 401 ),
					'body'     => json_encode( $error_response ),
				)
			);

		Monkey\Functions\expect( 'is_wp_error' )
			->once()
			->andReturn( false );

		Monkey\Functions\expect( 'wp_remote_retrieve_response_code' )
			->once()
			->andReturn( 401 );

		Monkey\Functions\expect( 'wp_remote_retrieve_body' )
			->once()
			->andReturn( json_encode( $error_response ) );

		$this->expectException( RuntimeException::class );
		$this->expectExceptionMessage( 'OpenRouter AI Error: Invalid API key' );

		$provider->analyze( $prompt, $context );
	}

	public function test_analyze_handles_json_parsing_errors() {
		$provider = new OpenRouterProvider( 'test-api-key' );
		$prompt   = 'Test prompt';
		$context  = array();

		$openrouter_response = array(
			'choices' => array(
				array(
					'message' => array(
						'content' => 'invalid json {{{',
					),
				),
			),
		);

		Monkey\Functions\expect( 'wp_json_encode' )
			->andReturnUsing( function ( $data ) {
				return json_encode( $data );
			} );

		Monkey\Functions\expect( 'home_url' )
			->once()
			->andReturn( 'https://example.com' );

		Monkey\Functions\expect( 'wp_remote_post' )
			->once()
			->andReturn(
				array(
					'response' => array( 'code' => 200 ),
					'body'     => json_encode( $openrouter_response ),
				)
			);

		Monkey\Functions\expect( 'is_wp_error' )
			->once()
			->andReturn( false );

		Monkey\Functions\expect( 'wp_remote_retrieve_response_code' )
			->once()
			->andReturn( 200 );

		Monkey\Functions\expect( 'wp_remote_retrieve_body' )
			->once()
			->andReturn( json_encode( $openrouter_response ) );

		$this->expectException( RuntimeException::class );
		$this->expectExceptionMessage( 'Failed to parse OpenRouter response as JSON' );

		$provider->analyze( $prompt, $context );
	}

	public function test_analyze_cleans_markdown_code_blocks() {
		$provider = new OpenRouterProvider( 'test-api-key' );
		$prompt   = 'Test prompt';
		$context  = array();

		$ai_response = array( 'competitors' => array() );

		$openrouter_response = array(
			'choices' => array(
				array(
					'message' => array(
						'content' => "```json\n" . json_encode( $ai_response ) . "\n```",
					),
				),
			),
		);

		Monkey\Functions\expect( 'wp_json_encode' )
			->andReturnUsing( function ( $data ) {
				return json_encode( $data );
			} );

		Monkey\Functions\expect( 'home_url' )
			->once()
			->andReturn( 'https://example.com' );

		Monkey\Functions\expect( 'wp_remote_post' )
			->once()
			->andReturn(
				array(
					'response' => array( 'code' => 200 ),
					'body'     => json_encode( $openrouter_response ),
				)
			);

		Monkey\Functions\expect( 'is_wp_error' )
			->once()
			->andReturn( false );

		Monkey\Functions\expect( 'wp_remote_retrieve_response_code' )
			->once()
			->andReturn( 200 );

		Monkey\Functions\expect( 'wp_remote_retrieve_body' )
			->once()
			->andReturn( json_encode( $openrouter_response ) );

		$result = $provider->analyze( $prompt, $context );

		$this->assertInstanceOf( AnalysisResult::class, $result );
		$this->assertEquals( $ai_response, $result->get_insights() );
	}
}
