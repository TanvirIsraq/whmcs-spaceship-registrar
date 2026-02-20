<?php

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

require_once __DIR__ . '/lib/Spaceship/ApiClient.php';
require_once __DIR__ . '/lib/Spaceship/Cache.php';

use Spaceship\ApiClient;
use Spaceship\Cache;
use WHMCS\Database\Capsule;

/**
 * Supported registrar configuration options.
 * 
 * include_balance signals WHMCS to show the account balance on the 
 * configuration page, supported via our custom GetAccountBalance function.
 */
function spaceship_GetRegistrarConfigOptions()
{
    return [
        'include_balance' => true,
    ];
}

/**
 * Define module configuration options.
 *
 * @return array
 */
function spaceship_getConfigArray()
{
    return [
        'FriendlyName' => [
            'Type' => 'System',
            'Value' => 'Spaceship',
        ],
        'Instructions' => [
            'FriendlyName' => 'Module Info',
            'Type' => 'System',
            'Value' => '<div class="alert alert-info">
                <strong>Spaceship.com Registrar Module v2.1.0</strong><br />
                This module allows you to automate domain registration and management via the Spaceship Public API.<br />
                <ul style="margin-top: 5px;">
                    <li>Obtain your API credentials from the <a href="https://www.spaceship.com/application/api-manager/" target="_blank" class="alert-link">Spaceship API Manager</a>.</li>
                    <li>Ensure your server IP is whitelisted in your Spaceship API settings.</li>
                    <li><strong>Note:</strong> Spaceship Sandbox is currently unavailable. Test Mode is for future use.</li>
                </ul>
            </div>',
        ],
        'APIKey' => [
            'FriendlyName' => 'API Key',
            'Type' => 'text',
            'Size' => '100',
            'Default' => '',
            'Description' => 'Enter your Spaceship API Key',
        ],
        'APISecret' => [
            'FriendlyName' => 'API Secret',
            'Type' => 'password',
            'Size' => '100',
            'Default' => '',
            'Description' => 'Enter your Spaceship API Secret',
        ],
        'TestMode' => [
            'FriendlyName' => 'Test Mode',
            'Type' => 'yesno',
            'Description' => 'Tick to enable test mode (Currently Unavailable - Spaceship has no public sandbox yet)',
        ],
    ];
}

/**
 * Activate the module and initialize the cache table.
 */
function spaceship_activate()
{
    try {
        Cache::init();
        return ['status' => 'success', 'description' => 'Spaceship module activated and cache table initialized.'];
    } catch (\Exception $e) {
        return ['status' => 'error', 'description' => 'Could not create cache table: ' . $e->getMessage()];
    }
}

/**
 * Get internal API client instance
 *
 * @param array $params
 * @return ApiClient
 */
function _spaceship_get_client($params)
{
    return new ApiClient(
        $params['APIKey'],
        $params['APISecret'],
        $params['TestMode'] === 'on'
    );
}

/**
 * Global cache to prevent multiple contact creation calls in same process.
 */
$spaceshipContactCache = [];

/**
 * Helper to fetch domain info with persistent database caching.
 *
 * @param array $params
 * @param bool $force Force fresh fetch
 * @return array
 */
function _spaceship_get_domain_info($params, $force = false)
{
    $domain = $params['domainname'];

    if (!$force) {
        $cached = Cache::get($domain, 'domain_info');
        if ($cached) {
            return $cached;
        }
    }

    $client = _spaceship_get_client($params);
    $result = $client->request('GET', "/domains/{$domain}", [], 'GetDomainInfo');

    // Cache for 290 seconds (slightly less than 5 mins to be safe)
    Cache::set($domain, 'domain_info', $result, 290);
    return $result;
}

/**
 * Helper to fetch privacy status with persistent database caching.
 */
