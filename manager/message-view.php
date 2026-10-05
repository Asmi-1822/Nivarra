<?php

declare(strict_types=1);

require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';
require_once '../includes/manager-only.php';
require_once '../includes/csrf.php';

$messageId = filter_input(
    INPUT_GET,
    'id',
    FILTER_VALIDATE_INT
);

if (!$messageId) {

    flash(
        'error',
        'Invalid message.'
    );

    redirect('messages.php');
}


/*
|--------------------------------------------------------------------------
| Actions
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    verify_csrf();

    $action = $_POST['action'] ?? '';


    /*
    |--------------------------------------------------------------------------
    | Save Reply
    |--------------------------------------------------------------------------
    */

    if ($action === 'save_reply') {

        $reply = trim(
            (string)($_POST['reply'] ?? '')
        );


        $stmt = $pdo->prepare("
            UPDATE contact_messages
            SET reply = ?
            WHERE id = ?
        ");

        $stmt->execute([
            $reply !== '' ? $reply : null,
            $messageId
        ]);


        flash(
            'success',
            'Reply saved successfully.'
        );

        redirect(
            'message-details.php?id=' .
            $messageId
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Mark Read
    |--------------------------------------------------------------------------
    */

    if ($action === 'mark_read') {

        $stmt = $pdo->prepare("
            UPDATE contact_messages
            SET status = 'read'
            WHERE id = ?
        ");

        $stmt->execute([
            $messageId
        ]);

        flash(
            'success',
            'Message marked as read.'
        );

        redirect(
            'message-details.php?id=' .
            $messageId
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Archive
    |--------------------------------------------------------------------------
    */

    if ($action === 'archive') {

        $stmt = $pdo->prepare("
            UPDATE contact_messages
            SET status = 'archived'
            WHERE id = ?
        ");

        $stmt->execute([
            $messageId
        ]);

        flash(
            'success',
            'Message archived.'
        );

        redirect('messages.php');
    }
}


/*
|--------------------------------------------------------------------------
| Load Message
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT
        id,
        name,
        email,
        subject,
        message,
        reply,
        status,
        created_at
    FROM contact_messages
    WHERE id = ?
    LIMIT 1
");

$stmt->execute([
    $messageId
]);

$message = $stmt->fetch(PDO::FETCH_ASSOC);


if (!$message) {

    flash(
        'error',
        'Message not found.'
    );

    redirect('messages.php');
}


$pageTitle = 'Message Details';

/*
|--------------------------------------------------------------------------
| Status Styling
|--------------------------------------------------------------------------
*/

$statusClass = match ($message['status']) {

    'unread'   => 'bg-danger',

    'read'     => 'bg-success',

    'archived' => 'bg-secondary',

    default    => 'bg-dark'
};

include '../includes/header.php';
include '../includes/manager-navbar.php';

?>

<div class="container py-4">

    <!-- Back Button -->

    <div class="mb-3">

        <a
            href="messages.php"
            class="btn btn-secondary d-flex align-items-center justify-content-center"
            style="width: 58.66px; height: 38px; padding: 0;">

            Back

        </a>

    </div>


    <div class="card shadow-sm">

        <div class="card-header bg-primary text-white">

            Message Details

        </div>


        <div class="card-body">


            <!-- Customer Information -->

            <div class="row mb-3">

                <div class="col-md-6">

                    <strong>Name:</strong>

                    <?= e($message['name']) ?>

                </div>


                <div class="col-md-6">

                    <strong>Email:</strong>

                    <a
                        href="mailto:<?= e($message['email']) ?>">

                        <?= e($message['email']) ?>

                    </a>

                </div>

            </div>


            <!-- Subject and Status -->

            <div class="row mb-3">

                <div class="col-md-6">

                    <strong>Subject:</strong>

                    <?= e($message['subject']) ?>

                </div>


                <div class="col-md-6">

                    <strong>Status:</strong>

                    <span
                        class="badge <?= $statusClass ?>">

                        <?= e(
                            ucfirst(
                                (string)$message['status']
                            )
                        ) ?>

                    </span>

                </div>

            </div>


            <!-- Received -->

            <div class="mb-3">

                <strong>Received:</strong>

                <?= e($message['created_at']) ?>

            </div>


            <!-- Customer Message -->

            <div class="mb-4">

                <strong>
                    Message:
                </strong>

                <div class="border rounded p-3 mt-2 bg-light">

                    <?= nl2br(
                        e($message['message'])
                    ) ?>

                </div>

            </div>


            <!-- Manager Reply -->

            <div class="mb-4">

                <label
                    for="reply"
                    class="form-label fw-bold">

                    Reply

                </label>

                <form
                    method="POST"
                    action="message-details.php?id=<?= $messageId ?>">

                    <?= csrf_input() ?>

                    <input
                        type="hidden"
                        name="action"
                        value="save_reply">

                    <textarea
                        name="reply"
                        id="reply"
                        class="form-control"
                        rows="6"
                        placeholder="Enter your reply to the customer..."><?= e((string)($message['reply'] ?? '')) ?></textarea>

                    <div class="mt-2">

                        <button
                            type="submit"
                            class="btn btn-maroon">

                            Save Reply

                        </button>

                    </div>

                </form>

            </div>


            <!-- Existing Reply -->

            <?php if (
                trim((string)($message['reply'] ?? '')) !== ''
            ): ?>

                <div class="mb-4">

                    <strong>
                        Current Reply:
                    </strong>

                    <div class="border rounded p-3 mt-2 bg-light">

                        <?= nl2br(
                            e((string)$message['reply'])
                        ) ?>

                    </div>

                </div>

            <?php endif; ?>


            <!-- Actions -->

            <div class="d-flex gap-2 flex-wrap">

                <?php if (
                    $message['status'] === 'unread'
                ): ?>

                    <form method="POST">

                        <?= csrf_input() ?>

                        <input
                            type="hidden"
                            name="action"
                            value="mark_read">

                        <button
                            type="submit"
                            class="btn btn-success">

                            Mark Read

                        </button>

                    </form>

                <?php endif; ?>


                <?php if (
                    $message['status'] !== 'archived'
                ): ?>

                    <form method="POST">

                        <?= csrf_input() ?>

                        <input
                            type="hidden"
                            name="action"
                            value="archive">

                        <button
                            type="submit"
                            class="btn btn-warning"
                            onclick="return confirm('Archive this message?')">

                            Archive

                        </button>

                    </form>

                <?php endif; ?>

            </div>

        </div>

    </div>

</div>

<?php include '../includes/footer.php'; ?>