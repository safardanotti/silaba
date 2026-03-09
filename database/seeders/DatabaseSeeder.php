<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\KodeAkun;
use App\Models\SaldoAwal;
use App\Models\KasBank;
use App\Models\MasterPiutang;
use App\Models\AnggaranLabaRugi;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     * Data ini PERSIS SAMA dengan silaba_db.sql dari Native PHP
     */
    public function run(): void
    {
        // =============================================
        // USERS - SAMA DENGAN NATIVE
        // =============================================
        User::create([
            'username' => 'admin',
            'password' => Hash::make('admin123'),
            'full_name' => 'Administrator',
            'role' => 'admin',
        ]);

        User::create([
            'username' => 'pimpinan',
            'password' => Hash::make('admin123'),
            'full_name' => 'Pimpinan Koperasi',
            'role' => 'pimpinan',
        ]);

        User::create([
            'username' => 'staff',
            'password' => Hash::make('admin123'),
            'full_name' => 'Staff Koperasi',
            'role' => 'staff',
        ]);

        
        // KODE AKUN - SAMA PERSIS DENGAN NATIVE
        
        $kodeAkunData = [
            // AKTIVA
            ['kode_akun' => '100', 'nama_akun' => 'KAS', 'tipe_akun' => 'aktiva'],
            ['kode_akun' => '101', 'nama_akun' => 'BANK', 'tipe_akun' => 'aktiva'],
            ['kode_akun' => '120', 'nama_akun' => 'PIUTANG', 'tipe_akun' => 'aktiva'],
            ['kode_akun' => '130', 'nama_akun' => 'UANG MUKA', 'tipe_akun' => 'aktiva'],
            ['kode_akun' => '132', 'nama_akun' => 'BIAYA DIBAYAR DI MUKA', 'tipe_akun' => 'aktiva'],
            ['kode_akun' => '145', 'nama_akun' => 'PENYERTAAN MODAL KERJA', 'tipe_akun' => 'aktiva'],
            ['kode_akun' => '200', 'nama_akun' => 'INVENTARIS', 'tipe_akun' => 'aktiva'],
            ['kode_akun' => '210', 'nama_akun' => 'AKUMULASI PENYUSUTAN', 'tipe_akun' => 'aktiva'],
            ['kode_akun' => '360', 'nama_akun' => 'PAJAK DIBAYAR DI MUKA', 'tipe_akun' => 'aktiva'],
            
            // PASSIVA
            ['kode_akun' => '300', 'nama_akun' => 'HUTANG', 'tipe_akun' => 'passiva'],
            ['kode_akun' => '310', 'nama_akun' => 'UANG TITIPAN', 'tipe_akun' => 'passiva'],
            ['kode_akun' => '320', 'nama_akun' => 'SIMPANAN SUKARELA', 'tipe_akun' => 'passiva'],
            ['kode_akun' => '350', 'nama_akun' => 'PENDAPATAN YANG AKAN DI TERIMA', 'tipe_akun' => 'passiva'],
            ['kode_akun' => '370', 'nama_akun' => 'HUTANG PAJAK', 'tipe_akun' => 'passiva'],
            ['kode_akun' => '500', 'nama_akun' => 'SIMPANAN POKOK', 'tipe_akun' => 'passiva'],
            ['kode_akun' => '510', 'nama_akun' => 'SIMPANAN WAJIB', 'tipe_akun' => 'passiva'],
            ['kode_akun' => '520', 'nama_akun' => 'DANA CADANGAN', 'tipe_akun' => 'passiva'],
            ['kode_akun' => '525', 'nama_akun' => 'MODAL DONASI', 'tipe_akun' => 'passiva'],
            ['kode_akun' => '530', 'nama_akun' => 'DANA SOSIAL', 'tipe_akun' => 'passiva'],
            ['kode_akun' => '540', 'nama_akun' => 'DANA PEMBANGUNAN KERJA', 'tipe_akun' => 'passiva'],
            ['kode_akun' => '550', 'nama_akun' => 'DANA PENDIDIKAN', 'tipe_akun' => 'passiva'],
            ['kode_akun' => '560', 'nama_akun' => 'DANA PENGURUS', 'tipe_akun' => 'passiva'],
            ['kode_akun' => '565', 'nama_akun' => 'DANA KESEJAHTERAAN KARYAWAN', 'tipe_akun' => 'passiva'],
            ['kode_akun' => '600', 'nama_akun' => 'SHU/LABARUGI TAHUN LALU', 'tipe_akun' => 'passiva'],
            
            // PENDAPATAN
            ['kode_akun' => '700', 'nama_akun' => 'PENDAPATAN PERDAGANGAN', 'tipe_akun' => 'pendapatan'],
            ['kode_akun' => '703', 'nama_akun' => 'PENDAPATAN SIMPAN PINJAM', 'tipe_akun' => 'pendapatan'],
            ['kode_akun' => '711', 'nama_akun' => 'PENDAPATAN BORONGAN', 'tipe_akun' => 'pendapatan'],
            ['kode_akun' => '712', 'nama_akun' => 'PENDAPATAN KEPIL', 'tipe_akun' => 'pendapatan'],
            ['kode_akun' => '713', 'nama_akun' => 'PENDAPATAN CLEANING SERVICE', 'tipe_akun' => 'pendapatan'],
            ['kode_akun' => '714', 'nama_akun' => 'PENDAPATAN AIR ISI ULANG', 'tipe_akun' => 'pendapatan'],
            ['kode_akun' => '715', 'nama_akun' => 'PENDAPATAN FOTOCOPY', 'tipe_akun' => 'pendapatan'],
            ['kode_akun' => '716', 'nama_akun' => 'PENDAPATAN SEWA KENDARAAN', 'tipe_akun' => 'pendapatan'],
            ['kode_akun' => '718', 'nama_akun' => 'PENDAPATAN PERMAKANAN', 'tipe_akun' => 'pendapatan'],
            ['kode_akun' => '719', 'nama_akun' => 'PENDAPATAN BUKAN ANGGOTA LAINNYA', 'tipe_akun' => 'pendapatan'],
            ['kode_akun' => '724', 'nama_akun' => 'PENDAPATAN BAGI HASIL PT KMB', 'tipe_akun' => 'pendapatan'],
            ['kode_akun' => '725', 'nama_akun' => 'PENDAPATAN STEAM ALAT BERAT PTP', 'tipe_akun' => 'pendapatan'],
            
            // BIAYA
            ['kode_akun' => '800', 'nama_akun' => 'BIAYA PERDAGANGAN', 'tipe_akun' => 'biaya'],
            ['kode_akun' => '803', 'nama_akun' => 'BIAYA SIMPAN PINJAM', 'tipe_akun' => 'biaya'],
            ['kode_akun' => '811', 'nama_akun' => 'BIAYA BORONGAN', 'tipe_akun' => 'biaya'],
            ['kode_akun' => '812', 'nama_akun' => 'BIAYA KEPIL', 'tipe_akun' => 'biaya'],
            ['kode_akun' => '813', 'nama_akun' => 'BIAYA CLEANING SERVICE', 'tipe_akun' => 'biaya'],
            ['kode_akun' => '814', 'nama_akun' => 'BIAYA AIR ISI ULANG', 'tipe_akun' => 'biaya'],
            ['kode_akun' => '815', 'nama_akun' => 'BIAYA FOTOCOPY', 'tipe_akun' => 'biaya'],
            ['kode_akun' => '816', 'nama_akun' => 'BIAYA SEWA KENDARAAN', 'tipe_akun' => 'biaya'],
            ['kode_akun' => '818', 'nama_akun' => 'BIAYA PERMAKANAN', 'tipe_akun' => 'biaya'],
            ['kode_akun' => '820', 'nama_akun' => 'BIAYA BADAN PENGURUS', 'tipe_akun' => 'biaya'],
            ['kode_akun' => '825', 'nama_akun' => 'BIAYA STEAM ALAT BERAT PTP', 'tipe_akun' => 'biaya'],
            ['kode_akun' => '830', 'nama_akun' => 'BIAYA BADAN PENGAWAS', 'tipe_akun' => 'biaya'],
            ['kode_akun' => '840', 'nama_akun' => 'BIAYA ADM.UMUM & KEUANGAN', 'tipe_akun' => 'biaya'],
            ['kode_akun' => '841', 'nama_akun' => 'BIAYA PAKET LEBARAN', 'tipe_akun' => 'biaya'],
            ['kode_akun' => '846', 'nama_akun' => 'BIAYA MANAGER', 'tipe_akun' => 'biaya'],
            
            // SHU TAHUN BERJALAN
            ['kode_akun' => '900', 'nama_akun' => 'SHU/LABARUGI TAHUN BERJALAN', 'tipe_akun' => 'passiva'],
        ];

        foreach ($kodeAkunData as $akun) {
            KodeAkun::create($akun);
        }

        // =============================================
        // MASTER PIUTANG - SAMA DENGAN NATIVE
        // =============================================
        $masterPiutangData = [
            ['nama_debitur' => 'Pengadaan Tiket Pesawat Udara PT. Pelindo II PLG', 'kode_akun_default' => '700', 'aktif' => 1],
            ['nama_debitur' => 'Pengadaan Barang - Barang Electronic', 'kode_akun_default' => '700', 'aktif' => 1],
            ['nama_debitur' => 'Pekerjaan Sewa Speed Bout Antar Jemput Pandu', 'kode_akun_default' => '716', 'aktif' => 1],
            ['nama_debitur' => 'Pekerjaan Sewa Kendaraan PT. JPPI', 'kode_akun_default' => '716', 'aktif' => 1],
            ['nama_debitur' => 'Pekerjaan Kepil Kapal PT. Pelindo II Palembang', 'kode_akun_default' => '712', 'aktif' => 1],
            ['nama_debitur' => 'Pekerjaan Cleaning Service Kantor, Terminal Penumpang, dan Rumah Dinas GM', 'kode_akun_default' => '713', 'aktif' => 1],
            ['nama_debitur' => 'Pekerjaan Cleaning Service Kantor TPK dan Lapangan', 'kode_akun_default' => '713', 'aktif' => 1],
            ['nama_debitur' => 'Pekerjaan Cleaning Service Tj. Buyut', 'kode_akun_default' => '713', 'aktif' => 1],
            ['nama_debitur' => 'Pekerjaan Kebersihan Gedung Kantor PT. PTP Palembang', 'kode_akun_default' => '713', 'aktif' => 1],
            ['nama_debitur' => 'Pekerjaan Kebersihan Areal Konvensional', 'kode_akun_default' => '713', 'aktif' => 1],
            ['nama_debitur' => 'Pekerjaan Pemborongan/Leveransir', 'kode_akun_default' => '711', 'aktif' => 1],
            ['nama_debitur' => 'Tagihan Sewa Kendaraan Operasional KSKP', 'kode_akun_default' => '716', 'aktif' => 1],
            ['nama_debitur' => 'Pekerjaan Sewa 1 Buah Mobil Kendaraan Inova pada PT. JAI', 'kode_akun_default' => '716', 'aktif' => 1],
            ['nama_debitur' => 'Pekerjaan Sewa Mobil Antar Jemput pandu', 'kode_akun_default' => '716', 'aktif' => 1],
            ['nama_debitur' => 'Pek. Operator Genset', 'kode_akun_default' => '716', 'aktif' => 1],
            ['nama_debitur' => 'Pek. Sewa Kendaraan Patroli PFSO', 'kode_akun_default' => '716', 'aktif' => 1],
            ['nama_debitur' => 'Pekerjaan Pengadaan Jamuan Tamu PT. Pelindo II Palembang', 'kode_akun_default' => '718', 'aktif' => 1],
            ['nama_debitur' => 'Pekerjaan Fotocopy dan Penjilidan Surat Dinas PT. Pelindo II Palembang', 'kode_akun_default' => '715', 'aktif' => 1],
            ['nama_debitur' => 'Bagi Hasil KMB', 'kode_akun_default' => '724', 'aktif' => 1],
        ];

        foreach ($masterPiutangData as $mp) {
            MasterPiutang::create($mp);
        }

        // =============================================
        // SALDO AWAL FEBRUARI 2023 - SAMA DENGAN NATIVE
        // =============================================
        $periode = '2023-02-01';
        $saldoAwalData = [
            ['kode_akun' => '100', 'debet' => 600259149.51, 'kredit' => 0, 'saldo_akhir_debet' => 1053780581.28, 'saldo_akhir_kredit' => 0],
            ['kode_akun' => '120', 'debet' => 4317740487.42, 'kredit' => 0, 'saldo_akhir_debet' => 4595651774.42, 'saldo_akhir_kredit' => 0],
            ['kode_akun' => '130', 'debet' => 23552690.00, 'kredit' => 0, 'saldo_akhir_debet' => 23552690.00, 'saldo_akhir_kredit' => 0],
            ['kode_akun' => '132', 'debet' => 483767452.50, 'kredit' => 0, 'saldo_akhir_debet' => 483767452.50, 'saldo_akhir_kredit' => 0],
            ['kode_akun' => '145', 'debet' => 990000000.00, 'kredit' => 0, 'saldo_akhir_debet' => 990000000.00, 'saldo_akhir_kredit' => 0],
            ['kode_akun' => '200', 'debet' => 2658879560.71, 'kredit' => 0, 'saldo_akhir_debet' => 2658879560.71, 'saldo_akhir_kredit' => 0],
            ['kode_akun' => '210', 'debet' => 0, 'kredit' => 1846433587.61, 'saldo_akhir_debet' => 0, 'saldo_akhir_kredit' => 1858352305.44],
            ['kode_akun' => '300', 'debet' => 0, 'kredit' => 1626534090.00, 'saldo_akhir_debet' => 0, 'saldo_akhir_kredit' => 1622669817.00],
            ['kode_akun' => '310', 'debet' => 0, 'kredit' => 633861115.11, 'saldo_akhir_debet' => 0, 'saldo_akhir_kredit' => 756154819.11],
            ['kode_akun' => '320', 'debet' => 0, 'kredit' => 380457004.90, 'saldo_akhir_debet' => 0, 'saldo_akhir_kredit' => 378490176.61],
            ['kode_akun' => '360', 'debet' => 405821729.72, 'kredit' => 0, 'saldo_akhir_debet' => 409004729.72, 'saldo_akhir_kredit' => 0],
            ['kode_akun' => '500', 'debet' => 0, 'kredit' => 1655000.00, 'saldo_akhir_debet' => 0, 'saldo_akhir_kredit' => 1645000.00],
            ['kode_akun' => '510', 'debet' => 0, 'kredit' => 2332696850.00, 'saldo_akhir_debet' => 0, 'saldo_akhir_kredit' => 2344091850.00],
            ['kode_akun' => '520', 'debet' => 0, 'kredit' => 524800610.43, 'saldo_akhir_debet' => 0, 'saldo_akhir_kredit' => 524800610.43],
            ['kode_akun' => '525', 'debet' => 0, 'kredit' => 41238841.50, 'saldo_akhir_debet' => 0, 'saldo_akhir_kredit' => 41238841.50],
            ['kode_akun' => '530', 'debet' => 0, 'kredit' => 52141682.89, 'saldo_akhir_debet' => 0, 'saldo_akhir_kredit' => 48437182.89],
            ['kode_akun' => '540', 'debet' => 0, 'kredit' => 153342440.03, 'saldo_akhir_debet' => 0, 'saldo_akhir_kredit' => 153342440.03],
            ['kode_akun' => '550', 'debet' => 0, 'kredit' => 43220873.27, 'saldo_akhir_debet' => 0, 'saldo_akhir_kredit' => 43220873.27],
            ['kode_akun' => '600', 'debet' => 0, 'kredit' => 1688131455.97, 'saldo_akhir_debet' => 0, 'saldo_akhir_kredit' => 1684744801.22],
            ['kode_akun' => '700', 'debet' => 0, 'kredit' => 102489213.00, 'saldo_akhir_debet' => 0, 'saldo_akhir_kredit' => 118496451.00],
            ['kode_akun' => '703', 'debet' => 0, 'kredit' => 14675000.00, 'saldo_akhir_debet' => 0, 'saldo_akhir_kredit' => 32896000.00],
            ['kode_akun' => '711', 'debet' => 0, 'kredit' => 152028661.00, 'saldo_akhir_debet' => 0, 'saldo_akhir_kredit' => 260490472.00],
            ['kode_akun' => '712', 'debet' => 0, 'kredit' => 37725681.00, 'saldo_akhir_debet' => 0, 'saldo_akhir_kredit' => 37725681.00],
            ['kode_akun' => '713', 'debet' => 0, 'kredit' => 189744644.00, 'saldo_akhir_debet' => 0, 'saldo_akhir_kredit' => 195094644.00],
            ['kode_akun' => '716', 'debet' => 0, 'kredit' => 30091054.00, 'saldo_akhir_debet' => 0, 'saldo_akhir_kredit' => 30091054.00],
            ['kode_akun' => '718', 'debet' => 0, 'kredit' => 33022000.00, 'saldo_akhir_debet' => 0, 'saldo_akhir_kredit' => 33022000.00],
            ['kode_akun' => '719', 'debet' => 0, 'kredit' => 281721.23, 'saldo_akhir_debet' => 0, 'saldo_akhir_kredit' => 333764.74],
            ['kode_akun' => '800', 'debet' => 87680969.60, 'kredit' => 0, 'saldo_akhir_debet' => 249590994.20, 'saldo_akhir_kredit' => 0],
            ['kode_akun' => '811', 'debet' => 235183367.00, 'kredit' => 0, 'saldo_akhir_debet' => 296074810.00, 'saldo_akhir_kredit' => 0],
            ['kode_akun' => '812', 'debet' => 3726247.44, 'kredit' => 0, 'saldo_akhir_debet' => 34019934.88, 'saldo_akhir_kredit' => 0],
            ['kode_akun' => '813', 'debet' => 32107193.70, 'kredit' => 0, 'saldo_akhir_debet' => 201556690.46, 'saldo_akhir_kredit' => 0],
            ['kode_akun' => '816', 'debet' => 12243331.73, 'kredit' => 0, 'saldo_akhir_debet' => 43947813.46, 'saldo_akhir_kredit' => 0],
            ['kode_akun' => '818', 'debet' => 30020000.00, 'kredit' => 0, 'saldo_akhir_debet' => 77710500.00, 'saldo_akhir_kredit' => 0],
            ['kode_akun' => '840', 'debet' => 3589346.61, 'kredit' => 0, 'saldo_akhir_debet' => 25051163.61, 'saldo_akhir_kredit' => 0],
            ['kode_akun' => '900', 'debet' => 0, 'kredit' => 0, 'saldo_akhir_debet' => 0, 'saldo_akhir_kredit' => 97448071.13],
        ];

        foreach ($saldoAwalData as $sa) {
            SaldoAwal::create([
                'periode' => $periode,
                'kode_akun' => $sa['kode_akun'],
                'debet' => $sa['debet'],
                'kredit' => $sa['kredit'],
                'saldo_akhir_debet' => $sa['saldo_akhir_debet'],
                'saldo_akhir_kredit' => $sa['saldo_akhir_kredit'],
            ]);
        }

        // =============================================
        // KAS BANK FEBRUARI 2023 - SAMA DENGAN NATIVE
        // =============================================
        KasBank::create([
            'periode' => '2023-02-01',
            'jenis' => 'kas',
            'nama' => 'KAS',
            'saldo_awal' => 381070715.84,
            'mutasi_debet' => 892289771.00,
            'mutasi_kredit' => 1017592398.04,
            'saldo_akhir' => 255768088.80,
        ]);

        KasBank::create([
            'periode' => '2023-02-01',
            'jenis' => 'bank',
            'nama' => 'BANK',
            'saldo_awal' => 219188433.67,
            'mutasi_debet' => 604832641.51,
            'mutasi_kredit' => 686008582.70,
            'saldo_akhir' => 138012492.48,
        ]);

        // =============================================
        // ANGGARAN LABA RUGI FEBRUARI 2023 - SAMA DENGAN NATIVE
        // =============================================
        $anggaranData = [
            ['kode_akun' => '700', 'anggaran_tahun' => 1550000000.00, 'anggaran_triwulan' => 387500000.05, 'realisasi_bulan_lalu' => 102489213.00],
            ['kode_akun' => '703', 'anggaran_tahun' => 275000000.00, 'anggaran_triwulan' => 68750000.00, 'realisasi_bulan_lalu' => 14675000.00],
            ['kode_akun' => '711', 'anggaran_tahun' => 2000000000.00, 'anggaran_triwulan' => 500000000.00, 'realisasi_bulan_lalu' => 152028661.00],
            ['kode_akun' => '712', 'anggaran_tahun' => 420000000.00, 'anggaran_triwulan' => 105000000.00, 'realisasi_bulan_lalu' => 37725681.00],
            ['kode_akun' => '713', 'anggaran_tahun' => 3650000000.00, 'anggaran_triwulan' => 912500000.00, 'realisasi_bulan_lalu' => 189744644.00],
            ['kode_akun' => '714', 'anggaran_tahun' => 0.00, 'anggaran_triwulan' => 0.00, 'realisasi_bulan_lalu' => 0.00],
            ['kode_akun' => '715', 'anggaran_tahun' => 0.00, 'anggaran_triwulan' => 0.00, 'realisasi_bulan_lalu' => 0.00],
            ['kode_akun' => '716', 'anggaran_tahun' => 571800000.00, 'anggaran_triwulan' => 142950000.00, 'realisasi_bulan_lalu' => 30091054.00],
            ['kode_akun' => '718', 'anggaran_tahun' => 180000000.00, 'anggaran_triwulan' => 45000000.00, 'realisasi_bulan_lalu' => 33022000.00],
            ['kode_akun' => '719', 'anggaran_tahun' => 750000.00, 'anggaran_triwulan' => 187500.00, 'realisasi_bulan_lalu' => 281721.23],
            ['kode_akun' => '725', 'anggaran_tahun' => 0.00, 'anggaran_triwulan' => 0.00, 'realisasi_bulan_lalu' => 0.00],
            ['kode_akun' => '800', 'anggaran_tahun' => 1375000000.00, 'anggaran_triwulan' => 343750000.03, 'realisasi_bulan_lalu' => 87680969.60],
            ['kode_akun' => '803', 'anggaran_tahun' => 54000000.00, 'anggaran_triwulan' => 13500000.00, 'realisasi_bulan_lalu' => 0.00],
            ['kode_akun' => '811', 'anggaran_tahun' => 1550000000.00, 'anggaran_triwulan' => 387500000.00, 'realisasi_bulan_lalu' => 235183367.00],
            ['kode_akun' => '812', 'anggaran_tahun' => 400000000.00, 'anggaran_triwulan' => 100000000.00, 'realisasi_bulan_lalu' => 3726247.44],
            ['kode_akun' => '813', 'anggaran_tahun' => 2750000000.00, 'anggaran_triwulan' => 687500000.00, 'realisasi_bulan_lalu' => 32107193.70],
            ['kode_akun' => '814', 'anggaran_tahun' => 0.00, 'anggaran_triwulan' => 0.00, 'realisasi_bulan_lalu' => 0.00],
            ['kode_akun' => '815', 'anggaran_tahun' => 0.00, 'anggaran_triwulan' => 0.00, 'realisasi_bulan_lalu' => 0.00],
            ['kode_akun' => '816', 'anggaran_tahun' => 546800000.00, 'anggaran_triwulan' => 136700000.00, 'realisasi_bulan_lalu' => 12243331.73],
            ['kode_akun' => '818', 'anggaran_tahun' => 150000000.00, 'anggaran_triwulan' => 37500000.00, 'realisasi_bulan_lalu' => 30020000.00],
            ['kode_akun' => '820', 'anggaran_tahun' => 98000000.00, 'anggaran_triwulan' => 24500000.00, 'realisasi_bulan_lalu' => 0.00],
            ['kode_akun' => '825', 'anggaran_tahun' => 0.00, 'anggaran_triwulan' => 0.00, 'realisasi_bulan_lalu' => 0.00],
            ['kode_akun' => '830', 'anggaran_tahun' => 26000000.00, 'anggaran_triwulan' => 6500000.00, 'realisasi_bulan_lalu' => 0.00],
            ['kode_akun' => '840', 'anggaran_tahun' => 330000000.00, 'anggaran_triwulan' => 82500000.00, 'realisasi_bulan_lalu' => 3589346.61],
            ['kode_akun' => '841', 'anggaran_tahun' => 190000000.00, 'anggaran_triwulan' => 47500000.00, 'realisasi_bulan_lalu' => 0.00],
            ['kode_akun' => '846', 'anggaran_tahun' => 58500000.00, 'anggaran_triwulan' => 14625000.00, 'realisasi_bulan_lalu' => 0.00],
        ];

        foreach ($anggaranData as $anggaran) {
            AnggaranLabaRugi::create([
                'periode' => $periode,
                'kode_akun' => $anggaran['kode_akun'],
                'anggaran_tahun' => $anggaran['anggaran_tahun'],
                'anggaran_triwulan' => $anggaran['anggaran_triwulan'],
                'realisasi_bulan_lalu' => $anggaran['realisasi_bulan_lalu'],
            ]);
        }

        $this->command->info('Database seeded successfully with Native PHP data!');
    }
}
