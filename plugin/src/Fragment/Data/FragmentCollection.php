<?php
/**
 * The registered Fragments.
 *
 * @package HyxRender
 */

declare( strict_types=1 );

namespace Hyx\Render\Fragment\Data;

/**
 * Registered Fragments, keyed by name.
 */
final class FragmentCollection {

	/**
	 * Fragments by name.
	 *
	 * @var array<string, Fragment>
	 */
	private array $items = array();

	/**
	 * Adds a Fragment.
	 *
	 * @param Fragment $fragment Fragment to add.
	 */
	public function add( Fragment $fragment ): void {
		$this->items[ $fragment->name ] = $fragment;
	}

	/**
	 * Finds a Fragment by name.
	 *
	 * @param string $name Fragment name.
	 */
	public function find( string $name ): ?Fragment {
		return $this->items[ $name ] ?? null;
	}
}
