<?php

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

require_once __DIR__ . '/lib/Spaceship/ApiClient.php';
require_once __DIR__ . '/lib/Spaceship/Cache.php';

// Load JobFew Framework (Global Addon Helper), or local Premium/ copy as fallback
$_jfBridgeGlobal = defined('ROOTDIR') ? ROOTDIR . '/modules/addons/jobfew_helper/lib/JobFew/Premium/Bridge.php' : '';
$_jfBridgeLocal = __DIR__ . '/lib/Spaceship/Premium/Bridge.php';

if (!empty($_jfBridgeGlobal) && \file_exists($_jfBridgeGlobal)) {
    require_once $_jfBridgeGlobal;
} elseif (\file_exists($_jfBridgeLocal)) {
    require_once $_jfBridgeLocal;
}
unset($_jfBridgeGlobal, $_jfBridgeLocal);

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
    $config = [
        'Description' => [
            'FriendlyName' => ' ',
            'Type' => 'System',
            'Value' => '<div class="alert alert-info" style="margin: 5px 0;">
                <h4 style="margin: 0 0 10px 0;">Spaceship.com Registrar Module v2.2.3{PRO_BADGE}</h4>
                <p style="margin-bottom: 10px;">Automate domain registration and management via the Spaceship Public API.</p>
                <div style="margin: 10px 0;">{STATUS_LINE}</div>
                <hr style="margin: 10px 0;">
                <ul style="margin: 0; padding-left: 20px;">
                    <li><strong>📚 Resources:</strong> <a href="https://github.com/TanvirIsraq/whmcs-spaceship-registrar" target="_blank" class="alert-link">GitHub Docs</a></li>
                    <li>Obtain credentials from the <a href="https://www.spaceship.com/application/api-manager/" target="_blank" class="alert-link">API Manager</a>.</li>
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
            'OptionName' => 'sandbox',
            'Description' => 'Tick to enable test mode (Currently Unavailable - Spaceship has no public sandbox yet)',
        ],
    ];

    // Pro/Free Status Handling (Decoupled to Config)
    $configPath = __DIR__ . '/lib/Spaceship/Premium/Config.php';
    if (\file_exists($configPath)) {
        require_once $configPath;
        if (\class_exists('\Spaceship\Premium\Config')) {
            $config = \Spaceship\Premium\Config::enhanceConfig($config);
        }
    } else {
        // Fallback for Free Version
        $config['Description']['Value'] = \str_replace(
            ['{PRO_BADGE}', '{STATUS_LINE}'],
            ['', '<strong>Free Version:</strong> Upgrade for TLD Pricing Sync. <a href="https://my.topeta.com/checkout/?edd_action=add_to_cart&download_id=424" target="_blank" class="alert-link" style="text-decoration: underline;">Upgrade to Premium (PRO)</a>'],
            $config['Description']['Value']
        );
    }

    return $config;
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

