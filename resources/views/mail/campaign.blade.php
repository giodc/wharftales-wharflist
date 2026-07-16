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
                {!! \App\Support\Html::sanitizeCampaign($bodyHtml) !!}
            </td>
        </tr>
        @include('mail.partials.footer')
    </table>
</body>
</html>
