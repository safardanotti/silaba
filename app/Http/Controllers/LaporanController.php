<?php

namespace App\Http\Controllers;

use App\Models\Transaksi;
use Illuminate\Http\Request;

class LaporanController extends Controller
{
    public function transaksi(Request $request)
    {
        $bulan = $request->get('bulan', date('m'));
        $tahun = $request->get('tahun', date('Y'));
        $jenis = $request->get('jenis', '');
        
        $bulanNama = [
            '01' => 'Januari', '02' => 'Februari', '03' => 'Maret',
            '04' => 'April', '05' => 'Mei', '06' => 'Juni',
            '07' => 'Juli', '08' => 'Agustus', '09' => 'September',
            '10' => 'Oktober', '11' => 'November', '12' => 'Desember'
        ];
        
        $periodeAwal = "{$tahun}-{$bulan}-01";
        $periodeAkhir = date('Y-m-t', strtotime($periodeAwal));
        
        $query = Transaksi::with(['user', 'detailTransaksi', 'detailTransaksi.kodeAkun'])
            ->whereBetween('tanggal', [$periodeAwal, $periodeAkhir])
            ->orderBy('tanggal', 'asc')
            ->orderBy('created_at', 'asc');
        
        if ($jenis) {
            $query->where('jenis_transaksi', $jenis);
        }
        
        $transactions = $query->paginate(10);
        
        // Prepare data for JavaScript modal
        $transactionsJson = [];
        foreach ($transactions as $trans) {
            $details = [];
            foreach ($trans->detailTransaksi as $detail) {
                $details[] = [
                    'kode_akun' => $detail->kode_akun,
                    'nama_akun' => $detail->kodeAkun->nama_akun ?? '-',
                    'debet' => (float) $detail->debet,
                    'kredit' => (float) $detail->kredit
                ];
            }
            
            $transactionsJson[] = [
                'id' => $trans->id,
                'tanggal' => $trans->tanggal->format('d F Y'),
                'jenis_transaksi' => $trans->jenis_transaksi,
                'jenis_masuk' => strtoupper($trans->jenis_masuk ?? '-'),
                'uraian_kegiatan' => $trans->uraian_kegiatan,
                'created_by' => $trans->user->full_name ?? '-',
                'created_at' => $trans->created_at ? $trans->created_at->format('d/m/Y H:i') : '-',
                'details' => $details,
                'total_debet' => (float) $trans->detailTransaksi->sum('debet'),
                'total_kredit' => (float) $trans->detailTransaksi->sum('kredit')
            ];
        }
        
        return view('laporan.transaksi', compact(
            'transactions', 'bulan', 'tahun', 'bulanNama', 'jenis', 'transactionsJson'
        ));
    }
}
