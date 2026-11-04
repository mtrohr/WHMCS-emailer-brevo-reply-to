<?php

/**
 * Brevo mail provider for WHMCS.
 *
 * Sends every outgoing WHMCS email through the Brevo transactional email API
 * and adds a Reply-To to each message, except for configured From addresses.
 */

namespace WHMCS\Module\Mail;

if (!defined('WHMCS')) {
    die('This file cannot be accessed directly');
}

require_once __DIR__ . '/lib/HttpTransport.php';
require_once __DIR__ . '/lib/PayloadBuilder.php';

use BrevoMailProvider\HttpTransport;
use BrevoMailProvider\PayloadBuilder;
use BrevoMailProvider\PayloadException;
use BrevoMailProvider\TransportException;
use WHMCS\Authentication\CurrentUser;
use WHMCS\Exception\Mail\SendFailure;
use WHMCS\Exception\Module\InvalidConfiguration;
use WHMCS\Mail\Message;
use WHMCS\Module\Contracts\SenderModuleInterface;
use WHMCS\Module\MailSender\DescriptionTrait;

class Brevo implements SenderModuleInterface
{
    use DescriptionTrait;

    /** @var HttpTransport|null */
    private $transport;

    /**
     * Replace the HTTP transport. Tests use this to inject a fake.
     *
     * @param HttpTransport $transport
     *
     * @return $this
     */
    public function setTransport(HttpTransport $transport)
    {
        $this->transport = $transport;
        return $this;
    }

    /**
     * @return HttpTransport
     */
    private function transport()
    {
        if ($this->transport === null) {
            $this->transport = new HttpTransport();
        }
        return $this->transport;
    }

    public function getName()
    {
        return 'Brevo';
    }

    public function getDisplayName()
    {
        return 'Brevo';
    }

    public function settings()
    {
        return array(
            'apiKey' => array(
                'FriendlyName' => 'Brevo API Key',
                'Type' => 'password',
                'Description' => 'API key from Brevo, Settings, SMTP & API. Starts with xkeysib-.',
            ),
            'replyToEmail' => array(
                'FriendlyName' => 'Reply-To Email',
                'Type' => 'text',
                'Description' => 'Support inbox added as Reply-To on every message.',
            ),
            'replyToName' => array(
                'FriendlyName' => 'Reply-To Name',
                'Type' => 'text',
                'Description' => 'Display name for the Reply-To address. Max 70 characters.',
            ),
            'skipReplyToFrom' => array(
                'FriendlyName' => 'Skip Reply-To for These From Addresses',
                'Type' => 'text',
                'Description' => 'Comma-separated support department addresses. '
                    . 'Messages from these addresses get no Reply-To, so ticket replies reach the department.',
            ),
            'brandTag' => array(
                'FriendlyName' => 'Brand Tag',
                'Type' => 'text',
                'Description' => 'Tag added to every message for filtering in Brevo reports.',
            ),
            'testFromEmail' => array(
                'FriendlyName' => 'Test From Email',
                'Type' => 'text',
                'Description' => 'Sender used by Test Configuration. Use an address on a domain verified in Brevo.',
            ),
        );
    }

    /**
     * Check the API key, then send a test email to the logged-in admin through
     * the same path as send().
     *
     * @param array $params
     *
     * @return array
     *
     * @throws InvalidConfiguration|SendFailure
     */
    public function testConnection(array $params)
    {
        $apiKey = $this->apiKey($params);

        try {
            $response = $this->transport()->request('GET', '/account', $apiKey);
        } catch (TransportException $e) {
            throw new InvalidConfiguration(
                'Could not reach Brevo: ' . $this->scrub($e->getMessage(), $apiKey)
            );
        }

        $status = $response['status'];
        if ($status === 401) {
            throw new InvalidConfiguration(
                'Brevo rejected the API key (HTTP 401). Check the key in Brevo, Settings, SMTP & API.'
            );
        }
        if ($status < 200 || $status >= 300) {
            throw new InvalidConfiguration(
                'Brevo account check failed. ' . $this->describeError($response, $apiKey)
            );
        }

        $fromEmail = isset($params['testFromEmail']) ? trim((string) $params['testFromEmail']) : '';
        if ($fromEmail === '' || filter_var($fromEmail, FILTER_VALIDATE_EMAIL) === false) {
            throw new InvalidConfiguration(
                'Set Test From Email to a valid address on a domain verified in Brevo.'
            );
        }

        $admin = (new CurrentUser())->admin();
        $adminEmail = $admin ? trim((string) $admin->email) : '';
        if ($adminEmail === '') {
            throw new InvalidConfiguration('Could not find the email address of the logged-in admin.');
        }

        $this->deliver($params, array(
            'fromEmail' => $fromEmail,
            'fromName' => 'WHMCS',
            'to' => array(array($adminEmail, '')),
            'cc' => array(),
            'bcc' => array(),
            'replyTo' => null,
            'subject' => 'Brevo mail provider test',
            'html' => '<p>This is a test email from the WHMCS Brevo mail provider.</p>'
                . '<p>Check the Reply-To header of this message.</p>',
            'text' => "This is a test email from the WHMCS Brevo mail provider.\n"
                . 'Check the Reply-To header of this message.',
            'attachments' => array(),
        ));

        return array('success' => true);
    }

