<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;

class VerifyEmailController extends Controller
{
    /**
     * Mark the authenticated user's email address as verified.
     */
    public function __invoke(Request $request, $id, $hash): RedirectResponse
    {
        $user = User::findOrFail($id);

        // Verify the signature
        if (!hash_equals((string) $hash, sha1($user->getEmailForVerification()))) {
            return redirect(
                config('app.frontend_url').'/verify-email?verified=0'
            );
        }

        if ($user->hasVerifiedEmail()) {
            return redirect(
                config('app.frontend_url').'/dashboard?verified=1'
            );
        }

        if ($user->markEmailAsVerified()) {
            event(new Verified($user));
        }

        return redirect(
            config('app.frontend_url').'/dashboard?verified=1'
        );
    }
}
