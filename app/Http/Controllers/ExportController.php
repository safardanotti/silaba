<?php

namespace App\Http\Controllers;

use App\Models\Transaksi;
use App\Models\DetailTransaksi;
use App\Models\NeracaPosting;
use App\Models\KasBank;
use App\Models\SaldoAwal;
use App\Models\KodeAkun;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ExportController extends Controller
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
     * Export Penerimaan ke Excel
     */
    public function penerimaan(Request $request)
    {
        $periode = $request->get('periode', date('Y-m'));
        
        // Get transactions with details
        $data = DB::table('transaksi as t')
            ->join('detail_transaksi as dt', 't.id', '=', 'dt.transaksi_id')
            ->join('kode_akun as ka', 'dt.kode_akun', '=', 'ka.kode_akun')
            ->where('t.jenis_transaksi', 'penerimaan')
            ->whereRaw("DATE_FORMAT(t.tanggal, '%Y-%m') = ?", [$periode])
            ->select([
                't.id as trans_id',
                't.tanggal',
                't.uraian_kegiatan',
                't.jenis_masuk',
                'dt.id as detail_id',
                'dt.kode_akun',
                'ka.nama_akun',
                'dt.debet',
                'dt.kredit'
            ])
            ->orderBy('t.tanggal')
            ->orderBy('t.id')
            ->orderBy('dt.id')
            ->get();

        // Group data by transaction
        $transactions = [];
        $totalDebet = 0;
        $totalKredit = 0;

        foreach ($data as $row) {
            $transId = $row->trans_id;
            if (!isset($transactions[$transId])) {
                $transactions[$transId] = [
                    'tanggal' => $row->tanggal,
                    'uraian_kegiatan' => $row->uraian_kegiatan,
                    'jenis_masuk' => $row->jenis_masuk,
                    'details' => []
                ];
            }
            $transactions[$transId]['details'][] = [
                'kode_akun' => $row->kode_akun,
                'nama_akun' => $row->nama_akun,
                'debet' => $row->debet,
                'kredit' => $row->kredit
            ];
            
            $totalDebet += $row->debet;
            $totalKredit += $row->kredit;
        }

        // Calculate KAS and BANK totals
        $kasBankTotals = DB::table('detail_transaksi as dt')
            ->join('transaksi as t', 'dt.transaksi_id', '=', 't.id')
            ->where('t.jenis_transaksi', 'penerimaan')
            ->whereRaw("DATE_FORMAT(t.tanggal, '%Y-%m') = ?", [$periode])
            ->whereIn('dt.kode_akun', ['100', '101'])
            ->select([
                DB::raw("SUM(CASE WHEN t.jenis_masuk = 'kas' AND dt.kode_akun = '100' THEN (dt.debet - dt.kredit) ELSE 0 END) as total_kas"),
                DB::raw("SUM(CASE WHEN t.jenis_masuk = 'bank' AND dt.kode_akun IN ('100', '101') THEN (dt.debet - dt.kredit) ELSE 0 END) as total_bank")
            ])
            ->first();

        $totalKas = $kasBankTotals->total_kas ?? 0;
        $totalBank = $kasBankTotals->total_bank ?? 0;

        // Format periode
        $bulan = substr($periode, 5, 2);
        $tahun = substr($periode, 0, 4);
        $periodeName = $this->bulanNama[$bulan] . ' ' . $tahun;

        // Set headers for Excel download
        return response()->view('export.penerimaan', compact(
            'transactions', 'totalDebet', 'totalKredit', 'totalKas', 'totalBank', 'periodeName', 'periode'
        ))->header('Content-Type', 'application/vnd.ms-excel')
          ->header('Content-Disposition', 'attachment;filename="PENERIMAAN_KASBANK_' . $periode . '.xls"')
          ->header('Cache-Control', 'max-age=0');
    }

    /**
     * Export Pengeluaran ke Excel
     */
    public function pengeluaran(Request $request)
    {
        $periode = $request->get('periode', date('Y-m'));
        
        // Get transactions with details
        $data = DB::table('transaksi as t')
            ->join('detail_transaksi as dt', 't.id', '=', 'dt.transaksi_id')
            ->join('kode_akun as ka', 'dt.kode_akun', '=', 'ka.kode_akun')
            ->where('t.jenis_transaksi', 'pengeluaran')
            ->whereRaw("DATE_FORMAT(t.tanggal, '%Y-%m') = ?", [$periode])
            ->select([
                't.id as trans_id',
                't.tanggal',
                't.uraian_kegiatan',
                't.jenis_masuk',
                'dt.id as detail_id',
                'dt.kode_akun',
                'ka.nama_akun',
                'dt.debet',
                'dt.kredit'
            ])
            ->orderBy('t.tanggal')
            ->orderBy('t.id')
            ->orderBy('dt.id')
            ->get();

        // Group data by transaction
        $transactions = [];
        $totalDebet = 0;
        $totalKredit = 0;

        foreach ($data as $row) {
            $transId = $row->trans_id;
            if (!isset($transactions[$transId])) {
                $transactions[$transId] = [
                    'tanggal' => $row->tanggal,
                    'uraian_kegiatan' => $row->uraian_kegiatan,
                    'jenis_masuk' => $row->jenis_masuk,
                    'details' => []
                ];
            }
            $transactions[$transId]['details'][] = [
                'kode_akun' => $row->kode_akun,
                'nama_akun' => $row->nama_akun,
                'debet' => $row->debet,
                'kredit' => $row->kredit
            ];
            
            $totalDebet += $row->debet;
            $totalKredit += $row->kredit;
        }

        // Calculate KAS and BANK totals
        $kasBankTotals = DB::table('detail_transaksi as dt')
            ->join('transaksi as t', 'dt.transaksi_id', '=', 't.id')
            ->where('t.jenis_transaksi', 'pengeluaran')
            ->whereRaw("DATE_FORMAT(t.tanggal, '%Y-%m') = ?", [$periode])
            ->whereIn('dt.kode_akun', ['100', '101'])
            ->select([
                DB::raw("SUM(CASE WHEN t.jenis_masuk = 'kas' AND dt.kode_akun = '100' THEN (dt.kredit - dt.debet) ELSE 0 END) as total_kas"),
                DB::raw("SUM(CASE WHEN t.jenis_masuk = 'bank' AND dt.kode_akun IN ('100', '101') THEN (dt.kredit - dt.debet) ELSE 0 END) as total_bank")
            ])
            ->first();

        $totalKas = $kasBankTotals->total_kas ?? 0;
        $totalBank = $kasBankTotals->total_bank ?? 0;

        // Format periode
        $bulan = substr($periode, 5, 2);
        $tahun = substr($periode, 0, 4);
        $periodeName = $this->bulanNama[$bulan] . ' ' . $tahun;

        // Set headers for Excel download
        return response()->view('export.pengeluaran', compact(
            'transactions', 'totalDebet', 'totalKredit', 'totalKas', 'totalBank', 'periodeName', 'periode'
        ))->header('Content-Type', 'application/vnd.ms-excel')
          ->header('Content-Disposition', 'attachment;filename="PENGELUARAN_KASBANK_' . $periode . '.xls"')
          ->header('Cache-Control', 'max-age=0');
    }

    /**
     * Export Rupa-rupa ke Excel
     */
    public function rupaRupa(Request $request)
    {
        $periode = $request->get('periode', date('Y-m'));
        
        // Get transactions with details
        $data = DB::table('transaksi as t')
            ->join('detail_transaksi as dt', 't.id', '=', 'dt.transaksi_id')
            ->join('kode_akun as ka', 'dt.kode_akun', '=', 'ka.kode_akun')
            ->where('t.jenis_transaksi', 'rupa_rupa')
            ->whereRaw("DATE_FORMAT(t.tanggal, '%Y-%m') = ?", [$periode])
            ->select([
                't.id as trans_id',
                't.tanggal',
                't.uraian_kegiatan',
                't.jenis_masuk',
                'dt.id as detail_id',
                'dt.kode_akun',
                'ka.nama_akun',
                'dt.debet',
                'dt.kredit'
            ])
            ->orderBy('t.tanggal')
            ->orderBy('t.id')
            ->orderBy('dt.id')
            ->get();

        // Group data by transaction
        $transactions = [];
        $totalDebet = 0;
        $totalKredit = 0;

        foreach ($data as $row) {
            $transId = $row->trans_id;
            if (!isset($transactions[$transId])) {
                $transactions[$transId] = [
                    'tanggal' => $row->tanggal,
                    'uraian_kegiatan' => $row->uraian_kegiatan,
                    'jenis_masuk' => $row->jenis_masuk,
                    'details' => []
                ];
            }
            $transactions[$transId]['details'][] = [
                'kode_akun' => $row->kode_akun,
                'nama_akun' => $row->nama_akun,
                'debet' => $row->debet,
                'kredit' => $row->kredit
            ];
            
            $totalDebet += $row->debet;
            $totalKredit += $row->kredit;
        }

        // Format periode
        $bulan = substr($periode, 5, 2);
        $tahun = substr($periode, 0, 4);
        $periodeName = $this->bulanNama[$bulan] . ' ' . $tahun;

        // Set headers for Excel download
        return response()->view('export.rupa-rupa', compact(
            'transactions', 'totalDebet', 'totalKredit', 'periodeName', 'periode'
        ))->header('Content-Type', 'application/vnd.ms-excel')
          ->header('Content-Disposition', 'attachment;filename="RUPARUPA_' . $periode . '.xls"')
          ->header('Cache-Control', 'max-age=0');
    }

    /**
     * Export Rekap Per Akun ke Excel
     */
    public function rekapAkun(Request $request)
    {
        $jenis = $request->get('jenis', 'semua');
        $periode = $request->get('periode', 'semua');

        // Build query
        $query = DB::table('detail_transaksi as dt')
            ->join('transaksi as t', 'dt.transaksi_id', '=', 't.id')
            ->join('kode_akun as ka', 'dt.kode_akun', '=', 'ka.kode_akun')
            ->whereNotIn('dt.kode_akun', ['100', '101'])
            ->select(
                'dt.kode_akun',
                'ka.nama_akun',
                'ka.tipe_akun',
                DB::raw('SUM(dt.debet) as total_debet'),
                DB::raw('SUM(dt.kredit) as total_kredit'),
                DB::raw('COUNT(DISTINCT t.id) as jumlah_transaksi')
            )
            ->groupBy('dt.kode_akun', 'ka.nama_akun', 'ka.tipe_akun')
            ->orderBy('dt.kode_akun');

        if ($jenis !== 'semua') {
            $query->where('t.jenis_transaksi', $jenis);
        }

        if ($periode !== 'semua') {
            $query->whereRaw("DATE_FORMAT(t.tanggal, '%Y-%m') = ?", [$periode]);
        }

        $data = $query->get();

        // Calculate grand totals
        $grandTotalDebet = $data->sum('total_debet');
        $grandTotalKredit = $data->sum('total_kredit');

        // Format periode name
        $periodeName = 'Semua Periode';
        if ($periode !== 'semua') {
            $bulan = substr($periode, 5, 2);
            $tahun = substr($periode, 0, 4);
            $periodeName = $this->bulanNama[$bulan] . ' ' . $tahun;
        }

        $jenisName = ucfirst($jenis);
        if ($jenis === 'rupa_rupa') $jenisName = 'Rupa-rupa';
        if ($jenis === 'semua') $jenisName = 'Semua Transaksi';

        // Set headers for Excel download
        $filename = 'REKAP_AKUN_' . strtoupper(str_replace('-', '_', $jenis)) . '_' . ($periode !== 'semua' ? $periode : 'ALL') . '.xls';
        
        return response()->view('export.rekap-akun', compact(
            'data', 'grandTotalDebet', 'grandTotalKredit', 'periodeName', 'jenisName'
        ))->header('Content-Type', 'application/vnd.ms-excel')
          ->header('Content-Disposition', 'attachment;filename="' . $filename . '"')
          ->header('Cache-Control', 'max-age=0');
    }

    /**
     * Export Laba Rugi ke Excel
     */
    public function labaRugi(Request $request)
    {
        $bulan = $request->get('bulan', date('m'));
        $tahun = $request->get('tahun', date('Y'));
        $periode = "{$tahun}-{$bulan}-01";
        $yearMonth = date('Y-m', strtotime($periode));

        // JUMLAH 1: PENDAPATAN (700, 703)
        $pendapatan = $this->getLabaRugiAccountData(['700', '703'], $periode, $yearMonth, 'pendapatan');
        $jumlah1 = collect($pendapatan)->sum('sd_bulan_ini');

        // JUMLAH 2: PENDAPATAN USAHA DILUAR ANGGOTA (711-719, 725)
        $pendapatanUsaha = $this->getLabaRugiAccountRangeData('711', '719', $periode, $yearMonth, 'pendapatan', '725');
        $jumlah2 = collect($pendapatanUsaha)->sum('sd_bulan_ini');

        // JUMLAH 3: BIAYA DENGAN ANGGOTA (800, 803)
        $biayaDenganAnggota = $this->getLabaRugiAccountData(['800', '803'], $periode, $yearMonth, 'biaya');
        $jumlah3 = collect($biayaDenganAnggota)->sum('sd_bulan_ini');

        // JUMLAH 4: BIAYA-BIAYA DILUAR ANGGOTA (811-818)
        $biayaDiluarAnggota = $this->getLabaRugiAccountRangeData('811', '818', $periode, $yearMonth, 'biaya');
        $jumlah4 = collect($biayaDiluarAnggota)->sum('sd_bulan_ini');

        // JUMLAH 5: BIAYA ORGANISASI DAN MANAJEMEN (820-846)
        $biayaOrganisasi = $this->getLabaRugiAccountRangeData('820', '846', $periode, $yearMonth, 'biaya');
        $jumlah5 = collect($biayaOrganisasi)->sum('sd_bulan_ini');

        // Calculate Laba Rugi
        $jumlahPendapatan = $jumlah1 + $jumlah2;
        $jumlahBiaya = $jumlah3 + $jumlah4 + $jumlah5;
        $labaRugi1_3 = $jumlah1 - $jumlah3;
        $labaRugi2_4 = $jumlah2 - $jumlah4;
        $labaRugiBersih = $jumlahPendapatan - $jumlahBiaya;

        $periodeName = $this->bulanNama[$bulan];

        // Set headers for Excel download
        return response()->view('export.laba-rugi', compact(
            'pendapatan', 'pendapatanUsaha', 'biayaDenganAnggota', 
            'biayaDiluarAnggota', 'biayaOrganisasi',
            'jumlah1', 'jumlah2', 'jumlah3', 'jumlah4', 'jumlah5',
            'jumlahPendapatan', 'jumlahBiaya',
            'labaRugi1_3', 'labaRugi2_4', 'labaRugiBersih',
            'periodeName', 'tahun'
        ))->header('Content-Type', 'application/vnd.ms-excel')
          ->header('Content-Disposition', 'attachment;filename="LABA_RUGI_' . $periodeName . '_' . $tahun . '.xls"')
          ->header('Cache-Control', 'max-age=0');
    }

    /**
     * Export Neraca ke Excel
     */
    public function neraca(Request $request)
    {
        $bulan = $request->get('bulan', date('m'));
        $tahun = $request->get('tahun', date('Y'));
        $periode = "{$tahun}-{$bulan}-01";
        $yearMonth = "{$tahun}-{$bulan}";

        // Get neraca data
        $neracaData = $this->generateNeracaData($periode, $yearMonth);
        $organized = $this->organizeNeracaData($neracaData);

        $periodeName = $this->bulanNama[$bulan];

        return response()->view('export.neraca', array_merge([
            'periodeName' => $periodeName,
            'tahun' => $tahun,
            'bulanNama' => $this->bulanNama,
        ], $organized))
        ->header('Content-Type', 'application/vnd.ms-excel')
        ->header('Content-Disposition', 'attachment;filename="NERACA_' . $periodeName . '_' . $tahun . '.xls"')
        ->header('Cache-Control', 'max-age=0');
    }

    /**
     * Export Neraca Mutasi ke Excel
     * FIXED: Sekarang memperhitungkan mutasi dari piutang (penjualan kredit)
     */
    public function neracaMutasi(Request $request)
    {
        $bulan = $request->get('bulan', date('m'));
        $tahun = $request->get('tahun', date('Y'));
        $periodeAwal = "{$tahun}-{$bulan}-01";
        $periodeAkhir = date('Y-m-t', strtotime($periodeAwal));
        $yearMonth = "{$tahun}-{$bulan}";

        // Generate neraca mutasi data - SAMA PERSIS DENGAN NeracaController
        $mutasiData = $this->generateNeracaMutasiData($periodeAwal, $periodeAkhir, $bulan, $tahun, $yearMonth);

        $periodeName = $this->bulanNama[$bulan];

        return response()->view('export.neraca-mutasi', [
            'periodeName' => $periodeName,
            'tahun' => $tahun,
            'neracaMutasi' => $mutasiData['neracaMutasi'],
            'totals' => $mutasiData['totals'],
        ])
        ->header('Content-Type', 'application/vnd.ms-excel')
        ->header('Content-Disposition', 'attachment;filename="NERACA_MUTASI_' . $periodeName . '_' . $tahun . '.xls"')
        ->header('Cache-Control', 'max-age=0');
    }

    /**
     * Generate Neraca Mutasi data for export
     * FIXED: Sekarang SAMA PERSIS dengan NeracaController::mutasi()
     */
    private function generateNeracaMutasiData($periodeAwal, $periodeAkhir, $bulan, $tahun, $yearMonth)
    {
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
        // STEP 3: HITUNG SHU TAHUN BERJALAN (900)
        // ======================================
        $shuTahunBerjalan = $this->calculateSHUFromLabaRugi($periodeAwal, $yearMonth);

        // ======================================
        // STEP 4: GET ALL KODE AKUN AND CALCULATE SALDO AKHIR
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

            // Khusus untuk SHU Tahun Berjalan (900) - dari perhitungan Laba Rugi
            if ($kode == '900') {
                if ($shuTahunBerjalan > 0) {
                    $saldoAkhirKredit = $shuTahunBerjalan;
                } else {
                    $saldoAkhirDebet = abs($shuTahunBerjalan);
                }
            } elseif ($kode == '210') {
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

                $neracaMutasi[] = [
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

        return [
            'neracaMutasi' => $neracaMutasi,
            'totals' => $totals,
        ];
    }

    /**
     * Calculate SHU from Laba Rugi methodology
     * SAMA PERSIS dengan NeracaController
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
     * Export Approved Neraca ke Excel
     */
    public function neracaApproved($id)
    {
        $posting = NeracaPosting::with('details')->findOrFail($id);
        
        if ($posting->status !== 'approved') {
            return redirect()->back()->with('error', 'Neraca belum disetujui');
        }

        $organized = $this->organizePostingData($posting->details);

        $periode = \Carbon\Carbon::parse($posting->periode);
        $bulan = $periode->format('m');
        $tahun = $periode->format('Y');
        $periodeName = $this->bulanNama[$bulan];

        return response()->view('export.neraca', array_merge([
            'periodeName' => $periodeName,
            'tahun' => $tahun,
            'bulanNama' => $this->bulanNama,
        ], $organized))
        ->header('Content-Type', 'application/vnd.ms-excel')
        ->header('Content-Disposition', 'attachment;filename="NERACA_APPROVED_' . $periodeName . '_' . $tahun . '.xls"')
        ->header('Cache-Control', 'max-age=0');
    }

    /**
     * Generate Neraca data
     */
    private function generateNeracaData($periode, $yearMonth)
    {
        // Get saldo akhir from saldo_awal table
        $neracaData = DB::table('kode_akun as ka')
            ->leftJoin('saldo_awal as sa', function ($join) use ($periode) {
                $join->on('ka.kode_akun', '=', 'sa.kode_akun')
                    ->where('sa.periode', '=', $periode);
            })
            ->whereNotBetween('ka.kode_akun', ['700', '899'])
            ->select(
                'ka.kode_akun',
                'ka.nama_akun',
                'ka.tipe_akun',
                DB::raw('COALESCE(sa.saldo_akhir_debet, 0) as saldo_debet'),
                DB::raw('COALESCE(sa.saldo_akhir_kredit, 0) as saldo_kredit')
            )
            ->orderBy('ka.kode_akun')
            ->get()
            ->toArray();

        // Get Kas & Bank
        $kasBank = KasBank::where('periode', $periode)
            ->selectRaw("
                SUM(CASE WHEN jenis = 'kas' THEN saldo_akhir ELSE 0 END) as kas,
                SUM(CASE WHEN jenis = 'bank' THEN saldo_akhir ELSE 0 END) as bank
            ")
            ->first();

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

        // Calculate SHU using the same method as NeracaController
        $shuTahunBerjalan = $this->calculateSHUFromLabaRugi($periode, $yearMonth);
        $neracaData[] = (object) [
            'kode_akun' => '900',
            'nama_akun' => 'SHU TAHUN BERJALAN',
            'tipe_akun' => 'passiva',
            'saldo_debet' => $shuTahunBerjalan < 0 ? abs($shuTahunBerjalan) : 0,
            'saldo_kredit' => $shuTahunBerjalan > 0 ? $shuTahunBerjalan : 0
        ];

        return $neracaData;
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
            } elseif (in_array($item->kode_akun, ['300', '310', '320', '370'])) {
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

        return compact(
            'aktiva', 'passiva',
            'totalAktivaLancar', 'totalAktivaTetap', 'totalAktiva',
            'totalHutang', 'totalDana', 'totalModal', 'totalSHU', 'totalPassiva'
        );
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
            } elseif (in_array($item->kode_akun, ['300', '310', '320', '370'])) {
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

        return compact(
            'aktiva', 'passiva',
            'totalAktivaLancar', 'totalAktivaTetap', 'totalAktiva',
            'totalHutang', 'totalDana', 'totalModal', 'totalSHU', 'totalPassiva'
        );
    }

    /**
     * Get account data for specific codes (Laba Rugi)
     * FIXED: Sekarang memperhitungkan piutang
     */
    private function getLabaRugiAccountData($codes, $periode, $yearMonth, $type)
    {
        $data = DB::table('kode_akun as ka')
            ->leftJoin('anggaran_laba_rugi as alr', function ($join) use ($periode) {
                $join->on('ka.kode_akun', '=', 'alr.kode_akun')
                    ->where('alr.periode', '=', $periode);
            })
            ->whereIn('ka.kode_akun', $codes)
            ->select(
                'ka.kode_akun',
                'ka.nama_akun',
                DB::raw('COALESCE(alr.anggaran_tahun, 0) as anggaran_tahun'),
                DB::raw('COALESCE(alr.anggaran_triwulan, 0) as anggaran_triwulan'),
                DB::raw('COALESCE(alr.realisasi_bulan_lalu, 0) as realisasi_bulan_lalu')
            )
            ->orderBy('ka.kode_akun')
            ->get();

        return $this->calculateLabaRugiMutasi($data, $yearMonth, $periode, $type);
    }

    /**
     * Get account data for code range (Laba Rugi)
     * FIXED: Sekarang memperhitungkan piutang
     */
    private function getLabaRugiAccountRangeData($startCode, $endCode, $periode, $yearMonth, $type, $additionalCode = null)
    {
        $query = DB::table('kode_akun as ka')
            ->leftJoin('anggaran_laba_rugi as alr', function ($join) use ($periode) {
                $join->on('ka.kode_akun', '=', 'alr.kode_akun')
                    ->where('alr.periode', '=', $periode);
            })
            ->where(function ($q) use ($startCode, $endCode, $additionalCode) {
                $q->whereBetween('ka.kode_akun', [$startCode, $endCode]);
                if ($additionalCode) {
                    $q->orWhere('ka.kode_akun', $additionalCode);
                }
            })
            ->select(
                'ka.kode_akun',
                'ka.nama_akun',
                DB::raw('COALESCE(alr.anggaran_tahun, 0) as anggaran_tahun'),
                DB::raw('COALESCE(alr.anggaran_triwulan, 0) as anggaran_triwulan'),
                DB::raw('COALESCE(alr.realisasi_bulan_lalu, 0) as realisasi_bulan_lalu')
            )
            ->orderBy('ka.kode_akun')
            ->get();

        return $this->calculateLabaRugiMutasi($query, $yearMonth, $periode, $type);
    }

    /**
     * Calculate mutasi for laba rugi accounts
     * FIXED: Sekarang memperhitungkan piutang untuk pendapatan
     */
    private function calculateLabaRugiMutasi($data, $yearMonth, $periode, $type)
    {
        return $data->map(function ($row) use ($yearMonth, $periode, $type) {
            $kode = $row->kode_akun;
            $mutasiDebet = 0;
            $mutasiKredit = 0;

            // 1. From regular transactions
            $transaksi = DB::table('detail_transaksi as dt')
                ->join('transaksi as t', 'dt.transaksi_id', '=', 't.id')
                ->where('dt.kode_akun', $kode)
                ->whereRaw("DATE_FORMAT(t.tanggal, '%Y-%m') = ?", [$yearMonth])
                ->select(
                    DB::raw('COALESCE(SUM(dt.debet), 0) as total_debet'),
                    DB::raw('COALESCE(SUM(dt.kredit), 0) as total_kredit')
                )
                ->first();

            $mutasiDebet += $transaksi->total_debet ?? 0;
            $mutasiKredit += $transaksi->total_kredit ?? 0;

            // 2. From piutang (credit sales) - only for pendapatan
            if ($type === 'pendapatan') {
                $piutang = DB::table('transaksi_piutang as tp')
                    ->join('master_piutang as mp', 'tp.master_piutang_id', '=', 'mp.id')
                    ->where('mp.kode_akun_default', $kode)
                    ->where('tp.periode', $periode)
                    ->select(DB::raw('COALESCE(SUM(tp.mutasi_debet), 0) as total_penjualan_kredit'))
                    ->first();

                if ($piutang && $piutang->total_penjualan_kredit > 0) {
                    $mutasiKredit += $piutang->total_penjualan_kredit;
                }
            }

            // Calculate mutasi bulan ini based on type
            if ($type === 'pendapatan') {
                $row->mutasi_bulan_ini = $mutasiKredit - $mutasiDebet;
            } else {
                $row->mutasi_bulan_ini = $mutasiDebet - $mutasiKredit;
            }

            $row->sd_bulan_ini = $row->realisasi_bulan_lalu + $row->mutasi_bulan_ini;

            return $row;
        })->toArray();
    }
}
