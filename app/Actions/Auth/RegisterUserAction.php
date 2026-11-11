<?php

namespace App\Actions\Auth;

use App\Models\User;

class RegisterUserAction
{
    /**
     * Create a new user, fire the email verification notification, and
     * issue a Sanctum personal access token for immediate API use.
     *
     * The User model casts `password` as `hashed`, so the plain password
     * is hashed automatically on assignment - no manual Hash::make here.
     *
     * @param  array{name: string, email: string, password: string, role?: string}  $data
     * @return array{user: User, token: string}
     */
    public function handle(array $data): array
    {
        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
            'role' => $data['role'] ?? 'creator',
        ]);

        $user->sendEmailVerificationNotification();

        $token = $user->createToken('api')->plainTextToken;

        return ['user' => $user, 'token' => $token];
    }
}
