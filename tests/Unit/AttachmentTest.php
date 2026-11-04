<?php

namespace BrevoMailProvider\Tests\Unit;

use WHMCS\Exception\Mail\SendFailure;

class AttachmentTest extends TestCase
{
    /** @var string[] */
    private $tempFiles = array();

    protected function tearDown(): void
    {
        foreach ($this->tempFiles as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
    }

    private function tempFile($contents)
    {
        $path = tempnam(sys_get_temp_dir(), 'brevo');
        file_put_contents($path, $contents);
        $this->tempFiles[] = $path;
        return $path;
    }

    public function testAttachmentFromData()
    {
        $m = $this->message()->addAttachment(array('filename' => 'Invoice-1042.pdf', 'data' => '%PDF-1.4 data'));

        $p = $this->send($m);

        $this->assertSame(array(
            array('content' => base64_encode('%PDF-1.4 data'), 'name' => 'Invoice-1042.pdf'),
        ), $p['attachment']);
    }

    public function testAttachmentFromFilepath()
    {
        $path = $this->tempFile("line one\nline two");
        $m = $this->message()->addAttachment(array('filename' => 'notes.TXT', 'filepath' => $path));

        $p = $this->send($m);

        $this->assertSame(base64_encode("line one\nline two"), $p['attachment'][0]['content']);
        $this->assertSame('notes.TXT', $p['attachment'][0]['name']);
    }

    public function testDataPreferredOverFilepath()
    {
        $m = $this->message()->addAttachment(array(
            'filename' => 'a.csv',
            'data' => 'from-data',
            'filepath' => '/does/not/exist.csv',
        ));

        $p = $this->send($m);

        $this->assertSame(base64_encode('from-data'), $p['attachment'][0]['content']);
    }

    public function testDisallowedExtensionThrowsNamingFile()
    {
        $m = $this->message()->addAttachment(array('filename' => 'setup.exe', 'data' => 'MZ'));

        try {
            $this->send($m);
            $this->fail('Expected SendFailure');
        } catch (SendFailure $e) {
            $this->assertStringContainsString('setup.exe', $e->getMessage());
            $this->assertStringContainsString('.exe', $e->getMessage());
        }
        $this->assertCount(0, $this->transport->requests, 'nothing sent');
    }

    public function testMissingExtensionThrows()
    {
        $m = $this->message()->addAttachment(array('filename' => 'README', 'data' => 'x'));

        $this->expectException(SendFailure::class);
        $this->expectExceptionMessage('"README"');
        $this->send($m);
    }

    public function testUnreadableFileThrows()
    {
        $m = $this->message()->addAttachment(array('filename' => 'gone.pdf', 'filepath' => '/no/such/file.pdf'));

        $this->expectException(SendFailure::class);
        $this->expectExceptionMessage('gone.pdf');
        $this->send($m);
    }
}
