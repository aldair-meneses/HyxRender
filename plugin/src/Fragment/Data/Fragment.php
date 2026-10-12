<?php
/**
 * A registered Fragment.
 *
 * @package HyxRender
 */

declare( strict_types=1 );

namespace Hyx\Render\Fragment\Data;

use Closure;

/**
 * An endpoint registered by a developer that returns HTML directly.
 */
final class Fragment {

	/**
	 * Builds a Fragment.
	 *
	 * @param string        $name    Name in `vendor/name` form.
	 * @param string        $label   Human-readable label.
	 * @param Closure       $render  Returns the Fragment's HTML from its arguments.
	 * @param RequestPolicy $request What a request has to satisfy before the Fragment renders.
	 * @param CachePolicy   $cache   How long a render may be reused.
	 */
	public function __construct(
		public readonly string $name,
		public readonly string $label,
		public readonly Closure $render,
		public readonly RequestPolicy $request,
		public readonly CachePolicy $cache,
	) {
	}
}