function _spaceship_get_privacy_status($params, $force = false)
{
    $domain = $params['domainname'];

    if (!$force) {
        $cached = Cache::get($domain, 'privacy_status');
        if ($cached) {
            return $cached;
        }
    }

    $client = _spaceship_get_client($params);
    $result = $client->request('GET', "/domains/{$domain}/privacy/preference", [], 'GetIDProtectStatus');

    $status = ($result['isPrivacyEnabled']) ? 'on' : 'off';
    Cache::set($domain, 'privacy_status', $status, 290);
    return $status;
}

/**
 * Helper to prepare contact data from WHMCS params
 *
 * @param array $params
 * @param string $type registrant, admin, tech, or billing
 * @return array
 */
function _spaceship_prepare_contact_data($params, $type)
{
    return [
        'firstName' => $params["{$type}firstname"],
        'lastName' => $params["{$type}lastname"],
        'organization' => $params["{$type}companyname"],
        'email' => $params["{$type}email"],
        'address1' => $params["{$type}address1"],
        'address2' => $params["{$type}address2"],
        'city' => $params["{$type}city"],
        'country' => $params["{$type}country"],
        'stateProvince' => $params["{$type}fullstate"],
        'postalCode' => $params["{$type}postcode"],
        'phone' => $params["{$type}phonenumberformatted"],
    ];
}

/**
 * Helper to save contact and return ID, with basic in-memory deduplication
 *
 * @param ApiClient $client
 * @param array $params
 * @param string $type
 * @return string ContactID
 */
function _spaceship_save_contact($client, $params, $type)
{
    global $spaceshipContactCache;

    $data = _spaceship_prepare_contact_data($params, $type);
    $cacheKey = md5(json_encode($data));

    if (isset($spaceshipContactCache[$cacheKey])) {
        return $spaceshipContactCache[$cacheKey];
    }

    $result = $client->request('POST', '/contacts', $data, 'SaveContact');
    $spaceshipContactCache[$cacheKey] = $result['contactId'];

    return $result['contactId'];
}

/**
 * Get the current account balance from Spaceship.
 *
 * @param array $params
 * @return array
 */
function spaceship_GetAccountBalance($params)
{
    $client = _spaceship_get_client($params);

    try {
        // Fallback: If no direct balance endpoint exists, we try a common metadata endpoint
        // For now, returning a static note since Spaceship API v1 does not have /balance
        return [
            'balance' => 'See Dashboard',
            'currency' => 'USD',
        ];
    } catch (\Exception $e) {
        return ['error' => $e->getMessage()];
    }
}

/**
 * Register a domain.
 *
 * @param array $params
 * @return array
 */
function spaceship_RegisterDomain($params)
{
    $client = _spaceship_get_client($params);

    try {
        // 1. Prepare Contacts
        $contactTypes = ['registrant', 'admin', 'tech', 'billing'];
        $contactIds = [];

        foreach ($contactTypes as $type) {
            $contactIds[$type] = _spaceship_save_contact($client, $params, $type);
        }

        // 2. Register Domain
        $registrationData = [
            'autoRenew' => false,
            'years' => $params['regperiod'],
            'contacts' => [
                'registrant' => $contactIds['registrant'],
                'admin' => $contactIds['admin'],
                'tech' => $contactIds['tech'],
                'billing' => $contactIds['billing'],
            ],
            'nameservers' => [
                'provider' => 'custom',
                'hosts' => [
                    $params['ns1'],
                    $params['ns2'],
                ],
            ],
        ];

        if (!empty($params['ns3']))
            $registrationData['nameservers']['hosts'][] = $params['ns3'];
        if (!empty($params['ns4']))
            $registrationData['nameservers']['hosts'][] = $params['ns4'];
        if (!empty($params['ns5']))
            $registrationData['nameservers']['hosts'][] = $params['ns5'];

        $client->request('POST', "/domains/{$params['domainname']}/register", $registrationData, 'RegisterDomain');

        return ['success' => true];
    } catch (\Exception $e) {
        if (function_exists('logModuleCall')) {
            logModuleCall('spaceship', 'RegisterDomain Error', $params, $e->getMessage());
        }
        return ['error' => $e->getMessage()];
    }
}

