<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Struktur tabel SAMA PERSIS dengan silaba_db Native PHP
     */
    public function up(): void
    {
        // =============================================
        // KODE AKUN TABLE
        // =============================================
        Schema::create('kode_akun', function (Blueprint $table) {
            $table->string('kode_akun', 10)->primary();
            $table->string('nama_akun', 100);
            $table->enum('tipe_akun', ['aktiva', 'passiva', 'pendapatan', 'biaya']);
            $table->timestamp('created_at')->useCurrent();
        });

        // =============================================
        // TRANSAKSI TABLE
        // =============================================
        Schema::create('transaksi', function (Blueprint $table) {
            $table->id();
            $table->date('tanggal');
            $table->text('uraian_kegiatan');
            $table->enum('jenis_transaksi', ['penerimaan', 'pengeluaran', 'rupa_rupa']);
            $table->enum('jenis_masuk', ['kas', 'bank'])->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamp('created_at')->useCurrent();
            
            $table->foreign('created_by')->references('id')->on('users');
        });

        // =============================================
        // DETAIL TRANSAKSI TABLE
        // =============================================
        Schema::create('detail_transaksi', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('transaksi_id');
            $table->string('kode_akun', 10);
            $table->decimal('debet', 15, 2)->default(0);
            $table->decimal('kredit', 15, 2)->default(0);
            
            $table->foreign('transaksi_id')->references('id')->on('transaksi')->onDelete('cascade');
            $table->foreign('kode_akun')->references('kode_akun')->on('kode_akun');
        });

        // =============================================
        // SALDO AWAL TABLE
        // =============================================
        Schema::create('saldo_awal', function (Blueprint $table) {
            $table->id();
            $table->date('periode');
            $table->string('kode_akun', 10);
            $table->decimal('debet', 15, 2)->default(0);
            $table->decimal('kredit', 15, 2)->default(0);
            $table->decimal('saldo_akhir_debet', 15, 2)->default(0);
            $table->decimal('saldo_akhir_kredit', 15, 2)->default(0);
            $table->timestamp('created_at')->useCurrent();
            $table->boolean('is_initial')->default(false)->comment('Flag untuk saldo awal pertama kali');
            
            $table->foreign('kode_akun')->references('kode_akun')->on('kode_akun');
            $table->unique(['periode', 'kode_akun'], 'unique_periode_akun');
        });

        // =============================================
        // MASTER PIUTANG TABLE
        // =============================================
        Schema::create('master_piutang', function (Blueprint $table) {
            $table->id();
            $table->string('nama_debitur', 255)->unique();
            $table->text('keterangan')->nullable();
            $table->string('kode_akun_default', 10)->nullable();
            $table->boolean('aktif')->default(true);
            $table->timestamp('created_at')->useCurrent();
            
            $table->foreign('kode_akun_default')->references('kode_akun')->on('kode_akun');
        });

        // =============================================
        // TRANSAKSI PIUTANG TABLE
        // =============================================
        Schema::create('transaksi_piutang', function (Blueprint $table) {
            $table->id();
            $table->date('periode');
            $table->unsignedBigInteger('master_piutang_id');
            $table->decimal('saldo_awal', 15, 2)->default(0);
            $table->decimal('mutasi_debet', 15, 2)->default(0);
            $table->decimal('mutasi_kredit', 15, 2)->default(0);
            $table->decimal('saldo_akhir', 15, 2)->default(0);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
            
            $table->foreign('master_piutang_id')->references('id')->on('master_piutang');
            $table->foreign('created_by')->references('id')->on('users');
            $table->unique(['periode', 'master_piutang_id'], 'unique_periode_piutang');
        });

        // =============================================
        // ANGGARAN LABA RUGI TABLE
        // =============================================
        Schema::create('anggaran_laba_rugi', function (Blueprint $table) {
            $table->id();
            $table->date('periode');
            $table->string('kode_akun', 10);
            $table->decimal('anggaran_tahun', 20, 2)->default(0);
            $table->decimal('anggaran_triwulan', 20, 2)->default(0);
            $table->decimal('realisasi_bulan_lalu', 20, 2)->default(0);
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
            
            $table->foreign('kode_akun')->references('kode_akun')->on('kode_akun');
            $table->unique(['periode', 'kode_akun'], 'unique_periode_kode');
        });

        // =============================================
        // KAS BANK TABLE
        // =============================================
        Schema::create('kas_bank', function (Blueprint $table) {
            $table->id();
            $table->date('periode');
            $table->enum('jenis', ['kas', 'bank']);
            $table->string('nama', 100);
            $table->decimal('saldo_awal', 20, 2)->default(0);
            $table->decimal('mutasi_debet', 20, 2)->default(0);
            $table->decimal('mutasi_kredit', 20, 2)->default(0);
            $table->decimal('saldo_akhir', 20, 2)->default(0);
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
            
            $table->unique(['periode', 'jenis', 'nama'], 'unique_periode_jenis_nama');
        });

        // =============================================
        // NERACA POSTING TABLE
        // =============================================
        Schema::create('neraca_posting', function (Blueprint $table) {
            $table->id();
            $table->date('periode')->unique();
            $table->enum('status', ['draft', 'posted', 'revisi', 'approved'])->default('draft');
            $table->unsignedBigInteger('posted_by')->nullable();
            $table->timestamp('posted_at')->nullable();
            $table->unsignedBigInteger('reviewed_by')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('catatan_revisi')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
            
            $table->foreign('posted_by')->references('id')->on('users');
            $table->foreign('reviewed_by')->references('id')->on('users');
        });

        // =============================================
        // NERACA POSTING DETAIL TABLE
        // =============================================
        Schema::create('neraca_posting_detail', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('posting_id');
            $table->string('kode_akun', 10);
            $table->string('nama_akun', 100);
            $table->string('tipe_akun', 50);
            $table->decimal('saldo_debet', 15, 2)->default(0);
            $table->decimal('saldo_kredit', 15, 2)->default(0);
            
            $table->foreign('posting_id')->references('id')->on('neraca_posting')->onDelete('cascade');
            $table->index(['posting_id', 'kode_akun'], 'idx_posting_kode');
        });

        // =============================================
        // NERACA REVISI HISTORY TABLE
        // =============================================
        Schema::create('neraca_revisi_history', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('posting_id');
            $table->unsignedBigInteger('user_id');
            $table->enum('action', ['post', 'revisi', 'approve']);
            $table->text('catatan')->nullable();
            $table->timestamp('created_at')->useCurrent();
            
            $table->foreign('posting_id')->references('id')->on('neraca_posting')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('neraca_revisi_history');
        Schema::dropIfExists('neraca_posting_detail');
        Schema::dropIfExists('neraca_posting');
        Schema::dropIfExists('kas_bank');
        Schema::dropIfExists('anggaran_laba_rugi');
        Schema::dropIfExists('transaksi_piutang');
        Schema::dropIfExists('master_piutang');
        Schema::dropIfExists('saldo_awal');
        Schema::dropIfExists('detail_transaksi');
        Schema::dropIfExists('transaksi');
        Schema::dropIfExists('kode_akun');
    }
};
