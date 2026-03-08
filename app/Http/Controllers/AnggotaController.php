<?php

namespace App\Http\Controllers;

use App\Models\Anggota;
use App\Models\User;
use App\Models\Pinjaman;
use App\Models\SaldoSimpanan;
use App\Models\PendaftaranAnggota;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

class AnggotaController extends Controller
{
    /**
     * Tampilkan daftar anggota
     */
    public function index(Request $request)
    {
        $query = Anggota::query();

        // Filter by status
        if ($request->filled('status')) {
            $query->where('status_anggota', $request->status);
        }

        // Filter by unit kerja
        if ($request->filled('unit_kerja')) {
            $query->where('unit_kerja', $request->unit_kerja);
        }

        // Search
        if ($request->filled('search')) {
            $query->cari($request->search);
        }

        $anggota = $query->withCount(['pinjamanAktif', 'saldoSimpanan'])
            ->orderBy('nama_anggota')
            ->paginate(20);

        $unitKerja = Anggota::distinct()->pluck('unit_kerja')->filter();

        return view('admin.anggota.index', compact('anggota', 'unitKerja'));
    }

    /**
     * Form tambah anggota baru
     */
    public function create()
    {
        return view('admin.anggota.create');
    }

    /**
     * Simpan anggota baru
     */
    public function store(Request $request)
    {
        $request->validate([
            'nama_anggota' => 'required|string|max:100',
            'nik' => 'nullable|string|size:16|unique:anggota',
            'tempat_lahir' => 'nullable|string|max:50',
            'tanggal_lahir' => 'nullable|date',
            'jenis_kelamin' => 'nullable|in:L,P',
            'alamat' => 'nullable|string',
            'no_hp' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:100',
            'unit_kerja' => 'nullable|string|max:100',
            'jabatan' => 'nullable|string|max:100',
            'tanggal_masuk' => 'nullable|date',
            'foto' => 'nullable|image|max:2048',
        ]);

        $data = $request->except('foto', 'buat_akun', 'password');
        $data['no_anggota'] = Anggota::generateNoAnggota();
        $data['created_by'] = auth()->id();

        // Upload foto jika ada
        if ($request->hasFile('foto')) {
            $data['foto'] = $request->file('foto')->store('anggota/foto', 'public');
        }

        $anggota = Anggota::create($data);

        // Buat akun user jika diminta
        if ($request->buat_akun) {
            $username = strtolower(str_replace(' ', '', $anggota->nama_anggota)) . $anggota->id;
            $password = $request->password ?? 'password123';

            User::create([
                'username' => $username,
                'password' => Hash::make($password),
                'full_name' => $anggota->nama_anggota,
                'role' => 'anggota',
                'anggota_id' => $anggota->id,
                'status' => 'active',
            ]);
        }

        return redirect()->route('admin.anggota.index')
            ->with('success', 'Anggota berhasil ditambahkan dengan No. Anggota: ' . $anggota->no_anggota);
    }

    /**
     * Tampilkan detail anggota
     */
    public function show($id)
    {
        $anggota = Anggota::with([
            'user',
            'pinjaman' => function ($q) {
                $q->orderBy('created_at', 'desc');
            },
            'pinjaman.angsuran',
            'saldoSimpanan.jenisSimpanan',
            'simpanan' => function ($q) {
                $q->orderBy('tanggal_transaksi', 'desc')->limit(20);
            },
        ])->findOrFail($id);

        return view('admin.anggota.show', compact('anggota'));
    }

    /**
     * Form edit anggota
     */
    public function edit($id)
    {
        $anggota = Anggota::with('user')->findOrFail($id);
        return view('admin.anggota.edit', compact('anggota'));
    }