/**
 * Renew a domain.
 *
 * @param array $params
 * @return array
 */
function spaceship_RenewDomain($params)
{
    $client = _spaceship_get_client($params);

    try {
        $renewalData = [
            'years' => $params['regperiod'],
        ];

        $client->request('POST', "/domains/{$params['domainname']}/renew", $renewalData, 'RenewDomain');

        return ['success' => true];
    } catch (\Exception $e) {
        if (function_exists('logModuleCall')) {
            logModuleCall('spaceship', 'RenewDomain Error', $params, $e->getMessage());
        }
        return ['error' => $e->getMessage()];
    }
}

/**
 * Transfer a domain.
 *
 * @param array $params
 * @return array
 */
function spaceship_TransferDomain($params)
{
    $client = _spaceship_get_client($params);

    try {
        // 1. Prepare Contacts
        $contactTypes = ['registrant', 'admin', 'tech', 'billing'];
        $contactIds = [];

        foreach ($contactTypes as $type) {
            $contactIds[$type] = _spaceship_save_contact($client, $params, $type);
        }

        // 2. Request Transfer
        $transferData = [
            'authCode' => $params['eppcode'],
            'autoRenew' => false,
            'years' => 1,
            'contacts' => [
                'registrant' => $contactIds['registrant'],
                'admin' => $contactIds['admin'],
                'tech' => $contactIds['tech'],
                'billing' => $contactIds['billing'],
            ],
        ];

        $client->request('POST', "/domains/{$params['domainname']}/transfer", $transferData, 'TransferDomain');

        return ['success' => true];
    } catch (\Exception $e) {
        if (function_exists('logModuleCall')) {
            logModuleCall('spaceship', 'TransferDomain Error', $params, $e->getMessage());
        }
        return ['error' => $e->getMessage()];
    }
}

/**
 * Get the nameservers for a domain.
 *
 * @param array $params
 * @return array
 */
function spaceship_GetNameservers($params)
{
    try {
        $result = _spaceship_get_domain_info($params);

        $ns = [];
        if (isset($result['nameservers']['hosts'])) {
            foreach ($result['nameservers']['hosts'] as $i => $host) {
                $ns['ns' . ($i + 1)] = $host;
            }
        }

        return $ns;
    } catch (\Exception $e) {
        if (function_exists('logModuleCall')) {
            logModuleCall('spaceship', 'GetNameservers Error', $params, $e->getMessage());
        }
        return ['error' => $e->getMessage()];
    }
}

/**
 * Save nameservers for a domain.
 *
 * @param array $params
 * @return array
 */
function spaceship_SaveNameservers($params)
{
    $client = _spaceship_get_client($params);

    try {
        $hosts = [
            $params['ns1'],
            $params['ns2'],
        ];

        if (!empty($params['ns3']))
            $hosts[] = $params['ns3'];
        if (!empty($params['ns4']))
            $hosts[] = $params['ns4'];
        if (!empty($params['ns5']))
            $hosts[] = $params['ns5'];

        $nsData = [
            'provider' => 'custom',
            'hosts' => $hosts,
        ];

        $client->request('PUT', "/domains/{$params['domainname']}/nameservers", $nsData, 'SaveNameservers');

        // Clear cache so user sees new NS immediately
        Cache::clear($params['domainname']);

        return ['success' => true];
    } catch (\Exception $e) {
        return ['error' => $e->getMessage()];
    }
}

/**
 * Get registrar lock status.
 *
 * @param array $params
 * @return string lock, unlock
 */
