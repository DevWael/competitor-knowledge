<?php

declare(strict_types=1);

namespace CompetitorKnowledge\Tests\Unit\Search\DTO;

use CompetitorKnowledge\Search\DTO\SearchResults;
use PHPUnit\Framework\TestCase;

class SearchResultsTest extends TestCase {

	public function test_constructor_accepts_array() {
		$data    = array( array( 'url' => 'https://example.com' ) );
		$results = new SearchResults( $data );

		$this->assertInstanceOf( SearchResults::class, $results );
	}

	public function test_get_results_returns_data() {
		$data = array(
			array(
				'url'     => 'https://example.com',
				'title'   => 'Example Product',
				'content' => 'Product description',
			),
			array(
				'url'     => 'https://competitor.com',
				'title'   => 'Competitor Product',
				'content' => 'Another description',
			),
		);

		$results = new SearchResults( $data );

		$this->assertEquals( $data, $results->get_results() );
		$this->assertCount( 2, $results->get_results() );
	}

	public function test_is_empty_returns_true_for_empty_array() {
		$results = new SearchResults( array() );

		$this->assertTrue( $results->is_empty() );
	}

	public function test_is_empty_returns_false_for_non_empty_array() {
		$data = array(
			array( 'url' => 'https://example.com' ),
		);

		$results = new SearchResults( $data );

		$this->assertFalse( $results->is_empty() );
	}

	public function test_handles_single_result() {
		$data = array(
			array(
				'url'     => 'https://single.com',
				'title'   => 'Single Result',
				'content' => 'Only one result',
			),
		);

		$results = new SearchResults( $data );

		$this->assertFalse( $results->is_empty() );
		$this->assertCount( 1, $results->get_results() );
	}

	public function test_handles_multiple_results() {
		$data = array();
		for ( $i = 0; $i < 10; $i++ ) {
			$data[] = array(
				'url'     => "https://example{$i}.com",
				'title'   => "Product {$i}",
				'content' => "Description {$i}",
			);
		}

		$results = new SearchResults( $data );

		$this->assertFalse( $results->is_empty() );
		$this->assertCount( 10, $results->get_results() );
	}

	public function test_preserves_result_structure() {
		$data = array(
			array(
				'url'      => 'https://example.com',
				'title'    => 'Test Product',
				'content'  => 'Description',
				'metadata' => array(
					'score'  => 0.95,
					'source' => 'tavily',
				),
			),
		);

		$results = new SearchResults( $data );
		$stored  = $results->get_results();

		$this->assertEquals( $data[0]['metadata']['score'], $stored[0]['metadata']['score'] );
		$this->assertEquals( $data[0]['metadata']['source'], $stored[0]['metadata']['source'] );
	}
}
