#!/usr/bin/env php
<?php

/**
 * Ideamart Subscriber Sync & Daily Unsubscribe Script
 *
 * Usage:
 *   php cron_sync_and_unsubscribe.php [options]
 *
 * Options:
 *   --sync                 Verify and sync subscriber statuses from Ideamart (/subscription/getStatus)
 *   --unsub                Pick 2 to 5 random REGISTERED subscribers and unsubscribe them (/subscription/send)
 *   --both                 Execute sync first, then unsubscribe (Default if neither --sync nor --unsub given)
 *   --dry-run              Simulate operations without making external unsubscription API calls or modifying DB
 *   --count=<N>            Specify exact number of subscribers to unsubscribe (default: random 2 to 5)
 *   --limit=<N>            Limit number of candidate users to sync
 *   --all-statuses         Check all users including UNREGISTERED (by default UNREGISTERED users are skipped)
 *   --skip-today-updated   Skip users whose status was already verified/updated today
 *   --sleep-ms=<N>         Milliseconds to sleep between Telco API queries (default: 100ms)
 *   --reset                Reset and ignore saved same-day sync checkpoint
 *   --help, -h             Show this help message
 */

define('ALLOW_MAINTENANCE_RUN', true);

require_once __DIR__ . "/app/config.php";
require_once __DIR__ . "/app/telco.php";
require_once __DIR__ . "/app/logger.php";

// Parse CLI arguments
$options = getopt("h", [
    "sync",
    "unsub",
    "both",
    "dry-run",
    "count::",
    "limit::",
    "all-statuses",
    "skip-today-updated",
    "sleep-ms::",
    "reset",
    "help"
]);

if (isset($options['h']) || isset($options['help'])) {
    echo "Ideamart Subscriber Sync & Daily Unsubscribe Script\n\n";
    echo "Usage:\n";
    echo "  php cron_sync_and_unsubscribe.php [options]\n\n";
    echo "Options:\n";
    echo "  --sync                 Verify and sync subscriber statuses from Ideamart (/subscription/getStatus)\n";
    echo "  --unsub                Pick 2 to 5 random REGISTERED subscribers and unsubscribe them\n";
    echo "  --both                 Execute both --sync and --unsub (Default behavior)\n";
    echo "  --dry-run              Simulate operations without making API calls or modifying DB\n";
    echo "  --count=<N>            Specify exact number of users to unsubscribe (default: random 2 to 5)\n";
    echo "  --limit=<N>            Limit number of users to check during sync\n";
    echo "  --all-statuses         Check all users including UNREGISTERED (by default UNREGISTERED are skipped)\n";
    echo "  --skip-today-updated   Skip users whose status was already verified/updated today\n";
    echo "  --sleep-ms=<N>         Milliseconds to pause between API calls (default: 100ms)\n";
    echo "  --reset                Reset saved same-day progress checkpoint\n";
    echo "  --help, -h             Show this help message\n\n";
    exit(0);
}

$isDryRun = isset($options['dry-run']);
$doSync = isset($options['sync']);
$doUnsub = isset($options['unsub']);
$doBoth = isset($options['both']);
$allStatuses = isset($options['all-statuses']);
$skipTodayUpdated = isset($options['skip-today-updated']);
$resetCheckpoint = isset($options['reset']);

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

$syncLimit = null;
if (isset($options['limit']) && is_numeric($options['limit']) && (int)$options['limit'] > 0) {
    $syncLimit = (int)$options['limit'];
}

$sleepMs = 100;
if (isset($options['sleep-ms']) && is_numeric($options['sleep-ms']) && (int)$options['sleep-ms'] >= 0) {
    $sleepMs = (int)$options['sleep-ms'];
}

$today = date('Y-m-d');
$checkpointFile = __DIR__ . '/log/sync_checkpoint.json';
$isMaintenanceActive = isset($_ENV['APP_MAINTENANCE']) && filter_var($_ENV['APP_MAINTENANCE'], FILTER_VALIDATE_BOOLEAN);

echo "=====================================================\n";
echo "Ideamart Subscriber Sync & Daily Unsubscribe Manager\n";
echo "Date: " . date('Y-m-d H:i:s') . "\n";
echo "Mode: " . ($isDryRun ? "DRY RUN (Simulation)" : "LIVE EXECUTION") . "\n";
if ($isMaintenanceActive) {
    echo "Maintenance: ACTIVE (CLI Bypass)\n";
}
echo "Tasks: " . ($doSync ? "[Sync" . ($syncLimit ? " (Limit $syncLimit)" : "") . "] " : "") . ($doUnsub ? "[Unsubscribe ($unsubCount users)]" : "") . "\n";
echo "=====================================================\n\n";

