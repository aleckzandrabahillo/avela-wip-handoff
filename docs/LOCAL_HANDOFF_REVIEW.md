# Receiving-laptop preparation — 31 October 2026

## Source and completed preparation

The current checkout is `C:\Users\Public\Project\Avela`, cloned from the owner's private `avela-wip-handoff` repository. Fetching `origin/main` confirmed source commit `2970c393b66eb8939f16aa45ba4e02cd27a25d44`; no newer main revision was missing at verification. The original `PHP_Group02_Project` and `avela_transfer` source/database installations were not overwritten.

The compatible private PHP runtime already on this laptop provides PHP 8.5.12 with `pdo_mysql`, `mbstring`, `fileinfo`, `gd`, `openssl`, `curl` and `proc_open`. Locked Composer dependencies were installed with plugins/scripts disabled, and the dependency audit returned no advisories or abandoned packages. Python 3.14 is available at `C:\Python314\python.exe`. No extra XAMPP installation is needed merely to duplicate those tools. Destination database credentials/port will be configured against the actual intended local server rather than assuming XAMPP port 3306.

Windows checkout line endings changed the initial catalog's bytes and broke the suitability evidence's existing catalog checksum. Normalizing the catalog JSON to LF restored the reviewed hash; `/database/*.json text eol=lf` now preserves those bytes. The checksum requirement was not disabled. The JSON changes only normalize line endings; catalog content, stock and prices were not edited.

Checks completed:

- All 299 first-party PHP files and 23 browser JavaScript files passed syntax checks.
- Product matching 29, routine guide 29, hair validation 36, delivery/payment validation 106 and PII primitive checks 30 passed: 230 focused checks. Payment transport was simulated; these checks send no real payments.
- Six concern-interpreter Python unittest methods passed.
- Three new catalog-source unittest methods passed, including manifest identity, reviewed raw hash and all 213 approved image SHA-256 values.
- Git whitespace checks passed; `.env` remains ignored. There is no configured TypeScript project, PHP static-analysis pipeline or frontend production-build script to report as passing.

## Required private package is not here yet

At preparation time, the encrypted archive, extraction helper and separately transferred key were absent from the project and the checked user transfer/download folders. The owner confirmed they are probably still on the main laptop. The code, catalog manifests and images do not establish that its accounts/orders/35-table database have been restored.

The main-laptop guide identifies this existing package directory:

```text
C:\Users\zandr\.codex\visualizations\2026\10\24\01a16d48-b2b8-7103-be6b-e66929e44897\device-handoff-20261031
```

The receiving folders have been created with current-user/SYSTEM access. Transfer these privately:

| Source package member | Receiving location |
| --- | --- |
| `private/avela-private-transfer.avelaenc` | `C:\Users\marvin cardona\Avela-transfer\avela-private-transfer.avelaenc` |
| `unlock-transfer.py` | `C:\Users\marvin cardona\Avela-transfer\unlock-transfer.py` |
| `private/avela-transfer-key.private.json` | `C:\Users\marvin cardona\Avela-transfer-key\avela-transfer-key.private.json` |

Transfer the key separately from the archive. Do not paste secrets or upload the private material to GitHub/chat. Ask the source operator to confirm paths, SHA-256 checksums and that the authenticated package matches the intended source/data snapshot. Preserve original MFA/PII keys; do not generate replacements for its encrypted data.

## Resume after arrival

Inspect the helper, authenticate extraction into a new protected directory, verify archived file hashes, then restore `database.sql` into a new empty database with a dedicated application account. Compare restored table counts and verify the expected 213 active products. Keep existing databases untouched. Do not run the installer on restored data.

Copy the extracted private configuration/uploads into their intended locations; retain its original database encryption keys. Adapt database connection, private keyring/outbox paths, Python and Defender paths, local session settings and origin. Keep Maya local experiments/simulation/readiness disabled until their documented separate acceptance. Follow `DEVICE_TRANSFER.md`, `PII_ENCRYPTION.md`, `MFA.md`, `EMAIL.md` and `UPLOAD_SCANNING.md` rather than importing another checkout's runtime bypasses or sessions.

After readiness and read-only decryption checks, start the PHP development server with `public/` as root at `http://localhost:18080/`, verify HTTP/browser rendering and private-path denial, and exercise important workflows using a separately guarded synthetic database. Real provider delivery, upload scanning and deployment acceptance require their own evidence. Do not weaken authenticator enrollment or encryption to complete the restore.

**Current limitation:** no owner-data restore, runtime/browser workflow verification or live local URL for this checkout is claimed yet. This task prepared and checked source/dependencies while awaiting the private handoff. Public deployment remains a later step after local review.
