<?php
/**
 * How long a Fragment's HTML may be reused.
 *
 * @package HyxRender
 */

declare( strict_types=1 );

namespace Hyx\Render\Fragment\Data;

/**
 * A Fragment's cache settings.
 */
final class CachePolicy {

	/**
	 * Builds a cache policy.
	 *
	 * @param int  $ttl          Seconds a render may be reused.
	 * @param bool $vary_by_user Whether each user gets their own cached render.
	 */
	public function __construct(
		public readonly int $ttl,
		public readonly bool $vary_by_user,
	) {
	}
}
