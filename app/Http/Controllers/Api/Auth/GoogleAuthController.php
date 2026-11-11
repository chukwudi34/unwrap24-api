<?php

namespace App\Http\Controllers\Api\Auth;

use App\Actions\Auth\GoogleLoginAction;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Laravel\Socialite\Facades\Socialite;

class GoogleAuthController extends Controller
{
    public function __construct(private GoogleLoginAction $googleLogin) {}

    /**
     * Redirect the browser to Google's OAuth consent screen.
     *
     * Stateless flow: no session is required, so this works for a
     * server that is purely a token-issuing API behind a separate
     * frontend domain.
     */
    public function redirect(): RedirectResponse
    {
        return Socialite::driver('google')->stateless()->redirect();
    }

    /**
     * Handle Google's callback, find-or-create the user, and hand off
     * a Sanctum token to the frontend.
     *
     * Handoff mechanism: we redirect to `{FRONTEND_URL}/auth/google/callback`
     * with the plaintext Sanctum token as a `token` query parameter. The
     * frontend is responsible for reading it immediately, storing it
     * (e.g. in memory / httpOnly cookie set by its own backend-for-frontend,
     * or localStorage per its own security posture), and then stripping it
     * from the URL. On failure, it redirects with `?error=...` instead.
     */
    public function callback(): RedirectResponse
    {
        $frontendUrl = Config::get('services.frontend_url');

        try {
            $googleUser = Socialite::driver('google')->stateless()->user();
        } catch (\Throwable $e) {
            Log::warning('Google OAuth callback failed.', ['error' => $e->getMessage()]);

            return redirect()->away("{$frontendUrl}/auth/google/callback?error=google_auth_failed");
        }

        $result = $this->googleLogin->handle($googleUser);

        return redirect()->away(
            "{$frontendUrl}/auth/google/callback?token={$result['token']}"
        );
    }
}
