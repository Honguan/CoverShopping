<?php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;



require 'PHPMailerN/src/Exception.php';
require 'PHPMailerN/src/PHPMailer.php';
require 'PHPMailerN/src/SMTP.php';
require_once __DIR__ . '/database.php';

// 
function pmail()
{

    $mail = new PHPMailer(true);
    load_env(__DIR__ . '/.env');
    //Server settings
    //$mail->SMTPDebug = SMTP::DEBUG_SERVER;                      // Enable verbose debug output
    $mail->isSMTP();                                            // Send using SMTP
    $mail->Host       = getenv('MAIL_HOST');                    // Set the SMTP server to send through
    $mail->SMTPAuth   = true;                                   // Enable SMTP authentication
    $mail->Username   = getenv('MAIL_USERNAME');                // SMTP username
    $mail->Password   = getenv('MAIL_PASSWORD');                // SMTP password
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;         // Enable TLS encryption
    $mail->Port       = getenv('MAIL_PORT');                    // TCP port to connect to

    return $mail;
}
