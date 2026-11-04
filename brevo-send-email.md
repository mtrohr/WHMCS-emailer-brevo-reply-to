# Brevo Transactional Email API Reference

Source: https://developers.brevo.com/reference/send-transac-email
Source: https://developers.brevo.com/docs/send-a-transactional-email

## Endpoint

```
POST https://api.brevo.com/v3/smtp/email
```

## Headers

```
api-key: <your Brevo API key>
Content-Type: application/json
Accept: application/json
```

API keys start with `xkeysib-`. Create one in Brevo under Settings > SMTP & API.

## Account check (use for testConnection)

```
GET https://api.brevo.com/v3/account
api-key: <your Brevo API key>
```

Returns 200 with account details when the key works.

## Request body fields used by this module

| Field | Type | Notes |
| --- | --- | --- |
| `sender` | object | `{ "email": "...", "name": "..." }`. Required without a templateId. Name max 70 chars. |
| `to` | array | `[{ "email": "...", "name": "..." }]`. Required. Name max 70 chars. |
| `cc` | array | Same shape as `to`. Optional. |
| `bcc` | array | Same shape as `to`. Optional. |
| `replyTo` | object | `{ "email": "...", "name": "..." }`. Email required inside the object, name optional, name max 70 chars. |
| `subject` | string | Required without a templateId. |
| `htmlContent` | string | HTML body. |
| `textContent` | string | Plain text body. |
| `attachment` | array | `[{ "content": "<base64>", "name": "invoice.pdf" }]`. `name` is required with `content`. Alternative: `{ "url": "https://..." }`. Local file paths are not supported. |
| `tags` | array of strings | For filtering in Brevo reports. |
| `headers` | object | Custom non-standard headers only. Standard headers like Reply-To are NOT supported here. Use the `replyTo` field instead. Supports `IdempotencyKey` for safe retries. |

## Allowed attachment extensions

xlsx, xls, ods, docx, docm, doc, csv, pdf, txt, gif, jpg, jpeg, png, tif, tiff, rtf, bmp, cgm, css, shtml, html, htm, zip, xml, ppt, pptx, tar, ez, ics, mobi, msg, pub, eps, odt, mp3, m4a, m4v, wma, ogg, flac, wav, aif, aifc, aiff, mp4, mov, avi, mkv, mpeg, mpg, wmv, pkpass, xlsm

An attachment with another extension gets rejected. Log a clear error when this happens.

## Example request

```json
{
  "sender": { "email": "billing@example.com", "name": "Example Billing" },
  "to": [{ "email": "customer@example.com", "name": "Jane Customer" }],
  "replyTo": { "email": "support@example.com", "name": "Example Support" },
  "subject": "Invoice #1042",
  "htmlContent": "<p>Your invoice is attached.</p>",
  "textContent": "Your invoice is attached.",
  "attachment": [{ "content": "JVBERi0xLjQK...", "name": "Invoice-1042.pdf" }],
  "tags": ["Invoice Created"]
}
```

## Responses

- `201 Created`: sent. Body: `{ "messageId": "<...>" }`
- `202 Accepted`: scheduled (not used by this module)
- `400` and other errors: body has `code` (string) and `message` (readable string)

Common error codes: `invalid_parameter`, `missing_parameter`, `unauthorized`, `permission_denied`, `not_enough_credits`, `account_under_validation`, `duplicate_request`, `DMARC policy requires domain authentication`.

## Sender requirements

The sender email domain must be verified in Brevo with DKIM and SPF records. Unverified senders get rejected.
