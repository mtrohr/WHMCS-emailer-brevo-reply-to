<?php

namespace BrevoMailProvider\Tests;

use BrevoMailProvider\HttpTransport;
use BrevoMailProvider\TransportException;

/**
 * Records requests and returns queued responses instead of calling Brevo.
 */
class FakeTransport extends HttpTransport
{
    /** @var array[] Each entry: method, path, apiKey, body (decoded JSON or null). */
    public $requests = array();

    /** @var array[] Queue of responses or exceptions. */
    private $queue = array();

    public function queueResponse($status, $body = '')
    {
        $this->queue[] = array('status' => $status, 'body' => $body);
        return $this;
    }

    public function queueCurlError($message)
    {
        $this->queue[] = new TransportException($message);
        return $this;
    }

    public function request($method, $path, $apiKey, $jsonBody = null)
    {
        $this->requests[] = array(
            'method' => $method,
            'path' => $path,
            'apiKey' => $apiKey,
            'raw' => $jsonBody,
            'body' => $jsonBody === null ? null : json_decode($jsonBody, true),
        );

        $next = empty($this->queue)
            ? array('status' => 201, 'body' => '{"messageId":"<test@brevo>"}')
            : array_shift($this->queue);

        if ($next instanceof TransportException) {
            throw $next;
        }
        return $next;
    }

    /**
     * @return array Decoded body of the last request.
     */
    public function lastPayload()
    {
        $last = end($this->requests);
        return $last ? $last['body'] : array();
    }
}