function spaceship_GetRegistrarLock($params)
{
    try {
        /** 
         * Important: The specific /transfer/lock endpoint only supports PUT.
         * We retrieve status from the main domain info object instead.
         */
        $result = _spaceship_get_domain_info($params);

        $isLocked = false;

        // Method 1: direct isLocked boolean field
        if (isset($result['isLocked'])) {
            $isLocked = (bool) $result['isLocked'];
        }

        /**
         * Method 2: Fallback to EPP Statuses. 
         * 'clientTransferProhibited' indicates an active lock at the registry.
         */
        if (!$isLocked && isset($result['eppStatuses']) && is_array($result['eppStatuses'])) {
            if (in_array('clientTransferProhibited', $result['eppStatuses'])) {
                $isLocked = true;
            }
        }

        return $isLocked ? 'locked' : 'unlocked';
    } catch (\Exception $e) {
        if (function_exists('logModuleCall')) {
            logModuleCall('spaceship', 'GetRegistrarLock Error', $params, $e->getMessage());
        }
        /**
         * Return 'unlocked' on error to allow the user to see the 'Enable Lock' button,
         * which serves as a pathway for them to attempt to fix the state.
         */
        return 'unlocked';
    }
}

/**
 * Get the current transfer status of a domain.
 *
 * @param array $params
 * @return array
 */
function spaceship_GetTransferStatus($params)
{
    $client = _spaceship_get_client($params);

    try {
        $result = $client->request('GET', "/domains/{$params['domainname']}/transfer", [], 'GetTransferStatus');

        // Map Spaceship status (pending, completed, failed)
        return [
            'status' => $result['status'],
        ];
    } catch (\Exception $e) {
        if (function_exists('logModuleCall')) {
            logModuleCall('spaceship', 'GetTransferStatus Error', $params, $e->getMessage());
        }
        return ['error' => $e->getMessage()];
    }
}

/**
 * Update registrar lock status.
 *
 * @param array $params
 * @return array
 */
function spaceship_SaveRegistrarLock($params)
{
    $client = _spaceship_get_client($params);

    // WHMCS sends 'locked' or 'unlocked' in 'lockenabled' field
    $lockEnabled = isset($params['lockenabled']) ? $params['lockenabled'] : '';
    $isLocked = ($lockEnabled === 'locked');

    try {
        $client->request('PUT', "/domains/{$params['domainname']}/transfer/lock", [
            'isLocked' => (bool) $isLocked
        ], 'SaveRegistrarLock');

        // Clear cache
        Cache::clear($params['domainname']);

        return ['success' => true];
    } catch (\Exception $e) {
        if (function_exists('logModuleCall')) {
            logModuleCall('spaceship', 'SaveRegistrarLock Error', $params, $e->getMessage());
        }
        return ['error' => 'Failed to update lock status: ' . $e->getMessage()];
    }
}

/**
 * Get ID Protection (WHOIS Privacy) status.
 *
 * @param array $params
 * @return string
 */
function spaceship_GetIDProtectStatus($params)
{
    try {
        return _spaceship_get_privacy_status($params);
    } catch (\Exception $e) {
        if (function_exists('logModuleCall')) {
            logModuleCall('spaceship', 'GetIDProtectStatus Error', $params, $e->getMessage());
        }
        return $e->getMessage();
    }
}

/**
 * Toggle ID Protection (WHOIS Privacy)
 *
 * @param array $params
 * @return array
 */
function spaceship_IDProtectToggle($params)
{
    $client = _spaceship_get_client($params);
    $status = (bool) $params['protectenable'];

    try {
        // Spaceship typically uses a 'preference' endpoint for privacy
        $client->request('PUT', "/domains/{$params['domainname']}/privacy/preference", ['isPrivacyEnabled' => $status], 'IDProtectToggle');

        // Clear cache
        Cache::clear($params['domainname']);

        return ['success' => true];
    } catch (\Exception $e) {
        if (function_exists('logModuleCall')) {
            logModuleCall('spaceship', 'IDProtectToggle Error', $params, $e->getMessage());
        }
        return ['error' => $e->getMessage()];
    }
}

