<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Tabel untuk Sistem Koperasi - Anggota, Pinjaman, Simpanan
     */
    public function up(): void
    {
        // =============================================
        // ANGGOTA TABLE - Data Master Anggota Koperasi
        // =============================================
        Schema::create('anggota', function (Blueprint $table) {
            $table->id();
            $table->string('no_anggota', 20)->unique();
            $table->string('nama_anggota', 100);
            $table->string('nik', 16)->unique()->nullable();
            $table->string('tempat_lahir', 50)->nullable();
            $table->date('tanggal_lahir')->nullable();
            $table->enum('jenis_kelamin', ['L', 'P'])->nullable();
            $table->text('alamat')->nullable();
            $table->string('no_hp', 20)->nullable();
            $table->string('email', 100)->nullable();
            $table->string('unit_kerja', 100)->nullable(); // PT PDS, dll
            $table->string('jabatan', 100)->nullable();
            $table->date('tanggal_masuk')->nullable();
            $table->enum('status_anggota', ['aktif', 'tidak_aktif', 'keluar'])->default('aktif');
            $table->string('foto', 255)->nullable();
            $table->text('keterangan')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
            
            $table->foreign('created_by')->references('id')->on('users');
            $table->index('nama_anggota');
            $table->index('unit_kerja');
            $table->index('status_anggota');
        });

        // =============================================
        // MODIFY USERS TABLE - Tambah kolom untuk anggota
        // =============================================
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedBigInteger('anggota_id')->nullable()->after('role');
            $table->string('status', 20)->default('active')->after('anggota_id');
            
            $table->foreign('anggota_id')->references('id')->on('anggota')->onDelete('set null');
        });

        // Update role column untuk support 'anggota'
        // Karena enum sulit diubah, kita bisa langsung gunakan string
        DB::statement("ALTER TABLE users MODIFY COLUMN role VARCHAR(20) DEFAULT 'staff'");

        // =============================================
        // JENIS SIMPANAN TABLE - Master Jenis Simpanan
        // =============================================
        Schema::create('jenis_simpanan', function (Blueprint $table) {
            $table->id();
            $table->string('kode_simpanan', 10)->unique();
            $table->string('nama_simpanan', 100);
            $table->decimal('minimal_setor', 15, 2)->default(0);
            $table->boolean('wajib_bulanan')->default(false);
            $table->text('keterangan')->nullable();
            $table->string('kode_akun', 10)->nullable(); // Link ke kode_akun
            $table->boolean('aktif')->default(true);
            $table->timestamps();
            
            $table->foreign('kode_akun')->references('kode_akun')->on('kode_akun');
        });

        // =============================================
        // SIMPANAN ANGGOTA TABLE - Data Simpanan Tiap Anggota
        // =============================================
        Schema::create('simpanan_anggota', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('anggota_id');
            $table->unsignedBigInteger('jenis_simpanan_id');
            $table->date('tanggal_transaksi');
            $table->enum('jenis_transaksi', ['setor', 'tarik']);
            $table->decimal('jumlah', 15, 2);
            $table->decimal('saldo_sebelum', 15, 2)->default(0);
            $table->decimal('saldo_sesudah', 15, 2)->default(0);
            $table->text('keterangan')->nullable();
            $table->unsignedBigInteger('transaksi_id')->nullable(); // Link ke transaksi akuntansi
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
            
            $table->foreign('anggota_id')->references('id')->on('anggota')->onDelete('cascade');
            $table->foreign('jenis_simpanan_id')->references('id')->on('jenis_simpanan');
            $table->foreign('transaksi_id')->references('id')->on('transaksi')->onDelete('set null');
            $table->foreign('created_by')->references('id')->on('users');
            $table->index(['anggota_id', 'tanggal_transaksi']);
        });

        // =============================================
        // SALDO SIMPANAN TABLE - Ringkasan Saldo Simpanan
        // =============================================
        Schema::create('saldo_simpanan', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('anggota_id');
            $table->unsignedBigInteger('jenis_simpanan_id');
            $table->decimal('total_saldo', 15, 2)->default(0);
            $table->timestamps();
            
            $table->foreign('anggota_id')->references('id')->on('anggota')->onDelete('cascade');
            $table->foreign('jenis_simpanan_id')->references('id')->on('jenis_simpanan');
            $table->unique(['anggota_id', 'jenis_simpanan_id']);
        });

        // =============================================
        // PRODUK PINJAMAN TABLE - Master Jenis Pinjaman
        // =============================================
        Schema::create('produk_pinjaman', function (Blueprint $table) {
            $table->id();
            $table->string('kode_produk', 10)->unique();
            $table->string('nama_produk', 100);
            $table->decimal('bunga_persen', 5, 2)->default(0); // Bunga per tahun
            $table->integer('max_tenor')->default(12); // Maksimal tenor dalam bulan
            $table->decimal('max_pinjaman', 15, 2)->default(0);
            $table->decimal('min_pinjaman', 15, 2)->default(0);
            $table->text('syarat_ketentuan')->nullable();
            $table->string('kode_akun_piutang', 10)->nullable(); // Akun piutang
            $table->string('kode_akun_bunga', 10)->nullable(); // Akun pendapatan bunga
            $table->boolean('aktif')->default(true);
            $table->timestamps();
            
            $table->foreign('kode_akun_piutang')->references('kode_akun')->on('kode_akun');
            $table->foreign('kode_akun_bunga')->references('kode_akun')->on('kode_akun');
        });

        // =============================================
        // PENGAJUAN PINJAMAN TABLE - Permohonan Pinjaman Anggota
        // =============================================
        Schema::create('pengajuan_pinjaman', function (Blueprint $table) {
            $table->id();
            $table->string('no_pengajuan', 30)->unique();
            $table->unsignedBigInteger('anggota_id');
            $table->unsignedBigInteger('produk_pinjaman_id');
            $table->decimal('jumlah_pinjaman', 15, 2);
            $table->integer('tenor'); // Jangka waktu dalam bulan
            $table->text('keperluan')->nullable();
            $table->enum('status', ['pending', 'diproses', 'disetujui', 'ditolak', 'dicairkan'])->default('pending');
            
            // Dokumen persyaratan
            $table->string('dok_ktp', 255)->nullable();
            $table->string('dok_kk', 255)->nullable();
            $table->string('dok_slip_gaji', 255)->nullable();
            $table->string('dok_lainnya', 255)->nullable();
            
            // Approval
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->text('catatan_approval')->nullable();
            
            $table->timestamps();
            
            $table->foreign('anggota_id')->references('id')->on('anggota');
            $table->foreign('produk_pinjaman_id')->references('id')->on('produk_pinjaman');
            $table->foreign('approved_by')->references('id')->on('users');
            $table->index(['anggota_id', 'status']);
        });

        // =============================================
        // PINJAMAN TABLE - Data Pinjaman yang Disetujui
        // =============================================
        Schema::create('pinjaman', function (Blueprint $table) {
            $table->id();
            $table->string('no_pinjaman', 30)->unique();
            $table->unsignedBigInteger('pengajuan_id')->nullable();
            $table->unsignedBigInteger('anggota_id');
            $table->unsignedBigInteger('produk_pinjaman_id');
            $table->date('tanggal_pinjaman');
            $table->date('tanggal_jatuh_tempo');
            $table->decimal('jumlah_pinjaman', 15, 2);
            $table->decimal('bunga_persen', 5, 2)->default(0);
            $table->integer('tenor'); // Jangka waktu dalam bulan
            $table->decimal('angsuran_pokok', 15, 2)->default(0);
            $table->decimal('angsuran_bunga', 15, 2)->default(0);
            $table->decimal('total_angsuran', 15, 2)->default(0); // Per bulan
            $table->decimal('saldo_pokok', 15, 2)->default(0); // Sisa pokok
            $table->decimal('saldo_bunga', 15, 2)->default(0); // Sisa bunga
            $table->integer('angsuran_ke')->default(0); // Angsuran terakhir dibayar
            $table->enum('status', ['aktif', 'lunas', 'macet', 'restruktur'])->default('aktif');
            $table->unsignedBigInteger('transaksi_id')->nullable(); // Transaksi pencairan
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
            
            $table->foreign('pengajuan_id')->references('id')->on('pengajuan_pinjaman')->onDelete('set null');
            $table->foreign('anggota_id')->references('id')->on('anggota');
            $table->foreign('produk_pinjaman_id')->references('id')->on('produk_pinjaman');
            $table->foreign('transaksi_id')->references('id')->on('transaksi')->onDelete('set null');
            $table->foreign('created_by')->references('id')->on('users');
            $table->index(['anggota_id', 'status']);
        });

        // =============================================
        // ANGSURAN PINJAMAN TABLE - Detail Pembayaran Angsuran
        // =============================================
        Schema::create('angsuran_pinjaman', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('pinjaman_id');
            $table->integer('angsuran_ke');
            $table->date('tanggal_jatuh_tempo');
            $table->date('tanggal_bayar')->nullable();
            $table->decimal('angsuran_pokok', 15, 2)->default(0);
            $table->decimal('angsuran_bunga', 15, 2)->default(0);
            $table->decimal('total_angsuran', 15, 2)->default(0);
            $table->decimal('jumlah_bayar', 15, 2)->default(0);
            $table->decimal('denda', 15, 2)->default(0);
            $table->decimal('sisa_pokok_sebelum', 15, 2)->default(0);
            $table->decimal('sisa_pokok_sesudah', 15, 2)->default(0);
            $table->enum('status', ['belum_bayar', 'sebagian', 'lunas', 'terlambat'])->default('belum_bayar');
            $table->unsignedBigInteger('transaksi_id')->nullable(); // Link ke transaksi akuntansi
            $table->text('keterangan')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
            
            $table->foreign('pinjaman_id')->references('id')->on('pinjaman')->onDelete('cascade');
            $table->foreign('transaksi_id')->references('id')->on('transaksi')->onDelete('set null');
            $table->foreign('created_by')->references('id')->on('users');
            $table->unique(['pinjaman_id', 'angsuran_ke']);
            $table->index(['pinjaman_id', 'status']);
        });

        // =============================================
        // PENDAFTARAN ANGGOTA TABLE - Pendaftaran Online
        // =============================================
        Schema::create('pendaftaran_anggota', function (Blueprint $table) {
            $table->id();
            $table->string('nama_lengkap', 100);
            $table->string('nik', 16)->unique();
            $table->string('tempat_lahir', 50)->nullable();
            $table->date('tanggal_lahir')->nullable();
            $table->enum('jenis_kelamin', ['L', 'P'])->nullable();
            $table->text('alamat')->nullable();
            $table->string('no_hp', 20);
            $table->string('email', 100)->unique();
            $table->string('unit_kerja', 100)->nullable();
            $table->string('jabatan', 100)->nullable();
            
            // Dokumen
            $table->string('dok_ktp', 255)->nullable();
            $table->string('dok_kk', 255)->nullable();
            $table->string('dok_foto', 255)->nullable();
            
            $table->enum('status', ['pending', 'disetujui', 'ditolak'])->default('pending');
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->text('catatan')->nullable();
            $table->timestamps();
            
            $table->foreign('approved_by')->references('id')->on('users');
        });

        // =============================================
        // NOTIFIKASI TABLE - Sistem Notifikasi
        // =============================================
        Schema::create('notifikasi', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('judul', 200);
            $table->text('pesan');
            $table->string('tipe', 50)->default('info'); // info, success, warning, danger
            $table->string('link', 255)->nullable();
            $table->boolean('dibaca')->default(false);
            $table->timestamps();
            
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->index(['user_id', 'dibaca']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notifikasi');
        Schema::dropIfExists('pendaftaran_anggota');
        Schema::dropIfExists('angsuran_pinjaman');
        Schema::dropIfExists('pinjaman');
        Schema::dropIfExists('pengajuan_pinjaman');
        Schema::dropIfExists('produk_pinjaman');
        Schema::dropIfExists('saldo_simpanan');
        Schema::dropIfExists('simpanan_anggota');
        Schema::dropIfExists('jenis_simpanan');
        
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['anggota_id']);
            $table->dropColumn(['anggota_id', 'status']);
        });
        
        // Revert role column back to enum
        DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('admin', 'pimpinan', 'staff') DEFAULT 'staff'");
        
        Schema::dropIfExists('anggota');
    }
};
