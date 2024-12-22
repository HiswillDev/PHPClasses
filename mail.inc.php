<?php 
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;

function email($to,$subject,$body,$replyto=null){
$mailer = new PHPMailer(true);
$mailer->isSMTP(true);
$mailer->Host = "mail1.serv00.com";
$mailer->SMTPAuth = true;
$mailer->Username = "mail@hiws.eu.org";
$mailer->Password = "Hiswill256@";
$mailer->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS; //ENCRYPTION_STARTLS
$mailer->Port = 465; //465 - SMTPS, 587 - TLS
$mailer->isHTML(true);
$mailer->addAddress($to);
$mailer->Subject = $subject;
$mailer->Body= $body;
$mailer->send();
}
