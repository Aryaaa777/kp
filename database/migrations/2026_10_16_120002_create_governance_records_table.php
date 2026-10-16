<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration tabel `governance_records` (data tata kelola koperasi).
 *
 * FR-090: mencatat data tata kelola per periode (admin penuh).
 * FR-091: evaluasi kepatuhan (FASE-2, NEEDS CONFIRMATION) — tidak diimplementasikan.
 *
 * PROVISIONAL — MENUNGGU KONFIRMASI DINAS (Q-010):
 * - Periodisitas dibatasi **tahunan** untuk simulasi.
 * - `period` dibuat NOT NULL (default annual) agar unique constraint (koperasi, tahun, periode)
 *   benar-benar berlaku (di MySQL, NULL ganda lolos unique). Data Dictionary §10 menyebut
 *   `period` nullable — penyimpangan ini didokumentasikan di `docs/DECISIONS.md` §10.
 * - Indikator resmi tata kelola belum ada; hanya `meeting_count` (contoh) & `summary` manual.
 *
 * @see docs/DATA_DICTIONARY.md §10 (governance_records)
 * @see docs/DECISIONS.md §10 (aturan provisional)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('governance_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cooperative_id')
                ->constrained('cooperatives')
                ->restrictOnDelete()
                ->comment('FK koperasi (NN)');
            $table->unsignedSmallInteger('period_year')->comment('Tahun pencatatan');
            $table->string('period', 20)->default('annual')
                ->comment('PROVISIONAL: periodisitas (Q-010). Default annual.');
            $table->unsignedInteger('meeting_count')->nullable()
                ->comment('Contoh indikator: jumlah rapat (bukan indikator resmi)');
            $table->decimal('governance_score', 6, 2)->nullable()
                ->comment('Ringkasan skor opsional (input manual, tanpa formula resmi)');
            $table->text('summary')->nullable()->comment('Catatan evaluasi');
            $table->string('status', 20)->default('draft')
                ->comment('draft|final (final = penanda internal)');
            $table->foreignId('recorded_by')
                ->constrained('users')
                ->restrictOnDelete()
                ->comment('Pencatat — diisi server-side');
            $table->timestamps();
            $table->softDeletes()->comment('Soft delete (BR-011: pengarsipan)');

            $table->unique(['cooperative_id', 'period_year', 'period'], 'governance_records_unique_period');
            $table->index('period_year');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('governance_records');
    }
};
