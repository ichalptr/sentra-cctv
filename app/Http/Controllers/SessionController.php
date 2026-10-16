<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class SessionController extends Controller
{
    public function setup(Request $r)
    {
        if (User::exists()) {
            return redirect()->route('login');
        }abort_unless(in_array($r->ip(), ['127.0.0.1', '::1']), 403, 'Setup pertama hanya melalui localhost.');

        return view('session', ['setup' => true]);
    }

    public function create(Request $r)
    {
        if (User::exists()) {
            return redirect()->route('login')->with('status', 'Akun administrator sudah tersedia. Silakan masuk.');
        }
        abort_unless(in_array($r->ip(), ['127.0.0.1', '::1']), 403);
        $d = $r->validate(['name' => ['required', 'string', 'max:80'], 'email' => ['required', 'email', 'max:190', 'unique:users,email'], 'password' => ['required', 'string', 'min:12', 'max:128', 'confirmed']]);
        $u = DB::transaction(function () use ($d) {
            DB::table('cameras')->where('key', 'CAM-01')->lockForUpdate()->first();
            if (User::exists()) {
                return null;
            }

            return User::create(['name' => $d['name'], 'email' => strtolower($d['email']), 'password' => Hash::make($d['password'])]);
        });
        $r->session()->regenerate();

        return redirect()->route('login')->with('status', $u
            ? 'Akun administrator berhasil dibuat. Silakan masuk menggunakan akun tersebut.'
            : 'Akun administrator sudah tersedia. Silakan masuk.');
    }

    public function login()
    {
        if (! User::exists()) {
            return redirect()->route('setup');
        }

return view('session', ['setup' => false]);
    }

    public function authenticate(Request $r)
    {
        $d = $r->validate(['email' => ['required', 'email'], 'password' => ['required', 'string', 'max:128']]);
        if (! Auth::attempt(['email' => strtolower($d['email']), 'password' => $d['password']])) {
            return back()->withErrors(['email' => 'Email atau password tidak sesuai.'])->onlyInput('email');
        }$r->session()->regenerate();

        return redirect()->intended(route('dashboard'));
    }

    public function logout(Request $r)
    {
        Auth::logout();
        $r->session()->invalidate();
        $r->session()->regenerateToken();

        return redirect()->route('login');
    }
}
