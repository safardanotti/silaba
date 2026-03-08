<?php

namespace App\Http\Controllers;

use App\Models\Pinjaman;
use App\Models\PengajuanPinjaman;
use App\Models\AngsuranPinjaman;
use App\Models\Anggota;
use App\Models\ProdukPinjaman;
use App\Models\Transaksi;
use App\Models\DetailTransaksi;
use App\Models\Notifikasi;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PinjamanController extends Controller
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
     * Daftar semua pinjaman
     */
    public function index(Request $request)
    {
        $query = Pinjaman::with(['anggota', 'produkPinjaman']);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('anggota_id')) {
            $query->where('anggota_id', $request->anggota_id);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('no_pinjaman', 'like', "%{$search}%")
                    ->orWhereHas('anggota', function ($qa) use ($search) {
                        $qa->where('nama_anggota', 'like', "%{$search}%")
                            ->orWhere('no_anggota', 'like', "%{$search}%");
                    });
            });
        }

        $pinjaman = $query->orderBy('created_at', 'desc')->paginate(20);

        $summary = [
            'total_pinjaman' => Pinjaman::aktif()->sum('jumlah_pinjaman'),
            'total_saldo' => Pinjaman::aktif()->sum('saldo_pokok'),
            'jumlah_aktif' => Pinjaman::aktif()->count(),
            'jumlah_lunas' => Pinjaman::lunas()->count(),
        ];

        return view('admin.pinjaman.index', compact('pinjaman', 'summary'));
    }

    /**
     * Form tambah pinjaman baru
     */
    public function create()
    {
        $anggota = Anggota::aktif()->orderBy('nama_anggota')->get();
        $produk = ProdukPinjaman::aktif()->get();

        return view('admin.pinjaman.create', compact('anggota', 'produk'));
    }

    /**
     * Simpan pinjaman baru
     */
    public function store(Request $request)
    {
        // Parse currency before validation
        $jumlahPinjaman = $this->parseCurrency($request->jumlah_pinjaman);
        
        // Merge parsed value back to request for validation
        $request->merge(['jumlah_pinjaman' => $jumlahPinjaman]);

        $request->validate([
            'anggota_id' => 'required|exists:anggota,id',
            'produk_pinjaman_id' => 'required|exists:produk_pinjaman,id',
            'jumlah_pinjaman' => 'required|numeric|min:0',
            'tenor' => 'required|integer|min:1',
            'tanggal_pinjaman' => 'required|date',
        ]);

        $produk = ProdukPinjaman::findOrFail($request->produk_pinjaman_id);
        $anggota = Anggota::findOrFail($request->anggota_id);

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

        $hitungan = $produk->hitungAngsuranBulanan($jumlahPinjaman, $request->tenor);

        DB::beginTransaction();
        try {
            // Buat transaksi pengeluaran (pencairan)
            $transaksi = Transaksi::create([
                'tanggal' => $request->tanggal_pinjaman,
                'uraian_kegiatan' => "PENCAIRAN PINJAMAN a/n {$anggota->nama_anggota} ({$produk->kode_produk})",
                'jenis_transaksi' => 'pengeluaran',
                'jenis_masuk' => $request->jenis_keluar ?? 'kas',
                'created_by' => auth()->id(),
            ]);

            // Detail - Debet Piutang
            DetailTransaksi::create([
                'transaksi_id' => $transaksi->id,
                'kode_akun' => $produk->kode_akun_piutang ?? '120',
                'debet' => $jumlahPinjaman,
                'kredit' => 0,
            ]);

            // Detail - Kredit Kas/Bank
            $kodeKas = $request->jenis_keluar === 'bank' ? '101' : '100';
            DetailTransaksi::create([
                'transaksi_id' => $transaksi->id,
                'kode_akun' => $kodeKas,
                'debet' => 0,
                'kredit' => $jumlahPinjaman,
            ]);

            // Buat pinjaman
            $pinjaman = Pinjaman::create([
                'no_pinjaman' => Pinjaman::generateNoPinjaman(),
                'anggota_id' => $request->anggota_id,
                'produk_pinjaman_id' => $request->produk_pinjaman_id,
                'tanggal_pinjaman' => $request->tanggal_pinjaman,
                'tanggal_jatuh_tempo' => date('Y-m-d', strtotime($request->tanggal_pinjaman . " + {$request->tenor} months")),
                'jumlah_pinjaman' => $jumlahPinjaman,
                'bunga_persen' => $produk->bunga_persen,
                'tenor' => $request->tenor,
                'angsuran_pokok' => $hitungan['angsuran_pokok'],
                'angsuran_bunga' => $hitungan['angsuran_bunga'],
                'total_angsuran' => $hitungan['total_angsuran'],
                'saldo_pokok' => $jumlahPinjaman,
                'saldo_bunga' => $hitungan['total_bunga'],
                'status' => 'aktif',
                'transaksi_id' => $transaksi->id,
                'created_by' => auth()->id(),
            ]);

            // Generate jadwal angsuran
            $pinjaman->generateJadwalAngsuran();

            // Kirim notifikasi ke anggota
            if ($anggota->user) {
                Notifikasi::kirim(
                    $anggota->user->id,
                    'Pinjaman Dicairkan',
                    "Pinjaman Anda sebesar Rp " . number_format($jumlahPinjaman, 0, ',', '.') . " telah dicairkan. No. Pinjaman: {$pinjaman->no_pinjaman}",
                    'success',
                    route('anggota.pinjaman.show', $pinjaman->id)
                );
            }

            DB::commit();

            return redirect()->route('admin.pinjaman.show', $pinjaman->id)
                ->with('success', 'Pinjaman berhasil dibuat dengan No. ' . $pinjaman->no_pinjaman);

        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()
                ->with('error', 'Gagal membuat pinjaman: ' . $e->getMessage())
                ->withInput();
        }
    }

    /**
     * Detail pinjaman
     */
    public function show($id)
    {
        $pinjaman = Pinjaman::with([
            'anggota',
            'produkPinjaman',
            'angsuran' => function ($q) {
                $q->orderBy('angsuran_ke');
            },
            'transaksi.detailTransaksi',
        ])->findOrFail($id);

        return view('admin.pinjaman.show', compact('pinjaman'));
    }

    /**
     * Form bayar angsuran
     */
    public function bayarAngsuran($pinjamanId, $angsuranId)
    {
        $pinjaman = Pinjaman::with('anggota')->findOrFail($pinjamanId);
        $angsuran = AngsuranPinjaman::findOrFail($angsuranId);

        return view('admin.pinjaman.bayar-angsuran', compact('pinjaman', 'angsuran'));
    }

    /**
     * Proses bayar angsuran
     */
    public function prosesBayarAngsuran(Request $request, $pinjamanId, $angsuranId)
    {
        // Parse currency before validation
        $jumlahBayar = $this->parseCurrency($request->jumlah_bayar);
        $request->merge(['jumlah_bayar' => $jumlahBayar]);

        $request->validate([
            'jumlah_bayar' => 'required|numeric|min:0',
            'tanggal_bayar' => 'required|date',
        ]);

        $pinjaman = Pinjaman::with('anggota', 'produkPinjaman')->findOrFail($pinjamanId);
        $angsuran = AngsuranPinjaman::findOrFail($angsuranId);
        $anggota = $pinjaman->anggota;

        DB::beginTransaction();
        try {
            // Buat transaksi penerimaan
            $transaksi = Transaksi::create([
                'tanggal' => $request->tanggal_bayar,
                'uraian_kegiatan' => "ANGSURAN KE-{$angsuran->angsuran_ke} PINJAMAN {$pinjaman->no_pinjaman} a/n {$anggota->nama_anggota}",
                'jenis_transaksi' => 'penerimaan',
                'jenis_masuk' => $request->jenis_masuk ?? 'kas',
                'created_by' => auth()->id(),
            ]);

            // Hitung proporsi pokok dan bunga
            $proporsiPokok = min($angsuran->angsuran_pokok, $jumlahBayar);
            $proporsiBunga = $jumlahBayar - $proporsiPokok;

            // Detail - Debet Kas/Bank
            $kodeKas = $request->jenis_masuk === 'bank' ? '101' : '100';
            DetailTransaksi::create([
                'transaksi_id' => $transaksi->id,
                'kode_akun' => $kodeKas,
                'debet' => $jumlahBayar,
                'kredit' => 0,
            ]);

            // Detail - Kredit Piutang (pokok)
            if ($proporsiPokok > 0) {
                DetailTransaksi::create([
                    'transaksi_id' => $transaksi->id,
                    'kode_akun' => $pinjaman->produkPinjaman->kode_akun_piutang ?? '120',
                    'debet' => 0,
                    'kredit' => $proporsiPokok,
                ]);
            }

            // Detail - Kredit Pendapatan Bunga
            if ($proporsiBunga > 0) {
                DetailTransaksi::create([
                    'transaksi_id' => $transaksi->id,
                    'kode_akun' => $pinjaman->produkPinjaman->kode_akun_bunga ?? '700',
                    'debet' => 0,
                    'kredit' => $proporsiBunga,
                ]);
            }

            // Update angsuran
            $angsuran->update([
                'tanggal_bayar' => $request->tanggal_bayar,
                'jumlah_bayar' => $jumlahBayar,
                'status' => $jumlahBayar >= $angsuran->total_angsuran ? 'lunas' : 'sebagian',
                'transaksi_id' => $transaksi->id,
            ]);

            // Update saldo pinjaman
            $pinjaman->saldo_pokok -= $proporsiPokok;
            $pinjaman->saldo_bunga -= $proporsiBunga;
            $pinjaman->angsuran_ke = $angsuran->angsuran_ke;

            // Cek apakah lunas
            if ($pinjaman->saldo_pokok <= 0) {
                $pinjaman->status = 'lunas';
                $pinjaman->saldo_pokok = 0;
            }

            $pinjaman->save();

            // Kirim notifikasi ke anggota
            if ($anggota->user) {
                Notifikasi::kirim(
                    $anggota->user->id,
                    'Pembayaran Angsuran',
                    "Pembayaran angsuran ke-{$angsuran->angsuran_ke} sebesar Rp " . number_format($jumlahBayar, 0, ',', '.') . " telah diterima.",
                    'success',
                    route('anggota.pinjaman.show', $pinjaman->id)
                );
            }

            DB::commit();

            return redirect()->route('admin.pinjaman.show', $pinjaman->id)
                ->with('success', 'Pembayaran angsuran berhasil dicatat');

        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()
                ->with('error', 'Gagal mencatat pembayaran: ' . $e->getMessage())
                ->withInput();
        }
    }

    /**
     * Daftar pengajuan pinjaman
     */
    public function pengajuan(Request $request)
    {
        $query = PengajuanPinjaman::with(['anggota', 'produkPinjaman']);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $pengajuan = $query->orderBy('created_at', 'desc')->paginate(20);

        $countPending = PengajuanPinjaman::pending()->count();

        return view('admin.pinjaman.pengajuan', compact('pengajuan', 'countPending'));
    }

    /**
     * Detail pengajuan
     */
    public function pengajuanDetail($id)
    {
        $pengajuan = PengajuanPinjaman::with(['anggota', 'produkPinjaman'])->findOrFail($id);
        
        // Hitung simulasi angsuran
        $simulasi = $pengajuan->produkPinjaman->hitungAngsuranBulanan(
            $pengajuan->jumlah_pinjaman,
            $pengajuan->tenor
        );

        return view('admin.pinjaman.pengajuan-detail', compact('pengajuan', 'simulasi'));
    }

    /**
     * Approve pengajuan
     */
    public function approvePengajuan(Request $request, $id)
    {
        $pengajuan = PengajuanPinjaman::with(['anggota', 'produkPinjaman'])->findOrFail($id);

        if ($pengajuan->status !== 'pending' && $pengajuan->status !== 'diproses') {
            return redirect()->back()->with('error', 'Pengajuan sudah diproses');
        }

        DB::beginTransaction();
        try {
            $pengajuan->update([
                'status' => 'disetujui',
                'approved_by' => auth()->id(),
                'approved_at' => now(),
                'catatan_approval' => $request->catatan,
            ]);

            // Kirim notifikasi ke anggota
            if ($pengajuan->anggota->user) {
                Notifikasi::kirim(
                    $pengajuan->anggota->user->id,
                    'Pengajuan Pinjaman Disetujui',
                    "Pengajuan pinjaman Anda sebesar Rp " . number_format($pengajuan->jumlah_pinjaman, 0, ',', '.') . " telah DISETUJUI. Silakan tunggu proses pencairan.",
                    'success',
                    route('anggota.pengajuan.show', $pengajuan->id)
                );
            }

            DB::commit();

            return redirect()->route('admin.pinjaman.pengajuan')
                ->with('success', 'Pengajuan pinjaman disetujui');

        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->with('error', 'Gagal approve: ' . $e->getMessage());
        }
    }

    /**
     * Tolak pengajuan
     */
    public function tolakPengajuan(Request $request, $id)
    {
        $pengajuan = PengajuanPinjaman::with(['anggota'])->findOrFail($id);

        $request->validate([
            'catatan' => 'required|string',
        ]);

        $pengajuan->update([
            'status' => 'ditolak',
            'approved_by' => auth()->id(),
            'approved_at' => now(),
            'catatan_approval' => $request->catatan,
        ]);

        // Kirim notifikasi ke anggota
        if ($pengajuan->anggota->user) {
            Notifikasi::kirim(
                $pengajuan->anggota->user->id,
                'Pengajuan Pinjaman Ditolak',
                "Pengajuan pinjaman Anda sebesar Rp " . number_format($pengajuan->jumlah_pinjaman, 0, ',', '.') . " DITOLAK. Alasan: {$request->catatan}",
                'danger',
                route('anggota.pengajuan.show', $pengajuan->id)
            );
        }

        return redirect()->route('admin.pinjaman.pengajuan')
            ->with('success', 'Pengajuan pinjaman ditolak');
    }

    /**
     * Cairkan pinjaman dari pengajuan yang disetujui
     */
    public function cairkan(Request $request, $id)
    {
        $pengajuan = PengajuanPinjaman::with(['anggota', 'produkPinjaman'])->findOrFail($id);

        if ($pengajuan->status !== 'disetujui') {
            return redirect()->back()->with('error', 'Hanya pengajuan yang disetujui yang bisa dicairkan');
        }

        $request->validate([
            'tanggal_pinjaman' => 'required|date',
            'jenis_keluar' => 'required|in:kas,bank',
        ]);

        $produk = $pengajuan->produkPinjaman;
        $anggota = $pengajuan->anggota;
        $hitungan = $produk->hitungAngsuranBulanan($pengajuan->jumlah_pinjaman, $pengajuan->tenor);

        DB::beginTransaction();
        try {
            // Buat transaksi pengeluaran (pencairan)
            $transaksi = Transaksi::create([
                'tanggal' => $request->tanggal_pinjaman,
                'uraian_kegiatan' => "PENCAIRAN PINJAMAN a/n {$anggota->nama_anggota} ({$produk->kode_produk})",
                'jenis_transaksi' => 'pengeluaran',
                'jenis_masuk' => $request->jenis_keluar,
                'created_by' => auth()->id(),
            ]);

            // Detail - Debet Piutang
            DetailTransaksi::create([
                'transaksi_id' => $transaksi->id,
                'kode_akun' => $produk->kode_akun_piutang ?? '120',
                'debet' => $pengajuan->jumlah_pinjaman,
                'kredit' => 0,
            ]);

            // Detail - Kredit Kas/Bank
            $kodeKas = $request->jenis_keluar === 'bank' ? '101' : '100';
            DetailTransaksi::create([
                'transaksi_id' => $transaksi->id,
                'kode_akun' => $kodeKas,
                'debet' => 0,
                'kredit' => $pengajuan->jumlah_pinjaman,
            ]);

            // Buat pinjaman
            $pinjaman = Pinjaman::create([
                'no_pinjaman' => Pinjaman::generateNoPinjaman(),
                'pengajuan_id' => $pengajuan->id,
                'anggota_id' => $pengajuan->anggota_id,
                'produk_pinjaman_id' => $pengajuan->produk_pinjaman_id,
                'tanggal_pinjaman' => $request->tanggal_pinjaman,
                'tanggal_jatuh_tempo' => date('Y-m-d', strtotime($request->tanggal_pinjaman . " + {$pengajuan->tenor} months")),
                'jumlah_pinjaman' => $pengajuan->jumlah_pinjaman,
                'bunga_persen' => $produk->bunga_persen,
                'tenor' => $pengajuan->tenor,
                'angsuran_pokok' => $hitungan['angsuran_pokok'],
                'angsuran_bunga' => $hitungan['angsuran_bunga'],
                'total_angsuran' => $hitungan['total_angsuran'],
                'saldo_pokok' => $pengajuan->jumlah_pinjaman,
                'saldo_bunga' => $hitungan['total_bunga'],
                'status' => 'aktif',
                'transaksi_id' => $transaksi->id,
                'created_by' => auth()->id(),
            ]);

            // Generate jadwal angsuran
            $pinjaman->generateJadwalAngsuran();

            // Update status pengajuan
            $pengajuan->update(['status' => 'dicairkan']);

            // Kirim notifikasi ke anggota
            if ($anggota->user) {
                Notifikasi::kirim(
                    $anggota->user->id,
                    'Pinjaman Dicairkan',
                    "Pinjaman Anda sebesar Rp " . number_format($pengajuan->jumlah_pinjaman, 0, ',', '.') . " telah dicairkan. No. Pinjaman: {$pinjaman->no_pinjaman}",
                    'success',
                    route('anggota.pinjaman.show', $pinjaman->id)
                );
            }

            DB::commit();

            return redirect()->route('admin.pinjaman.show', $pinjaman->id)
                ->with('success', 'Pinjaman berhasil dicairkan dengan No. ' . $pinjaman->no_pinjaman);

        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()
                ->with('error', 'Gagal mencairkan pinjaman: ' . $e->getMessage());
        }
    }

    /**
     * Hitung simulasi angsuran (AJAX)
     */
    public function hitungSimulasi(Request $request)
    {
        $produk = ProdukPinjaman::find($request->produk_id);
        
        if (!$produk) {
            return response()->json(['error' => 'Produk tidak ditemukan'], 404);
        }

        // Parse currency format
        $jumlah = $this->parseCurrency($request->jumlah);

        $simulasi = $produk->hitungAngsuranBulanan($jumlah, $request->tenor);
        $simulasi['bunga_persen'] = $produk->bunga_persen;

        return response()->json($simulasi);
    }
}