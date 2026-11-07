<?php
declare(strict_types=1);

require_once APP_DIR . '/vendor/PHPMailer/Exception.php';
require_once APP_DIR . '/vendor/PHPMailer/PHPMailer.php';
require_once APP_DIR . '/vendor/PHPMailer/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;

function send_mail(string $to, string $subject, string $html): bool
{
    $c = config('mail', []);
    $mail = new PHPMailer(true);
    try {
        $mail->CharSet = 'UTF-8';
        if (!empty($c['host'])) {
            $mail->isSMTP();
            $mail->Host = $c['host'];
            $mail->Port = (int) ($c['port'] ?? 465);
            $mail->SMTPAuth = !empty($c['user']);
            $mail->Username = $c['user'] ?? '';
            $mail->Password = $c['pass'] ?? '';
            $secure = $c['secure'] ?? 'ssl';
            $mail->SMTPSecure = $secure === 'tls' ? PHPMailer::ENCRYPTION_STARTTLS : ($secure === 'ssl' ? PHPMailer::ENCRYPTION_SMTPS : '');
            $mail->Timeout = 15;
        } else {
            $mail->isMail();
        }
        $mail->setFrom($c['from'] ?? 'no-reply@relog.gg', $c['from_name'] ?? 'RLG Bootcamp');
        $mail->addAddress($to);
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $html;
        $mail->AltBody = trim(html_entity_decode(strip_tags(str_replace(['<br>', '<br/>', '</p>'], "\n", $html))));
        $mail->send();
        log_line('mail.log', "OK  $to  $subject");
        return true;
    } catch (Throwable $ex) {
        log_line('mail.log', "ERR $to  $subject  " . $mail->ErrorInfo . ' ' . $ex->getMessage());
        return false;
    }
}

function mail_template(string $heading, string $bodyHtml, ?string $buttonText = null, ?string $buttonUrl = null): string
{
    $logo = e(abs_url('/assets/img/logo.png'));
    $button = '';
    if ($buttonText && $buttonUrl) {
        $button = '<p style="margin:28px 0 8px"><a href="' . e($buttonUrl) . '" style="display:inline-block;background:#FF6700;color:#ffffff;text-decoration:none;font-weight:700;text-transform:uppercase;padding:12px 28px;border-radius:0 10px 0 10px;font-family:Arial,sans-serif;font-size:15px">' . e($buttonText) . '</a></p>'
            . '<p style="color:#8a8a8a;font-size:12px;word-break:break-all">If the button does not work, open this link:<br>' . e($buttonUrl) . '</p>';
    }
    return '<!doctype html><html><body style="margin:0;background:#0b0b0b;padding:32px 12px;font-family:Arial,Helvetica,sans-serif">'
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0"><tr><td align="center">'
        . '<table role="presentation" width="560" cellpadding="0" cellspacing="0" style="max-width:560px;background:#151515;border-top:3px solid #FF6700;border-radius:0 14px 0 14px">'
        . '<tr><td style="padding:28px 32px 8px"><img src="' . $logo . '" alt="Relog Media" height="34" style="display:block"></td></tr>'
        . '<tr><td style="padding:8px 32px 32px;color:#e6e6e6;font-size:15px;line-height:1.55">'
        . '<h1 style="color:#ffffff;font-size:22px;text-transform:uppercase;margin:12px 0 16px">' . e($heading) . '</h1>'
        . $bodyHtml . $button
        . '</td></tr></table>'
        . '<p style="color:#6e6e6e;font-size:12px;margin-top:18px">RLG Bootcamp · Relog Media</p>'
        . '</td></tr></table></body></html>';
}
