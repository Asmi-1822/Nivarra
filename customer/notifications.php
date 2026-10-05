<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/customer-only.php';

$customerId = (int)($_SESSION['customer_id'] ?? 0);

if ($customerId <= 0) {
    header('Location: ../login.php');
    exit;
}

/*
|--------------------------------------------------------------------------
| Handle Notification Actions
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $action = trim((string)($_POST['action'] ?? ''));

    /*
    |--------------------------------------------------------------------------
    | Mark All As Read
    |--------------------------------------------------------------------------
    */

    if ($action === 'mark_all_read') {

        $stmt = $pdo->prepare("
            UPDATE customer_notifications
            SET is_read = 1
            WHERE customer_id = ?
              AND is_read = 0
        ");

        $stmt->execute([$customerId]);

        header('Location: notification.php');
        exit;
    }

    /*
    |--------------------------------------------------------------------------
    | Mark Individual Notification As Read
    |--------------------------------------------------------------------------
    */

    if ($action === 'mark_read') {

        $notificationId = (int)(
            $_POST['notification_id'] ?? 0
        );

        if ($notificationId > 0) {

            $stmt = $pdo->prepare("
                UPDATE customer_notifications
                SET is_read = 1
                WHERE id = ?
                  AND customer_id = ?
            ");

            $stmt->execute([
                $notificationId,
                $customerId
            ]);
        }

        header('Location: notification.php');
        exit;
    }

    /*
    |--------------------------------------------------------------------------
    | Delete Notification
    |--------------------------------------------------------------------------
    */

    if ($action === 'delete') {

        $notificationId = (int)(
            $_POST['notification_id'] ?? 0
        );

        if ($notificationId > 0) {

            $stmt = $pdo->prepare("
                DELETE FROM customer_notifications
                WHERE id = ?
                  AND customer_id = ?
            ");

            $stmt->execute([
                $notificationId,
                $customerId
            ]);
        }

        header('Location: notification.php');
        exit;
    }
}


/*
|--------------------------------------------------------------------------
| Filter
|--------------------------------------------------------------------------
*/

$filter = strtolower(
    trim((string)($_GET['filter'] ?? 'all'))
);

$allowedFilters = [
    'all',
    'unread',
    'read'
];

if (!in_array($filter, $allowedFilters, true)) {
    $filter = 'all';
}


/*
|--------------------------------------------------------------------------
| Build Notification Query
|--------------------------------------------------------------------------
*/

$sql = "
    SELECT
        id,
        customer_id,
        title,
        message,
        notification_type,
        is_read,
        created_at
    FROM customer_notifications
    WHERE customer_id = ?
";

$params = [$customerId];

if ($filter === 'unread') {

    $sql .= "
        AND is_read = 0
    ";

} elseif ($filter === 'read') {

    $sql .= "
        AND is_read = 1
    ";
}

$sql .= "
    ORDER BY created_at DESC
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);

$notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);


