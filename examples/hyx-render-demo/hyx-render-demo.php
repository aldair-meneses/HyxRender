<?php
/**
 * Plugin Name: HyxRender Demo
 * Description: Demo data for HyxRender: a transactions table, its REST API and WP-CLI commands. Development only, never shipped.
 * Version: 0.1.0
 * Requires at least: 6.9
 * Requires PHP: 8.1
 * License: GPL-2.0-or-later
 * Text Domain: hyx-render-demo
 *
 * @package HyxRenderDemo
 */

declare( strict_types=1 );

use Hyx\RenderDemo\Plugin;

defined( 'ABSPATH' ) || exit;

register_activation_hook( __FILE__, array( Plugin::class, 'activate' ) );

add_action( 'plugins_loaded', array( Plugin::class, 'boot' ) );
