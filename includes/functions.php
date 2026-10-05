<?php

declare(strict_types=1);


/*
|--------------------------------------------------------------------------
| HTML Escaping
|--------------------------------------------------------------------------
*/

function e(mixed $value): string
{
    return htmlspecialchars(
        (string)($value ?? ''),
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );
}


/*
|--------------------------------------------------------------------------
| Redirect
|--------------------------------------------------------------------------
*/

function redirect(string $path): never
{
    header("Location: {$path}");
    exit;
}


/*
|--------------------------------------------------------------------------
| Request Helpers
|--------------------------------------------------------------------------
*/

function post(string $key): string
{
    return trim((string)($_POST[$key] ?? ''));
}

function get(string $key): string
{
    return trim((string)($_GET[$key] ?? ''));
}


/*
|--------------------------------------------------------------------------
| Authentication Helpers
|--------------------------------------------------------------------------
*/

function isLoggedIn(): bool
{
    return isset($_SESSION['employee_id'])
        || isset($_SESSION['customer_id']);
}

function currentRole(): ?string
{
    $role = $_SESSION['role'] ?? null;

    if ($role === null) {
        return null;
    }

    return (string)$role;
}

function currentUserId(): ?int
{
    if (isset($_SESSION['employee_id'])) {
        return (int)$_SESSION['employee_id'];
    }

    return null;
}

function currentCustomerId(): ?int
{
    if (isset($_SESSION['customer_id'])) {
        return (int)$_SESSION['customer_id'];
    }

    return null;
}


/*
|--------------------------------------------------------------------------
| Flash Messages
|--------------------------------------------------------------------------
*/

function flash(string $key, string $message): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }

    if (!isset($_SESSION['flash']) || !is_array($_SESSION['flash'])) {
        $_SESSION['flash'] = [];
    }

    $_SESSION['flash'][$key] = $message;
}

function flashMessage(string $key): ?string
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }

    if (
        !isset($_SESSION['flash']) ||
        !is_array($_SESSION['flash']) ||
        !array_key_exists($key, $_SESSION['flash'])
    ) {
        return null;
    }

    $message = (string)$_SESSION['flash'][$key];

    unset($_SESSION['flash'][$key]);

    return $message;
}

/*
|--------------------------------------------------------------------------
| Cart
|--------------------------------------------------------------------------
*/

function cart(): array
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }

    if (
        !isset($_SESSION['cart']) ||
        !is_array($_SESSION['cart'])
    ) {
        $_SESSION['cart'] = [];
    }

    return $_SESSION['cart'];
}

function cartTotal(PDO $pdo): float
{
    $total = 0.0;

    if (
        !isset($_SESSION['cart']) ||
        !is_array($_SESSION['cart']) ||
        empty($_SESSION['cart'])
    ) {
        return 0.0;
    }

    foreach ($_SESSION['cart'] as $item) {

        $price = (float)($item['price'] ?? 0);
        $quantity = (int)($item['qty'] ?? 0);

        if ($quantity < 1) {
            continue;
        }

        $total += $price * $quantity;
    }

    return $total;
}


/*
|--------------------------------------------------------------------------
| Audit Log
|--------------------------------------------------------------------------
*/

