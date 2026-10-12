<?php
/**
 * A Fragment that couldn't be rendered.
 *
 * @package HyxRender
 */

declare( strict_types=1 );

namespace Hyx\Render\Fragment\Exception;

use UnexpectedValueException;

/**
 * Thrown when a Fragment's render callable gives back something other than HTML.
 */
final class FragmentRenderException extends UnexpectedValueException {
}
