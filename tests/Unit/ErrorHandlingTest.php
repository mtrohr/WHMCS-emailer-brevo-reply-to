<?php

namespace BrevoMailProvider\Tests\Unit;

use ActivityLog;
use BrevoMailProvider\HttpTransport;
use BrevoMailProvider\TransportException;
use WHMCS\Exception\Mail\SendFailure;
use WHMCS\Exception\Module\InvalidConfiguration;
use WHMCS\Module\Mail\Brevo;

class ErrorHandlingTest extends TestCase
{
    public function testCurlErrorThrowsWithCurlMessage()
    {
        $this->transport->queueCurlError('cURL error 28: Operation timed out after 30001 milliseconds');

        $this->expectException(SendFailure::class);
        $this->expectExceptionMessage('Brevo send failed: cURL error 28: Operation timed out');
        $this->send($this->message());
    }

    public function testNon2xxThrowsWithStatusCodeAndMessage()
    {
        $this->transport->queueResponse(400, '{"code":"invalid_parameter","message":"sender is not valid"}');

        $this->expectException(SendFailure::class);
        $this->expectExceptionMessage('Brevo send failed. HTTP 400: invalid_parameter - sender is not valid');
        $this->send($this->message());
    }

    public function testNon2xxWithNonJsonBody()
    {
        $this->transport->queueResponse(502, '<html>Bad Gateway</html>');

        $this->expectException(SendFailure::class);
        $this->expectExceptionMessage('Brevo send failed. HTTP 502');
        $this->send($this->message());
    }

    public function testAnySuccessStatusAccepted()
    {
        $this->transport->queueResponse(202, '{}');
        $this->send($this->message());
        $this->assertCount(1, $this->transport->requests);
    }

    public function testRealCurlErrorIsReadable()
    {
        $module = new Brevo();
        $module->setTransport(new HttpTransport('http://127.0.0.1:1/v3'));

        try {
            $module->send($this->params(), $this->message());
            $this->fail('Expected SendFailure');
        } catch (SendFailure $e) {
            $this->assertMatchesRegularExpression('/^Brevo send failed: cURL error \d+: \S/', $e->getMessage());
            $this->assertStringNotContainsString(self::API_KEY, $e->getMessage());
        }
    }

    public function testNoRetryOnFailure()
    {
        $this->transport->queueResponse(500, '{"code":"internal","message":"boom"}');

        try {
            $this->send($this->message());
        } catch (SendFailure $e) {
            // expected
        }
        $this->assertCount(1, $this->transport->requests);
    }

    /**
     * Brevo or curl echoing the key back must never leak it.
     *
     * @dataProvider leakingFailures
     */
    public function testApiKeyNeverInExceptionOrLog($setup)
    {
        $setup($this->transport);
        $messages = array();

        try {
            $this->send($this->message(), $this->params(array('replyToEmail' => 'bad')));
        } catch (\Exception $e) {
            $messages[] = $e->getMessage();
            $previous = $e->getPrevious();
            while ($previous) {
                $messages[] = $previous->getMessage();
                $previous = $previous->getPrevious();
            }
        }

        $this->assertNotEmpty($messages, 'a failure was raised');
        foreach (array_merge($messages, ActivityLog::$entries) as $text) {
            $this->assertStringNotContainsString(self::API_KEY, $text);
            $this->assertStringNotContainsString('SECRET0123456789', $text);
        }
    }

    public function leakingFailures()
    {
        $key = self::API_KEY;
        return array(
            'curl error echoing key' => array(function ($t) use ($key) {
                $t->queueCurlError('cURL error 6: bad header api-key: ' . $key);
            }),
            'error body echoing key' => array(function ($t) use ($key) {
                $t->queueResponse(401, json_encode(array('code' => 'unauthorized', 'message' => 'Key not found: ' . $key)));
            }),
            'error code echoing key' => array(function ($t) use ($key) {
                $t->queueResponse(403, json_encode(array('code' => $key, 'message' => 'denied')));
            }),
        );
    }

    public function testApiKeyNeverInTestConnectionErrors()
    {
        $this->transport->queueResponse(500, json_encode(array('code' => 'x', 'message' => self::API_KEY)));

        try {
            $this->module->testConnection($this->params());
            $this->fail('Expected InvalidConfiguration');
        } catch (InvalidConfiguration $e) {
            $this->assertStringNotContainsString(self::API_KEY, $e->getMessage());
            $this->assertStringContainsString('[redacted]', $e->getMessage());
        }
    }

    public function testTransportExceptionIsRuntimeException()
    {
        $this->assertInstanceOf(\RuntimeException::class, new TransportException('x'));
    }
}
