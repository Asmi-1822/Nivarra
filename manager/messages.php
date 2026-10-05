<?php

declare(strict_types=1);

require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';
require_once '../includes/manager-only.php';
require_once '../includes/csrf.php';

$pageTitle = 'Customer Messages';

/*
|--------------------------------------------------------------------------
| Actions
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    verify_csrf();

    $messageId =
        (int)($_POST['message_id'] ?? 0);

    $action =
        $_POST['action'] ?? '';

    $allowed = [
        'read',
        'unread',
        'archive'
    ];

    if (
        $messageId > 0 &&
        in_array(
            $action,
            $allowed,
            true
        )
    ) {

        $statusMap = [
            'read' => 'read',
            'unread' => 'unread',
            'archive' => 'archived'
        ];

        $stmt = $pdo->prepare("
            UPDATE contact_messages
            SET status = ?
            WHERE id = ?
        ");

        $stmt->execute([
            $statusMap[$action],
            $messageId
        ]);

        if (function_exists('auditLog')) {

            auditLog(
                $_SESSION['employee_id'],
                'message_status_update',
                'Message #' .
                $messageId .
                ' changed to ' .
                $statusMap[$action]
            );
        }

        flash(
            'success',
            'Message updated.'
        );

        redirect('messages.php');
    }
}

/*
|--------------------------------------------------------------------------
| Statistics
|--------------------------------------------------------------------------
*/

$stats = $pdo->query("
    SELECT
        COUNT(*) total,
        SUM(status='unread') unread_count,
        SUM(status='read') read_count,
        SUM(status='archived') archived_count
    FROM contact_messages
")->fetch(PDO::FETCH_ASSOC);

/*
|--------------------------------------------------------------------------
| Filters
|--------------------------------------------------------------------------
*/

$search =
    trim($_GET['search'] ?? '');

$status =
    trim($_GET['status'] ?? '');

$where = [];
$params = [];

if ($search !== '') {

    $where[] = "
        (
            name LIKE ?
            OR email LIKE ?
            OR subject LIKE ?
        )
    ";

    $term =
        '%' . $search . '%';

    $params[] = $term;
    $params[] = $term;
    $params[] = $term;
}

if ($status !== '') {

    $where[] = 'status = ?';

    $params[] = $status;
}

$whereSql = '';

if ($where) {

    $whereSql =
        'WHERE ' .
        implode(
            ' AND ',
            $where
        );
}

/*
|--------------------------------------------------------------------------
| Messages
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT *
    FROM contact_messages
    $whereSql
    ORDER BY created_at DESC
");

$stmt->execute($params);

$messages =
    $stmt->fetchAll(
        PDO::FETCH_ASSOC
    );

include '../includes/header.php';
include '../includes/manager-navbar.php';
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between mb-4">

        <div>

            <h2 class="mb-4">

                Customer Messages

            </h2>

        </div>

        <div class="d-flex flex-column align-items-end gap-2">

            <a
                href="dashboard.php"
                class="btn btn-secondary d-flex align-items-center justify-content-center"
                style="width: 58.66px; height: 38px; padding: 0;">

                Back

            </a>

        </div>

    </div>

    <?php if ($msg = flashMessage('success')): ?>

        <div class="alert alert-success">

            <?= e($msg) ?>

        </div>

    <?php endif; ?>

    <!-- Statistics -->

    <div class="row g-3 mb-4">

        <div class="col-md-3">

            <div class="card shadow-sm">

                <div class="card-body text-center">

                    <h3>

                        <?= (int)$stats['total'] ?>

                    </h3>

                    <p class="mb-0">

                        Total

                    </p>

                </div>

            </div>

        </div>

        <div class="col-md-3">

            <div class="card shadow-sm">

                <div class="card-body text-center">

                    <h3 class="text-danger">

                        <?= (int)$stats['unread_count'] ?>

                    </h3>

                    <p class="mb-0">

                        Unread

                    </p>

                </div>

            </div>

        </div>

        <div class="col-md-3">

            <div class="card shadow-sm">

                <div class="card-body text-center">

                    <h3 class="text-success">

                        <?= (int)$stats['read_count'] ?>

                    </h3>

                    <p class="mb-0">

                        Read

                    </p>

                </div>

            </div>

        </div>

        <div class="col-md-3">

            <div class="card shadow-sm">

                <div class="card-body text-center">

                    <h3 class="text-secondary">

                        <?= (int)$stats['archived_count'] ?>

                    </h3>

                    <p class="mb-0">

                        Archived

                    </p>

                </div>

            </div>

        </div>

    </div>

    <!-- Search -->

    <div class="card shadow-sm mb-4">

        <div class="card-body">

            <form method="GET">

                <div class="row g-3">

                    <div class="col-md-6">

                        <input
                            type="text"
                            name="search"
                            class="form-control"
                            value="<?= e($search) ?>"
                            placeholder="Search messages">

                    </div>

                    <div class="col-md-4">

                        <select
                            name="status"
                            class="form-select">

                            <option value="">
                                All Statuses
                            </option>

                            <option value="unread">
                                Unread
                            </option>

                            <option value="read">
                                Read
                            </option>

                            <option value="archived">
                                Archived
                            </option>

                        </select>

                    </div>

                    <div class="col-md-2">

                        <button
                            class="btn btn-maroon w-100">

                            Filter

                        </button>

                    </div>

                </div>

            </form>

        </div>

    </div>

    <!-- Messages Table -->

    <div class="card shadow-sm">

        <div class="card-body">

            <div class="table-responsive">

                <table class="table table-hover">

                    <thead>

                    <tr>

                        <th>Name</th>
                        <th>Email</th>
                        <th>Subject</th>
                        <th>Status</th>
                        <th>Date</th>
                        <th>Actions</th>

                    </tr>

                    </thead>

                    <tbody>

                    <?php foreach ($messages as $message): ?>

                        <tr>

                            <td>

                                <?= e(
                                    $message['name']
                                ) ?>

                            </td>

                            <td>

                                <?= e(
                                    $message['email']
                                ) ?>

                            </td>

                            <td>

                                <?= e(
                                    $message['subject']
                                ) ?>

                            </td>

                            <td>

                                <span class="badge bg-<?=
                                    match(
                                        $message['status']
                                    ) {
                                        'read' => 'success',
                                        'archived' => 'secondary',
                                        default => 'danger'
                                    }
                                ?>">

                                    <?= e(
                                        ucfirst(
                                            $message['status']
                                        )
                                    ) ?>

                                </span>

                            </td>

                            <td>

                                <?= e(
                                    $message['created_at']
                                ) ?>

                            </td>

                            <td>

                                <a
                                    href="message-view.php?id=<?= (int)$message['id'] ?>"
                                    class="btn btn-info btn-sm">

                                    View

                                </a>

                            </td>

                        </tr>

                    <?php endforeach; ?>

                    </tbody>

                </table>

            </div>

        </div>

    </div>

</div>

<?php include '../includes/footer.php'; ?>