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
        $text = preg_replace(['#<(br|/p|/h1|/div|/tr)\b[^>]*>#i', '#<a [^>]*href="([^"]+)"[^>]*>.*?</a>#is'], ["\n", '$1'], $html);
        $text = html_entity_decode(strip_tags((string) $text), ENT_QUOTES, 'UTF-8');
        $mail->AltBody = trim(preg_replace("/[ \t]*\n[ \t\n]*/", "\n\n", (string) $text));
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
    // Logo PNG has its own dark background baked in, so it stays readable when mail apps switch to dark mode.
    $logoFile = APP_ROOT . '/assets/img/email-logo.png';
    $logo = e(abs_url('/assets/img/email-logo.png') . '?v=' . (is_file($logoFile) ? filemtime($logoFile) : '1'));
    $font = "font-family:Arial,Helvetica,sans-serif";
    $button = '';
    if ($buttonText && $buttonUrl) {
        $button = '<table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin:30px 0 10px"><tr>'
            . '<td bgcolor="#FF6700" style="background:#FF6700;border-radius:0 12px 0 12px">'
            . '<a href="' . e($buttonUrl) . '" style="display:inline-block;padding:15px 30px;' . $font . ';font-size:17px;font-weight:bold;line-height:20px;color:#ffffff;text-decoration:none;text-transform:uppercase;letter-spacing:.5px">' . e($buttonText) . '</a>'
            . '</td></tr></table>'
            . '<p style="margin:18px 0 0;' . $font . ';font-size:13px;line-height:20px;color:#666666">' . e(__('If the button does not work, open this link:')) . '<br>'
            . '<a href="' . e($buttonUrl) . '" style="color:#D45600;word-break:break-all">' . e($buttonUrl) . '</a></p>';
    }
    return '<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
        . '<meta name="color-scheme" content="light"><meta name="supported-color-schemes" content="light"></head>'
        . '<body style="margin:0;padding:0;background:#ececec">'
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" bgcolor="#ececec" style="background:#ececec"><tr><td align="center" style="padding:28px 12px">'
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="max-width:560px">'
        // header
        . '<tr><td align="left" bgcolor="#141414" style="background:#141414;padding:18px 28px;border-radius:0 16px 0 0">'
        . '<img src="' . $logo . '" alt="RLG" width="160" height="54" style="display:block;border:0;width:160px;height:auto">'
        . '</td></tr>'
        . '<tr><td height="4" bgcolor="#FF6700" style="background:#FF6700;font-size:0;line-height:0">&nbsp;</td></tr>'
        // body
        . '<tr><td bgcolor="#ffffff" style="background:#ffffff;padding:30px 28px 34px;border-radius:0 0 0 16px;' . $font . ';font-size:17px;line-height:27px;color:#1a1a1a">'
        . '<h1 style="margin:0 0 18px;' . $font . ';font-size:24px;line-height:30px;font-weight:bold;color:#111111;text-transform:uppercase">' . e($heading) . '</h1>'
        . '<div style="' . $font . ';font-size:17px;line-height:27px;color:#1a1a1a">' . $bodyHtml . '</div>'
        . $button
        . '</td></tr>'
        . '<tr><td align="center" style="padding:18px 10px 0;' . $font . ';font-size:13px;line-height:18px;color:#777777">RLG Bootcamp · Relog Media</td></tr>'
        . '</table></td></tr></table></body></html>';
}