if ($isMaintenanceActive) {
    unsublog("Notice: Script running under ACTIVE Maintenance Mode (CLI Bypass)");
}

$subscription = new Subscription(
    app['sub_msg_url'],
    app['sub_status_url'],
    app['sub_base_url']
);

function normalizeSubscriberAddress($address) {
    $address = trim($address);
    if (strpos($address, 'tel:') === 0) {
        return 'tel:' . trim(substr($address, 4));
    }
    return 'tel:' . $address;
}

// 1. SUBSCRIBER SYNC TASK (Ideamart 1-by-1 status verification)
if ($doSync) {
    echo "[STEP 1] Synchronizing subscribers via Ideamart API (/subscription/getStatus)...\n";

    // Checkpoint management
    $lastAddress = '';
    $stats = [
        'checked' => 0,
        'updated' => 0,
        'unchanged' => 0,
        'errors' => 0
    ];
    $transitions = [];

    if (!$resetCheckpoint && file_exists($checkpointFile)) {
        $saved = json_decode(file_get_contents($checkpointFile), true);
        if ($saved && isset($saved['date']) && $saved['date'] === $today) {
            $lastAddress = $saved['last_address'] ?? '';
            $stats['checked'] = (int)($saved['stats']['checked'] ?? 0);
            $stats['updated'] = (int)($saved['stats']['updated'] ?? 0);
            $stats['unchanged'] = (int)($saved['stats']['unchanged'] ?? 0);
            $stats['errors'] = (int)($saved['stats']['errors'] ?? 0);
            $transitions = $saved['transitions'] ?? [];
            if (!empty($lastAddress)) {
                echo "  Resuming previous sync from today (last address: $lastAddress, previously checked: {$stats['checked']})\n";
            }
        }
    }

    $baseWhere = [];
    if (!$allStatuses) {
        $unregStatus = $mysqli->real_escape_string(app['sub_unreg']);
        $baseWhere[] = "sub_status != '$unregStatus'";
    }
    if ($skipTodayUpdated) {
        $baseWhere[] = "(sub_date IS NULL OR sub_date != '$today')";
    }

    $whereClause = !empty($baseWhere) ? "WHERE " . implode(" AND ", $baseWhere) : "";

    $countSql = "SELECT COUNT(*) as cnt FROM " . app['user_table'] . " $whereClause;";
    $cntRes = getSQLdata($mysqli, $countSql);
    $totalCandidates = (int)($cntRes['cnt'] ?? 0);

    echo "  Total candidate users matching criteria: $totalCandidates\n";

    $processedInThisRun = 0;
    $chunkSize = 50;
    $hasMore = true;

    $saveCheckpoint = function() use ($checkpointFile, $today, &$lastAddress, &$stats, &$transitions) {
        $data = [
            'date' => $today,
            'last_address' => $lastAddress,
            'stats' => $stats,
            'transitions' => $transitions,
            'updated_at' => date('Y-m-d H:i:s')
        ];
        @file_put_contents($checkpointFile, json_encode($data, JSON_PRETTY_PRINT));
    };

    while ($hasMore) {
        if ($syncLimit !== null && $processedInThisRun >= $syncLimit) {
            echo "  Reached specified limit of $syncLimit users. Stopping sync.\n";
            break;
        }

        $currentWhere = $baseWhere;
        if (!empty($lastAddress)) {
            $escapedLast = $mysqli->real_escape_string($lastAddress);
            $currentWhere[] = "address > '$escapedLast'";
        }

        $whereStr = !empty($currentWhere) ? "WHERE " . implode(" AND ", $currentWhere) : "";
        $limitToFetch = $chunkSize;
        if ($syncLimit !== null) {
            $limitToFetch = min($chunkSize, $syncLimit - $processedInThisRun);
        }

        $sql = "SELECT address, sub_status, sub_date FROM " . app['user_table'] . " $whereStr ORDER BY address ASC LIMIT $limitToFetch;";
        $chunk = getSQLdata($mysqli, $sql);

        if (!$chunk) {
            $hasMore = false;
            break;
        }

        $users = isset($chunk['address']) ? [$chunk] : $chunk;
        if (empty($users)) {
            $hasMore = false;
            break;
        }

        foreach ($users as $user) {
            $rawAddress = $user['address'];
            $currentStatus = $user['sub_status'] ?? 'UNKNOWN';
            $lastAddress = $rawAddress;
            $processedInThisRun++;
            $stats['checked']++;

            $normAddress = normalizeSubscriberAddress($rawAddress);

            $resp = null;
            $maxRetries = 2;
            for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
                $resp = $subscription->getStatus(app['app_id'], app['password'], $normAddress);
                if ($resp && (isset($resp['statusCode']) || isset($resp['subscriptionStatus']))) {
                    break;
                }
                if ($attempt < $maxRetries) {
                    usleep(200000);
                }
            }

            $apiStatus = null;
            $statusCode = $resp['statusCode'] ?? null;
            $statusDetail = $resp['statusDetail'] ?? '';

            if ($statusCode === 'S1000' && !empty($resp['subscriptionStatus'])) {
                $apiStatus = $resp['subscriptionStatus'];
            } elseif (stripos($statusDetail, 'Format of the address is invalid Or User Already UnRegistered') !== false) {
                $apiStatus = app['sub_unreg'];
            } elseif (stripos($statusDetail, 'blacklisted') !== false) {
                $apiStatus = 'BLACKLISTED';
            } elseif (in_array($resp['subscriptionStatus'] ?? '', ['PENDING CONFIRMATION', 'PENDING_CONFIRMATION', 'PENDING'])) {
                $apiStatus = app['sub_not_confirmed'];
            }

            if ($apiStatus === null) {
                $stats['errors']++;
                echo "    [ERR] $rawAddress - API Error: " . ($statusCode ?? 'No code') . " ($statusDetail)\n";
                unsublog("Sync API Error: $rawAddress | Code: $statusCode | Detail: $statusDetail");
            } else {
                if ($apiStatus !== $currentStatus) {
                    $transKey = "$currentStatus -> $apiStatus";
                    $transitions[$transKey] = ($transitions[$transKey] ?? 0) + 1;
                    $stats['updated']++;

                    echo "    [UPDATE] $rawAddress: $currentStatus -> $apiStatus\n";

                    if (!$isDryRun) {
                        updateUserDB($mysqli, $rawAddress, [
                            'sub_status' => $apiStatus,
                            'sub_date' => $today
                        ]);
                        unsublog("Sync Updated: $rawAddress | $transKey");
                    }
                } else {
                    $stats['unchanged']++;
                    if (!$isDryRun && $user['sub_date'] !== $today) {
                        updateUserDB($mysqli, $rawAddress, [
                            'sub_date' => $today
                        ]);
                    }
                }
            }

            if ($sleepMs > 0) {
                usleep($sleepMs * 1000);
            }

            if ($processedInThisRun % 50 === 0) {
                echo "    ...processed $processedInThisRun users in this run ({$stats['checked']} total)...\n";
                $saveCheckpoint();
            }

            if ($syncLimit !== null && $processedInThisRun >= $syncLimit) {
                break;
            }
        }

        $saveCheckpoint();
    }

    echo "\nSync Complete: Checked {$stats['checked']} users, Updated {$stats['updated']}, Unchanged {$stats['unchanged']}, Errors {$stats['errors']}.\n";
    if (!empty($transitions)) {
        echo "  Transitions:\n";
        foreach ($transitions as $trans => $tCount) {
            echo "    * $trans: $tCount\n";
        }
    }
    echo "\n";
    unsublog("Sync Finished: Checked: {$stats['checked']} | Updated: {$stats['updated']} | Unchanged: {$stats['unchanged']} | Errors: {$stats['errors']} | DryRun: " . ($isDryRun ? "yes" : "no"));
}

// 2. DAILY UNSUBSCRIBE TASK
if ($doUnsub) {
    echo "[STEP 2] Selecting and unsubscribing $unsubCount users...\n";

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

            $respRaw = $subscription->UnregUser(app['app_id'], app['password'], $address);
            $resp = json_decode($respRaw, true);

            $statusCode = $resp['statusCode'] ?? null;
            $statusDetail = $resp['statusDetail'] ?? 'No detail';
            $subscriptionStatus = $resp['subscriptionStatus'] ?? null;

            if ($statusCode === 'S1000' || $subscriptionStatus === app['sub_unreg'] || stripos($statusDetail, 'already') !== false) {
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

            sleep(1);
        }

        echo "\nUnsubscribe Process Complete: $successCount succeeded, $failCount failed.\n";
        unsublog("Unsubscribe Summary: Processed $selectedCount users | Success: $successCount | Failed: $failCount");
    }
}

echo "\nDone!\n";
