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
			<label for="_wbpp_step_g"><?php esc_html_e( 'Mixing step in grams (optional)', 'weight-based-product-packs' ); ?></label>
			<input type="number" min="0" step="10" name="_wbpp_step_g" id="_wbpp_step_g"
				value="<?php echo esc_attr( $step ); ?>" placeholder="<?php echo esc_attr( '0' ); ?>" />
			<?php echo wc_help_tip( esc_html__( 'E.g. 250 lets customers add each item in 250 g increments instead of whole bundles, with bundle prices prorated per gram. Must divide the pack capacity exactly. Leave empty or 0 for default bundle behavior.', 'weight-based-product-packs' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
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

	<div class="options_group wbpp-packagings-group">
		<p class="form-field">
			<label><?php esc_html_e( 'Packaging options', 'weight-based-product-packs' ); ?></label>
			<span class="description" style="display:block;margin-bottom:8px;">
				<?php esc_html_e( 'Optional: let the customer choose a container (e.g. hardbox, zip pouch, glass jar). Each option can have its own cost and capacity; a capacity of 0 uses the pack capacity.', 'weight-based-product-packs' ); ?>
			</span>
		</p>
		<div class="wbpp-packagings" data-next-index="<?php echo esc_attr( count( $packagings ) ); ?>">
			<?php $rows = $has_custom_packagings ? $packagings : array(); ?>
			<?php foreach ( $rows as $i => $row ) : ?>
				<p class="form-field wbpp-packaging-row" data-index="<?php echo esc_attr( $i ); ?>">
					<input type="text" class="short" name="_wbpp_packagings[<?php echo esc_attr( $i ); ?>][label]"
						value="<?php echo esc_attr( $row['label'] ); ?>" placeholder="<?php esc_attr_e( 'Label (e.g. Hardbox)', 'weight-based-product-packs' ); ?>" />
					<input type="text" class="short wc_input_price" name="_wbpp_packagings[<?php echo esc_attr( $i ); ?>][cost]"
						value="<?php echo esc_attr( $row['cost'] ); ?>" placeholder="<?php esc_attr_e( 'Cost', 'weight-based-product-packs' ); ?>" />
					<input type="number" min="0" step="10" class="short" name="_wbpp_packagings[<?php echo esc_attr( $i ); ?>][capacity_g]"
						value="<?php echo esc_attr( $row['capacity_g'] ); ?>" placeholder="<?php esc_attr_e( 'Capacity (grams)', 'weight-based-product-packs' ); ?>" />
					<button type="button" class="button wbpp-packaging-remove">&times;</button>
				</p>
			<?php endforeach; ?>
		</div>
		<p class="form-field">
			<button type="button" class="button wbpp-packaging-add"><?php esc_html_e( 'Add packaging', 'weight-based-product-packs' ); ?></button>
			<template class="wbpp-packaging-template">
				<p class="form-field wbpp-packaging-row" data-index="{i}">
					<input type="text" class="short" name="_wbpp_packagings[{i}][label]" value="" placeholder="<?php esc_attr_e( 'Label (e.g. Hardbox)', 'weight-based-product-packs' ); ?>" />
					<input type="text" class="short wc_input_price" name="_wbpp_packagings[{i}][cost]" value="" placeholder="<?php esc_attr_e( 'Cost', 'weight-based-product-packs' ); ?>" />
					<input type="number" min="0" step="10" class="short" name="_wbpp_packagings[{i}][capacity_g]" value="" placeholder="<?php esc_attr_e( 'Capacity (grams)', 'weight-based-product-packs' ); ?>" />
					<button type="button" class="button wbpp-packaging-remove">&times;</button>
				</p>
			</template>
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

<script>
(function () {
	'use strict';
	var wrap = document.querySelector('#wbpp_pack_data .wbpp-packagings');
	var tpl  = document.querySelector('#wbpp_pack_data .wbpp-packaging-template');
	var add  = document.querySelector('#wbpp_pack_data .wbpp-packaging-add');
	if (!wrap || !tpl || !add) { return; }

	add.addEventListener('click', function () {
		var next = parseInt(wrap.getAttribute('data-next-index'), 10) || 0;
		var html = tpl.innerHTML.replace(/\{i\}/g, String(next));
		wrap.insertAdjacentHTML('beforeend', html);
		wrap.setAttribute('data-next-index', String(next + 1));
	});

	wrap.addEventListener('click', function (e) {
		var btn = e.target.closest('.wbpp-packaging-remove');
		if (btn) {
			btn.closest('.wbpp-packaging-row').remove();
		}
	});
})();
</script>
