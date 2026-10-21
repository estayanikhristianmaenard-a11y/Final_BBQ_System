## Reliability follow-up (21 October 2026)

Checkout now keeps customer-specific, tab-local drafts for 30 minutes without
security tokens, restores fields after refresh/failed navigation, and clears the
draft on successful ordering. Offline submission is blocked with an explanation.
The placing-order button is disabled; asynchronous product and order actions show
Saving/Updating and restore controls after failure. Customer reconnection feedback
explicitly warns when status may be stale. Interrupted mutations are never retried
automatically.

Verification passed: 202 backend assertions, 90 local TLS SMTP checks and 35
browser checks. Two independent browser sessions completed a cash pickup and a
demo-paid delivery, including their receipts and fixed PHP20 delivery fee. Actual
phones and real Gmail inbox delivery still need the presentation-day check in
[DEMO-CHECKLIST.md](DEMO-CHECKLIST.md). Test helpers were archived privately and the
disposable server/database were removed; the main server remains available.

[Rehearsal results](../output/playwright/reliability/results.json).

---

# Current review: 21 October 2026

The simplification recommendations below are now implemented. Customer order
pages show one main total and next instruction, with history/help collapsed.
Cart has one checkout action per viewport. Pickup optional fields are grouped;
delivery keeps required contact/address and the fixed PHP20 fee within Pasig.
Order windows put the next action first. Product windows put Stock first and
collapse product editing. Reports put totals before two presets and custom dates.
Mobile manager logout is inside navigation; Kitchen has a stage selector.

Validation: **202 backend assertions and 36 focused browser checks passed**.
All 34 refreshed page captures returned HTTP200 without horizontal overflow or
broken images. Four additional screenshots show order/product windows. Selected
mobile pages and both windows were inspected visually. Tests used a disposable
database; the source database was preserved. PHP/JavaScript syntax checks passed.

[Current screenshot gallery](../output/playwright/review-audit/index.html).
Current design rules live in [UI.md](UI.md). Older entries below record earlier
implementations and may describe superseded layouts or grill implementations.

Cleanup preserved old screenshot sets, one-off development helpers, two redundant
UI documents and seven unused SVG illustrations in verified ZIP archives under
ignored `archives/`. A fresh SQL backup and checksum are in `archives/backups/`.
The disposable review server/database were removed. Active ngrok/server runtime,
QR assets, compatibility routes, notification handlers, optional PayMongo adapter,
vendor files, licenses and maintained tests remain. Exact file counts are in
`archives/cleanup-manifest-20261021.json`.

---

# UI review history

Reviewed the earlier complete-page contact sheets, then captured the current application: **39 views, 78 full-page PNGs**, at 1440px desktop and 390px mobile. Coverage includes guest/login/OTP, customer menu/cart/checkout/order states/receipt, demo payment, staff dashboard/kitchen/orders/inventory/notifications, and manager management/reports/email/QR/store pages. Routing-only actions and APIs are not visual pages. The capture manifest records expected HTTP results, broken-image counts and overflow measurements.

## Findings and changes

| Finding | Applied improvement |
|---|---|
| Flat grill and calamansi banner clashes with realistic product renders | Transparent 3D-style grill render using the same food colors and textures |
| Original small-screen rule hid the grill at 360px and below | Stacked mobile hero; grill stays visible at 320px |
| Banner had no interaction | Keyboard/touch-friendly **Tilt grill / Reset view** control |
| Preparing state looked the same as a generic status history | Small grill and steam animation only for the actual `PREPARING` state |
| Motion needs user control | Pause/play button, reduced-motion support, static no-JavaScript fallback |
| Dashboard, empty cart and receipt header used unrelated emoji branding | Shared brand mark and matching cart/receipt/notification SVG outlines; plain labels for receipt actions and not-found states |
| Brand and staff-entry links had small tap targets | Minimum 44px height |

The banner is an **interactive 3D-style raster illustration**, with CSS perspective, rather than a WebGL model or a 360-degree viewer. It adds no 3D library, artificial order progress, delays or payment behavior. The transparent 640px WebP is 84,746 bytes. Existing order status polling determines when the preparing indicator appears and disappears.

## Verification

