<?php
/**
 * Fragment rendering.
 *
 * @package HyxRender
 */

declare( strict_types=1 );

namespace Hyx\Render\Fragment\Service;

use Hyx\Render\Fragment\Data\Fragment;
use Hyx\Render\Fragment\Enum\RenderError;
use Hyx\Render\Fragment\Exception\FragmentRenderException;

/**
 * Runs a Fragment's render callable and makes sure HTML comes out.
 */
final class FragmentRenderService {

	/**
	 * The Fragment's HTML: what the callable echoes, followed by the string it returns.
	 *
	 * Output is buffered so nothing is sent before the response headers.
	 *
	 * @param Fragment             $fragment  Fragment to render.
	 * @param array<string, mixed> $arguments Sanitized arguments.
	 * @throws FragmentRenderException When the callable returns something other than a string or nothing.
	 */
	public function render( Fragment $fragment, array $arguments ): string {
		ob_start();

		try {
			$result = ( $fragment->render )( $arguments );
		} finally {
			$echoed = (string) ob_get_clean();
		}

		if ( null !== $result && ! is_string( $result ) ) {
			throw new FragmentRenderException( esc_html( RenderError::NOT_HTML->message( $fragment->name ) ) );
		}

		return $echoed . $result;
	}
}
