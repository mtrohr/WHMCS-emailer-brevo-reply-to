<?php

namespace BrevoMailProvider\Tests\Unit;

use ActivityLog;
use BrevoMailProvider\Tests\FakeTransport;
use WHMCS\Authentication\CurrentUser;
use WHMCS\Mail\Message;
use WHMCS\Module\Mail\Brevo;

abstract class TestCase extends \PHPUnit\Framework\TestCase
{
    const API_KEY = 'xkeysib-SECRET0123456789abcdef-TESTKEY';

    /** @var FakeTransport */
    protected $transport;

    /** @var Brevo */
    protected $module;

    protected function setUp(): void
    {
        ActivityLog::$entries = array();
        CurrentUser::$adminEmail = 'admin@example.com';
        $this->transport = new FakeTransport();
        $this->module = new Brevo();
        $this->module->setTransport($this->transport);
    }

    protected function params(array $overrides = array())
    {
        return array_merge(array(
            'apiKey' => self::API_KEY,
            'replyToEmail' => 'support@example.com',
            'replyToName' => 'Example Support',
            'skipReplyToFrom' => 'tickets@example.com, sales@example.com',
            'brandTag' => '',
            'testFromEmail' => 'noreply@example.com',
        ), $overrides);
    }

    protected function message()
    {
        $m = new Message();
        $m->setFrom('billing@example.com', 'Example Billing')
            ->setSubject('Invoice #1042')
            ->addRecipient('to', 'customer@example.com', 'Jane Customer')
            ->setBody('<p>Your invoice is attached.</p>')
            ->setPlainText('Your invoice is attached.');
        return $m;
    }

    protected function send(Message $message, $params = null)
    {
        $this->module->send($params === null ? $this->params() : $params, $message);
        return $this->transport->lastPayload();
    }
}
