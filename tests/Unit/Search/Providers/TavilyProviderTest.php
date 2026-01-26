<?php

declare(strict_types=1);

namespace CompetitorKnowledge\Tests\Unit\Search\Providers;

use CompetitorKnowledge\Search\Providers\TavilyProvider;
use CompetitorKnowledge\Search\DTO\SearchResults;
use RuntimeException;
use PHPUnit\Framework\TestCase;
use Brain\Monkey;
use Mockery;

class TavilyProviderTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
	}

	protected function tearDown(): void {
		Mockery::close();
		Monkey\tearDown();
		parent::tearDown();
	}

	public function test_constructor_sets_api_key() {
		$api_key  = 'test-api-key';
		$provider = new TavilyProvider( $api_key );

		$this->assertInstanceOf( TavilyProvider::class, $provider );
	}

	public function test_search_sends_correct_api_request() {
		$provider = new TavilyProvider( 'test-api-key' );
		$query    = 'best competitor products';
		$limit    = 5;

		$expected_response = array(
			'results' => array(
				array(
					'url'     => 'https://example.com',
					'title'   => 'Product 1',
					'content' => 'Description',
				),
			),
		);

		Monkey\Functions\expect( 'wp_json_encode' )
			->andReturnUsing( function ( $data ) {
				return json_encode( $data );
			} );

		Monkey\Functions\expect( 'wp_remote_post' )
			->once()
			->with(
				'https://api.tavily.com/search',
				Mockery::on( function ( $args ) {
					return isset( $args['headers']['Content-Type'] )
						&& $args['headers']['Content-Type'] === 'application/json'
						&& $args['timeout'] === 30;
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

		$result = $provider->search( $query, $limit );

		$this->assertInstanceOf( SearchResults::class, $result );
		$this->assertFalse( $result->is_empty() );
	}

	public function test_search_returns_search_results_dto() {
		$provider = new TavilyProvider( 'test-api-key' );
		$query    = 'test query';

		$api_response = array(
			'results' => array(
				array(
					'url'     => 'https://example1.com',
					'title'   => 'Result 1',
					'content' => 'Content 1',
				),
				array(
					'url'     => 'https://example2.com',
					'title'   => 'Result 2',
					'content' => 'Content 2',
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
					'body'     => json_encode( $api_response ),
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
			->andReturn( json_encode( $api_response ) );

		$result = $provider->search( $query );

		$this->assertInstanceOf( SearchResults::class, $result );
		$this->assertCount( 2, $result->get_results() );
	}

	public function test_search_handles_api_errors() {
		$provider = new TavilyProvider( 'test-api-key' );
		$query    = 'test query';

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
		$this->expectExceptionMessage( 'Tavily Search Failed: Connection failed' );

		$provider->search( $query );
	}

	public function test_search_handles_non_200_status() {
		$provider = new TavilyProvider( 'test-api-key' );
		$query    = 'test query';

		$error_response = array(
			'error' => 'Invalid API key',
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
		$this->expectExceptionMessage( 'Tavily Search Error: Invalid API key' );

		$provider->search( $query );
	}

	public function test_search_respects_max_results_parameter() {
		$provider = new TavilyProvider( 'test-api-key' );
		$query    = 'test query';
		$limit    = 10;

		$api_response = array(
			'results' => array_fill( 0, 10, array( 'url' => 'https://example.com' ) ),
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
					'body'     => json_encode( $api_response ),
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
			->andReturn( json_encode( $api_response ) );

		$result = $provider->search( $query, $limit );

		$this->assertCount( 10, $result->get_results() );
	}

	public function test_search_handles_missing_results_key() {
		$provider = new TavilyProvider( 'test-api-key' );
		$query    = 'test query';

		$invalid_response = array(
			'data' => array(),
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
					'body'     => json_encode( $invalid_response ),
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
			->andReturn( json_encode( $invalid_response ) );

		$this->expectException( RuntimeException::class );
		$this->expectExceptionMessage( 'Tavily Search Error: Unknown error' );

		$provider->search( $query );
	}
}
