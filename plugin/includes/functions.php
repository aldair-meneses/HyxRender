<?php
/**
 * Public PHP API.
 *
 * @package HyxRender
 */

declare( strict_types=1 );

use Hyx\Render\Plugin;

defined( 'ABSPATH' ) || exit;

/**
 * Registers a Fragment, an endpoint that returns HTML for htmx.
 *
 * Call it during the `hyx_render_init` action.
 *
 * @param string               $name Name in `vendor/name` form.
 * @param array<string, mixed> $args Registration arguments.
 * @return bool Whether the Fragment was registered.
 */
function hyx_render_register_fragment( string $name, array $args ): bool {
	if ( ! did_action( 'hyx_render_init' ) ) {
		// Left untranslated: before `after_setup_theme`, loading translations triggers its own notice.
		_doing_it_wrong( __FUNCTION__, 'Register Fragments during the hyx_render_init action.', esc_html( Plugin::VERSION ) );

		return false;
	}

	return Plugin::fragments()->register( $name, $args );
}
