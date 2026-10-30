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

Current preparation is on `codex/render-defense`. The local `http://localhost:18080` instance remains the fallback. Container/cloud acceptance is pending until the actual deployment succeeds.

## Independent stop controller

`deploy/stop-defense.py` runs in a separate native Python cron service named `avela-defense-auto-stop`, every five minutes. It accepts only the fixed Render HTTPS API, refuses redirects, verifies exact workspace/service/name/type identities, and suspends the web service once `STOP_DEADLINE_UTC` arrives. It confirms the web suspension before suspending its own recurring job. Provider failures retain the controller for retries. Configure the deadline before paid activation, with at least one hour of margin inside the owner's 24-hour maximum; scheduler/API outages still require operator attention.

The private controller environment contains `STOP_RENDER_API_KEY`, `STOP_WORKSPACE_ID`, `STOP_WEB_ID`, `STOP_WEB_NAME`, `STOP_CONTROLLER_ID` and `STOP_DEADLINE_UTC`. None belong in the web service. This account currently offers an account-wide API key; the owner creates it explicitly, it stays outside Git/build images, and the owner must revoke it after confirmed shutdown. Run `python deploy/stop-defense.py --audit-only` to verify identities without suspending either resource, and test real suspension against the free preview before upgrading. The controller's minimum charge and residual disk storage count toward the budget.

Read-only cloud verification on 31 October matched all 35 table counts, decrypted all 13 address-book records and the one enrolled authenticator, and confirmed verified TLS plus DML-only application grants. No original local rows/accounts were changed by this check.
