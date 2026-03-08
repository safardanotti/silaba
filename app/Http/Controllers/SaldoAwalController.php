<?php

namespace App\Http\Controllers;

use App\Models\SaldoAwal;
use App\Models\KodeAkun;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SaldoAwalController extends Controller
{
    /**
     * Display saldo awal form
     */
    public function index(Request $request)
    {
        $periode = $request->get('periode', date('Y-m'));
        
        // Get all kode akun with existing saldo
        $saldoData = DB::table('kode_akun as ka')
            ->leftJoin('saldo_awal as sa', function($join) use ($periode) {
                $join->on('ka.kode_akun', '=', 'sa.kode_akun')
                     ->whereRaw("DATE_FORMAT(sa.periode, '%Y-%m') = ?", [$periode]);
            })
            ->select([
                'ka.kode_akun',
                'ka.nama_akun',
                'ka.tipe_akun',
                DB::raw('COALESCE(sa.debet, 0) as debet'),
                DB::raw('COALESCE(sa.kredit, 0) as kredit')
            ])
            ->orderBy('ka.kode_akun')
            ->get();
        
        return view('saldo-awal.index', compact('saldoData', 'periode'));
    }

    /**
     * Store saldo awal
     */
    public function store(Request $request)
    {
        $request->validate([
            'periode' => 'required|date_format:Y-m',
            'kode_akun' => 'required|array',
            'debet' => 'required|array',
            'kredit' => 'required|array',
        ]);

        $periode = $request->periode;

        try {
            DB::beginTransaction();

            // Delete existing saldo awal for this period
            SaldoAwal::whereRaw("DATE_FORMAT(periode, '%Y-%m') = ?", [$periode])->delete();

            // Insert new saldo awal
            $periodeDate = $periode . '-01';
            
            foreach ($request->kode_akun as $index => $kodeAkun) {
                if (!empty($kodeAkun)) {
                    $debet = $this->parseCurrency($request->debet[$index] ?? 0);
                    $kredit = $this->parseCurrency($request->kredit[$index] ?? 0);

                    if ($debet > 0 || $kredit > 0) {
                        SaldoAwal::create([
                            'periode' => $periodeDate,
                            'kode_akun' => $kodeAkun,
                            'debet' => $debet,
                            'kredit' => $kredit,
                        ]);
                    }
                }
            }

            DB::commit();

            return redirect()->route('saldo-awal.index', ['periode' => $periode])
                ->with('success', 'Saldo awal berhasil disimpan!');

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Gagal menyimpan saldo awal: ' . $e->getMessage())
                ->withInput();
        }
    }

    /**
     * Parse Indonesian currency format to float
     */
    private function parseCurrency($value)
    {
        if (empty($value)) return 0;
        
        // Remove thousand separators (dots) and convert decimal comma to dot
        $value = str_replace('.', '', $value);
        $value = str_replace(',', '.', $value);
        
        return floatval($value);
    }
}
