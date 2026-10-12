<?php
/**
 * What a request has to satisfy before a Fragment renders.
 *
 * @package HyxRender
 */

declare( strict_types=1 );

namespace Hyx\Render\Fragment\Data;

use Closure;

/**
 * A Fragment's allowed methods, argument schema and permission check.
 */
final class RequestPolicy {

	/**
	 * Builds a request policy.
	 *
	 * @param array<string, array<string, mixed>> $args                Arguments declared as a REST argument schema.
	 * @param string[]                            $methods             Allowed HTTP methods, uppercase.
	 * @param Closure                             $permission_callback Decides from the arguments whether the request may see the Fragment.
	 */
	public function __construct(
		public readonly array $args,
		public readonly array $methods,
		public readonly Closure $permission_callback,
	) {
	}
}
