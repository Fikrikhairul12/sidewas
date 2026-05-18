<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;

class GoogleAuthController extends Controller
{
    public function redirect()
    {
        return Socialite::driver('google')->redirect();
    }

    public function callback()
    {
        if (request()->has('error')) {
            return redirect()
                ->route('login')
                ->with('error', 'Login dengan Google dibatalkan.');
        }

        $googleUser = Socialite::driver('google')->user();

        if (!User::isAllowedEmailDomain($googleUser->getEmail())) {
            return redirect()
                ->route('login')
                ->with('error', 'Login Google hanya diperbolehkan menggunakan email @bpjsketenagakerjaan.go.id.');
        }

        $user = User::where('google_id', $googleUser->getId())
            ->orWhere('email', $googleUser->getEmail())
            ->first();

        if (!$user) {
            $user = User::create([
                'name' => $googleUser->getName(),
                'email' => $googleUser->getEmail(),
                'email_verified_at' => now(),
                'google_id' => $googleUser->getId(),
                'avatar' => $googleUser->getAvatar(),
                'provider' => 'google',
                'password' => bcrypt(Str::random(32)),
            ]);
        } else {
            $user->update([
                'google_id' => $googleUser->getId(),
                'avatar' => $googleUser->getAvatar(),
                'provider' => 'google',
                'email_verified_at' => $user->email_verified_at ?? now(),
            ]);
        }

        Auth::login($user, true);

        return redirect()->intended(route('dashboard'));
    }
}
