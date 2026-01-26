<?php
/**
 * Dependency Injection Container.
 *
 * @package CompetitorKnowledge\Core
 */

declare(strict_types=1);

namespace CompetitorKnowledge\Core;

use Exception;
use ReflectionClass;
use ReflectionException;

/**
 * Class Container
 *
 * Simple Dependency Injection Container.
 *
 * @package CompetitorKnowledge\Core
 */
class Container {

	/**
	 * Shared instance.
	 *
	 * @var Container|null
	 */
	private static ?Container $instance = null;

	/**
	 * Bindings.
	 *
	 * @var array<string, callable>
	 */
	private array $bindings = array();

	/**
	 * Resolved instances.
	 *
	 * @var array<string, object>
	 */
	private array $instances = array();

	/**
	 * Get the shared instance.
	 *
	 * @return Container
	 */
	public static function get_instance(): Container {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Bind a class/interface to a resolver.
	 *
	 * @param string   $abstract Class or interface name.
	 * @param callable $concrete Resolver function.
	 */
	public function bind( string $abstract, callable $concrete ): void {
		$this->bindings[ $abstract ] = $concrete;
	}

	/**
	 * Resolve a class instance.
	 *
	 * @param string $abstract Class name.
	 *
	 * @return mixed
	 * @throws Exception If resolution fails.
	 */
	public function get( string $abstract ) {
		if ( isset( $this->instances[ $abstract ] ) ) {
			return $this->instances[ $abstract ];
		}

		if ( isset( $this->bindings[ $abstract ] ) ) {
			$instance                     = $this->bindings[ $abstract ]( $this );
			$this->instances[ $abstract ] = $instance;
			return $instance;
		}

		// Auto-wiring.
		if ( ! class_exists( $abstract ) ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception messages are not output to browser.
			throw new Exception( "Class {$abstract} does not exist." );
		}

		try {
			$reflector = new ReflectionClass( $abstract );
			if ( ! $reflector->isInstantiable() ) {
				throw new Exception( "Class {$abstract} is not instantiable." );
			}

			$constructor = $reflector->getConstructor();
			if ( null === $constructor ) {
				return new $abstract();
			}

			$dependencies = array();
			foreach ( $constructor->getParameters() as $parameter ) {
				$type = $parameter->getType();
				if ( null === $type || ! $type instanceof \ReflectionNamedType || $type->isBuiltin() ) {
					throw new Exception( "Cannot resolve parameter {$parameter->getName()} in class {$abstract}" );
				}
				$dependencies[] = $this->get( $type->getName() );
			}

			$instance                     = $reflector->newInstanceArgs( $dependencies );
			$this->instances[ $abstract ] = $instance;

			return $instance;
		} catch ( ReflectionException $e ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception messages are not output to browser.
			throw new Exception( 'Reflection error: ' . $e->getMessage() );
		}
	}
}
