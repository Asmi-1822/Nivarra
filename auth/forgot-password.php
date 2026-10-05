<?php

declare(strict_types=1);

require_once '../includes/config.php';
require_once '../includes/csrf.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>

<meta charset="UTF-8">

<meta name="viewport"
content="width=device-width,initial-scale=1">

<title>Forgot Password</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
rel="stylesheet">

<link rel="stylesheet"
href="../assets/css/nivaraa.css">

</head>

<body>

<div class="container py-5">

<div class="row justify-content-center">

<div class="col-md-5">

<div class="card shadow">

<div class="card-body">

<h3>Forgot Password</h3>

<div class="alert alert-warning">

Password reset functionality will be enabled once customer accounts are activated.

</div>

<form>

<div class="mb-3">

<label>Email Address</label>

<input
type="email"
class="form-control"
disabled>

</div>

<button
class="btn btn-secondary w-100"
disabled>

Send Reset Link

</button>

</form>

</div>

</div>

</div>

</div>

</div>

</body>
</html>