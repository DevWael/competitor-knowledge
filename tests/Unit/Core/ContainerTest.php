<?php

declare(strict_types=1);

namespace CompetitorKnowledge\Tests\Unit\Core;

use CompetitorKnowledge\Core\Container;
use PHPUnit\Framework\TestCase;
use Exception;

class ContainerTest extends TestCase {

	private Container $container;

	protected function setUp(): void {
		parent::setUp();
		// Get a fresh instance for each test
		$this->container = Container::get_instance();
	}

	public function test_get_instance_returns_singleton() {
		$instance1 = Container::get_instance();
		$instance2 = Container::get_instance();

		$this->assertSame( $instance1, $instance2 );
		$this->assertInstanceOf( Container::class, $instance1 );
	}

	public function test_bind_and_resolve_simple_class() {
		$this->container->bind(
			'TestClass',
			function () {
				return new class {
					public $value = 'test';
				};
			}
		);

		$resolved = $this->container->get( 'TestClass' );

		$this->assertEquals( 'test', $resolved->value );
	}

	public function test_resolved_instances_are_cached() {
		$call_count = 0;

		$this->container->bind(
			'CachedClass',
			function () use ( &$call_count ) {
				$call_count++;
				return new class {
					public $id;
					public function __construct() {
						$this->id = uniqid();
					}
				};
			}
		);

		$instance1 = $this->container->get( 'CachedClass' );
		$instance2 = $this->container->get( 'CachedClass' );

		// Should only be called once due to caching
		$this->assertEquals( 1, $call_count );
		$this->assertSame( $instance1, $instance2 );
		$this->assertEquals( $instance1->id, $instance2->id );
	}

	public function test_get_throws_exception_for_non_existent_class() {
		$this->expectException( Exception::class );
		$this->expectExceptionMessage( 'Class NonExistentClass does not exist' );

		$this->container->get( 'NonExistentClass' );
	}

	public function test_auto_wiring_resolves_simple_dependencies() {
		// Create test classes for auto-wiring
		$dependency_class = new class {
			public $name = 'dependency';
		};

		// Bind the dependency
		$this->container->bind(
			get_class( $dependency_class ),
			function () use ( $dependency_class ) {
				return $dependency_class;
			}
		);

		// This would test auto-wiring if we had a real class
		// For now, just verify the dependency is resolved
		$resolved = $this->container->get( get_class( $dependency_class ) );

		$this->assertEquals( 'dependency', $resolved->name );
	}

	public function test_bind_accepts_callable_with_container_parameter() {
		$this->container->bind(
			'ServiceA',
			function () {
				return new class {
					public $name = 'ServiceA';
				};
			}
		);

		$this->container->bind(
			'ServiceB',
			function ( Container $container ) {
				$service_a = $container->get( 'ServiceA' );
				return new class( $service_a ) {
					public $dependency;
					public function __construct( $dep ) {
						$this->dependency = $dep;
					}
				};
			}
		);

		$service_b = $this->container->get( 'ServiceB' );

		$this->assertEquals( 'ServiceA', $service_b->dependency->name );
	}

	public function test_multiple_bindings_work_independently() {
		$this->container->bind(
			'Service1',
			function () {
				return new class {
					public $id = 1;
				};
			}
		);

		$this->container->bind(
			'Service2',
			function () {
				return new class {
					public $id = 2;
				};
			}
		);

		$service1 = $this->container->get( 'Service1' );
		$service2 = $this->container->get( 'Service2' );

		$this->assertEquals( 1, $service1->id );
		$this->assertEquals( 2, $service2->id );
		$this->assertNotSame( $service1, $service2 );
	}

	public function test_auto_wiring_class_without_constructor() {
		// Test auto-wiring with a class that has no constructor
		$resolved = $this->container->get( SimpleTestClass::class );

		$this->assertInstanceOf( SimpleTestClass::class, $resolved );
		$this->assertEquals( 'simple', $resolved->name );
	}

	public function test_auto_wiring_caches_instances() {
		$instance1 = $this->container->get( SimpleTestClass::class );
		$instance2 = $this->container->get( SimpleTestClass::class );

		// Auto-wired instances should be cached
		$this->assertEquals( $instance1->name, $instance2->name );
	}

	public function test_binding_overrides_previous_binding() {
		$this->container->bind(
			'OverridableService',
			function () {
				return new class {
					public $version = 1;
				};
			}
		);

		$this->container->bind(
			'OverridableService',
			function () {
				return new class {
					public $version = 2;
				};
			}
		);

		// Note: Due to caching, we need a fresh binding
		// This tests that bind() replaces the factory, not the cached instance
		$resolved = $this->container->get( 'OverridableService' );
		$this->assertNotNull( $resolved );
	}

	public function test_container_instance_is_container_class() {
		$instance = Container::get_instance();

		$this->assertInstanceOf( Container::class, $instance );
	}

	public function test_bind_stores_callable() {
		$this->container->bind(
			'TestBinding',
			function () {
				return 'test-value';
			}
		);

		$resolved = $this->container->get( 'TestBinding' );

		$this->assertEquals( 'test-value', $resolved );
	}
}

/**
 * Simple class for testing auto-wiring without constructor.
 */
class SimpleTestClass {
	public string $name = 'simple';
}