/**
 * Get domain auth code.
 *
 * @param array $params
 * @return array
 */
function spaceship_GetEPPCode($params)
{
    $client = _spaceship_get_client($params);

    try {
        $result = $client->request('GET', "/domains/{$params['domainname']}/transfer/auth-code", [], 'GetEPPCode');

        return [
            'eppcode' => $result['authCode']
        ];
    } catch (\Exception $e) {
        if (function_exists('logModuleCall')) {
            logModuleCall('spaceship', 'GetEPPCode Error', $params, $e->getMessage());
        }
        return ['error' => $e->getMessage()];
    }
}

/**
 * Synchronize domain status and expiry.
 * 
 * Uses forceful refresh to bypass the in-memory cache and get live 
 * data from the registrar.
 *
 * @param array $params
 * @return array
 */
function spaceship_Sync($params)
{
    try {
        $result = _spaceship_get_domain_info($params, true);

        $expiry = new \DateTime($result['expirationDate']);
        $status = $result['lifecycleStatus'];

        /**
         * Status Mapping:
         * Spaceship uses granular lifecycle statuses that need to be grouped
         * for WHMCS's binary Active/Expired/Redemption logic.
         */
        return [
            'expirydate' => $expiry->format('Y-m-d'),
            'active' => ($status === 'registered'),
            'expired' => (in_array($status, ['expired', 'grace1', 'grace2', 'redemption'])),
            'redemption' => ($status === 'redemption'),
        ];
    } catch (\Exception $e) {
        if (function_exists('logModuleCall')) {
            logModuleCall('spaceship', 'Sync Error', $params, $e->getMessage());
        }
        return ['error' => $e->getMessage()];
    }
}

/**
 * Get the contact details for a domain.
 *
 * @param array $params
 * @return array
 */
function spaceship_GetContactDetails($params)
{
    $client = _spaceship_get_client($params);

    try {
        $domainInfo = _spaceship_get_domain_info($params);
        $contacts = [];

        foreach (['registrant', 'admin', 'tech', 'billing'] as $type) {
            if (isset($domainInfo['contacts'][$type])) {
                $contactId = $domainInfo['contacts'][$type];
                $details = $client->request('GET', "/contacts/{$contactId}", [], 'GetContactDetails');

                $whmcsType = ucfirst($type);
                $contacts[$whmcsType] = [
                    'First Name' => $details['firstName'],
                    'Last Name' => $details['lastName'],
                    'Organization Name' => $details['organization'],
                    'Email Address' => $details['email'],
                    'Address 1' => $details['address1'],
                    'Address 2' => $details['address2'],
                    'City' => $details['city'],
                    'State' => $details['stateProvince'],
                    'Postcode' => $details['postalCode'],
                    'Country' => $details['country'],
                    'Phone Number' => $details['phone'],
                ];
            }
        }

        return $contacts;
    } catch (\Exception $e) {
        return ['error' => $e->getMessage()];
    }
}

/**
 * Save contact details for a domain.
 *
 * @param array $params
 * @return array
 */
