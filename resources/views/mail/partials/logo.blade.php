@if ($logoUrl)
    <tr>
        <td style="padding-bottom:24px;text-align:{{ $logoAlign }};">
            <img src="{{ $logoUrl }}" alt="{{ $siteName }}" style="max-height:64px;max-width:240px;display:inline-block;">
        </td>
    </tr>
@endif
