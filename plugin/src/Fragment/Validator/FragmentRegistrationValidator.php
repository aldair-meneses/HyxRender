<?php
/**
 * The shape of a Fragment registration.
 *
 * @package HyxRender
 */

declare( strict_types=1 );

namespace Hyx\Render\Fragment\Validator;

use Hyx\Render\Fragment\Enum\RegistrationError;
use Hyx\Render\Fragment\Exception\FragmentRegistrationException;

/**
 * Checks a registration's name, permission callback and renderer.
 */
final class FragmentRegistrationValidator {

	/**
	 * Refuses a registration that breaks a rule.
	 *
	 * @param string               $name Fragment name.
	 * @param array<string, mixed> $args Registration arguments.
	 * @throws FragmentRegistrationException When a rule is broken.
	 */
	public function validate( string $name, array $args ): void {
		if ( 1 !== preg_match( '#^[a-z0-9-]+/[a-z0-9-]+$#', $name ) ) {
			throw new FragmentRegistrationException( esc_html( RegistrationError::MALFORMED_NAME->message( $name ) ) );
		}

		if ( ! is_callable( $args['permission_callback'] ?? null ) ) {
			throw new FragmentRegistrationException( esc_html( RegistrationError::MISSING_PERMISSION_CALLBACK->message( $name ) ) );
		}

		if ( ! $this->has_one_renderer( $args ) ) {
			throw new FragmentRegistrationException( esc_html( RegistrationError::AMBIGUOUS_RENDERER->message( $name ) ) );
		}
	}

	/**
	 * Whether the registration names exactly one way to render: a callable or a template.
	 *
	 * @param array<string, mixed> $args Registration arguments.
	 */
	private function has_one_renderer( array $args ): bool {
		$render   = $args['render'] ?? null;
		$template = $args['template'] ?? null;

		if ( null !== $render ) {
			return null === $template && is_callable( $render );
		}

		return null !== $template;
	}
}
