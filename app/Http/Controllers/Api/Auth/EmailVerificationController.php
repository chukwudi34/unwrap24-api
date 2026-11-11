<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ResendVerificationRequest;
use App\Models\User;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class EmailVerificationController extends Controller
{
    /**
     * Verify the signed email verification link.
     *
     * This is a token-based API (no browser session), so the link opened
     * from the user's inbox carries no Authorization header - the route
     * signature itself (id + hash, via the `signed` middleware) is the
     * only authentication, matching Laravel's standard email verification
     * mechanics but resolved manually instead of via
     * Illuminate\Foundation\Auth\EmailVerificationRequest (which expects
     * an authenticated session/guard user). We redirect back to the
     * frontend with a status flag so the Next.js app can render a result
     * page, rather than returning raw JSON to a browser navigation.
     */
    public function verify(Request $request, string $id, string $hash): RedirectResponse
    {
        $frontendUrl = Config::get('services.frontend_url');

        /** @var User|null $user */
        $user = User::find($id);

        if (! $user || ! hash_equals(sha1($user->getEmailForVerification()), $hash)) {
            throw new NotFoundHttpException;
        }

        if ($user->hasVerifiedEmail()) {
            return redirect()->away("{$frontendUrl}/email-verification?status=already-verified");
        }

        $user->markEmailAsVerified();

        return redirect()->away("{$frontendUrl}/email-verification?status=success");
    }

    public function resend(ResendVerificationRequest $request): JsonResponse
    {
        /** @var User&MustVerifyEmail $user */
        $user = $request->user();

        if ($user->hasVerifiedEmail()) {
            return response()->json([
                'message' => 'Email already verified.',
            ]);
        }

        $user->sendEmailVerificationNotification();

        return response()->json([
            'message' => 'Verification link sent.',
        ]);
    }
}
