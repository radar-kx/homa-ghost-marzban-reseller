<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\View\View;

class AuthController extends Controller
{
    public function create(): View { return view('auth.login'); }

    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate(['email' => ['required', 'email', 'max:190'], 'password' => ['required', 'string', 'max:255']]);
        $key = Str::lower($credentials['email']).'|'.$request->ip();
        if (RateLimiter::tooManyAttempts($key, 5)) return back()->withErrors(['email' => 'تلاش‌های ناموفق زیاد است؛ چند دقیقه دیگر دوباره امتحان کنید.'])->onlyInput('email');

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            RateLimiter::hit($key, 300);
            return back()->withErrors(['email' => 'ایمیل یا رمز عبور درست نیست.'])->onlyInput('email');
        }
        if (! $request->user()->is_active) {
            Auth::logout();
            return back()->withErrors(['email' => 'حساب شما غیرفعال شده است.']);
        }

        RateLimiter::clear($key);
        $request->session()->regenerate();
        return redirect()->intended(route('dashboard'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout(); $request->session()->invalidate(); $request->session()->regenerateToken();
        return redirect()->route('login');
    }
}
