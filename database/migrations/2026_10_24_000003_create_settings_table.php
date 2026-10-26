<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Tabel pengaturan aplikasi sederhana (key/value). Dipakai FR-191 untuk
     * menyimpan pilihan field publik yang dapat diubah admin tanpa mengubah
     * file konfigurasi pada server. Nilai tetap divalidasi terhadap allowlist
     * Q-008 di sisi server; tabel ini bukan mekanisme otorisasi.
     */
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('key', 100)->unique();
            $table->text('value')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
