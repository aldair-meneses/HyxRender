<?php
/**
 * Where Fragment templates may live.
 *
 * @package HyxRender
 */

declare( strict_types=1 );

namespace Hyx\Render\Fragment\Service;

/**
 * Keeps Fragment template paths inside the plugins, mu-plugins and theme folders.
 */
final class TemplateLocationService {

	/**
	 * The template to render: the theme's `hyx-render/{vendor}/{name}.php` override, or the plugin's file.
	 *
	 * An override that resolves outside the allowed folders is ignored.
	 *
	 * @param string $name            Fragment name.
	 * @param string $plugin_template The template file the Fragment registered.
	 */
	public function locate( string $name, string $plugin_template ): string {
		$override = locate_template( 'hyx-render/' . $name . '.php' );

		return '' !== $override && $this->is_allowed( $override ) ? $override : $plugin_template;
	}

	/**
	 * Whether a template path is an existing file inside an allowed folder.
	 *
	 * Both sides are compared after `realpath()`, so `..` segments and symlinks
	 * pointing elsewhere are caught. Symlinked plugins WordPress knows about stay
	 * allowed through their registered real paths.
	 *
	 * @param string $path Template path.
	 */
	public function is_allowed( string $path ): bool {
		$real_path = realpath( $path );

		if ( false === $real_path || ! is_file( $real_path ) ) {
			return false;
		}

		foreach ( $this->allowed_folders() as $folder ) {
			if ( str_starts_with( $real_path, $folder . DIRECTORY_SEPARATOR ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Real paths of the folders templates may come from.
	 *
	 * @return list<string>
	 */
	private function allowed_folders(): array {
		global $wp_theme_directories, $wp_plugin_paths;

		$folders = array_merge(
			array( WP_PLUGIN_DIR, WPMU_PLUGIN_DIR ),
			(array) $wp_theme_directories,
			array_values( (array) $wp_plugin_paths )
		);

		return array_values( array_filter( array_map( 'realpath', $folders ) ) );
	}
}
