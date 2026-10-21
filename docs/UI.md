# BulkBites UI guide

The website supports a small six-item BBQ menu, pickup, and delivery within
Pasig for a fixed PHP20 fee. Keep the existing paper, charcoal, ember and gold
palette, shared typography, realistic product images and matching SVG icons.

## Customer experience

- Keep Menu, Cart and My orders as the main navigation.
- Quantity controls update in place; adding food shows a small confirmation.
- Show one checkout action per viewport: the floating action on mobile or the
  summary action on desktop.
- Checkout starts with fulfillment and payment. Pickup contact/notes are
  optional and collapsed; delivery exposes required address/contact fields.
- An order shows its total and current next instruction first. Detailed history,
  help and cancellation remain available in expandable sections.
- Payment confirmation, receipts and order records must accurately distinguish
  cash due, paid and simulated payments.
- Checkout retains a customer-specific draft in the same browser tab for up to
  30 minutes. Refresh/failed navigation restores contact details, notes and
  selections; successful ordering clears the draft. Security tokens are excluded.
- Offline checkout explains the connection problem and prevents submission.
  During submission the order button is disabled and reads Placing order.

## Manager and staff experience

- Managers use Dashboard, Orders, Products & stock, Reports and Settings.
- Keep order rows compact. View order opens a window with the next action
  first; secondary actions and metadata are expandable. Status updates remain
  automatic without page reloads.
- Pending order actions read Updating; product saves read Saving. Buttons are
  disabled until the request finishes and recover on failure. Lost responses
  never trigger an automatic repeat of a stock, payment or status action.
- Manage product opens a window starting with restocking. Corrections,
  thresholds, product editing and menu visibility are secondary controls.
- Reports show totals before controls. Offer Today, Last 7 days and an
  expandable custom date range.
- Mobile staff/manager navigation contains Log out. Kitchen has an All stages
  selector on mobile and keeps its columns on desktop.

## Consistency and accessibility

Use the shared form/button components, visible focus, readable labels, 44px
interaction targets and meaningful validation. Keep native form fallbacks and
CSRF protections. Never invent payment or cooking progress. Grill interactions
respect reduced motion and should not delay ordering.

## Review evidence

Current screenshots: [desktop/mobile gallery](../output/playwright/review-audit/index.html).
Review history and verification: [UI review](UI-REVIEW.md).
Historical screenshot sets, earlier UI documents and unused SVG illustrations
are preserved in ignored private archives. They are not runtime dependencies.

## Banner and order-wait refinement (21 October 2026)

The delivery fee and Pasig-only area appear on separate lines, with space before
View menu. Mobile ordering status and daily hours are centered. The mobile grill
is up to 280px wide; its controls have a smaller 34px visible circle inside a 44px
tap target. Checkout shows the Pasig-only restriction before delivery selection.

After five minutes, actionable new orders display a waiting-for-acceptance notice
in Orders and Kitchen. Unpaid online orders and already accepted orders are
excluded. The existing live updates recalculate the warning automatically.
Native order/product windows passed keyboard focus, Escape and focus-return checks.

Verification: 208 backend assertions, 43 browser checks, and final captures at
320/390/760/1440px with daily hours visible, no overflow and no broken images.

## Combined dashboard (21 October 2026)

Managers see one Open/Close control, three daily summary cards, the live kitchen
queue, and low-stock notices only when needed. Mobile summary cards use two
compact rows. Staff use the same queue without manager financial cards. Manager
Kitchen links redirect to Dashboard; Orders retains Active orders and History.
One API poll updates the queue, summary, ordering state and stock notice. Status,
cash and Open/Close actions stay on the page. Plain forms return to the correct
role workspace. Staff responses exclude manager totals.

Validation: 208 business assertions, 65 HTTP access/redirect checks and 25 browser
checks passed. Final captures additionally verified readable totals and responsive
layout at 320/390/760/1440px, native form returns and staff privacy. Testing used a
disposable database; live records were preserved.
