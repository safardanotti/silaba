<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AnggotaMiddleware
{
    /**
     * Handle an incoming request.
     * Middleware untuk memastikan user adalah anggota
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (!auth()->check()) {
            return redirect()->route('login');
        }

        if (!auth()->user()->isAnggota()) {
            if ($request->ajax()) {
                return response()->json(['error' => 'Unauthorized'], 403);
            }
            return redirect()->route('dashboard')->with('error', 'Halaman ini hanya untuk anggota koperasi.');
        }

        // Pastikan user memiliki data anggota
        if (!auth()->user()->anggota) {
            return redirect()->route('login')->with('error', 'Data anggota tidak ditemukan.');
        }

        return $next($request);
    }
}
