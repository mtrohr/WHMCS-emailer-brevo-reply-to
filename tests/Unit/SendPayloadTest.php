<?php

namespace BrevoMailProvider\Tests\Unit;

use ActivityLog;
use WHMCS\Exception\Mail\SendFailure;

class SendPayloadTest extends TestCase
{
    public function testPostsToSmtpEmailWithApiKey()
    {
        $this->send($this->message());

        $this->assertCount(1, $this->transport->requests);
        $req = $this->transport->requests[0];
        $this->assertSame('POST', $req['method']);
        $this->assertSame('/smtp/email', $req['path']);
        $this->assertSame(self::API_KEY, $req['apiKey']);
    }

    public function testMapsSenderRecipientsSubjectAndBodies()
    {
        $m = $this->message()
            ->addRecipient('to', 'second@example.com', 'Second Person')
            ->addRecipient('cc', 'cc@example.com', 'Copy Person')
            ->addRecipient('bcc', 'bcc@example.com', 'Blind Person');

        $p = $this->send($m);

        $this->assertSame(array('email' => 'billing@example.com', 'name' => 'Example Billing'), $p['sender']);
        $this->assertSame(array(
            array('email' => 'customer@example.com', 'name' => 'Jane Customer'),
            array('email' => 'second@example.com', 'name' => 'Second Person'),
        ), $p['to']);
        $this->assertSame(array(array('email' => 'cc@example.com', 'name' => 'Copy Person')), $p['cc']);
        $this->assertSame(array(array('email' => 'bcc@example.com', 'name' => 'Blind Person')), $p['bcc']);
        $this->assertSame('Invoice #1042', $p['subject']);
        $this->assertSame('<p>Your invoice is attached.</p>', $p['htmlContent']);
        $this->assertSame('Your invoice is attached.', $p['textContent']);
        $this->assertArrayNotHasKey('headers', $p);
    }

    public function testOmitsEmptyCcBccAndNames()
    {
        $m = new \WHMCS\Mail\Message();
        $m->setFrom('billing@example.com', '')
            ->setSubject('Hi')
            ->addRecipient('to', 'customer@example.com', '')
            ->setBody('<p>Hi</p>');

        $p = $this->send($m);

        $this->assertArrayNotHasKey('cc', $p);
        $this->assertArrayNotHasKey('bcc', $p);
        $this->assertSame(array('email' => 'billing@example.com'), $p['sender']);
        $this->assertSame(array(array('email' => 'customer@example.com')), $p['to']);
        $this->assertArrayNotHasKey('attachment', $p);
        $this->assertArrayNotHasKey('tags', $p);
    }

    public function testHtmlOnlyWhenPlainTextEmpty()
    {
        $p = $this->send($this->message()->setPlainText(''));

        $this->assertSame('<p>Your invoice is attached.</p>', $p['htmlContent']);
        $this->assertArrayNotHasKey('textContent', $p);
    }

    public function testTextOnlyWhenNoHtml()
    {
        $p = $this->send($this->message()->setBody(''));

        $this->assertArrayNotHasKey('htmlContent', $p);
        $this->assertSame('Your invoice is attached.', $p['textContent']);
    }

    public function testTruncatesEveryNameTo70Characters()
    {
        $long = str_repeat('a', 69) . 'éXYZ';
        $m = new \WHMCS\Mail\Message();
        $m->setFrom('billing@example.com', $long)
            ->setSubject('Hi')
            ->setBody('<p>Hi</p>')
            ->addRecipient('to', 'to@example.com', $long)
            ->addRecipient('cc', 'cc@example.com', $long)
            ->addRecipient('bcc', 'bcc@example.com', $long);

        $p = $this->send($m, $this->params(array('replyToName' => $long)));

        $expected = str_repeat('a', 69) . 'é';
        $this->assertSame($expected, $p['sender']['name']);
        $this->assertSame($expected, $p['to'][0]['name']);
        $this->assertSame($expected, $p['cc'][0]['name']);
        $this->assertSame($expected, $p['bcc'][0]['name']);
        $this->assertSame($expected, $p['replyTo']['name']);
        $this->assertSame(70, mb_strlen($p['sender']['name'], 'UTF-8'));
    }

    public function testTruncatesExistingReplyToName()
    {
        $p = $this->send($this->message()->setReplyTo('agent@example.com', str_repeat('b', 80)));

        $this->assertSame(str_repeat('b', 70), $p['replyTo']['name']);
    }

    public function testBrandTagIncluded()
    {
        $p = $this->send($this->message(), $this->params(array('brandTag' => ' BrandOne ')));

        $this->assertSame(array('BrandOne'), $p['tags']);
    }

    public function testNoRecipientThrows()
    {
        $m = new \WHMCS\Mail\Message();
        $m->setFrom('billing@example.com')->setSubject('Hi')->setBody('<p>Hi</p>');

        $this->expectException(SendFailure::class);
        $this->expectExceptionMessage('No recipients');
        $this->send($m);
    }

    public function testMissingApiKeyThrowsInvalidConfiguration()
    {
        $this->expectException(\WHMCS\Exception\Module\InvalidConfiguration::class);
        $this->expectExceptionMessage('Brevo API Key is not set.');
        $this->send($this->message(), $this->params(array('apiKey' => '  ')));
    }

    public function testNoActivityLoggedOnNormalSend()
    {
        $this->send($this->message());

        $this->assertSame(array(), ActivityLog::$entries);
    }
}
