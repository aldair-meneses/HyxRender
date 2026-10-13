<?php
/**
 * Fragment template rendering.
 *
 * @package HyxRender
 */

declare( strict_types=1 );

namespace Hyx\Render\Fragment\View;

use Hyx\Render\Fragment\Service\TemplateLocationService;

/**
 * Renders a Fragment's PHP template, or the active theme's override of it, from inside the allowed folders.
 */
final class FragmentTemplateView {

	/**
	 * Wires the view to template lookup.
	 *
	 * @param TemplateLocationService $locations Finds overrides and keeps paths inside the allowed folders.
	 */
	public function __construct( private readonly TemplateLocationService $locations ) {
	}

	/**
	 * Whether a plugin template can be rendered: an existing file inside the allowed folders.
	 *
	 * @param string $plugin_template The template file a Fragment registers.
	 */
	public function can_render( string $plugin_template ): bool {
		return $this->locations->is_allowed( $plugin_template );
	}

	/**
	 * Renders the theme's `hyx-render/{vendor}/{name}.php` override when there is a usable one,
	 * and the plugin's template otherwise. The template sees only `$args`.
	 *
	 * @param string               $name            Fragment name.
	 * @param string               $plugin_template The template file the Fragment registered.
	 * @param array<string, mixed> $args            Sanitized Fragment arguments.
	 * @return string The rendered HTML.
	 */
	public function render( string $name, string $plugin_template, array $args ): string {
		ob_start();
		( static function ( string $hyx_render_template, array $args ): void { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- The included template reads $args.
			include $hyx_render_template;
		} )( $this->locations->locate( $name, $plugin_template ), $args );

		return (string) ob_get_clean();
	}
}
