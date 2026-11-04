# Brevo Mail Provider for WHMCS

A WHMCS mail provider module. It sends every outgoing WHMCS email through the Brevo transactional email API and adds a Reply-To to each message.

Requirements: WHMCS 8.8 or newer, PHP 7.4 or newer with the curl and json extensions. The module has zero Composer dependencies.

## What it does

- Sends all WHMCS mail with `POST https://api.brevo.com/v3/smtp/email`.
- Adds a Reply-To to every message, in this order:
  1. A Reply-To already set by WHMCS stays as is.
  2. Messages from an address on the skip list get no Reply-To. Ticket replies then reach the department address for email piping.
  3. All other messages get the Reply-To Email setting. An empty or invalid setting sends no Reply-To and writes a warning to the WHMCS Activity Log.
- Sends the Brand Tag as a Brevo tag on every message.
- Rejects attachments with file types Brevo does not allow. The send fails with the file name in the WHMCS email log.
- Truncates every display name to 70 characters, the Brevo limit.
- Never writes the API key to a log or error message. Never retries a failed send.

## Settings

| Setting | Purpose |
| --- | --- |
| Brevo API Key | Key from Brevo, Settings, SMTP & API. Starts with `xkeysib-`. |
| Reply-To Email | Support inbox added as Reply-To. |
| Reply-To Name | Display name for the Reply-To. |
| Skip Reply-To for These From Addresses | Comma-separated support department addresses. Matching ignores case and spaces. |
| Brand Tag | Tag for Brevo reports, for example the brand name. |
| Test From Email | Sender for the Test Configuration email. Use an address on a domain verified in Brevo. |

Each WHMCS install stores its own settings. Nothing is hardcoded, so the same module serves all three brands.

## Install

1. Copy the `modules/mail/Brevo/` folder into the WHMCS root, so the main file sits at `<whmcs>/modules/mail/Brevo/Brevo.php`.
2. In the WHMCS Admin Area, open System Settings, then Mail.
3. Note the current Mail Provider and its settings. You need these for rollback.
4. Select Brevo as the Mail Provider.
5. Fill in the settings above and save.
6. Click Test Configuration. The module checks the API key with `GET /v3/account`, then sends a test email to your admin address through the full send path.

Do not copy `tests/`, `vendor/`, or `composer.json` to the server. They are for development only.

## Brevo setup

Do this once per brand.

1. In Brevo, open Senders, Domains & Dedicated IPs, then Domains.
2. Add each domain WHMCS sends from. This covers the system From address, every department address, and the Test From Email domain.
3. Add the DKIM and SPF records Brevo shows to the DNS for each domain. Wait until Brevo marks the domain as authenticated. Brevo rejects mail from unverified senders.
4. Open Settings, SMTP & API, then API Keys. Create one API key per brand, named after the brand, for example `whmcs-brand-one`.
5. Paste each key into the matching WHMCS install only.
6. If you restrict API keys by IP in Brevo, add the IP address of each WHMCS server.

## Rollback

1. In the WHMCS Admin Area, open System Settings, then Mail.
2. Switch Mail Provider back to the previous option, for example PHP Mail or SMTP.
3. Re-enter the previous settings if WHMCS cleared them, then save.
4. Click Test Configuration to confirm mail flows again.
5. Optional: delete `modules/mail/Brevo/` from the server.

The module stores no data outside the WHMCS mail settings, so rollback needs no cleanup.

## Manual test checklist for staging

Run these on a staging WHMCS install with a Gmail address as the recipient. For each message, open it in Gmail, click the three dots menu, then Show original, and check the `Reply-To:` header.

| Test | How | Expected Reply-To |
| --- | --- | --- |
| Test email | System Settings, Mail, Test Configuration | Reply-To Email setting |
| Invoice with PDF | Create an invoice for a test client with PDF attachment enabled, then send it | Reply-To Email setting. The PDF opens. |
| Password reset | Request a password reset from the client area for a test client | Reply-To Email setting |
| Ticket reply from a department | Open a ticket in a department whose address is on the skip list, then reply as admin | No Reply-To header, or the department address set by WHMCS. Replying from Gmail reaches the department and pipes into the ticket. |

Also check for each message:

- In Show original, `SPF: PASS` and `DKIM: PASS` for the sending domain.
- In Brevo, Transactional, Logs, the message shows the Brand Tag.
- In WHMCS, System Logs, Email Message Log, the message shows as sent.
- Set Reply-To Email to an invalid value, send any email, and confirm a warning appears in the Activity Log while the message still sends.

## Development

```
composer install
composer test     # PHPUnit
composer compat   # PHP_CodeSniffer with PHPCompatibility, PHP 7.4 and newer
```

Layout:

- `modules/mail/Brevo/Brevo.php`: module class, settings, send and test flow.
- `modules/mail/Brevo/lib/PayloadBuilder.php`: payload mapping and Reply-To rules.
- `modules/mail/Brevo/lib/HttpTransport.php`: curl wrapper. Tests replace it with `tests/FakeTransport.php`.
- `tests/stubs/whmcs.php`: stubs for the WHMCS classes, the interface, the trait, `CurrentUser`, and `logActivity()`.