function spaceship_SaveContactDetails($params)
{
    $client = _spaceship_get_client($params);

    try {
        $contactIds = [];

        // We need to map WHMCS Contact Details array to the format our helper expects
        // or just use the same deduplication logic here manually.
        foreach (['Registrant', 'Admin', 'Tech', 'Billing'] as $type) {
            $typeLower = strtolower($type);
            $details = $params['contactdetails'][$type];

            $contactData = [
                'firstName' => $details['First Name'],
                'lastName' => $details['Last Name'],
                'organization' => $details['Organization Name'],
                'email' => $details['Email Address'],
                'address1' => $details['Address 1'],
                'address2' => $details['Address 2'],
                'city' => $details['City'],
                'country' => $details['Country'],
                'stateProvince' => $details['State'],
                'postalCode' => $details['Postcode'],
                'phone' => $details['Phone Number'],
            ];

            // Use the same in-memory cache logic
            global $spaceshipContactCache;
            $cacheKey = md5(json_encode($contactData));

            if (isset($spaceshipContactCache[$cacheKey])) {
                $contactIds[$typeLower] = $spaceshipContactCache[$cacheKey];
            } else {
                $contactResult = $client->request('POST', '/contacts', $contactData, 'SaveContactDetails');
                $contactIds[$typeLower] = $contactResult['contactId'];
                $spaceshipContactCache[$cacheKey] = $contactResult['contactId'];
            }
        }

        $client->request('PUT', "/domains/{$params['domainname']}/contacts", $contactIds, 'UpdateDomainContacts');

        // Clear cache
        Cache::clear($params['domainname']);

        return ['success' => true];
    } catch (\Exception $e) {
        if (function_exists('logModuleCall')) {
            logModuleCall('spaceship', 'SaveContactDetails Error', $params, $e->getMessage());
        }
        return ['error' => $e->getMessage()];
    }
}

/**
 * Get DNS records for a domain.
 *
 * @param array $params
 * @return array
 */
function spaceship_GetDNS($params)
{
    $client = _spaceship_get_client($params);

    try {
        // Spaceship requires 'take' and 'skip' for DNS record retrieval
        $requestParams = [
            'take' => 100, // Fetch up to 100 records
            'skip' => 0
        ];

        $result = $client->request('GET', "/dns/records/{$params['domainname']}", $requestParams, 'GetDNS');
        $records = [];

        if (isset($result['items'])) {
            foreach ($result['items'] as $item) {
                // Map Spaceship fields to WHMCS
                $records[] = [
                    'hostname' => $item['name'],
                    'type' => $item['type'],
                    'address' => isset($item['address']) ? $item['address'] : (isset($item['target']) ? $item['target'] : ''),
                    'priority' => isset($item['priority']) ? $item['priority'] : '',
                ];
            }
        }

        return $records;
    } catch (\Exception $e) {
        $message = $e->getMessage();

        // Friendly translation for common DNS setup issues
        if (strpos($message, '404') !== false) {
            $message = "DNS records not found. This domain might not be using Spaceship nameservers.";
        }

        if (function_exists('logModuleCall')) {
            logModuleCall('spaceship', 'GetDNS Error', $params, $e->getMessage());
        }
        return ['error' => $message];
    }
}

/**
 * Save DNS records for a domain.
 *
 * @param array $params
 * @return array
 */
function spaceship_SaveDNS($params)
{
    $client = _spaceship_get_client($params);

    try {
        $recordsToSync = [];
        foreach ($params['dnsrecords'] as $record) {
            if (empty($record['hostname']) || empty($record['address']))
                continue;

            $recordData = [
                'type' => $record['type'],
                'name' => $record['hostname'],
                'ttl' => 3600,
            ];

            if ($record['type'] === 'MX') {
                $recordData['target'] = $record['address'];
                $recordData['priority'] = !empty($record['priority']) ? intval($record['priority']) : 10;
            } elseif (in_array($record['type'], ['CNAME', 'SRV'])) {
                $recordData['target'] = $record['address'];
                if ($record['type'] === 'SRV' && !empty($record['priority'])) {
                    $recordData['priority'] = intval($record['priority']);
                }
            } else {
                $recordData['address'] = $record['address'];
            }

            $recordsToSync[] = $recordData;
        }

        if (!empty($recordsToSync)) {
            // Spaceship API Save Records expects an array of records
            $client->request('POST', "/dns/records/{$params['domainname']}", $recordsToSync, 'SaveDNS');
        }

        return ['success' => true];
    } catch (\Exception $e) {
        return ['error' => $e->getMessage()];
    }
}

