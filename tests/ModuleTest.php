<?php

// Mock WHMCS environment
if (!defined("WHMCS")) {
    define("WHMCS", true);
}

require_once __DIR__ . '/../modules/registrars/spaceship/spaceship.php';

use Spaceship\ApiClient;

// Mock ApiClient to avoid real network calls
class MockApiClient extends ApiClient
{
    private $mockResponses = [];

    public function __construct()
    {
        parent::__construct('test_key', 'test_secret', true);
    }

    public function setMockResponse($method, $endpoint, $response, $code = 200)
    {
        $this->mockResponses[$method . $endpoint] = ['body' => $response, 'code' => $code];
    }

    public function request($method, $endpoint, $params = [], $action = '')
    {
        $key = $method . $endpoint;
        if (isset($this->mockResponses[$key])) {
            if ($this->mockResponses[$key]['code'] >= 400) {
                throw new \Exception("Mock API Error: " . ($this->mockResponses[$key]['body']['detail'] ?? 'Unknown'));
            }
            return $this->mockResponses[$key]['body'];
        }
        throw new \Exception("No mock response for $key");
    }
}

// Override the internal client getter for testing
function _spaceship_get_client($params)
{
    global $mockClient;
    return $mockClient;
}

$mockClient = new MockApiClient();

// Test cases
function runTests()
{
    global $mockClient;

    echo "Running Tests...\n";

    // 1. Test Config Array
    $config = spaceship_getConfigArray();
    if ($config['FriendlyName']['Value'] === 'Spaceship') {
        echo "[PASS] getConfigArray\n";
    } else {
        echo "[FAIL] getConfigArray\n";
    }

    // 2. Test Sync
    $mockClient->setMockResponse('GET', '/domains/example.com', [
        'expirationDate' => '2025-12-31T00:00:00.000Z',
        'lifecycleStatus' => 'registered'
    ]);
    $sync = spaceship_Sync(['domainname' => 'example.com', 'APIKey' => 't', 'APISecret' => 's', 'TestMode' => 'on']);
    if ($sync['expirydate'] === '2025-12-31' && $sync['active'] === true) {
        echo "[PASS] Sync\n";
    } else {
        echo "[FAIL] Sync: " . print_r($sync, true) . "\n";
    }

    // 3. Test Registration Flow
    $mockClient->setMockResponse('POST', '/contacts', ['contactId' => 'contact_123']);
    $mockClient->setMockResponse('POST', '/domains/example.com/register', ['success' => true]);
    $regParams = [
        'domainname' => 'example.com',
        'regperiod' => 1,
        'ns1' => 'ns1.test.com',
        'ns2' => 'ns2.test.com',
        'APIKey' => 't',
        'APISecret' => 's',
        'TestMode' => 'on',
    ];
    // Fill in contact fields
    foreach (['registrant', 'admin', 'tech', 'billing'] as $t) {
        $regParams["{$t}firstname"] = 'J';
        $regParams["{$t}lastname"] = 'D';
        $regParams["{$t}companyname"] = 'C';
        $regParams["{$t}email"] = 'e@e.com';
        $regParams["{$t}address1"] = 'a1';
        $regParams["{$t}address2"] = 'a2';
        $regParams["{$t}city"] = 'c';
        $regParams["{$t}country"] = 'US';
        $regParams["{$t}fullstate"] = 'NY';
        $regParams["{$t}postcode"] = '10001';
        $regParams["{$t}phonenumberformatted"] = '+1.123456789';
    }

    $regResult = spaceship_RegisterDomain($regParams);
    if (isset($regResult['success']) && $regResult['success'] === true) {
        echo "[PASS] RegisterDomain\n";
    } else {
        echo "[FAIL] RegisterDomain: " . ($regResult['error'] ?? 'Unknown') . "\n";
    }

    // 4. Test Get Nameservers
    $mockClient->setMockResponse('GET', '/domains/example.com', [
        'nameservers' => [
            'hosts' => ['ns1.spaceship.com', 'ns2.spaceship.com']
        ]
    ]);
    $nsResult = spaceship_GetNameservers(['domainname' => 'example.com', 'APIKey' => 't', 'APISecret' => 's', 'TestMode' => 'on']);
    if ($nsResult['ns1'] === 'ns1.spaceship.com' && $nsResult['ns2'] === 'ns2.spaceship.com') {
        echo "[PASS] GetNameservers\n";
    } else {
        echo "[FAIL] GetNameservers\n";
    }

    // 5. Test Transfer Flow
    $mockClient->setMockResponse('POST', '/domains/transfer.com/transfer', ['success' => true]);
    $transParams = $regParams;
    $transParams['domainname'] = 'transfer.com';
    $transResult = spaceship_TransferDomain($transParams);
    if (isset($transResult['success']) && $transResult['success'] === true) {
        echo "[PASS] TransferDomain\n";
    } else {
        echo "[FAIL] TransferDomain: " . ($transResult['error'] ?? 'Unknown') . "\n";
    }

    // 6. Test Renewal Flow
    $mockClient->setMockResponse('POST', '/domains/renew.com/renew', ['success' => true]);
    $renewResult = spaceship_RenewDomain(['domainname' => 'renew.com', 'regperiod' => 1, 'APIKey' => 't', 'APISecret' => 's', 'TestMode' => 'on']);
    if (isset($renewResult['success']) && $renewResult['success'] === true) {
        echo "[PASS] RenewDomain\n";
    } else {
        echo "[FAIL] RenewDomain: " . ($renewResult['error'] ?? 'Unknown') . "\n";
    }

    // 7. Test DNS Get Records
    $mockClient->setMockResponse('GET', '/dns/records/dns.com', [
        'items' => [
            ['name' => '@', 'type' => 'A', 'address' => '192.168.1.1'],
            ['name' => 'mail', 'type' => 'MX', 'target' => 'mail.dns.com', 'priority' => 10],
            ['name' => '_service', 'type' => 'SRV', 'target' => 'server.dns.com', 'priority' => 5]
        ]
    ]);
    $dnsGetResult = spaceship_GetDNS(['domainname' => 'dns.com', 'APIKey' => 't', 'APISecret' => 's', 'TestMode' => 'on']);
    $expectedRecords = [
        ['hostname' => '@', 'type' => 'A', 'address' => '192.168.1.1', 'priority' => ''],
        ['hostname' => 'mail', 'type' => 'MX', 'address' => 'mail.dns.com', 'priority' => '10'],
        ['hostname' => '_service', 'type' => 'SRV', 'address' => 'server.dns.com', 'priority' => '5']
    ];
    if ($dnsGetResult == $expectedRecords) {
        echo "[PASS] GetDNS\n";
    } else {
        echo "[FAIL] GetDNS: " . print_r($dnsGetResult, true) . "\n";
    }

    // 8. Test DNS Save Records
    $mockClient->setMockResponse('POST', '/dns/records/save.com', ['success' => true]);
    $dnsSaveResult = spaceship_SaveDNS([
        'domainname' => 'save.com',
        'dnsrecords' => [
            ['hostname' => 'www', 'type' => 'A', 'address' => '192.168.1.2'],
            ['hostname' => 'mail', 'type' => 'MX', 'address' => 'mail.save.com', 'priority' => 20],
            ['hostname' => '_service', 'type' => 'SRV', 'address' => 'service.save.com', 'priority' => 10]
        ]
    ]);
    if (isset($dnsSaveResult['success']) && $dnsSaveResult['success'] === true) {
        echo "[PASS] SaveDNS\n";
    } else {
        echo "[FAIL] SaveDNS: " . ($dnsSaveResult['error'] ?? 'Unknown') . "\n";
    }
}

runTests();