    /**
     * @param array   $params
     * @param Message $message
     *
     * @return void
     *
     * @throws InvalidConfiguration|SendFailure
     */
    public function send(array $params, Message $message)
    {
        $this->deliver($params, array(
            'fromEmail' => $message->getFromEmail(),
            'fromName' => $message->getFromName(),
            'to' => $message->getRecipients('to'),
            'cc' => $message->getRecipients('cc'),
            'bcc' => $message->getRecipients('bcc'),
            'replyTo' => $message->getReplyTo(),
            'subject' => $message->getSubject(),
            'html' => $message->getBody(),
            'text' => $message->getPlainText(),
            'attachments' => $message->getAttachments(),
        ));
    }

    /**
     * Shared send path for send() and testConnection().
     *
     * @param array $params
     * @param array $data Normalised message, see PayloadBuilder.
     *
     * @return void
     *
     * @throws InvalidConfiguration|SendFailure
     */
    private function deliver(array $params, array $data)
    {
        $apiKey = $this->apiKey($params);

        try {
            $payload = PayloadBuilder::build($data, $params);
        } catch (PayloadException $e) {
            throw new SendFailure($this->scrub($e->getMessage(), $apiKey));
        }

        $json = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($json === false) {
            throw new SendFailure('Could not encode the message for Brevo: ' . json_last_error_msg());
        }

        try {
            $response = $this->transport()->request('POST', '/smtp/email', $apiKey, $json);
        } catch (TransportException $e) {
            throw new SendFailure('Brevo send failed: ' . $this->scrub($e->getMessage(), $apiKey));
        }

        $status = $response['status'];
        if ($status < 200 || $status >= 300) {
            throw new SendFailure('Brevo send failed. ' . $this->describeError($response, $apiKey));
        }
    }

    /**
     * @param array $params
     *
     * @return string
     *
     * @throws InvalidConfiguration
     */
    private function apiKey(array $params)
    {
        $key = isset($params['apiKey']) ? trim((string) $params['apiKey']) : '';
        if ($key === '') {
            throw new InvalidConfiguration('Brevo API Key is not set.');
        }
        return $key;
    }

    /**
     * Build "HTTP <status>: <code> - <message>" from a Brevo error response.
     *
     * @param array  $response
     * @param string $apiKey
     *
     * @return string
     */
    private function describeError(array $response, $apiKey)
    {
        $text = 'HTTP ' . $response['status'];
        $decoded = json_decode($response['body'], true);
        if (is_array($decoded)) {
            $code = isset($decoded['code']) && is_scalar($decoded['code']) ? (string) $decoded['code'] : '';
            $msg = isset($decoded['message']) && is_scalar($decoded['message']) ? (string) $decoded['message'] : '';
            if ($code !== '') {
                $text .= ': ' . $code;
                if ($msg !== '') {
                    $text .= ' - ' . $msg;
                }
            } elseif ($msg !== '') {
                $text .= ': ' . $msg;
            }
        }
        return $this->scrub($text, $apiKey);
    }

    /**
     * Remove the API key from any text before it reaches a log or exception.
     *
     * @param string $text
     * @param string $apiKey
     *
     * @return string
     */
    private function scrub($text, $apiKey)
    {
        if ($apiKey === '') {
            return $text;
        }
        return str_replace($apiKey, '[redacted]', $text);
    }
}
