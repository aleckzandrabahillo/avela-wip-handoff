# Restored local Avela — 31 October 2026

Avela is running at **http://localhost:18080/** from `C:\Users\Public\Project\Avela`. This is the current `avela-wip-handoff` source, initially verified at published commit `2970c393b66eb8939f16aa45ba4e02cd27a25d44`, with local portability/setup fixes on `codex/device-local-setup`. No changes were pushed. The older source trees and their databases remain separate.

## Authenticated restoration

The three downloaded files were the expected encrypted archive, extraction helper and key. The helper was inspected before execution. Authenticated AES-GCM extraction succeeded and all **144 archived file hashes** were verified. The extracted SQL was checked for database-switching, privilege/global and other out-of-scope statements before import.

The backup was imported into a **new** database, `avela_device_20261031`, on the pre-existing strictly identified loopback MySQL server at port **13331**. All **35 table names and row counts** matched the source dump, with **213 active products**. A dedicated application user has only SELECT, INSERT, UPDATE and DELETE on this new schema. No installer/reset, owner-account changes or schema migration was run against restored data.

The original `.env`, PII keyring and five uploaded files were restored privately. Original MFA/PII keys, SMTP credentials and CAPTCHA settings were retained; database credentials, local origin, Python/scanner/keyring/outbox paths and session settings were adapted. Read-only checks successfully decrypted protected profile/order/address-book values and the existing enrolled authenticator. There is one enrolled authenticator and 13 encrypted address-book rows in this snapshot. Existing passwords and enrollment settings remain authoritative; no sessions were copied and MFA was not bypassed.

Private configuration, credentials, decrypted SQL, backup keys, uploads, sessions and logs are ignored or stored outside the repository/public root. Owner/SYSTEM-only ACLs protect the private runtime and `.env`. PHP temporary/session directories use their physical Windows path: Codex app-folder aliases were unsuitable for the external Defender service. The old-machine reference workers/configuration were not executed.

## Run after restart

From this project directory in PowerShell:

```powershell
.\scripts\start_local.ps1
```

Keep its terminal open. The launcher reuses the identified existing MySQL runtime, checks the exact new database/server/data-directory identity, runs read-only readiness checks, refuses unrelated listeners and starts PHP with only `public/` served. It starts no email/payment worker, tunnel or scheduler. `scripts/healthcheck.php` reports the database connection; `scripts/check_device.php` adds boolean schema/key/identity checks and prints no secrets. This machine's PHP need not be on PATH; the launcher reads its private runtime pointer.

## Portability fixes and verification

- Composer installation used the lockfile with plugins/scripts disabled. The audit reported no advisories or abandoned packages. Required PHP extensions and `proc_open` are available.
- New catalog JSON must retain LF bytes to match its reviewed evidence; the legacy `demo_catalog.json` instead requires its original CRLF bytes. Explicit Git attributes preserve each format. Both hashes remain enforced. An initially overbroad LF rule was corrected after the isolated workflow test exposed the legacy mismatch.
- Three schema inspectors now normalize deprecated BIGINT/TINYINT display widths for MySQL 8/MariaDB compatibility. Types, signedness, nullability, defaults and existing constraint checks remain enforced. No table definition or stored row was changed. Fourteen positive/negative type checks pass.
- All **302 first-party PHP files**, **23 browser JavaScript files** and the PowerShell launcher passed syntax checks. The five earlier pure suites passed 230 checks; with the 14 type checks and **32 guarded HTTP/service workflow checks**, there are 276 focused passing checks. Four catalog-source and six concern-interpreter Python unittest methods passed. No TypeScript/static-analysis/frontend-build pipeline is configured.
- The 32 workflow checks ran in a newly initialized, separate synthetic-only MySQL instance/schema on port 13427 and private HTTP server on 18430. They covered multiple owned addresses, encryption, guest/CSRF/ownership/stale rejection, default/delete/legacy behavior, audit-failure rollback, activation/session rotation/replay rejection, assessment/routine/recommendation, product and asynchronous bag behavior. That fixture used independent keys and log mail, never restored owner data. Its servers were stopped afterward; its private fixture files were retained.
- SMTP TLS authentication succeeded without sending a message. Real Python concern interpretation succeeded. A Microsoft-signed native Defender executable passed a real clean-file scan; antivirus service and real-time protection were active. Upload scanning remains enabled and fail-closed. Harmless malware-detection and full multipart upload acceptance remain separate checks.
- All 213 active product image paths resolve. Browser inspection showed **213 products**, all 14 current-page images loaded and no horizontal overflow at the existing viewport. HTTP home/shop/login/register returned 200, guest staff routes redirected to login, and configuration/Git/runtime/log/schema/script paths returned 404.
- The restored business-table row counts remained equal to the backup during verification. Guest-access checks generated one normal authentication-log row and one rate-limit row; these security records were retained rather than erased. No account, address, assessment, stock or order was edited for acceptance testing.

## Boundaries before deployment

Maya Sandbox/local experiment/simulation/webhook/scheduler flags remain **false**, as required by the handoff. COD is independent; a new device-specific Maya acceptance check is needed before online-payment testing. No new payment, outbound email message, public webhook, scheduler or Internet hosting was created. SMTP authentication is not new Inbox-receipt evidence.

The owner should review normal account/authenticator sign-in and their retained orders/profiles here. Before public deployment, choose hosting, verify maintained runtimes, HTTPS/secure cookies and public-only document root, persistent private keys/uploads/database storage, recovery-tested backups, staff MFA coverage, provider acceptance, production CAPTCHA and genuine upload-scanner acceptance. Never ship private transfer files, development sessions/outboxes or machine-bound pointers. Keep the source laptop and its original keys intact until review is complete.
