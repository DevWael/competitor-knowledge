<?php

declare(strict_types=1);

namespace CompetitorKnowledge\Tests\Unit\AI\Providers;

use CompetitorKnowledge\AI\Providers\OllamaProvider;
use CompetitorKnowledge\AI\DTO\AnalysisResult;
use RuntimeException;
use PHPUnit\Framework\TestCase;
use Brain\Monkey;
use Mockery;

class OllamaProviderTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
	}

	protected function tearDown(): void {
		Mockery::close();
		Monkey\tearDown();
		parent::tearDown();
	}

	public function test_constructor_sets_api_url_and_model() {
		$api_url = 'http://localhost:11434';
		$model   = 'llama3';

		$provider = new OllamaProvider( $api_url, $model );

		$this->assertInstanceOf( OllamaProvider::class, $provider );
	}

	public function test_constructor_uses_default_values() {
		$provider = new OllamaProvider();

		$this->assertInstanceOf( OllamaProvider::class, $provider );
	}

	public function test_analyze_sends_correct_request_format() {
		$provider = new OllamaProvider( 'http://localhost:11434', 'llama3' );
		$prompt   = 'Analyze this data';
		$context  = array( 'product' => 'Test Product' );

		$expected_response = array(
			'response' => wp_json_encode( array( 'competitors' => array() ) ),
		);

		Monkey\Functions\expect( 'wp_json_encode' )
			->andReturnUsing( function ( $data ) {
				return json_encode( $data );
			} );

		Monkey\Functions\expect( 'wp_remote_post' )
			->once()
			->with(
				'http://localhost:11434/api/generate',
				Mockery::on( function ( $args ) {
					return isset( $args['headers']['Content-Type'] )
						&& $args['headers']['Content-Type'] === 'application/json'
						&& $args['timeout'] === 120;
				} )
			)
			->andReturn(
				array(
					'response' => array( 'code' => 200 ),
					'body'     => json_encode( $expected_response ),
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
			->andReturn( json_encode( $expected_response ) );

		$result = $provider->analyze( $prompt, $context );

		$this->assertInstanceOf( AnalysisResult::class, $result );
	}

	public function test_analyze_returns_analysis_result_on_success() {
		$provider = new OllamaProvider();
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

		$ollama_response = array(
			'response' => json_encode( $ai_response ),
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
					'body'     => json_encode( $ollama_response ),
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
			->andReturn( json_encode( $ollama_response ) );

		$result = $provider->analyze( $prompt, $context );

		$this->assertInstanceOf( AnalysisResult::class, $result );
		$this->assertEquals( $ai_response, $result->get_insights() );
	}

	public function test_analyze_throws_exception_on_wp_error() {
		$provider = new OllamaProvider();
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
		$this->expectExceptionMessage( 'Ollama AI Failed: Connection failed' );

		$provider->analyze( $prompt, $context );
	}

	public function test_analyze_throws_exception_on_non_200_status() {
		$provider = new OllamaProvider();
		$prompt   = 'Test prompt';
		$context  = array();

		$error_response = array(
			'error' => 'Model not found',
		);

		Monkey\Functions\expect( 'wp_json_encode' )
			->andReturnUsing( function ( $data ) {
				return json_encode( $data );
			} );

		Monkey\Functions\expect( 'wp_remote_post' )
			->once()
			->andReturn(
				array(
					'response' => array( 'code' => 404 ),
					'body'     => json_encode( $error_response ),
				)
			);

		Monkey\Functions\expect( 'is_wp_error' )
			->once()
			->andReturn( false );

		Monkey\Functions\expect( 'wp_remote_retrieve_response_code' )
			->once()
			->andReturn( 404 );

		Monkey\Functions\expect( 'wp_remote_retrieve_body' )
			->once()
			->andReturn( json_encode( $error_response ) );

		$this->expectException( RuntimeException::class );
		$this->expectExceptionMessage( 'Ollama AI Error: Model not found' );

		$provider->analyze( $prompt, $context );
	}

	public function test_analyze_handles_json_parsing_errors() {
		$provider = new OllamaProvider();
		$prompt   = 'Test prompt';
		$context  = array();

		$ollama_response = array(
			'response' => 'invalid json {{{',
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
					'body'     => json_encode( $ollama_response ),
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
			->andReturn( json_encode( $ollama_response ) );

		$this->expectException( RuntimeException::class );
		$this->expectExceptionMessage( 'Failed to parse Ollama response as JSON' );

		$provider->analyze( $prompt, $context );
	}

	public function test_analyze_cleans_markdown_code_blocks() {
		$provider = new OllamaProvider();
		$prompt   = 'Test prompt';
		$context  = array();

		$ai_response = array( 'competitors' => array() );

		// Response wrapped in markdown code blocks
		$ollama_response = array(
			'response' => "```json\n" . json_encode( $ai_response ) . "\n```",
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
					'body'     => json_encode( $ollama_response ),
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
			->andReturn( json_encode( $ollama_response ) );

		$result = $provider->analyze( $prompt, $context );

		$this->assertInstanceOf( AnalysisResult::class, $result );
		$this->assertEquals( $ai_response, $result->get_insights() );
	}
}
