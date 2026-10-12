<?php
/**
 * Builds Fragments from registrations.
 *
 * @package HyxRender
 */

declare( strict_types=1 );

namespace Hyx\Render\Fragment\Data;

use Closure;
use Hyx\Render\Fragment\Service\TemplateLocationService;
use Hyx\Render\Fragment\View\FragmentTemplateView;

/**
 * Turns the arguments of a valid `hyx_render_register_fragment()` call into a Fragment.
 */
final class FragmentFactory {

	/**
	 * Wires the factory to template rendering.
	 *
	 * @param FragmentTemplateView    $templates Renders template files.
	 * @param TemplateLocationService $locations Finds the theme's override of a template.
	 */
	public function __construct(
		private readonly FragmentTemplateView $templates,
		private readonly TemplateLocationService $locations,
	) {
	}

	/**
	 * Builds a Fragment, filling in the defaults: GET only and no caching.
	 *
	 * @param string               $name Fragment name.
	 * @param array<string, mixed> $args Validated registration arguments.
	 */
	public function from_registration( string $name, array $args ): Fragment {
		$cache = (array) ( $args['cache'] ?? array() );

		return new Fragment(
			$name,
			(string) ( $args['label'] ?? '' ),
			$this->renderer( $name, $args ),
			new RequestPolicy(
				(array) ( $args['args'] ?? array() ),
				array_values( array_map( 'strtoupper', (array) ( $args['methods'] ?? array( 'GET' ) ) ) ),
				Closure::fromCallable( $args['permission_callback'] ),
			),
			new CachePolicy( (int) ( $cache['ttl'] ?? 0 ), (bool) ( $cache['vary_by_user'] ?? false ) ),
		);
	}

	/**
	 * The callable that turns arguments into the Fragment's HTML.
	 *
	 * A template is located on each render, so the active theme's override applies.
	 *
	 * @param string               $name Fragment name.
	 * @param array<string, mixed> $args Validated registration arguments.
	 */
	private function renderer( string $name, array $args ): Closure {
		$template = $args['template'] ?? null;

		if ( null === $template ) {
			return Closure::fromCallable( $args['render'] );
		}

		return fn( array $arguments ): string => $this->templates->render( $this->locations->locate( $name, (string) $template ), $arguments );
	}
}
