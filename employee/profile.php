<?php

declare(strict_types=1);

require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';
require_once '../includes/employee-only.php';

$pageTitle = 'My Profile';

$employeeId = (int)($_SESSION['employee_id'] ?? 0);

if ($employeeId <= 0) {
    header('Location: ../login.php');
    exit;
}

/*
|--------------------------------------------------------------------------
| Employee Information
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT
        e.*,
        ep.address,
        ep.phone,
        ep.emergency_contact,
        ep.leave_allowance
    FROM employees e
    LEFT JOIN employee_profiles ep
        ON ep.employee_id = e.id
    WHERE e.id = ?
    LIMIT 1
");

$stmt->execute([$employeeId]);

$employee = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$employee) {
    header('Location: dashboard.php');
    exit;
}

/*
|--------------------------------------------------------------------------
| Leave Summary
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT
        COALESCE(
            SUM(
                CASE
                    WHEN status = 'approved'
                    THEN DATEDIFF(end_date, start_date) + 1
                    ELSE 0
                END
            ),
            0
        ) AS approved_leave_days,

        COALESCE(
            SUM(
                CASE
                    WHEN status = 'pending'
                    THEN DATEDIFF(end_date, start_date) + 1
                    ELSE 0
                END
            ),
            0
        ) AS pending_leave_days

    FROM leave_requests
    WHERE employee_id = ?
");

$stmt->execute([$employeeId]);

$leaveSummary =
    $stmt->fetch(PDO::FETCH_ASSOC)
    ?: [];

$leaveAllowance =
    (int)($employee['leave_allowance'] ?? 0);

$approvedLeaveDays =
    (int)($leaveSummary['approved_leave_days'] ?? 0);

$pendingLeaveDays =
    (int)($leaveSummary['pending_leave_days'] ?? 0);

$remainingLeave =
    max(
        0,
        $leaveAllowance - $approvedLeaveDays
    );

/*
|--------------------------------------------------------------------------
| Ratings
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT
        COUNT(*) AS rating_count,
        COALESCE(AVG(rating), 0) AS average_rating
    FROM employee_ratings
    WHERE employee_id = ?
");

$stmt->execute([$employeeId]);

$ratingSummary =
    $stmt->fetch(PDO::FETCH_ASSOC)
    ?: [];

$averageRating =
    (float)($ratingSummary['average_rating'] ?? 0);

$ratingCount =
    (int)($ratingSummary['rating_count'] ?? 0);

/*
|--------------------------------------------------------------------------
| Evaluations
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT
        COUNT(*) AS evaluation_count,

        COALESCE(
            AVG(
                (
                    punctuality_score
                    + teamwork_score
                    + customer_service_score
                    + productivity_score
                ) / 4
            ),
            0
        ) AS average_score

    FROM evaluations
    WHERE employee_id = ?
");

$stmt->execute([$employeeId]);

$evaluationSummary =
    $stmt->fetch(PDO::FETCH_ASSOC)
    ?: [];

$evaluationCount =
    (int)($evaluationSummary['evaluation_count'] ?? 0);

$averageEvaluation =
    (float)($evaluationSummary['average_score'] ?? 0);

/*
|--------------------------------------------------------------------------
| Upcoming Shifts
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT
        id,
        shift_date,
        start_time,
        end_time,
        position
    FROM shifts
    WHERE employee_id = ?
      AND shift_date >= CURDATE()
    ORDER BY shift_date ASC, start_time ASC
    LIMIT 5
");

$stmt->execute([$employeeId]);

$upcomingShifts =
    $stmt->fetchAll(PDO::FETCH_ASSOC);

/*
|--------------------------------------------------------------------------
| Attendance
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT
        attendance_date,
        check_in_time,
        check_out_time,
        total_hours,
        status
    FROM attendance_logs
    WHERE employee_id = ?
    ORDER BY attendance_date DESC
    LIMIT 5
");

$stmt->execute([$employeeId]);

$attendanceHistory =
    $stmt->fetchAll(PDO::FETCH_ASSOC);

/*
|--------------------------------------------------------------------------
| Shift Coverage Requests
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT
        scr.id,
        scr.status,
        scr.created_at,
        s.shift_date,
        s.start_time,
        s.end_time,
        e.full_name AS covering_employee_name
    FROM shift_coverage_requests scr
    JOIN shifts s
        ON s.id = scr.shift_id
    LEFT JOIN employees e
        ON e.id = scr.covering_employee_id
    WHERE scr.requesting_employee_id = ?
    ORDER BY scr.created_at DESC
    LIMIT 5
");

$stmt->execute([$employeeId]);

$coverageRequests =
    $stmt->fetchAll(PDO::FETCH_ASSOC);

/*
|--------------------------------------------------------------------------
| Notifications
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT
        id,
        title,
        message,
        status,
        created_at
    FROM employee_notifications
    WHERE employee_id = ?
    ORDER BY created_at DESC
    LIMIT 5
");

$stmt->execute([$employeeId]);

$notifications =
    $stmt->fetchAll(PDO::FETCH_ASSOC);



include '../includes/header.php';
include '../includes/employee-navbar.php';
?>

<div class="container-fluid py-4">

    <!-- Header -->

    <div class="d-flex justify-content-between align-items-start mb-4">

        <div>

            <h2>
                My Profile
            </h2>

            <p class="text-muted mb-0">
                View your personal information and employee activity
            </p>

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


    <!-- Employee Information -->

    <div class="row g-4">

        <div class="col-lg-8">

            <div class="card shadow-sm mb-4">

                <div class="card-header">

                    <h5 class="mb-0">
                        Employee Information
                    </h5>

                </div>

                <div class="card-body">

                    <div class="row g-3">

                        <div class="col-md-6">

                            <strong>
                                Employee Code
                            </strong>

                            <div class="text-muted">

                                <?= e(
                                    (string)($employee['employee_code'] ?? '')
                                ) ?>

                            </div>

                        </div>


                        <div class="col-md-6">

                            <strong>
                                Full Name
                            </strong>

                            <div class="text-muted">

                                <?= e(
                                    (string)($employee['full_name'] ?? '')
                                ) ?>

                            </div>

                        </div>


                        <div class="col-md-6">

                            <strong>
                                Username
                            </strong>

                            <div class="text-muted">

                                <?= e(
                                    (string)($employee['username'] ?? '')
                                ) ?>

                            </div>

                        </div>


                        <div class="col-md-6">

                            <strong>
                                Email
                            </strong>

                            <div class="text-muted">

                                <?= e(
                                    (string)($employee['email'] ?? '')
                                ) ?>

                            </div>

                        </div>


                        <div class="col-md-6">

                            <strong>
                                Phone
                            </strong>

                            <div class="text-muted">

                                <?= e(
                                    (string)($employee['phone'] ?? '')
                                ) ?: 'Not provided' ?>

                            </div>

                        </div>


                        <div class="col-md-6">

                            <strong>
                                Position
                            </strong>

                            <div class="text-muted">

                                <?= e(
                                    (string)($employee['position'] ?? '')
                                ) ?: 'Not assigned' ?>

                            </div>

                        </div>


                        <div class="col-md-6">

                            <strong>
                                Role
                            </strong>

                            <div class="text-muted text-capitalize">

                                <?= e(
                                    (string)($employee['role'] ?? '')
                                ) ?>

                            </div>

                        </div>


                        <div class="col-md-6">

                            <strong>
                                Hire Date
                            </strong>

                            <div class="text-muted">

                                <?php if (
                                    !empty($employee['hire_date'])
                                ): ?>

                                    <?= e(
                                        date(
                                            'd M Y',
                                            strtotime(
                                                (string)$employee['hire_date']
                                            )
                                        )
                                    ) ?>

                                <?php else: ?>

                                    Not available

                                <?php endif; ?>

                            </div>

                        </div>


                        <div class="col-md-6">

                            <strong>
                                Emergency Contact
                            </strong>

                            <div class="text-muted">

                                <?= e(
                                    (string)($employee['emergency_contact'] ?? '')
                                ) ?: 'Not provided' ?>

                            </div>

                        </div>


                        <div class="col-md-6">

                            <strong>
                                Address
                            </strong>

                            <div class="text-muted">

                                <?= e(
                                    (string)($employee['address'] ?? '')
                                ) ?: 'Not provided' ?>

                            </div>

                        </div>

                    </div>

                </div>

            </div>


            <!-- Leave -->

            <div class="card shadow-sm mb-4">

                <div class="card-header">

                    <h5 class="mb-0">
                        Leave Summary
                    </h5>

                </div>

                <div class="card-body">

                    <div class="row text-center g-3">

                        <div class="col-md-4">

                            <div class="border rounded p-3">

                                <h4 class="mb-1">

                                    <?= $leaveAllowance ?>

                                </h4>

                                <small class="text-muted">

                                    Leave Allowance

                                </small>

                            </div>

                        </div>


                        <div class="col-md-4">

                            <div class="border rounded p-3">

                                <h4 class="mb-1">

                                    <?= $approvedLeaveDays ?>

                                </h4>

                                <small class="text-muted">

                                    Approved Days

                                </small>

                            </div>

                        </div>


                        <div class="col-md-4">

                            <div class="border rounded p-3">

                                <h4 class="mb-1">

                                    <?= $remainingLeave ?>

                                </h4>

                                <small class="text-muted">

                                    Remaining Days

                                </small>

                            </div>

                        </div>

                    </div>

                    <div class="mt-3">

                        <small class="text-muted">

                            Pending leave days:
                            <?= $pendingLeaveDays ?>

                        </small>

                    </div>

                </div>

            </div>


            <!-- Upcoming Shifts -->

            <div class="card shadow-sm mb-4">

                <div class="card-header">

                    <h5 class="mb-0">
                        Upcoming Shifts
                    </h5>

                </div>

                <div class="card-body p-0">

                    <div class="table-responsive">

                        <table class="table table-hover mb-0">

                            <thead>

                                <tr>

                                    <th>
                                        Date
                                    </th>

                                    <th>
                                        Shift
                                    </th>

                                    <th>
                                        Time
                                    </th>

                                    <th>
                                        Position
                                    </th>

                                </tr>

                            </thead>

                            <tbody>

                                <?php if (!$upcomingShifts): ?>

                                    <tr>

                                        <td
                                            colspan="4"
                                            class="text-center text-muted py-4">

                                            No upcoming shifts.

                                        </td>

                                    </tr>

                                <?php else: ?>

                                    <?php foreach (
                                        $upcomingShifts
                                        as $shift
                                    ): ?>

                                        <tr>

                                            <td>

                                                <?= e(
                                                    date(
                                                        'd M Y',
                                                        strtotime(
                                                            (string)$shift['shift_date']
                                                        )
                                                    )
                                                ) ?>

                                            </td>

                                            <td>

                                                <?=
                                                    e(
                                                        profileShiftType(
                                                            (string)$shift['start_time'],
                                                            (string)$shift['end_time']
                                                        )
                                                    )
                                                ?>

                                            </td>

                                            <td>

                                                <?= e(
                                                    date(
                                                        'h:i A',
                                                        strtotime(
                                                            (string)$shift['start_time']
                                                        )
                                                    )
                                                ) ?>

                                                -

                                                <?= e(
                                                    date(
                                                        'h:i A',
                                                        strtotime(
                                                            (string)$shift['end_time']
                                                        )
                                                    )
                                                ) ?>

                                            </td>

                                            <td>

                                                <?= e(
                                                    (string)($shift['position'] ?? '')
                                                ) ?: 'Not assigned' ?>

                                            </td>

                                        </tr>

                                    <?php endforeach; ?>

                                <?php endif; ?>

                            </tbody>

                        </table>

                    </div>

                </div>

            </div>


            <!-- Attendance History -->

            <div class="card shadow-sm mb-4">

                <div class="card-header">

                    <h5 class="mb-0">
                        Attendance History
                    </h5>

                </div>

                <div class="card-body p-0">

                    <div class="table-responsive">

                        <table class="table table-hover mb-0">

                            <thead>

                                <tr>

                                    <th>
                                        Date
                                    </th>

                                    <th>
                                        Check In
                                    </th>

                                    <th>
                                        Check Out
                                    </th>

                                    <th>
                                        Hours
                                    </th>

                                    <th>
                                        Status
                                    </th>

                                </tr>

                            </thead>

                            <tbody>

                                <?php if (!$attendanceHistory): ?>

                                    <tr>

                                        <td
                                            colspan="5"
                                            class="text-center text-muted py-4">

                                            No attendance records found.

                                        </td>

                                    </tr>

                                <?php else: ?>

                                    <?php foreach (
                                        $attendanceHistory
                                        as $attendance
                                    ): ?>

                                        <tr>

                                            <td>

                                                <?= e(
                                                    date(
                                                        'd M Y',
                                                        strtotime(
                                                            (string)$attendance['attendance_date']
                                                        )
                                                    )
                                                ) ?>

                                            </td>

                                            <td>

                                                <?= !empty(
                                                    $attendance['check_in_time']
                                                )
                                                    ? e(
                                                        date(
                                                            'h:i A',
                                                            strtotime(
                                                                (string)$attendance['check_in_time']
                                                            )
                                                        )
                                                    )
                                                    : '-' ?>

                                            </td>

                                            <td>

                                                <?= !empty(
                                                    $attendance['check_out_time']
                                                )
                                                    ? e(
                                                        date(
                                                            'h:i A',
                                                            strtotime(
                                                                (string)$attendance['check_out_time']
                                                            )
                                                        )
                                                    )
                                                    : '-' ?>

                                            </td>

                                            <td>

                                                <?= number_format(
                                                    (float)($attendance['total_hours'] ?? 0),
                                                    2
                                                ) ?>

                                            </td>

                                            <td>

                                                <span
                                                    class="badge <?= profileBadgeClass(
                                                        (string)($attendance['status'] ?? '')
                                                    ) ?>">

                                                    <?= e(
                                                        ucfirst(
                                                            (string)($attendance['status'] ?? '')
                                                        )
                                                    ) ?>

                                                </span>

                                            </td>

                                        </tr>

                                    <?php endforeach; ?>

                                <?php endif; ?>

                            </tbody>

                        </table>

                    </div>

                </div>

            </div>


            <!-- Shift Coverage -->

            <div class="card shadow-sm mb-4">

                <div class="card-header">

                    <h5 class="mb-0">
                        Shift Coverage Requests
                    </h5>

                </div>

                <div class="card-body p-0">

                    <div class="table-responsive">

                        <table class="table table-hover mb-0">

                            <thead>

                                <tr>

                                    <th>
                                        Date
                                    </th>

                                    <th>
                                        Time
                                    </th>

                                    <th>
                                        Covering Employee
                                    </th>

                                    <th>
                                        Status
                                    </th>

                                </tr>

                            </thead>

                            <tbody>

                                <?php if (!$coverageRequests): ?>

                                    <tr>

                                        <td
                                            colspan="4"
                                            class="text-center text-muted py-4">

                                            No coverage requests found.

                                        </td>

                                    </tr>

                                <?php else: ?>

                                    <?php foreach (
                                        $coverageRequests
                                        as $request
                                    ): ?>

                                        <tr>

                                            <td>

                                                <?= e(
                                                    date(
                                                        'd M Y',
                                                        strtotime(
                                                            (string)$request['shift_date']
                                                        )
                                                    )
                                                ) ?>

                                            </td>

                                            <td>

                                                <?= e(
                                                    date(
                                                        'h:i A',
                                                        strtotime(
                                                            (string)$request['start_time']
                                                        )
                                                    )
                                                ) ?>

                                                -

                                                <?= e(
                                                    date(
                                                        'h:i A',
                                                        strtotime(
                                                            (string)$request['end_time']
                                                        )
                                                    )
                                                ) ?>

                                            </td>

                                            <td>

                                                <?= e(
                                                    (string)(
                                                        $request['covering_employee_name']
                                                        ?? ''
                                                    )
                                                ) ?: 'Not assigned' ?>

                                            </td>

                                            <td>

                                                <span
                                                    class="badge <?= profileBadgeClass(
                                                        (string)($request['status'] ?? '')
                                                    ) ?>">

                                                    <?= e(
                                                        ucfirst(
                                                            (string)($request['status'] ?? '')
                                                        )
                                                    ) ?>

                                                </span>

                                            </td>

                                        </tr>

                                    <?php endforeach; ?>

                                <?php endif; ?>

                            </tbody>

                        </table>

                    </div>

                </div>

            </div>

        </div>


        <!-- Right Column -->

        <div class="col-lg-4">

            <!-- Statistics -->

            <div class="card shadow-sm mb-4">

                <div class="card-header">

                    <h5 class="mb-0">
                        My Statistics
                    </h5>

                </div>

                <div class="card-body">

                    <div class="d-flex justify-content-between mb-3">

                        <span>
                            Average Rating
                        </span>

                        <strong>

                            <?= number_format(
                                $averageRating,
                                2
                            ) ?>

                        </strong>

                    </div>


                    <div class="d-flex justify-content-between mb-3">

                        <span>
                            Ratings Received
                        </span>

                        <strong>

                            <?= $ratingCount ?>

                        </strong>

                    </div>


                    <div class="d-flex justify-content-between">

                        <span>
                            Average Evaluation
                        </span>

                        <strong>

                            <?= number_format(
                                $averageEvaluation,
                                2
                            ) ?>

                        </strong>

                    </div>

                    <div class="d-flex justify-content-between mt-3">

                        <span>
                            Evaluations
                        </span>

                        <strong>

                            <?= $evaluationCount ?>

                        </strong>

                    </div>

                </div>

            </div>


            <!-- Notifications -->

            <div class="card shadow-sm mb-4">

                <div class="card-header">

                    <h5 class="mb-0">
                        Recent Notifications
                    </h5>

                </div>

                <div class="card-body">

                    <?php if (!$notifications): ?>

                        <p class="text-muted mb-0">

                            No notifications.

                        </p>

                    <?php else: ?>

                        <?php foreach (
                            $notifications
                            as $notification
                        ): ?>

                            <div class="border-bottom pb-3 mb-3">

                                <div class="d-flex justify-content-between gap-2">

                                    <strong>

                                        <?= e(
                                            (string)($notification['title'] ?? '')
                                        ) ?>

                                    </strong>

                                    <span
                                        class="badge <?= profileBadgeClass(
                                            (string)($notification['status'] ?? '')
                                        ) ?>">

                                        <?= e(
                                            ucfirst(
                                                (string)($notification['status'] ?? '')
                                            )
                                        ) ?>

                                    </span>

                                </div>

                                <p class="small text-muted mb-1 mt-2">

                                    <?= e(
                                        (string)($notification['message'] ?? '')
                                    ) ?>

                                </p>

                                <?php if (
                                    !empty(
                                        $notification['created_at']
                                    )
                                ): ?>

                                    <small class="text-muted">

                                        <?= e(
                                            date(
                                                'd M Y h:i A',
                                                strtotime(
                                                    (string)$notification['created_at']
                                                )
                                            )
                                        ) ?>

                                    </small>

                                <?php endif; ?>

                            </div>

                        <?php endforeach; ?>

                    <?php endif; ?>

                </div>

            </div>


            <!-- Quick Actions -->

            <div class="card shadow-sm">

                <div class="card-header">

                    <h5 class="mb-0">
                        Quick Actions
                    </h5>

                </div>

                <div class="card-body">

                    <div class="d-grid gap-2">

                        <a
                            href="edit-profile.php"
                            class="btn btn-maroon">

                            Edit Profile

                        </a>


                        <a
                            href="change-password.php"
                            class="btn btn-outline-secondary">

                            Change Password

                        </a>


                        <a
                            href="my-schedule.php"
                            class="btn btn-outline-secondary">

                            My Schedule

                        </a>


                        <a
                            href="apply-leave.php"
                            class="btn btn-outline-secondary">

                            Apply For Leave

                        </a>


                        <a
                            href="my-evaluations.php"
                            class="btn btn-outline-secondary">

                            My Evaluations

                        </a>


                        <a
                            href="attendance-history.php"
                            class="btn btn-outline-secondary">

                            My Attendance

                        </a>


                        <a
                            href="my-rating.php"
                            class="btn btn-outline-secondary">

                            My Ratings

                        </a>


                        <a
                            href="notifications.php"
                            class="btn btn-outline-secondary">

                            Notifications

                        </a>

                    </div>

                </div>

            </div>

        </div>

    </div>

</div>

<?php include '../includes/footer.php'; ?>