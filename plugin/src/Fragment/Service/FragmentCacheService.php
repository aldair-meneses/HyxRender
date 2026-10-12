<?php
/**
 * Fragment render caching.
 *
 * @package HyxRender
 */

declare( strict_types=1 );

namespace Hyx\Render\Fragment\Service;

use Hyx\Render\Fragment\Data\Fragment;

/**
 * Reuses a Fragment's rendered HTML for the same arguments.
 */
final class FragmentCacheService {

	/**
	 * The cached HTML for these arguments, rendering and storing it when missing.
	 *
	 * @param Fragment             $fragment  Fragment to render.
	 * @param array<string, mixed> $arguments Sanitized arguments.
	 */
	public function remember( Fragment $fragment, array $arguments ): string {
		if ( $fragment->cache->ttl <= 0 ) {
			return ( $fragment->render )( $arguments );
		}

		$key  = $this->key( $fragment, $arguments );
		$html = get_transient( $key );

		if ( is_string( $html ) ) {
			return $html;
		}

		$html = ( $fragment->render )( $arguments );
		set_transient( $key, $html, $fragment->cache->ttl );

		return $html;
	}

	/**
	 * The transient key for a render: the Fragment, its arguments and, when it varies by user, the user.
	 *
	 * @param Fragment             $fragment  Fragment to render.
	 * @param array<string, mixed> $arguments Sanitized arguments.
	 */
	private function key( Fragment $fragment, array $arguments ): string {
		$user = $fragment->cache->vary_by_user ? get_current_user_id() : null;

		return 'hyx_render_fragment_' . md5( (string) wp_json_encode( array( $fragment->name, $arguments, $user ) ) );
	}
}
