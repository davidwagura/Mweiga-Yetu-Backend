<?php

namespace App\Http\Controllers;

use App\Models\User;
use Laravel\Socialite\Facades\Socialite;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Throwable;

class GoogleAuthController extends Controller
{
    /**
     * Redirect the user to Google’s OAuth page.
     */
    public function redirect()
    {
        return Socialite::driver('google')->redirect();
    }

    /**
     * Handle the callback from Google.
     */
    public function callback()
    {
        try {
            $googleUser = Socialite::driver('google')->user();
        } catch (Throwable $e) {
            return redirect(env('FRONTEND_URL', 'http://localhost:5173') . '/login?error=google_auth_failed');
        }

        $user = User::where('email', $googleUser->email)->first();

        if (!$user) {
            return redirect(env('FRONTEND_URL', 'http://localhost:5173') . '/');
        }

        Auth::login($user);

        $token = $user->createToken('ApiToken')->plainTextToken;

        return redirect(env('FRONTEND_URL', 'http://localhost:5173') . '/google/callback?token=' . $token);
    }
}