- All 78 captures matched their expected HTTP status; no broken images, PHP error text or horizontal page overflow was detected.
- Visually inspected all seven current contact sheets and the earlier seven complete-page sheets; full PNGs are available in the gallery for checking below the contact-sheet crop.
- Checked keyboard reset, tilt on 320px phones, pause/play, reduced motion, static no-JavaScript rendering, and absence of the animation for New, Ready, Completed and Cancelled states.
- Existing baseline suite: **170 assertions passed**, no workflow gaps; syntax checks passed for changed PHP and JavaScript.
- Captures use a disposable, anonymized database, with email and external payment disabled. Fixture login/email and public-link setup notices are expected, not evidence that the live sender was disconnected. No real email was sent.

## Artifacts

[Full screenshot gallery](../output/playwright/current-review/index.html), [capture manifest](../output/playwright/current-review/manifest.json), [small phone tilted grill](../output/playwright/current-review/menu-320-tilted.png), [Preparing view](../output/playwright/current-review/mobile/customer-order-preparing.png).

The screenshots and browser artifacts are local ignored output files. This review is kept with the project documentation. Staff/manager pages keep their existing charcoal/amber operational theme; customer pages keep ember actions. Those color differences distinguish staff tools from customer ordering.

## Customer and owner review improvements (2026-10-21)

All six follow-up findings are implemented:

- Staff and manager daily completion/cancellation counts use the latest matching terminal transition in status history, in Philippine time. Duplicate history cannot double-count an order. Imported orders without a recorded terminal transition are excluded from these daily counts rather than assigned an invented completion date. Order-created-today and payment collection figures retain their existing meanings.
- Checkout preserves notes and payment choice after server validation errors and reloads, alongside the existing pickup details. Notes remain escaped. A successful placement clears the notes/payment draft for the next order.
- Cancelled unpaid orders show **Cancelled - no payment due** in order details, lists, receipts/summaries and staff views. Paid cancellations keep refund guidance and refund-required/refunded states.
- Staff sees **Awaiting acceptance** separately from **Awaiting payment**. Cash orders and paid online orders enter the first count; unpaid online orders enter the second. The existing total New count remains available to API consumers. Kitchen polling updates both counters without permitting unpaid online preparation.
- The customer dashboard reads its pickup address from Store settings, matching the menu and checkout. Already-placed orders retain their pickup-location snapshots.
- The mobile banner uses a compact horizontal grill/control row while preserving the tilt interaction. Long customer notes also wrap within kitchen cards at 320px.

Validation: **181 baseline assertions passed** (including 11 new business regressions), **31 browser checks passed**, changed PHP/JavaScript syntax checks passed. Browser flows exercised an invalid-contact retry, retained Demo selection and notes, escaped script-like text, successful placement and draft clearing, configurable pickup address, cancelled payment wording, API count consistency, and layouts at 1440/390/320px. All order and setting mutations used an isolated sample database with external mail and payments disabled.

Latest previews: [compact menu](../output/playwright/business-review/menu-live-390.png), [staff counts](../output/playwright/business-review/staff-390.png), [kitchen at 320px](../output/playwright/business-review/kitchen-320.png), [retained checkout](../output/playwright/business-review/checkout-retained-320.png). The latter three use sample data. The earlier complete-page gallery above records the preceding UI pass.

## Grilling and sauce interaction (2026-10-21)

The latest banner replaces Tilt grill with rising smoke, glowing coals, a **Brush sauce** button and **Pause / Play**. After feedback that the original effect was too subtle, smoke and brush were enlarged and each brush pass extended to **3.2 seconds**. It paints the four skewers in sequence, then leaves a glaze. The button is temporarily disabled during the pass and becomes reusable afterward. A short live status announces completion. These controls are outside the ordering forms and never add sauce to an actual order.

Motion starts only with JavaScript. The pause control also stops an active brush pass, leaving a static glaze. With reduced motion, the grill starts still and brushing applies a static glaze. **Play remains visible**, letting the visitor explicitly opt just this component into animation; scoped CSS overrides the site's reduced-motion duration only after that action. Changes to the system preference reset the opt-in. With JavaScript disabled, the image stays static and inactive controls remain hidden. The Preparing status component retains pause/play and does not show a sauce control.

Verified **27 browser checks** covering smoke, embers, brush/repeat protection, keyboard operation, pause/resume, reduced-motion defaults and explicit opt-in, actual animation-clock progression, no-JavaScript fallback, no order POSTs or cart changes, and fit at 1440/390/320px. Separately checked the shared Preparing component at 320px, including play/pause, and the user's exact `index.php#menu` route. The `#menu` anchor scrolls toward the menu below the banner; scroll upward to reach the grill. Changed PHP and JavaScript passed syntax checks. No new production libraries, backend behavior or database changes were needed. A local Playwright video helper was installed for the demonstration recording only.