/*
|--------------------------------------------------------------------------
| Notification Counts
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT
        COUNT(*) AS total_count,
        SUM(CASE WHEN is_read = 0 THEN 1 ELSE 0 END) AS unread_count,
        SUM(CASE WHEN is_read = 1 THEN 1 ELSE 0 END) AS read_count
    FROM customer_notifications
    WHERE customer_id = ?
");

$stmt->execute([$customerId]);

$notificationCounts = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

$totalCount = (int)(
    $notificationCounts['total_count'] ?? 0
);

$unreadCount = (int)(
    $notificationCounts['unread_count'] ?? 0
);

$readCount = (int)(
    $notificationCounts['read_count'] ?? 0
);

include '../includes/header.php';
include '../includes/customer-navbar.php';

?>

<!-- =========================================================
     Main Content
========================================================= -->

<div class="container py-4">

    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">

        <div>

            <h2 class="mb-0">
                Notifications
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

    <div>

        <?php if ($unreadCount > 0): ?>

            <form
                method="post"
                class="mb-0">

                <input
                    type="hidden"
                    name="action"
                    value="mark_all_read">

                <button
                    type="submit"
                    class="btn btn-outline-danger">

                    Mark All as Read

                </button>

            </form>

        <?php endif; ?>

    </div>


    <!-- =====================================================
         Notification Filters
    ====================================================== -->

    <div class="d-flex flex-wrap align-items-center gap-2 mb-4">

        <a
            href="notification.php?filter=all"
            class="btn <?= $filter === 'all'
                ? 'btn-danger'
                : 'btn-outline-danger' ?>">

            All

            <span class="badge bg-light text-dark ms-1">
                <?= $totalCount ?>
            </span>

        </a>


        <a
            href="notification.php?filter=unread"
            class="btn <?= $filter === 'unread'
                ? 'btn-danger'
                : 'btn-outline-danger' ?>">

            Unread

            <span class="badge bg-light text-dark ms-1">
                <?= $unreadCount ?>
            </span>

        </a>


        <a
            href="notification.php?filter=read"
            class="btn <?= $filter === 'read'
                ? 'btn-danger'
                : 'btn-outline-danger' ?>">

            Read

            <span class="badge bg-light text-dark ms-1">
                <?= $readCount ?>
            </span>

        </a>

    </div>


    <!-- =====================================================
         Notifications
    ====================================================== -->

    <?php if (empty($notifications)): ?>

        <div class="card shadow-sm">

            <div class="card-body text-center py-5">

                <h5 class="mb-2">
                    No Notifications
                </h5>

                <p class="text-muted mb-0">

                    <?php if ($filter === 'unread'): ?>

                        You have no unread notifications.

                    <?php elseif ($filter === 'read'): ?>

                        You have no read notifications.

                    <?php else: ?>

                        You do not have any notifications yet.

                    <?php endif; ?>

                </p>

            </div>

        </div>

    <?php else: ?>

        <div class="d-flex flex-column gap-3">

            <?php foreach ($notifications as $notification): ?>

                <?php

                $notificationId = (int)(
                    $notification['id'] ?? 0
                );

                $title = (string)(
                    $notification['title'] ?? ''
                );

                $message = (string)(
                    $notification['message'] ?? ''
                );

                $type = (string)(
                    $notification['notification_type'] ?? ''
                );

                $isRead = (int)(
                    $notification['is_read'] ?? 0
                );

                $createdAt = (string)(
                    $notification['created_at'] ?? ''
                );

                $formattedDate = '';

                if ($createdAt !== '') {

                    $timestamp = strtotime($createdAt);

                    if ($timestamp !== false) {

                        $formattedDate = date(
                            'd M Y, h:i A',
                            $timestamp
                        );
                    }
                }

                ?>

                <div
                    class="card notification-card shadow-sm
                    <?= $isRead === 0
                        ? 'notification-unread'
                        : 'notification-read' ?>">

                    <div class="card-body">

                        <div class="row g-3 align-items-start">

                            <!-- Notification Content -->

                            <div class="col">

                                <div
                                    class="d-flex flex-wrap align-items-center gap-2 mb-2">

                                    <h5 class="mb-0">

                                        <?= e($title) ?>

                                    </h5>

                                    <span
                                        class="badge <?= e(
                                            notificationTypeClass($type)
                                        ) ?>">

                                        <?= e(
                                            notificationTypeLabel($type)
                                        ) ?>

                                    </span>

                                    <?php if ($isRead === 0): ?>

                                        <span class="badge bg-danger">
                                            Unread
                                        </span>

                                    <?php endif; ?>

                                </div>


                                <?php if ($message !== ''): ?>

                                    <p class="mb-2">

                                        <?= nl2br(
                                            e($message)
                                        ) ?>

                                    </p>

                                <?php endif; ?>


                                <?php if ($formattedDate !== ''): ?>

                                    <div class="small text-muted">

                                        <?= e($formattedDate) ?>

                                    </div>

                                <?php endif; ?>

                            </div>


                            <!-- Actions -->

                            <div class="col-auto">

                                <div
                                    class="d-flex flex-wrap gap-2">

                                    <?php if ($isRead === 0): ?>

                                        <form
                                            method="post"
                                            class="mb-0">

                                            <input
                                                type="hidden"
                                                name="action"
                                                value="mark_read">

                                            <input
                                                type="hidden"
                                                name="notification_id"
                                                value="<?= $notificationId ?>">

                                            <button
                                                type="submit"
                                                class="btn btn-sm btn-outline-success">

                                                Mark Read

                                            </button>

                                        </form>

                                    <?php endif; ?>


                                    <form
                                        method="post"
                                        class="mb-0"
                                        onsubmit="return confirm('Delete this notification?');">

                                        <input
                                            type="hidden"
                                            name="action"
                                            value="delete">

                                        <input
                                            type="hidden"
                                            name="notification_id"
                                            value="<?= $notificationId ?>">

                                        <button
                                            type="submit"
                                            class="btn btn-sm btn-outline-danger">

                                            Delete

                                        </button>

                                    </form>

                                </div>

                            </div>

                        </div>

                    </div>

                </div>

            <?php endforeach; ?>

        </div>

    <?php endif; ?>


</div>

<?php include '../includes/header.php';?>