<?php

declare(strict_types=1);

require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';
require_once '../includes/employee-only.php';

$pageTitle = 'Employee Dashboard';

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

$stmt->execute([
    $employeeId
]);

$employee = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$employee) {
    header('Location: ../login.php');
    exit;
}


/*
|--------------------------------------------------------------------------
| Leave Summary
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT
        COUNT(*) AS total_requests,
        COALESCE(
            SUM(
                CASE
                    WHEN status = 'approved'
                    THEN DATEDIFF(end_date, start_date) + 1
                    ELSE 0
                END
            ),
            0
        ) AS approved_leave_days
    FROM leave_requests
    WHERE employee_id = ?
");

$stmt->execute([
    $employeeId
]);

$leaveStats = $stmt->fetch(PDO::FETCH_ASSOC) ?: [
    'total_requests' => 0,
    'approved_leave_days' => 0
];

$leaveAllowance = (int)(
    $employee['leave_allowance'] ?? 0
);

$approvedLeaveDays = (int)(
    $leaveStats['approved_leave_days'] ?? 0
);

$remainingLeave = max(
    0,
    $leaveAllowance - $approvedLeaveDays
);


/*
|--------------------------------------------------------------------------
| Pending Leave Requests
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT
        id,
        leave_type,
        start_date,
        end_date,
        status,
        created_at
    FROM leave_requests
    WHERE employee_id = ?
    ORDER BY created_at DESC
    LIMIT 5
");

$stmt->execute([
    $employeeId
]);

$leaveRequests = $stmt->fetchAll(PDO::FETCH_ASSOC);


/*
|--------------------------------------------------------------------------
| Today's Shift
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT *
    FROM shifts
    WHERE employee_id = ?
      AND shift_date = CURDATE()
    ORDER BY start_time ASC
");

$stmt->execute([
    $employeeId
]);

$todayShifts = $stmt->fetchAll(PDO::FETCH_ASSOC);


/*
|--------------------------------------------------------------------------
| Upcoming Shifts
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT *
    FROM shifts
    WHERE employee_id = ?
      AND shift_date >= CURDATE()
    ORDER BY shift_date ASC, start_time ASC
    LIMIT 7
");

$stmt->execute([
    $employeeId
]);

$upcomingShifts = $stmt->fetchAll(PDO::FETCH_ASSOC);


/*
|--------------------------------------------------------------------------
| Attendance Summary
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT
        COUNT(*) AS total_days,
        COALESCE(SUM(total_hours), 0) AS total_hours
    FROM attendance_logs
    WHERE employee_id = ?
");

$stmt->execute([
    $employeeId
]);

$attendanceStats = $stmt->fetch(PDO::FETCH_ASSOC) ?: [
    'total_days' => 0,
    'total_hours' => 0
];

$totalAttendanceDays = (int)(
    $attendanceStats['total_days'] ?? 0
);

$totalAttendanceHours = (float)(
    $attendanceStats['total_hours'] ?? 0
);


/*
|--------------------------------------------------------------------------
| Today's Attendance
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT *
    FROM attendance_logs
    WHERE employee_id = ?
      AND attendance_date = CURDATE()
    ORDER BY id DESC
    LIMIT 1
");

$stmt->execute([
    $employeeId
]);

$todayAttendance = $stmt->fetch(PDO::FETCH_ASSOC);


/*
|--------------------------------------------------------------------------
| Evaluation Summary
|--------------------------------------------------------------------------
|
| evaluations does not contain a single "score" column.
|
| Overall score:
|
| (punctuality + teamwork + customer service + productivity) / 4
|
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT
        COUNT(*) AS total_evaluations,
        ROUND(
            AVG(
                (
                    COALESCE(punctuality_score, 0) +
                    COALESCE(teamwork_score, 0) +
                    COALESCE(customer_service_score, 0) +
                    COALESCE(productivity_score, 0)
                ) / 4
            ),
            2
        ) AS average_score
    FROM evaluations
    WHERE employee_id = ?
");

$stmt->execute([
    $employeeId
]);

$evaluationStats = $stmt->fetch(PDO::FETCH_ASSOC) ?: [
    'total_evaluations' => 0,
    'average_score' => 0
];

$totalEvaluations = (int)(
    $evaluationStats['total_evaluations'] ?? 0
);

$averageEvaluationScore = (float)(
    $evaluationStats['average_score'] ?? 0
);


/*
|--------------------------------------------------------------------------
| Rating Summary
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT
        COUNT(*) AS total_reviews,
        ROUND(AVG(rating), 2) AS average_rating
    FROM employee_ratings
    WHERE employee_id = ?
");

$stmt->execute([
    $employeeId
]);

$ratingStats = $stmt->fetch(PDO::FETCH_ASSOC) ?: [
    'total_reviews' => 0,
    'average_rating' => 0
];

$totalRatings = (int)(
    $ratingStats['total_reviews'] ?? 0
);

$averageRating = (float)(
    $ratingStats['average_rating'] ?? 0
);


/*
|--------------------------------------------------------------------------
| Notifications
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT
        *
    FROM employee_notifications
    WHERE employee_id = ?
    ORDER BY created_at DESC
    LIMIT 5
");

$stmt->execute([
    $employeeId
]);

$notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);


/*
|--------------------------------------------------------------------------
| Unread Notifications
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT COUNT(*)
    FROM employee_notifications
    WHERE employee_id = ?
      AND status = 'unread'
");

$stmt->execute([
    $employeeId
]);

$unreadNotifications = (int)$stmt->fetchColumn();


/*
|--------------------------------------------------------------------------
| Shift Coverage Requests
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT
        scr.*,
        s.shift_date,
        s.start_time,
        s.end_time,
        s.position
    FROM shift_coverage_requests scr
    INNER JOIN shifts s
        ON s.id = scr.shift_id
    WHERE scr.requesting_employee_id = ?
       OR scr.covering_employee_id = ?
    ORDER BY scr.created_at DESC
    LIMIT 5
");

$stmt->execute([
    $employeeId,
    $employeeId
]);

$coverageRequests = $stmt->fetchAll(PDO::FETCH_ASSOC);

include '../includes/header.php';
include '../includes/employee-navbar.php';

?>

<div class="container py-4">

    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">

        <div>

            <h2 class="mb-1">
                Employee Dashboard
            </h2>

            <p class="text-muted mb-0">

                Welcome,
                <?= e((string)($employee['full_name'] ?? '')) ?>

            </p>

        </div>


        <?php if (
            strtolower(
                trim(
                    (string)($employee['role'] ?? '')
                )
            ) === 'manager'
        ): ?>

            <a
                href="../manager/dashboard.php"
                class="btn btn-maroon">

                Manager Dashboard

            </a>

        <?php endif; ?>

    </div>


    <!-- Summary Cards -->
    <div class="row g-3 mb-4">


        <!-- Today's Shift -->

        <div class="col-md-6 col-lg-3">

            <div class="card shadow-sm h-100">

                <div class="card-body">

                    <h6 class="text-muted">
                        Today's Shift
                    </h6>

                    <?php if ($todayShifts): ?>

                        <?php foreach ($todayShifts as $shift): ?>

                            <?php
                            $shiftType =
                                employeeDashboardShiftType(
                                    (string)$shift['start_time'],
                                    (string)$shift['end_time']
                                );
                            ?>

                            <div class="mb-2">

                                <strong>
                                    <?= e($shiftType) ?> Shift
                                </strong>

                                <div>
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
                                </div>

                            </div>

                        <?php endforeach; ?>

                    <?php else: ?>

                        <h4 class="mb-0">
                            No Shift
                        </h4>

                        <small class="text-muted">
                            No shift scheduled today.
                        </small>

                    <?php endif; ?>

                </div>

            </div>

        </div>


        <!-- Leave Balance -->

        <div class="col-md-6 col-lg-3">

            <div class="card shadow-sm h-100">

                <div class="card-body">

                    <h6 class="text-muted">
                        Leave Balance
                    </h6>

                    <h3 class="mb-1">
                        <?= e((string)$remainingLeave) ?>
                    </h3>

                    <small class="text-muted">

                        <?= e((string)$approvedLeaveDays) ?>
                        used of
                        <?= e((string)$leaveAllowance) ?>

                    </small>

                </div>

            </div>

        </div>


        <!-- Attendance -->

        <div class="col-md-6 col-lg-3">

            <div class="card shadow-sm h-100">

                <div class="card-body">

                    <h6 class="text-muted">
                        Attendance
                    </h6>

                    <h3 class="mb-1">
                        <?= e((string)$totalAttendanceDays) ?>
                    </h3>

                    <small class="text-muted">
                        Days recorded
                    </small>

                    <div class="mt-1">

                        <?= e(
                            number_format(
                                $totalAttendanceHours,
                                2
                            )
                        ) ?>

                        hours

                    </div>

                </div>

            </div>

        </div>


        <!-- Notifications -->

        <div class="col-md-6 col-lg-3">

            <div class="card shadow-sm h-100">

                <div class="card-body">

                    <h6 class="text-muted">
                        Notifications
                    </h6>

                    <h3 class="mb-1">
                        <?= e(
                            (string)$unreadNotifications
                        ) ?>
                    </h3>

                    <small class="text-muted">
                        Unread notifications
                    </small>

                </div>

            </div>

        </div>

    </div>


    <!-- Quick Actions -->

    <div class="card shadow-sm mb-4">

        <div class="card-header">

            <h5 class="mb-0">
                Quick Actions
            </h5>

        </div>

        <div class="card-body">

            <div class="d-flex flex-wrap gap-2">

                <a
                    href="profile.php"
                    class="btn btn-maroon">

                    My Profile

                </a>

                <a
                    href="my-schedule.php"
                    class="btn btn-outline-primary">

                    My Schedule

                </a>

                <a
                    href="apply-leave.php"
                    class="btn btn-outline-warning">

                    Apply Leave

                </a>

                <a 
                    href="cover-shift-request.php"
                    class=" btn btn-outline-warning">
                
                    Apply covrerage
                    
                </a>

                <a
                    href="check-in.php"
                    class="btn btn-outline-success">

                    Check In

                </a>

                <a
                    href="check-out.php"
                    class="btn btn-outline-danger">

                    Check Out

                </a>

                <a
                    href="my-evaluations.php"
                    class="btn btn-outline-secondary">

                    My Evaluations

                </a>

                <a
                    href="my-rating.php"
                    class="btn btn-outline-secondary">

                    My Ratings

                </a>

                <a
                    href="notifications.php"
                    class="btn btn-outline-info">

                    Notifications

                    <?php if ($unreadNotifications > 0): ?>

                        <span class="badge bg-danger ms-1">

                            <?= e(
                                (string)$unreadNotifications
                            ) ?>

                        </span>

                    <?php endif; ?>

                </a>

            </div>

        </div>

    </div>


    <div class="row">


        <!-- Today's Attendance -->

        <div class="col-lg-6">

            <div class="card shadow-sm mb-4">

                <div class="card-header">

                    <h5 class="mb-0">
                        Today's Attendance
                    </h5>

                </div>

                <div class="card-body">

                    <?php if ($todayAttendance): ?>

                        <div class="row g-3">

                            <div class="col-md-4">

                                <strong>
                                    Check In
                                </strong>

                                <div>

                                    <?= e(
                                        (string)(
                                            $todayAttendance[
                                                'check_in_time'
                                            ] ?? '-'
                                        )
                                    ) ?>

                                </div>

                            </div>

                            <div class="col-md-4">

                                <strong>
                                    Check Out
                                </strong>

                                <div>

                                    <?= e(
                                        (string)(
                                            $todayAttendance[
                                                'check_out_time'
                                            ] ?? '-'
                                        )
                                    ) ?>

                                </div>

                            </div>

                            <div class="col-md-4">

                                <strong>
                                    Total Hours
                                </strong>

                                <div>

                                    <?= e(
                                        number_format(
                                            (float)(
                                                $todayAttendance[
                                                    'total_hours'
                                                ] ?? 0
                                            ),
                                            2
                                        )
                                    ) ?>

                                </div>

                            </div>

                        </div>

                    <?php else: ?>

                        <div class="alert alert-info mb-0">

                            No attendance record for today.

                        </div>

                    <?php endif; ?>

                </div>

            </div>

        </div>


        <!-- Performance -->

        <div class="col-lg-6">

            <div class="card shadow-sm mb-4">

                <div class="card-header">

                    <h5 class="mb-0">
                        Performance Summary
                    </h5>

                </div>

                <div class="card-body">

                    <div class="row text-center">

                        <div class="col-6">

                            <h3>
                                <?= e(
                                    number_format(
                                        $averageEvaluationScore,
                                        2
                                    )
                                ) ?>
                            </h3>

                            <small class="text-muted">
                                Average Evaluation
                            </small>

                        </div>

                        <div class="col-6">

                            <h3>
                                <?= e(
                                    number_format(
                                        $averageRating,
                                        2
                                    )
                                ) ?>
                            </h3>

                            <small class="text-muted">
                                Average Rating
                            </small>

                        </div>

                    </div>

                    <hr>

                    <div class="row text-center">

                        <div class="col-6">

                            <strong>
                                <?= e(
                                    (string)$totalEvaluations
                                ) ?>
                            </strong>

                            <div class="text-muted">
                                Evaluations
                            </div>

                        </div>

                        <div class="col-6">

                            <strong>
                                <?= e(
                                    (string)$totalRatings
                                ) ?>
                            </strong>

                            <div class="text-muted">
                                Ratings
                            </div>

                        </div>

                    </div>

                </div>

            </div>

        </div>

    </div>


    <!-- Upcoming Shifts -->

    <div class="card shadow-sm mb-4">

        <div class="card-header">

            <div class="d-flex justify-content-between align-items-center">

                <h5 class="mb-0">
                    Upcoming Shifts
                </h5>

                <a
                    href="my-schedule.php"
                    class="btn btn-sm btn-outline-primary">

                    View Schedule

                </a>

            </div>

        </div>

        <div class="card-body">

            <?php if ($upcomingShifts): ?>

                <div class="table-responsive">

                    <table class="table table-striped align-middle mb-0">

                        <thead>

                            <tr>

                                <th>
                                    Date
                                </th>

                                <th>
                                    Shift
                                </th>

                                <th>
                                    Start
                                </th>

                                <th>
                                    End
                                </th>

                                <th>
                                    Position
                                </th>

                            </tr>

                        </thead>

                        <tbody>

                            <?php foreach (
                                $upcomingShifts
                                as $shift
                            ): ?>

                                <?php
                                $shiftType =
                                    employeeDashboardShiftType(
                                        (string)$shift['start_time'],
                                        (string)$shift['end_time']
                                    );
                                ?>

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

                                        <span
                                            class="badge <?= $shiftType === 'Night'
                                                ? 'bg-dark'
                                                : 'bg-primary' ?>">

                                            <?= e($shiftType) ?>

                                        </span>

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
                                    </td>

                                    <td>
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
                                            (string)(
                                                $shift['position'] ?? '-'
                                            )
                                        ) ?>
                                    </td>

                                </tr>

                            <?php endforeach; ?>

                        </tbody>

                    </table>

                </div>

            <?php else: ?>

                <div class="alert alert-info mb-0">

                    No upcoming shifts scheduled.

                </div>

            <?php endif; ?>

        </div>

    </div>


    <div class="row">


        <!-- Leave Requests -->

        <div class="col-lg-6">

            <div class="card shadow-sm mb-4">

                <div class="card-header">

                    <div class="d-flex justify-content-between align-items-center">

                        <h5 class="mb-0">
                            Recent Leave Requests
                        </h5>

                        <a
                            href="apply-leave.php"
                            class="btn btn-sm btn-outline-warning">

                            Leave Management

                        </a>

                    </div>

                </div>

                <div class="card-body">

                    <?php if ($leaveRequests): ?>

                        <div class="table-responsive">

                            <table class="table table-sm align-middle mb-0">

                                <thead>

                                    <tr>

                                        <th>
                                            Type
                                        </th>

                                        <th>
                                            Dates
                                        </th>

                                        <th>
                                            Status
                                        </th>

                                    </tr>

                                </thead>

                                <tbody>

                                    <?php foreach (
                                        $leaveRequests
                                        as $leave
                                    ): ?>

                                        <?php

                                        $leaveStatus =
                                            strtolower(
                                                trim(
                                                    (string)(
                                                        $leave['status']
                                                        ?? ''
                                                    )
                                                )
                                            );

                                        $leaveBadge =
                                            match ($leaveStatus) {

                                                'approved'
                                                    => 'bg-success',

                                                'rejected'
                                                    => 'bg-danger',

                                                'cancelled'
                                                    => 'bg-secondary',

                                                default
                                                    => 'bg-warning text-dark'
                                            };

                                        ?>

                                        <tr>

                                            <td>
                                                <?= e(
                                                    ucfirst(
                                                        (string)(
                                                            $leave[
                                                                'leave_type'
                                                            ] ?? ''
                                                        )
                                                    )
                                                ) ?>
                                            </td>

                                            <td>

                                                <?= e(
                                                    (string)(
                                                        $leave[
                                                            'start_date'
                                                        ] ?? ''
                                                    )
                                                ) ?>

                                                -

                                                <?= e(
                                                    (string)(
                                                        $leave[
                                                            'end_date'
                                                        ] ?? ''
                                                    )
                                                ) ?>

                                            </td>

                                            <td>

                                                <span
                                                    class="badge <?= $leaveBadge ?>">

                                                    <?= e(
                                                        ucfirst(
                                                            $leaveStatus
                                                        )
                                                    ) ?>

                                                </span>

                                            </td>

                                        </tr>

                                    <?php endforeach; ?>

                                </tbody>

                            </table>

                        </div>

                    <?php else: ?>

                        <div class="alert alert-info mb-0">

                            No leave requests found.

                        </div>

                    <?php endif; ?>

                </div>

            </div>

        </div>


        <!-- Notifications -->

        <div class="col-lg-6">

            <div class="card shadow-sm mb-4">

                <div class="card-header">

                    <div class="d-flex justify-content-between align-items-center">

                        <h5 class="mb-0">
                            Recent Notifications
                        </h5>

                        <a
                            href="notifications.php"
                            class="btn btn-sm btn-outline-info">

                            View All

                        </a>

                    </div>

                </div>

                <div class="card-body">

                    <?php if ($notifications): ?>

                        <?php foreach (
                            $notifications
                            as $notification
                        ): ?>

                            <div class="border-bottom pb-2 mb-2">

                                <div class="d-flex justify-content-between">

                                    <strong>

                                        <?= e(
                                            (string)(
                                                $notification['title']
                                                ?? ''
                                            )
                                        ) ?>

                                    </strong>

                                    <?php if (
                                        ($notification['status'] ?? '')
                                        === 'unread'
                                    ): ?>

                                        <span class="badge bg-danger">
                                            New
                                        </span>

                                    <?php endif; ?>

                                </div>

                                <div class="text-muted small">

                                    <?= e(
                                        (string)(
                                            $notification['message']
                                            ?? ''
                                        )
                                    ) ?>

                                </div>

                            </div>

                        <?php endforeach; ?>

                    <?php else: ?>

                        <div class="alert alert-info mb-0">

                            No notifications.

                        </div>

                    <?php endif; ?>

                </div>

            </div>

        </div>

    </div>


    <!-- Shift Coverage -->

    <div class="card shadow-sm mb-4">

        <div class="card-header">

            <div class="d-flex justify-content-between align-items-center">

                <h5 class="mb-0">
                    Shift Coverage Requests
                </h5>

                <a
                    href="coverage-requests.php"
                    class="btn btn-sm btn-outline-primary">

                    View

                </a>

            </div>

        </div>

        <div class="card-body">

            <?php if ($coverageRequests): ?>

                <div class="table-responsive">

                    <table class="table table-striped align-middle mb-0">

                        <thead>

                            <tr>

                                <th>
                                    Date
                                </th>

                                <th>
                                    Time
                                </th>

                                <th>
                                    Position
                                </th>

                                <th>
                                    Status
                                </th>

                            </tr>

                        </thead>

                        <tbody>

                            <?php foreach (
                                $coverageRequests
                                as $request
                            ): ?>

                                <?php

                                $coverageStatus =
                                    strtolower(
                                        trim(
                                            (string)(
                                                $request['status']
                                                ?? ''
                                            )
                                        )
                                    );

                                $coverageBadge =
                                    match ($coverageStatus) {

                                        'accepted'
                                            => 'bg-success',

                                        'declined'
                                            => 'bg-danger',

                                        'completed'
                                            => 'bg-primary',

                                        default
                                            => 'bg-warning text-dark'
                                    };

                                ?>

                                <tr>

                                    <td>
                                        <?= e(
                                            (string)(
                                                $request['shift_date']
                                                ?? ''
                                            )
                                        ) ?>
                                    </td>

                                    <td>

                                        <?= e(
                                            date(
                                                'h:i A',
                                                strtotime(
                                                    (string)(
                                                        $request[
                                                            'start_time'
                                                        ]
                                                    )
                                                )
                                            )
                                        ) ?>

                                        -

                                        <?= e(
                                            date(
                                                'h:i A',
                                                strtotime(
                                                    (string)(
                                                        $request[
                                                            'end_time'
                                                        ]
                                                    )
                                                )
                                            )
                                        ) ?>

                                    </td>

                                    <td>
                                        <?= e(
                                            (string)(
                                                $request['position']
                                                ?? '-'
                                            )
                                        ) ?>
                                    </td>

                                    <td>

                                        <span
                                            class="badge <?= $coverageBadge ?>">

                                            <?= e(
                                                ucfirst(
                                                    $coverageStatus
                                                )
                                            ) ?>

                                        </span>

                                    </td>

                                </tr>

                            <?php endforeach; ?>

                        </tbody>

                    </table>

                </div>

            <?php else: ?>

                <div class="alert alert-info mb-0">

                    No shift coverage requests found.

                </div>

            <?php endif; ?>

        </div>

    </div>

</div>

<?php require_once '../includes/footer.php'; ?>