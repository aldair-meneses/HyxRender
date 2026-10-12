<?php
/**
 * Fragment template rendering.
 *
 * @package HyxRender
 */

declare( strict_types=1 );

namespace Hyx\Render\Fragment\View;

/**
 * Renders a Fragment's PHP template file.
 */
final class FragmentTemplateView {

	/**
	 * Renders a template file, which sees only `$args`.
	 *
	 * @param string               $template Template file path.
	 * @param array<string, mixed> $args     Sanitized Fragment arguments.
	 * @return string The rendered HTML.
	 */
	public function render( string $template, array $args ): string {
		ob_start();
		( static function ( string $hyx_render_template, array $args ): void { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- The included template reads $args.
			include $hyx_render_template;
		} )( $template, $args );

		return (string) ob_get_clean();
	}
}