/**
 * Register a personal nameserver (Child Nameserver).
 *
 * @param array $params
 * @return array
 */
function spaceship_RegisterNameserver($params)
{
    $client = _spaceship_get_client($params);

    /**
     * Formatting Note:
     * WHMCS sends the full nameserver (ns1.example.com).
     * Spaceship API expects only the host prefix (ns1).
     */
    $host = str_replace('.' . $params['domainname'], '', $params['nameserver']);

    try {
        $client->request('POST', "/domains/{$params['domainname']}/personal-nameservers", [
            'host' => $host,
            'ip' => $params['ipaddress'],
        ], 'RegisterNameserver');
        return ['success' => true];
    } catch (\Exception $e) {
        if (function_exists('logModuleCall')) {
            logModuleCall('spaceship', 'RegisterNameserver Error', $params, $e->getMessage());
        }
        return ['error' => $e->getMessage()];
    }
}

/**
 * Modify a personal nameserver (Child Nameserver).
 *
 * @param array $params
 * @return array
 */
function spaceship_ModifyNameserver($params)
{
    $client = _spaceship_get_client($params);
    $host = str_replace('.' . $params['domainname'], '', $params['nameserver']);

    try {
        $client->request('PUT', "/domains/{$params['domainname']}/personal-nameservers/{$host}", [
            'oldIp' => $params['currentipaddress'],
            'newIp' => $params['newipaddress'],
        ], 'ModifyNameserver');
        return ['success' => true];
    } catch (\Exception $e) {
        if (function_exists('logModuleCall')) {
            logModuleCall('spaceship', 'ModifyNameserver Error', $params, $e->getMessage());
        }
        return ['error' => $e->getMessage()];
    }
}

/**
 * Delete a personal nameserver (Child Nameserver).
 *
 * @param array $params
 * @return array
 */
function spaceship_DeleteNameserver($params)
{
    $client = _spaceship_get_client($params);
    $host = str_replace('.' . $params['domainname'], '', $params['nameserver']);

    try {
        $client->request('DELETE', "/domains/{$params['domainname']}/personal-nameservers/{$host}", [], 'DeleteNameserver');
        return ['success' => true];
    } catch (\Exception $e) {
        if (function_exists('logModuleCall')) {
            logModuleCall('spaceship', 'DeleteNameserver Error', $params, $e->getMessage());
        }
        return ['error' => $e->getMessage()];
    }
}

/**
 * Check the availability of one or more domains.
 *
 * @param array $params
 * @return \WHMCS\Domains\DomainLookup\ResultsList
 */
function spaceship_CheckAvailability($params)
{
    $client = _spaceship_get_client($params);
    $searchTerm = $params['searchTerm'];
    $tldsToInclude = $params['tldsToInclude'];
    $isOverride = $params['isOverride'];

    $domainsToCheck = [];
    if ($isOverride) {
        $domainsToCheck[] = $searchTerm;
    } else {
        foreach ($tldsToInclude as $tld) {
            $domainsToCheck[] = $searchTerm . $tld;
        }
    }

    try {
        $result = $client->request('POST', "/domains/available", ['domains' => $domainsToCheck], 'CheckAvailability');
        $results = new \WHMCS\Domains\DomainLookup\ResultsList();

        foreach ($result['items'] as $item) {
            $searchResult = new \WHMCS\Domains\DomainLookup\SearchResult($item['domain'], $item['tld']);

            if ($item['isAvailable']) {
                $status = \WHMCS\Domains\DomainLookup\SearchResult::STATUS_NOT_REGISTERED;
            } else {
                $status = \WHMCS\Domains\DomainLookup\SearchResult::STATUS_REGISTERED;
            }

            $searchResult->setStatus($status);
            $results->append($searchResult);
        }

        return $results;
    } catch (\Exception $e) {
        return new \WHMCS\Domains\DomainLookup\ResultsList();
    }
}
