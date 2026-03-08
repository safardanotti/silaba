<?php

namespace App\Http\Controllers;

use App\Models\Transaksi;
use App\Models\KodeAkun;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class IkhtisarController extends Controller
{
    /**
     * Display ikhtisar jurnal page
     */
    public function jurnal(Request $request)
    {
        $bulan = $request->get('bulan', date('m'));
        $tahun = $request->get('tahun', date('Y'));

        $bulanNama = [
            '01' => 'JANUARI', '02' => 'FEBRUARI', '03' => 'MARET', '04' => 'APRIL',
            '05' => 'MEI', '06' => 'JUNI', '07' => 'JULI', '08' => 'AGUSTUS',
            '09' => 'SEPTEMBER', '10' => 'OKTOBER', '11' => 'NOVEMBER', '12' => 'DESEMBER'
        ];

        $periodeName = $bulanNama[$bulan] . ' ' . $tahun;

        // Query untuk penerimaan
        $penerimaan = $this->getIkhtisarByJenis('penerimaan', $tahun, $bulan);
        $totalsPenerimaan = $this->calculateTotals($penerimaan);

        // Query untuk pengeluaran
        $pengeluaran = $this->getIkhtisarByJenis('pengeluaran', $tahun, $bulan);
        $totalsPengeluaran = $this->calculateTotals($pengeluaran);

        // Query untuk rupa-rupa
        $rupaRupa = $this->getIkhtisarByJenis('rupa_rupa', $tahun, $bulan);
        $totalsRupaRupa = $this->calculateTotals($rupaRupa);

        // Query untuk penjualan kredit (dari tabel piutang) - FIXED!
        $penjualanKredit = $this->getPenjualanKredit($tahun, $bulan);
        $totalsPenjualanKredit = $this->calculateTotals($penjualanKredit);

        return view('ikhtisar.jurnal', compact(
            'bulan', 'tahun', 'bulanNama', 'periodeName',
            'penerimaan', 'totalsPenerimaan',
            'pengeluaran', 'totalsPengeluaran',
            'rupaRupa', 'totalsRupaRupa',
            'penjualanKredit', 'totalsPenjualanKredit'
        ));
    }

    /**
     * Get ikhtisar by jenis transaksi
     */
    private function getIkhtisarByJenis($jenis, $tahun, $bulan)
    {
        return DB::table('detail_transaksi as dt')
            ->join('transaksi as t', 'dt.transaksi_id', '=', 't.id')
            ->join('kode_akun as ka', 'dt.kode_akun', '=', 'ka.kode_akun')
            ->where('t.jenis_transaksi', $jenis)
            ->whereYear('t.tanggal', $tahun)
            ->whereMonth('t.tanggal', $bulan)
            ->select(
                'dt.kode_akun',
                'ka.nama_akun',
                DB::raw('COALESCE(SUM(dt.debet), 0) as total_debet'),
                DB::raw('COALESCE(SUM(dt.kredit), 0) as total_kredit'),
                DB::raw('COALESCE(SUM(dt.debet), 0) - COALESCE(SUM(dt.kredit), 0) as mutasi')
            )
            ->groupBy('dt.kode_akun', 'ka.nama_akun')
            ->orderBy('dt.kode_akun')
            ->get();
    }

    /**
     * Get penjualan kredit from piutang table
     * FIXED: Menggunakan UNION ALL seperti PHP Native untuk mendapatkan:
     * 1. Piutang (120) di DEBET
     * 2. Akun Pendapatan (700, 711, 712, dst) di KREDIT
     */
    private function getPenjualanKredit($tahun, $bulan)
    {
        // Check if tables exist
        if (!DB::getSchemaBuilder()->hasTable('transaksi_piutang') || 
            !DB::getSchemaBuilder()->hasTable('master_piutang')) {
            return collect([]);
        }

        $periode = "{$tahun}-{$bulan}-01";

        try {
            // Query UNION ALL sama persis dengan PHP Native
            $sql = "
                SELECT 
                    '120' as kode_akun,
                    'Piutang' as nama_akun,
                    SUM(tp.mutasi_debet) as total_debet,
                    0 as total_kredit
                FROM transaksi_piutang tp
                WHERE tp.periode = ? AND tp.mutasi_debet > 0
                
                UNION ALL
                
                SELECT 
                    mp.kode_akun_default as kode_akun,
                    ka.nama_akun,
                    0 as total_debet,
                    SUM(tp.mutasi_debet) as total_kredit
                FROM transaksi_piutang tp
                JOIN master_piutang mp ON tp.master_piutang_id = mp.id
                LEFT JOIN kode_akun ka ON mp.kode_akun_default = ka.kode_akun
                WHERE tp.periode = ? AND tp.mutasi_debet > 0
                GROUP BY mp.kode_akun_default, ka.nama_akun
                
                ORDER BY kode_akun
            ";

            $result = DB::select($sql, [$periode, $periode]);

            // Convert to collection of objects
            return collect($result)->map(function ($item) {
                return (object) [
                    'kode_akun' => $item->kode_akun,
                    'nama_akun' => $item->nama_akun,
                    'total_debet' => $item->total_debet ?? 0,
                    'total_kredit' => $item->total_kredit ?? 0,
                ];
            });

        } catch (\Exception $e) {
            \Log::error('Error getPenjualanKredit: ' . $e->getMessage());
            return collect([]);
        }
    }

    /**
     * Calculate totals
     */
    private function calculateTotals($data)
    {
        $totalDebet = 0;
        $totalKredit = 0;

        foreach ($data as $row) {
            $totalDebet += $row->total_debet ?? 0;
            $totalKredit += $row->total_kredit ?? 0;
        }

        return ['debet' => $totalDebet, 'kredit' => $totalKredit];
    }
}