if (!\function_exists('_spaceship_get_client')) {
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

    // Use main domain info endpoint which is reliable
    $result = _spaceship_get_domain_info($params, $force);

    $status = 'off';

    // Check for 'privacyLevel' which is the official API spec
    if (isset($result['privacyLevel']) && $result['privacyLevel'] !== 'PUBLIC') {
        $status = 'on';
    }

    Cache::set($domain, 'privacy_status', $status, 290);
    return $status;
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

            // Default status: treat unknown as registered to be safe
            $status = \WHMCS\Domains\DomainLookup\SearchResult::STATUS_REGISTERED;

            if ($item['isAvailable']) {
                $status = \WHMCS\Domains\DomainLookup\SearchResult::STATUS_NOT_REGISTERED;

                // PREMIUM FEATURE: Search Enhancer (Process Pricing Markup)
                $configPath = __DIR__ . '/lib/Spaceship/Premium/Config.php';
                if (isset($item['premiumPricing']) && \file_exists($configPath)) {
                    require_once $configPath;
                    if (\class_exists('\Spaceship\Premium\Config') && \Spaceship\Premium\Config::isActive($params)) {
                        $enhancerFile = __DIR__ . '/lib/Spaceship/Premium/SearchEnhancer.php';
                        if (\file_exists($enhancerFile)) {
                            require_once $enhancerFile;
                            \Spaceship\Premium\SearchEnhancer::processPremiumAvailability($searchResult, $item, $params);
                        }
                    }
                }
            }

            $searchResult->setStatus($status);
            $results->append($searchResult);
        }

        return $results;
    } catch (\Exception $e) {
        if (\function_exists('logModuleCall')) {
            \logModuleCall('spaceship', 'CheckAvailability Error', $params, $e->getMessage());
        }
        return (new \WHMCS\Domains\DomainLookup\ResultsList())->setError($e->getMessage());
    }
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
    // WHMCS uses 'firstname', 'lastname' etc for registrant (no prefix)
    // and 'adminfirstname', 'adminlastname' etc for admin
    $prefix = ($type === 'registrant') ? '' : $type;

    // 1. Resolve Phone with global fallback
    $phone = $params["{$prefix}phonenumberformatted"]
        ?? $params["{$prefix}fullphonenumber"]
        ?? $params["{$prefix}phonenumber"]
        ?? $params['phonenumberformatted']
        ?? $params['fullphonenumber']
        ?? $params['phonenumber']
        ?? '';

    $postalRaw = \trim($params["{$prefix}postcode"] ?? $params['postcode'] ?? '');
    $postalCode = \preg_replace('/[^0-9\\s-]/', '', $postalRaw);
    $postalCode = \preg_replace('/\\s+/', ' ', $postalCode);
    $postalCode = \trim($postalCode);

    $data = [
        'firstName' => \trim($params["{$prefix}firstname"] ?? $params['firstname'] ?? ''),
        'lastName' => \trim($params["{$prefix}lastname"] ?? $params['lastname'] ?? ''),
        'organization' => \trim($params["{$prefix}companyname"] ?? $params['companyname'] ?? ''),
        'email' => \trim($params["{$prefix}email"] ?? $params['email'] ?? ''),
        'address1' => \trim($params["{$prefix}address1"] ?? $params['address1'] ?? ''),
        'address2' => \trim($params["{$prefix}address2"] ?? $params['address2'] ?? ''),
        'city' => \trim($params["{$prefix}city"] ?? $params['city'] ?? ''),
        'country' => $params["{$prefix}country"] ?? $params['country'] ?? '',
        'stateProvince' => \trim($params["{$prefix}fullstate"] ?? $params['fullstate'] ?? $params['state'] ?? ''),
        'postalCode' => $postalCode,
        'phone' => \trim($phone),
    ];

    // Remove empty optional fields. Required fields must stay (even if empty) to satisfy API schema.
    $required = ['firstName', 'lastName', 'email', 'address1', 'city', 'country', 'postalCode', 'phone'];

    return \array_filter($data, function ($value, $key) use ($required) {
        return $value !== '' || \in_array($key, $required);
    }, ARRAY_FILTER_USE_BOTH);
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
    if ($data['postalCode'] !== '' && !\preg_match('/^[\\d-][\\d\\s-]+[\\d-]$|^[\\d-]{0,2}$/', $data['postalCode'])) {
        throw new \Exception('Invalid postal code. Allowed: digits, spaces, hyphen.');
    }
    $cacheKey = md5(json_encode($data));

    if (isset($spaceshipContactCache[$cacheKey])) {
        return $spaceshipContactCache[$cacheKey];
    }

    $result = $client->request('PUT', '/contacts', $data, 'SaveContact');
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
            'privacyProtection' => [
                'isActive' => isset($params['idprotection']) ? (bool) $params['idprotection'] : false,
                'level' => (isset($params['idprotection']) && $params['idprotection']) ? 'high' : 'public',
                'userConsent' => true,
            ],
        ];

        if (!empty($params['ns3']))
            $registrationData['nameservers']['hosts'][] = $params['ns3'];
        if (!empty($params['ns4']))
            $registrationData['nameservers']['hosts'][] = $params['ns4'];
        if (!empty($params['ns5']))
            $registrationData['nameservers']['hosts'][] = $params['ns5'];

        $client->request('POST', "/domains/{$params['domainname']}", $registrationData, 'RegisterDomain');

        return ['success' => true];
    } catch (\Exception $e) {
        if (\function_exists('logModuleCall')) {
            \logModuleCall('spaceship', 'RegisterDomain Error', $params, $e->getMessage());
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
        if (\function_exists('logModuleCall')) {
            \logModuleCall('spaceship', 'RenewDomain Error', $params, $e->getMessage());
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
            'authCode' => $params['eppcode'] ?? '',
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
        if (\function_exists('logModuleCall')) {
            \logModuleCall('spaceship', 'TransferDomain Error', $params, $e->getMessage());
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
        if (\function_exists('logModuleCall')) {
            \logModuleCall('spaceship', 'GetNameservers Error', $params, $e->getMessage());
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
        if (\function_exists('logModuleCall')) {
            \logModuleCall('spaceship', 'GetRegistrarLock Error', $params, $e->getMessage());
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
        if (\function_exists('logModuleCall')) {
            \logModuleCall('spaceship', 'GetTransferStatus Error', $params, $e->getMessage());
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
        if (\function_exists('logModuleCall')) {
            \logModuleCall('spaceship', 'SaveRegistrarLock Error', $params, $e->getMessage());
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
        if (\function_exists('logModuleCall')) {
            \logModuleCall('spaceship', 'GetIDProtectStatus Error', $params, $e->getMessage());
        }
        return '';
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
        /**
         * Privacy Update Spec (Verified from docs.spaceship.dev):
         * Endpoint: PUT /v1/domains/{domainName}/privacy/preference
         * Payload: {"privacyLevel": "high"|"public", "userConsent": true}
         */
        $updateData = [
            'privacyLevel' => $status ? 'high' : 'public',
            'userConsent' => true
        ];

        $client->request('PUT', "/domains/{$params['domainname']}/privacy/preference", $updateData, 'IDProtectToggle');

        // Clear cache
        Cache::clear($params['domainname']);

        return ['success' => true];
    } catch (\Exception $e) {
        if (\function_exists('logModuleCall')) {
            \logModuleCall('spaceship', 'IDProtectToggle Error', $params, $e->getMessage());
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
        if (\function_exists('logModuleCall')) {
            \logModuleCall('spaceship', 'GetEPPCode Error', $params, $e->getMessage());
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
        if (\function_exists('logModuleCall')) {
            \logModuleCall('spaceship', 'Sync Error', $params, $e->getMessage());
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
    static $localContactCache = []; // Process-level cache

    try {
        $domainInfo = _spaceship_get_domain_info($params);
        $contacts = [];

        foreach (['registrant', 'admin', 'tech', 'billing'] as $type) {
            if (isset($domainInfo['contacts'][$type])) {
                $contactId = $domainInfo['contacts'][$type];

                // Deduplication: If we already fetched this ID in this call or a previous one in same process
                if (!isset($localContactCache[$contactId])) {
                    $localContactCache[$contactId] = $client->request('GET', "/contacts/{$contactId}", [], 'GetContactDetails');
                }

                $details = $localContactCache[$contactId];
                $whmcsType = ucfirst($type);
                $contacts[$whmcsType] = [
                    'First Name' => $details['firstName'],
                    'Last Name' => $details['lastName'],
                    'Organization Name' => isset($details['organization']) ? $details['organization'] : '',
                    'Email Address' => $details['email'],
                    'Address 1' => $details['address1'],
                    'Address 2' => isset($details['address2']) ? $details['address2'] : '',
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
        if (\function_exists('logModuleCall')) {
            \logModuleCall('spaceship', 'SaveContactDetails Error', $params, $e->getMessage());
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

        if (\function_exists('logModuleCall')) {
            \logModuleCall('spaceship', 'GetDNS Error', $params, $e->getMessage());
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
            /** 
             * Spaceship API Update DNS records Spec:
             * Endpoint: PUT /v1/dns/records/{domain}
             * Payload: {"items": [...records]}
             * This replaces the entire record set for the zone.
             */
            $dnsPayload = [
                'items' => $recordsToSync
            ];
            $client->request('PUT', "/dns/records/{$params['domainname']}", $dnsPayload, 'SaveDNS');
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
    $host = \str_replace('.' . $params['domainname'], '', $params['nameserver']);

    try {
        // Spaceship API uses PUT with a host-specific path and "ips" array.
        $ips = [];
        if (!empty($params['ipaddress'])) {
            $ips[] = $params['ipaddress'];
        }

        $client->request('PUT', "/domains/{$params['domainname']}/personal-nameservers/{$host}", [
            'host' => $host,
            'ips' => $ips,
        ], 'RegisterNameserver');
        return ['success' => true];
    } catch (\Exception $e) {
        if (\function_exists('logModuleCall')) {
            \logModuleCall('spaceship', 'RegisterNameserver Error', $params, $e->getMessage());
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
    $host = \str_replace('.' . $params['domainname'], '', $params['nameserver']);

    try {
        // Update the host configuration with the new IP(s).
        $ips = [];
        if (!empty($params['newipaddress'])) {
            $ips[] = $params['newipaddress'];
        } elseif (!empty($params['currentipaddress'])) {
            $ips[] = $params['currentipaddress'];
        }

        $client->request('PUT', "/domains/{$params['domainname']}/personal-nameservers/{$host}", [
            'host' => $host,
            'ips' => $ips,
        ], 'ModifyNameserver');
        return ['success' => true];
    } catch (\Exception $e) {
        if (\function_exists('logModuleCall')) {
            \logModuleCall('spaceship', 'ModifyNameserver Error', $params, $e->getMessage());
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
    $host = \str_replace('.' . $params['domainname'], '', $params['nameserver']);

    try {
        $client->request('DELETE', "/domains/{$params['domainname']}/personal-nameservers/{$host}", [], 'DeleteNameserver');
        return ['success' => true];
    } catch (\Exception $e) {
        if (\function_exists('logModuleCall')) {
            \logModuleCall('spaceship', 'DeleteNameserver Error', $params, $e->getMessage());
        }
        return ['error' => $e->getMessage()];
    }
}



/**
 * Get TLD pricing synchronization (Premium Feature).
 * Returns a ResultsList of ImportItem objects as required by WHMCS.
 *
 * @param array $params
 * @return \WHMCS\Results\ResultsList
 */
function spaceship_GetTldPricing($params)
{
    try {
        $resultsClass = '\WHMCS\Domains\TldSync\ResultsList';
        if (!\class_exists($resultsClass)) {
            $resultsClass = '\WHMCS\Results\ResultsList';
        }
        $results = \class_exists($resultsClass) ? new $resultsClass() : [];

        // Check for premium activation (decoupled to Config class)
        $configPath = __DIR__ . '/lib/Spaceship/Premium/Config.php';
        if (\file_exists($configPath)) {
            require_once $configPath;
            if (\class_exists('\Spaceship\Premium\Config') && \Spaceship\Premium\Config::isActive($params)) {
                // Feature Toggle Check: Only block background Cron (CLI) if disabled.
                // Manual Sync (Web) is always allowed if the license is active.
                if (($params['EnableAutoSync'] ?? '') !== 'on' && \PHP_SAPI === 'cli') {
                    if (\function_exists('logModuleCall')) {
                        \logModuleCall('spaceship', 'Sync: Skipped', 'Auto-Sync Disabled', 'TLD Pricing Sync is disabled for automated cron tasks.');
                    }
                    return $results;
                }

                $syncFile = __DIR__ . '/lib/Spaceship/Premium/PricingSync.php';
                if (\file_exists($syncFile)) {
                    require_once $syncFile;
                    if (class_exists('\Spaceship\Premium\PricingSync')) {
                        return (new \Spaceship\Premium\PricingSync($params))->handle();
                    } else {
                        if (\function_exists('logModuleCall')) {
                            \logModuleCall('spaceship', 'Sync: Error', 'Class Missing', 'PricingSync class not found in file.');
                        }
                    }
                } else {
                    if (\function_exists('logModuleCall')) {
                        \logModuleCall('spaceship', 'Sync: Error', 'File Missing', 'PricingSync file missing at ' . $syncFile);
                    }
                }
            }
        }

        return $results;
    } catch (\Throwable $e) {
        if (\function_exists('logModuleCall')) {
            \logModuleCall('spaceship', 'Sync: FATAL ERROR', $e->getMessage(), $e->getTraceAsString());
        }
        return new \WHMCS\Results\ResultsList();
    }
}



