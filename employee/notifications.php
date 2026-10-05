<?php

declare(strict_types=1);

require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';
require_once '../includes/employee-only.php';
require_once '../includes/csrf.php';

$employeeId = (int)$_SESSION['employee_id'];

/*
|--------------------------------------------------------------------------
| Mark As Read
|--------------------------------------------------------------------------
*/

if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_POST['mark_read'])
) {
    verify_csrf();

    $notificationId =
        (int)($_POST['notification_id'] ?? 0);

    $stmt = $pdo->prepare("
        UPDATE employee_notifications
        SET status = 'read'
        WHERE id = ?
        AND employee_id = ?
    ");

    $stmt->execute([
        $notificationId,
        $employeeId
    ]);

    redirect('notifications.php');
}

/*
|--------------------------------------------------------------------------
| Archive Notification
|--------------------------------------------------------------------------
*/

if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_POST['archive'])
) {
    verify_csrf();

    $notificationId =
        (int)($_POST['notification_id'] ?? 0);

    $stmt = $pdo->prepare("
        UPDATE employee_notifications
        SET status = 'archived'
        WHERE id = ?
        AND employee_id = ?
    ");

    $stmt->execute([
        $notificationId,
        $employeeId
    ]);

    redirect('notifications.php');
}

/*
|--------------------------------------------------------------------------
| Filter
|--------------------------------------------------------------------------
*/

$status =
    $_GET['status'] ?? 'all';

$sql = "
    SELECT *
    FROM employee_notifications
    WHERE employee_id = ?
";

$params = [$employeeId];

if (
    in_array(
        $status,
        ['unread', 'read', 'archived'],
        true
    )
) {
    $sql .= " AND status = ?";
    $params[] = $status;
}

$sql .= "
    ORDER BY created_at DESC
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);

$notifications =
    $stmt->fetchAll(PDO::FETCH_ASSOC);

/*
|--------------------------------------------------------------------------
| Unread Count
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT COUNT(*)
    FROM employee_notifications
    WHERE employee_id = ?
    AND status = 'unread'
");

$stmt->execute([$employeeId]);

$unreadCount =
    (int)$stmt->fetchColumn();

$pageTitle = 'Notifications';

include '../includes/header.php';
include '../includes/employee-navbar.php';
?>

<div class="container py-4">

    <!-- Header -->

    <div class="d-flex justify-content-between align-items-start mb-4">

        <div>

            <h2 class="mb-0">

                Notifications

            </h2>

        </div>

        <a
            href="dashboard.php"
            class="btn btn-secondary d-flex align-items-center justify-content-center"
            style="width: 58.66px; height: 38px; padding: 0;">

            Back

        </a>

    </div>


    <!-- Filters -->

    <div class="d-flex justify-content-between align-items-center mb-3">

        <div class="d-flex gap-2">

            <a
                href="?status=all"
                class="btn btn-outline-secondary btn-sm">

                All

            </a>

            <a
                href="?status=unread"
                class="btn btn-outline-primary btn-sm">

                Unread

            </a>

            <a
                href="?status=read"
                class="btn btn-outline-success btn-sm">

                Read

            </a>

            <a
                href="?status=archived"
                class="btn btn-outline-dark btn-sm">

                Archived

            </a>

        </div>

        <span class="badge bg-danger">

            <?= $unreadCount ?>

            Unread

        </span>

    </div>


    <!-- Notifications -->

    <?php if(empty($notifications)): ?>

        <div class="alert alert-info">

            No notifications found.

        </div>

    <?php endif; ?>


    <?php foreach($notifications as $notification): ?>

        <?php

        $badge = match($notification['type']) {

            'leave'      => 'warning',
            'shift'      => 'primary',
            'coverage'   => 'info',
            'evaluation' => 'success',

            default      => 'secondary'
        };

        ?>

        <div class="card shadow-sm mb-3">

            <div class="card-body">

                <div
                    class="d-flex justify-content-between align-items-start">

                    <div>

                        <h5>

                            <?= e($notification['title']) ?>

                            <span
                                class="badge bg-<?= $badge ?>">

                                <?= ucfirst(
                                    e($notification['type'])
                                ) ?>

                            </span>

                        </h5>

                        <p class="mb-2">

                            <?= nl2br(
                                e($notification['message'])
                            ) ?>

                        </p>

                        <small class="text-muted">

                            <?= e(
                                $notification['created_at']
                            ) ?>

                        </small>

                    </div>


                    <div>

                        <?php if(
                            $notification['status']
                            === 'unread'
                        ): ?>

                            <form
                                method="POST"
                                class="mb-2">

                                <?= csrf_input() ?>

                                <input
                                    type="hidden"
                                    name="notification_id"
                                    value="<?= (int)$notification['id'] ?>">

                                <button
                                    type="submit"
                                    name="mark_read"
                                    class="btn btn-sm btn-success">

                                    Mark Read

                                </button>

                            </form>

                        <?php endif; ?>


                        <?php if(
                            $notification['status']
                            !== 'archived'
                        ): ?>

                            <form method="POST">

                                <?= csrf_input() ?>

                                <input
                                    type="hidden"
                                    name="notification_id"
                                    value="<?= (int)$notification['id'] ?>">

                                <button
                                    type="submit"
                                    name="archive"
                                    class="btn btn-sm btn-outline-dark">

                                    Archive

                                </button>

                            </form>

                        <?php endif; ?>

                    </div>

                </div>

            </div>

        </div>

    <?php endforeach; ?>

</div>

<?php include '../includes/footer.php'; ?>