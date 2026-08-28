<?php
/**
 * Weight-based pack builder template.
 * Theme override: your-theme/woocommerce/single-product/add-to-cart/pack-builder.php
 *
 * Available variables:
 *
 * @var WBPP_Product_Pack $product
 * @var array            $wbpp_bundles  Bundle list (WBPP_Items::get_for_pack).
 * @var array            $wbpp_groups   Bundles grouped by parent product (one card per item).
 * @var array            $wbpp_sizes    Sibling packs sharing the source category (size switcher).
 * @var array            $wbpp_problems Configuration warnings.
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="wbpp-builder" data-pack-id="<?php echo esc_attr( $product->get_id() ); ?>">

	<?php if ( ! empty( $wbpp_problems ) ) : ?>

		<div class="woocommerce-error" role="alert">
			<?php foreach ( $wbpp_problems as $wbpp_problem ) : ?>
				<li><?php echo esc_html( $wbpp_problem ); ?></li>
			<?php endforeach; ?>
		</div>

	<?php elseif ( empty( $wbpp_bundles ) ) : ?>

		<div class="woocommerce-info" role="alert">
			<?php esc_html_e( 'No items are currently available for this pack.', 'weight-based-product-packs' ); ?>
		</div>

	<?php else : ?>

		<?php if ( count( $wbpp_sizes ) > 1 ) : ?>
			<div class="wbpp-switcher" role="group" aria-label="<?php esc_attr_e( 'Pack size', 'weight-based-product-packs' ); ?>">
				<span class="wbpp-switcher-label"><?php esc_html_e( 'Pack size', 'weight-based-product-packs' ); ?></span>
				<?php foreach ( $wbpp_sizes as $wbpp_size ) : ?>
					<?php if ( $wbpp_size['is_current'] ) : ?>
						<span class="wbpp-size is-current" aria-current="true"><?php echo esc_html( WBPP_Items::weight_label( $wbpp_size['capacity_g'] ) ); ?></span>
					<?php else : ?>
						<a class="wbpp-size" href="<?php echo esc_url( $wbpp_size['url'] ); ?>"><?php echo esc_html( WBPP_Items::weight_label( $wbpp_size['capacity_g'] ) ); ?></a>
					<?php endif; ?>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>

		<div class="wbpp-progress" aria-live="polite">
			<div class="wbpp-progress-bar">
				<span class="wbpp-progress-fill"></span>
			</div>
			<div class="wbpp-progress-text">
				<span class="wbpp-current">0</span>
				<span class="wbpp-sep">/</span>
				<span class="wbpp-capacity"><?php echo esc_html( number_format_i18n( $product->get_capacity_g() ) ); ?></span>
				<span class="wbpp-unit"><?php esc_html_e( 'g', 'weight-based-product-packs' ); ?></span>
			</div>
		</div>

		<div class="wbpp-groups">
			<?php foreach ( $wbpp_groups as $wbpp_group ) : ?>
				<div class="wbpp-group" data-parent="<?php echo esc_attr( $wbpp_group['parent_id'] ); ?>">
					<div class="wbpp-group-head">
						<?php if ( $wbpp_group['image_url'] ) : ?>
							<img class="wbpp-group-image" src="<?php echo esc_url( $wbpp_group['image_url'] ); ?>" alt="<?php echo esc_attr( $wbpp_group['name'] ); ?>" loading="lazy" />
						<?php endif; ?>
						<span class="wbpp-group-name"><?php echo esc_html( $wbpp_group['name'] ); ?></span>
						<span class="wbpp-group-total"></span>
					</div>

					<?php foreach ( $wbpp_group['rows'] as $wbpp_row ) : ?>
						<div class="wbpp-row<?php echo $wbpp_row['stock'] !== null && $wbpp_row['stock'] <= 0 ? ' is-out' : ''; ?>" data-id="<?php echo esc_attr( $wbpp_row['id'] ); ?>">
							<span class="wbpp-row-label"><?php echo esc_html( $wbpp_row['attr_label'] ? $wbpp_row['attr_label'] : WBPP_Items::weight_label( $wbpp_row['weight_g'] ) ); ?></span>
							<span class="wbpp-row-price"><?php echo wp_kses_post( $wbpp_row['price_html'] ); ?></span>
							<div class="wbpp-stepper">
								<button type="button" class="wbpp-step wbpp-minus" aria-label="<?php esc_attr_e( 'Decrease quantity', 'weight-based-product-packs' ); ?>">&minus;</button>
								<span class="wbpp-count">0</span>
								<button type="button" class="wbpp-step wbpp-plus" aria-label="<?php esc_attr_e( 'Increase quantity', 'weight-based-product-packs' ); ?>">+</button>
							</div>
						</div>
					<?php endforeach; ?>
				</div>
			<?php endforeach; ?>
		</div>

		<div class="wbpp-summary">
			<div class="wbpp-summary-main">
				<div class="wbpp-total-price"></div>
				<div class="wbpp-hint" aria-live="polite"></div>
				<button type="button" class="button alt wbpp-add" disabled>
					<?php esc_html_e( 'Add pack to cart', 'weight-based-product-packs' ); ?>
				</button>
			</div>
			<div class="wbpp-preview" aria-hidden="true">
				<span class="wbpp-preview-fill"></span>
				<span class="wbpp-preview-text">0%</span>
			</div>
		</div>

	<?php endif; ?>
</div>
