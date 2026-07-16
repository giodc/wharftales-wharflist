@php
    $hasFooter = filled($footerCompany ?? null)
        || filled($footerAddress ?? null)
        || filled($footerCity ?? null)
        || filled($footerCountry ?? null)
        || filled($unsubscribeUrl ?? null);
@endphp
@if ($hasFooter)
    <tr>
        <td style="padding-top:32px;border-top:1px solid #dddddd;font-size:12px;line-height:1.5;color:#555555;">
            @if (! empty($footerCompany))<div>{{ $footerCompany }}</div>@endif
            @if (! empty($footerAddress))<div>{{ $footerAddress }}</div>@endif
            @if (! empty($footerCity) || ! empty($footerCountry))
                <div>{{ collect([$footerCity ?? null, $footerCountry ?? null])->filter()->implode(', ') }}</div>
            @endif
            @if (! empty($unsubscribeUrl))
                <div style="margin-top:12px;">
                    <a href="{{ $unsubscribeUrl }}" style="color:#555555;">Unsubscribe</a>
                </div>
            @endif
        </td>
    </tr>
@endif
