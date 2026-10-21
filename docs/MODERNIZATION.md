# BulkBites BBQ modernization research (historical proposal)

Status: the Laravel proposal below is superseded by the user's choice to keep
HTML, CSS, JavaScript, PHP, MySQL/MariaDB, and XAMPP. Use
[WORKFLOW.md](WORKFLOW.md) for the current implementation plan, including ngrok
QR access and PayMongo test payments before staff acceptance. The installed
skills and their provenance listed below remain applicable.
Reviewed: 2026-10-18 (Asia/Manila).

## Recommended direction

Use a single Laravel application with Livewire, Blade, Tailwind CSS, and the
existing MySQL/MariaDB data model. This fits the current PHP implementation and
the customer ordering, kitchen, inventory, and management workflows. It is an
engineering recommendation for this project, not a claim that one stack is
universally best.

| Area | Proposed choice | Purpose |
| --- | --- | --- |
| Application | Laravel 13 with a compatible supported PHP runtime | Routing, validation, authorization, migrations, mail, and shared business services |
| Interface | Livewire 4, Blade, Tailwind CSS; optional free Flux components | Interactive customer, kitchen, and manager screens with reusable components |
| Assets | Vite and Node.js tooling | Build and optimize application assets |
| Database | Retain MySQL/MariaDB; verify target server compatibility | Preserve relational orders, stock, receipts, and audit records |
| Authentication | Adapt existing email OTP behavior to Laravel sessions and authorization | Preserve passwordless login and manager-controlled personnel access |
| Email | Laravel mail and a database-backed queue initially | Process receipt delivery independently of staff browser polling |
| Order updates | Polling initially; consider Laravel Reverb after deployment is chosen | Keep kitchen and customer status current |
| Validation | Laravel feature tests and Playwright browser checks | Verify stock, permissions, order transitions, receipts, and mobile workflows |
| Automation | GitHub Actions once repeatable local checks exist | Run project checks on proposed changes |

The starter kit's default authentication is not a replacement for our email OTP
requirements. Preserve expiration, single-use codes, attempt limits, throttling,
staff invitations, and role separation when adapting it.

Laravel 13 requires PHP 8.3 or later. A local XAMPP PHP executable found during
inspection reports PHP 8.2.12, and PHP/Composer were not found on the current
shell PATH. A compatible development runtime and Composer must be configured
before scaffolding. Database server compatibility must also be checked directly;
the bundled snapshot's MariaDB version does not establish the installed version.

## Alternatives

| Option | When it fits | Tradeoff |
| --- | --- | --- |
| Improve current plain PHP | A rubric requires plain PHP/XAMPP, or a short deadline rules out migration | Less migration work; more application structure and tooling remain custom |
| Laravel + Livewire | Recommended for the current shop workflows and PHP codebase | Requires framework migration and a newer development runtime |
| Laravel + Inertia + React/TypeScript | Team already knows React or the interface needs substantial browser-side state | Adds frontend concepts, types, and components to maintain |
| Node.js + NestJS + a selected frontend | Team wants JavaScript/TypeScript on the server and accepts rewriting the PHP backend | Business logic must be reimplemented and verified; database records can still be migrated |

Laravel is a PHP web framework. Node.js is a JavaScript runtime, so a closer
framework comparison is Laravel versus NestJS or Express running on Node.js.
Either approach supports an online application shared by customers, staff, and
managers. Node.js does not automatically make stock transactions, permissions,
or kitchen updates more reliable; those require sound implementation in either
stack. Live updates are possible in both ecosystems.

For the chosen online deployment, use one authoritative hosted database shared
through the application backend, HTTPS, and supervised background processing for
email delivery. Select a host after confirming the runtime, database, worker,
and any WebSocket requirements. Hosting costs and credentials are not configured.

## Delivery sequence

1. Establish a baseline: run the current system against an isolated database,
   record working workflows and confirmed defects, and verify backup restoration.
2. Set up the compatible Laravel runtime and a separate application/database for
   development. Define how existing record IDs, order numbers, item price
   snapshots, and audit history will be transferred.
3. Port authentication and permissions, preserving email OTP behavior. Verify
   customer/staff/manager URL access and account deactivation.
4. Redesign the customer menu, cart, checkout, order tracking, and receipts with
   real product content, clear availability, and mobile layouts.
5. Port checkout and inventory rules. Keep atomic deductions, stock locking,
   duplicate-submit protection, cancellation handling, and order history. Use
   integer centavos or a decimal-safe money strategy in application calculations.
6. Improve the kitchen queue and notifications, including reconnect behavior,
   visible failed actions, and explicit stock/status updates.
7. Improve manager catalog, stock movements, personnel management, reports, and
   email delivery visibility. Reconcile report totals with underlying orders.
8. Validate restoration and migration on a copy, complete browser and business
   rule checks, and prepare deployment and rollback instructions.

Release checks should cover two customers ordering the last available stock,
replayed checkout submissions, unauthorized role access, failed receipt email,
cancelled order stock handling, historical receipt accuracy, report reconciliation,
and phone-sized screens.

## Decisions still needed

- Does a school/client require plain PHP and XAMPP, or permit Laravel?
- Does the team prefer maintaining PHP/Laravel or learning a JavaScript/TypeScript backend?

Confirmed: deployment should be online and accessible from multiple devices.

The recommendation assumes frameworks are allowed. The skill installation is
useful with either implementation. No framework migration or database changes
were performed as part of this proposal.

## Installed GitHub skills

Skills are stored in `.agents/skills/`, which Codex supports for repository-local
discovery. They become available on the next turn; restart the session if they
do not appear. Commit this directory to share the skills through GitHub.

| Skill | Upstream | Pinned revision | Intended use |
| --- | --- | --- | --- |
| frontend-design | [anthropics/skills](https://github.com/anthropics/skills/tree/dbd4588f9e1033efb41dad4bef2f7947c8993d44/skills/frontend-design) | `dbd4588f9e1033efb41dad4bef2f7947c8993d44` | Visual direction, typography, responsive layouts, and clear interface language |
| playwright | [openai/skills](https://github.com/openai/skills/tree/49f948faa9258a0c61caceaf225e179651397431/skills/.curated/playwright) | `49f948faa9258a0c61caceaf225e179651397431` | Real-browser workflow checks, screenshots, and UI debugging |

Both were installed using the built-in skill-installer helper. Upstream license
files are included. Installation does not install Playwright's browser binaries
or prove that application workflows pass. The Playwright wrapper requires Bash
and npm/npx; verify these in the execution environment before running it, or use
the upstream-supported CLI directly where available. No browser dependencies
were installed in this step.

Skills supply repeatable instructions and tools. Their value comes from applying
them to concrete changes and validating the results.

## Primary references

- [Laravel starter kits](https://laravel.com/docs/13.x/starter-kits)
- [Laravel runtime and deployment requirements](https://laravel.com/docs/13.x/deployment)
- [Laravel database support](https://laravel.com/docs/13.x/database)
- [Laravel queues](https://laravel.com/docs/13.x/queues)
- [Laravel testing](https://laravel.com/docs/13.x/testing)
- [Laravel Reverb](https://laravel.com/docs/13.x/reverb)
- [Official skill discovery documentation](https://learn.chatgpt.com/docs/build-skills)
- [Introduction to Node.js](https://nodejs.org/en/learn/getting-started/introduction-to-nodejs)
- [NestJS documentation](https://docs.nestjs.com/)