Previews: [brush in action](../output/playwright/grilling-sauce/brushing-desktop.png), [mobile menu](../output/playwright/grilling-sauce/menu-390.png), [small phone](../output/playwright/grilling-sauce/menu-320.png).

**Moving preview:** [recorded grilling animation](../output/playwright/grilling-sauce/grilling-animation.webm). PNG links above are still frames.

## Real 3D scene and login placeholders (latest, 2026-10-21)

The customer banner now uses a real WebGL scene, replacing the image-and-flat-brush effect above. The custom scene in `assets/js/grill-3d.js` models the open grill body, grate rods, bamboo skewers, irregular meat pieces, isaw, glowing charcoal, calamansi and a wood/metal/bristle brush. Materials, directional lighting, shadows and smoke sprites provide depth. Basting moves the brush through world coordinates and changes the food material's gloss. It is a stylized illustration, not a scanned model of the shop's actual products.

The visitor can drag horizontally or focus the canvas and use left/right arrows. Existing Brush sauce and Play/Pause controls remain. Reduced-motion preference is respected until explicit Play. The animation loop stops outside the viewport or while the document is hidden; rendering pixel ratio is capped at 1.5. WebGL failure leaves the existing still image and a short status, with inactive animation controls hidden. Order/payment behavior is unchanged.

