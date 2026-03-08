<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Anggota;
use App\Models\Pinjaman;
use App\Models\AngsuranPinjaman;
use App\Models\PengajuanPinjaman;
use App\Models\ProdukPinjaman;
use App\Models\SaldoSimpanan;
use App\Models\SimpananAnggota;
use App\Models\Notifikasi;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class ApiAnggotaController extends Controller
{
    /**
     * Login API
     */
    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'username' => 'required|string',
            'password' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = User::where('username', $request->username)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Username atau password salah'
            ], 401);
        }

        if ($user->role !== 'anggota') {
            return response()->json([
                'success' => false,
                'message' => 'Akun ini bukan akun anggota'
            ], 403);
        }

        if ($user->status !== 'active') {
            return response()->json([
                'success' => false,
                'message' => 'Akun tidak aktif'
            ], 403);
        }

        // Generate simple token (for production use Laravel Sanctum)
        $token = base64_encode($user->id . '|' . time() . '|' . md5($user->username . time()));

        return response()->json([
            'success' => true,
            'message' => 'Login berhasil',
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'username' => $user->username,
                    'full_name' => $user->full_name,
                    'role' => $user->role,
                    'anggota_id' => $user->anggota_id,
                ],
                'token' => $token
            ]
        ]);
    }

    /**
     * Get Dashboard Data
     */
    public function dashboard(Request $request)
    {
        $user = $this->getAuthUser($request);
        if (!$user) {
            return $this->unauthorizedResponse();
        }

        $anggota = $user->anggota;
        if (!$anggota) {
            return response()->json([
                'success' => false,
                'message' => 'Data anggota tidak ditemukan'
            ], 404);
        }

        // Summary data
        $totalSimpanan = SaldoSimpanan::where('anggota_id', $anggota->id)->sum('total_saldo');
        $totalPinjaman = Pinjaman::where('anggota_id', $anggota->id)->aktif()->sum('saldo_pokok');
        $pinjamanAktif = Pinjaman::where('anggota_id', $anggota->id)->aktif()->count();
        
        // Angsuran yang harus dibayar bulan ini
        $angsuranBulanIni = AngsuranPinjaman::whereHas('pinjaman', function ($q) use ($anggota) {
                $q->where('anggota_id', $anggota->id)->aktif();
            })
            ->belumBayar()
            ->whereMonth('tanggal_jatuh_tempo', now()->month)
            ->whereYear('tanggal_jatuh_tempo', now()->year)
            ->with('pinjaman:id,no_pinjaman,produk_pinjaman_id')
            ->get();

        // Notifikasi belum dibaca - dengan pengecekan method exists
        $notifikasiBelumDibaca = 0;
        if (method_exists($user, 'notifikasiBelumDibaca')) {
            $notifikasiBelumDibaca = $user->notifikasiBelumDibaca()->count();
        }

        return response()->json([
            'success' => true,
            'data' => [
                'anggota' => [
                    'id' => $anggota->id,
                    'no_anggota' => $anggota->no_anggota,
                    'nama_anggota' => $anggota->nama_anggota,
                    'unit_kerja' => $anggota->unit_kerja,
                    'jabatan' => $anggota->jabatan,
                    'foto' => $anggota->foto ? url('storage/' . $anggota->foto) : null,
                ],
                'summary' => [
                    'total_simpanan' => (float) $totalSimpanan,
                    'total_pinjaman' => (float) $totalPinjaman,
                    'pinjaman_aktif' => $pinjamanAktif,
                    'notifikasi_belum_dibaca' => $notifikasiBelumDibaca,
                ],
                'angsuran_bulan_ini' => $angsuranBulanIni->map(function ($item) {
                    return [
                        'id' => $item->id,
                        'pinjaman_id' => $item->pinjaman_id,
                        'no_pinjaman' => $item->pinjaman->no_pinjaman ?? '-',
                        'angsuran_ke' => $item->angsuran_ke,
                        'tanggal_jatuh_tempo' => $item->tanggal_jatuh_tempo->format('Y-m-d'),
                        'total_angsuran' => (float) $item->total_angsuran,
                        'status' => $item->status,
                    ];
                }),
            ]
        ]);
    }

    /**
     * Get Profil Anggota
     */
    public function profil(Request $request)
    {
        $user = $this->getAuthUser($request);
        if (!$user) {
            return $this->unauthorizedResponse();
        }

        $anggota = $user->anggota;
        if (!$anggota) {
            return response()->json([
                'success' => false,
                'message' => 'Data anggota tidak ditemukan'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $anggota->id,
                'no_anggota' => $anggota->no_anggota,
                'nama_anggota' => $anggota->nama_anggota,
                'nik' => $anggota->nik,
                'tempat_lahir' => $anggota->tempat_lahir,
                'tanggal_lahir' => $anggota->tanggal_lahir ? $anggota->tanggal_lahir->format('Y-m-d') : null,
                'jenis_kelamin' => $anggota->jenis_kelamin,
                'alamat' => $anggota->alamat,
                'no_hp' => $anggota->no_hp,
                'email' => $anggota->email,
                'unit_kerja' => $anggota->unit_kerja,
                'jabatan' => $anggota->jabatan,
                'tanggal_masuk' => $anggota->tanggal_masuk ? $anggota->tanggal_masuk->format('Y-m-d') : null,
                'status_anggota' => $anggota->status_anggota,
                'foto' => $anggota->foto ? url('storage/' . $anggota->foto) : null,
            ]
        ]);
    }

    /**
     * Get Simpanan
     */
    public function simpanan(Request $request)
    {
        $user = $this->getAuthUser($request);
        if (!$user) {
            return $this->unauthorizedResponse();
        }

        $anggota = $user->anggota;

        $saldoSimpanan = SaldoSimpanan::where('anggota_id', $anggota->id)
            ->with('jenisSimpanan')
            ->get()
            ->map(function ($item) {
                return [
                    'id' => $item->id,
                    'jenis_simpanan' => $item->jenisSimpanan->nama_simpanan ?? '-',
                    'total_saldo' => (float) $item->total_saldo,
                ];
            });

        $riwayatSimpanan = SimpananAnggota::where('anggota_id', $anggota->id)
            ->with('jenisSimpanan')
            ->orderBy('tanggal_transaksi', 'desc')
            ->limit(50)
            ->get()
            ->map(function ($item) {
                return [
                    'id' => $item->id,
                    'tanggal_transaksi' => $item->tanggal_transaksi,
                    'jenis_simpanan' => $item->jenisSimpanan->nama_simpanan ?? '-',
                    'jenis_transaksi' => $item->jenis_transaksi,
                    'jumlah' => (float) $item->jumlah,
                    'keterangan' => $item->keterangan,
                ];
            });

        return response()->json([
            'success' => true,
            'data' => [
                'saldo_simpanan' => $saldoSimpanan,
                'riwayat_simpanan' => $riwayatSimpanan,
            ]
        ]);
    }

    /**
     * Get Daftar Pinjaman
     */
    public function pinjaman(Request $request)
    {
        $user = $this->getAuthUser($request);
        if (!$user) {
            return $this->unauthorizedResponse();
        }

        $anggota = $user->anggota;

        $pinjaman = Pinjaman::where('anggota_id', $anggota->id)
            ->with('produkPinjaman')
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($item) {
                return [
                    'id' => $item->id,
                    'no_pinjaman' => $item->no_pinjaman,
                    'produk' => $item->produkPinjaman->nama_produk ?? '-',
                    'tanggal_pinjaman' => $item->tanggal_pinjaman->format('Y-m-d'),
                    'jumlah_pinjaman' => (float) $item->jumlah_pinjaman,
                    'tenor' => $item->tenor,
                    'angsuran_ke' => $item->angsuran_ke,
                    'saldo_pokok' => (float) $item->saldo_pokok,
                    'total_angsuran' => (float) $item->total_angsuran,
                    'status' => $item->status,
                    'persentase_lunas' => $item->persentase_lunas ?? 0,
                ];
            });

        return response()->json([
            'success' => true,
            'data' => $pinjaman
        ]);
    }

    /**
     * Get Detail Pinjaman
     */
    public function pinjamanDetail(Request $request, $id)
    {
        $user = $this->getAuthUser($request);
        if (!$user) {
            return $this->unauthorizedResponse();
        }

        $anggota = $user->anggota;

        $pinjaman = Pinjaman::where('anggota_id', $anggota->id)
            ->with([
                'produkPinjaman',
                'angsuran' => function ($q) {
                    $q->orderBy('angsuran_ke');
                }
            ])
            ->find($id);

        if (!$pinjaman) {
            return response()->json([
                'success' => false,
                'message' => 'Pinjaman tidak ditemukan'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $pinjaman->id,
                'no_pinjaman' => $pinjaman->no_pinjaman,
                'produk' => $pinjaman->produkPinjaman->nama_produk ?? '-',
                'tanggal_pinjaman' => $pinjaman->tanggal_pinjaman->format('Y-m-d'),
                'tanggal_jatuh_tempo' => $pinjaman->tanggal_jatuh_tempo->format('Y-m-d'),
                'jumlah_pinjaman' => (float) $pinjaman->jumlah_pinjaman,
                'bunga_persen' => (float) $pinjaman->bunga_persen,
                'tenor' => $pinjaman->tenor,
                'angsuran_pokok' => (float) $pinjaman->angsuran_pokok,
                'angsuran_bunga' => (float) $pinjaman->angsuran_bunga,
                'total_angsuran' => (float) $pinjaman->total_angsuran,
                'saldo_pokok' => (float) $pinjaman->saldo_pokok,
                'angsuran_ke' => $pinjaman->angsuran_ke,
                'status' => $pinjaman->status,
                'persentase_lunas' => $pinjaman->persentase_lunas ?? 0,
                'jadwal_angsuran' => $pinjaman->angsuran->map(function ($item) {
                    return [
                        'id' => $item->id,
                        'angsuran_ke' => $item->angsuran_ke,
                        'tanggal_jatuh_tempo' => $item->tanggal_jatuh_tempo->format('Y-m-d'),
                        'tanggal_bayar' => $item->tanggal_bayar ? $item->tanggal_bayar->format('Y-m-d') : null,
                        'angsuran_pokok' => (float) $item->angsuran_pokok,
                        'angsuran_bunga' => (float) $item->angsuran_bunga,
                        'total_angsuran' => (float) $item->total_angsuran,
                        'jumlah_bayar' => (float) $item->jumlah_bayar,
                        'sisa_pokok_sebelum' => (float) $item->sisa_pokok_sebelum,
                        'sisa_pokok_sesudah' => (float) $item->sisa_pokok_sesudah,
                        'status' => $item->status,
                    ];
                }),
            ]
        ]);
    }

    /**
     * Get Daftar Pengajuan
     */
    public function pengajuan(Request $request)
    {
        $user = $this->getAuthUser($request);
        if (!$user) {
            return $this->unauthorizedResponse();
        }

        $anggota = $user->anggota;

        $pengajuan = PengajuanPinjaman::where('anggota_id', $anggota->id)
            ->with('produkPinjaman')
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($item) {
                return [
                    'id' => $item->id,
                    'no_pengajuan' => $item->no_pengajuan,
                    'produk' => $item->produkPinjaman->nama_produk ?? '-',
                    'jumlah_pinjaman' => (float) $item->jumlah_pinjaman,
                    'tenor' => $item->tenor,
                    'keperluan' => $item->keperluan,
                    'status' => $item->status,
                    'catatan_approval' => $item->catatan_approval,
                    'created_at' => $item->created_at->format('Y-m-d H:i:s'),
                ];
            });

        return response()->json([
            'success' => true,
            'data' => $pengajuan
        ]);
    }

    /**
     * Get Detail Pengajuan
     */
    public function pengajuanDetail(Request $request, $id)
    {
        $user = $this->getAuthUser($request);
        if (!$user) {
            return $this->unauthorizedResponse();
        }

        $anggota = $user->anggota;

        $pengajuan = PengajuanPinjaman::where('anggota_id', $anggota->id)
            ->with('produkPinjaman')
            ->find($id);

        if (!$pengajuan) {
            return response()->json([
                'success' => false,
                'message' => 'Pengajuan tidak ditemukan'
            ], 404);
        }

        $simulasi = $pengajuan->produkPinjaman->hitungAngsuranBulanan(
            $pengajuan->jumlah_pinjaman,
            $pengajuan->tenor
        );

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $pengajuan->id,
                'no_pengajuan' => $pengajuan->no_pengajuan,
                'produk' => [
                    'id' => $pengajuan->produkPinjaman->id,
                    'nama' => $pengajuan->produkPinjaman->nama_produk,
                    'bunga_persen' => (float) $pengajuan->produkPinjaman->bunga_persen,
                ],
                'jumlah_pinjaman' => (float) $pengajuan->jumlah_pinjaman,
                'tenor' => $pengajuan->tenor,
                'keperluan' => $pengajuan->keperluan,
                'status' => $pengajuan->status,
                'catatan_approval' => $pengajuan->catatan_approval,
                'created_at' => $pengajuan->created_at->format('Y-m-d H:i:s'),
                'simulasi' => $simulasi,
            ]
        ]);
    }

    /**
     * Get Produk Pinjaman
     */
    public function produkPinjaman(Request $request)
    {
        $user = $this->getAuthUser($request);
        if (!$user) {
            return $this->unauthorizedResponse();
        }

        $produk = ProdukPinjaman::aktif()->get()->map(function ($item) {
            return [
                'id' => $item->id,
                'kode_produk' => $item->kode_produk,
                'nama_produk' => $item->nama_produk,
                'bunga_persen' => (float) $item->bunga_persen,
                'max_tenor' => $item->max_tenor,
                'max_pinjaman' => (float) $item->max_pinjaman,
                'min_pinjaman' => (float) $item->min_pinjaman,
                'syarat_ketentuan' => $item->syarat_ketentuan,
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $produk
        ]);
    }

    /**
     * Simulasi Pinjaman
     */
    public function simulasiPinjaman(Request $request)
    {
        $user = $this->getAuthUser($request);
        if (!$user) {
            return $this->unauthorizedResponse();
        }

        $validator = Validator::make($request->all(), [
            'produk_id' => 'required|exists:produk_pinjaman,id',
            'jumlah' => 'required|numeric|min:0',
            'tenor' => 'required|integer|min:1',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal',
                'errors' => $validator->errors()
            ], 422);
        }

        $produk = ProdukPinjaman::find($request->produk_id);
        $simulasi = $produk->hitungAngsuranBulanan($request->jumlah, $request->tenor);

        return response()->json([
            'success' => true,
            'data' => [
                'produk' => $produk->nama_produk,
                'bunga_persen' => (float) $produk->bunga_persen,
                'jumlah_pinjaman' => (float) $request->jumlah,
                'tenor' => $request->tenor,
                'angsuran_pokok' => $simulasi['angsuran_pokok'],
                'angsuran_bunga' => $simulasi['angsuran_bunga'],
                'total_angsuran' => $simulasi['total_angsuran'],
                'total_bunga' => $simulasi['total_bunga'],
                'total_bayar' => $simulasi['total_bayar'],
            ]
        ]);
    }

    /**
     * Store Pengajuan Pinjaman
     */
    public function storePengajuan(Request $request)
    {
        $user = $this->getAuthUser($request);
        if (!$user) {
            return $this->unauthorizedResponse();
        }

        $anggota = $user->anggota;

        // Cek apakah ada pengajuan pending
        $pengajuanPending = PengajuanPinjaman::where('anggota_id', $anggota->id)
            ->whereIn('status', ['pending', 'diproses'])
            ->exists();

        if ($pengajuanPending) {
            return response()->json([
                'success' => false,
                'message' => 'Anda masih memiliki pengajuan yang sedang diproses'
            ], 400);
        }

        $validator = Validator::make($request->all(), [
            'produk_pinjaman_id' => 'required|exists:produk_pinjaman,id',
            'jumlah_pinjaman' => 'required|numeric|min:0',
            'tenor' => 'required|integer|min:1',
            'keperluan' => 'required|string',
            'dok_ktp' => 'required|file|mimes:jpg,jpeg,png,pdf|max:2048',
            'dok_kk' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:2048',
            'dok_slip_gaji' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:2048',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal',
                'errors' => $validator->errors()
            ], 422);
        }

        $produk = ProdukPinjaman::findOrFail($request->produk_pinjaman_id);

        // Validasi jumlah dan tenor
        if ($request->jumlah_pinjaman < $produk->min_pinjaman || $request->jumlah_pinjaman > $produk->max_pinjaman) {
            return response()->json([
                'success' => false,
                'message' => "Jumlah pinjaman harus antara Rp " . number_format($produk->min_pinjaman, 0, ',', '.') . " - Rp " . number_format($produk->max_pinjaman, 0, ',', '.')
            ], 400);
        }

        if ($request->tenor > $produk->max_tenor) {
            return response()->json([
                'success' => false,
                'message' => "Tenor maksimal adalah {$produk->max_tenor} bulan"
            ], 400);
        }

        // Upload dokumen
        $dokKtp = $request->file('dok_ktp')->store('pengajuan/dokumen', 'public');
        $dokKk = $request->hasFile('dok_kk') ? $request->file('dok_kk')->store('pengajuan/dokumen', 'public') : null;
        $dokSlipGaji = $request->hasFile('dok_slip_gaji') ? $request->file('dok_slip_gaji')->store('pengajuan/dokumen', 'public') : null;

        $pengajuan = PengajuanPinjaman::create([
            'no_pengajuan' => PengajuanPinjaman::generateNoPengajuan(),
            'anggota_id' => $anggota->id,
            'produk_pinjaman_id' => $request->produk_pinjaman_id,
            'jumlah_pinjaman' => $request->jumlah_pinjaman,
            'tenor' => $request->tenor,
            'keperluan' => $request->keperluan,
            'dok_ktp' => $dokKtp,
            'dok_kk' => $dokKk,
            'dok_slip_gaji' => $dokSlipGaji,
            'status' => 'pending',
        ]);

        // Kirim notifikasi ke admin - dengan try catch
        try {
            if (class_exists('App\Models\Notifikasi') && method_exists(Notifikasi::class, 'kirimKeAdmin')) {
                Notifikasi::kirimKeAdmin(
                    'Pengajuan Pinjaman Baru',
                    "Pengajuan pinjaman baru dari {$anggota->nama_anggota} sebesar Rp " . number_format($request->jumlah_pinjaman, 0, ',', '.'),
                    'info',
                    route('admin.pinjaman.pengajuan-detail', $pengajuan->id)
                );
            }
        } catch (\Exception $e) {
            // Abaikan error notifikasi
            Log::warning('Gagal kirim notifikasi: ' . $e->getMessage());
        }

        return response()->json([
            'success' => true,
            'message' => 'Pengajuan pinjaman berhasil diajukan dengan No. ' . $pengajuan->no_pengajuan,
            'data' => [
                'id' => $pengajuan->id,
                'no_pengajuan' => $pengajuan->no_pengajuan,
            ]
        ]);
    }

    /**
     * Get Notifikasi
     */
    public function notifikasi(Request $request)
    {
        $user = $this->getAuthUser($request);
        if (!$user) {
            return $this->unauthorizedResponse();
        }

        try {
            $notifikasi = $user->notifikasi()
                ->orderBy('created_at', 'desc')
                ->limit(50)
                ->get()
                ->map(function ($item) {
                    return [
                        'id' => $item->id,
                        'judul' => $item->judul,
                        'pesan' => $item->pesan,
                        'tipe' => $item->tipe,
                        'dibaca' => $item->dibaca,
                        'created_at' => $item->created_at->format('Y-m-d H:i:s'),
                    ];
                });
        } catch (\Exception $e) {
            $notifikasi = [];
        }

        return response()->json([
            'success' => true,
            'data' => $notifikasi
        ]);
    }

    /**
     * Mark Notifikasi as Read
     */
    public function bacaNotifikasi(Request $request, $id)
    {
        $user = $this->getAuthUser($request);
        if (!$user) {
            return $this->unauthorizedResponse();
        }

        $notifikasi = Notifikasi::where('user_id', $user->id)->find($id);
        
        if (!$notifikasi) {
            return response()->json([
                'success' => false,
                'message' => 'Notifikasi tidak ditemukan'
            ], 404);
        }

        $notifikasi->tandaiDibaca();

        return response()->json([
            'success' => true,
            'message' => 'Notifikasi telah ditandai dibaca'
        ]);
    }

    /**
     * Mark All Notifikasi as Read
     */
    public function bacaSemuaNotifikasi(Request $request)
    {
        $user = $this->getAuthUser($request);
        if (!$user) {
            return $this->unauthorizedResponse();
        }

        Notifikasi::where('user_id', $user->id)
            ->where('dibaca', false)
            ->update(['dibaca' => true]);

        return response()->json([
            'success' => true,
            'message' => 'Semua notifikasi telah ditandai dibaca'
        ]);
    }

    /**
     * Helper: Get Authenticated User - FIXED VERSION
     */
    private function getAuthUser(Request $request)
    {
        $token = $request->header('Authorization');
        
        Log::info('=== AUTH DEBUG ===');
        Log::info('Token received: ' . $token);
        
        if (!$token) {
            Log::info('No token provided');
            return null;
        }

        // Remove "Bearer " prefix if present
        $token = str_replace('Bearer ', '', $token);
        
        Log::info('Token after cleanup: ' . $token);

        try {
            $decoded = base64_decode($token);
            Log::info('Decoded token: ' . $decoded);
            
            $parts = explode('|', $decoded);
            
            // Hanya perlu minimal 1 part (user_id)
            if (count($parts) < 1) {
                Log::info('Invalid token format - parts count: ' . count($parts));
                return null;
            }

            $userId = $parts[0];
            Log::info('User ID from token: ' . $userId);
            
            $user = User::find($userId);

            if (!$user) {
                Log::info('User not found for ID: ' . $userId);
                return null;
            }

            Log::info('User found: ' . $user->username . ' | Role: ' . $user->role . ' | Status: ' . $user->status);
            
            // Return user tanpa cek role dan status (sudah dicek saat login)
            return $user;
            
        } catch (\Exception $e) {
            Log::info('Exception in getAuthUser: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Helper: Unauthorized Response
     */
    private function unauthorizedResponse()
    {
        return response()->json([
            'success' => false,
            'message' => 'Unauthorized'
        ], 401);
    }
}