<?php

declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$customerName = trim(
    (string)($_SESSION['customer_username'] ?? 'Customer')
);
?>

<nav class="navbar navbar-expand-lg navbar-dark bg-maroon">

    <div class="container">

        <a
            class="navbar-brand d-flex align-items-center"
            href="dashboard.php">
            <img
                src="../assets/images/logo.png"
                alt="NIVARRA"
                height="40"
                class="me-2">
            <span>NIVARRA Customer</span>

        </a>

        <div class="ms-auto d-flex gap-2">

            <a
                class="btn btn-light"
                href="profile.php">
                Profile
            </a>

            <a
                class="btn btn-warning"
                href="../auth/logout.php">
                Logout
            </a>

        </div>

    </div>
</nav>