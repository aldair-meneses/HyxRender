<?php
/**
 * Fixture: a theme's override of the acme/themed-greeting Fragment template.
 *
 * @package HyxRender
 *
 * @var array<string, mixed> $args Sanitized Fragment arguments.
 */

declare( strict_types=1 );

?>
<p>Theme template greets <?php echo esc_html( $args['name'] ); ?></p>
