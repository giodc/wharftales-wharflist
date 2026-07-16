<x-mail::message>
# Confirm your subscription

Thanks for subscribing to **{{ $siteName }}**.

Please confirm your email address to complete your subscription.

<x-mail::button :url="$verifyUrl">
Verify email
</x-mail::button>

If you did not request this, you can ignore this email.

Thanks,<br>
{{ $siteName }}
</x-mail::message>
