<?php
/**
 * Weight-based pack builder template.
 * Theme override: your-theme/woocommerce/single-product/add-to-cart/pack-builder.php
 *
 * Available variables:
 *
 * @var WBP_Product_Pack $product
 * @var array            $bundles  Bundle list (WBP_Items::get_for_pack).
 * @var array            $problems Configuration warnings.
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="wbp-builder" data-pack-id="<?php echo esc_attr( $product->get_id() ); ?>">

	<?php if ( ! empty( $problems ) ) : ?>

		<div class="woocommerce-error" role="alert">
			<?php foreach ( $problems as $problem ) : ?>
				<li><?php echo esc_html( $problem ); ?></li>
			<?php endforeach; ?>
		</div>

	<?php elseif ( empty( $bundles ) ) : ?>

		<div class="woocommerce-info" role="alert">
			<?php esc_html_e( 'No items are currently available for this pack.', 'weight-based-product-packs' ); ?>
		</div>

	<?php else : ?>

		<div class="wbp-progress" aria-live="polite">
			<div class="wbp-progress-bar">
				<span class="wbp-progress-fill"></span>
			</div>
			<div class="wbp-progress-text">
				<span class="wbp-current">0</span>
				<span class="wbp-sep">/</span>
				<span class="wbp-capacity"><?php echo esc_html( number_format_i18n( $product->get_capacity_g() ) ); ?></span>
				<span class="wbp-unit"><?php esc_html_e( 'g', 'weight-based-product-packs' ); ?></span>
			</div>
		</div>

		<div class="wbp-grid">
			<?php foreach ( $bundles as $b ) : ?>
				<div class="wbp-item" data-id="<?php echo esc_attr( $b['id'] ); ?>">
					<?php if ( $b['image_url'] ) : ?>
						<img class="wbp-item-image" src="<?php echo esc_url( $b['image_url'] ); ?>" alt="<?php echo esc_attr( $b['name'] ); ?>" loading="lazy" />
					<?php endif; ?>

					<div class="wbp-item-name"><?php echo esc_html( $b['name'] ); ?></div>

					<div class="wbp-item-meta">
						<span class="wbp-item-weight"><?php echo esc_html( WBP_Items::weight_label( $b['weight_g'] ) ); ?></span>
						<span class="wbp-item-price"><?php echo wp_kses_post( $b['price_html'] ); ?></span>
					</div>

					<div class="wbp-stepper">
						<button type="button" class="wbp-step wbp-minus" aria-label="<?php esc_attr_e( 'Decrease quantity', 'weight-based-product-packs' ); ?>">&minus;</button>
						<span class="wbp-count">0</span>
						<button type="button" class="wbp-step wbp-plus" aria-label="<?php esc_attr_e( 'Increase quantity', 'weight-based-product-packs' ); ?>">+</button>
					</div>
				</div>
			<?php endforeach; ?>
		</div>

		<div class="wbp-summary">
			<div class="wbp-total-price"></div>
			<div class="wbp-hint" aria-live="polite"></div>
			<button type="button" class="button alt wbp-add" disabled>
				<?php esc_html_e( 'Add pack to cart', 'weight-based-product-packs' ); ?>
			</button>
		</div>

	<?php endif; ?>
</div>
