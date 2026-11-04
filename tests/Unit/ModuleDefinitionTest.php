<?php

namespace BrevoMailProvider\Tests\Unit;

use WHMCS\Module\Contracts\SenderModuleInterface;
use WHMCS\Module\MailSender\DescriptionTrait;

class ModuleDefinitionTest extends TestCase
{
    public function testImplementsInterfaceAndUsesTrait()
    {
        $this->assertInstanceOf(SenderModuleInterface::class, $this->module);
        $this->assertContains(DescriptionTrait::class, class_uses($this->module));
    }

    public function testNames()
    {
        $this->assertSame('Brevo', $this->module->getName());
        $this->assertSame('Brevo', $this->module->getDisplayName());
    }

    public function testSettings()
    {
        $settings = $this->module->settings();

        $this->assertSame(
            array('apiKey', 'replyToEmail', 'replyToName', 'skipReplyToFrom', 'brandTag', 'testFromEmail'),
            array_keys($settings)
        );
        $this->assertSame('password', $settings['apiKey']['Type']);
        foreach ($settings as $key => $field) {
            $this->assertArrayHasKey('FriendlyName', $field, $key);
            $this->assertArrayHasKey('Description', $field, $key);
            if ($key !== 'apiKey') {
                $this->assertSame('text', $field['Type'], $key);
            }
        }
    }

    public function testModuleFolderHasNoVendorDependency()
    {
        $dir = dirname(__DIR__, 2) . '/modules/mail/Brevo';
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS));
        foreach ($files as $file) {
            $this->assertStringNotContainsString('vendor/autoload', file_get_contents($file->getPathname()));
        }
    }
}
