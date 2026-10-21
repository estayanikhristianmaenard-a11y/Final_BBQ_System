<?php
/** Shared email presentation. Inline styles and tables keep the layout usable in mail clients. */
function branded_email_html(string $title, string $preview, string $content): string
{
    return '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
        . '<title>' . e($title) . '</title><style>@media only screen and (max-width:480px){.email-body{padding:24px 18px!important}.email-code{font-size:32px!important;letter-spacing:6px!important}}</style></head>'
        . '<body style="margin:0;padding:0;background:#fbfbf8;color:#242824;font-family:Segoe UI,Arial,sans-serif;-webkit-text-size-adjust:100%">'
        . '<div style="display:none;max-height:0;overflow:hidden;opacity:0;mso-hide:all">' . e($preview) . '</div>'
        . '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background:#fbfbf8"><tr><td align="center" style="padding:24px 12px">'
        . '<table role="presentation" width="560" cellspacing="0" cellpadding="0" border="0" style="width:100%;max-width:560px;border:1px solid #dfe3db;border-radius:12px;background:#fff">'
        . '<tr><td bgcolor="#242824" style="padding:20px 24px;background:#242824;border-radius:11px 11px 0 0;border-bottom:3px solid #f4ac3d">'
        . '<table role="presentation" cellspacing="0" cellpadding="0" border="0"><tr><td width="36" style="font-size:25px;line-height:32px"><span aria-hidden="true">&#128293;</span></td>'
        . '<td style="color:#fff;font-family:Segoe UI,Arial,sans-serif;font-size:20px;font-weight:700">BulkBites BBQ</td></tr></table></td></tr>'
        . '<tr><td class="email-body" style="padding:28px 24px;font-size:15px;line-height:1.6;overflow-wrap:anywhere">' . $content . '</td></tr>'
        . '<tr><td style="padding:16px 24px;border-top:1px solid #dfe3db;color:#60665e;font-size:12px;line-height:1.6">BulkBites BBQ &bull; Pickup ordering<br>This is an automated email. Please do not reply.</td></tr>'
        . '</table></td></tr></table></body></html>';
}
function login_email_content(string $code, int $expiryMinutes): array
{
    $text="Your BulkBites BBQ sign-in code is: {$code}\n\n"
        . "Enter it on the sign-in page. It expires in {$expiryMinutes} minutes and works once.\n\n"
        . 'Do not share this code. If you did not request it, you can ignore this email.';
    $content='<h1 style="margin:0 0 12px;font-size:24px;line-height:1.25;color:#242824">Your sign-in code</h1>'
        . '<p style="margin:0 0 20px">Enter this code on the BulkBites sign-in page.</p>'
        . '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0"><tr><td align="center" bgcolor="#fff7e7" style="padding:22px 10px;background:#fff7e7;border:1px solid #f4ac3d;border-radius:8px">'
        . '<div class="email-code" dir="ltr" style="font-family:Consolas,Courier New,monospace;font-size:38px;font-weight:700;line-height:1.25;letter-spacing:8px;color:#242824">' . e($code) . '</div></td></tr></table>'
        . '<p style="margin:18px 0 0;color:#bc4015;font-weight:600">Expires in ' . $expiryMinutes . ' minutes. Use it once.</p>'
        . '<p style="margin:20px 0 0;color:#60665e;font-size:13px">Do not share this code with anyone. If you did not request it, you can ignore this email.</p>';
    return ['subject'=>'Your BulkBites BBQ sign-in code','text'=>$text,
        'html'=>branded_email_html('Your sign-in code','Your one-time sign-in code expires in ' . $expiryMinutes . ' minutes.',$content)];
}
