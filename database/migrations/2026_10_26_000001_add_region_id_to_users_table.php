<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Menambahkan `users.region_id` untuk peran `admin_wilayah` (FR-012).
 *
 * PROVISIONAL — pembatasan wilayah bersifat konfigurasi (`koperasi.admin_region_scope.enabled`)
 * dan default **nonaktif** sampai dinas mengonfirmasi aturan resmi (Q-005).
 *
 * `nullOnDelete` dipilih agar penghapusan referensi wilayah tidak memblokir operasi;
 * akun admin wilayah tanpa wilayah tidak dapat mengakses data koperasi saat scope aktif.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('region_id')
                ->nullable()
                ->after('koperasi_id')
                ->constrained('regions')
                ->nullOnDelete()
                ->comment('Wilayah kewenangan admin_wilayah (FR-012, PROVISIONAL)');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('region_id');
        });
    }
};
