<?php

define('WHMCS', true);

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/stubs/whmcs.php';
require_once __DIR__ . '/../modules/mail/Brevo/Brevo.php';
require_once __DIR__ . '/FakeTransport.php';
require_once __DIR__ . '/Unit/TestCase.php';
