<?php

declare(strict_types=1);


/*
|--------------------------------------------------------------------------
| CSRF Token
|--------------------------------------------------------------------------
*/

function csrf_token(): string
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }

    if (
        empty($_SESSION['csrf_token']) ||
        !is_string($_SESSION['csrf_token'])
    ) {
        $_SESSION['csrf_token'] = bin2hex(
            random_bytes(32)
        );
    }

    return $_SESSION['csrf_token'];
}


/*
|--------------------------------------------------------------------------
| Generate CSRF Token
|--------------------------------------------------------------------------
*/

function generateCsrfToken(): string
{
    return csrf_token();
}


/*
|--------------------------------------------------------------------------
| Verify CSRF Token
|--------------------------------------------------------------------------
*/

function verifyCsrfToken(?string $token): bool
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }

    if (
        empty($_SESSION['csrf_token']) ||
        !is_string($_SESSION['csrf_token']) ||
        empty($token)
    ) {
        return false;
    }

    return hash_equals(
        $_SESSION['csrf_token'],
        $token
    );
}


/*
|--------------------------------------------------------------------------
| Strict CSRF Verification
|--------------------------------------------------------------------------
|
| Existing forms use:
|
| verify_csrf();
|
| Invalid tokens result in HTTP 403.
|--------------------------------------------------------------------------
*/

function verify_csrf(?string $token = null): void
{
    if ($token === null) {
        $token = $_POST['csrf_token'] ?? '';
    }

    if (
        !is_string($token) ||
        !verifyCsrfToken($token)
    ) {
        http_response_code(403);

        exit('Invalid CSRF token');
    }
}


/*
|--------------------------------------------------------------------------
| CSRF Hidden Input
|--------------------------------------------------------------------------
*/

function csrf_input(): string
{
    return sprintf(
        '<input type="hidden" name="csrf_token" value="%s">',
        htmlspecialchars(
            csrf_token(),
            ENT_QUOTES | ENT_SUBSTITUTE,
            'UTF-8'
        )
    );
}