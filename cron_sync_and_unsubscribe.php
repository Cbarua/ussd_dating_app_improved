#!/usr/bin/env php
<?php

/**
 * mSpace Subscriber Sync & Daily Unsubscribe Script
 *
 * Usage:
 *   php cron_sync_and_unsubscribe.php [options]
 *
 * Options:
 *   --sync            Sync subscribers from mSpace (/subscription/getSubscriberList) into local database
 *   --unsub           Pick 2 to 5 random REGISTERED subscribers and unsubscribe them (/subscription/send)
 *   --both            Execute sync first, then unsubscribe (Default if neither --sync nor --unsub given)
 *   --dry-run         Simulate operations without making external unsubscription API calls or modifying DB
 *   --count=<N>       Specify exact number of subscribers to unsubscribe (default: random 2 to 5)
 *   --max-pages=<N>   Limit sync to at most N pages (useful for testing or batch runs)
 *   --charging-info   Enrich subscribers with detailed charging info (/subscription/getSubscriberChargingInfo)
 *   --help, -h        Show this help message
 */

define('ALLOW_MAINTENANCE_RUN', true);

require_once __DIR__ . "/app/config.php";
require_once __DIR__ . "/app/telco.php";
require_once __DIR__ . "/app/logger.php";

// Parse CLI arguments
$options = getopt("h", ["sync", "unsub", "both", "dry-run", "count::", "max-pages::", "charging-info", "help"]);

if (isset($options['h']) || isset($options['help'])) {
    echo "mSpace Subscriber Sync & Daily Unsubscribe Script\n\n";
    echo "Usage:\n";
    echo "  php cron_sync_and_unsubscribe.php [options]\n\n";
    echo "Options:\n";
    echo "  --sync            Sync subscriber list from mSpace into local database\n";
    echo "  --unsub           Pick 2 to 5 random REGISTERED subscribers and unsubscribe them\n";
    echo "  --both            Execute both --sync and --unsub (Default behavior)\n";
    echo "  --dry-run         Simulate operations without making API calls or modifying DB\n";
    echo "  --count=<N>       Specify exact number of users to unsubscribe (default: random 2 to 5)\n";
    echo "  --max-pages=<N>   Limit sync to at most N pages of subscribers\n";
    echo "  --charging-info   Fetch detailed charging info for users via getSubscriberChargingInfo\n";
    echo "  --help, -h        Show this help message\n\n";
    exit(0);
}

$isDryRun = isset($options['dry-run']);
$doSync = isset($options['sync']);
$doUnsub = isset($options['unsub']);
$doBoth = isset($options['both']);
$enrichCharging = isset($options['charging-info']);

// If neither --sync nor --unsub is explicitly passed, run both by default
if (!$doSync && !$doUnsub) {
    $doSync = true;
    $doUnsub = true;
} elseif ($doBoth) {
    $doSync = true;
    $doUnsub = true;
}

$unsubCount = null;
if (isset($options['count']) && is_numeric($options['count']) && (int)$options['count'] > 0) {
    $unsubCount = (int)$options['count'];
} else {
    $unsubCount = rand(2, 5);
}

$maxPages = null;
if (isset($options['max-pages']) && is_numeric($options['max-pages']) && (int)$options['max-pages'] > 0) {
    $maxPages = (int)$options['max-pages'];
}

$isMaintenanceActive = isset($_ENV['APP_MAINTENANCE']) && filter_var($_ENV['APP_MAINTENANCE'], FILTER_VALIDATE_BOOLEAN);

echo "=====================================================\n";
echo "mSpace Subscriber Sync & Daily Unsubscribe Manager\n";
echo "Date: " . date('Y-m-d H:i:s') . "\n";
echo "Mode: " . ($isDryRun ? "DRY RUN (Simulation)" : "LIVE EXECUTION") . "\n";
if ($isMaintenanceActive) {
    echo "Maintenance: ACTIVE (CLI Bypass)\n";
}
echo "Tasks: " . ($doSync ? "[Sync" . ($maxPages ? " (Max $maxPages pages)" : "") . "] " : "") . ($doUnsub ? "[Unsubscribe ($unsubCount users)]" : "") . "\n";
echo "=====================================================\n\n";

if ($isMaintenanceActive) {
    unsublog("Notice: Script running under ACTIVE Maintenance Mode (CLI Bypass)");
}

$subscription = new Subscription(
    app['sub_msg_url'],
    app['sub_status_url'],
    app['sub_base_url'],
    app['sub_list_url'],
    app['sub_charging_info_url']
);