function auditLog(
    ?int $employeeId,
    string $action,
    string $description = ''
): void {

    global $pdo;

    try {

        $stmt = $pdo->prepare("
            INSERT INTO audit_logs (
                employee_id,
                action,
                description,
                ip_address,
                user_agent
            )
            VALUES (
                ?, ?, ?, ?, ?
            )
        ");

        $stmt->execute([

            $employeeId,

            $action,

            $description,

            $_SERVER['REMOTE_ADDR'] ?? null,

            substr(
                (string)($_SERVER['HTTP_USER_AGENT'] ?? ''),
                0,
                255
            )
        ]);

    } catch (Throwable $e) {

        error_log(
            'Audit Log Error: ' .
            $e->getMessage()
        );
    }
}


/*
|--------------------------------------------------------------------------
| Order Status
|--------------------------------------------------------------------------
*/

function orderStatusClass(string $status): string
{
    return match (strtolower(trim($status))) {

        'pending' =>
            'bg-warning text-dark',

        'preparing' =>
            'bg-info text-dark',

        'ready' =>
            'bg-primary',

        'served' =>
            'bg-success',

        default =>
            'bg-secondary'
    };
}

function orderStatusLabel(string $status): string
{
    $status = trim($status);

    if ($status === '') {
        return 'Unknown';
    }

    return ucfirst(
        strtolower($status)
    );
}


/*
|--------------------------------------------------------------------------
| Notification Type Styling
|--------------------------------------------------------------------------
*/

function notificationTypeClass(string $type): string
{
    return match (strtolower(trim($type))) {

        'order' =>
            'bg-primary',

        'reservation' =>
            'bg-warning text-dark',

        'offer' =>
            'bg-success',

        'review' =>
            'bg-info text-dark',

        'loyalty' =>
            'bg-dark',

        'system' =>
            'bg-secondary',

        default =>
            'bg-secondary'
    };
}

function notificationTypeLabel(string $type): string
{
    $type = trim($type);

    if ($type === '') {
        return 'Notification';
    }

    return ucfirst(
        strtolower($type)
    );
}


/*
|--------------------------------------------------------------------------
| Image Path
|--------------------------------------------------------------------------
|
| Supports database values such as:
|
| menu_photos/image.jpg
|
| OR:
|
| image.jpg
|--------------------------------------------------------------------------
*/

function cartImagePath(?string $image): ?string
{
    $image = trim((string)$image);

    if ($image === '') {
        return null;
    }

    $image = str_replace(
        '\\',
        '/',
        $image
    );

    if (
        str_contains($image, '/')
    ) {
        return '../' . ltrim(
            $image,
            '/'
        );
    }

    return '../menu_photos/' . $image;
}

/*
|--------------------------------------------------------------------------
| Helper Functions
|--------------------------------------------------------------------------
*/

function redirectToAddresses(): never
{
    header('Location: addresses.php');
    exit;
}

function cleanAddressValue(?string $value): string
{
    return trim((string)$value);
}

/*
|--------------------------------------------------------------------------
| Format Discount
|--------------------------------------------------------------------------
*/

function formatOfferDiscount(
    string $discountType,
    float $discountValue
): string {

    return match (strtolower(trim($discountType))) {

        'percentage' =>
            rtrim(rtrim(number_format($discountValue, 2), '0'), '.')
            . '% OFF',

        'fixed' =>
            '₹' . number_format($discountValue, 2) . ' OFF',

        default =>
            number_format($discountValue, 2)
    };
}

/*
|--------------------------------------------------------------------------
| Format Offer Image
|--------------------------------------------------------------------------
*/

function offerImagePath(?string $image): ?string
{
    $image = trim((string)$image);

    if ($image === '') {
        return null;
    }

    /*
     * If the database already contains a directory/path,
     * use it directly.
     */
    if (
        str_contains($image, '/') ||
        str_contains($image, '\\')
    ) {
        return '../' . ltrim(
            str_replace('\\', '/', $image),
            '/'
        );
    }

    /*
     * Existing records may contain only the filename.
     * Offers are expected to use the offers directory.
     */
    return '../offers/' . $image;
}

/*
|--------------------------------------------------------------------------
| Status helper
|--------------------------------------------------------------------------
*/

function reviewStatusClass(string $status): string
{
    return match (strtolower(trim($status))) {

        'pending' =>
            'bg-warning text-dark',

        'approved' =>
            'bg-success',

        'rejected' =>
            'bg-danger',

        default =>
            'bg-secondary'
    };
}

function reviewStatusLabel(string $status): string
{
    $status = trim($status);

    if ($status === '') {
        return 'Unknown';
    }

    return ucfirst(
        strtolower($status)
    );
}


/*
|--------------------------------------------------------------------------
| Star display
|--------------------------------------------------------------------------
*/

function reviewStars(int $rating): string
{
    $rating = max(
        0,
        min(5, $rating)
    );

    return str_repeat('★', $rating)
        . str_repeat('☆', 5 - $rating);
}

/*
|--------------------------------------------------------------------------
| Helpers
|--------------------------------------------------------------------------
*/

function profileShiftType(
    string $startTime,
    string $endTime
): string {

    $start =
        strtotime($startTime);

    $end =
        strtotime($endTime);

    if ($start === false || $end === false) {
        return 'Shift';
    }

    /*
    |--------------------------------------------------------------------------
    | Night Shift
    |--------------------------------------------------------------------------
    */

    if (
        $start >= strtotime('18:00')
        || $start < strtotime('06:00')
    ) {

        return 'Night';
    }

    /*
    |--------------------------------------------------------------------------
    | Overnight Shift
    |--------------------------------------------------------------------------
    */

    if (
        $end < $start
        && $start >= strtotime('12:00')
    ) {

        return 'Night';
    }

    return 'Day';
}

function profileBadgeClass(
    string $status
): string {

    return match (
        strtolower(trim($status))
    ) {
        'approved',
        'accepted',
        'completed',
        'read' => 'bg-success',

        'pending',
        'unread' => 'bg-warning text-dark',

        'declined',
        'rejected' => 'bg-danger',

        'archived' => 'bg-secondary',

        default => 'bg-secondary'
    };
}

/*
|--------------------------------------------------------------------------
| Helper: Shift Type
|--------------------------------------------------------------------------
|
| This determines whether the shift is displayed as Day or Night.
| The actual shift remains stored in the same shifts table.
|
|--------------------------------------------------------------------------
*/

function employeeDashboardShiftType(
    string $startTime,
    string $endTime
): string {

    $startHour = (int)date(
        'H',
        strtotime($startTime)
    );

    $endHour = (int)date(
        'H',
        strtotime($endTime)
    );

    /*
    | Night shift if it starts at or after 18:00,
    | or starts before 06:00.
    |
    | This is only a display classification.
    */

    if (
        $startHour >= 18
        || $startHour < 6
    ) {
        return 'Night';
    }

    /*
    | A shift ending after midnight can also be
    | represented by an end time earlier than start.
    */

    if (
        $endHour < $startHour
        && $startHour >= 12
    ) {
        return 'Night';
    }

    return 'Day';
}

/*
|--------------------------------------------------------------------------
| Generate Employee Code
|--------------------------------------------------------------------------
|
| Employee role:
| EMP001
| EMP002
| EMP003
|
| Manager role:
| MGR001
| MGR002
| MGR003
|
| The number is determined from existing employee_code values.
|--------------------------------------------------------------------------
*/

function generateEmployeeCode(
    PDO $pdo,
    string $role
): string {

    if ($role === 'manager') {
        $prefix = 'MGR';
    } else {
        $prefix = 'EMP';
    }

    $stmt = $pdo->prepare("
        SELECT employee_code
        FROM employees
        WHERE employee_code LIKE ?
        ORDER BY id DESC
    ");

    $stmt->execute([
        $prefix . '%'
    ]);

    $highestNumber = 0;

    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {

        $code = (string)$row['employee_code'];

        if (
            preg_match(
                '/^' .
                preg_quote($prefix, '/') .
                '(\d+)$/i',
                $code,
                $matches
            )
        ) {

            $number = (int)$matches[1];

            if ($number > $highestNumber) {
                $highestNumber = $number;
            }
        }
    }

    $nextNumber = $highestNumber + 1;

    return $prefix . str_pad(
        (string)$nextNumber,
        3,
        '0',
        STR_PAD_LEFT
    );
}