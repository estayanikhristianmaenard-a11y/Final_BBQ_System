# Manager hours override QA

251 backend assertions passed, including 11 new hours-override checks. Source database checksums unchanged.

65 HTTP checks and 15 browser checks passed. Changed PHP files and JavaScript syntax passed.

Verified outside a configured opening window: manager Open allows checkout and reserves stock; Close blocks new orders immediately; existing orders remain accessible; Use automatic hours restores scheduled closure. Configured hours and order limits remain unchanged. Staff Open continues to follow the schedule. Customer menu and API agree with the effective ordering state. Dashboard actions update without navigation. Mobile 390px and desktop 1440px fit without horizontal overflow.

Manual overrides remain until changed, automatic hours are resumed, or Store settings are saved. No database schema upgrade is required: the override uses the existing settings table. All test actions ran against a disposable database.

[Mobile dashboard](dashboard-390.png) | [Desktop dashboard](dashboard-1440.png)
