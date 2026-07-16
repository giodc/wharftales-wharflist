<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>{{ $siteName }}</title>
</head>
<body style="margin:0;padding:0;background:#ffffff;color:#111111;font-family:Arial,Helvetica,sans-serif;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:640px;margin:0 auto;padding:24px;">
        @include('mail.partials.logo')
        <tr>
            <td style="font-size:16px;line-height:1.6;">
                <h1 style="font-size:22px;margin:0 0 16px;">Confirm your subscription</h1>
                <p style="margin:0 0 16px;">Thanks for subscribing to <strong>{{ $siteName }}</strong>.</p>
                <p style="margin:0 0 24px;">Please confirm your email address to complete your subscription.</p>
                <p style="margin:0 0 24px;">
                    <a href="{{ $verifyUrl }}" style="display:inline-block;background:#111111;color:#ffffff;text-decoration:none;padding:12px 20px;border-radius:4px;font-weight:bold;">Verify email</a>
                </p>
                <p style="margin:0 0 16px;color:#555555;font-size:14px;">If you did not request this, you can ignore this email.</p>
                <p style="margin:0;">Thanks,<br>{{ $siteName }}</p>
            </td>
        </tr>
        @include('mail.partials.footer')
    </table>
</body>
</html>
