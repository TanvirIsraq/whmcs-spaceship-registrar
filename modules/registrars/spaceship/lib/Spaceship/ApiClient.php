<?php

namespace Spaceship;

class ApiClient
{
    private $apiKey;
    private $apiSecret;
    private $testMode;
    private $baseUrl = 'https://spaceship.dev/api/v1';

    private $lastRequest;
    private $lastResponse;

    /**
     * ApiClient constructor.
     *
     * @param string $apiKey
     * @param string $apiSecret
     * @param bool $testMode
     */
    public function __construct($apiKey, $apiSecret, $testMode = false)
    {
        $this->apiKey = $apiKey;
        $this->apiSecret = $apiSecret;
        $this->testMode = (bool) $testMode;

        if ($this->testMode) {
            $this->baseUrl = 'https://sandbox.spaceship.dev/api/v1';
        }
    }

    /**
     * Send a request to the Spaceship API
     *
     * @param string $method GET, POST, PUT, DELETE
     * @param string $endpoint API endpoint (e.g., /domains)
     * @param array $params Request parameters
     * @param string $action Optional action name for WHMCS logging
     * @return array API response
     * @throws \Exception
     */
    public function request($method, $endpoint, $params = [], $action = '')
    {
        $url = $this->baseUrl . $endpoint;
        $ch = \curl_init();

        $headers = [
            'X-API-Key: ' . $this->apiKey,
            'X-API-Secret: ' . $this->apiSecret,
            'Content-Type: application/json',
            'Accept: application/json',
            'User-Agent: WHMCS-Spaceship-Module/2.2.2',
        ];

        $requestBody = '';
        if ($method === 'GET' && !empty($params)) {
            $url .= '?' . \http_build_query($params);
        } elseif (\in_array($method, ['POST', 'PUT', 'PATCH'])) {
            $requestBody = \json_encode($params);
            \curl_setopt($ch, \CURLOPT_POSTFIELDS, $requestBody);
        }

        $this->lastRequest = [
            'url' => $url,
            'method' => $method,
            'headers' => $headers,
            'body' => $params
        ];

        \curl_setopt($ch, \CURLOPT_URL, $url);
        \curl_setopt($ch, \CURLOPT_RETURNTRANSFER, true);
        \curl_setopt($ch, \CURLOPT_CUSTOMREQUEST, $method);
        \curl_setopt($ch, \CURLOPT_HTTPHEADER, $headers);
        \curl_setopt($ch, \CURLOPT_SSL_VERIFYPEER, true);
        \curl_setopt($ch, \CURLOPT_SSL_VERIFYHOST, 2);
        \curl_setopt($ch, \CURLOPT_TIMEOUT, 30);

        $response = \curl_exec($ch);
        $httpCode = \curl_getinfo($ch, \CURLINFO_HTTP_CODE);
        $error = \curl_error($ch);
        \curl_close($ch);

        $this->lastResponse = [
            'httpCode' => $httpCode,
            'body' => $response,
            'error' => $error
        ];

        // Log to WHMCS Module Log if function exists
        if (\function_exists('logModuleCall') && !empty($action)) {
            $processedResponse = \json_decode($response, true);

            // If response is empty but we have a success code, provide a helpful message
            if (empty($response) && $httpCode >= 200 && $httpCode < 300) {
                $processedResponse = ['status' => 'success', 'http_code' => $httpCode, 'message' => 'Empty response (typical for 204 No Content)'];
            } elseif (empty($response)) {
                $processedResponse = ['status' => 'error', 'http_code' => $httpCode, 'message' => 'Empty response from server'];
            }

            \logModuleCall(
                'spaceship',
                $action,
                $this->lastRequest,
                $response, // raw
                $processedResponse, // processed
                [$this->apiKey, $this->apiSecret] // Sensitive data to mask
            );
        }

        if ($error) {
            throw new \Exception("Spaceship API Curl Error: " . $error);
        }

        $result = \json_decode($response, true);

        if ($httpCode >= 400) {
            $message = isset($result['detail']) ? $result['detail'] : 'Unknown API Error';
            throw new \Exception("Spaceship API Error ($httpCode): " . $message);
        }

        return $result;
    }

    public function getLastRequest()
    {
        return $this->lastRequest;
    }
    public function getLastResponse()
    {
        return $this->lastResponse;
    }
}
