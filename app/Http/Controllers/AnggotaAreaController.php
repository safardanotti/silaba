<?php

namespace App\Http\Controllers;

use App\Models\Anggota;
use App\Models\Pinjaman;
use App\Models\AngsuranPinjaman;
use App\Models\PengajuanPinjaman;
use App\Models\ProdukPinjaman;
use App\Models\SaldoSimpanan;
use App\Models\SimpananAnggota;
use App\Models\Notifikasi;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class AnggotaAreaController extends Controller
{
    /**
     * Parse Indonesian currency format to float
     * Converts "5.000.000" or "5.000.000,50" to 5000000 or 5000000.50
     */
    private function parseCurrency($value)
    {
        if (empty($value)) return 0;
        if (is_numeric($value)) return floatval($value);
        
        // Remove thousand separators (dots) and convert decimal comma to dot
        $value = str_replace('.', '', $value);
        $value = str_replace(',', '.', $value);
        
        return floatval($value);
    }

    /**
     * Dashboard anggota
     */
    public function dashboard()
    {
        $user = auth()->user();
        $anggota = $user->anggota;

        if (!$anggota) {
            return redirect()->route('login')->with('error', 'Data anggota tidak ditemukan');
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
            ->with('pinjaman')
            ->get();

        // Pinjaman aktif
        $pinjamanList = Pinjaman::where('anggota_id', $anggota->id)
            ->aktif()
            ->with('produkPinjaman')
            ->get();

        // Saldo simpanan per jenis
        $saldoSimpanan = SaldoSimpanan::where('anggota_id', $anggota->id)
            ->with('jenisSimpanan')
            ->get();

        // Notifikasi terbaru
        $notifikasi = $user->notifikasiBelumDibaca()->limit(5)->get();

        // Riwayat transaksi terbaru
        $riwayatSimpanan = SimpananAnggota::where('anggota_id', $anggota->id)
            ->with('jenisSimpanan')
            ->orderBy('tanggal_transaksi', 'desc')
            ->limit(5)
            ->get();

        return view('anggota.dashboard', compact(
            'anggota',
            'totalSimpanan',
            'totalPinjaman',
            'pinjamanAktif',
            'angsuranBulanIni',
            'pinjamanList',
            'saldoSimpanan',
            'notifikasi',
            'riwayatSimpanan'
        ));
    }

    /**
     * Profil anggota
     */
    public function profil()
    {
        $anggota = auth()->user()->anggota;
        return view('anggota.profil', compact('anggota'));
    }

    /**
     * Update profil
     */
    public function updateProfil(Request $request)
    {
        $anggota = auth()->user()->anggota;

        $request->validate([
            'alamat' => 'nullable|string',
            'no_hp' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:100',
            'foto' => 'nullable|image|max:2048',
        ]);

        $data = $request->only(['alamat', 'no_hp', 'email']);

        if ($request->hasFile('foto')) {
            if ($anggota->foto) {
                Storage::disk('public')->delete($anggota->foto);
            }
            $data['foto'] = $request->file('foto')->store('anggota/foto', 'public');
        }

        $anggota->update($data);

        return redirect()->back()->with('success', 'Profil berhasil diupdate');
    }

    // =============================================
    // SIMPANAN
    // =============================================

    /**
     * Daftar simpanan
     */
    public function simpanan()
    {
        $anggota = auth()->user()->anggota;

        $saldoSimpanan = SaldoSimpanan::where('anggota_id', $anggota->id)
            ->with('jenisSimpanan')
            ->get();

        $riwayatSimpanan = SimpananAnggota::where('anggota_id', $anggota->id)
            ->with('jenisSimpanan')
            ->orderBy('tanggal_transaksi', 'desc')
            ->paginate(20);

        return view('anggota.simpanan', compact('saldoSimpanan', 'riwayatSimpanan'));
    }

    // =============================================
    // PINJAMAN
    // =============================================

    /**
     * Daftar pinjaman
     */
    public function pinjaman()
    {
        $anggota = auth()->user()->anggota;

        $pinjaman = Pinjaman::where('anggota_id', $anggota->id)
            ->with('produkPinjaman')
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return view('anggota.pinjaman.index', compact('pinjaman'));
    }

    /**
     * Detail pinjaman
     */
    public function pinjamanShow($id)
    {
        $anggota = auth()->user()->anggota;

        $pinjaman = Pinjaman::where('anggota_id', $anggota->id)
            ->with([
                'produkPinjaman',
                'angsuran' => function ($q) {
                    $q->orderBy('angsuran_ke');
                }
            ])
            ->findOrFail($id);

        return view('anggota.pinjaman.show', compact('pinjaman'));
    }

    // =============================================
    // PENGAJUAN PINJAMAN
    // =============================================

    /**
     * Daftar pengajuan
     */
    public function pengajuan()
    {
        $anggota = auth()->user()->anggota;

        $pengajuan = PengajuanPinjaman::where('anggota_id', $anggota->id)
            ->with('produkPinjaman')
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return view('anggota.pengajuan.index', compact('pengajuan'));
    }

    /**
     * Form pengajuan baru
     */
    public function pengajuanCreate()
    {
        $anggota = auth()->user()->anggota;
        $produk = ProdukPinjaman::aktif()->get();

        // Cek apakah ada pengajuan pending
        $pengajuanPending = PengajuanPinjaman::where('anggota_id', $anggota->id)
            ->whereIn('status', ['pending', 'diproses'])
            ->exists();

        if ($pengajuanPending) {
            return redirect()->route('anggota.pengajuan.index')
                ->with('error', 'Anda masih memiliki pengajuan yang sedang diproses');
        }

        return view('anggota.pengajuan.create', compact('produk'));
    }

    /**
     * Simpan pengajuan
     */
    public function pengajuanStore(Request $request)
    {
        $anggota = auth()->user()->anggota;

        // Parse currency before validation
        $jumlahPinjaman = $this->parseCurrency($request->jumlah_pinjaman);
        
        // Merge parsed value back to request for validation
        $request->merge(['jumlah_pinjaman' => $jumlahPinjaman]);

        $request->validate([
            'produk_pinjaman_id' => 'required|exists:produk_pinjaman,id',
            'jumlah_pinjaman' => 'required|numeric|min:0',
            'tenor' => 'required|integer|min:1',
            'keperluan' => 'required|string',
            'dok_ktp' => 'required|file|mimes:jpg,jpeg,png,pdf|max:2048',
            'dok_kk' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:2048',
            'dok_slip_gaji' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:2048',
        ]);

        $produk = ProdukPinjaman::findOrFail($request->produk_pinjaman_id);

        // Validasi jumlah dan tenor
        if ($jumlahPinjaman < $produk->min_pinjaman || $jumlahPinjaman > $produk->max_pinjaman) {
            return redirect()->back()
                ->with('error', "Jumlah pinjaman harus antara Rp " . number_format($produk->min_pinjaman, 0, ',', '.') . " - Rp " . number_format($produk->max_pinjaman, 0, ',', '.'))
                ->withInput();
        }

        if ($request->tenor > $produk->max_tenor) {
            return redirect()->back()
                ->with('error', "Tenor maksimal adalah {$produk->max_tenor} bulan")
                ->withInput();
        }

        // Upload dokumen
        $dokKtp = $request->file('dok_ktp')->store('pengajuan/dokumen', 'public');
        $dokKk = $request->hasFile('dok_kk') ? $request->file('dok_kk')->store('pengajuan/dokumen', 'public') : null;
        $dokSlipGaji = $request->hasFile('dok_slip_gaji') ? $request->file('dok_slip_gaji')->store('pengajuan/dokumen', 'public') : null;

        $pengajuan = PengajuanPinjaman::create([
            'no_pengajuan' => PengajuanPinjaman::generateNoPengajuan(),
            'anggota_id' => $anggota->id,
            'produk_pinjaman_id' => $request->produk_pinjaman_id,
            'jumlah_pinjaman' => $jumlahPinjaman,
            'tenor' => $request->tenor,
            'keperluan' => $request->keperluan,
            'dok_ktp' => $dokKtp,
            'dok_kk' => $dokKk,
            'dok_slip_gaji' => $dokSlipGaji,
            'status' => 'pending',
        ]);

        // Kirim notifikasi ke admin
        Notifikasi::kirimKeAdmin(
            'Pengajuan Pinjaman Baru',
            "Pengajuan pinjaman baru dari {$anggota->nama_anggota} sebesar Rp " . number_format($jumlahPinjaman, 0, ',', '.'),
            'info',
            route('admin.pinjaman.pengajuan-detail', $pengajuan->id)
        );

        return redirect()->route('anggota.pengajuan.index')
            ->with('success', 'Pengajuan pinjaman berhasil diajukan dengan No. ' . $pengajuan->no_pengajuan);
    }

    /**
     * Detail pengajuan
     */
    public function pengajuanShow($id)
    {
        $anggota = auth()->user()->anggota;

        $pengajuan = PengajuanPinjaman::where('anggota_id', $anggota->id)
            ->with('produkPinjaman')
            ->findOrFail($id);

        $simulasi = $pengajuan->produkPinjaman->hitungAngsuranBulanan(
            $pengajuan->jumlah_pinjaman,
            $pengajuan->tenor
        );

        return view('anggota.pengajuan.show', compact('pengajuan', 'simulasi'));
    }

    // =============================================
    // NOTIFIKASI
    // =============================================

    /**
     * Daftar notifikasi
     */
    public function notifikasi()
    {
        $notifikasi = auth()->user()->notifikasi()
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return view('anggota.notifikasi', compact('notifikasi'));
    }

    /**
     * Tandai notifikasi dibaca
     */
    public function bacaNotifikasi($id)
    {
        $notifikasi = Notifikasi::where('user_id', auth()->id())->findOrFail($id);
        $notifikasi->tandaiDibaca();

        if ($notifikasi->link) {
            return redirect($notifikasi->link);
        }

        return redirect()->back();
    }

    /**
     * Tandai semua notifikasi dibaca
     */
    public function bacaSemuaNotifikasi()
    {
        Notifikasi::where('user_id', auth()->id())
            ->where('dibaca', false)
            ->update(['dibaca' => true]);

        return redirect()->back()->with('success', 'Semua notifikasi telah ditandai dibaca');
    }

    /**
     * Simulasi pinjaman (AJAX)
     */
    public function simulasiPinjaman(Request $request)
    {
        $produk = ProdukPinjaman::find($request->produk_id);
        
        if (!$produk) {
            return response()->json(['error' => 'Produk tidak ditemukan'], 404);
        }

        // Parse currency format
        $jumlah = $this->parseCurrency($request->jumlah);

        $simulasi = $produk->hitungAngsuranBulanan($jumlah, $request->tenor);
        $simulasi['bunga_persen'] = $produk->bunga_persen;
        $simulasi['min_pinjaman'] = $produk->min_pinjaman;
        $simulasi['max_pinjaman'] = $produk->max_pinjaman;
        $simulasi['max_tenor'] = $produk->max_tenor;

        return response()->json($simulasi);
    }
}