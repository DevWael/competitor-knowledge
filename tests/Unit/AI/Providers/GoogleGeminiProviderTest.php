<?php

declare(strict_types=1);

namespace CompetitorKnowledge\Tests\Unit\AI\Providers;

use CompetitorKnowledge\AI\Providers\GoogleGeminiProvider;
use CompetitorKnowledge\AI\DTO\AnalysisResult;
use RuntimeException;
use PHPUnit\Framework\TestCase;
use Brain\Monkey;
use Mockery;

class GoogleGeminiProviderTest extends TestCase {

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
		$model   = 'gemini-2.0-flash-exp';

		$provider = new GoogleGeminiProvider( $api_key, $model );

		$this->assertInstanceOf( GoogleGeminiProvider::class, $provider );
	}

	public function test_constructor_uses_default_model() {
		$provider = new GoogleGeminiProvider( 'test-api-key' );

		$this->assertInstanceOf( GoogleGeminiProvider::class, $provider );
	}

	public function test_analyze_returns_analysis_result_on_success() {
		$provider = new GoogleGeminiProvider( 'test-api-key' );
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

		$gemini_response = array(
			'candidates' => array(
				array(
					'content' => array(
						'parts' => array(
							array( 'text' => json_encode( $ai_response ) ),
						),
					),
				),
			),
		);

		Monkey\Functions\expect( 'wp_json_encode' )
			->andReturnUsing( function ( $data ) {
				return json_encode( $data );
			} );

		Monkey\Functions\expect( 'wp_remote_post' )
			->once()
			->andReturn(
				array(
					'response' => array( 'code' => 200 ),
					'body'     => json_encode( $gemini_response ),
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
			->andReturn( json_encode( $gemini_response ) );

		$result = $provider->analyze( $prompt, $context );

		$this->assertInstanceOf( AnalysisResult::class, $result );
		$this->assertEquals( $ai_response, $result->get_insights() );
	}

	public function test_analyze_throws_exception_on_wp_error() {
		$provider = new GoogleGeminiProvider( 'test-api-key' );
		$prompt   = 'Test prompt';
		$context  = array();

		$wp_error = Mockery::mock( 'WP_Error' );
		$wp_error->shouldReceive( 'get_error_message' )
			->andReturn( 'Connection failed' );

		Monkey\Functions\expect( 'wp_json_encode' )
			->andReturnUsing( function ( $data ) {
				return json_encode( $data );
			} );

		Monkey\Functions\expect( 'wp_remote_post' )
			->once()
			->andReturn( $wp_error );

		Monkey\Functions\expect( 'is_wp_error' )
			->once()
			->with( $wp_error )
			->andReturn( true );

		$this->expectException( RuntimeException::class );
		$this->expectExceptionMessage( 'Google AI Failed: Connection failed' );

		$provider->analyze( $prompt, $context );
	}

	public function test_analyze_throws_exception_on_non_200_status() {
		$provider = new GoogleGeminiProvider( 'test-api-key' );
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
		$this->expectExceptionMessage( 'Google AI Error: Invalid API key' );

		$provider->analyze( $prompt, $context );
	}

	public function test_analyze_handles_json_parsing_errors() {
		$provider = new GoogleGeminiProvider( 'test-api-key' );
		$prompt   = 'Test prompt';
		$context  = array();

		$gemini_response = array(
			'candidates' => array(
				array(
					'content' => array(
						'parts' => array(
							array( 'text' => 'invalid json {{{' ),
						),
					),
				),
			),
		);

		Monkey\Functions\expect( 'wp_json_encode' )
			->andReturnUsing( function ( $data ) {
				return json_encode( $data );
			} );

		Monkey\Functions\expect( 'wp_remote_post' )
			->once()
			->andReturn(
				array(
					'response' => array( 'code' => 200 ),
					'body'     => json_encode( $gemini_response ),
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
			->andReturn( json_encode( $gemini_response ) );

		$this->expectException( RuntimeException::class );
		$this->expectExceptionMessage( 'Failed to parse AI response as JSON' );

		$provider->analyze( $prompt, $context );
	}

	public function test_analyze_cleans_markdown_code_blocks() {
		$provider = new GoogleGeminiProvider( 'test-api-key' );
		$prompt   = 'Test prompt';
		$context  = array();

		$ai_response = array( 'competitors' => array() );

		// Response wrapped in markdown code blocks
		$gemini_response = array(
			'candidates' => array(
				array(
					'content' => array(
						'parts' => array(
							array( 'text' => "```json\n" . json_encode( $ai_response ) . "\n```" ),
						),
					),
				),
			),
		);

		Monkey\Functions\expect( 'wp_json_encode' )
			->andReturnUsing( function ( $data ) {
				return json_encode( $data );
			} );

		Monkey\Functions\expect( 'wp_remote_post' )
			->once()
			->andReturn(
				array(
					'response' => array( 'code' => 200 ),
					'body'     => json_encode( $gemini_response ),
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
			->andReturn( json_encode( $gemini_response ) );

		$result = $provider->analyze( $prompt, $context );

		$this->assertInstanceOf( AnalysisResult::class, $result );
		$this->assertEquals( $ai_response, $result->get_insights() );
	}
}
