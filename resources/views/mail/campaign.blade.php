<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>{{ $siteName }}</title>
</head>
<body style="margin:0;padding:0;background:#ffffff;color:#111111;font-family:Arial,Helvetica,sans-serif;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:640px;margin:0 auto;padding:24px;">
        @if ($logoUrl)
            <tr>
                <td style="padding-bottom:24px;">
                    <img src="{{ $logoUrl }}" alt="{{ $siteName }}" style="max-height:64px;max-width:240px;">
                </td>
            </tr>
        @endif
        <tr>
            <td style="font-size:16px;line-height:1.6;">
                {!! \App\Support\Html::sanitizeCampaign($bodyHtml) !!}
            </td>
        </tr>
        <tr>
            <td style="padding-top:32px;border-top:1px solid #dddddd;font-size:12px;line-height:1.5;color:#555555;">
                @if ($footerCompany)<div>{{ $footerCompany }}</div>@endif
                @if ($footerAddress)<div>{{ $footerAddress }}</div>@endif
                @if ($footerCity || $footerCountry)
                    <div>{{ collect([$footerCity, $footerCountry])->filter()->implode(', ') }}</div>
                @endif
                <div style="margin-top:12px;">
                    <a href="{{ $unsubscribeUrl }}" style="color:#555555;">Unsubscribe</a>
                </div>
            </td>
        </tr>
    </table>
</body>
</html>
