<?php

namespace App\Http\Controllers;

use App\Models\KasBank;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class KasBankController extends Controller
{
    /**
     * Display kas & bank page
     */
    public function index(Request $request)
    {
        $bulan = $request->get('bulan', date('m'));
        $tahun = $request->get('tahun', date('Y'));
        $periode = "{$tahun}-{$bulan}-01";

        $bulanNama = [
            '01' => 'Januari', '02' => 'Februari', '03' => 'Maret',
            '04' => 'April', '05' => 'Mei', '06' => 'Juni',
            '07' => 'Juli', '08' => 'Agustus', '09' => 'September',
            '10' => 'Oktober', '11' => 'November', '12' => 'Desember'
        ];

        $kasData = KasBank::getKasData($periode);
        $bankData = KasBank::getBankData($periode);

        return view('kas-bank.index', compact(
            'bulan', 'tahun', 'periode', 'bulanNama', 'kasData', 'bankData'
        ));
    }

    /**
     * Save saldo awal for kas and bank
     */
    public function saveSaldoAwal(Request $request)
    {
        $request->validate([
            'periode' => 'required|date',
            'saldo_awal_kas' => 'required|string',
            'saldo_awal_bank' => 'required|string',
        ]);

        try {
            DB::beginTransaction();

            $periode = $request->periode;
            $saldoAwalKas = $this->parseCurrency($request->saldo_awal_kas);
            $saldoAwalBank = $this->parseCurrency($request->saldo_awal_bank);

            // Get existing mutasi for KAS
            $existingKas = KasBank::where('periode', $periode)->where('jenis', 'kas')->first();
            $mutasiDebetKas = $existingKas ? $existingKas->mutasi_debet : 0;
            $mutasiKreditKas = $existingKas ? $existingKas->mutasi_kredit : 0;

            // Update or create KAS
            KasBank::updateOrCreate(
                ['periode' => $periode, 'jenis' => 'kas'],
                [
                    'nama' => 'KAS',
                    'saldo_awal' => $saldoAwalKas,
                    'mutasi_debet' => $mutasiDebetKas,
                    'mutasi_kredit' => $mutasiKreditKas,
                    'saldo_akhir' => $saldoAwalKas + $mutasiDebetKas - $mutasiKreditKas
                ]
            );

            // Get existing mutasi for BANK
            $existingBank = KasBank::where('periode', $periode)->where('jenis', 'bank')->first();
            $mutasiDebetBank = $existingBank ? $existingBank->mutasi_debet : 0;
            $mutasiKreditBank = $existingBank ? $existingBank->mutasi_kredit : 0;

            // Update or create BANK
            KasBank::updateOrCreate(
                ['periode' => $periode, 'jenis' => 'bank'],
                [
                    'nama' => 'BANK',
                    'saldo_awal' => $saldoAwalBank,
                    'mutasi_debet' => $mutasiDebetBank,
                    'mutasi_kredit' => $mutasiKreditBank,
                    'saldo_akhir' => $saldoAwalBank + $mutasiDebetBank - $mutasiKreditBank
                ]
            );

            DB::commit();

            return redirect()->back()->with('success', 'Saldo awal berhasil disimpan!');

        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->with('error', 'Error: ' . $e->getMessage());
        }
    }

    /**
     * Update mutasi for kas and bank
     */
    public function updateMutasi(Request $request)
    {
        $request->validate([
            'periode' => 'required|date',
            'mutasi_debet_kas' => 'required|string',
            'mutasi_kredit_kas' => 'required|string',
            'mutasi_debet_bank' => 'required|string',
            'mutasi_kredit_bank' => 'required|string',
        ]);

        try {
            DB::beginTransaction();

            $periode = $request->periode;

            // Get kas data
            $kasRecord = KasBank::where('periode', $periode)->where('jenis', 'kas')->first();
            $saldoAwalKas = $kasRecord ? $kasRecord->saldo_awal : 0;
            $mutasiDebetKas = $this->parseCurrency($request->mutasi_debet_kas);
            $mutasiKreditKas = $this->parseCurrency($request->mutasi_kredit_kas);
            $saldoAkhirKas = $saldoAwalKas + $mutasiDebetKas - $mutasiKreditKas;

            KasBank::updateOrCreate(
                ['periode' => $periode, 'jenis' => 'kas'],
                [
                    'nama' => 'KAS',
                    'saldo_awal' => $saldoAwalKas,
                    'mutasi_debet' => $mutasiDebetKas,
                    'mutasi_kredit' => $mutasiKreditKas,
                    'saldo_akhir' => $saldoAkhirKas
                ]
            );

            // Get bank data
            $bankRecord = KasBank::where('periode', $periode)->where('jenis', 'bank')->first();
            $saldoAwalBank = $bankRecord ? $bankRecord->saldo_awal : 0;
            $mutasiDebetBank = $this->parseCurrency($request->mutasi_debet_bank);
            $mutasiKreditBank = $this->parseCurrency($request->mutasi_kredit_bank);
            $saldoAkhirBank = $saldoAwalBank + $mutasiDebetBank - $mutasiKreditBank;

            KasBank::updateOrCreate(
                ['periode' => $periode, 'jenis' => 'bank'],
                [
                    'nama' => 'BANK',
                    'saldo_awal' => $saldoAwalBank,
                    'mutasi_debet' => $mutasiDebetBank,
                    'mutasi_kredit' => $mutasiKreditBank,
                    'saldo_akhir' => $saldoAkhirBank
                ]
            );

            DB::commit();

            return redirect()->back()->with('success', 'Mutasi berhasil diupdate!');

        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->with('error', 'Error: ' . $e->getMessage());
        }
    }

    /**
     * Copy saldo akhir to next month
     */
    public function copyToNextMonth(Request $request)
    {
        $request->validate([
            'periode' => 'required|date',
        ]);

        try {
            DB::beginTransaction();

            $periode = $request->periode;
            $nextPeriode = date('Y-m-01', strtotime($periode . ' +1 month'));

            // Get current data
            $currentData = KasBank::where('periode', $periode)->get();

            if ($currentData->isEmpty()) {
                return redirect()->back()->with('error', 'Tidak ada data untuk periode ini!');
            }

            foreach ($currentData as $data) {
                KasBank::updateOrCreate(
                    ['periode' => $nextPeriode, 'jenis' => $data->jenis],
                    [
                        'nama' => $data->nama,
                        'saldo_awal' => $data->saldo_akhir,
                        'mutasi_debet' => 0,
                        'mutasi_kredit' => 0,
                        'saldo_akhir' => $data->saldo_akhir
                    ]
                );
            }

            DB::commit();

            return redirect()->back()->with('success', 'Saldo akhir berhasil dicopy ke bulan berikutnya!');

        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->with('error', 'Error: ' . $e->getMessage());
        }
    }

    /**
     * Parse currency string to float
     */
    private function parseCurrency($value)
    {
        $value = str_replace('.', '', $value);
        $value = str_replace(',', '.', $value);
        return floatval($value);
    }
}
