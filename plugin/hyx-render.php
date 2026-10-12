<?php
/**
 * Plugin Name: Hyx Render
 * Description: Starter scaffold for HyxRender plugin development.
 * Version: 0.1.0
 * Requires at least: 6.0
 * Requires PHP: 8.1
 * Author: You
 * License: GPL-2.0-or-later
 *
 * @package HyxRender
 */

declare( strict_types=1 );

use Hyx\Render\Plugin;

defined( 'ABSPATH' ) || exit;

define( 'HYX_RENDER_VERSION', Plugin::VERSION );
define( 'HYX_RENDER_FILE', __FILE__ );
define( 'HYX_RENDER_DIR', __DIR__ );

register_activation_hook( __FILE__, array( Plugin::class, 'activate' ) );
register_deactivation_hook( __FILE__, array( Plugin::class, 'deactivate' ) );

add_action( 'plugins_loaded', array( Plugin::class, 'boot' ) );
