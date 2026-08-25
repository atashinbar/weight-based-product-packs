# Weight-Based Product Packs for WooCommerce

Let customers fill weight-based packs (e.g. 1 kg boxes) with pre-defined weight bundles. A pack can only be purchased when its total weight **exactly matches its capacity** — perfect for nuts & dried fruit stores, candy shops, tea blends, or any store that packs goods into pre-ordered boxes.

## How it works

1. Define your bundles as normal WooCommerce products/variations with a **price**, **weight** and **stock** (e.g. 100 g / 200 g / 500 g pistachio packs).
2. Create a "Weight-Based Pack" product, set its capacity (grams), an optional box cost, and the source category.
3. On the product page the customer fills the pack with steppers, guided by a **weight progress bar** and live price.
4. The **Add to cart** button stays disabled until the pack is exactly full (e.g. 5 × 200 g = 1000 g). Validation is enforced server-side again in the cart and at checkout.

## Features

- New `Weight-Based Pack` product type
- Weight-based (not count-based) capacity validation
- Price = Σ(bundle price × qty) + optional box cost — always computed server-side
- Automatic stock reduction per bundle after payment (restored on cancellation)
- Contents breakdown in cart, checkout, order admin and emails
- Multiple packs with different contents in a single order
- Shipping weight = pack capacity (works with weight-based shipping)
- Translation-ready; Persian (fa_IR) translation included
- HPOS compatible · tested with WooCommerce 6–11

## Installation

Upload the `weight-based-product-packs` folder to `/wp-content/plugins/` and activate. Full usage guide in [`readme.txt`](readme.txt).

## Releases

Pushing a git tag (e.g. `v1.0.1`) triggers the [WordPress.org deploy workflow](.github/workflows/deploy.yml) (requires the `WPORG_USERNAME` and `SVN_PASSWORD` repository secrets). Every push to `main` builds an installable zip artifact via [build-zip.yml](.github/workflows/build-zip.yml).

## License

[GPL-2.0-or-later](LICENSE)

---

## فارسی

افزونه «پک وزنی ووکامرس»: مشتری پک (کارتن ۱، ۲ یا ۵ کیلویی) را با بسته‌های وزنی از پیش تعریف‌شده طوری پر می‌کند که جمع وزن **دقیقاً** برابر ظرفیت پک شود. راهنمای کامل فارسی در پوشه افزونه و [`readme.txt`](readme.txt) — ترجمه فارسی داخل افزونه همراه است. برای انتشار نسخه جدید کافی است نسخه را در هدر افزونه و `Stable tag` فایل readme بالا ببرید و تگ بزنید؛ GitHub Action به‌صورت خودکار آن را روی SVN وردپرس منتشر می‌کند.