/**
 * Normalizes subscriber address to ensure clean format (e.g. 'tel:9471xxxxxxx' or 'tel:<hash>:mobitel')
 */
function normalizeSubscriberAddress($address) {
    $address = trim($address);
    if (strpos($address, 'tel:') === 0) {
        return 'tel:' . trim(substr($address, 4));
    }
    return 'tel:' . $address;
}

// --------------------------------------------------------------------------
// 1. SUBSCRIBER SYNC TASK
// --------------------------------------------------------------------------
if ($doSync) {
    echo "[STEP 1] Syncing subscribers from mSpace API...\n";
    
    $page = 1;
    $totalFetched = 0;
    $totalInserted = 0;
    $totalUpdated = 0;
    $totalSkipped = 0;
    $hasMore = true;
    $pagesProcessed = 0;

    while ($hasMore) {
        if ($maxPages !== null && $pagesProcessed >= $maxPages) {
            echo "  Reached maximum page limit ($maxPages). Stopping sync.\n";
            break;
        }

        echo "  Fetching page $page... ";
        
        // Retry loop for resilient page fetching
        $response = null;
        $maxRetries = 3;
        for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
            $response = $subscription->getSubscriberList(app['app_id'], app['password'], $page);
            if ($response && isset($response['statusCode'])) {
                break;
            }
            if ($attempt < $maxRetries) {
                echo "[retry $attempt] ";
                sleep(2);
            }
        }

        if (!$response || !isset($response['statusCode'])) {
            echo "FAILED (Empty or invalid response from API after $maxRetries attempts)\n";
            unsublog("Sync Error: Empty/invalid response on page $page after $maxRetries retries");
            break;
        }

        $statusCode = $response['statusCode'];
        $statusDetail = $response['statusDetail'] ?? 'No detail';

        if ($statusCode === 'S1001') {
            echo "No subscribers found on platform.\n";
            break;
        }

        if ($statusCode !== 'S1000') {
            echo "ERROR [$statusCode]: $statusDetail\n";
            unsublog("Sync Error: Code $statusCode - $statusDetail on page $page");
            break;
        }

        $subscribers = $response['subscribers'] ?? [];
        // Handle single object vs list of objects
        if (isset($subscribers['subscriberId'])) {
            $subscribers = [$subscribers];
        }

        $countInPage = count($subscribers);
        echo "Received $countInPage subscriber(s)\n";

        if ($countInPage === 0) {
            break;
        }

        $pagesProcessed++;

        // Optionally enrich with getSubscriberChargingInfo in batches of up to 10
        $chargingInfoMap = [];
        if ($enrichCharging && $countInPage > 0) {
            $batchIds = [];
            foreach ($subscribers as $s) {
                $subId = trim($s['subscriberId'] ?? '');
                if (!empty($subId) && $subId !== 'N/A') {
                    $batchIds[] = normalizeSubscriberAddress($subId);
                }
            }
            $chunks = array_chunk($batchIds, 10);
            foreach ($chunks as $chunk) {
                $chargeRes = $subscription->getSubscriberChargingInfo(app['app_id'], app['password'], $chunk);
                if (isset($chargeRes['destinationResponses']) && is_array($chargeRes['destinationResponses'])) {
                    foreach ($chargeRes['destinationResponses'] as $dest) {
                        if (isset($dest['subscriberId'])) {
                            $chargingInfoMap[normalizeSubscriberAddress($dest['subscriberId'])] = $dest;
                        }
                    }
                }
                usleep(200000); // 200ms delay between batch calls
            }
        }

        foreach ($subscribers as $sub) {
            $rawId = trim($sub['subscriberId'] ?? '');
            if (empty($rawId) || $rawId === 'N/A') {
                $totalSkipped++;
                continue;
            }

            $address = normalizeSubscriberAddress($rawId);
            $subStatus = $sub['subscriptionStatus'] ?? 'REGISTERED';
            $lastChargedDate = $sub['lastChargedDate'] ?? null;
            
            // If charging info was fetched, prefer that status
            if (isset($chargingInfoMap[$address]['subscriptionStatus'])) {
                $subStatus = $chargingInfoMap[$address]['subscriptionStatus'];
            }

            // Extract date part (YYYY-MM-DD) if available
            $subDate = date('Y-m-d');
            if (!empty($lastChargedDate)) {
                $datePart = substr($lastChargedDate, 0, 10);
                if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $datePart)) {
                    $subDate = $datePart;
                }
            }

            $totalFetched++;

            // Check if user already exists
            $escapedAddress = $mysqli->real_escape_string($address);
            $checkSql = "SELECT address, sub_status FROM " . app['user_table'] . " WHERE address = '$escapedAddress' LIMIT 1;";
            $existing = getSQLdata($mysqli, $checkSql);

            if ($existing && isset($existing['address'])) {
                if ($existing['sub_status'] !== $subStatus) {
                    if (!$isDryRun) {
                        updateUserDB($mysqli, $address, ['sub_status' => $subStatus, 'sub_date' => $subDate]);
                    }
                    $totalUpdated++;
                }
            } else {
                if (!$isDryRun) {
                    $insertSql = "INSERT INTO " . app['user_table'] . " (address, sub_status, sub_date) VALUES ('$escapedAddress', '$subStatus', '$subDate');";
                    executeSQL($mysqli, $insertSql);
                }
                $totalInserted++;
            }
        }

        // Check pagination
        $moreDataAvailable = $response['moreDataAvailable'] ?? false;
        $nextPage = isset($response['nextPageNumber']) ? (int)$response['nextPageNumber'] : -1;

        $hasMore = ($moreDataAvailable === true || $moreDataAvailable === 'true') && ($nextPage > $page);
        $page = $nextPage;

        usleep(300000); // 300ms pause between pages
    }

    echo "Sync Complete: Processed $pagesProcessed page(s), Fetched $totalFetched valid users ($totalSkipped N/A skipped), Inserted $totalInserted new, Updated $totalUpdated existing.\n\n";
    unsublog("Sync Finished: Pages: $pagesProcessed | Fetched: $totalFetched | Inserted: $totalInserted | Updated: $totalUpdated | Skipped: $totalSkipped | DryRun: " . ($isDryRun ? "yes" : "no"));
}

