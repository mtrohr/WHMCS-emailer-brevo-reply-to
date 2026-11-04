<?php

/**
 * Test stubs for the WHMCS classes the Brevo module uses.
 * Shapes follow whmcs-mail-provider.md. These never ship with the module.
 */

namespace WHMCS\Module\Contracts {

    interface SenderModuleInterface
    {
        public function settings();

        public function getName();

        public function getDisplayName();

        public function testConnection(array $params);

        public function send(array $params, \WHMCS\Mail\Message $message);
    }
}

namespace WHMCS\Module\MailSender {

    trait DescriptionTrait
    {
    }
}

namespace WHMCS\Exception\Mail {

    class SendFailure extends \Exception
    {
    }
}

namespace WHMCS\Exception\Module {

    class InvalidConfiguration extends \Exception
    {
    }
}

namespace WHMCS\Authentication {

    class CurrentUser
    {
        /** @var string|null Email returned by admin()->email, set by tests. */
        public static $adminEmail = 'admin@example.com';

        public function admin()
        {
            if (self::$adminEmail === null) {
                return null;
            }
            $admin = new \stdClass();
            $admin->email = self::$adminEmail;
            return $admin;
        }
    }
}

namespace WHMCS\Mail {

    class Message
    {
        private $fromEmail = '';
        private $fromName = '';
        private $subject = '';
        private $recipients = array('to' => array(), 'cc' => array(), 'bcc' => array());
        private $replyTo = array();
        private $body = '';
        private $plainText = '';
        private $attachments = array();

        public function setFrom($email, $name = '')
        {
            $this->fromEmail = $email;
            $this->fromName = $name;
            return $this;
        }

        public function setSubject($subject)
        {
            $this->subject = $subject;
            return $this;
        }

        public function addRecipient($kind, $email, $name = '')
        {
            $this->recipients[$kind][] = array($email, $name);
            return $this;
        }

        public function setReplyTo($email, $name = '')
        {
            $this->replyTo = array('email' => $email, 'name' => $name);
            return $this;
        }

        public function setBody($html)
        {
            $this->body = $html;
            return $this;
        }

        public function setPlainText($text)
        {
            $this->plainText = $text;
            return $this;
        }

        public function addAttachment(array $attachment)
        {
            $this->attachments[] = $attachment;
            return $this;
        }

        public function getFromEmail()
        {
            return $this->fromEmail;
        }

        public function getFromName()
        {
            return $this->fromName;
        }

        public function getSubject()
        {
            return $this->subject;
        }

        public function getRecipients($kind)
        {
            return isset($this->recipients[$kind]) ? $this->recipients[$kind] : array();
        }

        public function getReplyTo()
        {
            return $this->replyTo;
        }

        public function getBody()
        {
            return $this->body;
        }

        public function getPlainText()
        {
            return $this->plainText;
        }

        public function getAttachments()
        {
            return $this->attachments;
        }
    }
}

namespace {

    /** Records every logActivity() call for assertions. */
    class ActivityLog
    {
        /** @var string[] */
        public static $entries = array();
    }

    function logActivity($message, $userId = 0)
    {
        ActivityLog::$entries[] = (string) $message;
    }
}
