<?php

namespace App\Http\Controllers;

use App\Models\MasterPiutang;
use App\Models\TransaksiPiutang;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class PiutangController extends Controller
{
    /**
     * Nama bulan Indonesia
     */
    private $bulanNama = [
        '01' => 'Januari', '02' => 'Februari', '03' => 'Maret',
        '04' => 'April', '05' => 'Mei', '06' => 'Juni',
        '07' => 'Juli', '08' => 'Agustus', '09' => 'September',
        '10' => 'Oktober', '11' => 'November', '12' => 'Desember'
    ];

    /**
     * Display piutang page
     */
    public function index(Request $request)
    {
        $periode = $request->get('periode', date('Y-m'));
        $bulan = substr($periode, 5, 2);
        $tahun = substr($periode, 0, 4);
        $periodeDate = "{$tahun}-{$bulan}-01";

        $success = session('success');
        $error = session('error');

        // Check for auto saldo parameter
        $prevData = [];
        if ($request->has('auto_saldo')) {
            $prevData = $this->getAutoSaldoAwal($tahun, $bulan);
            if (!empty($prevData)) {
                $success = 'Saldo awal berhasil diambil dari saldo akhir bulan sebelumnya.';
            } else {
                $error = 'Tidak ada data bulan sebelumnya.';
            }
        }

        // Get piutang data
        $piutangData = $this->getPiutangData($periodeDate, $prevData);

        // Calculate totals
        $totals = $this->calculateTotals($piutangData);

        return view('piutang.index', compact(
            'piutangData', 'periode', 'bulan', 'tahun',
            'totals', 'success', 'error'
        ) + ['bulanNama' => $this->bulanNama]);
    }

    /**
     * Store piutang data
     */
    public function store(Request $request)
    {
        $request->validate([
            'periode' => 'required|date_format:Y-m',
            'master_piutang_id' => 'required|array',
            'saldo_awal' => 'required|array',
            'mutasi_debet' => 'required|array',
            'mutasi_kredit' => 'required|array',
        ]);

        try {
            DB::beginTransaction();

            $periodeDate = $request->periode . '-01';

            // Delete existing data for this period
            TransaksiPiutang::where('periode', $periodeDate)->delete();

            // Insert new data
            $masterIds = $request->master_piutang_id;
            $saldoAwals = $request->saldo_awal;
            $mutasiDebets = $request->mutasi_debet;
            $mutasiKredits = $request->mutasi_kredit;

            $insertedCount = 0;
            foreach ($masterIds as $index => $masterId) {
                // Parse Indonesian number format
                $saldoAwal = $this->parseIndonesianNumber($saldoAwals[$index] ?? '0');
                $mutasiDebet = $this->parseIndonesianNumber($mutasiDebets[$index] ?? '0');
                $mutasiKredit = $this->parseIndonesianNumber($mutasiKredits[$index] ?? '0');

                // Calculate saldo akhir
                $saldoAkhir = $saldoAwal + $mutasiDebet - $mutasiKredit;

                TransaksiPiutang::create([
                    'periode' => $periodeDate,
                    'master_piutang_id' => $masterId,
                    'saldo_awal' => $saldoAwal,
                    'mutasi_debet' => $mutasiDebet,
                    'mutasi_kredit' => $mutasiKredit,
                    'saldo_akhir' => $saldoAkhir,
                    'created_by' => Auth::id(),
                ]);
                $insertedCount++;
            }

            DB::commit();

            return redirect()
                ->route('piutang.index', ['periode' => $request->periode])
                ->with('success', "Data piutang berhasil disimpan! ({$insertedCount} data)");

        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()
                ->back()
                ->with('error', 'Gagal menyimpan data: ' . $e->getMessage())
                ->withInput();
        }
    }

    /**
     * Get piutang data with transaksi
     */
    private function getPiutangData($periodeDate, $prevData = [])
    {
        $data = DB::table('master_piutang as mp')
            ->leftJoin('transaksi_piutang as tp', function ($join) use ($periodeDate) {
                $join->on('mp.id', '=', 'tp.master_piutang_id')
                    ->where('tp.periode', '=', $periodeDate);
            })
            ->where('mp.aktif', 1)
            ->select(
                'mp.id',
                'mp.nama_debitur',
                'mp.kode_akun_default',
                'tp.saldo_awal',
                'tp.mutasi_debet',
                'tp.mutasi_kredit',
                'tp.saldo_akhir'
            )
            ->orderBy('mp.id')
            ->get();

        // If auto_saldo is set, update saldo_awal from previous data
        if (!empty($prevData)) {
            $data = $data->map(function ($item) use ($prevData) {
                if (isset($prevData[$item->id])) {
                    $item->saldo_awal = $prevData[$item->id];
                } else {
                    $item->saldo_awal = $item->saldo_awal ?? 0;
                }
                return $item;
            });
        }

        return $data;
    }

    /**
     * Get auto saldo awal from previous month
     */
    private function getAutoSaldoAwal($tahun, $bulan)
    {
        $prevPeriode = date('Y-m-d', strtotime("{$tahun}-{$bulan}-01 -1 month"));

        $data = TransaksiPiutang::where('periode', $prevPeriode)
            ->pluck('saldo_akhir', 'master_piutang_id')
            ->toArray();

        return $data;
    }

    /**
     * Calculate totals for piutang data
     */
    private function calculateTotals($data)
    {
        $totalSaldoAwal = 0;
        $totalMutasiDebet = 0;
        $totalMutasiKredit = 0;
        $totalSaldoAkhir = 0;

        foreach ($data as $row) {
            $saldoAwal = $row->saldo_awal ?? 0;
            $mutasiDebet = $row->mutasi_debet ?? 0;
            $mutasiKredit = $row->mutasi_kredit ?? 0;
            $saldoAkhir = $saldoAwal + $mutasiDebet - $mutasiKredit;

            $totalSaldoAwal += $saldoAwal;
            $totalMutasiDebet += $mutasiDebet;
            $totalMutasiKredit += $mutasiKredit;
            $totalSaldoAkhir += $saldoAkhir;
        }

        return [
            'saldo_awal' => $totalSaldoAwal,
            'mutasi_debet' => $totalMutasiDebet,
            'mutasi_kredit' => $totalMutasiKredit,
            'saldo_akhir' => $totalSaldoAkhir,
        ];
    }

    /**
     * Parse Indonesian number format to float
     */
    private function parseIndonesianNumber($value)
    {
        if (empty($value)) {
            return 0;
        }

        // Remove thousand separators (.)
        $value = str_replace('.', '', $value);
        // Convert decimal comma to dot
        $value = str_replace(',', '.', $value);

        return floatval($value);
    }
}
