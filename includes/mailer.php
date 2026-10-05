<?php
declare(strict_types=1);

use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\PHPMailer;

require_once __DIR__ . '/../vendor/phpmailer/src/Exception.php';
require_once __DIR__ . '/../vendor/phpmailer/src/PHPMailer.php';
require_once __DIR__ . '/../vendor/phpmailer/src/SMTP.php';
require_once __DIR__ . '/mail-config.php';


/**
 * Send an HTML email using NIVARRA SMTP configuration.
 *
 * @param string $to Recipient email address.
 * @param string $subject Email subject.
 * @param string $body HTML email body.
 * @return bool True when the email is sent successfully.
 */
function sendMail(string $to, string $subject, string $body): bool
{
    $mail = new PHPMailer(true);

    try {
        $mail->isSMTP();

        $mail->Host = MAIL_HOST;
        $mail->SMTPAuth = true;
        $mail->Username = MAIL_USERNAME;
        $mail->Password = MAIL_PASSWORD;

        if (MAIL_ENCRYPTION === 'tls') {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        } elseif (MAIL_ENCRYPTION === 'ssl') {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        }

        $mail->Port = MAIL_PORT;

        $mail->setFrom(
            MAIL_FROM_ADDRESS,
            MAIL_FROM_NAME
        );

        $mail->addAddress($to);

        $mail->isHTML(true);
        $mail->CharSet = 'UTF-8';

        $mail->Subject = $subject;
        $mail->Body = $body;

        $mail->AltBody = strip_tags($body);

        $mail->send();

        return true;
    } catch (Exception $e) {
        error_log('NIVARRA mail error: ' . $mail->ErrorInfo);
        return false;
    }
}