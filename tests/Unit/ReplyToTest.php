<?php

namespace BrevoMailProvider\Tests\Unit;

use ActivityLog;

class ReplyToTest extends TestCase
{
    public function testAddsReplyToFromSettings()
    {
        $p = $this->send($this->message());

        $this->assertSame(
            array('email' => 'support@example.com', 'name' => 'Example Support'),
            $p['replyTo']
        );
        $this->assertArrayNotHasKey('headers', $p);
    }

    public function testReplyToNameOmittedWhenSettingEmpty()
    {
        $p = $this->send($this->message(), $this->params(array('replyToName' => '')));

        $this->assertSame(array('email' => 'support@example.com'), $p['replyTo']);
    }

    public function testKeepsExistingReplyTo()
    {
        $p = $this->send($this->message()->setReplyTo('agent@example.com', 'Agent Smith'));

        $this->assertSame(array('email' => 'agent@example.com', 'name' => 'Agent Smith'), $p['replyTo']);
    }

    public function testExistingReplyToWinsOverSkipList()
    {
        $m = $this->message()
            ->setFrom('tickets@example.com', 'Tickets')
            ->setReplyTo('agent@example.com', 'Agent');

        $p = $this->send($m);

        $this->assertSame('agent@example.com', $p['replyTo']['email']);
    }

    /**
     * @dataProvider skippedFromProvider
     */
    public function testSkipsReplyToForListedFromAddress($from, $skipList)
    {
        $m = $this->message()->setFrom($from, 'Support Dept');

        $p = $this->send($m, $this->params(array('skipReplyToFrom' => $skipList)));

        $this->assertArrayNotHasKey('replyTo', $p);
        $this->assertSame(array(), ActivityLog::$entries);
    }

    public function skippedFromProvider()
    {
        return array(
            'exact' => array('tickets@example.com', 'tickets@example.com'),
            'upper case from' => array('TICKETS@Example.COM', 'tickets@example.com'),
            'upper case list' => array('tickets@example.com', 'Tickets@EXAMPLE.com'),
            'spaces in list' => array('sales@example.com', '  tickets@example.com ,   sales@example.com  '),
            'spaces in from' => array(' sales@example.com ', 'tickets@example.com,sales@example.com'),
        );
    }

    public function testDoesNotSkipPartialMatch()
    {
        $m = $this->message()->setFrom('mytickets@example.com');

        $p = $this->send($m, $this->params(array('skipReplyToFrom' => 'tickets@example.com')));

        $this->assertSame('support@example.com', $p['replyTo']['email']);
    }

    public function testInvalidReplyToSettingIsLoggedAndSkipped()
    {
        $p = $this->send($this->message(), $this->params(array('replyToEmail' => 'not-an-email')));

        $this->assertArrayNotHasKey('replyTo', $p);
        $this->assertCount(1, ActivityLog::$entries);
        $this->assertStringContainsString('Reply-To Email setting is empty or invalid', ActivityLog::$entries[0]);
        $this->assertCount(1, $this->transport->requests, 'message still sends');
    }

    public function testEmptyReplyToSettingIsLoggedAndSkipped()
    {
        $p = $this->send($this->message(), $this->params(array('replyToEmail' => '')));

        $this->assertArrayNotHasKey('replyTo', $p);
        $this->assertCount(1, ActivityLog::$entries);
    }
}
