<?php

namespace App\Http\Controllers;

use App\Models\KodeAkun;
use App\Models\AnggaranLabaRugi;
use App\Models\SaldoAwal;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LabaRugiController extends Controller
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
     * Display laba rugi page
     */
    public function index(Request $request)
    {
        $bulan = $request->get('bulan', date('m'));
        $tahun = $request->get('tahun', date('Y'));
        $periode = "{$tahun}-{$bulan}-01";
        $showLabaRugi = $request->has('generate') || ($request->has('bulan') && $request->has('tahun'));

        $data = [
            'bulan' => $bulan,
            'tahun' => $tahun,
            'bulanNama' => $this->bulanNama,
            'showLabaRugi' => $showLabaRugi,
        ];

        if ($showLabaRugi) {
            $labaRugiData = $this->generateLabaRugi($periode, $bulan, $tahun);
            $data = array_merge($data, $labaRugiData);

            // Update SHU tahun berjalan (kode 900)
            $this->updateSHU($periode, $labaRugiData['labaRugiBersih']);
        }

        return view('laba-rugi.index', $data);
    }

    /**
     * Generate Laba Rugi data
     */
    private function generateLabaRugi($periode, $bulan, $tahun)
    {
        $yearMonth = date('Y-m', strtotime($periode));

        // JUMLAH 1: PENDAPATAN (700, 703)
        $pendapatan = $this->getAccountData(['700', '703'], $periode, $yearMonth, 'pendapatan');
        $jumlah1 = $this->sumColumn($pendapatan, 'sd_bulan_ini');

        // JUMLAH 2: PENDAPATAN USAHA DILUAR ANGGOTA (711-719, 725)
        $pendapatanUsaha = $this->getAccountRangeData('711', '719', $periode, $yearMonth, 'pendapatan', '725');
        $jumlah2 = $this->sumColumn($pendapatanUsaha, 'sd_bulan_ini');

        // JUMLAH 3: BIAYA DENGAN ANGGOTA (800, 803)
        $biayaDenganAnggota = $this->getAccountData(['800', '803'], $periode, $yearMonth, 'biaya');
        $jumlah3 = $this->sumColumn($biayaDenganAnggota, 'sd_bulan_ini');

        // JUMLAH 4: BIAYA-BIAYA DILUAR ANGGOTA (811-818)
        $biayaDiluarAnggota = $this->getAccountRangeData('811', '818', $periode, $yearMonth, 'biaya');
        $jumlah4 = $this->sumColumn($biayaDiluarAnggota, 'sd_bulan_ini');

        // JUMLAH 5: BIAYA ORGANISASI DAN MANAJEMEN (820-846)
        $biayaOrganisasi = $this->getAccountRangeData('820', '846', $periode, $yearMonth, 'biaya');
        $jumlah5 = $this->sumColumn($biayaOrganisasi, 'sd_bulan_ini');

        // Calculate Laba Rugi
        $jumlahPendapatan = $jumlah1 + $jumlah2;
        $jumlahBiaya = $jumlah3 + $jumlah4 + $jumlah5;
        $labaRugi1_3 = $jumlah1 - $jumlah3;
        $labaRugi2_4 = $jumlah2 - $jumlah4;
        $labaRugiBersih = $jumlahPendapatan - $jumlahBiaya;

        return [
            'pendapatan' => $pendapatan,
            'pendapatanUsaha' => $pendapatanUsaha,
            'biayaDenganAnggota' => $biayaDenganAnggota,
            'biayaDiluarAnggota' => $biayaDiluarAnggota,
            'biayaOrganisasi' => $biayaOrganisasi,
            'jumlah1' => $jumlah1,
            'jumlah2' => $jumlah2,
            'jumlah3' => $jumlah3,
            'jumlah4' => $jumlah4,
            'jumlah5' => $jumlah5,
            'jumlahPendapatan' => $jumlahPendapatan,
            'jumlahBiaya' => $jumlahBiaya,
            'labaRugi1_3' => $labaRugi1_3,
            'labaRugi2_4' => $labaRugi2_4,
            'labaRugiBersih' => $labaRugiBersih,
            'allAccounts' => array_merge(
                $pendapatan->toArray(),
                $pendapatanUsaha->toArray(),
                $biayaDenganAnggota->toArray(),
                $biayaDiluarAnggota->toArray(),
                $biayaOrganisasi->toArray()
            ),
        ];
    }

    /**
     * Get account data for specific codes
     */
    private function getAccountData($codes, $periode, $yearMonth, $type)
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

        return $this->calculateMutasi($data, $yearMonth, $periode, $type);
    }

    /**
     * Get account data for code range
     */
    private function getAccountRangeData($startCode, $endCode, $periode, $yearMonth, $type, $additionalCode = null)
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

        return $this->calculateMutasi($query, $yearMonth, $periode, $type);
    }

    /**
     * Calculate mutasi for each account
     */
    private function calculateMutasi($data, $yearMonth, $periode, $type)
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
        });
    }

    /**
     * Sum column values
     */
    private function sumColumn($data, $column)
    {
        return $data->sum($column);
    }

    /**
     * Update SHU tahun berjalan (kode 900)
     */
    private function updateSHU($periode, $labaRugiBersih)
    {
        if ($labaRugiBersih != 0) {
            try {
                $saldoAkhirDebet = $labaRugiBersih < 0 ? abs($labaRugiBersih) : 0;
                $saldoAkhirKredit = $labaRugiBersih > 0 ? $labaRugiBersih : 0;

                DB::table('saldo_awal')->updateOrInsert(
                    ['periode' => $periode, 'kode_akun' => '900'],
                    [
                        'debet' => 0,
                        'kredit' => 0,
                        'saldo_akhir_debet' => $saldoAkhirDebet,
                        'saldo_akhir_kredit' => $saldoAkhirKredit,
                        'updated_at' => now(),
                    ]
                );
            } catch (\Exception $e) {
                // Handle error silently
            }
        }
    }

    /**
     * Store anggaran laba rugi
     */
    public function storeAnggaran(Request $request)
    {
        $request->validate([
            'periode' => 'required|date',
            'anggaran_tahun' => 'required|array',
            'anggaran_triwulan' => 'required|array',
            'realisasi_bulan_lalu' => 'required|array',
        ]);

        try {
            DB::beginTransaction();

            $periode = $request->periode;
            $redirectUrl = $request->redirect_url ?? route('laba-rugi.index');

            foreach ($request->anggaran_tahun as $kodeAkun => $anggaranTahun) {
                DB::table('anggaran_laba_rugi')->updateOrInsert(
                    ['periode' => $periode, 'kode_akun' => $kodeAkun],
                    [
                        'anggaran_tahun' => floatval($anggaranTahun ?? 0),
                        'anggaran_triwulan' => floatval($request->anggaran_triwulan[$kodeAkun] ?? 0),
                        'realisasi_bulan_lalu' => floatval($request->realisasi_bulan_lalu[$kodeAkun] ?? 0),
                        'updated_at' => now(),
                    ]
                );
            }

            DB::commit();

            return redirect($redirectUrl)->with('success', 'Anggaran berhasil disimpan!');

        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->with('error', 'Gagal menyimpan anggaran: ' . $e->getMessage());
        }
    }
}
