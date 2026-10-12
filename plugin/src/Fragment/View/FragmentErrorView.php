<?php
/**
 * Fragment error markup.
 *
 * @package HyxRender
 */

declare( strict_types=1 );

namespace Hyx\Render\Fragment\View;

/**
 * Renders the error paragraph htmx swaps into the page.
 */
final class FragmentErrorView {

	/**
	 * Renders an error message as escaped HTML.
	 *
	 * @param string $message Plain-text message.
	 */
	public function render( string $message ): string {
		return sprintf( '<p class="hyx-render-error" role="alert">%s</p>', esc_html( $message ) );
	}
}
