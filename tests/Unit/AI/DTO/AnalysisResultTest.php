<?php

declare(strict_types=1);

namespace CompetitorKnowledge\Tests\Unit\AI\DTO;

use CompetitorKnowledge\AI\DTO\AnalysisResult;
use PHPUnit\Framework\TestCase;

class AnalysisResultTest extends TestCase {

	public function test_constructor_accepts_array() {
		$data   = array( 'competitors' => array() );
		$result = new AnalysisResult( $data );

		$this->assertInstanceOf( AnalysisResult::class, $result );
	}

	public function test_get_insights_returns_data() {
		$data = array(
			'competitors' => array(
				array(
					'name'  => 'Competitor A',
					'price' => 99.99,
				),
			),
		);

		$result = new AnalysisResult( $data );

		$this->assertEquals( $data, $result->get_insights() );
	}

	public function test_to_array_returns_insights() {
		$data = array(
			'competitors' => array(
				array(
					'name'  => 'Competitor B',
					'price' => 149.99,
				),
			),
			'summary'     => 'Analysis complete',
		);

		$result = new AnalysisResult( $data );

		$this->assertEquals( $data, $result->to_array() );
		$this->assertEquals( $result->get_insights(), $result->to_array() );
	}

	public function test_handles_empty_array() {
		$result = new AnalysisResult( array() );

		$this->assertEquals( array(), $result->get_insights() );
		$this->assertEquals( array(), $result->to_array() );
	}

	public function test_handles_complex_nested_data() {
		$data = array(
			'competitors' => array(
				array(
					'name'             => 'Competitor C',
					'price'            => 199.99,
					'currency'         => 'USD',
					'stock_status'     => 'in_stock',
					'comparison_notes' => 'Better specs',
					'features'         => array(
						'feature1',
						'feature2',
					),
				),
			),
			'metadata'    => array(
				'analyzed_at' => '2026-01-26',
				'confidence'  => 0.95,
			),
		);

		$result = new AnalysisResult( $data );

		$this->assertEquals( $data, $result->get_insights() );
		$this->assertIsArray( $result->get_insights()['competitors'] );
		$this->assertCount( 1, $result->get_insights()['competitors'] );
	}
}
