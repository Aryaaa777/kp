<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration tabel `health_assessments` (penilaian kesehatan koperasi).
 *
 * FR-080: mencatat hasil penilaian kesehatan per periode (kewenangan admin, BR-010).
 * FR-081: pengelola melihat riwayat kesehatan koperasinya (read-only).
 *
 * PROVISIONAL — MENUNGGU KONFIRMASI DINAS (Q-010, Q-007):
 * - Periodisitas dibatasi **tahunan** untuk simulasi. `period` tetap disimpan sebagai
 *   VARCHAR (bukan ENUM DB) agar semester/triwulan/bulanan dapat ditambahkan tanpa
 *   migrasi destruktif setelah dinas menetapkan aturannya.
 * - Skor & predikat adalah **input manual** opsional; TIDAK ada formula resmi.
 * - `status` `final` hanya penanda internal, BUKAN klasifikasi resmi dinas.
 *
 * @see docs/DATA_DICTIONARY.md §9 (health_assessments)
 * @see docs/DECISIONS.md §10 (aturan provisional)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('health_assessments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cooperative_id')
                ->constrained('cooperatives')
                ->restrictOnDelete()
                ->comment('FK koperasi (NN)');
            $table->unsignedSmallInteger('period_year')->comment('Tahun penilaian');
            $table->string('period', 20)->default('annual')
                ->comment('PROVISIONAL: periodisitas (Q-010). Default annual.');
            $table->date('assessment_date')->comment('Tanggal penilaian');
            $table->decimal('score', 6, 2)->nullable()
                ->comment('Skor total (input manual opsional; tanpa formula resmi)');
            $table->string('predicate', 50)->nullable()
                ->comment('Predikat sementara (teks manual, BUKAN klasifikasi resmi)');
            $table->foreignId('assessor_id')->nullable()
                ->constrained('users')->nullOnDelete()
                ->comment('Penilai (admin dinas) — diisi server-side');
            $table->text('notes')->nullable()->comment('Catatan pendukung');
            $table->string('status', 20)->default('draft')
                ->comment('draft|final (final = penanda internal, bukan pengesahan resmi)');
            $table->timestamps();
            $table->softDeletes()->comment('Soft delete (BR-011: pengarsipan)');

            $table->unique(['cooperative_id', 'period_year', 'period'], 'health_assessments_unique_period');
            $table->index('period_year');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('health_assessments');
    }
};
