=== Weight-Based Product Packs for WooCommerce ===
Contributors: atashinbar
Tags: woocommerce, pack, weight, mix and match, box builder
Requires at least: 6.4
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Let customers fill weight-based packs with pre-defined bundles. Packs can only be purchased when filled exactly to capacity.

== Description ==

This plugin is designed for stores that sell by filling containers with a chosen combination of goods — for example nuts and dried fruits packed into pre-ordered 1 kg, 2 kg or 5 kg boxes.

The customer picks a pack, then fills it with the weight bundles you define (e.g. a 100 g pistachio pack, a 200 g almond pack). The **total weight must exactly match the pack capacity** — the Add to Cart button stays disabled until the pack is perfectly full.

= Features =

* New "Weight-Based Pack" product type with any capacity (grams)
* Fill packs with weight bundles (simple products or product variations)
* Weight progress bar and live price calculation on the product page
* Price = sum of bundle prices + optional fixed box cost
* Server-side validation: under-filled and over-filled packs are rejected
* Stock is reduced automatically after payment and restored on cancellation
* Contents breakdown shown in cart, checkout, admin orders and emails
* Shipping weight of each pack equals its capacity
* Multiple packs with different contents in one order
* HPOS compatible; tested with WooCommerce 6–11
* Translation ready (Persian translation included)

== Installation ==

1. Upload the `weight-based-product-packs` folder to `/wp-content/plugins/`.
2. Activate "Weight-Based Product Packs for WooCommerce" on the Plugins screen.
3. Follow the Usage steps below.

== Usage ==

= Step 1: create the weight bundles =

For each item (pistachios, almonds, ...) create a WooCommerce product:

* **Recommended — variable product:** e.g. "Pistachios" with a "Weight" attribute and 100 g / 200 g / 500 g variations. Give each variation a **price** and a **weight** (the standard WooCommerce weight field) and, with stock management enabled, a **stock quantity** (e.g. 100 units of the 100 g bundle).
* **Simple product:** each bundle is a standalone product with a price, weight and stock.

Important: bundle weights must be entered in the standard WooCommerce weight field; the plugin converts them to grams based on the store weight unit (WooCommerce → Settings → Shipping → Weight unit). With the unit set to kg, a 100 g bundle is 0.1.

Put all items in one product category (e.g. "Nuts").

= Step 2: create the pack =

1. Go to Products → Add New.
2. In the Product data box set the type to **Weight-Based Pack**.
3. In the **Pack Settings** tab:
   * Pack capacity (grams): e.g. 1000 for a 1 kg box
   * Box cost (optional): fixed packaging amount
   * Allowed items category: the category from step 1
   * Excluded IDs (optional)
4. Publish.

Repeat with capacities 2000 and 5000 for the 2 kg and 5 kg boxes.

= Step 3: the customer experience =

1. The customer opens a pack from your shop (the "Build pack" button).
2. Using the + / − buttons they choose how many of each bundle to include; the weight progress bar and live price update as they go.
3. "Add pack to cart" is enabled only when the total weight exactly matches the capacity (e.g. 5 × 200 g = 1000 g).
4. The customer can add more packs with different combinations to the same cart and check out once.

== Frequently Asked Questions ==

= Why is a bundle missing from the pack builder page? =

The bundle must: belong to the pack's selected category, have a weight greater than zero, be in stock and not be excluded. Variations must also be published.

= Can a customer buy a partially filled pack? =

No. The button is disabled client-side, and the server rejects both under-filled and over-filled packs; contents are re-validated in the cart and at checkout.

= Where do prices come from? =

Always from the server, using current product prices; browser-side tampering has no effect.

= Is stock reduced? =

Yes — after payment each bundle's stock is reduced by the ordered quantity, and restored if the order is cancelled.

= Can I customize the template? =

Yes — copy `templates/single-product/add-to-cart/pack-builder.php` to `your-theme/woocommerce/single-product/add-to-cart/pack-builder.php` and edit it. Styles use the `wbp-*` classes.

== Changelog ==

= 1.0.0 =
* Initial release.
