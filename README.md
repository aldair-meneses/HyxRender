# HyxRender WP dev stack (Bedrock-style + Docker)

WordPress core and public plugins are Composer-managed. Your plugin stays in
`plugin/` and is symlinked into WordPress on `composer install`.

## Layout

- `composer.json`: site package. `roots/wordpress` core, wpackagist repo,
  path repo (`plugin/`, symlinked). Free Elementor is a dev dependency of the
  site only, so the plugin can be developed and tested against it.
- `plugin/`: your plugin under development (has its own `composer.json`,
  tracked in git). Live at `web/app/plugins/hyx-render` via symlink.
  - `plugin/src/`: PHP code (PSR-4, `Hyx\Render\`).
  - `plugin/tests/`: PHPUnit integration tests.
  - `plugin/assets/src/`: editor and front-end runtime JS, built into
    `plugin/build/` (gitignored) by `plugin/package.json`.
- `config/application.php` + `config/environments/`: WP config (Roots wp-config)
- `web/`: docroot. `web/wp` (core), `web/app/plugins|themes` (composer-built,
  gitignored). `web/index.php`, `web/wp-config.php` are tracked stubs.
- `Dockerfile`, `docker/`: php:8.3-apache with Xdebug, Composer, WP-CLI.
  `docker/phpunit/` holds the WordPress test suite config.
- `phpunit.xml.dist`, `phpcs.xml`, `psalm.xml`, `mago.toml`: quality gate configs.
- `.env`: shared by Docker Compose and WordPress (phpdotenv). Gitignored.

## Setup

```bash
cp .env.example .env   # change passwords, salts are generated per install
composer install       # runs in Docker via bin/composer shim (needs direnv, see below)
docker compose up -d --build
```

WP-CLI runs inside the container (Bedrock paths are preconfigured in `wp-cli.yml`).
direnv puts `bin/` on PATH, so bare `wp` goes through the `bin/wp` shim into
the container. Needs a shell with the direnv hook (added to `~/.zshrc`) and
`direnv allow` (already done for this repo):

```bash
wp plugin list
wp plugin activate hyx-render
```

Without direnv, or outside the project dir:

```bash
docker compose exec wordpress wp --allow-root plugin list
```

```bash
docker compose exec wordpress wp --allow-root core install \
  --url=http://localhost:8080 --title='HyxRender Dev' \
  --admin_user=admin --admin_password=admin --admin_email=admin@example.com --skip-email
docker compose exec wordpress wp --allow-root plugin activate hyx-render
```

Site: `http://localhost:8080`, admin: `http://localhost:8080/wp/wp-admin/`.

## Tooling: everything in Docker

Host tools are not required except Docker and direnv. direnv puts `bin/` on
PATH, so bare `composer`, `npm` and `wp` go through shims into the containers:

- `bin/composer` → `docker compose --profile tools run --rm composer`.
  Runs as your UID/GID (exported by `.envrc`), so installed files stay owned
  by you. Xdebug is forced off for it. It waits for the database, which the
  test suite needs.
- `bin/npm` → `docker compose --profile tools run --rm node`, a Node 22
  container working in `plugin/`. Also runs as your UID/GID.
- `bin/wp` → container WP-CLI, documented below.

Needs a shell with the direnv hook (added to `~/.zshrc`) and `direnv allow`
(already done for this repo). Without direnv, call the
`docker compose ...` forms directly.

## Workflows

Add a public plugin (normal install dir, committed to `composer.lock`):

```bash
composer require wpackagist-plugin/contact-form-7
docker compose exec wordpress wp --allow-root plugin activate contact-form-7
```

Develop your plugin: just edit `plugin/`. The symlink means changes are live,
no reinstall. If you add a dependency to `plugin/composer.json`, run
`composer update hyx/hyx-render` at the root so the lockfile picks it up.

## Xdebug

Preconfigured: `host.docker.internal:9003`, `start_with_request=yes`.

1. Install the VS Code `PHP Debug` extension.
2. Run `Listen for Xdebug (Docker)` from `.vscode/launch.json`.
3. Breakpoint in `plugin/hyx-render.php` in `hyx_render_boot()`, reload a page.

Full speed when not debugging: `XDEBUG_MODE=off` in `.env`, then
`docker compose up -d`. The "Could not connect" log line just means no IDE
listener is attached.

## Tests

`composer test` runs the PHPUnit 9.6 integration suite (wp-phpunit and
yoast/wp-test-utils) in the Composer container, against its own database:

```bash
composer test
```

- The test database is `WP_TESTS_DB_NAME` from `.env` (default
  `wordpress_test`). Each run creates it if needed and grants `DB_USER` access,
  using `MYSQL_ROOT_PASSWORD`, so no manual setup is needed.
- WordPress installs into that database fresh on every run. The site database
  is never touched.
- Tests live in `plugin/tests/Integration/` (`*Test.php`, namespace
  `Hyx\Render\Tests\Integration`) and extend
  `Yoast\WPTestUtils\WPIntegration\TestCase`.

## Editor and runtime assets

```bash
npm ci          # once, installs @wordpress/scripts into plugin/node_modules
npm run build   # plugin/assets/src/{editor,runtime} → plugin/build/
npm run start   # rebuild on change
npm run size    # check the gzipped size budgets
```

## Quality gates

All checks run in Docker, no host PHP or Node tools needed. The plugin's floor
is PHP 8.1 and WordPress 6.9, and the tools enforce it:

- `composer lint`: PHPCS with WordPress Coding Standards and PHPCompatibility
  (`testVersion` 8.1) on `plugin/`
- `composer lint:fix`: auto-fix via phpcbf
- `composer lint:mago`: Mago linter, `php-version` 8.1 (rejects newer syntax)
- `composer analyze`: Psalm level 4 with the WordPress plugin and taint
  analysis (offline SAST, no API), targeting PHP 8.1. Tests are excluded.
- `composer test`: WordPress integration tests (see below)
- `composer audit`: known CVEs in dependencies (needs network, no key)
- `composer check`: all of the above, in order

Asset size budgets: `npm run build && npm run size` fails when the front-end
runtime (`build/runtime.js`) passes 2 KB gzipped or the editor bundle
(`build/editor.js`) passes 30 KB gzipped.

Push guardrail: `.githooks/pre-push` rebuilds images, runs `composer check`,
then builds the assets and checks the size budgets, all in Docker. Fails
closed: no Docker, no green gate, no push. Enable once per clone:

```bash
git config core.hooksPath .githooks
```

Deliberately not gated: Mago `format` (its style conflicts with WPCS spacing,
e.g. `if ( true )`) and Mago `analyze` (no WordPress stubs; Psalm with the
humanmade WP plugin covers analysis instead).

Mago is scoped to `plugin/` via `mago.toml`, so the composer-managed theme,
core and vendor code is never touched. Its `array-style` rule is set to
`long` to match WPCS.
