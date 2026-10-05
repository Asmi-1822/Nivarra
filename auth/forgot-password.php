<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/mailer.php';
require_once __DIR__ . '/includes/email_templates.php';

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    try {
        verify_csrf($_POST['csrf_token'] ?? '');

        $email = trim((string)($_POST['email'] ?? ''));

        if ($email === '') {
            throw new RuntimeException('Please enter your email address.');
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('Please enter a valid email address.');
        }

        $userType = null;
        $userId = null;
        $recipientName = 'NIVARRA User';

        /*
        |--------------------------------------------------------------------------
        | Check Employee
        |--------------------------------------------------------------------------
        */

        $stmt = $pdo->prepare("
            SELECT id, full_name, email
            FROM employees
            WHERE email = ?
              AND active = 1
            LIMIT 1
        ");

        $stmt->execute([$email]);

        $employee = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($employee) {
            $userType = 'employee';
            $userId = (int)$employee['id'];

            $recipientName = trim(
                (string)($employee['full_name'] ?? '')
            );

            if ($recipientName === '') {
                $recipientName = 'NIVARRA Employee';
            }
        }

        /*
        |--------------------------------------------------------------------------
        | Check Customer
        |--------------------------------------------------------------------------
        */

        if ($userType === null) {

            $stmt = $pdo->prepare("
                SELECT
                    c.id,
                    c.email,
                    c.username,
                    cp.first_name,
                    cp.last_name
                FROM customers c
                LEFT JOIN customer_profiles cp
                    ON cp.customer_id = c.id
                WHERE c.email = ?
                  AND c.status = 'active'
                LIMIT 1
            ");

            $stmt->execute([$email]);

            $customer = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($customer) {
                $userType = 'customer';
                $userId = (int)$customer['id'];

                $recipientName = trim(
                    (string)($customer['first_name'] ?? '') . ' ' .
                    (string)($customer['last_name'] ?? '')
                );

                if ($recipientName === '') {
                    $recipientName = trim(
                        (string)($customer['username'] ?? '')
                    );
                }

                if ($recipientName === '') {
                    $recipientName = 'NIVARRA Customer';
                }
            }
        }

        /*
        |--------------------------------------------------------------------------
        | Always show the same response
        |--------------------------------------------------------------------------
        */

        $message = 'If an active NIVARRA account exists for that email address, '
                 . 'a password reset link has been sent. Please check your email.';

        /*
        |--------------------------------------------------------------------------
        | Do not continue if account does not exist
        |--------------------------------------------------------------------------
        */

        if ($userType === null || $userId === null) {
            return;
        }

        /*
        |--------------------------------------------------------------------------
        | Invalidate previous unused reset tokens
        |--------------------------------------------------------------------------
        */

        $stmt = $pdo->prepare("
            UPDATE password_resets
            SET used_at = CURRENT_TIMESTAMP
            WHERE user_type = ?
              AND user_id = ?
              AND used_at IS NULL
        ");

        $stmt->execute([
            $userType,
            $userId
        ]);

        /*
        |--------------------------------------------------------------------------
        | Generate secure token
        |--------------------------------------------------------------------------
        */

        $rawToken = bin2hex(random_bytes(32));

        $tokenHash = hash(
            'sha256',
            $rawToken
        );

        /*
        |--------------------------------------------------------------------------
        | Token expiration
        |--------------------------------------------------------------------------
        */

        $expiresAt = date(
            'Y-m-d H:i:s',
            time() + (PASSWORD_RESET_EXPIRY_MINUTES * 60)
        );

        /*
        |--------------------------------------------------------------------------
        | Store hashed token
        |--------------------------------------------------------------------------
        */

        $stmt = $pdo->prepare("
            INSERT INTO password_resets
            (
                user_type,
                user_id,
                token_hash,
                expires_at
            )
            VALUES (?, ?, ?, ?)
        ");

        $stmt->execute([
            $userType,
            $userId,
            $tokenHash,
            $expiresAt
        ]);

        /*
        |--------------------------------------------------------------------------
        | Build reset URL
        |--------------------------------------------------------------------------
        */

        $resetUrl = APP_URL
                  . '/reset-password.php?token='
                  . urlencode($rawToken);

        /*
        |--------------------------------------------------------------------------
        | Create email
        |--------------------------------------------------------------------------
        */

        $emailBody = passwordResetTemplate(
            $resetUrl,
            $recipientName
        );

        /*
        |--------------------------------------------------------------------------
        | Send email
        |--------------------------------------------------------------------------
        */

        $sent = sendMail(
            $email,
            'NIVARRA - Password Reset',
            $emailBody
        );

        /*
        |--------------------------------------------------------------------------
        | If email failed, invalidate the token.
        |--------------------------------------------------------------------------
        */

        if (!$sent) {

            $stmt = $pdo->prepare("
                UPDATE password_resets
                SET used_at = CURRENT_TIMESTAMP
                WHERE token_hash = ?
                LIMIT 1
            ");

            $stmt->execute([$tokenHash]);

            error_log(
                'NIVARRA password reset email could not be sent to: '
                . $email
            );
        }

    } catch (Throwable $e) {

        error_log(
            'NIVARRA forgot-password error: '
            . $e->getMessage()
        );

        $error = $e instanceof RuntimeException
            ? $e->getMessage()
            : 'Unable to process your request. Please try again.';
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
                    <h4 class="mb-0">Forgot Password</h4>
                </div>

                <div class="card-body">

                    <?php if ($message !== ''): ?>

                        <div class="alert alert-success">
                            <?= e($message) ?>
                        </div>

                    <?php endif; ?>

                    <?php if ($error !== ''): ?>

                        <div class="alert alert-danger">
                            <?= e($error) ?>
                        </div>

                    <?php endif; ?>

                    <p class="text-muted">
                        Enter the email address associated with your
                        NIVARRA account and we will send you a password
                        reset link.
                    </p>

                    <form method="POST" action="">

                        <?= csrf_input() ?>

                        <div class="mb-3">

                            <label for="email" class="form-label">
                                Email Address
                            </label>

                            <input
                                type="email"
                                class="form-control"
                                id="email"
                                name="email"
                                maxlength="150"
                                autocomplete="email"
                                required
                            >

                        </div>

                        <button type="submit"
                                class="btn btn-maroon w-100">
                            Send Reset Link
                        </button>

                    </form>

                    <div class="text-center mt-3">

                        <a href="login.php">
                            Return to Login
                        </a>

                    </div>

                </div>

            </div>

        </div>

    </div>

</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>