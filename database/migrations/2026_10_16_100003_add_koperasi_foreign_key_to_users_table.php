<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Menambahkan foreign key `users.koperasi_id` → `cooperatives.id`.
     *
     * Kolomnya sendiri sudah dibuat pada F1; constraint ditambahkan di sini setelah tabel
     * `cooperatives` tersedia. `nullOnDelete` dipilih karena kolom nullable: bila koperasi
     * benar-benar dihapus (hard delete; operasional memakai soft delete), akun pengelola
     * dilepas tautannya alih-alih memblokir penghapusan.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreign('koperasi_id')
                ->references('id')
                ->on('cooperatives')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['koperasi_id']);
        });
    }
};
