<?php
declare(strict_types=1);


/**
 * Password reset email template.
 *
 * @param string $resetUrl Password reset URL.
 * @param string $recipientName Recipient display name.
 * @return string HTML email.
 */
function passwordResetTemplate(
    string $resetUrl,
    string $recipientName = 'Customer'
): string {
    $safeName = htmlspecialchars(
        $recipientName,
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );

    $safeUrl = htmlspecialchars(
        $resetUrl,
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );

    $expiry = defined('PASSWORD_RESET_EXPIRY_MINUTES')
        ? PASSWORD_RESET_EXPIRY_MINUTES
        : 60;

    return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>NIVARRA Password Reset</title>
</head>

<body style="margin:0; padding:0; background-color:#F8F5F0; font-family:Arial, Helvetica, sans-serif; color:#2E1F1A;">

<table width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#F8F5F0; padding:40px 15px;">
    <tr>
        <td align="center">

            <table width="600" cellpadding="0" cellspacing="0" border="0"
                   style="max-width:600px; width:100%; background-color:#ffffff; border-radius:12px; overflow:hidden;">

                <tr>
                    <td style="background-color:#8B1E1E; padding:25px; text-align:center;">
                        <h1 style="margin:0; color:#ffffff; font-size:28px;">
                            NIVARRA
                        </h1>

                        <p style="margin:8px 0 0; color:#ffffff; font-size:14px;">
                            Restaurant Management System
                        </p>
                    </td>
                </tr>

                <tr>
                    <td style="padding:35px 30px;">

                        <h2 style="margin-top:0; color:#8B1E1E;">
                            Password Reset Request
                        </h2>

                        <p>
                            Hello {$safeName},
                        </p>

                        <p>
                            We received a request to reset the password
                            associated with your NIVARRA account.
                        </p>

                        <p>
                            Click the button below to create a new password:
                        </p>

                        <p style="text-align:center; margin:30px 0;">
                            <a href="{$safeUrl}"
                               style="
                                   display:inline-block;
                                   background-color:#8B1E1E;
                                   color:#ffffff;
                                   text-decoration:none;
                                   padding:13px 25px;
                                   border-radius:6px;
                                   font-weight:bold;
                               ">
                                Reset Password
                            </a>
                        </p>

                        <p>
                            This password reset link will expire in
                            <strong>{$expiry} minutes</strong>.
                        </p>

                        <p>
                            If you did not request a password reset,
                            you can safely ignore this email.
                        </p>

                        <hr style="border:0; border-top:1px solid #eeeeee; margin:30px 0;">

                        <p style="font-size:13px; color:#777777;">
                            For security reasons, do not share this
                            password reset link with anyone.
                        </p>

                    </td>
                </tr>

                <tr>
                    <td style="background-color:#2E1F1A; padding:18px; text-align:center;">
                        <p style="margin:0; color:#ffffff; font-size:12px;">
                            &copy; NIVARRA Restaurant
                        </p>
                    </td>
                </tr>

            </table>

        </td>
    </tr>
</table>

</body>
</html>
HTML;
}