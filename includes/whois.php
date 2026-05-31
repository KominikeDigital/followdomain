<?php
// Prevent direct access
if (count(get_included_files()) === 1) {
    http_response_code(403);
    exit('Direct access not allowed.');
}

/**
 * Clean and normalize domain name input
 */
function cleanDomainName($domain) {
    $domain = trim($domain);
    $domain = strtolower($domain);
    
    // Remove protocol and paths
    $domain = preg_replace('#^https?://#', '', $domain);
    $domain = preg_replace('#^www\.#', '', $domain);
    $domain = explode('/', $domain)[0];
    
    // Remove port numbers
    $domain = explode(':', $domain)[0];
    
    // Remove invalid characters
    $domain = preg_replace('/[^a-z0-9\.\-]/', '', $domain);
    
    return $domain;
}

/**
 * Query official RDAP server (HTTPS based)
 */
function fetchDomainInfoRDAP($domain) {
    $url = "https://rdap.org/domain/" . urlencode($domain);
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36');
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
    if ($httpCode === 404) {
        return [
            'success' => true,
            'registered' => false,
            'message' => 'Domain is available.'
        ];
    }
    
    if ($httpCode !== 200 || !$response) {
        return [
            'success' => false,
            'message' => "RDAP query failed with HTTP code: $httpCode"
        ];
    }
    
    $data = json_decode($response, true);
    if (!$data) {
        return [
            'success' => false,
            'message' => "Invalid RDAP JSON payload."
        ];
    }
    
    $result = [
        'success' => true,
        'registered' => true,
        'domain' => $domain,
        'expiration_date' => null,
        'registration_date' => null,
        'last_changed_date' => null,
        'registrar' => 'Unknown',
        'status' => [],
        'nameservers' => []
    ];
    
    // Status
    if (isset($data['status'])) {
        $result['status'] = $data['status'];
    }
    
    // Nameservers
    if (isset($data['nameservers'])) {
        foreach ($data['nameservers'] as $ns) {
            if (isset($ns['ldhName'])) {
                $result['nameservers'][] = strtolower($ns['ldhName']);
            }
        }
    }
    
    // Parse events (dates)
    if (isset($data['events'])) {
        foreach ($data['events'] as $event) {
            $action = isset($event['eventAction']) ? $event['eventAction'] : '';
            $date = isset($event['eventDate']) ? $event['eventDate'] : '';
            
            if (!$date) continue;
            
            // Format dates as YYYY-MM-DD HH:ii:ss
            $formattedDate = date('Y-m-d H:i:s', strtotime($date));
            
            if ($action === 'expiration') {
                $result['expiration_date'] = $formattedDate;
            } elseif ($action === 'registration') {
                $result['registration_date'] = $formattedDate;
            } elseif ($action === 'last changed' || $action === 'last update') {
                $result['last_changed_date'] = $formattedDate;
            }
        }
    }
    
    // Find Registrar Name
    if (isset($data['entities'])) {
        foreach ($data['entities'] as $entity) {
            if (isset($entity['roles']) && in_array('registrar', $entity['roles'])) {
                if (isset($entity['vcardArray'][1])) {
                    foreach ($entity['vcardArray'][1] as $vcard) {
                        if ($vcard[0] === 'fn') {
                            $result['registrar'] = $vcard[3];
                            break 2;
                        }
                    }
                }
            }
        }
    }
    
    return $result;
}

/**
 * Query Trabİs WHOIS server over port 43 for .tr domains
 */
function fetchDomainInfoTrabis($domain) {
    $server = "whois.trabis.gov.tr";
    $fp = @fsockopen($server, 43, $errno, $errstr, 5);
    
    if (!$fp) {
        return [
            'success' => false,
            'message' => "Could not connect to Trabİs WHOIS (Port 43 blocked or server offline): $errstr ($errno)"
        ];
    }
    
    fwrite($fp, $domain . "\r\n");
    $out = "";
    while (!feof($fp)) {
        $out .= fgets($fp, 2048);
    }
    fclose($fp);
    
    // Check if domain is available (not found)
    if (stripos($out, "No match found") !== false || stripos($out, "Not found") !== false || trim($out) === "") {
        return [
            'success' => true,
            'registered' => false,
            'message' => 'Domain is available.'
        ];
    }
    
    $result = [
        'success' => true,
        'registered' => true,
        'domain' => $domain,
        'expiration_date' => null,
        'registration_date' => null,
        'last_changed_date' => null,
        'registrar' => 'TRABİS (.tr Registry)',
        'status' => [],
        'nameservers' => []
    ];
    
    // Extract dates
    if (preg_match('/Created on\.+\:\s*([0-9]{4})-([a-zA-Z]{3})-([0-9]{2})/i', $out, $matches)) {
        $result['registration_date'] = date('Y-m-d H:i:s', strtotime($matches[1] . '-' . $matches[2] . '-' . $matches[3]));
    }
    if (preg_match('/Expires on\.+\:\s*([0-9]{4})-([a-zA-Z]{3})-([0-9]{2})/i', $out, $matches)) {
        $result['expiration_date'] = date('Y-m-d H:i:s', strtotime($matches[1] . '-' . $matches[2] . '-' . $matches[3]));
    }
    if (preg_match('/Last Update Time\:\s*([0-9]{4}-[0-9]{2}-[0-9]{2}T[0-9]{2}:[0-9]{2}:[0-9]{2})/i', $out, $matches)) {
        $result['last_changed_date'] = date('Y-m-d H:i:s', strtotime($matches[1]));
    }
    
    // Extract Registrar
    if (preg_match('/Organization Name\s*:\s*([^\r\n]+)/i', $out, $matches)) {
        $result['registrar'] = trim($matches[1]);
    }
    
    // Extract Status
    if (preg_match('/Domain Status\s*:\s*([^\r\n]+)/i', $out, $matches)) {
        $result['status'][] = trim($matches[1]);
    }
    if (preg_match('/Transfer Status\s*:\s*([^\r\n]+)/i', $out, $matches)) {
        $result['status'][] = trim($matches[1]);
    }
    
    // Extract Nameservers
    if (preg_match('/\*\* Domain Servers:\s*(.*?)(?=\n\*\*|$)/s', $out, $matches)) {
        $nsLines = explode("\n", trim($matches[1]));
        foreach ($nsLines as $line) {
            $line = trim($line);
            if ($line) {
                $result['nameservers'][] = strtolower($line);
            }
        }
    }
    
    return $result;
}

/**
 * Universal lookup router
 */
function fetchDomainInfo($domain) {
    $domain = cleanDomainName($domain);
    if (empty($domain) || strpos($domain, '.') === false) {
        return [
            'success' => false,
            'message' => 'Invalid domain name format.'
        ];
    }
    
    // Route based on TLD
    if (preg_match('/\.tr$/i', $domain)) {
        return fetchDomainInfoTrabis($domain);
    } else {
        return fetchDomainInfoRDAP($domain);
    }
}
