<?php

namespace App\Http\Controllers;

use App\Models\Anggota;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class ManajemenUserController extends Controller
{
    /**
     * Tampilkan daftar semua user
     */
    public function index(Request $request)
    {
        $query = User::query();

        // Filter by role
        if ($request->filled('role')) {
            $query->where('role', $request->role);
        }

        // Filter by status
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // Search
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('username', 'like', "%{$search}%")
                    ->orWhere('full_name', 'like', "%{$search}%");
            });
        }

        $users = $query->with('anggota')->orderBy('created_at', 'desc')->paginate(20);

        return view('admin.manajemen-user.index', compact('users'));
    }

    /**
     * Form tambah user baru
     */
    public function create()
    {
        $anggota = Anggota::whereDoesntHave('user')->aktif()->orderBy('nama_anggota')->get();
        return view('admin.manajemen-user.create', compact('anggota'));
    }

    /**
     * Simpan user baru
     */
    public function store(Request $request)
    {
        $request->validate([
            'username' => 'required|string|max:50|unique:users',
            'password' => 'required|string|min:6|confirmed',
            'full_name' => 'required|string|max:100',
            'role' => 'required|in:admin,pimpinan,staff,anggota',
            'anggota_id' => 'nullable|exists:anggota,id',
            'status' => 'required|in:active,inactive',
        ]);

        User::create([
            'username' => $request->username,
            'password' => Hash::make($request->password),
            'full_name' => $request->full_name,
            'role' => $request->role,
            'anggota_id' => $request->role == 'anggota' ? $request->anggota_id : null,
            'status' => $request->status,
        ]);

        return redirect()->route('admin.manajemen-user.index')
            ->with('success', 'User berhasil ditambahkan');
    }

    /**
     * Form edit user
     */
    public function edit($id)
    {
        $user = User::findOrFail($id);
        $anggota = Anggota::where(function ($q) use ($user) {
                $q->whereDoesntHave('user')
                    ->orWhere('id', $user->anggota_id);
            })
            ->aktif()
            ->orderBy('nama_anggota')
            ->get();

        return view('admin.manajemen-user.edit', compact('user', 'anggota'));
    }

    /**
     * Update user
     */
    public function update(Request $request, $id)
    {
        $user = User::findOrFail($id);

        $request->validate([
            'username' => 'required|string|max:50|unique:users,username,' . $id,
            'full_name' => 'required|string|max:100',
            'role' => 'required|in:admin,pimpinan,staff,anggota',
            'anggota_id' => 'nullable|exists:anggota,id',
            'status' => 'required|in:active,inactive,pending',
            'password' => 'nullable|string|min:6|confirmed',
        ]);

        $data = [
            'username' => $request->username,
            'full_name' => $request->full_name,
            'role' => $request->role,
            'anggota_id' => $request->role == 'anggota' ? $request->anggota_id : null,
            'status' => $request->status,
        ];

        // Update password jika diisi
        if ($request->filled('password')) {
            $data['password'] = Hash::make($request->password);
        }

        $user->update($data);

        return redirect()->route('admin.manajemen-user.index')
            ->with('success', 'User berhasil diupdate');
    }

    /**
     * Hapus user
     */
    public function destroy($id)
    {
        $user = User::findOrFail($id);

        // Jangan hapus user yang sedang login
        if ($user->id === auth()->id()) {
            return redirect()->back()->with('error', 'Tidak bisa menghapus akun sendiri');
        }

        $user->delete();

        return redirect()->route('admin.manajemen-user.index')
            ->with('success', 'User berhasil dihapus');
    }

    /**
     * Reset password user
     */
    public function resetPassword($id)
    {
        $user = User::findOrFail($id);
        $newPassword = 'password123';
        
        $user->update(['password' => Hash::make($newPassword)]);

        return redirect()->back()
            ->with('success', "Password user {$user->username} berhasil direset ke: {$newPassword}");
    }
}
