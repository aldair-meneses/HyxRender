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
	 * Wires the cache to rendering.
	 *
	 * @param FragmentRenderService $renderer Renders a Fragment when nothing is cached.
	 */
	public function __construct( private readonly FragmentRenderService $renderer ) {
	}

	/**
	 * The cached HTML for these arguments, rendering and storing it when missing.
	 *
	 * @param Fragment             $fragment  Fragment to render.
	 * @param array<string, mixed> $arguments Sanitized arguments.
	 * @throws \Hyx\Render\Fragment\Exception\FragmentRenderException When the render callable gives back something other than HTML.
	 */
	public function remember( Fragment $fragment, array $arguments ): string {
		if ( $fragment->cache->ttl <= 0 ) {
			return $this->renderer->render( $fragment, $arguments );
		}

		$key  = $this->key( $fragment, $arguments );
		$html = get_transient( $key );

		if ( is_string( $html ) ) {
			return $html;
		}

		$html = $this->renderer->render( $fragment, $arguments );
		set_transient( $key, $html, $fragment->cache->ttl );

		return $html;
	}

	/**
	 * The transient key for a render: the Fragment, its arguments, the locale, the active theme
	 * (whose template override may apply) and, when it varies by user, the user.
	 *
	 * @param Fragment             $fragment  Fragment to render.
	 * @param array<string, mixed> $arguments Sanitized arguments.
	 */
	private function key( Fragment $fragment, array $arguments ): string {
		$user = $fragment->cache->vary_by_user ? get_current_user_id() : null;

		return 'hyx_render_fragment_' . md5( serialize( array( $fragment->name, $arguments, $user, determine_locale(), get_stylesheet() ) ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize -- Only hashed, never unserialized. JSON fails on INF and rewrites invalid UTF-8, which made keys collide.
	}
}
