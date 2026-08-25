<?php
/**
 * Resolves the weight bundles available inside a pack.
 *
 * A "bundle" is a simple product or a variation of a variable product that:
 *  - has a valid weight (converted to grams)
 *  - is in stock (when stock management is enabled)
 *  - belongs to the pack's source category and is not excluded
 */

defined( 'ABSPATH' ) || exit;

class WBP_Items {

	/**
	 * Per-request cache (pack_id => bundles).
	 *
	 * @var array
	 */
	private static $cache = array();

	public static function init() {}

	/* ---------------------------------------------------------------------
	 * Weight unit conversion
	 * ------------------------------------------------------------------- */

	/**
	 * Convert a weight from the store unit to whole grams.
	 */
	public static function to_grams( $weight, $unit = null ) {
		$unit   = $unit ? $unit : get_option( 'woocommerce_weight_unit', 'kg' );
		$weight = (float) $weight;
		switch ( $unit ) {
			case 'kg':
				return (int) round( $weight * 1000 );
			case 'lbs':
				return (int) round( $weight * 453.59237 );
			case 'oz':
				return (int) round( $weight * 28.349523125 );
			case 'g':
			default:
				return (int) round( $weight );
		}
	}

	/**
	 * Convert grams to the store weight unit.
	 */
	public static function from_grams( $grams, $unit = null ) {
		$unit   = $unit ? $unit : get_option( 'woocommerce_weight_unit', 'kg' );
		$grams = (float) $grams;
		switch ( $unit ) {
			case 'kg':
				return $grams / 1000;
			case 'lbs':
				return $grams / 453.59237;
			case 'oz':
				return $grams / 28.349523125;
			case 'g':
			default:
				return $grams;
		}
	}

	/**
	 * Human readable weight label, e.g. "200 g" or "1.5 kg".
	 */
	public static function weight_label( $grams ) {
		$grams = (int) $grams;
		if ( $grams >= 1000 ) {
			$kg    = $grams / 1000;
			$label = number_format_i18n( $kg, ( fmod( $kg, 1 ) !== 0.0 ) ? wc_get_price_decimals() : 0 );
			/* translators: %s: weight in kilograms */
			return sprintf( __( '%s kg', 'weight-based-product-packs' ), $label );
		}
		/* translators: %s: weight in grams */
		return sprintf( __( '%s g', 'weight-based-product-packs' ), number_format_i18n( $grams ) );
	}

	/* ---------------------------------------------------------------------
	 * Resolution
	 * ------------------------------------------------------------------- */

	/**
	 * List the bundles available for a pack.
	 *
	 * Returns an array keyed by product/variation ID; each value describes one bundle:
	 * id, parent_id, name, weight_g, price, price_html, stock (null = unlimited), image_url, permalink.
	 *
	 * @param WBP_Product_Pack|int $pack
	 * @return array
	 */
	public static function get_for_pack( $pack ) {
		if ( is_numeric( $pack ) ) {
			$pack_id = absint( $pack );
		} elseif ( $pack instanceof WC_Product ) {
			$pack_id = $pack->get_id();
		} else {
			return array();
		}

		if ( isset( self::$cache[ $pack_id ] ) ) {
			return self::$cache[ $pack_id ];
		}

		$bundles = array();

		$pack_product = wc_get_product( $pack_id );
		if ( ! $pack_product || 'pack' !== $pack_product->get_type() ) {
			self::$cache[ $pack_id ] = $bundles;
			return $bundles;
		}

		$term_id = $pack_product->get_source_cat();
		if ( ! $term_id || ! term_exists( $term_id, 'product_cat' ) ) {
			self::$cache[ $pack_id ] = $bundles;
			return $bundles;
		}

		$excluded = $pack_product->get_exclude_ids();

		$query = new WP_Query(
			array(
				'post_type'      => 'product',
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'no_found_rows'  => true,
				'tax_query'      => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- category lookup is the standard way to source pack items.
					array(
						'taxonomy' => 'product_cat',
						'field'    => 'term_id',
						'terms'    => $term_id,
					),
				),
			)
		);

		foreach ( $query->posts as $pid ) {
			// Exclusions are filtered in the loop to keep the query cache-friendly.
			if ( in_array( (int) $pid, $excluded, true ) ) {
				continue;
			}

			$product = wc_get_product( $pid );
			if ( ! $product ) {
				continue;
			}

			$type = $product->get_type();
			if ( 'simple' === $type ) {
				$b = self::make_bundle( $product, $product );
				if ( $b ) {
					$bundles[ $b['id'] ] = $b;
				}
			} elseif ( 'variable' === $type ) {
				foreach ( $product->get_children() as $vid ) {
					$variation = wc_get_product( $vid );
					if ( ! $variation || ! $variation->exists() ) {
						continue;
					}
					if ( 'publish' !== $variation->get_status() ) {
						continue;
					}
					$b = self::make_bundle( $product, $variation );
					if ( $b ) {
						$bundles[ $b['id'] ] = $b;
					}
				}
			}
		}

