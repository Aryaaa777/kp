<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration tabel `submission_reviews` (log transisi & catatan tinjauan) — F5.
 *
 * FR-141/142: setiap transisi menuliskan status lama → baru, aktor, waktu, catatan/alasan
 * (PRD §12.4). Bersifat **append-only** (tidak diubah dari UI).
 *
 * @see docs/DATA_DICTIONARY.md §16 (submission_reviews)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('submission_reviews', function (Blueprint $table) {
            $table->id();

            $table->foreignId('submission_id')->constrained('submissions')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();

            $table->string('from_status', 30)->nullable();
            $table->string('to_status', 30);

            // Wajib saat `needs_revision`/`rejected` (divalidasi di lapisan service/FormRequest).
            $table->text('note')->nullable();

            // Append-only: hanya created_at.
            $table->timestamp('created_at')->nullable();

            $table->index('submission_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('submission_reviews');
    }
};
