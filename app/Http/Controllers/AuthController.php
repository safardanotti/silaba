<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    public function showLogin()
    {
        if (Auth::check()) {
            return $this->redirectBasedOnRole();
        }
        
        return view('auth.login');
    }

    public function login(Request $request)
    {
        $request->validate([
            'username' => 'required|string',
            'password' => 'required|string',
        ]);

        $credentials = [
            'username' => $request->username,
            'password' => $request->password,
        ];

        if (Auth::attempt($credentials)) {
            $request->session()->regenerate();
            
            // Cek status user
            if (Auth::user()->status !== 'active') {
                Auth::logout();
                return back()->withErrors([
                    'username' => 'Akun Anda tidak aktif. Hubungi administrator.',
                ])->withInput($request->only('username'));
            }
            
            return $this->redirectBasedOnRole();
        }

        return back()->withErrors([
            'username' => 'Username atau password salah!',
        ])->withInput($request->only('username'));
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        
        return redirect()->route('login');
    }

    /**
     * Redirect user berdasarkan role
     */
    protected function redirectBasedOnRole()
    {
        $user = Auth::user();

        // Jika role anggota, redirect ke area anggota
        if ($user->isAnggota()) {
            return redirect()->route('anggota.dashboard');
        }

        // Jika admin/pimpinan/staff, redirect ke dashboard admin
        return redirect()->route('dashboard');
    }
}
