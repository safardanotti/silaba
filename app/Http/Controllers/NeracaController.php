<?php

namespace App\Http\Controllers;

use App\Models\KodeAkun;
use App\Models\KasBank;
use App\Models\SaldoAwal;
use App\Models\NeracaPosting;
use App\Models\NeracaPostingDetail;
use App\Models\NeracaRevisiHistory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class NeracaController extends Controller
{
    /**
     * Nama bulan Indonesia
     */
    private $bulanNama = [
        '01' => 'JANUARI', '02' => 'FEBRUARI', '03' => 'MARET',
        '04' => 'APRIL', '05' => 'MEI', '06' => 'JUNI',
        '07' => 'JULI', '08' => 'AGUSTUS', '09' => 'SEPTEMBER',
        '10' => 'OKTOBER', '11' => 'NOVEMBER', '12' => 'DESEMBER'
    ];

    /**
     * Display neraca page
     */
    public function index(Request $request)
    {
        $bulan = $request->get('bulan', date('m'));
        $tahun = $request->get('tahun', date('Y'));
        $periode = "{$tahun}-{$bulan}-01";
        $showNeraca = $request->has('bulan') && $request->has('tahun');

        // Check posting status
        $postingData = NeracaPosting::where('periode', $periode)->first();

        $data = [
            'bulan' => $bulan,
            'tahun' => $tahun,
            'bulanNama' => $this->bulanNama,
            'showNeraca' => $showNeraca,
            'postingData' => $postingData,
        ];

        if ($showNeraca) {
            $neracaData = $this->generateNeraca($periode, $bulan, $tahun);
            $data = array_merge($data, $neracaData);
        }

        return view('neraca.index', $data);
    }

    /**
     * Generate Neraca data - SAMA PERSIS DENGAN NATIVE PHP
     */
    private function generateNeraca($periode, $bulan, $tahun)
    {
        $yearMonth = date('Y-m', strtotime($periode));

        // Get saldo akhir from saldo_awal table
        $neracaData = DB::select("
            SELECT 
                ka.kode_akun,
                ka.nama_akun,
                ka.tipe_akun,
                COALESCE(sa.saldo_akhir_debet, 0) as saldo_debet,
                COALESCE(sa.saldo_akhir_kredit, 0) as saldo_kredit
            FROM kode_akun ka
            LEFT JOIN saldo_awal sa ON ka.kode_akun = sa.kode_akun AND sa.periode = ?
            WHERE ka.kode_akun NOT BETWEEN '700' AND '899'
            ORDER BY ka.kode_akun
        ", [$periode]);

        // Get Kas & Bank from kas_bank table
        $kasBank = DB::selectOne("
            SELECT 
                SUM(CASE WHEN jenis = 'kas' THEN saldo_akhir ELSE 0 END) as kas,
                SUM(CASE WHEN jenis = 'bank' THEN saldo_akhir ELSE 0 END) as bank
            FROM kas_bank
            WHERE periode = ?
        ", [$periode]);

        // Update kas and bank values
        foreach ($neracaData as &$row) {
            if ($row->kode_akun == '100' && $kasBank && $kasBank->kas > 0) {
                $row->saldo_debet = $kasBank->kas;
                $row->saldo_kredit = 0;
            }
            if ($row->kode_akun == '101' && $kasBank && $kasBank->bank > 0) {
                $row->saldo_debet = $kasBank->bank;
                $row->saldo_kredit = 0;
            }
        }

        // Calculate SHU tahun berjalan (900) - PAKE METODE LABA RUGI
        $shuTahunBerjalan = $this->calculateSHUFromLabaRugi($periode, $yearMonth);

        // Add SHU to data
        $neracaData[] = (object) [
            'kode_akun' => '900',
            'nama_akun' => 'SHU TAHUN BERJALAN',
            'tipe_akun' => 'passiva',
            'saldo_debet' => $shuTahunBerjalan < 0 ? abs($shuTahunBerjalan) : 0,
            'saldo_kredit' => $shuTahunBerjalan > 0 ? $shuTahunBerjalan : 0
        ];

        // Organize data for display
        $organized = $this->organizeNeracaData($neracaData);
        $organized['neracaRaw'] = $neracaData;

        return $organized;
    }

    /**
     * Calculate SHU from Laba Rugi methodology
     * Sama persis dengan Laba Rugi: realisasi_bulan_lalu + mutasi_bulan_ini
     */
    private function calculateSHUFromLabaRugi($periode, $yearMonth)
    {
        $periodeAwal = $periode;
        $periodeAkhir = date('Y-m-t', strtotime($periode));

        // Get anggaran data (realisasi bulan lalu)
        $anggaranData = DB::table('anggaran_laba_rugi')
            ->where('periode', $periode)
            ->get()
            ->keyBy('kode_akun');

        // Get mutasi dari transaksi
        $mutasiData = DB::select("
            SELECT 
                dt.kode_akun,
                SUM(dt.debet) as total_debet,
                SUM(dt.kredit) as total_kredit
            FROM detail_transaksi dt
            JOIN transaksi t ON dt.transaksi_id = t.id
            WHERE t.tanggal BETWEEN ? AND ?
            GROUP BY dt.kode_akun
        ", [$periodeAwal, $periodeAkhir]);

        $mutasi = [];
        foreach ($mutasiData as $row) {
            $mutasi[$row->kode_akun] = [
                'debet' => $row->total_debet,
                'kredit' => $row->total_kredit
            ];
        }

        // Get mutasi dari piutang (penjualan kredit)
        $piutangData = DB::select("
            SELECT 
                mp.kode_akun_default,
                SUM(tp.mutasi_debet) as total_debet
            FROM transaksi_piutang tp
            JOIN master_piutang mp ON tp.master_piutang_id = mp.id
            WHERE tp.periode = ?
            GROUP BY mp.kode_akun_default
        ", [$periode]);

        foreach ($piutangData as $row) {
            $kode = $row->kode_akun_default;
            if (!isset($mutasi[$kode])) {
                $mutasi[$kode] = ['debet' => 0, 'kredit' => 0];
            }
            $mutasi[$kode]['kredit'] += $row->total_debet; // Penjualan kredit = kredit pendapatan
        }

        // Calculate Pendapatan (700-799)
        $totalPendapatan = 0;
        $pendapatanCodes = DB::table('kode_akun')
            ->whereBetween('kode_akun', ['700', '799'])
            ->pluck('kode_akun');

        foreach ($pendapatanCodes as $kode) {
            $realisasiBulanLalu = isset($anggaranData[$kode]) ? $anggaranData[$kode]->realisasi_bulan_lalu : 0;
            $mutasiDebet = $mutasi[$kode]['debet'] ?? 0;
            $mutasiKredit = $mutasi[$kode]['kredit'] ?? 0;

            // Pendapatan: mutasi bulan ini = kredit - debet
            $mutasiBulanIni = $mutasiKredit - $mutasiDebet;
            $sdBulanIni = $realisasiBulanLalu + $mutasiBulanIni;

            $totalPendapatan += $sdBulanIni;
        }

        // Calculate Biaya (800-899)
        $totalBiaya = 0;
        $biayaCodes = DB::table('kode_akun')
            ->whereBetween('kode_akun', ['800', '899'])
            ->pluck('kode_akun');

        foreach ($biayaCodes as $kode) {
            $realisasiBulanLalu = isset($anggaranData[$kode]) ? $anggaranData[$kode]->realisasi_bulan_lalu : 0;
            $mutasiDebet = $mutasi[$kode]['debet'] ?? 0;
            $mutasiKredit = $mutasi[$kode]['kredit'] ?? 0;

            // Biaya: mutasi bulan ini = debet - kredit
            $mutasiBulanIni = $mutasiDebet - $mutasiKredit;
            $sdBulanIni = $realisasiBulanLalu + $mutasiBulanIni;

            $totalBiaya += $sdBulanIni;
        }

        return $totalPendapatan - $totalBiaya;
    }

    /**
     * Organize neraca data for display
     */
    private function organizeNeracaData($neracaData)
    {
        $aktiva = ['lancar' => [], 'tetap' => []];
        $passiva = ['hutang' => [], 'dana' => [], 'modal' => [], 'shu' => []];

        foreach ($neracaData as $item) {
            $saldo = $item->saldo_debet > 0 ? $item->saldo_debet : $item->saldo_kredit;

            if ($saldo == 0 && !in_array($item->kode_akun, ['370', '900'])) {
                continue;
            }

            // AKTIVA
            if (in_array($item->kode_akun, ['100', '101', '120', '130', '132', '145', '360'])) {
                $aktiva['lancar'][] = [
                    'kode' => $item->kode_akun,
                    'nama' => $item->nama_akun,
                    'jumlah' => abs($saldo)
                ];
            } elseif (in_array($item->kode_akun, ['200', '210'])) {
                $aktiva['tetap'][] = [
                    'kode' => $item->kode_akun,
                    'nama' => $item->nama_akun,
                    'jumlah' => abs($saldo),
                    'is_negative' => $item->kode_akun == '210'
                ];
            }
            // PASSIVA
            elseif (in_array($item->kode_akun, ['300', '310', '320', '370'])) {
                $passiva['hutang'][] = [
                    'kode' => $item->kode_akun,
                    'nama' => $item->nama_akun,
                    'jumlah' => abs($saldo)
                ];
            } elseif (in_array($item->kode_akun, ['530', '540', '550', '560', '565'])) {
                $passiva['dana'][] = [
                    'kode' => $item->kode_akun,
                    'nama' => $item->nama_akun,
                    'jumlah' => abs($saldo)
                ];
            } elseif (in_array($item->kode_akun, ['500', '510', '520', '525'])) {
                $passiva['modal'][] = [
                    'kode' => $item->kode_akun,
                    'nama' => $item->nama_akun,
                    'jumlah' => abs($saldo)
                ];
            } elseif (in_array($item->kode_akun, ['600', '900'])) {
                $passiva['shu'][] = [
                    'kode' => $item->kode_akun,
                    'nama' => $item->nama_akun,
                    'jumlah' => abs($saldo)
                ];
            }
        }

        // Calculate totals
        $totalAktivaLancar = array_sum(array_column($aktiva['lancar'], 'jumlah'));
        $totalAktivaTetap = 0;
        foreach ($aktiva['tetap'] as $item) {
            if ($item['is_negative'] ?? false) {
                $totalAktivaTetap -= $item['jumlah'];
            } else {
                $totalAktivaTetap += $item['jumlah'];
            }
        }
        $totalAktiva = $totalAktivaLancar + $totalAktivaTetap;

        $totalHutang = array_sum(array_column($passiva['hutang'], 'jumlah'));
        $totalDana = array_sum(array_column($passiva['dana'], 'jumlah'));
        $totalModal = array_sum(array_column($passiva['modal'], 'jumlah'));
        $totalSHU = array_sum(array_column($passiva['shu'], 'jumlah'));
        $totalPassiva = $totalHutang + $totalDana + $totalModal + $totalSHU;

        return [
            'aktiva' => $aktiva,
            'passiva' => $passiva,
            'totalAktivaLancar' => $totalAktivaLancar,
            'totalAktivaTetap' => $totalAktivaTetap,
            'totalAktiva' => $totalAktiva,
            'totalHutang' => $totalHutang,
            'totalDana' => $totalDana,
            'totalModal' => $totalModal,
            'totalSHU' => $totalSHU,
            'totalPassiva' => $totalPassiva,
        ];
    }

    /**
     * Post neraca for review
     */
    public function post(Request $request)
    {
        $bulan = $request->get('bulan', date('m'));
        $tahun = $request->get('tahun', date('Y'));
        $periode = "{$tahun}-{$bulan}-01";
        $yearMonth = "{$tahun}-{$bulan}";

        try {
            DB::beginTransaction();

            // Get neraca data
            $neracaData = DB::select("
                SELECT 
                    ka.kode_akun,
                    ka.nama_akun,
                    ka.tipe_akun,
                    COALESCE(sa.saldo_akhir_debet, 0) as saldo_debet,
                    COALESCE(sa.saldo_akhir_kredit, 0) as saldo_kredit
                FROM kode_akun ka
                LEFT JOIN saldo_awal sa ON ka.kode_akun = sa.kode_akun AND sa.periode = ?
                WHERE ka.kode_akun NOT BETWEEN '700' AND '899'
                ORDER BY ka.kode_akun
            ", [$periode]);

            // Get Kas & Bank
            $kasBank = DB::selectOne("
                SELECT 
                    SUM(CASE WHEN jenis = 'kas' THEN saldo_akhir ELSE 0 END) as kas,
                    SUM(CASE WHEN jenis = 'bank' THEN saldo_akhir ELSE 0 END) as bank
                FROM kas_bank
                WHERE periode = ?
            ", [$periode]);

            // Update kas and bank values
            foreach ($neracaData as &$row) {
                if ($row->kode_akun == '100' && $kasBank && $kasBank->kas > 0) {
                    $row->saldo_debet = $kasBank->kas;
                    $row->saldo_kredit = 0;
                }
                if ($row->kode_akun == '101' && $kasBank && $kasBank->bank > 0) {
                    $row->saldo_debet = $kasBank->bank;
                    $row->saldo_kredit = 0;
                }
            }

            // Calculate SHU
            $shuTahunBerjalan = $this->calculateSHUFromLabaRugi($periode, $yearMonth);

            // Add SHU to data
            $neracaData[] = (object) [
                'kode_akun' => '900',
                'nama_akun' => 'SHU TAHUN BERJALAN',
                'tipe_akun' => 'passiva',
                'saldo_debet' => $shuTahunBerjalan < 0 ? abs($shuTahunBerjalan) : 0,
                'saldo_kredit' => $shuTahunBerjalan > 0 ? $shuTahunBerjalan : 0
            ];

            // Check if posting exists
            $existingPost = NeracaPosting::where('periode', $periode)->first();

            if ($existingPost) {
                $existingPost->update([
                    'status' => 'posted',
                    'posted_by' => Auth::id(),
                    'posted_at' => now(),
                    'catatan_revisi' => null,
                ]);
                $postingId = $existingPost->id;
                NeracaPostingDetail::where('posting_id', $postingId)->delete();
            } else {
                $posting = NeracaPosting::create([
                    'periode' => $periode,
                    'status' => 'posted',
                    'posted_by' => Auth::id(),
                    'posted_at' => now(),
                ]);
                $postingId = $posting->id;
            }

            // Insert neraca detail
            foreach ($neracaData as $item) {
                NeracaPostingDetail::create([
                    'posting_id' => $postingId,
                    'kode_akun' => $item->kode_akun,
                    'nama_akun' => $item->nama_akun,
                    'tipe_akun' => $item->tipe_akun,
                    'saldo_debet' => $item->saldo_debet,
                    'saldo_kredit' => $item->saldo_kredit,
                ]);
            }

            // Add history
            NeracaRevisiHistory::create([
                'posting_id' => $postingId,
                'user_id' => Auth::id(),
                'action' => 'post',
                'catatan' => 'Neraca telah diposting untuk direview',
            ]);

            DB::commit();

            return redirect()
                ->route('neraca.index', ['bulan' => $bulan, 'tahun' => $tahun])
                ->with('success', 'Neraca berhasil diposting untuk direview pimpinan');

        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()
                ->route('neraca.index', ['bulan' => $bulan, 'tahun' => $tahun])
                ->with('error', 'Error: ' . $e->getMessage());
        }
    }

    /**
     * Display review neraca list
     */
    public function review()
    {
        $postingList = NeracaPosting::with('postedBy')
            ->whereIn('status', ['posted', 'revisi', 'approved'])
            ->orderBy('periode', 'desc')
            ->get();

        // Get recent history
        $recentHistory = NeracaRevisiHistory::with(['posting', 'user'])
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        return view('neraca.review', [
            'postingList' => $postingList,
            'recentHistory' => $recentHistory,
            'bulanNama' => $this->bulanNama,
        ]);
    }

    /**
     * Display review neraca detail
     */
    public function reviewDetail($id)
    {
        $posting = NeracaPosting::with(['postedBy', 'details', 'history.user'])->findOrFail($id);

        $organized = $this->organizePostingData($posting->details);

        return view('neraca.review-detail', array_merge([
            'posting' => $posting,
            'bulanNama' => $this->bulanNama,
        ], $organized));
    }

    /**
     * Organize posting data for display
     */
    private function organizePostingData($details)
    {
        $aktiva = ['lancar' => [], 'tetap' => []];
        $passiva = ['hutang' => [], 'dana' => [], 'modal' => [], 'shu' => []];

        foreach ($details as $item) {
            $saldo = $item->saldo_debet > 0 ? $item->saldo_debet : $item->saldo_kredit;

            if ($saldo == 0 && !in_array($item->kode_akun, ['370', '900'])) {
                continue;
            }

            // AKTIVA
            if (in_array($item->kode_akun, ['100', '101', '120', '130', '132', '145', '360'])) {
                $aktiva['lancar'][] = [
                    'kode' => $item->kode_akun,
                    'nama' => $item->nama_akun,
                    'jumlah' => abs($saldo)
                ];
            } elseif (in_array($item->kode_akun, ['200', '210'])) {
                $aktiva['tetap'][] = [
                    'kode' => $item->kode_akun,
                    'nama' => $item->nama_akun,
                    'jumlah' => abs($saldo),
                    'is_negative' => $item->kode_akun == '210'
                ];
            }
            // PASSIVA
            elseif (in_array($item->kode_akun, ['300', '310', '320', '370'])) {
                $passiva['hutang'][] = [
                    'kode' => $item->kode_akun,
                    'nama' => $item->nama_akun,
                    'jumlah' => abs($saldo)
                ];
            } elseif (in_array($item->kode_akun, ['530', '540', '550', '560', '565'])) {
                $passiva['dana'][] = [
                    'kode' => $item->kode_akun,
                    'nama' => $item->nama_akun,
                    'jumlah' => abs($saldo)
                ];
            } elseif (in_array($item->kode_akun, ['500', '510', '520', '525'])) {
                $passiva['modal'][] = [
                    'kode' => $item->kode_akun,
                    'nama' => $item->nama_akun,
                    'jumlah' => abs($saldo)
                ];
            } elseif (in_array($item->kode_akun, ['600', '900'])) {
                $passiva['shu'][] = [
                    'kode' => $item->kode_akun,
                    'nama' => $item->nama_akun,
                    'jumlah' => abs($saldo)
                ];
            }
        }

        // Calculate totals
        $totalAktivaLancar = array_sum(array_column($aktiva['lancar'], 'jumlah'));
        $totalAktivaTetap = 0;
        foreach ($aktiva['tetap'] as $item) {
            if ($item['is_negative'] ?? false) {
                $totalAktivaTetap -= $item['jumlah'];
            } else {
                $totalAktivaTetap += $item['jumlah'];
            }
        }
        $totalAktiva = $totalAktivaLancar + $totalAktivaTetap;

        $totalHutang = array_sum(array_column($passiva['hutang'], 'jumlah'));
        $totalDana = array_sum(array_column($passiva['dana'], 'jumlah'));
        $totalModal = array_sum(array_column($passiva['modal'], 'jumlah'));
        $totalSHU = array_sum(array_column($passiva['shu'], 'jumlah'));
        $totalPassiva = $totalHutang + $totalDana + $totalModal + $totalSHU;

        return [
            'aktiva' => $aktiva,
            'passiva' => $passiva,
            'totalAktivaLancar' => $totalAktivaLancar,
            'totalAktivaTetap' => $totalAktivaTetap,
            'totalAktiva' => $totalAktiva,
            'totalHutang' => $totalHutang,
            'totalDana' => $totalDana,
            'totalModal' => $totalModal,
            'totalSHU' => $totalSHU,
            'totalPassiva' => $totalPassiva,
        ];
    }

    /**
     * Request revision for neraca
     */
    public function requestRevision(Request $request, $id)
    {
        $request->validate([
            'catatan' => 'required|string|max:1000',
        ]);

        $posting = NeracaPosting::findOrFail($id);

        try {
            DB::beginTransaction();

            $posting->update([
                'status' => 'revisi',
                'catatan_revisi' => $request->catatan,
                'reviewed_by' => Auth::id(),
                'reviewed_at' => now(),
            ]);

            NeracaRevisiHistory::create([
                'posting_id' => $id,
                'user_id' => Auth::id(),
                'action' => 'revisi',
                'catatan' => $request->catatan,
            ]);

            DB::commit();

            return redirect()
                ->route('neraca.review')
                ->with('success', 'Neraca telah dikembalikan untuk direvisi');

        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->with('error', 'Error: ' . $e->getMessage());
        }
    }

    /**
     * Approve neraca
     */
    public function approve(Request $request, $id)
    {
        $posting = NeracaPosting::findOrFail($id);

        try {
            DB::beginTransaction();

            $posting->update([
                'status' => 'approved',
                'reviewed_by' => Auth::id(),
                'reviewed_at' => now(),
                'approved_at' => now(),
            ]);

            NeracaRevisiHistory::create([
                'posting_id' => $id,
                'user_id' => Auth::id(),
                'action' => 'approve',
                'catatan' => $request->catatan ?? 'Neraca telah disetujui',
            ]);

            DB::commit();

            return redirect()
                ->route('neraca.review')
                ->with('success', 'Neraca telah disetujui');

        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->with('error', 'Error: ' . $e->getMessage());
        }
    }

    /**
     * Display neraca mutasi page - SAMA PERSIS DENGAN NATIVE PHP
     * FIXED: Akun 900 tidak ditampilkan di Neraca Mutasi
     */
    public function mutasi(Request $request)
    {
        $bulan = $request->get('bulan', date('m'));
        $tahun = $request->get('tahun', date('Y'));
        $periodeAwal = "{$tahun}-{$bulan}-01";
        $periodeAkhir = date('Y-m-t', strtotime($periodeAwal));
        $yearMonth = "{$tahun}-{$bulan}";

        // ======================================
        // STEP 1: GET SALDO AWAL dari tabel saldo_awal
        // ======================================
        $saldoAwalDb = DB::table('saldo_awal')
            ->where('periode', $periodeAwal)
            ->select('kode_akun', 'debet', 'kredit')
            ->get()
            ->keyBy('kode_akun');

        $saldoAwal = [];
        foreach ($saldoAwalDb as $kode => $sa) {
            $saldoAwal[$kode] = [
                'debet' => $sa->debet,
                'kredit' => $sa->kredit
            ];
        }

        // ======================================
        // STEP 2: KALKULASI MUTASI DARI TRANSAKSI
        // ======================================
        $mutasi = [];

        // A. DARI TRANSAKSI (PENERIMAAN, PENGELUARAN, RUPA-RUPA)
        $transaksiData = DB::select("
            SELECT 
                dt.kode_akun,
                SUM(dt.debet) as total_debet,
                SUM(dt.kredit) as total_kredit
            FROM detail_transaksi dt
            JOIN transaksi t ON dt.transaksi_id = t.id
            WHERE t.tanggal BETWEEN ? AND ?
            GROUP BY dt.kode_akun
        ", [$periodeAwal, $periodeAkhir]);

        foreach ($transaksiData as $row) {
            $kode = $row->kode_akun;

            // Gabungkan KAS & BANK jadi kode 100
            if ($kode == '100' || $kode == '101') {
                $kode = '100';
            }

            if (!isset($mutasi[$kode])) {
                $mutasi[$kode] = ['debet' => 0, 'kredit' => 0];
            }

            $mutasi[$kode]['debet'] += $row->total_debet;
            $mutasi[$kode]['kredit'] += $row->total_kredit;
        }

        // B. DARI PIUTANG (Ikhtisar Jurnal Penjualan Kredit)
        $piutangData = DB::select("
            SELECT 
                mp.kode_akun_default,
                tp.mutasi_debet,
                tp.mutasi_kredit
            FROM transaksi_piutang tp
            JOIN master_piutang mp ON tp.master_piutang_id = mp.id
            WHERE tp.periode = ?
        ", [$periodeAwal]);

        $totalPenjualanKredit = 0;
        $penjualanKreditPerAkun = [];

        foreach ($piutangData as $row) {
            if ($row->mutasi_debet > 0) {
                $totalPenjualanKredit += $row->mutasi_debet;

                $kodePendapatan = $row->kode_akun_default;
                if (!isset($penjualanKreditPerAkun[$kodePendapatan])) {
                    $penjualanKreditPerAkun[$kodePendapatan] = 0;
                }
                $penjualanKreditPerAkun[$kodePendapatan] += $row->mutasi_debet;
            }
        }

        // Tambahkan mutasi dari penjualan kredit
        if ($totalPenjualanKredit > 0) {
            // Tambah debet piutang (120)
            if (!isset($mutasi['120'])) {
                $mutasi['120'] = ['debet' => 0, 'kredit' => 0];
            }
            $mutasi['120']['debet'] += $totalPenjualanKredit;

            // Tambah kredit pendapatan
            foreach ($penjualanKreditPerAkun as $kodePendapatan => $nilai) {
                if (!isset($mutasi[$kodePendapatan])) {
                    $mutasi[$kodePendapatan] = ['debet' => 0, 'kredit' => 0];
                }
                $mutasi[$kodePendapatan]['kredit'] += $nilai;
            }
        }

        // ======================================
        // STEP 3: GET ALL KODE AKUN AND CALCULATE SALDO AKHIR
        // NOTE: Akun 900 tidak dimasukkan karena hanya muncul di Neraca, bukan Neraca Mutasi
        // ======================================
        $kodeAkunList = KodeAkun::orderBy('kode_akun')->get();

        $neracaMutasi = [];
        $totals = [
            'saldo_awal_debet' => 0,
            'saldo_awal_kredit' => 0,
            'mutasi_debet' => 0,
            'mutasi_kredit' => 0,
            'saldo_akhir_debet' => 0,
            'saldo_akhir_kredit' => 0
        ];

        foreach ($kodeAkunList as $akun) {
            $kode = $akun->kode_akun;
            $nama = $akun->nama_akun;
            $tipe = $akun->tipe_akun;

            // Skip kode 101 (BANK) karena sudah digabung dengan 100
            if ($kode == '101') {
                continue;
            }

            // SKIP AKUN 900 di Neraca Mutasi (SHU tidak ditampilkan di Neraca Mutasi)
            // SHU hanya muncul di NERACA, bukan di Neraca Mutasi
            if ($kode == '900') {
                continue;
            }

            // Untuk kode 100, ubah namanya jadi "Kas Bank"
            if ($kode == '100') {
                $nama = 'Kas Bank';
            }

            // Get saldo awal
            $saldoAwalDebet = $saldoAwal[$kode]['debet'] ?? 0;
            $saldoAwalKredit = $saldoAwal[$kode]['kredit'] ?? 0;

            // Gabungkan saldo awal bank ke kas
            if ($kode == '100') {
                $saldoAwalDebet += $saldoAwal['101']['debet'] ?? 0;
                $saldoAwalKredit += $saldoAwal['101']['kredit'] ?? 0;
            }

            // Get mutasi
            $mutasiDebet = $mutasi[$kode]['debet'] ?? 0;
            $mutasiKredit = $mutasi[$kode]['kredit'] ?? 0;

            // Kalkulasi saldo akhir
            $saldoAkhirDebet = 0;
            $saldoAkhirKredit = 0;

            if ($kode == '210') {
                // Akumulasi Penyusutan: Normal di Kredit (kontra-aktiva)
                $saldoAkhirKredit = $saldoAwalKredit - $mutasiDebet + $mutasiKredit;
                if ($saldoAkhirKredit < 0) {
                    $saldoAkhirDebet = abs($saldoAkhirKredit);
                    $saldoAkhirKredit = 0;
                }
            } elseif ($tipe == 'aktiva' || $tipe == 'biaya') {
                // Aktiva & Biaya: Normal di Debet
                if ($saldoAwalKredit > 0 && $saldoAwalDebet == 0) {
                    $saldoAkhirKredit = $saldoAwalKredit - $mutasiDebet + $mutasiKredit;
                    if ($saldoAkhirKredit < 0) {
                        $saldoAkhirDebet = abs($saldoAkhirKredit);
                        $saldoAkhirKredit = 0;
                    }
                } else {
                    $saldoAkhirDebet = $saldoAwalDebet + $mutasiDebet - $mutasiKredit;
                    if ($saldoAkhirDebet < 0) {
                        $saldoAkhirKredit = abs($saldoAkhirDebet);
                        $saldoAkhirDebet = 0;
                    }
                }
            } else {
                // Passiva & Pendapatan: Normal di Kredit
                if ($saldoAwalDebet > 0 && $saldoAwalKredit == 0) {
                    $saldoAkhirDebet = $saldoAwalDebet + $mutasiDebet - $mutasiKredit;
                    if ($saldoAkhirDebet < 0) {
                        $saldoAkhirKredit = abs($saldoAkhirDebet);
                        $saldoAkhirDebet = 0;
                    }
                } else {
                    $saldoAkhirKredit = $saldoAwalKredit - $mutasiDebet + $mutasiKredit;
                    if ($saldoAkhirKredit < 0) {
                        $saldoAkhirDebet = abs($saldoAkhirKredit);
                        $saldoAkhirKredit = 0;
                    }
                }
            }

            // Hanya tampilkan yang memiliki saldo atau mutasi
            if ($saldoAwalDebet > 0 || $saldoAwalKredit > 0 ||
                $mutasiDebet > 0 || $mutasiKredit > 0 ||
                $saldoAkhirDebet > 0 || $saldoAkhirKredit > 0) {

                $neracaMutasi[] = (object)[
                    'kode_akun' => $kode,
                    'nama_akun' => $nama,
                    'tipe_akun' => $tipe,
                    'saldo_awal_debet' => $saldoAwalDebet,
                    'saldo_awal_kredit' => $saldoAwalKredit,
                    'mutasi_debet' => $mutasiDebet,
                    'mutasi_kredit' => $mutasiKredit,
                    'saldo_akhir_debet' => $saldoAkhirDebet,
                    'saldo_akhir_kredit' => $saldoAkhirKredit
                ];

                $totals['saldo_awal_debet'] += $saldoAwalDebet;
                $totals['saldo_awal_kredit'] += $saldoAwalKredit;
                $totals['mutasi_debet'] += $mutasiDebet;
                $totals['mutasi_kredit'] += $mutasiKredit;
                $totals['saldo_akhir_debet'] += $saldoAkhirDebet;
                $totals['saldo_akhir_kredit'] += $saldoAkhirKredit;
            }
        }

        return view('neraca.mutasi', [
            'bulan' => $bulan,
            'tahun' => $tahun,
            'bulanNama' => $this->bulanNama,
            'periodeDisplay' => $this->bulanNama[$bulan] . ' ' . $tahun,
            'neracaMutasi' => $neracaMutasi,
            'totals' => $totals,
        ]);
    }
}
