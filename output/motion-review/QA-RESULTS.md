# Animation and ordering fixes ? QA results

- 221 backend assertions passed, including six new hidden-category regression checks.
- Source database checksums unchanged after backend tests.
- 18 browser checks passed against a disposable app/database.
- PHP syntax checks and JavaScript parsing passed.

## Confirmed behavior

History with Today selected uses completion/cancellation dates, including orders placed yesterday and finished today. Its caption explains that date filter.

Hiding a category blocks direct cart additions, quantity changes and checkout of previously saved items. Unavailable lines are excluded from the payable subtotal. Reactivating the category restores ordering.

Order and product pop-ups fade in over 160ms; Escape still closes them. Successful cart updates highlight affected totals/quantities for 180ms. Customer instructions and kitchen cards highlight when the stage changes. Unchanged background polls do not flash cards. Accepting an order updates the customer page and manager dashboard without manual refresh.

View menu scrolls smoothly. Reduced-motion preferences disable the added animations and use immediate menu scrolling. Menu checked at 390px and 1440px without horizontal overflow.

Screenshots: [Mobile menu](menu-390.png), [Desktop menu](menu-1440.png), [Manager dashboard](dashboard-1440.png).

This pass used isolated orders and disabled external email/payment connections. Real email delivery and public hosting were outside this pass.