// --------------------------------------------------------------------------
// 2. DAILY UNSUBSCRIBE TASK
// --------------------------------------------------------------------------
if ($doUnsub) {
    echo "[STEP 2] Selecting and unsubscribing $unsubCount users...\n";

    // Query for REGISTERED users
    $regStatus = app['sub_reg'];
    $sql = "SELECT address, sub_status, sub_date FROM " . app['user_table'] . " WHERE sub_status = '$regStatus' ORDER BY RAND() LIMIT $unsubCount;";
    $db_data = getSQLdata($mysqli, $sql);

    if (!$db_data) {
        echo "No registered users found in the database to unsubscribe.\n";
        unsublog("Unsubscribe Notice: No users with status '$regStatus' found to unsubscribe.");
    } else {
        $users = isset($db_data['address']) ? [$db_data] : $db_data;
        $selectedCount = count($users);
        echo "Found $selectedCount registered user(s) to process.\n\n";

        $successCount = 0;
        $failCount = 0;

        foreach ($users as $index => $user) {
            $num = $index + 1;
            $address = $user['address'];
            echo "  [$num/$selectedCount] User: $address ... ";

            if ($isDryRun) {
                echo "[DRY RUN] Would unsubscribe via API and set status to UNREGISTERED\n";
                unsublog("[DRY RUN] Unsubscribe candidate: $address (current status: {$user['sub_status']})");
                $successCount++;
                continue;
            }

            // Call mSpace Unsubscription API (/subscription/send with action 0)
            $respRaw = $subscription->UnregUser(app['app_id'], app['password'], $address);
            $resp = json_decode($respRaw, true);

            $statusCode = $resp['statusCode'] ?? null;
            $statusDetail = $resp['statusDetail'] ?? 'No detail';
            $subscriptionStatus = $resp['subscriptionStatus'] ?? null;

            if ($statusCode === 'S1000' || $subscriptionStatus === app['sub_unreg'] || stripos($statusDetail, 'already') !== false) {
                // Update local database to UNREGISTERED
                updateUserDB($mysqli, $address, [
                    'sub_status' => app['sub_unreg'],
                    'sub_date' => date('Y-m-d')
                ]);

                echo "SUCCESS ($statusDetail)\n";
                unsublog("Unsubscribed SUCCESS: Address: $address | Detail: $statusDetail | Raw: " . trim($respRaw));
                $successCount++;
            } else {
                echo "FAILED [$statusCode]: $statusDetail\n";
                unsublog("Unsubscribed FAILED: Address: $address | Code: $statusCode | Detail: $statusDetail | Raw: " . trim($respRaw));
                $failCount++;
            }

            // Sleep 1 second between unsubscribe API calls to respect TPS limits
            sleep(1);
        }

        echo "\nUnsubscribe Process Complete: $successCount succeeded, $failCount failed.\n";
        unsublog("Unsubscribe Summary: Processed $selectedCount users | Success: $successCount | Failed: $failCount");
    }
}

echo "\nDone!\n";
