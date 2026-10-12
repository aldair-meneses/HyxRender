<?php
/**
 * A refused Fragment registration.
 *
 * @package HyxRender
 */

declare( strict_types=1 );

namespace Hyx\Render\Fragment\Exception;

use InvalidArgumentException;

/**
 * Thrown when a `hyx_render_register_fragment()` call breaks a registration rule.
 */
final class FragmentRegistrationException extends InvalidArgumentException {
}