    /**
     * Update anggota
     */
    public function update(Request $request, $id)
    {
        $anggota = Anggota::findOrFail($id);

        $request->validate([
            'nama_anggota' => 'required|string|max:100',
            'nik' => 'nullable|string|size:16|unique:anggota,nik,' . $id,
            'tempat_lahir' => 'nullable|string|max:50',
            'tanggal_lahir' => 'nullable|date',
            'jenis_kelamin' => 'nullable|in:L,P',
            'alamat' => 'nullable|string',
            'no_hp' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:100',
            'unit_kerja' => 'nullable|string|max:100',
            'jabatan' => 'nullable|string|max:100',
            'status_anggota' => 'required|in:aktif,tidak_aktif,keluar',
            'foto' => 'nullable|image|max:2048',
        ]);

        $data = $request->except('foto');

        // Upload foto baru jika ada
        if ($request->hasFile('foto')) {
            // Hapus foto lama
            if ($anggota->foto) {
                Storage::disk('public')->delete($anggota->foto);
            }
            $data['foto'] = $request->file('foto')->store('anggota/foto', 'public');
        }

        $anggota->update($data);

        return redirect()->route('admin.anggota.show', $id)
            ->with('success', 'Data anggota berhasil diupdate');
    }

    /**
     * Hapus anggota
     */
    public function destroy($id)
    {
        $anggota = Anggota::findOrFail($id);

        // Cek apakah punya pinjaman aktif
        if ($anggota->pinjamanAktif()->count() > 0) {
            return redirect()->back()
                ->with('error', 'Tidak bisa menghapus anggota yang masih memiliki pinjaman aktif');
        }

        // Hapus foto
        if ($anggota->foto) {
            Storage::disk('public')->delete($anggota->foto);
        }

        // Hapus user terkait
        if ($anggota->user) {
            $anggota->user->delete();
        }

        $anggota->delete();

        return redirect()->route('admin.anggota.index')
            ->with('success', 'Anggota berhasil dihapus');
    }

    /**
     * Buat akun user untuk anggota
     */
    public function buatAkun(Request $request, $id)
    {
        $anggota = Anggota::findOrFail($id);

        if ($anggota->user) {
            return redirect()->back()->with('error', 'Anggota sudah memiliki akun');
        }

        $request->validate([
            'username' => 'required|string|max:50|unique:users',
            'password' => 'required|string|min:6',
        ]);

        User::create([
            'username' => $request->username,
            'password' => Hash::make($request->password),
            'full_name' => $anggota->nama_anggota,
            'role' => 'anggota',
            'anggota_id' => $anggota->id,
            'status' => 'active',
        ]);

        return redirect()->back()->with('success', 'Akun berhasil dibuat untuk anggota');
    }

    // =============================================
    // PENDAFTARAN ANGGOTA
    // =============================================

    /**
     * Daftar pendaftaran anggota yang pending
     */
    public function pendaftaran(Request $request)
    {
        $query = PendaftaranAnggota::query();

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $pendaftaran = $query->orderBy('created_at', 'desc')->paginate(20);

        return view('admin.anggota.pendaftaran', compact('pendaftaran'));
    }

    /**
     * Detail pendaftaran
     */
    public function pendaftaranDetail($id)
    {
        $pendaftaran = PendaftaranAnggota::findOrFail($id);
        return view('admin.anggota.pendaftaran-detail', compact('pendaftaran'));
    }

    /**
     * Approve pendaftaran
     */
    public function approvePendaftaran(Request $request, $id)
    {
        $pendaftaran = PendaftaranAnggota::findOrFail($id);

        if ($pendaftaran->status !== 'pending') {
            return redirect()->back()->with('error', 'Pendaftaran sudah diproses');
        }

        $pendaftaran->update([
            'status' => 'disetujui',
            'approved_by' => auth()->id(),
            'approved_at' => now(),
            'catatan' => $request->catatan,
        ]);

        // Konversi ke anggota
        $result = $pendaftaran->konversiKeAnggota(auth()->id());

        return redirect()->route('admin.anggota.show', $result['anggota']->id)
            ->with('success', 'Pendaftaran disetujui. Anggota baru: ' . $result['anggota']->no_anggota . ', Username: ' . $result['user']->username);
    }

    /**
     * Tolak pendaftaran
     */
    public function tolakPendaftaran(Request $request, $id)
    {
        $pendaftaran = PendaftaranAnggota::findOrFail($id);

        $request->validate([
            'catatan' => 'required|string',
        ]);

        $pendaftaran->update([
            'status' => 'ditolak',
            'approved_by' => auth()->id(),
            'approved_at' => now(),
            'catatan' => $request->catatan,
        ]);

        return redirect()->route('admin.anggota.pendaftaran')
            ->with('success', 'Pendaftaran ditolak');
    }
}
