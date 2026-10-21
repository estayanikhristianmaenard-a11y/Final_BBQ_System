# Public demo setup

Run `bulkbites-bbq/SETUP-DEMO.bat` on the Windows server PC for the guided
email and public-link setup. Payments use the built-in [simulator](PAYMENT-DEMO.md),
with no merchant account or API keys. It needs an existing local database
and XAMPP PHP/MySQL. For another PC, follow the database setup in the README first.

The individual launchers can also be used independently:

| Launcher | Purpose |
| --- | --- |
| `SETUP-EMAIL.bat` | Save a sender email and Google App Password through local prompts. |
| `START-PUBLIC-DEMO.bat` | Start/reuse MySQL, the restricted PHP server on loopback port 8080, and an ngrok HTTPS tunnel; save its URL. |
| `SETUP-PAYMONGO.bat` | Optional future PayMongo TEST connection; not required for the demo simulator. |
| `STOP-PUBLIC-DEMO.bat` | Stop the project's recorded ngrok process; leave MySQL and PHP running. |

The public launcher reuses an authenticated ngrok installation when available.
Otherwise it downloads the official Windows agent, verifies its Authenticode
signature, and asks for the ngrok authtoken locally. It refuses to reuse an
unrelated service on port 8080 or an unrelated tunnel on the agent API port.
Tunnel request inspection is disabled. Keep the PC awake and connected, with
MySQL and the tunnel running for the presentation.

Open **Manager > Ordering QR** after signing in to download a PNG or print the
ordering QR. This QR opens the customer menu. It is separate from payment and
does not confirm that an order is paid. Public browsing and the cart work before
email sign-in; checkout requires verification.

## Credentials and account checks

Enter passwords, API keys, and authtokens only into the local setup prompts.
The mail, payment, and demo configuration files are ignored by Git. The public
router blocks configuration, SQL snapshots, tools, tests, and private includes.
It serves this application rather than XAMPP's full document root.

`SETUP-PAYMONGO.bat` accepts `sk_test_...` keys only. It looks for an existing
TEST webhook for the saved URL before creating one, then verifies its mode,
event, enabled status, and signing secret before saving configuration. A timeout
does not report success; rerunning first checks for the previously created
endpoint. Setup creates no checkout or payment. Account access and payment
method availability still need verification in the provider dashboard.

If the public URL changes, rerun the public launcher and download a fresh QR.
The simulator does not need a webhook. If using the optional provider integration,
rerun PayMongo setup too. Do not replace a saved merchant key while orders may still
be awaiting payment. Resolve pending payments before changing accounts.

Check configuration without revealing credentials:

```powershell
& C:/xampp/php/php.exe bulkbites-bbq/tools/setup-demo.php
```

Configured status is not proof of email delivery or a successful provider payment.
Use the [presentation checklist](DEMO-CHECKLIST.md) to verify those steps.

## QR dependency

QR generation runs locally in the browser, with no remote image or CDN request.
Vendored `qrcode-generator` version **1.4.4**, by Kazuhiko Arase, comes from the
[published npm package](https://www.npmjs.com/package/qrcode-generator/v/1.4.4)
and uses the MIT license included alongside the scripts in
`assets/vendor/qrcode-generator/LICENSE`. The UTF-8 plugin is included.
`qrcode.js` SHA-256:
`18ae399f81182bc9de916e9c77b195df20cc58d6f2d55a62b085a299f1bf1780`.
The output uses error correction H and a four-module white quiet zone.

## Verification on 2026-10-20

- 160 isolated ordering/payment/store/simulator assertions passed; source database checksums unchanged.
- 53 HTTP access checks and 18 restricted-router checks passed.
- 24 local setup assertions passed, including duplicate-webhook recovery and live-key rejection.
- 78 email/receipt checks passed against a local TLS SMTP fixture; no external email sent.
- Public HTTPS menu and secure session cookie verified through the running tunnel.
- Launcher restart reused the existing server/tunnel successfully.
- Downloaded 530 x 530 PNG decoded independently to the saved public menu URL.
- QR page visually checked at 1440px and 390px widths; no mobile horizontal overflow.
- Gmail sender settings are configured; verify real inbox delivery before presenting.
- The local payment simulator is ready; no external PayMongo transaction is required.

References: [ngrok Windows agent](https://ngrok.com/download/windows?tab=download),
[PayMongo webhook registration](https://docs.paymongo.com/docs/creating-a-webhook-endpoint),
[webhook fields](https://docs.paymongo.com/reference/webhook-resource).