		uasort(
			$bundles,
			function ( $a, $b ) {
				return strcmp( remove_accents( $a['name'] ), remove_accents( $b['name'] ) );
			}
		);

		self::$cache[ $pack_id ] = $bundles;
		return $bundles;
	}

	/**
	 * Build the bundle descriptor for a product/variation.
	 *
	 * @param WC_Product $parent Parent product (name and fallback image come from the parent for variations).
	 * @param WC_Product $item   Simple product or variation.
	 * @return array|null
	 */
	private static function make_bundle( WC_Product $parent, WC_Product $item ) {
		// A bundle must have a valid weight.
		$weight_g = self::to_grams( $item->get_weight() );
		if ( $weight_g <= 0 ) {
			return null;
		}

		// Stock.
		if ( ! $item->is_in_stock() ) {
			return null;
		}
		$stock = $item->managing_stock() ? (int) $item->get_stock_quantity() : null; // null = unlimited
		if ( null !== $stock && $stock <= 0 ) {
			return null;
		}

		$price = (float) $item->get_price();
		if ( $price < 0 ) {
			return null;
		}

		// Name: variations append their attribute values to the parent name.
		$name       = $parent->get_name();
		$attr_label = '';
		if ( $item->get_id() !== $parent->get_id() ) {
			$attr_label = wc_get_formatted_variation( $item, true, false, false );
			$name       = $attr_label ? $name . ' — ' . $attr_label : $name;
		}

		$image_id = $item->get_image_id() ? $item->get_image_id() : $parent->get_image_id();
		$parent_image_id = $parent->get_image_id();

		return array(
			'id'             => $item->get_id(),
			'parent_id'      => $parent->get_id(),
			'name'           => $name,
			'attr_label'     => $attr_label,
			'parent_name'    => $parent->get_name(),
			'parent_image'   => $parent_image_id ? wp_get_attachment_image_url( $parent_image_id, 'woocommerce_thumbnail' ) : wc_placeholder_img_src( 'woocommerce_thumbnail' ),
			'weight_g'       => $weight_g,
			'price'          => $price,
			'price_html'     => wc_price( $price ),
			'stock'          => $stock,
			'image_url'      => $image_id ? wp_get_attachment_image_url( $image_id, 'woocommerce_thumbnail' ) : wc_placeholder_img_src( 'woocommerce_thumbnail' ),
			'permalink'      => $parent->get_permalink(),
		);
	}

	/**
	 * Configuration problems for a pack (shown to the shop manager).
	 *
	 * @param WBP_Product_Pack $pack
	 * @return array
	 */
	public static function config_problems( $pack ) {
		$problems = array();

		if ( $pack->get_capacity_g() <= 0 ) {
			$problems[] = __( 'Pack capacity (grams) is not set.', 'weight-based-product-packs' );
		}
		if ( ! $pack->get_source_cat() ) {
			$problems[] = __( 'The allowed items category is not selected.', 'weight-based-product-packs' );
		}

		$bundles = self::get_for_pack( $pack );
		if ( $pack->get_source_cat() && empty( $bundles ) ) {
			$problems[] = __( 'No valid weight bundles were found in the selected category. Every item needs a weight and stock.', 'weight-based-product-packs' );
		}

		if ( $pack->get_capacity_g() > 0 && ! empty( $bundles ) ) {
			$capacity  = $pack->get_capacity_g();
			$reachable = self::capacity_reachable( $capacity, $bundles );
			if ( ! $reachable ) {
				$problems[] = sprintf(
					/* translators: %s: pack capacity in grams */
					__( 'No combination of the current bundle weights can fill the %s g capacity exactly; adjust the weights or the capacity.', 'weight-based-product-packs' ),
					number_format_i18n( $capacity )
				);
			}
		}

		return $problems;
	}

	/**
	 * Whether the capacity can theoretically be matched with the bundle weights
	 * (coin problem divisibility check — a necessary condition).
	 */
	private static function capacity_reachable( $capacity, $bundles ) {
		$gcd = 0;
		foreach ( $bundles as $b ) {
			$gcd = self::gcd( $gcd, (int) $b['weight_g'] );
		}
		return $gcd > 0 && 0 === $capacity % $gcd;
	}

	private static function gcd( $a, $b ) {
		return $b ? self::gcd( $b, $a % $b ) : $a;
	}
}
