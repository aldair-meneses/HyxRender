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
	return Plugin::fragments()->register( $name, $args );
}