Three.js **r180 / 0.180.0** is pinned and self-hosted under `assets/js/vendor/three/`, with its MIT license. Only the public banner imports it; the Preparing status component keeps its smaller existing image. Source/version: [official r180 package](https://github.com/mrdoob/three.js/blob/r180/package.json), [official renderer documentation](https://threejs.org/docs/#api/en/renderers/WebGLRenderer). No remote CDN or frontend build step is needed at runtime.

Both customer and personnel login fields now display **you@gmail.com** as placeholder text. Other valid email domains remain accepted because the authorized manager accounts use school addresses. Login still requires an emailed six-digit OTP, not a permanent password; no accounts, credentials or access rules were changed.

Validation: **18 browser checks** passed, including actual canvas rendering, a changed rendered image after keyboard/drag rotation, basting completion, explicit Play under reduced motion, layouts at 1440/390/320px, image fallback with WebGL disabled, and both placeholder fields without a Gmail-only restriction. No email or order POST was sent. PHP and JavaScript syntax checks passed.

Latest previews: [3D video](../output/playwright/real-3d/3d-grill.webm), [default view](../output/playwright/real-3d/default-view.png), [mobile view](../output/playwright/real-3d/banner-390.png).

The grill controls now use circular SVG brush and play/pause icons with 46px touch targets, tooltips and accessible names. Playback swaps its icon and label together. Browser checks passed for keyboard activation, basting completion, no JavaScript errors, and layouts at 1440/390/320px. Updated [mobile controls preview](../output/playwright/grill-controls/banner-390.png).

Customer and staff sign-in now share a roomier 460px card, a clear heading/body/helper type scale, consistent field spacing, 50px email and submit controls, and quieter account-switch links. Email helper text is associated with its input. Both forms passed browser validation and overflow checks at 1440/390/320px, without requesting an OTP; PHP lint passed. Previews: [desktop](../output/playwright/signin-spacing/customer-1440.png), [mobile](../output/playwright/signin-spacing/staff-320.png).

Checkout now displays a modal grilling progress screen after a valid Place order submission. It reuses the local 3D grill with smoke, glowing charcoal and a basting pass; a still image with CSS smoke is the fallback. The message says “Sending your order…” rather than claiming staff has started cooking. Native POST, validation, tokens and redirects remain in charge; no artificial delay is added. Repeat submits are blocked while pending, a slow-response message appears after 12 seconds, reduced motion stops animation, and pageshow resets the overlay. Thirteen isolated browser component checks and a simulated native-navigation handoff passed, including desktop/390/320px layouts; no real order or email was created. PHP and JS syntax checks passed. [Loading preview](../output/playwright/order-loading/loading-390.png).

## Simplified navigation and workspaces (2026-10-21)

Customer Home and staff Dashboard are now compatibility redirects to Menu and Kitchen. The Kitchen keeps queue counts and ordering controls, and shows low-stock warnings. Manager navigation has five destinations: Overview, Orders, Products & Stock, Reports, Settings. Product editing, price, stock adjustment, availability and menu visibility share one list; Add product and the stock movement log are collapsed. The stock action controller is shared with the legacy inventory endpoint, preserving transactions, CSRF and audit records. Category management remains available through existing URLs but is outside normal navigation.

The bell opens a compact, keyboard-accessible recent-updates panel, refreshed by the existing owner-scoped API. Mark all as read retains CSRF and owner checks. Separate notification URLs redirect to Orders or Kitchen; the unused full-page notification template was removed. Settings groups Store, Email, Ordering QR, Staff accounts and Customers. Reports open on today with collected sales, completed orders, demo totals and best sellers; detailed payment, order and customer figures remain expandable. The optional PayMongo adapter stays inactive under the default local simulator workflow.

Validation: 181 baseline assertions passed with zero workflow gaps and source database checksums unchanged; 61 HTTP checks and 12 login/asset redirect checks passed. Browser checks covered role menus, access denial, stock restock with confirmation, CSRF rejection, compatibility redirects, grouped settings, bell keyboard behavior and no overflow at 1440/390/320px. Test stock changes used a disposable database; no real orders, payments or emails were sent. PHP and changed JavaScript syntax checks passed. Previews: [Products & Stock](../output/playwright/simplified/products-1440.png), [mobile Orders](../output/playwright/simplified/customer-390.png), [mobile Reports](../output/playwright/simplified/reports-390.png).

## Complete workflow and visual consistency pass (2026-10-21)

The public and operations screens now share ember primary actions, readable semantic badges, sentence-case labels, consistent heading weights, 44px or larger action targets and aligned form spacing. Operations retain a charcoal header; the menu banner retains its gold call-to-action. Decorative emojis in staff/manager screens were replaced with shared SVG icons or useful text labels. The manager Overview no longer shows database implementation language.

Checkout keeps pickup details and the total beside the primary action, with receipt/quantity explanations in a disclosure. Order details use secondary buttons for additional shopping, and pending/accepted orders explain the next step. Access-denied pages retain HTTP 403 while using the shared header, footer and role-appropriate return link. A notification-panel hover contrast issue was corrected, and the Email page gives the right progress label when retrying receipts.

Live customer status and Kitchen polling now show reconnecting feedback and the last checked time. Kitchen GET refreshes have a timeout and avoid overlapping requests; updates resume when connectivity returns. Payment/state transitions, stock accounting and source records are preserved.

Validation completed:

- 181 business assertions: stock, concurrent checkout, payment gates, cancellations, refunds, simulator behavior and reporting. Source table checksums unchanged.
- 90 email/login assertions using local TLS SMTP: 18 fixture messages accepted, no external mail.
- 61 authenticated HTTP/access checks, 24 demo-setup checks, 18 restricted-router checks and 12 login/asset redirect checks.
- Fresh schema restoration and repeatable migration. A fresh source backup was also restored to an isolated database with matching row counts/checksums; temporary export removed.
- Full browser workflow: guest menu/cart, actual emailed OTP form and verification, preserved checkout, demo payment, locally delivered receipt, staff acceptance/preparation/readiness/completion and final receipt. Separate cash pickup could not complete until cash collection was confirmed. All 20 checks passed.
- Live-update failure/recovery, narrow 320px layouts and ordering QR generation: 13 checks passed. Final palette, notification hover/read action, shared 403 layout and trusted forwarded-HTTPS cookie/header behavior: 11 checks passed.
- 39 page views at 1440px and 390px produced 78 core PNGs with 235 response/render/overflow/script checks. Six key pages also checked at 320px. Seven contact sheets reviewed. Menu image captures explicitly load lazy images before recording.
- Expiry and receipt CLI workers executed successfully on the isolated review app. Existing expiry/retry scenarios are covered by the business/email suites. PHP/changed JavaScript/SVG syntax checks passed.

Review artifacts: [desktop/mobile gallery](../output/playwright/consistency/index.html), [checkout on mobile](../output/playwright/consistency/customer-checkout-390.png), [Kitchen on desktop](../output/playwright/consistency/staff-kitchen-1440.png), [end-to-end receipt](../output/playwright/consistency/flow-receipt.png).

Coverage limits: browser mobile emulation is not a physical phone test. Real Gmail delivery, real-device QR scanning and a live public tunnel were not exercised. Browser emails were confined to a local TLS SMTP fixture, and only the disposable application's settings and orders were changed. Maintenance workers were run manually; unattended scheduling remains a deployment step (no matching BulkBites/BBQ scheduled task was found during inspection). No real payment or external email was sent.

Kitchen ordering controls now use one contextual button: Close ordering when manually enabled, Open ordering when closed or paused. The existing hours, permissions and CSRF-protected status endpoint are preserved. Eighteen isolated browser checks passed for labels, keyboard submission and layouts at 1440/320px; PHP lint passed. Real store status was not changed during testing.

## Professor presentation cleanup (2026-10-21)

Default manager views now emphasize the six-item menu, price/stock/availability controls, today’s collections/completed orders, shop hours and ordering QR. Settings shows Store and Ordering QR, with Email setup, staff accounts, customer directory and categories in a native Advanced settings disclosure. Customer analytics, date filters and detailed accounting are behind Advanced reports. Demo totals remain separate from real collections.

Product creation moved below the operational list into Advanced product setup. Direct Edit product links jump to the expanded edit form; price stays visible while category/description/image controls are in More product details. Stock thresholds and order-limit fields are collapsed but remain part of their existing forms, so ordinary saves preserve their values. Email sender identity and credentials are concealed in Advanced email settings; pending-receipt warnings and retry controls remain accessible. These are presentation changes, not deletions or changes to permissions.

Cash, clearly labeled Demo payment, failure simulation, OTP, payment/stock checks, receipts, live order status, Kitchen and the one Open/Close button remain available. The optional PayMongo setup stays outside the presentation flow. No source configuration, credentials, products, customer history or orders were removed.

Validation: 75 isolated browser checks passed, including basic saves preserving collapsed field values, keyboard disclosure access, explicit report filters retaining their selected range, six products, payment choices and failed-payment simulation. Twenty-one screenshots cover seven screens at 1440/390/320px. All 61 HTTP/access checks and PHP syntax checks passed. Browser form changes used only a disposable database; no external emails or payments were sent. [Latest presentation gallery](../output/playwright/prof-demo/index.html).

## In-place ordering and focused Overview (2026-10-21)

Menu Add to cart now requests a same-origin JSON response instead of navigating back to the menu anchor. A small, dismissible live-status popup confirms the item/quantity; the menu badge, cart header and dock update from PHP-calculated state. Minus/plus selectors are available on each orderable menu item and stop at one. Existing cart quantity controls, typed changes, Remove and Clear update a shared server-rendered cart fragment in place. Focus and scroll position are preserved during replacement; the empty state appears without a page reload. Without JavaScript, native POST/redirect still works and menu adds return to the selected product anchor.

The JS enhancement retains CSRF/role checks and authoritative stock/prices, suppresses concurrent duplicate submissions, and never automatically resends after a lost response. A request that committed before its response was lost was tested to occur exactly once. The cart partial is shared between initial rendering and AJAX responses to avoid duplicate UI markup. The manager Overview is reduced to three daily figures, low-stock warnings when needed, and one Open Kitchen shortcut. Duplicate analytics and their unused query calls were removed from Overview; Reports still contains the detailed tools.

Validation: 24 interaction/layout/fallback checks and nine network/stock/tampering checks passed, including no navigation and preserved menu scroll. Screenshots reviewed at 1440/390/320px. All 65 HTTP checks passed, including AJAX CSRF and role failures. PHP and JS syntax checks passed. The 181 business assertions passed against a fresh temporary snapshot with the source database checksums unchanged. Tests now support BB_TEST_SNAPSHOT and compare collection changes relative to existing sales, rather than assuming an empty sales day. The saved project SQL snapshot was found older than the running database; refresh it through BACKUP-DATABASE.bat before the presentation. The saved snapshot and live database were left untouched by this work.

Previews: [mobile popup](../output/playwright/inplace-cart/menu-popup-390.png), [320px menu](../output/playwright/inplace-cart/menu-320.png), [simple Overview](../output/playwright/inplace-cart/overview-320.png). Tests used disposable application data and sessions; no real order, payment or external email was created.

## Pickup and delivery — 21 October 2026

Checkout now offers free Pickup or Delivery with a fixed ₱20 fee. Existing
payment-option styling, typography, spacing and mobile controls are reused.
Delivery address appears when selected; contact and address are required.
Switching fulfillment updates fee, total, place-order button and cash guidance.
The server computes the fee independently of browser fields and saves it with
the order. Kitchen initial HTML and refreshed cards, order history, order detail,
printable receipt and HTML/plain-text email receipts include fulfillment details.
Ready delivery orders instruct staff to complete after delivery/payment confirmation.
There is no map, rider account or GPS tracker. Preparation estimates exclude travel.

Validation: 202 isolated business assertions, 65 HTTP authorization checks, fresh
schema import plus repeatable migration, and checkout screenshots at 1440/390/320.
Historical order counts/totals were preserved; a private pre-migration SQL backup
is retained under ignored `.tmp/`. Browser previews: `output/playwright/delivery/`.

Additional browser checks passed for demo payment with the full delivery total,
paid receipt address, and JavaScript-disabled total review and checkout.

## Automatic order updates

Manager/staff Orders actions now POST asynchronously and refresh only the order
section, preserving the selected filter and scroll. Customer order detail and
My orders update in place every three seconds; detail checks the existing
ownership-protected revision endpoint before fetching changed content. Polling
pauses in hidden tabs and resumes on reconnect. Interrupted POSTs are never
resent automatically. Refreshed manager tables retain the existing mobile layout.

Focused verification: eight browser checks across separate manager/customer
sessions passed for Accepted, Preparing, Ready, cash confirmation, Completed,
filter retention, mobile table formatting and absence of page reloads or runtime
errors. PHP and JavaScript syntax checks passed. No database changes required.

## Order reliability and delivery coverage

Orders and Kitchen now use the same cash confirmation pop-up with the exact
amount. Opening or closing the pop-up does not record payment. Failed actions
show errors inside the active pop-up. Kitchen checks updates every three seconds,
blocks duplicate actions while saving, and times out after twelve seconds without
resending interrupted POSTs. Controls recover for the user to check order state.
Checkout shows fulfillment-specific guidance. Managers can edit delivery-area
notice under Settings; blank coverage asks customers to contact the shop first.
This notice is informational; staff confirm coverage before accepting delivery.

Verification: 14 focused browser checks passed, including simulated rejection,
slow connection/duplicate submission, actual isolated cash confirmation, saved
coverage, and switching checkout guidance. Existing 202 business assertions and
fresh schema plus repeated migration passed. Real orders were not used for tests.

## Product manager pop-up and manager review

Products & stock now has five columns: Product, Stock, Price, Status, Action.
Manage product opens a scrollable dialog with name/price editing, restocking,
availability and menu visibility. Product photo/category/description, stock
corrections and thresholds remain under collapsed advanced controls. Existing
CSRF-protected controllers and movement logging remain authoritative. Saves
refresh the server-rendered table and log without document navigation; failed
validation stays inside the dialog and keeps the entered values. A timeout never
automatically repeats a stock mutation. Hidden menu items and paused ordering
have separate labels. No database/schema changes were needed for this UI change.

Verification: 18 focused browser checks passed for 1440/390/320 layouts, product
name/price edits, restock, correction validation and recovery, availability,
visibility, and zero document navigations or browser errors. Optional image
upload uses the existing validated upload controller; no new upload test was run.

Remaining manager review priorities:
- Overview's Orders today links to all active orders, while Completed today links
  to all completed orders. The destinations should match the dates on the cards.
- Stock POST handlers cast quantities to integers rather than enforcing the
  form's integer/range limits server-side. Reject fractional/out-of-range values
  consistently before attempting a movement.

## Compact orders and View order window

Orders now defaults to Active orders, with History and All orders tabs. The list
has five columns: Order (readable date/time underneath), Customer/Type, Total,
Status/payment summary, and View order. Full item breakdown, fulfillment/address,
contact, notes, payment explanation, handler, and permitted actions appear inside
one dialog. Completed/cancelled orders retain View order instead of No actions.
History reads newest first and shows the latest 100; active work stays oldest
first. Existing status-specific URLs remain supported. Cash confirmation is
inline inside View order, with a Back button and no nested pop-up. Kitchen's
standalone cash confirmation remains supported. Server-side role, ownership,
payment, stock, refund, and transition rules remain in place.

Verification: 25 focused browser checks passed for default filters, five-column
rows, details, 1440/390/320 layout, in-place acceptance, cash confirmation/back,
completion leaving Active orders, History order, completed-order viewing, Escape
and restored scrolling. Existing 202 backend assertions passed; live source data
checksums unchanged. Preview: output/playwright/order-view-320.png.

## Manager overview, report controls and strict stock input

Overview cards now link to matching Today filters. Orders today uses placement
date; Completed today uses the terminal transition date, so an order placed
yesterday and completed today appears in the correct list. Today-filtered orders
show a date notice, preserve the date context through actions and allow returning
to all dates. Overview refreshes every three seconds and reuses the shared
Open/Close control with asynchronous feedback and a manager-only return target.
Scheduled opening hours and existing-order processing remain intact.

Report dates and presets are visible above the summary; detailed reports stay
collapsed. The top amount uses Net collections (payments minus recorded refunds),
matching Overview. Stock restock/corrections and minimum thresholds now reject
fractional, exponential, malformed and out-of-range quantities on the server.
Restock/correction quantities are limited to the form's ±999999 range, with the
existing positive-restock, nonzero and reason requirements; thresholds are 0–999999.

Verification: 23 focused browser checks passed, including an older order completed
today, list/count agreement, cross-page automatic Overview updates, Open/Close
without navigation, report date selection, invalid stock/threshold requests and a
valid restock, plus mobile layout. Audit check confirmed invalid requests wrote no
stock movements. Existing 202 backend assertions and 65 authentication/CSRF HTTP
checks passed. Tests used disposable business data; source checksums unchanged.

## Full screenshot and project cleanup audit — 21 October 2026

### Review scope and current evidence

528 existing PNGs were inventoried and screened through 22 contact sheets. Many
are historical iterations, contact sheets, or screenshots of compatibility URLs.
Seven groups are exact byte-for-byte duplicates. Earlier screenshots showing
large manager dashboards, expanded product tables, emoji icons, or separate
customer dashboards do not represent the current implementation.

34 fresh screenshots cover 17 main customer, staff, and manager views at 1440px
and 390px. The current gallery is
[Current screenshot review](../output/playwright/review-audit/index.html).
Full-size images and the old screenshot inventory are available from that gallery.
All capture requests reached the end of the capture run; this is a visual audit,
not another full end-to-end payment or email-delivery certification.

Screens use anonymized disposable data, deliberately disconnected email/payment
provider configurations, and no saved public URL. Their email setup warnings and
empty public-QR setup state are fixture conditions, not evidence the real sender
or public link is broken. No external emails, payments, or public deployment were
performed. No application source files or real business records were changed by
this audit.

### Suggested simplification, in priority order

| Priority | Area | Current finding | Proposed change |
|---|---|---|---|
| 1 | Customer order detail | Status appears in the callout, heading, timeline, and sidebar. Item totals also repeat in the sidebar. Mobile users scroll through the same information. | Put current status, next instruction, total and receipt action first. Put detailed history and support/cancellation guidance under disclosures; show each total breakdown once. |
| 1 | Cart | Mobile has a summary checkout button plus a floating checkout button. Add more items and Clear cart compete with the main task. | Keep one primary checkout action visible at a time: sticky on small screens when needed, summary button on desktop. Keep Clear cart as a quiet secondary control. |
| 1 | Order pop-up | At 320px, the customer/date/payment/handler fields appear before the action, pushing it below the fold. | Put next action and short status/payment summary near the top. Keep items visible; collapse full handler/history/contact metadata. Retain inline cash confirmation. |
| 1 | Product pop-up | Product name/price fields precede routine stock work, and the same window contains several forms. | Open with Stock. Collapse Product details and Advanced corrections; retain availability as a short section. Keep each Save scoped to its section rather than an ambiguous global save. |
| 2 | Mobile manager header | Pages, bell/count and a prominent Log out wrap into multiple rows. | Keep brand, navigation toggle, and bell on the first row; put Log out inside the navigation menu. Preserve accessible text and keyboard navigation. |
| 2 | Checkout | Optional contact for pickup and order notes, helper text and several instructional paragraphs make the page long. | Keep fulfillment/payment/total obvious. Group optional pickup fields and notes under Additional details. Always reveal and require contact/address for delivery. Remove repeated character-limit and next-step explanations. |
| 2 | Menu | The dark banner now contains address, fee, contact guidance, hours/status, CTA and 3D controls. | Preserve requested address/fee/status placement, shorten supporting copy, and keep the animated grill as one decorative feature. Avoid adding more animation or a second call to action. |
| 2 | Kitchen | Jump links, count badges and column headings repeat the same stages, especially on phones. | Keep the operational counts; make one compact mobile stage control rather than several rows of stage shortcuts. Do not hide New orders or payment state. |
| 2 | Reports | Five date presets plus From/To inputs take a full mobile card before the totals. | Put the totals first, with Today / Last 7 days / Custom range as the small date control. Preserve advanced report access and payment/refund distinctions. |
| 3 | General labels | Customer history still has View arrows, while manager tables now use View order. | Use View order consistently; use one button size, one dialog header/Close style and one spacing scale across the app. |

Keep the manager's five main navigation items, six-item menu, email verification,
stock history, truthful demo-payment labels, receipt/payment distinction, and
server-side order/payment guards. Removing these would reduce reliability rather
than user-facing complexity. No search, wishlist, promo codes, customer analytics
screen, rider accounts, or GPS features are needed for this demo.

### Cleanup inventory and recommended treatment

At the start of the audit the application contained 179 files (~2.0 MB), docs had
17 files, output occupied ~79.7 MB, and .tmp occupied ~49.8 MB. Sizes include
support/vendor files where present. Generated review evidence adds a small amount
to output; it is not part of the running application.

| Files / area | Evidence | Treatment |
|---|---|---|
| Old output/playwright screenshot sets | 528 saved PNGs in multiple historical galleries; 7 exact-duplicate groups. Example: consistency/customer-dashboard-1440.png is identical to consistency/customer-menu-1440.png. | Keep one current presentation gallery; archive or remove superseded sets after preserving any submission evidence. Screenshots do not run the website. |
| .tmp/pickup-ui-app and one-off check/capture/build scripts | Disposable app copy and many helper scripts used for repeated reviews. | Remove after review/test servers are stopped; move any reusable test into the maintained tests/tools directories first. |
| .playwright-cli snapshots/logs and temporary browser daemon files | Browser review output; not application dependencies. | Clear after closing test browsers, retaining only any failure evidence still needed. |
| .tmp SQL exports/backups | Includes pre-migration and fresh database exports. | Keep a verified latest backup in a deliberate private backup location before pruning old exports. Do not include sender/tunnel configs or database exports in a public demo package. |
| .tmp/bin/ngrok.exe and .tmp/public-demo | start-public-demo.ps1 explicitly uses these paths for ngrok, process IDs, logs and optional local tunnel configuration. ngrok.exe is about 31.7 MB of .tmp. | Keep while launchers use them. First relocate runtime dependencies and update/test launchers before considering a whole-folder cleanup. .tmp is not entirely disposable in the current setup. |
| assets/images/grill.svg and five non-BBQ product SVGs | No literal references in app/docs/SQL scan; live product records use the six *-render.webp paths. | Removal candidates after final reference verification. Keep grill-render.webp and current product renders. |
| assets/images/products/bbq.svg | Referenced by tests/demo-router.php, though not by live product images. | Update the router test to a retained asset before removing this sixth legacy product SVG. |
| docs/UI.md, UI-IMPROVEMENTS.md, VISUAL-REVIEW.md, UI-REVIEW.md | Overlapping UI histories and briefs. UI.md still says not to add delivery; ARCHITECTURE.md still describes pickup-only scope. | Consolidate current design rules into UI.md and review history into UI-REVIEW.md. Mark/archive earlier audits and update current architecture/workflow scope. Preserve useful setup, test and payment documentation. |
| customer/dashboard.php, staff/dashboard.php, manager/inventory.php | Redirect compatibility endpoints; reports still link to manager/inventory.php. | Keep redirects for now; update remaining internal links before retiring any endpoint. |
| customer/notifications.php and staff/notifications.php | notification-menu.php uses them as POST destinations. | Keep; they are active handlers even when their old full-page interfaces are no longer linked. |
| PayMongo adapter/config examples/setup/webhook/tests | Optional for the simulator demo, but bootstrap and checkout still call its helpers. | Optional coordinated retirement, not dead-file deletion. Keep until intentionally removing this integration and its callers/tests. |
| vendor, local Three.js, .agents, tests, schema/migrations, launch/setup/backup tools | Runtime, licenses, developer workflow, regression or setup dependencies. | Retain. File age, duplicate names such as .htaccess, and lack of a visible menu link do not imply they are unused. |

Reference checks are not a proof that every dynamically selected function or file
is dead. At the audit stage, no cleanup deletions had been made. Several apparent unused routes and CSS
selectors were checked and found active: .menu-toolbar is used by Customer
directory; .stage-links is used by Kitchen. They should not be removed just
because the customer menu no longer has a search toolbar.

### Recommended next implementation pass

Start with customer order-page deduplication, one mobile checkout CTA, shorter
order/product pop-ups, and a smaller manager mobile header. Then consolidate docs,
archive superseded screenshots, and prune verified obsolete assets/test helpers.
Keep the existing simple palette (paper/charcoal/ember), form controls, and manager
Overview as the visual reference instead of introducing another redesign.
