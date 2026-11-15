<?php
// Sends mail through PHP's mail(), i.e. the server's local sendmail/Postfix.

function send_mail(string $to, string $subject, string $text, string $html): bool
{
    if (MAIL_FROM === '') {
        error_log('send_mail: MAIL_FROM is not set in .env');
        return false;
    }

    $boundary = 'b_' . bin2hex(random_bytes(12));
    $from_name = '=?UTF-8?B?' . base64_encode(MAIL_FROM_NAME) . '?=';
    $headers = [
        'From: ' . $from_name . ' <' . MAIL_FROM . '>',
        'Reply-To: ' . MAIL_FROM,
        'MIME-Version: 1.0',
        'Content-Type: multipart/alternative; boundary="' . $boundary . '"',
        'X-Mailer: PersonalAccounts',
    ];
    $body = "--$boundary\r\n"
        . "Content-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n"
        . chunk_split(base64_encode($text)) . "\r\n"
        . "--$boundary\r\n"
        . "Content-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n"
        . chunk_split(base64_encode($html)) . "\r\n"
        . "--$boundary--\r\n";

    // -f sets the envelope sender so SPF/DMARC align with the From domain.
    $ok = mail($to, '=?UTF-8?B?' . base64_encode($subject) . '?=', $body, implode("\r\n", $headers), '-f' . MAIL_FROM);
    if (!$ok) {
        error_log('send_mail: mail() failed for ' . $to);
    }
    return $ok;
}

function mail_layout(string $heading, string $intro, string $button_text, string $url, string $footer): array
{
    $html = '<!DOCTYPE html><html lang="bn"><body style="margin:0;background:#f1f5f9;font-family:Arial,sans-serif;color:#0f172a">'
        . '<div style="max-width:520px;margin:24px auto;background:#fff;border-radius:12px;padding:28px">'
        . '<h2 style="margin:0 0 12px;color:#0f766e">' . e($heading) . '</h2>'
        . '<p style="line-height:1.7">' . e($intro) . '</p>'
        . '<p style="text-align:center;margin:28px 0"><a href="' . e($url) . '" style="background:#0f766e;color:#fff;padding:12px 22px;border-radius:8px;text-decoration:none;font-weight:bold">' . e($button_text) . '</a></p>'
        . '<p style="font-size:13px;color:#64748b;line-height:1.6">বাটন কাজ না করলে এই লিংকটি ব্রাউজারে খুলুন:<br><span style="word-break:break-all">' . e($url) . '</span></p>'
        . '<p style="font-size:13px;color:#64748b;line-height:1.6">' . e($footer) . '</p>'
        . '</div></body></html>';
    $text = "$heading\n\n$intro\n\n$button_text: $url\n\n$footer\n";
    return [$text, $html];
}
