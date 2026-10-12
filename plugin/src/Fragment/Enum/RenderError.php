<?php
/**
 * Why a Fragment can't be rendered.
 *
 * @package HyxRender
 */

declare( strict_types=1 );

namespace Hyx\Render\Fragment\Enum;

/**
 * The ways a Fragment's render callable can break its contract.
 */
enum RenderError: string {

	case NOT_HTML = 'not_html';

	/**
	 * The message shown to the developer.
	 *
	 * @param string $name Fragment name.
	 */
	public function message( string $name ): string {
		return sprintf(
			match ( $this ) {
				/* translators: %s: Fragment name. */
				self::NOT_HTML => __( 'The render callable of the "%s" Fragment must return its HTML as a string, or echo it.', 'hyx-render' ),
			},
			$name
		);
	}
}
