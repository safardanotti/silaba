<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\JenisSimpanan;
use App\Models\ProdukPinjaman;

class KoperasiSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Jenis Simpanan
        // Menggunakan kode_akun yang sudah ada di database:
        // 500 = SIMPANAN POKOK
        // 510 = SIMPANAN WAJIB
        // 320 = SIMPANAN SUKARELA
        $jenisSimpanan = [
            [
                'kode_simpanan' => 'SP',
                'nama_simpanan' => 'Simpanan Pokok',
                'minimal_setor' => 100000,
                'wajib_bulanan' => false,
                'keterangan' => 'Simpanan wajib yang dibayarkan saat pertama kali menjadi anggota',
                'kode_akun' => '500', // SIMPANAN POKOK
                'aktif' => true,
            ],
            [
                'kode_simpanan' => 'SW',
                'nama_simpanan' => 'Simpanan Wajib',
                'minimal_setor' => 50000,
                'wajib_bulanan' => true,
                'keterangan' => 'Simpanan wajib yang dibayarkan setiap bulan',
                'kode_akun' => '510', // SIMPANAN WAJIB
                'aktif' => true,
            ],
            [
                'kode_simpanan' => 'SS',
                'nama_simpanan' => 'Simpanan Sukarela',
                'minimal_setor' => 10000,
                'wajib_bulanan' => false,
                'keterangan' => 'Simpanan yang dapat disetor dan ditarik kapan saja',
                'kode_akun' => '320', // SIMPANAN SUKARELA
                'aktif' => true,
            ],
        ];

        foreach ($jenisSimpanan as $js) {
            JenisSimpanan::firstOrCreate(
                ['kode_simpanan' => $js['kode_simpanan']],
                $js
            );
        }

        // Produk Pinjaman
        // Menggunakan kode_akun yang sudah ada:
        // 120 = PIUTANG (untuk akun piutang)
        // 703 = PENDAPATAN SIMPAN PINJAM (untuk pendapatan bunga)
        $produkPinjaman = [
            [
                'kode_produk' => 'PDS',
                'nama_produk' => 'Pinjaman Dana Sejahtera',
                'bunga_persen' => 12.00, // 12% per tahun
                'max_tenor' => 12,
                'max_pinjaman' => 50000000,
                'min_pinjaman' => 1000000,
                'syarat_ketentuan' => "1. Anggota aktif minimal 6 bulan\n2. Tidak memiliki tunggakan pinjaman\n3. Melampirkan fotokopi KTP dan KK\n4. Melampirkan slip gaji 3 bulan terakhir",
                'kode_akun_piutang' => '120', // PIUTANG
                'kode_akun_bunga' => '703', // PENDAPATAN SIMPAN PINJAM
                'aktif' => true,
            ],
            [
                'kode_produk' => 'PKS',
                'nama_produk' => 'Pinjaman Konsumtif',
                'bunga_persen' => 15.00, // 15% per tahun
                'max_tenor' => 24,
                'max_pinjaman' => 100000000,
                'min_pinjaman' => 5000000,
                'syarat_ketentuan' => "1. Anggota aktif minimal 1 tahun\n2. Memiliki simpanan minimal Rp 1.000.000\n3. Tidak memiliki tunggakan pinjaman\n4. Melampirkan dokumen pendukung",
                'kode_akun_piutang' => '120', // PIUTANG
                'kode_akun_bunga' => '703', // PENDAPATAN SIMPAN PINJAM
                'aktif' => true,
            ],
            [
                'kode_produk' => 'PDR',
                'nama_produk' => 'Pinjaman Darurat',
                'bunga_persen' => 10.00, // 10% per tahun
                'max_tenor' => 6,
                'max_pinjaman' => 10000000,
                'min_pinjaman' => 500000,
                'syarat_ketentuan' => "1. Anggota aktif\n2. Untuk keperluan darurat (sakit, bencana, dll)\n3. Melampirkan surat keterangan",
                'kode_akun_piutang' => '120', // PIUTANG
                'kode_akun_bunga' => '703', // PENDAPATAN SIMPAN PINJAM
                'aktif' => true,
            ],
        ];

        foreach ($produkPinjaman as $pp) {
            ProdukPinjaman::firstOrCreate(
                ['kode_produk' => $pp['kode_produk']],
                $pp
            );
        }

        $this->command->info('Koperasi seeder completed!');
        $this->command->info('- 3 Jenis Simpanan created');
        $this->command->info('- 3 Produk Pinjaman created');
    }
}
