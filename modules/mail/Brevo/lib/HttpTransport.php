<?php

namespace BrevoMailProvider;

/**
 * Thrown when curl fails before an HTTP response arrives.
 */
class TransportException extends \RuntimeException
{
}

/**
 * Minimal curl wrapper for the Brevo API.
 *
 * Tests replace this class with a fake, so it holds no message logic.
 */
class HttpTransport
{
    const DEFAULT_BASE_URL = 'https://api.brevo.com/v3';
    const CONNECT_TIMEOUT = 10;
    const TOTAL_TIMEOUT = 30;

    /** @var string */
    private $baseUrl;

    /**
     * @param string $baseUrl
     */
    public function __construct($baseUrl = self::DEFAULT_BASE_URL)
    {
        $this->baseUrl = rtrim($baseUrl, '/');
    }

    /**
     * Send one request and return the status code and raw body.
     *
     * @param string      $method   GET or POST.
     * @param string      $path     Path below /v3, for example /smtp/email.
     * @param string      $apiKey   Brevo API key, sent only in the api-key header.
     * @param string|null $jsonBody JSON request body, or null for none.
     *
     * @return array{status:int, body:string}
     *
     * @throws TransportException On any curl error.
     */
    public function request($method, $path, $apiKey, $jsonBody = null)
    {
        $headers = array(
            'api-key: ' . $apiKey,
            'Accept: application/json',
        );
        if ($jsonBody !== null) {
            $headers[] = 'Content-Type: application/json';
        }

        $ch = curl_init($this->baseUrl . $path);
        if ($ch === false) {
            throw new TransportException('cURL error: could not initialise a curl handle');
        }

        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, strtoupper($method));
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, self::CONNECT_TIMEOUT);
        curl_setopt($ch, CURLOPT_TIMEOUT, self::TOTAL_TIMEOUT);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
        if ($jsonBody !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonBody);
        }

        $body = curl_exec($ch);
        if ($body === false) {
            $errno = curl_errno($ch);
            $error = curl_error($ch);
            curl_close($ch);
            throw new TransportException('cURL error ' . $errno . ': ' . $error);
        }

        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return array(
            'status' => $status,
            'body' => (string) $body,
        );
    }
}
