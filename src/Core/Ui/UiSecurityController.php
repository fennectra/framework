<?php

namespace Fennec\Core\Ui;

use Fennec\Core\Response;
use Fennec\Core\Security\AccountLockout;
use Fennec\Core\Security\SecurityLogger;

class UiSecurityController
{
    use UiHelper;

    public function events(): void
    {
        $limit = $this->queryInt('limit', 50);
        $logDir = FENNEC_BASE_PATH . '/var/logs';

        $logFiles = glob($logDir . '/security-*.log');
        if ($logFiles === false) {
            $logFiles = [];
        }
        rsort($logFiles);
        $logFiles = array_slice($logFiles, 0, 7);

        $events = [];
        $eventCounts = [];
        $maxPerType = 5;

        foreach ($logFiles as $logFile) {
            $lines = array_filter(file($logFile, FILE_IGNORE_NEW_LINES));
            foreach (array_reverse($lines) as $line) {
                $parsed = $this->parseLogLine($line);
                if (!$parsed) {
                    continue;
                }

                $eventType = $parsed['event'];
                $eventCounts[$eventType] = ($eventCounts[$eventType] ?? 0) + 1;

                if ($eventCounts[$eventType] <= $maxPerType) {
                    $events[] = $parsed;
                }

                if (count($events) >= $limit) {
                    break 2;
                }
            }
        }

        $summary = [];
        foreach ($eventCounts as $type => $count) {
            $summary[] = ['event' => $type, 'count' => $count];
        }
        usort($summary, fn ($a, $b) => $b['count'] - $a['count']);

        Response::json([
            'events' => $events,
            'summary' => $summary,
            'total' => array_sum($eventCounts),
        ]);
    }

    public function lockouts(): void
    {
        $locked = AccountLockout::locked();
        $result = [];

        foreach ($locked as $identifier => $data) {
            $result[] = [
                'email' => $identifier,
                'attempts' => $data['attempts'],
                'locked_until' => date('c', $data['locked_until']),
                'remaining' => $data['remaining'],
            ];
        }

        Response::json($result);
    }

    public function unlock(): void
    {
        $body = $this->body();
        $email = $body['email'] ?? '';

        if (!$email) {
            Response::json(['error' => 'Email is required'], 422);

            return;
        }

        AccountLockout::reset($email);
        SecurityLogger::track('account.unlocked', ['email' => $email, 'by' => 'admin_ui']);

        Response::json(['success' => true]);
    }
}
