<?php
/**
 * Creates the integration test database and grants DB_USER full access to it.
 *
 * Idempotent, so `composer test` runs it every time. It uses the MariaDB root
 * account from .env because the app user cannot create databases.
 */

declare(strict_types=1);

$root = dirname(__DIR__, 2);

require_once $root . '/vendor/autoload.php';

$env = Dotenv\Dotenv::createImmutable($root)->safeLoad();

$name = $env['WP_TESTS_DB_NAME'] ?? 'wordpress_test';
$user = $env['DB_USER'] ?? '';
$host = $env['DB_HOST'] ?? 'db';
$root_password = $env['MYSQL_ROOT_PASSWORD'] ?? '';

if (1 !== preg_match('/^\w+$/', $name) || 1 !== preg_match('/^\w+$/', $user) || '' === $root_password) {
    fwrite(STDERR, "provision-test-db: set WP_TESTS_DB_NAME, DB_USER and MYSQL_ROOT_PASSWORD in .env (letters, digits, underscores).\n");
    exit(1);
}

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$db = new mysqli($host, 'root', $root_password);
$db->query(sprintf('CREATE DATABASE IF NOT EXISTS `%s` CHARACTER SET utf8mb4', $name));
$db->query(sprintf("GRANT ALL PRIVILEGES ON `%s`.* TO '%s'@'%%'", $name, $user));
$db->close();

printf("provision-test-db: %s ready for %s\n", $name, $user);
