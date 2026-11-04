<?php

namespace BrevoMailProvider;

/**
 * Thrown when a message cannot be turned into a valid Brevo payload.
 */
class PayloadException extends \InvalidArgumentException
{
}

/**
 * Turns a normalised message array into a Brevo /smtp/email payload.
 *
 * Normalised message keys:
 *   fromEmail, fromName, subject, html, text (strings)
 *   to, cc, bcc (lists of [email, name])
 *   replyTo (array with email and name keys, or null)
 *   attachments (list of arrays with filename plus data or filepath)
 */
class PayloadBuilder
{
    const NAME_LIMIT = 70;

    /** Allowed attachment extensions, from brevo-send-email.md. */
    const ALLOWED_EXTENSIONS = array(
        'xlsx', 'xls', 'ods', 'docx', 'docm', 'doc', 'csv', 'pdf', 'txt', 'gif',
        'jpg', 'jpeg', 'png', 'tif', 'tiff', 'rtf', 'bmp', 'cgm', 'css', 'shtml',
        'html', 'htm', 'zip', 'xml', 'ppt', 'pptx', 'tar', 'ez', 'ics', 'mobi',
        'msg', 'pub', 'eps', 'odt', 'mp3', 'm4a', 'm4v', 'wma', 'ogg', 'flac',
        'wav', 'aif', 'aifc', 'aiff', 'mp4', 'mov', 'avi', 'mkv', 'mpeg', 'mpg',
        'wmv', 'pkpass', 'xlsm',
    );

    /**
     * @param array $message  Normalised message.
     * @param array $settings Module settings from WHMCS.
     *
     * @return array Brevo payload.
     *
     * @throws PayloadException
     */
    public static function build(array $message, array $settings)
    {
        $payload = array();

        $payload['sender'] = self::contact(
            self::str($message, 'fromEmail'),
            self::str($message, 'fromName')
        );

        foreach (array('to', 'cc', 'bcc') as $field) {
            $list = self::recipients(isset($message[$field]) ? $message[$field] : array());
            if (!empty($list)) {
                $payload[$field] = $list;
            }
        }
        if (empty($payload['to'])) {
            throw new PayloadException('No recipients: the message has no "to" address.');
        }

        $replyTo = self::resolveReplyTo($message, $settings);
        if ($replyTo !== null) {
            $payload['replyTo'] = $replyTo;
        }

        $payload['subject'] = self::str($message, 'subject');

        $html = self::str($message, 'html');
        $text = self::str($message, 'text');
        if (trim($html) !== '') {
            $payload['htmlContent'] = $html;
        }
        if (trim($text) !== '') {
            $payload['textContent'] = $text;
        }

        $attachments = self::attachments(
            isset($message['attachments']) && is_array($message['attachments'])
                ? $message['attachments']
                : array()
        );
        if (!empty($attachments)) {
            $payload['attachment'] = $attachments;
        }

        $tag = trim(self::str($settings, 'brandTag'));
        if ($tag !== '') {
            $payload['tags'] = array($tag);
        }

        return $payload;
    }

    /**
     * Apply the Reply-To rules in order:
     * 1. Keep a Reply-To already set on the message.
     * 2. Send none when the From address is on the skip list.
     * 3. Use the Reply-To Email setting when valid, else log and send none.
     *
     * @return array|null
     */
    public static function resolveReplyTo(array $message, array $settings)
    {
        $existing = isset($message['replyTo']) ? $message['replyTo'] : null;
        if (is_array($existing) && isset($existing['email']) && trim((string) $existing['email']) !== '') {
            $name = isset($existing['name']) ? (string) $existing['name'] : '';
            return self::contact(trim((string) $existing['email']), $name);
        }

        if (self::isSkipped(self::str($message, 'fromEmail'), self::str($settings, 'skipReplyToFrom'))) {
            return null;
        }

        $email = trim(self::str($settings, 'replyToEmail'));
        if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            self::log(
                'Brevo Mail: Reply-To Email setting is empty or invalid ("' . $email
                . '"). Message sent without Reply-To.'
            );
            return null;
        }

        return self::contact($email, self::str($settings, 'replyToName'));
    }

    /**
     * Case-insensitive, space-trimmed match of the From address against a
     * comma-separated list.
     *
     * @param string $fromEmail
     * @param string $skipList
     *
     * @return bool
     */
    public static function isSkipped($fromEmail, $skipList)
    {
        $from = strtolower(trim((string) $fromEmail));
        if ($from === '') {
            return false;
        }
        foreach (explode(',', (string) $skipList) as $entry) {
            if (strtolower(trim($entry)) === $from) {
                return true;
            }
        }
        return false;
    }

    /**
     * Truncate a display name to the Brevo limit of 70 characters.
     *
     * @param string $name
     *
     * @return string
     */
    public static function truncateName($name)
    {
        $name = trim((string) $name);
        if (function_exists('mb_substr')) {
            return mb_substr($name, 0, self::NAME_LIMIT, 'UTF-8');
        }
        return substr($name, 0, self::NAME_LIMIT);
    }

    /**
     * @param array $items Each item holds the email at index 0 and the name at index 1.
     *
     * @return array
     */
    private static function recipients($items)
    {
        $list = array();
        if (!is_array($items)) {
            return $list;
        }
        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }
            $email = isset($item[0]) ? trim((string) $item[0]) : '';
            if ($email === '') {
                continue;
            }
            $list[] = self::contact($email, isset($item[1]) ? (string) $item[1] : '');
        }
        return $list;
    }

    /**
     * @param string $email
     * @param string $name
     *
     * @return array
     */
    private static function contact($email, $name)
    {
        $contact = array('email' => $email);
        $name = self::truncateName($name);
        if ($name !== '') {
            $contact['name'] = $name;
        }
        return $contact;
    }

    /**
     * @param array $items
     *
     * @return array
     *
     * @throws PayloadException
     */
    private static function attachments(array $items)
    {
        $result = array();
        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }
            $path = isset($item['filepath']) ? (string) $item['filepath'] : '';
            $name = isset($item['filename']) ? trim((string) $item['filename']) : '';
            if ($name === '' && $path !== '') {
                $name = basename($path);
            }
            if ($name === '') {
                throw new PayloadException('Attachment rejected: an attachment has no file name.');
            }

            $extension = strtolower((string) pathinfo($name, PATHINFO_EXTENSION));
            if ($extension === '' || !in_array($extension, self::ALLOWED_EXTENSIONS, true)) {
                throw new PayloadException(
                    'Attachment rejected: "' . $name . '" has a file type Brevo does not allow ('
                    . ($extension === '' ? 'no extension' : '.' . $extension) . ').'
                );
            }

            if (array_key_exists('data', $item) && $item['data'] !== null) {
                $contents = (string) $item['data'];
            } elseif ($path !== '') {
                $contents = is_file($path) && is_readable($path) ? file_get_contents($path) : false;
                if ($contents === false) {
                    throw new PayloadException('Attachment rejected: cannot read file for "' . $name . '".');
                }
            } else {
                throw new PayloadException('Attachment rejected: "' . $name . '" has no data and no file path.');
            }

            $result[] = array(
                'content' => base64_encode($contents),
                'name' => $name,
            );
        }
        return $result;
    }

    /**
     * @param array  $source
     * @param string $key
     *
     * @return string
     */
    private static function str(array $source, $key)
    {
        return isset($source[$key]) && is_scalar($source[$key]) ? (string) $source[$key] : '';
    }

    /**
     * @param string $text
     *
     * @return void
     */
    private static function log($text)
    {
        if (function_exists('logActivity')) {
            logActivity($text);
        }
    }
}
