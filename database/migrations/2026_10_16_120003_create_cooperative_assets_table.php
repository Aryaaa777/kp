<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration tabel `cooperative_assets` (aset koperasi per periode).
 *
 * FR-100: mencatat aset per periode.
 * FR-101: riwayat aset antar periode (tanpa menimpa nilai lama, BR-007).
 *
 * PROVISIONAL — MENUNGGU KONFIRMASI DINAS (Q-010):
 * - Snapshot **tahunan** untuk simulasi (`period_year`).
 * - `quantity`, `acquisition_date`, `condition` BELUM ditetapkan Data Dictionary §11;
 *   ditambahkan sebagai field minimum untuk simulasi dan ditandai provisional.
 * - Tidak ada integrasi akuntansi/perhitungan keuangan otomatis.
 * - Beberapa baris per periode diizinkan (multi jenis aset) → tidak ada unique periode.
 *
 * @see docs/DATA_DICTIONARY.md §11 (cooperative_assets)
 * @see docs/DECISIONS.md §10 (aturan provisional)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cooperative_assets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cooperative_id')
                ->constrained('cooperatives')
                ->restrictOnDelete()
                ->comment('FK koperasi (NN)');
            $table->unsignedSmallInteger('period_year')->comment('Tahun snapshot aset');
            $table->string('asset_type', 100)
                ->comment('Jenis/kategori aset (teks bebas; taksonomi resmi belum ada)');
            $table->string('asset_name', 200)->nullable()->comment('Nama detail aset');
            $table->unsignedInteger('quantity')->nullable()
                ->comment('PROVISIONAL: jumlah (belum ditetapkan dictionary)');
            $table->decimal('value', 18, 2)->comment('Nilai (Rupiah, input manual)');
            $table->date('acquisition_date')->nullable()
                ->comment('PROVISIONAL: tanggal perolehan/terkait');
            $table->string('condition', 30)->nullable()
                ->comment('PROVISIONAL: kondisi aset (nilai dari config, belum resmi)');
            $table->text('description')->nullable()->comment('Keterangan');
            $table->foreignId('recorded_by')
                ->constrained('users')
                ->restrictOnDelete()
                ->comment('Pencatat — diisi server-side');
            $table->timestamps();
            $table->softDeletes()->comment('Soft delete (BR-011: pengarsipan)');

            $table->index(['cooperative_id', 'period_year']);
            $table->index('asset_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cooperative_assets');
    }
};
