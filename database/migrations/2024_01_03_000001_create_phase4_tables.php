<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Tidak ada yang perlu di-migrate - sudah digabung ke migration sebelumnya
    }

    public function down(): void
    {
        // Tidak ada yang perlu di-rollback
    }
};
