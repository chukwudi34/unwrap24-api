<?php

namespace App\Actions\Auth;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class LoginUserAction
{
    /**
     * Validate credentials and issue a new Sanctum personal access token.
     *
     * Unverified accounts are still allowed to log in (Laravel's standard
     * MustVerifyEmail pattern is enforced per-route via the
     * `verified` middleware where needed, not at login time), so a user
     * who registered but hasn't clicked their verification link yet can
     * still obtain a token and interact with the API.
     *
     * @param  array{email: string, password: string}  $data
     * @return array{user: User, token: string}
     *
     * @throws ValidationException
     */
    public function handle(array $data): array
    {
        $user = User::where('email', $data['email'])->first();

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        $token = $user->createToken('api')->plainTextToken;

        return ['user' => $user, 'token' => $token];
    }
}
