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
use Hyx\Render\Fragment\Validator\FragmentRegistrationValidator;
use Hyx\Render\Fragment\Validator\RequestPolicyValidator;
use Hyx\Render\Plugin;

/**
 * Turns a developer's registration into a Fragment the endpoint can serve.
 */
final class FragmentRegistrationService {

	/**
	 * Wires the service to the registered Fragments.
	 *
	 * @param FragmentCollection      $fragments Registered Fragments.
	 * @param FragmentFactory         $factory   Builds Fragments from registrations.
	 * @param TemplateLocationService $locations Keeps template paths inside the allowed folders.
	 */
	public function __construct(
		private readonly FragmentCollection $fragments,
		private readonly FragmentFactory $factory,
		private readonly TemplateLocationService $locations,
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
			( new FragmentRegistrationValidator() )->validate( $name, $args );
			( new RequestPolicyValidator() )->validate( $name, $args );
			$this->ensure_name_is_free( $name );
			$this->ensure_template_is_allowed( $name, $args );
		} catch ( FragmentRegistrationException $exception ) {
			_doing_it_wrong( 'hyx_render_register_fragment', esc_html( $exception->getMessage() ), esc_html( Plugin::VERSION ) );

			return false;
		}

		$this->fragments->add( $this->factory->from_registration( $name, $args ) );

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

	/**
	 * Refuses a template outside the allowed folders.
	 *
	 * @param string               $name Fragment name.
	 * @param array<string, mixed> $args Registration arguments.
	 * @throws FragmentRegistrationException When the template is not allowed.
	 */
	private function ensure_template_is_allowed( string $name, array $args ): void {
		$template = $args['template'] ?? null;

		if ( null !== $template && ! $this->locations->is_allowed( (string) $template ) ) {
			throw new FragmentRegistrationException( esc_html( RegistrationError::TEMPLATE_OUTSIDE_FOLDERS->message( $name ) ) );
		}
	}
}
