<?php

namespace App\Http\Controllers;

use App\Models\Transaksi;
use App\Models\DetailTransaksi;
use App\Models\KodeAkun;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RekapController extends Controller
{
    /**
     * Display rekap per akun page
     */
    public function perAkun(Request $request)
    {
        $jenis = $request->get('jenis', 'semua');
        $periode = $request->get('periode', 'semua');

        $bulanNama = [
            '01' => 'Januari', '02' => 'Februari', '03' => 'Maret',
            '04' => 'April', '05' => 'Mei', '06' => 'Juni',
            '07' => 'Juli', '08' => 'Agustus', '09' => 'September',
            '10' => 'Oktober', '11' => 'November', '12' => 'Desember'
        ];

        // Build query
        $query = DB::table('detail_transaksi as dt')
            ->join('transaksi as t', 'dt.transaksi_id', '=', 't.id')
            ->join('kode_akun as ka', 'dt.kode_akun', '=', 'ka.kode_akun')
            ->whereNotIn('dt.kode_akun', ['100', '101']) // Exclude KAS dan BANK
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

        return view('rekap.per-akun', compact(
            'data', 'jenis', 'periode', 'bulanNama',
            'grandTotalDebet', 'grandTotalKredit'
        ));
    }

    /**
     * Display detail akun page
     */
    public function detailAkun(Request $request)
    {
        $kodeAkun = $request->get('kode');
        $jenis = $request->get('jenis', 'semua');
        $periode = $request->get('periode', 'semua');

        if (!$kodeAkun) {
            return redirect()->route('rekap.akun')->with('error', 'Kode akun tidak valid');
        }

        $bulanNama = [
            '01' => 'Januari', '02' => 'Februari', '03' => 'Maret',
            '04' => 'April', '05' => 'Mei', '06' => 'Juni',
            '07' => 'Juli', '08' => 'Agustus', '09' => 'September',
            '10' => 'Oktober', '11' => 'November', '12' => 'Desember'
        ];

        // Get account info
        $akunInfo = KodeAkun::where('kode_akun', $kodeAkun)->first();

        if (!$akunInfo) {
            return redirect()->route('rekap.akun')->with('error', 'Kode akun tidak ditemukan');
        }

        // Build query for transactions
        $query = DB::table('detail_transaksi as dt')
            ->join('transaksi as t', 'dt.transaksi_id', '=', 't.id')
            ->join('users as u', 't.created_by', '=', 'u.id')
            ->where('dt.kode_akun', $kodeAkun)
            ->select(
                't.id',
                't.tanggal',
                't.uraian_kegiatan',
                't.jenis_transaksi',
                't.jenis_masuk',
                'dt.debet',
                'dt.kredit',
                'u.full_name'
            )
            ->orderBy('t.tanggal')
            ->orderBy('t.id');

        if ($jenis !== 'semua') {
            $query->where('t.jenis_transaksi', $jenis);
        }

        if ($periode !== 'semua') {
            $query->whereRaw("DATE_FORMAT(t.tanggal, '%Y-%m') = ?", [$periode]);
        }

        $transactions = $query->get();

        // Calculate totals
        $totalDebet = $transactions->sum('debet');
        $totalKredit = $transactions->sum('kredit');

        return view('rekap.detail-akun', compact(
            'akunInfo', 'transactions', 'jenis', 'periode', 'bulanNama',
            'totalDebet', 'totalKredit', 'kodeAkun'
        ));
    }
}
