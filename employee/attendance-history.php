<?php

declare(strict_types=1);

require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';
require_once '../includes/employee-only.php';

$employeeId = (int)$_SESSION['employee_id'];

/*
|--------------------------------------------------------------------------
| Monthly Statistics
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT
        COUNT(*) AS days_worked,
        SUM(total_hours) AS total_hours
    FROM attendance_logs
    WHERE employee_id = ?
    AND MONTH(attendance_date) = MONTH(CURDATE())
    AND YEAR(attendance_date) = YEAR(CURDATE())
");

$stmt->execute([$employeeId]);

$stats = $stmt->fetch(PDO::FETCH_ASSOC);

/*
|--------------------------------------------------------------------------
| Attendance History
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT *
    FROM attendance_logs
    WHERE employee_id = ?
    ORDER BY attendance_date DESC
");

$stmt->execute([$employeeId]);

$attendanceRecords =
    $stmt->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = 'Attendance History';

include '../includes/header.php';
include '../includes/employee-navbar.php';
?>

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-start mb-4">

        <div>
            <h2 class="mb-4">

                Attendance History

            </h2>
        </div>

        <div class="d-flex flex-column align-items-end gap-2">

            <a
                href="profile.php"
                class="btn btn-secondary d-flex align-items-center justify-content-center"
                style="width: 58.66px; height: 38px; padding: 0;">

                Back

            </a>

        </div>
    </div>

    <!-- Statistics -->

    <div class="row mb-4">

        <div class="col-md-6">

            <div class="card shadow-sm">

                <div class="card-body text-center">

                    <h3>
                        <?= (int)($stats['days_worked'] ?? 0) ?>
                    </h3>

                    <small>
                        Days Worked This Month
                    </small>

                </div>

            </div>

        </div>

        <div class="col-md-6">

            <div class="card shadow-sm">

                <div class="card-body text-center">

                    <h3>
                        <?= number_format(
                            (float)($stats['total_hours'] ?? 0),
                            2
                        ) ?>
                    </h3>

                    <small>
                        Hours Worked This Month
                    </small>

                </div>

            </div>

        </div>

    </div>

    <!-- Attendance Table -->

    <div class="card shadow-sm">

        <div class="card-header bg-primary text-white">

            Attendance Records

        </div>

        <div class="card-body">

            <div class="table-responsive">

                <table class="table table-striped">

                    <thead>

                    <tr>

                        <th>Date</th>
                        <th>Check In</th>
                        <th>Check Out</th>
                        <th>Total Hours</th>
                        <th>Status</th>

                    </tr>

                    </thead>

                    <tbody>

                    <?php if(empty($attendanceRecords)): ?>

                        <tr>

                            <td
                                colspan="5"
                                class="text-center">

                                No attendance records found.

                            </td>

                        </tr>

                    <?php endif; ?>

                    <?php foreach(
                        $attendanceRecords
                        as $record
                    ): ?>

                        <?php

                        $badge = match(
                            $record['status']
                        ) {

                            'present'
                                => 'success',

                            'late'
                                => 'warning',

                            'half_day'
                                => 'info',

                            'absent'
                                => 'danger',

                            default
                                => 'secondary'
                        };

                        ?>

                        <tr>

                            <td>

                                <?= e(
                                    $record['attendance_date']
                                ) ?>

                            </td>

                            <td>

                                <?= e(
                                    $record['check_in_time']
                                    ?? '-'
                                ) ?>

                            </td>

                            <td>

                                <?= e(
                                    $record['check_out_time']
                                    ?? '-'
                                ) ?>

                            </td>

                            <td>

                                <?= e(
                                    (string)(
                                        $record['total_hours']
                                        ?? 0
                                    )
                                ) ?>

                            </td>

                            <td>

                                <span
                                    class="badge bg-<?= $badge ?>">

                                    <?= ucfirst(
                                        e(
                                            $record['status']
                                        )
                                    ) ?>

                                </span>

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