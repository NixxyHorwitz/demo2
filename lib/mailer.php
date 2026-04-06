<?php

require 'PHPMailer/src/Exception.php';
require 'PHPMailer/src/PHPMailer.php';
require 'PHPMailer/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

function smtp_mail(string $to, string $subject, mixed $message, string $from_name): bool
{
    global $config;
    $mail = new PHPMailer(true);
    try {
        //Server settings
        $mail->isSMTP();                                            //Send using SMTP
        $mail->SMTPDebug  = $config['smtp']['debug'];               //Enable verbose debug output
        $mail->SMTPAuth   = true;                                   //Enable SMTP authentication
        $mail->Host       = $config['smtp']['host'];                //Set the SMTP server to send through
        $mail->Username   = $config['smtp']['username'];            //SMTP username
        $mail->Password   = $config['smtp']['password'];            //SMTP password
        $mail->SMTPSecure = $config['smtp']['encrypt'];             //Enable TLS encryption; `PHPMailer::ENCRYPTION_SMTPS` encouraged
        $mail->Port       = $config['smtp']['port'];                //TCP port to connect to, use 465 for `PHPMailer::ENCRYPTION_SMTPS` above

        //Recipients
        $mail->setFrom($config['smtp']['username'], $from_name);
        $mail->addAddress($to);                                     //Name is optional

        //Content
        $mail->isHTML(true);                                        //Set email format to HTML
        $mail->Subject = $subject;
        $mail->Body    = $message;

        $mail->send();
        return true;
    } catch (Exception $e) {
        return false;
    }
}
