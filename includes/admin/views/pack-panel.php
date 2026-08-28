<?php
/**
 * Pack Settings panel view (inside the WooCommerce product data box).
 *
 * Available variables: $capacity, $box_cost, $cat, $excluded
 */

defined( 'ABSPATH' ) || exit;
?>
<div id="wbpp_pack_data" class="panel woocommerce_options_panel hidden">
	<div class="options_group">
		<p class="form-field">
			<label for="_wbpp_capacity_g"><?php esc_html_e( 'Pack capacity (grams)', 'weight-based-product-packs' ); ?></label>
			<input type="number" min="50" step="10" name="_wbpp_capacity_g" id="_wbpp_capacity_g"
				value="<?php echo esc_attr( $capacity ); ?>" placeholder="<?php echo esc_attr( '1000' ); ?>" />
			<?php echo wc_help_tip( esc_html__( 'The total weight of the bundles inside the pack must equal this number exactly; e.g. enter 1000 for a 1 kg box.', 'weight-based-product-packs' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		</p>

		<p class="form-field">
			<label for="_wbpp_box_cost"><?php esc_html_e( 'Box cost (optional)', 'weight-based-product-packs' ); ?></label>
			<input type="text" class="short wc_input_price" name="_wbpp_box_cost" id="_wbpp_box_cost"
				value="<?php echo esc_attr( $box_cost ); ?>" placeholder="<?php echo esc_attr( '0' ); ?>" />
			<?php echo wc_help_tip( esc_html__( 'A fixed amount added to the total price of the pack contents; the cost of the box itself.', 'weight-based-product-packs' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		</p>

		<p class="form-field">
			<label for="_wbpp_source_cat"><?php esc_html_e( 'Allowed items category', 'weight-based-product-packs' ); ?></label>
			<?php
			wp_dropdown_categories(
				array(
					'taxonomy'          => 'product_cat',
					'name'              => '_wbpp_source_cat',
					'id'                => '_wbpp_source_cat',
					'selected'          => $cat,
					'show_option_none'  => __( '— Select a category —', 'weight-based-product-packs' ),
					'option_none_value' => '0',
					'hierarchical'      => true,
					'hide_empty'        => false,
					'class'             => 'select short',
				)
			);
			?>
			<?php echo wc_help_tip( esc_html__( 'Simple products and variations of variable products in this category appear as selectable bundles on the pack builder page. Every item must have a weight and, when stock management is enabled, stock.', 'weight-based-product-packs' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		</p>

		<p class="form-field">
			<label for="_wbpp_exclude_ids"><?php esc_html_e( 'Excluded IDs (optional)', 'weight-based-product-packs' ); ?></label>
			<input type="text" name="_wbpp_exclude_ids" id="_wbpp_exclude_ids"
				value="<?php echo esc_attr( $excluded ); ?>" placeholder="<?php echo esc_attr( '12,34' ); ?>" />
			<?php echo wc_help_tip( esc_html__( 'Comma-separated product or variation IDs that should not be shown in this pack.', 'weight-based-product-packs' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		</p>
	</div>

	<div class="options_group">
		<p class="form-field">
			<span class="description">
				<?php esc_html_e( 'Tip: for the pack to be fillable exactly, the bundle weights must be able to build multiples of the pack capacity; e.g. 100 g, 200 g and 500 g bundles are ideal for a 1000 g pack.', 'weight-based-product-packs' ); ?>
			</span>
		</p>
	</div>
</div>
