<?php
declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NIVARRA Mail Configuration
|--------------------------------------------------------------------------
| Keep SMTP credentials out of the password-reset pages.
| For Gmail, use an App Password instead of your normal Gmail password.
*/

define('MAIL_HOST', 'smtp.gmail.com');
define('MAIL_PORT', 587);
define('MAIL_USERNAME', 'your-email@gmail.com');
define('MAIL_PASSWORD', 'YOUR_GMAIL_APP_PASSWORD');

define('MAIL_ENCRYPTION', 'tls');

define('MAIL_FROM_ADDRESS', 'nivarrarestaurant@gmail.com');
define('MAIL_FROM_NAME', 'nuby auny vycg rjtw');

define('PASSWORD_RESET_EXPIRY_MINUTES', 60);

/*
|--------------------------------------------------------------------------
| Application URL
|--------------------------------------------------------------------------
*/

define('APP_URL', 'http://localhost/nivarra');