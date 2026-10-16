<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration tabel `submission_items` (detail perubahan field-level) — F5.
 *
 * FR-150: menyimpan nilai lama → nilai baru per field sehingga data resmi tidak
 * tertimpa sebelum `approved` (BR-014, PRD §12.3).
 *
 * @see docs/DATA_DICTIONARY.md §15 (submission_items)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('submission_items', function (Blueprint $table) {
            $table->id();

            $table->foreignId('submission_id')->constrained('submissions')->cascadeOnDelete();

            $table->string('field', 150);
            // Nilai disimpan sebagai teks (skalar); nilai kompleks dapat berupa JSON.
            $table->text('old_value')->nullable();
            $table->text('new_value')->nullable();

            $table->timestamps();

            // Satu item per field per pengajuan (L4). Indeks unik ini sekaligus memenuhi
            // kebutuhan indeks pada kolom submission_id.
            $table->unique(['submission_id', 'field']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('submission_items');
    }
};
