<?php

declare(strict_types=1);

/**
 * This file is part of the MultiFlexi package
 *
 * https://multiflexi.eu/
 *
 * (c) Vítězslav Dvořák <http://vitexsoftware.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace MultiFlexi\Ui;

require_once './init.php';

WebPage::singleton()->onlyForLogged();

$jobId = (int) WebPage::singleton()->getRequestValue('id', 'int');

if ($jobId <= 0) {
    http_response_code(400);

    exit;
}

\MultiFlexi\Security\CompanyAccessControl::enforceJobAccess($jobId);

$jobber = new \MultiFlexi\Job($jobId);

if (!$jobber->getMyKey()) {
    http_response_code(404);

    exit;
}

// SSE headers — must be sent before any output
header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('X-Accel-Buffering: no');  // disable nginx proxy buffering
header('Connection: keep-alive');

// Release the session lock — the stream can run for many minutes and must not
// block other requests (e.g. a page refresh) of the same user.
if (session_status() === \PHP_SESSION_ACTIVE) {
    session_write_close();
}

// Disable output buffering so events reach the browser immediately
while (ob_get_level() > 0) {
    ob_end_flush();
}

if (\function_exists('set_time_limit')) {
    set_time_limit(0);
}

$outputLines = new \MultiFlexi\JobOutputLine();
// Optional cursor so the job page can skip lines already shown in the terminal snapshot
$lastId = max(0, (int) WebPage::singleton()->getRequestValue('after', 'int'));

// On automatic EventSource reconnect the browser sends the id of the last event it saw
if (isset($_SERVER['HTTP_LAST_EVENT_ID']) && ctype_digit((string) $_SERVER['HTTP_LAST_EVENT_ID'])) {
    $lastId = max($lastId, (int) $_SERVER['HTTP_LAST_EVENT_ID']);
}

$lastHeartbeat = time();
$startTime = time();
$maxRuntime = 1800; // 30 minutes
$beginNotified = $jobber->getDataValue('begin') !== null;

/**
 * Emit a single SSE event.
 */
$emit = static function (string $event, array $data, ?int $id = null): void {
    if ($id !== null) {
        echo "id: {$id}\n";
    }

    echo "event: {$event}\n";
    echo 'data: '.json_encode($data)."\n\n";
    flush();
};

// Tell the browser to reconnect quickly and flush headers + padding right away,
// so proxies do not sit on the (still silent) response while the job is queued.
echo "retry: 2000\n";
echo ': '.str_repeat(' ', 2048)."\n\n";
flush();

// Stream loop
while (true) {
    if (time() - $startTime >= $maxRuntime) {
        $emit('timeout', ['message' => 'Stream timeout after 30 minutes']);

        break;
    }

    // Fetch new lines since last seen id
    $rows = $outputLines->getLinesSince($jobId, $lastId);

    foreach ($rows as $row) {
        $emit('output', [
            'id' => (int) $row['id'],
            'seq' => (int) $row['seq'],
            'type' => $row['type'],
            'line' => $row['line'],
            'created_at' => $row['created_at'],
        ], (int) $row['id']);
        $lastId = (int) $row['id'];
    }

    // Reload job to check if it has started/finished
    $jobber->loadFromSQL($jobId);

    if (!$beginNotified) {
        $begin = $jobber->getDataValue('begin');

        if ($begin !== null) {
            $emit('started', ['begin' => $begin]);
            $beginNotified = true;
        }
    }

    $exitcode = $jobber->getDataValue('exitcode');

    if ($exitcode !== null) {
        // Drain any remaining lines that arrived after the last fetch
        $remaining = $outputLines->getLinesSince($jobId, $lastId);

        foreach ($remaining as $row) {
            $emit('output', [
                'id' => (int) $row['id'],
                'seq' => (int) $row['seq'],
                'type' => $row['type'],
                'line' => $row['line'],
                'created_at' => $row['created_at'],
            ], (int) $row['id']);
        }

        $endValue = $jobber->getDataValue('end');

        $emit('done', [
            'exitcode' => (int) $exitcode,
            'exitcode_description' => $jobber->getExitCodeDescription(),
            'end' => $endValue,
            'end_ts' => $endValue ? (new \DateTime($endValue))->getTimestamp() : time(),
        ]);

        break;
    }

    // Keep-alive comment: prevents proxy / FastCGI idle timeouts while the job
    // is still waiting to start and nothing else is being sent.
    if (time() - $lastHeartbeat >= 10) {
        echo ": keep-alive\n\n";
        flush();
        $lastHeartbeat = time();

        if (connection_aborted()) {
            break;
        }
    }

    usleep(300000); // 300 ms poll interval
}
