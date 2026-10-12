<?php
/**
 * WordPress test suite configuration for the Docker dev stack.
 *
 * Loaded by wp-phpunit through WP_PHPUNIT__TESTS_CONFIG, both by the PHPUnit
 * bootstrap and by the separate PHP process that installs the test database,
 * so it loads the Composer autoloader and .env itself.
 */

declare(strict_types=1);

$hyx_root = dirname(__DIR__, 2);

require_once $hyx_root . '/vendor/autoload.php';

$hyx_env = Dotenv\Dotenv::createImmutable($hyx_root)->safeLoad();

define('ABSPATH', $hyx_root . '/web/wp/');
define('WP_CONTENT_DIR', $hyx_root . '/web/app');
define('WP_PLUGIN_DIR', WP_CONTENT_DIR . '/plugins');
define('WP_DEFAULT_THEME', 'twentytwentyfive');
define('WP_DEBUG', true);

define('DB_NAME', $hyx_env['WP_TESTS_DB_NAME'] ?? 'wordpress_test');
define('DB_USER', $hyx_env['DB_USER'] ?? '');
define('DB_PASSWORD', $hyx_env['DB_PASSWORD'] ?? '');
define('DB_HOST', $hyx_env['DB_HOST'] ?? 'db');
define('DB_CHARSET', 'utf8mb4');
define('DB_COLLATE', '');

define('WP_TESTS_DOMAIN', 'example.org');
define('WP_TESTS_EMAIL', 'admin@example.org');
define('WP_TESTS_TITLE', 'HyxRender Tests');
define('WP_PHP_BINARY', 'php');

$table_prefix = 'wptests_';
