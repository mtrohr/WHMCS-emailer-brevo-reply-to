<?php

namespace BrevoMailProvider\Tests\Unit;

use WHMCS\Authentication\CurrentUser;
use WHMCS\Exception\Mail\SendFailure;
use WHMCS\Exception\Module\InvalidConfiguration;

class TestConnectionTest extends TestCase
{
    public function testChecksAccountThenSendsTestEmailToAdmin()
    {
        $this->transport->queueResponse(200, '{"email":"owner@example.com"}');

        $result = $this->module->testConnection($this->params(array('brandTag' => 'BrandOne')));

        $this->assertSame(array('success' => true), $result);
        $this->assertCount(2, $this->transport->requests);

        $account = $this->transport->requests[0];
        $this->assertSame('GET', $account['method']);
        $this->assertSame('/account', $account['path']);
        $this->assertSame(self::API_KEY, $account['apiKey']);

        $send = $this->transport->requests[1];
        $this->assertSame('POST', $send['method']);
        $this->assertSame('/smtp/email', $send['path']);
        $p = $send['body'];
        $this->assertSame('noreply@example.com', $p['sender']['email']);
        $this->assertSame(array(array('email' => 'admin@example.com')), $p['to']);
        $this->assertSame(array('email' => 'support@example.com', 'name' => 'Example Support'), $p['replyTo']);
        $this->assertSame(array('BrandOne'), $p['tags']);
    }

    public function testRejectedKeyGivesReadableMessage()
    {
        $this->transport->queueResponse(401, '{"code":"unauthorized","message":"Key not found"}');

        $this->expectException(InvalidConfiguration::class);
        $this->expectExceptionMessage('Brevo rejected the API key (HTTP 401)');
        $this->module->testConnection($this->params());
    }

    public function testOtherAccountErrorIncludesBrevoDetails()
    {
        $this->transport->queueResponse(403, '{"code":"permission_denied","message":"IP not allowed"}');

        $this->expectException(InvalidConfiguration::class);
        $this->expectExceptionMessage('Brevo account check failed. HTTP 403: permission_denied - IP not allowed');
        $this->module->testConnection($this->params());
    }

    public function testAccountCurlError()
    {
        $this->transport->queueCurlError('cURL error 7: Failed to connect');

        $this->expectException(InvalidConfiguration::class);
        $this->expectExceptionMessage('Could not reach Brevo: cURL error 7: Failed to connect');
        $this->module->testConnection($this->params());
    }

    public function testInvalidTestFromEmail()
    {
        $this->transport->queueResponse(200, '{}');

        $this->expectException(InvalidConfiguration::class);
        $this->expectExceptionMessage('Test From Email');
        $this->module->testConnection($this->params(array('testFromEmail' => '')));
    }

    public function testMissingAdminEmail()
    {
        CurrentUser::$adminEmail = null;
        $this->transport->queueResponse(200, '{}');

        $this->expectException(InvalidConfiguration::class);
        $this->module->testConnection($this->params());
    }

    public function testSendFailureDuringTestSurfaces()
    {
        $this->transport->queueResponse(200, '{}');
        $this->transport->queueResponse(400, '{"code":"invalid_parameter","message":"sender not verified"}');

        $this->expectException(SendFailure::class);
        $this->expectExceptionMessage('HTTP 400: invalid_parameter - sender not verified');
        $this->module->testConnection($this->params());
    }
}
