# WHMCS Mail Provider Module Reference

Source: https://developers.whmcs.com/mail-providers/getting-started/
Source: https://developers.whmcs.com/classes/whmcs/module/contracts/sendermoduleinterface/

## Location and naming

- Modules live in `modules/mail/<ModuleName>/<ModuleName>.php`.
- Module name: one word, letters and numbers only, starts with a letter, unique.
- The class must live in the `WHMCS\Module\Mail` namespace.
- The class must implement `WHMCS\Module\Contracts\SenderModuleInterface`.
- The official sample also uses the trait `WHMCS\Module\MailSender\DescriptionTrait`.

## Classes referenced by the official sample

```php
use WHMCS\Authentication\CurrentUser;          // (new CurrentUser)->admin()->email gives the logged-in admin's email
use WHMCS\Exception\Mail\SendFailure;           // exception type for failed sends
use WHMCS\Exception\Module\InvalidConfiguration; // exception type for bad settings
use WHMCS\Mail\Message;                          // the outgoing message object
use WHMCS\Module\Contracts\SenderModuleInterface;
use WHMCS\Module\MailSender\DescriptionTrait;
```

## SenderModuleInterface methods

```php
public function settings();                                  // returns array of config fields
public function getName();                                   // returns string, internal name
public function getDisplayName();                            // returns string, name shown in Admin Area
public function testConnection(array $params);               // tests the saved settings
public function send(array $params, Message $message);       // delivers one message
```

`$params` holds the saved values from `settings()`, keyed by setting name.

## settings() format

Each key is the setting name. Each value is an array with:

- `FriendlyName` (label shown in the admin UI)
- `Type` (`text`, `password`, and similar WHMCS config field types)
- `Description` (help text)

Example shape:

```php
return [
    'apiKey' => [
        'FriendlyName' => 'API Key',
        'Type' => 'password',
        'Description' => 'Your API key.',
    ],
];
```

## testConnection()

The official sample sends a test message to the current admin's email address using `(new CurrentUser)->admin()->email`. On failure, the sample throws an `Exception` with a readable message. WHMCS shows the message in the Test Configuration result.

## Message object getters (from the official sample)

```php
$message->getFromName();          // sender display name
$message->getFromEmail();         // sender email
$message->getSubject();           // subject line
$message->getRecipients('to');    // array of [email, name] pairs, so $r[0] = email, $r[1] = name
$message->getRecipients('cc');    // same shape
$message->getRecipients('bcc');   // same shape
$message->getReplyTo();           // array with 'name' and 'email' keys, or empty/falsy when none set
$message->getBody();              // HTML body
$message->getPlainText();         // plain text body
$message->getAttachments();       // array of attachments, see below
```

## Attachment shape

Each item from `getAttachments()` has `filename`, plus one of:

- `data` (raw file contents in memory), or
- `filepath` (path on disk, read with `file_get_contents`)

Check for the `data` key first. Fall back to reading `filepath`.

## Body handling in the official sample

- When an HTML body exists, send the HTML body. If the plain text body is empty, the sample substitutes a single space so the plain text part is never empty.
- When no HTML body exists, send the plain text body only.

## Not covered by these docs

- The template name of the outgoing email. The docs show no getter for the template name on `Message`. Flag this before relying on template-based logic.
- Full class docs for `Message`: https://classdocs.whmcs.com/8.1/WHMCS/Mail_ns.html (older link from the official page).
