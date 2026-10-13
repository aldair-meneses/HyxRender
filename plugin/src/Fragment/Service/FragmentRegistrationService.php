<?php
/**
 * Fragment registration.
 *
 * @package HyxRender
 */

declare( strict_types=1 );

namespace Hyx\Render\Fragment\Service;

use Hyx\Render\Fragment\Data\FragmentCollection;
use Hyx\Render\Fragment\Data\FragmentFactory;
use Hyx\Render\Fragment\Enum\RegistrationError;
use Hyx\Render\Fragment\Exception\FragmentRegistrationException;
use Hyx\Render\Plugin;

/**
 * Turns a developer's registration into a Fragment the endpoint can serve.
 */
final class FragmentRegistrationService {

	/**
	 * Wires the service to the registered Fragments.
	 *
	 * @param FragmentCollection $fragments Registered Fragments.
	 * @param FragmentFactory    $factory   Builds Fragments from registrations.
	 */
	public function __construct(
		private readonly FragmentCollection $fragments,
		private readonly FragmentFactory $factory,
	) {
	}

	/**
	 * Registers a Fragment, or tells the developer why it can't.
	 *
	 * @param string               $name Name in `vendor/name` form.
	 * @param array<string, mixed> $args Registration arguments.
	 * @return bool Whether the Fragment was registered.
	 */
	public function register( string $name, array $args ): bool {
		try {
			$fragment = $this->factory->create_from_registration( $name, $args );
			$this->ensure_name_is_free( $name );
		} catch ( FragmentRegistrationException $exception ) {
			_doing_it_wrong( 'hyx_render_register_fragment', esc_html( $exception->getMessage() ), esc_html( Plugin::VERSION ) );

			return false;
		}

		$this->fragments->add( $fragment );

		return true;
	}

	/**
	 * Refuses a name another Fragment already has.
	 *
	 * @param string $name Fragment name.
	 * @throws FragmentRegistrationException When the name is taken.
	 */
	private function ensure_name_is_free( string $name ): void {
		if ( null !== $this->fragments->find( $name ) ) {
			throw new FragmentRegistrationException( esc_html( RegistrationError::DUPLICATE_NAME->message( $name ) ) );
		}
	}
}
