#!/usr/bin/env php
<?php

/**
 * Unified Subscriber Sync & Daily Unsubscribe Script
 * Supports both mSpace (bulk pagination) and Ideamart (individual verification).
 *
 * Usage:
 *   php cron_sync_and_unsubscribe.php [options]
 *
 * General Options:
 *   --sync                 Run subscriber synchronization into local database
 *   --unsub                Pick 2 to 5 random REGISTERED subscribers and unsubscribe them
 *   --both                 Execute both sync and unsubscribe (Default behavior)
 *   --dry-run              Simulate operations without making API unsubscribe calls or modifying DB
 *   --count=<N>            Specify exact number of subscribers to unsubscribe (default: random 2 to 5)
 *   --mode=<type>          Force sync mode: 'bulk' (mSpace style) or 'individual' (Ideamart style). Default: auto-detect from PLATFORM
 *   --help, -h             Show this help message
 *
 * mSpace (Bulk Mode) Options:
 *   --max-pages=<N>        Limit sync to at most N pages of subscribers
 *   --charging-info        Enrich subscribers with detailed charging info (/subscription/getSubscriberChargingInfo)
 *
 * Ideamart (Individual Mode) Options:
 *   --limit=<N>            Limit number of candidate users to verify
 *   --all-statuses         Check all users including UNREGISTERED (by default UNREGISTERED users are skipped)
 *   --skip-today-updated   Skip users whose status was already verified/updated today
 *   --sleep-ms=<N>         Milliseconds to pause between API queries (default: 100ms)
 *   --reset                Reset and ignore saved same-day progress checkpoint
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
    "mode::",
    "max-pages::",
    "charging-info",
    "limit::",
    "all-statuses",
    "skip-today-updated",
    "sleep-ms::",
    "reset",
    "help"
]);

if (isset($options['h']) || isset($options['help'])) {
    echo "Unified Subscriber Sync & Daily Unsubscribe Script\n\n";
    echo "Usage:\n";
    echo "  php cron_sync_and_unsubscribe.php [options]\n\n";
    echo "General Options:\n";
    echo "  --sync                 Run subscriber synchronization into local database\n";
    echo "  --unsub                Pick 2 to 5 random REGISTERED subscribers and unsubscribe them\n";
    echo "  --both                 Execute both sync and unsubscribe (Default behavior)\n";
    echo "  --dry-run              Simulate operations without making API calls or modifying DB\n";
    echo "  --count=<N>            Specify exact number of users to unsubscribe (default: random 2 to 5)\n";
    echo "  --mode=<type>          Force mode: 'bulk' (mSpace) or 'individual' (Ideamart)\n";
    echo "  --help, -h             Show this help message\n\n";
    echo "mSpace (Bulk Mode) Options:\n";
    echo "  --max-pages=<N>        Limit sync to at most N pages\n";
    echo "  --charging-info        Fetch detailed charging info via getSubscriberChargingInfo\n\n";
    echo "Ideamart (Individual Mode) Options:\n";
    echo "  --limit=<N>            Limit number of candidate users to check\n";
    echo "  --all-statuses         Check all users including UNREGISTERED\n";
    echo "  --skip-today-updated   Skip users already updated today\n";
    echo "  --sleep-ms=<N>         Milliseconds between API calls (default: 100ms)\n";
    echo "  --reset                Reset saved same-day progress checkpoint\n\n";
    exit(0);
}

$isDryRun = isset($options['dry-run']);
$doSync = isset($options['sync']);
$doUnsub = isset($options['unsub']);
$doBoth = isset($options['both']);

if (!$doSync && !$doUnsub) {
    $doSync = true;
    $doUnsub = true;
} elseif ($doBoth) {
    $doSync = true;
    $doUnsub = true;
}

// Determine Platform Mode
$configuredPlatform = strtolower(app['platform'] ?? $_ENV['PLATFORM'] ?? 'ideamart');
$modeOption = isset($options['mode']) ? strtolower((string)$options['mode']) : null;
$isBulkMode = ($modeOption === 'bulk') || ($modeOption === null && $configuredPlatform === 'mspace');

// Unsubscription count
$unsubCount = null;
if (isset($options['count']) && is_numeric($options['count']) && (int)$options['count'] > 0) {
    $unsubCount = (int)$options['count'];
} else {
    $unsubCount = rand(2, 5);
}

// Bulk options
$maxPages = null;
if (isset($options['max-pages']) && is_numeric($options['max-pages']) && (int)$options['max-pages'] > 0) {
    $maxPages = (int)$options['max-pages'];
}
$enrichCharging = isset($options['charging-info']);

// Individual options
$syncLimit = null;
if (isset($options['limit']) && is_numeric($options['limit']) && (int)$options['limit'] > 0) {
    $syncLimit = (int)$options['limit'];
}
$allStatuses = isset($options['all-statuses']);
$skipTodayUpdated = isset($options['skip-today-updated']);
$resetCheckpoint = isset($options['reset']);
$sleepMs = 100;
if (isset($options['sleep-ms']) && is_numeric($options['sleep-ms']) && (int)$options['sleep-ms'] >= 0) {
    $sleepMs = (int)$options['sleep-ms'];
}

$today = date('Y-m-d');
$checkpointFile = __DIR__ . '/log/sync_checkpoint.json';
$isMaintenanceActive = isset($_ENV['APP_MAINTENANCE']) && filter_var($_ENV['APP_MAINTENANCE'], FILTER_VALIDATE_BOOLEAN);

echo "=====================================================\n";
echo "Telco Subscriber Sync & Daily Unsubscribe Manager\n";
echo "Date: " . date('Y-m-d H:i:s') . "\n";
echo "Platform: " . strtoupper($configuredPlatform) . " (" . ($isBulkMode ? "Bulk Sync" : "Individual Verification") . ")\n";
echo "Mode: " . ($isDryRun ? "DRY RUN (Simulation)" : "LIVE EXECUTION") . "\n";
if ($isMaintenanceActive) {
    echo "Maintenance: ACTIVE (CLI Bypass)\n";
}
echo "Tasks: " . ($doSync ? "[Sync" . ($isBulkMode ? ($maxPages ? " (Max $maxPages pages)" : "") : ($syncLimit ? " (Limit $syncLimit)" : "")) . "] " : "") . ($doUnsub ? "[Unsubscribe ($unsubCount users)]" : "") . "\n";
echo "=====================================================\n\n";

if ($isMaintenanceActive) {
    unsublog("Notice: Script running under ACTIVE Maintenance Mode (CLI Bypass)");
}

$subscription = new Subscription(
    app['sub_msg_url'] ?? null,
    app['sub_status_url'] ?? null,
    app['sub_base_url'] ?? null,
    app['sub_list_url'] ?? null,
    app['sub_charging_info_url'] ?? null
);

function normalizeSubscriberAddress($address) {
    $address = trim($address);
    if (strpos($address, 'tel:') === 0) {
        return 'tel:' . trim(substr($address, 4));
    }
    return 'tel:' . $address;
}

class CliProgressBar {
    private int $total;
    private int $current = 0;
    private float $startTime;
    private bool $isTty;
    private int $barWidth;
    private float $lastRenderTime = 0;
    private array $lastExtra = [];

    public function __construct(int $total, int $barWidth = 26) {
        $this->total = max(1, $total);
        $this->barWidth = $barWidth;
        $this->startTime = microtime(true);
        $this->isTty = function_exists('posix_isatty') && @posix_isatty(STDOUT);
    }

    public function advance(int $step = 1, array $extra = []): void {
        $this->current += $step;
        if (!empty($extra)) {
            $this->lastExtra = $extra;
        }
        $now = microtime(true);

        if ($this->isTty) {
            if ($now - $this->lastRenderTime < 0.1 && $this->current < $this->total) {
                return;
            }
            $this->lastRenderTime = $now;
            $this->renderTty($this->lastExtra);
        } else {
            $interval = max(50, (int)($this->total / 10));
            if ($this->current % $interval === 0 || $this->current >= $this->total) {
                $percent = min(100, round(($this->current / $this->total) * 100, 1));
                $extraStr = !empty($this->lastExtra) ? " [" . implode(", ", array_map(fn($k, $v) => "$k: $v", array_keys($this->lastExtra), $this->lastExtra)) . "]" : "";
                echo "  -> Progress: {$percent}% ({$this->current}/{$this->total}){$extraStr}\n";
            }
        }
    }

    public function logMessage(string $msg): void {
        if ($this->isTty) {
            echo "\r\033[K" . $msg . "\n";
            $this->renderTty($this->lastExtra);
        } else {
            echo $msg . "\n";
        }
    }

    private function renderTty(array $extra = []): void {
        $percent = min(100, (int)round(($this->current / $this->total) * 100));
        $filled = (int)round(($this->current / $this->total) * $this->barWidth);
        $empty = max(0, $this->barWidth - $filled);
        $bar = str_repeat('=', max(0, $filled - 1)) . ($filled > 0 ? '>' : '') . str_repeat('-', $empty);

        $elapsed = max(0.001, microtime(true) - $this->startTime);
        $rate = $this->current / $elapsed;
        $remaining = $rate > 0 ? max(0, ($this->total - $this->current) / $rate) : 0;

        $etaStr = sprintf("%02dm %02ds", floor($remaining / 60), $remaining % 60);
        $extraParts = [];
        foreach ($extra as $k => $v) {
            $extraParts[] = "$k: $v";
        }
        $extraStr = !empty($extraParts) ? " | " . implode(" | ", $extraParts) : "";

        echo sprintf("\r  [%s] %3d%% (%d/%d) ETA: %s%s", $bar, $percent, $this->current, $this->total, $etaStr, $extraStr);
        flush();
    }

    public function finish(): void {
        if ($this->isTty) {
            $this->renderTty($this->lastExtra);
            echo "\n";
        }
    }
}

// 1. SUBSCRIBER SYNC TASK
if ($doSync) {
    if ($isBulkMode) {
        // --- BULK MODE (mSpace) ---
        echo "[STEP 1] Syncing subscribers from mSpace API (/subscription/getSubscriberList)...\n";
        
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
            if (isset($subscribers['subscriberId'])) {
                $subscribers = [$subscribers];
            }

            $countInPage = count($subscribers);
            echo "Received $countInPage subscriber(s)\n";

            if ($countInPage === 0) {
                break;
            }

            $pagesProcessed++;

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
                    usleep(200000);
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
                
                if (isset($chargingInfoMap[$address]['subscriptionStatus'])) {
                    $subStatus = $chargingInfoMap[$address]['subscriptionStatus'];
                }

                $subDate = date('Y-m-d');
                if (!empty($lastChargedDate)) {
                    $datePart = substr($lastChargedDate, 0, 10);
                    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $datePart)) {
                        $subDate = $datePart;
                    }
                }

                $totalFetched++;

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

            $moreDataAvailable = $response['moreDataAvailable'] ?? false;
            $nextPage = isset($response['nextPageNumber']) ? (int)$response['nextPageNumber'] : -1;

            $hasMore = ($moreDataAvailable === true || $moreDataAvailable === 'true') && ($nextPage > $page);
            $page = $nextPage;

            usleep(300000);
        }

        echo "Sync Complete: Processed $pagesProcessed page(s), Fetched $totalFetched valid users ($totalSkipped N/A skipped), Inserted $totalInserted new, Updated $totalUpdated existing.\n\n";
        unsublog("Sync Finished: Pages: $pagesProcessed | Fetched: $totalFetched | Inserted: $totalInserted | Updated: $totalUpdated | Skipped: $totalSkipped | DryRun: " . ($isDryRun ? "yes" : "no"));

    } else {
        // --- INDIVIDUAL MODE (Ideamart) ---
        echo "[STEP 1] Synchronizing subscribers via Ideamart API (/subscription/getStatus)...\n";

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

        $targetCount = ($syncLimit !== null) ? min($syncLimit, $totalCandidates) : $totalCandidates;
        $progressBar = new CliProgressBar($targetCount);

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
                $progressBar->logMessage("  Reached specified limit of $syncLimit users. Stopping sync.");
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
                    $progressBar->logMessage("    [ERR] $rawAddress - API Error: " . ($statusCode ?? 'No code') . " ($statusDetail)");
                    unsublog("Sync API Error: $rawAddress | Code: $statusCode | Detail: $statusDetail");
                } else {
                    if ($apiStatus !== $currentStatus) {
                        $transKey = "$currentStatus -> $apiStatus";
                        $transitions[$transKey] = ($transitions[$transKey] ?? 0) + 1;
                        $stats['updated']++;

                        $progressBar->logMessage("    [UPDATE] $rawAddress: $currentStatus -> $apiStatus");

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

                $progressBar->advance(1, [
                    'Updated' => $stats['updated'],
                    'Unchanged' => $stats['unchanged'],
                    'Errors' => $stats['errors']
                ]);

                if ($sleepMs > 0) {
                    usleep($sleepMs * 1000);
                }

                if ($processedInThisRun % 50 === 0) {
                    $saveCheckpoint();
                }

                if ($syncLimit !== null && $processedInThisRun >= $syncLimit) {
                    break;
                }
            }

            $saveCheckpoint();
        }

        $progressBar->finish();

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
}

// 2. DAILY UNSUBSCRIBE TASK (Shared by all platforms)
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
