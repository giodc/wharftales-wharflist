<?php

namespace App\Actions\Fortify;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AuthenticateUser
{
    public function __invoke(Request $request): ?User
    {
        $login = Str::lower(trim((string) $request->input('email', $request->input('login', ''))));

        if ($login === '' || blank($request->input('password'))) {
            return null;
        }

        $user = User::query()
            ->where(function ($query) use ($login): void {
                $query->whereRaw('LOWER(email) = ?', [$login])
                    ->orWhereRaw('LOWER(username) = ?', [$login]);
            })
            ->first();

        if (! $user || ! Hash::check((string) $request->input('password'), $user->password)) {
            return null;
        }

        return $user;
    }
}
