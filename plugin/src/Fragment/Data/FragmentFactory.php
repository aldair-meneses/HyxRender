<?php
/**
 * Builds Fragments from registrations.
 *
 * @package HyxRender
 */

declare( strict_types=1 );

namespace Hyx\Render\Fragment\Data;

use Closure;
use Hyx\Render\Fragment\Enum\RegistrationError;
use Hyx\Render\Fragment\Exception\FragmentRegistrationException;
use Hyx\Render\Fragment\View\FragmentTemplateView;

/**
 * Turns the arguments of a `hyx_render_register_fragment()` call into a Fragment,
 * reading each option exactly once and refusing any it can't use.
 */
final class FragmentFactory {

	/**
	 * Wires the factory to template rendering and the policy factories.
	 *
	 * @param FragmentTemplateView $templates Renders template files and their theme overrides.
	 * @param RequestPolicyFactory $requests  Reads the request-related options.
	 * @param CachePolicyFactory   $caches    Reads the cache option.
	 */
	public function __construct(
		private readonly FragmentTemplateView $templates,
		private readonly RequestPolicyFactory $requests,
		private readonly CachePolicyFactory $caches,
	) {
	}

	/**
	 * Builds a Fragment, defaulting to GET only, no arguments and no caching.
	 *
	 * @param string               $name Fragment name.
	 * @param array<string, mixed> $args Registration arguments.
	 * @throws FragmentRegistrationException When the name or an option can't be used.
	 */
	public function create_from_registration( string $name, array $args ): Fragment {
		if ( 1 !== preg_match( '#^[a-z0-9-]+/[a-z0-9-]+$#', $name ) ) {
			throw new FragmentRegistrationException( esc_html( RegistrationError::MALFORMED_NAME->message( $name ) ) );
		}

		return new Fragment(
			$name,
			(string) ( $args['label'] ?? '' ),
			$this->renderer( $name, $args['render'] ?? null, $args['template'] ?? null ),
			$this->requests->create_from_registration( $name, $args['permission_callback'] ?? null, $args['methods'] ?? null, $args['args'] ?? null ),
			$this->caches->create_from_registration( $name, $args['cache'] ?? null ),
		);
	}

	/**
	 * The callable that turns arguments into the Fragment's HTML: the `render` callable,
	 * or the `template` file, located on each render so the active theme's override applies.
	 *
	 * @param string $name     Fragment name.
	 * @param mixed  $render   The `render` option.
	 * @param mixed  $template The `template` option.
	 * @throws FragmentRegistrationException When there isn't exactly one usable renderer.
	 */
	private function renderer( string $name, mixed $render, mixed $template ): Closure {
		if ( null === $template && is_callable( $render ) ) {
			return Closure::fromCallable( $render );
		}

		if ( null !== $render || ! is_string( $template ) ) {
			throw new FragmentRegistrationException( esc_html( RegistrationError::AMBIGUOUS_RENDERER->message( $name ) ) );
		}

		if ( ! $this->templates->can_render( $template ) ) {
			throw new FragmentRegistrationException( esc_html( RegistrationError::TEMPLATE_OUTSIDE_FOLDERS->message( $name ) ) );
		}

		return fn( array $arguments ): string => $this->templates->render( $name, $template, $arguments );
	}
}
