# Short defense deployment

The owner requested an independent cloud deployment, capped at US$5 total for the defense and suspended no later than 24 hours after paid activation. This is not a permanent US$5/month hosting promise.

## Proposed resources and cost boundary

- Render Hobby workspace (no workspace subscription), Singapore Docker web service, one 4 GB/2 CPU instance while antivirus is enabled. Render currently lists this compute class at US$85/month, prorated to the second: approximately US$2.74–2.83 for 24 hours before tax.
- One 1 GB persistent disk at `/data`, currently US$0.25/GB/month, also prorated. Suspended compute does not remove retained storage charges; retained files must be backed up/reviewed before any later disk deletion.
- Aiven Free MySQL 8.4, 1 GB storage/1 GB RAM, created in its Asia Pacific tier. The assigned DigitalOcean region is Bangalore; Singapore is the closest available Render region. The free tier does not allow exact-region selection and can power off after inactivity.
- A separate shutdown controller must suspend the paid service within 24 hours even with the laptop off. Render scheduling/API access must be verified before paid activation. If a Render cron controller is used, its US$1/month minimum counts toward the US$5 budget; keep its token outside the web service and stop its recurring work after suspension. Taxes/bandwidth must fit the remaining headroom.

Sources checked on 31 October 2026: [Render pricing](https://render.com/pricing), [billing proration](https://render.com/articles/how-render-handles-scheduled-tasks), [Aiven free limits](https://aiven.io/docs/products/mysql/concepts/mysql-free-tier), [ClamAV memory requirements](https://docs.clamav.net/manual/Installing/Docker.html). Final account pricing, build, measured memory and acceptance still require confirmation.

## Build and persistence

The Dockerfile installs locked Composer dependencies with plugins/scripts disabled, PHP/Apache/GD/cURL/OPcache, Python and ClamAV. Six bounded Apache workers limit memory/connections. The container requires a separate mounted `/data` volume; it never installs schemas or mutates accounts at startup. Only `public/` is served. Uploaded paths are static data with execution disabled and allowlisted random image filenames.

`/data` holds sessions, private temporary files, logs, uploads, original PII keys, database CA and cached antivirus definitions. Sensitive directories are service-owned with restrictive permissions. The database has a separate DML-only application user. External database connections require the supplied CA and hostname/certificate verification; there is no permissive TLS fallback.

Private host variables supply the existing MFA/SMTP/CAPTCHA settings plus `PII_KEYRING_BASE64` and `DB_SSL_CA_BASE64`. The materializer creates secured external files and refuses to overwrite differing retained material. The initial uploaded image snapshot is supplied privately in bounded `UPLOAD_SEED_###` chunks with hashes, never committed. Seeding only adds absent allowlisted files after a real scan and refuses conflicting existing files.

ClamAV's official definition image seeds the cache; freshclam maintains it and clamd handles bounded scans over a local Unix socket. The native Windows Defender path remains unchanged locally. Missing scanner, nonzero scan result, timeout and invalid files remain failures, never successful uploads.

Production configuration rejects HTTP/untrusted origins, insecure cookies, log mail, local staff OTP exceptions, demo CAPTCHA keys, missing keys/TLS, disabled scanners and unverified Maya mode. The public health endpoint reports only readiness, checking database/address schema, keyring and scanner PING. It exposes no credentials or records. Reverse-proxy headers do not bypass payment peer/TLS checks.

## Release sequence

1. Verify connected workspace/repository access and budget/shutdown capability. Prepare and scan a private Git branch; never upload `.env`, dumps, keys, sessions, logs or owner uploads in the build.
2. Make a fresh consistent private snapshot of the verified local database. Restore into a newly empty cloud schema over verified TLS. Match every table count; preserve local data. Create an application user with only SELECT/INSERT/UPDATE/DELETE on that schema.
3. Build without activating paid resources where possible. Configure the volume, private variables, actual public APP_URL and approved reCAPTCHA hostname. Verify the original keyring/MFA data reads and imported uploads.
4. Establish and test the independent stop controller; record activation/deadline in UTC and Asia/Manila. Check the actual prorated price and any workspace subscription before activation.
5. Verify HTTPS, secure cookies, private-path denial, scanner clean/rejection behavior, database TLS, SMTP/OTP/activation delivery, address/assessment/routine/bag/checkout flows with synthetic data, and measured latency. A build alone does not establish these results.
6. Publish the working link only after acceptance. Keep Maya disabled until its provider/gateway/worker boundary is separately verified. Keep pending/uncertain historical mail and payment queues from being bulk-dispatched.

The release branch is `codex/render-defense`. The local `http://localhost:18080` instance remains intact as a fallback. The final public address is **https://avela.onrender.com/**. Render retained the first service's original subdomain when renamed, so the clean hostname uses a replacement service with the same cloud database. The first paid service was suspended before the replacement was activated; its disk is retained as a recovery copy.

## Independent stop controller

`deploy/stop-defense.py` runs in a separate native Python cron service named `avela-defense-auto-stop`, every five minutes. It accepts only the fixed Render HTTPS API, refuses redirects, verifies exact workspace/service/name/type identities, and suspends the web service once `STOP_DEADLINE_UTC` arrives. It confirms the web suspension before suspending its own recurring job. Provider failures retain the controller for retries. Configure the deadline before paid activation, with at least one hour of margin inside the owner's 24-hour maximum; scheduler/API outages still require operator attention.

The private controller environment contains `STOP_RENDER_API_KEY`, `STOP_WORKSPACE_ID`, `STOP_WEB_ID`, `STOP_WEB_NAME`, `STOP_CONTROLLER_ID` and `STOP_DEADLINE_UTC`. None belong in the web service. This account currently offers an account-wide API key; the owner creates it explicitly, it stays outside Git/build images, and the owner must revoke it after confirmed shutdown. Run `python deploy/stop-defense.py --audit-only` to verify identities without suspending either resource, and test real suspension against the free preview before upgrading. The controller's minimum charge and residual disk storage count toward the budget.

Read-only cloud verification on 31 October matched all 35 table counts, decrypted all 13 address-book records and the one enrolled authenticator, and confirmed verified TLS plus DML-only application grants. No original local rows/accounts were changed by this check.

## Verified cloud behavior and limits

The paid instance is one `2c-4g` Singapore web service with a 1 GB persistent `/data` disk. The separate controller successfully suspended the free preview and itself in a real cloud test, then was resumed and verified with the future deadline. The fixed deadline is `2026-10-31T20:20:00Z` (1 November, 04:20 Asia/Manila), with five-minute checks and over an hour of margin inside the owner's 24-hour maximum. For an explicitly approved rename, at most two exact names can temporarily be listed in `STOP_WEB_NAME`; service ID, workspace and type checks remain mandatory. Narrow this back to the final exact name after the change.

The runtime check ran as `www-data`: database certificate/hostname verification passed; all 13 saved addresses and the one enrolled authenticator decrypted; a clean PNG passed ClamAV; the standard harmless antivirus test was detected with scanner exit 1 and rejected by the upload service. SMTP authentication passed and one sender-only diagnostic was accepted. The owner confirmed receipt. No account proofs were generated/consumed, business records changed or old mail queues dispatched.

Public HTTP checks passed for home, shop, sign-in, registration, product detail and care guide. Seven private paths returned 403/404, HTTP redirected to HTTPS, session cookies carried Secure/HttpOnly/SameSite=Lax, and the readiness endpoint returned true while rejecting POST with 405. The browser displayed all 213 catalog products and a valid reCAPTCHA v2 checkbox without console errors. Three warm catalog requests had a median response time of 0.834 seconds from the setup device; this is a small smoke check, not a concurrency/load guarantee.

Aiven's default SQL mode includes ANSI quoting. `DatabaseSqlMode` removes the ANSI alias and ANSI_QUOTES for each application connection while retaining all other expanded flags, including STRICT_ALL_TABLES, ONLY_FULL_GROUP_BY, zero-date checks and division-by-zero handling. This does not change server/global settings or existing records. Ten pure checks and a real Aiven application/schema connection check passed; the local device check still passes with 213 active products.

Database network access is restricted to Render's two documented Singapore outbound ranges and the setup device's individual addresses. Those Render ranges are shared infrastructure; strong credentials, DML-only application privileges and verified TLS remain required. The Linux image explicitly installs Debian's separate `clamdscan` client. A temporary isolated maintenance document root was used to provision paid compute before disk attachment; the final command is the normal guarded application startup and readiness path is `/health.php`.

Maya remains disabled pending its separate public gateway, source-verification and worker acceptance. Authenticated account/order mutations were not exercised against the retained owner database. Their existing isolated local workflow evidence is separate from these cloud smoke checks. There is no TypeScript/static-analysis/frontend build pipeline; the actual Docker/locked Composer production build succeeded. Retained disk storage continues at the provider's storage rate after suspension, and the owner should revoke the dedicated controller API key after confirmed shutdown. Keep a backup before any later destructive cleanup.

## Final clean-host handoff

- Active service: `avela`, `srv-dbih6b8n74is739rs45g`, Singapore, one 4 GB/2 CPU instance. Its normal application deployment `8d921266ef949fa4df898f73048bf7d0941b460f` passed readiness after removing all temporary upload-import chunks and setting `UPLOAD_SEED_PARTS=0`. This avoids importing the original photos again on a future restart after a user intentionally replaces/removes them.
- Persistent disk: `dsk-dbih8rkipgvc73bipipg`, 1 GB at `/data`. Both retained uploaded profile-image hashes matched the transfer snapshot before cutover. They, the original keys and the database remained readable after the final restart.
- Original service: `srv-dbigjit9fdbs73djcnp0`, suspended with its original 1 GB disk retained. Total retained disk allocation is therefore 2 GB, currently US$0.50/month even after compute suspension. Do not resume this superseded service or delete either retained disk without a reviewed purpose/backup and the owner's authorization.
- The cloud stop job now checks the exact replacement service ID/name `avela`. It passed a real cloud identity/deadline check after retargeting. Its UTC deadline remains `2026-10-31T20:20:00Z`; the dedicated API key is absent from web-service variables.
- All six public-page checks, seven private-path denials, HTTPS redirect, secure cookie attributes and health-method checks passed again at the final hostname. Three warm shop requests had a median response time of **0.865 seconds**. Browser catalog rendering showed 213 products with no console errors. The owner added the final hostname to the original CAPTCHA key.
- The acceptance command ran again as the replacement container's `www-data` identity after restart: verified database TLS, 13 address decryptions, one authenticator decryption, clean-image scan, antivirus-test rejection and SMTP authentication all passed. It sent no second diagnostic email. Composer's locked production dependency audit returned no advisories or abandoned packages.
- After Google's domain update propagated, the final hostname displayed the real reCAPTCHA checkbox. The original console key matched the deployed site key, the exact hostname was saved, and origin validation remained enabled. No CAPTCHA was solved or bypassed by the acceptance check.

The approximate total through the fixed cutoff remains below the owner's US$5 defense budget (roughly US$3.7 before tax, including the first service's short use, both prorated disks and the US$1 cron minimum). Retained storage after the defense is a separate continuing charge; review a backup and remove unused storage through an explicitly authorized cleanup. The monthly US$85 compute list price is not a promise of a permanent sub-US$5/month deployment. Check the actual provider invoice and retained-resource state after the scheduled stop.
