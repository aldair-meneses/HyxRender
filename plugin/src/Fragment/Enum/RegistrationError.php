<?php
/**
 * Why a Fragment registration is refused.
 *
 * @package HyxRender
 */

declare( strict_types=1 );

namespace Hyx\Render\Fragment\Enum;

/**
 * The reasons `hyx_render_register_fragment()` refuses a registration.
 */
enum RegistrationError: string {

	case MALFORMED_NAME              = 'malformed_name';
	case DUPLICATE_NAME              = 'duplicate_name';
	case MISSING_PERMISSION_CALLBACK = 'missing_permission_callback';
	case AMBIGUOUS_RENDERER          = 'ambiguous_renderer';
	case TEMPLATE_OUTSIDE_FOLDERS    = 'template_outside_folders';

	/**
	 * The message shown to the developer.
	 *
	 * Each case keeps its own literal `__()` call, so the strings stay extractable for translation.
	 *
	 * @param string $name Fragment name.
	 */
	public function message( string $name ): string {
		return sprintf(
			match ( $this ) {
				/* translators: %s: Fragment name. */
				self::MALFORMED_NAME              => __( 'Fragment names must be in "vendor/name" form, using lowercase letters, digits and hyphens. "%s" is not.', 'hyx-render' ),
				/* translators: %s: Fragment name. */
				self::DUPLICATE_NAME              => __( 'A Fragment named "%s" is already registered.', 'hyx-render' ),
				/* translators: %s: Fragment name. */
				self::MISSING_PERMISSION_CALLBACK => __( 'The "%s" Fragment needs a callable permission_callback. Use "__return_true" for a public Fragment.', 'hyx-render' ),
				/* translators: %s: Fragment name. */
				self::AMBIGUOUS_RENDERER          => __( 'The "%s" Fragment needs either a callable render or a template file, not both.', 'hyx-render' ),
				/* translators: %s: Fragment name. */
				self::TEMPLATE_OUTSIDE_FOLDERS    => __( 'The "%s" Fragment template must be an existing file inside the plugins, mu-plugins or theme folders.', 'hyx-render' ),
			},
			$name
		);
	}
}
