<?php

namespace App\Actions\Auth;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Socialite\Contracts\User as SocialiteUser;

class GoogleLoginAction
{
    /**
     * Find or create a user from a Socialite Google user, and issue a
     * Sanctum personal access token.
     *
     * Match order: by google_id first, then by email (to link an
     * existing password-based account to Google). Google-authenticated
     * users are considered email-verified automatically.
     *
     * @return array{user: User, token: string}
     */
    public function handle(SocialiteUser $googleUser): array
    {
        $user = User::where('google_id', $googleUser->getId())
            ->orWhere('email', $googleUser->getEmail())
            ->first();

        if ($user) {
            if (empty($user->google_id)) {
                $user->google_id = $googleUser->getId();
            }

            if (! $user->hasVerifiedEmail()) {
                $user->email_verified_at = now();
            }

            $user->save();
        } else {
            $user = User::create([
                'name' => $googleUser->getName() ?: $googleUser->getNickname() ?: 'Unwrap24 User',
                'email' => $googleUser->getEmail(),
                'password' => Hash::make(Str::random(40)),
                'google_id' => $googleUser->getId(),
                'role' => 'creator',
                'email_verified_at' => now(),
            ]);
        }

        $token = $user->createToken('api')->plainTextToken;

        return ['user' => $user, 'token' => $token];
    }
}
