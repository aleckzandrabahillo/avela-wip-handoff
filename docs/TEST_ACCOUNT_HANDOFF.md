# Owner-requested test accounts

On 31 October 2026, the owner requested three fresh test accounts in the retained localhost and deployed databases and explicitly chose to preserve history and deactivate old users. Complete private SQL backups were verified before the operation. This is authorized account administration, not a database reset or evidence that retained data is disposable.

The active usernames are `zandrabahillo` (Customer), `avela_catalog` (Catalog Manager) and `avela_admin` (Administrator). Passwords and private provisioning material are deliberately excluded from this document and Git. The same owner-supplied credentials and phone number were checked in both environments using current password hashing and profile encryption. Customer activation was explicitly owner-provisioned and audited. The existing documented authenticator-enrollment transition remains unchanged; registration and sensitive-change proofs are unaffected for new users.

Eleven prior localhost accounts and twelve prior cloud accounts were deactivated. Only conflicting old email/usernames were replaced with unique inactive archive identifiers; old user IDs, order links, profiles and authenticator records remain. Old session versions were rotated and unused activation/OTP proofs consumed. Three new IDs were allocated normally; no IDs, sequences or tables were reset. Account mutations and audits committed together.

Verification found exactly three active accounts per database, all requested passwords matching their saved hashes, and all three phone numbers readable correctly. Each database retained 15 orders, 232 product rows and identical stock. Eight protected history/catalog tables were compared before and after within the transaction, including orders, line items, payment rows, addresses and authenticators. No setup email or historical order notification was sent.

Maya Sandbox uses the same application accounts/database in each environment. It does not have a separate Maya login for the Avela customer. Automated mutating regression tests use a separate synthetic database with independent keys and log mail; they never use these retained databases as disposable fixtures.
