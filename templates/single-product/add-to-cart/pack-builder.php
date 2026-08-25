<?php
/**
 * Weight-based pack builder template.
 * Theme override: your-theme/woocommerce/single-product/add-to-cart/pack-builder.php
 *
 * Available variables:
 *
 * @var WBP_Product_Pack $product
 * @var array            $bundles  Bundle list (WBP_Items::get_for_pack).
 * @var array            $groups   Bundles grouped by parent product (one card per item).
 * @var array            $sizes    Sibling packs sharing the source category (size switcher).
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

		<?php if ( count( $sizes ) > 1 ) : ?>
			<div class="wbp-switcher" role="group" aria-label="<?php esc_attr_e( 'Pack size', 'weight-based-product-packs' ); ?>">
				<span class="wbp-switcher-label"><?php esc_html_e( 'Pack size', 'weight-based-product-packs' ); ?></span>
				<?php foreach ( $sizes as $size ) : ?>
					<?php if ( $size['is_current'] ) : ?>
						<span class="wbp-size is-current" aria-current="true"><?php echo esc_html( WBP_Items::weight_label( $size['capacity_g'] ) ); ?></span>
					<?php else : ?>
						<a class="wbp-size" href="<?php echo esc_url( $size['url'] ); ?>"><?php echo esc_html( WBP_Items::weight_label( $size['capacity_g'] ) ); ?></a>
					<?php endif; ?>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>

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

		<div class="wbp-groups">
			<?php foreach ( $groups as $group ) : ?>
				<div class="wbp-group" data-parent="<?php echo esc_attr( $group['parent_id'] ); ?>">
					<div class="wbp-group-head">
						<?php if ( $group['image_url'] ) : ?>
							<img class="wbp-group-image" src="<?php echo esc_url( $group['image_url'] ); ?>" alt="<?php echo esc_attr( $group['name'] ); ?>" loading="lazy" />
						<?php endif; ?>
						<span class="wbp-group-name"><?php echo esc_html( $group['name'] ); ?></span>
						<span class="wbp-group-total"></span>
					</div>

					<?php foreach ( $group['rows'] as $b ) : ?>
						<div class="wbp-row<?php echo $b['stock'] !== null && $b['stock'] <= 0 ? ' is-out' : ''; ?>" data-id="<?php echo esc_attr( $b['id'] ); ?>">
							<span class="wbp-row-label"><?php echo esc_html( $b['attr_label'] ? $b['attr_label'] : WBP_Items::weight_label( $b['weight_g'] ) ); ?></span>
							<span class="wbp-row-price"><?php echo wp_kses_post( $b['price_html'] ); ?></span>
							<div class="wbp-stepper">
								<button type="button" class="wbp-step wbp-minus" aria-label="<?php esc_attr_e( 'Decrease quantity', 'weight-based-product-packs' ); ?>">&minus;</button>
								<span class="wbp-count">0</span>
								<button type="button" class="wbp-step wbp-plus" aria-label="<?php esc_attr_e( 'Increase quantity', 'weight-based-product-packs' ); ?>">+</button>
							</div>
						</div>
					<?php endforeach; ?>
				</div>
			<?php endforeach; ?>
		</div>

		<div class="wbp-summary">
			<div class="wbp-summary-main">
				<div class="wbp-total-price"></div>
				<div class="wbp-hint" aria-live="polite"></div>
				<button type="button" class="button alt wbp-add" disabled>
					<?php esc_html_e( 'Add pack to cart', 'weight-based-product-packs' ); ?>
				</button>
			</div>
			<div class="wbp-preview" aria-hidden="true">
				<span class="wbp-preview-fill"></span>
				<span class="wbp-preview-text">0%</span>
			</div>
		</div>

	<?php endif; ?>
</div>
