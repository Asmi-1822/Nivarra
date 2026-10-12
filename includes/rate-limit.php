<?php

declare(strict_types=1);

function rateLimit(
    string $action,
    int $maxRequests,
    int $seconds
): void {

    $key = 'rate_' . $action;

    $now = time();

    if (!isset($_SESSION[$key])) {

        $_SESSION[$key] = [
            'count' => 1,
            'time' => $now
        ];

        return;
    }

    if (
        ($now - $_SESSION[$key]['time'])
        > $seconds
    ) {

        $_SESSION[$key] = [
            'count' => 1,
            'time' => $now
        ];

        return;
    }

    $_SESSION[$key]['count']++;

    if (
        $_SESSION[$key]['count']
        > $maxRequests
    ) {

        http_response_code(429);

        exit('Too many requests.');
    }
}