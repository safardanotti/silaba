<?php

namespace App\Http\Controllers;

use App\Models\Transaksi;
use App\Models\DetailTransaksi;
use App\Models\KodeAkun;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class TransaksiController extends Controller
{
    /**
     * Show penerimaan form
     */
    public function penerimaan()
    {
        $kodeAkun = KodeAkun::orderBy('kode_akun')->get();
        return view('transaksi.penerimaan', compact('kodeAkun'));
    }

    /**
     * Store penerimaan transaction
     */
    public function storePenerimaan(Request $request)
    {
        $request->validate([
            'tanggal' => 'required|date',
            'uraian_kegiatan' => 'required|string|max:255',
            'jenis_masuk' => 'required|in:kas,bank',
            'kode_akun' => 'required|array|min:1',
            'kode_akun.*' => 'required|exists:kode_akun,kode_akun',
            'debet' => 'required|array',
            'kredit' => 'required|array',
        ]);

        try {
            DB::beginTransaction();

            // Create main transaction
            $transaksi = Transaksi::create([
                'tanggal' => $request->tanggal,
                'uraian_kegiatan' => $request->uraian_kegiatan,
                'jenis_transaksi' => 'penerimaan',
                'jenis_masuk' => $request->jenis_masuk,
                'created_by' => Auth::id(),
            ]);

            // Create detail transactions
            foreach ($request->kode_akun as $index => $kodeAkun) {
                if (!empty($kodeAkun)) {
                    $debet = $this->parseCurrency($request->debet[$index] ?? 0);
                    $kredit = $this->parseCurrency($request->kredit[$index] ?? 0);

                    DetailTransaksi::create([
                        'transaksi_id' => $transaksi->id,
                        'kode_akun' => $kodeAkun,
                        'debet' => $debet,
                        'kredit' => $kredit,
                    ]);
                }
            }

            DB::commit();

            return redirect()->route('transaksi.penerimaan')
                ->with('success', 'Transaksi penerimaan berhasil disimpan!');

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Gagal menyimpan transaksi: ' . $e->getMessage())
                ->withInput();
        }
    }

    /**
     * Show pengeluaran form
     */
    public function pengeluaran()
    {
        $kodeAkun = KodeAkun::orderBy('kode_akun')->get();
        return view('transaksi.pengeluaran', compact('kodeAkun'));
    }

    /**
     * Store pengeluaran transaction
     */
    public function storePengeluaran(Request $request)
    {
        $request->validate([
            'tanggal' => 'required|date',
            'uraian_kegiatan' => 'required|string|max:255',
            'jenis_masuk' => 'required|in:kas,bank',
            'kode_akun' => 'required|array|min:1',
            'kode_akun.*' => 'required|exists:kode_akun,kode_akun',
            'debet' => 'required|array',
            'kredit' => 'required|array',
        ]);

        try {
            DB::beginTransaction();

            $transaksi = Transaksi::create([
                'tanggal' => $request->tanggal,
                'uraian_kegiatan' => $request->uraian_kegiatan,
                'jenis_transaksi' => 'pengeluaran',
                'jenis_masuk' => $request->jenis_masuk,
                'created_by' => Auth::id(),
            ]);

            foreach ($request->kode_akun as $index => $kodeAkun) {
                if (!empty($kodeAkun)) {
                    $debet = $this->parseCurrency($request->debet[$index] ?? 0);
                    $kredit = $this->parseCurrency($request->kredit[$index] ?? 0);

                    DetailTransaksi::create([
                        'transaksi_id' => $transaksi->id,
                        'kode_akun' => $kodeAkun,
                        'debet' => $debet,
                        'kredit' => $kredit,
                    ]);
                }
            }

            DB::commit();

            return redirect()->route('transaksi.pengeluaran')
                ->with('success', 'Transaksi pengeluaran berhasil disimpan!');

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Gagal menyimpan transaksi: ' . $e->getMessage())
                ->withInput();
        }
    }

    /**
     * Show rupa-rupa form
     */
    public function rupaRupa()
    {
        $kodeAkun = KodeAkun::orderBy('kode_akun')->get();
        return view('transaksi.rupa-rupa', compact('kodeAkun'));
    }

    /**
     * Store rupa-rupa transaction
     */
    public function storeRupaRupa(Request $request)
    {
        $request->validate([
            'tanggal' => 'required|date',
            'uraian_kegiatan' => 'required|string|max:255',
            'kode_akun' => 'required|array|min:1',
            'kode_akun.*' => 'required|exists:kode_akun,kode_akun',
            'debet' => 'required|array',
            'kredit' => 'required|array',
        ]);

        try {
            DB::beginTransaction();

            $transaksi = Transaksi::create([
                'tanggal' => $request->tanggal,
                'uraian_kegiatan' => $request->uraian_kegiatan,
                'jenis_transaksi' => 'rupa_rupa',
                'jenis_masuk' => $request->jenis_masuk ?? null,
                'created_by' => Auth::id(),
            ]);

            foreach ($request->kode_akun as $index => $kodeAkun) {
                if (!empty($kodeAkun)) {
                    $debet = $this->parseCurrency($request->debet[$index] ?? 0);
                    $kredit = $this->parseCurrency($request->kredit[$index] ?? 0);

                    DetailTransaksi::create([
                        'transaksi_id' => $transaksi->id,
                        'kode_akun' => $kodeAkun,
                        'debet' => $debet,
                        'kredit' => $kredit,
                    ]);
                }
            }

            DB::commit();

            return redirect()->route('transaksi.rupa-rupa')
                ->with('success', 'Transaksi rupa-rupa berhasil disimpan!');

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Gagal menyimpan transaksi: ' . $e->getMessage())
                ->withInput();
        }
    }

    /**
     * Show edit form
     */
    public function edit($id)
    {
        $transaksi = Transaksi::with('detailTransaksi')->findOrFail($id);
        $kodeAkun = KodeAkun::orderBy('kode_akun')->get();
        
        return view('transaksi.edit', compact('transaksi', 'kodeAkun'));
    }

    /**
     * Update transaction
     */
    public function update(Request $request, $id)
    {
        $request->validate([
            'tanggal' => 'required|date',
            'uraian_kegiatan' => 'required|string|max:255',
            'jenis_masuk' => 'required|in:kas,bank',
            'kode_akun' => 'required|array|min:1',
            'kode_akun.*' => 'required|exists:kode_akun,kode_akun',
            'debet' => 'required|array',
            'kredit' => 'required|array',
        ]);

        try {
            DB::beginTransaction();

            $transaksi = Transaksi::findOrFail($id);
            
            // Update main transaction
            $transaksi->update([
                'tanggal' => $request->tanggal,
                'uraian_kegiatan' => $request->uraian_kegiatan,
                'jenis_masuk' => $request->jenis_masuk,
            ]);

            // Delete old details
            DetailTransaksi::where('transaksi_id', $id)->delete();

            // Insert new details
            foreach ($request->kode_akun as $index => $kodeAkun) {
                if (!empty($kodeAkun)) {
                    $debet = $this->parseCurrency($request->debet[$index] ?? 0);
                    $kredit = $this->parseCurrency($request->kredit[$index] ?? 0);

                    DetailTransaksi::create([
                        'transaksi_id' => $id,
                        'kode_akun' => $kodeAkun,
                        'debet' => $debet,
                        'kredit' => $kredit,
                    ]);
                }
            }

            DB::commit();

            return redirect()->route('laporan.transaksi')
                ->with('success', 'Transaksi berhasil diupdate!');

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Gagal update transaksi: ' . $e->getMessage())
                ->withInput();
        }
    }

    /**
     * Delete transaction
     */
    public function destroy($id)
    {
        try {
            DB::beginTransaction();

            // Delete detail transactions first (foreign key)
            DetailTransaksi::where('transaksi_id', $id)->delete();
            
            // Delete main transaction
            Transaksi::where('id', $id)->delete();

            DB::commit();

            return redirect()->back()
                ->with('success', 'Transaksi berhasil dihapus!');

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Gagal menghapus transaksi: ' . $e->getMessage());
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

    /**
     * Get kode akun for AJAX
     */
    public function getKodeAkun()
    {
        $kodeAkun = KodeAkun::orderBy('kode_akun')->get(['kode_akun', 'nama_akun', 'tipe_akun']);
        return response()->json($kodeAkun);
    }
}
