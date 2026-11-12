# Kkkonrad_FreeGift

Free gifts from cart price rules: products added to the cart at 0.00.

## Admin

Marketing → Cart Price Rules → a rule as usual (conditions, coupon, dates, groups,
priority all work as in core), then in **Actions**:

- **Apply** = *Add free gift (SKUs below)*
- **Free Gift SKUs** = `SKU1, SKU2` — simple/virtual products only
- **Discount Amount** = how many gifts (min. 1)
- *Apply to* conditions = which cart items trigger the gift (empty = any item)

One SKU → added automatically at 0.00. Several SKUs → the cart page shows
"Choose your free gift"; the customer picks up to *Discount Amount* of them.

When the rule stops applying (subtotal drops, coupon removed) the gift line is
removed. Gift qty is clamped to the allowed number. A gift never merges with a
paid line of the same product.

## How it works

- `Model\Calculator` — registered as discount type `kk_free_gift`; the rule
  engine calls it for matching items, it only records "rule fired" on the quote.
- `Plugin\SyncGifts` — around `Quote::collectTotals`: after totals, adds /
  trims / removes gift lines (`Model\Gifts::sync`) and collects once more.
- Gift lines: item option `kk_free_gift` = rule id, custom price 0,
  `no_discount` (so a gift cannot keep its own rule alive via *Apply to*).
- `kkfreegift/cart/add` (POST, form key) — the chooser button; re-validates.

## Not included

- "N gifts per X bought" / spent-amount steps — the gift count is fixed per rule.
- Gift popup on product pages, gift banners, "gift" badge in the cart line
  (it shows as 0,00 zł).
- A customer who deletes an auto-added gift gets it back on the next cart change.
- "Product found in cart" *conditions* (not *Apply to*) still see gift lines;
  don't use a gift SKU in a rule's own conditions.

## Check

`php Test/scenario.php new add <sku>` then `php Test/scenario.php <quoteId> <op> <arg>`
(ops: add, qty sku=n, giftqty n, coupon CODE, pick ruleId=sku, delete). Run each
step as its own process: Magento caches rule validity per process
(`Utility::canProcessRule`), so a multi-step single-process test lies.
