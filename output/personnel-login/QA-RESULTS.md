# Personnel password sign-in QA

- 240 backend assertions passed, including 19 personnel authentication cases.
- Source database checksums remained unchanged.
- 65 HTTP checks passed.
- 16 browser checks passed, including manager and staff password sign-in, wrong passwords, CSRF, account creation, manager password reset, recovery code verification, password confirmation, and customer email sign-in.
- 12 existing role/destination checks passed; all 10 changed PHP files passed syntax checks.
- Sign-in fits mobile (390px) and desktop (1440px) without horizontal overflow.

[Mobile sign-in](login-390.png) | [Desktop sign-in](login-1440.png)

Existing accounts need a password set once through Set or reset password, or by a manager under Staff accounts. No default password was assigned. Subsequent personnel sign-ins use email and password; customer checkout remains email-verified.

Password recovery was tested with a disposable OTP record and test sessions. No real email was sent in this pass. All test accounts/orders were in a disposable database.
