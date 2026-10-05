<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/csrf.php';

$error = '';
$success = '';

$token = trim((string)($_GET['token'] ?? $_POST['token'] ?? ''));

$resetRecord = null;

/*
|--------------------------------------------------------------------------
| Validate token
|--------------------------------------------------------------------------
*/

if ($token !== '') {

    $tokenHash = hash(
        'sha256',
        $token
    );

    $stmt = $pdo->prepare("
        SELECT
            id,
            user_type,
            user_id,
            expires_at,
            used_at
        FROM password_resets
        WHERE token_hash = ?
        LIMIT 1
    ");

    $stmt->execute([$tokenHash]);

    $resetRecord = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$resetRecord) {
        $error = 'This password reset link is invalid or has already been used.';
    } elseif (!empty($resetRecord['used_at'])) {
        $error = 'This password reset link has already been used.';
    } elseif (strtotime((string)$resetRecord['expires_at']) < time()) {
        $error = 'This password reset link has expired.';
    }
}

/*
|--------------------------------------------------------------------------
| Process new password
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $error === '') {

    try {

        verify_csrf($_POST['csrf_token'] ?? '');

        $password = (string)($_POST['password'] ?? '');
        $confirmPassword = (string)($_POST['confirm_password'] ?? '');

        if ($password === '') {
            throw new RuntimeException(
                'Please enter a new password.'
            );
        }

        if (strlen($password) < 8) {
            throw new RuntimeException(
                'Password must contain at least 8 characters.'
            );
        }

        if ($password !== $confirmPassword) {
            throw new RuntimeException(
                'Passwords do not match.'
            );
        }

        if (!$resetRecord) {
            throw new RuntimeException(
                'This password reset link is invalid or has expired.'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Re-check token inside the request
        |--------------------------------------------------------------------------
        */

        $tokenHash = hash(
            'sha256',
            $token
        );

        $stmt = $pdo->prepare("
            SELECT
                id,
                user_type,
                user_id,
                expires_at,
                used_at
            FROM password_resets
            WHERE token_hash = ?
            LIMIT 1
        ");

        $stmt->execute([$tokenHash]);

        $resetRecord = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$resetRecord) {
            throw new RuntimeException(
                'This password reset link is invalid.'
            );
        }

        if (!empty($resetRecord['used_at'])) {
            throw new RuntimeException(
                'This password reset link has already been used.'
            );
        }

        if (strtotime((string)$resetRecord['expires_at']) < time()) {
            throw new RuntimeException(
                'This password reset link has expired.'
            );
        }

        $userType = (string)$resetRecord['user_type'];
        $userId = (int)$resetRecord['user_id'];

        $passwordHash = password_hash(
            $password,
            PASSWORD_DEFAULT
        );

        if ($passwordHash === false) {
            throw new RuntimeException(
                'Unable to secure the new password.'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Update employee password
        |--------------------------------------------------------------------------
        */

        if ($userType === 'employee') {

            $stmt = $pdo->prepare("
                UPDATE employees
                SET password_hash = ?
                WHERE id = ?
                LIMIT 1
            ");

            $stmt->execute([
                $passwordHash,
                $userId
            ]);

        /*
        |--------------------------------------------------------------------------
        | Update customer password
        |--------------------------------------------------------------------------
        */

        } elseif ($userType === 'customer') {

            $stmt = $pdo->prepare("
                UPDATE customers
                SET
                    password_hash = ?,
                    updated_at = CURRENT_TIMESTAMP
                WHERE id = ?
                LIMIT 1
            ");

            $stmt->execute([
                $passwordHash,
                $userId
            ]);

        } else {

            throw new RuntimeException(
                'Invalid account type.'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Mark token as used
        |--------------------------------------------------------------------------
        */

        $stmt = $pdo->prepare("
            UPDATE password_resets
            SET used_at = CURRENT_TIMESTAMP
            WHERE id = ?
            LIMIT 1
        ");

        $stmt->execute([
            (int)$resetRecord['id']
        ]);

        /*
        |--------------------------------------------------------------------------
        | Success
        |--------------------------------------------------------------------------
        */

        $success = 'Your password has been reset successfully.';

        $token = '';

    } catch (Throwable $e) {

        error_log(
            'NIVARRA reset-password error: '
            . $e->getMessage()
        );

        $error = $e instanceof RuntimeException
            ? $e->getMessage()
            : 'Unable to reset your password. Please try again.';
    }
}
?>

<?php require_once __DIR__ . '/includes/header.php'; ?>

<div class="container py-5">

    <div class="row justify-content-center">

        <div class="col-md-6 col-lg-5">

            <div class="mb-3">

                <a href="login.php"
                   class="btn btn-secondary d-flex align-items-center justify-content-center"
                   style="width:58.66px; height:38px; padding:0;">
                    Back
                </a>

            </div>

            <div class="card shadow-sm">

                <div class="card-header bg-maroon text-white">

                    <h4 class="mb-0">
                        Reset Password
                    </h4>

                </div>

                <div class="card-body">

                    <?php if ($success !== ''): ?>

                        <div class="alert alert-success">

                            <?= e($success) ?>

                        </div>

                        <div class="text-center mt-3">

                            <a href="login.php"
                               class="btn btn-maroon">
                                Go to Login
                            </a>

                        </div>

                    <?php elseif ($error !== ''): ?>

                        <div class="alert alert-danger">

                            <?= e($error) ?>

                        </div>

                        <div class="text-center mt-3">

                            <a href="forgot-password.php"
                               class="btn btn-maroon">
                                Request New Reset Link
                            </a>

                        </div>

                    <?php elseif ($token !== ''): ?>

                        <p class="text-muted">
                            Enter a new password for your NIVARRA account.
                        </p>

                        <form method="POST" action="">

                            <?= csrf_input() ?>

                            <input
                                type="hidden"
                                name="token"
                                value="<?= e($token) ?>"
                            >

                            <div class="mb-3">

                                <label
                                    for="password"
                                    class="form-label">
                                    New Password
                                </label>

                                <input
                                    type="password"
                                    class="form-control"
                                    id="password"
                                    name="password"
                                    minlength="8"
                                    autocomplete="new-password"
                                    required
                                >

                                <div class="form-text">
                                    Minimum 8 characters.
                                </div>

                            </div>

                            <div class="mb-3">

                                <label
                                    for="confirm_password"
                                    class="form-label">
                                    Confirm New Password
                                </label>

                                <input
                                    type="password"
                                    class="form-control"
                                    id="confirm_password"
                                    name="confirm_password"
                                    minlength="8"
                                    autocomplete="new-password"
                                    required
                                >

                            </div>

                            <button
                                type="submit"
                                class="btn btn-maroon w-100">
                                Reset Password
                            </button>

                        </form>

                    <?php else: ?>

                        <div class="alert alert-danger">

                            Invalid password reset request.

                        </div>

                        <div class="text-center">

                            <a href="forgot-password.php"
                               class="btn btn-maroon">
                                Request New Reset Link
                            </a>

                        </div>

                    <?php endif; ?>

                </div>

            </div>

        </div>

    </div>

</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>