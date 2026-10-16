<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration tabel `activity_logs` (audit trail) — F5.
 *
 * FR-150: riwayat perubahan field-level (siapa, kapan, nilai lama → baru, entitas).
 * FR-151: audit log aktivitas (login, CRUD sensitif, perubahan status, unduh dokumen).
 *
 * Bersifat **append-only**: tidak dapat diubah/dihapus dari UI (NFR-SEC-012, AC-AUD-02).
 * Ditegakkan pada model (`updating`/`deleting` ditolak) dan Policy.
 *
 * @see docs/DATA_DICTIONARY.md §17 (activity_logs)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();

            // Null = aksi sistem.
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->string('action', 100);

            $table->string('subject_type', 150)->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();

            $table->text('description')->nullable();
            $table->json('properties')->nullable();

            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();

            // Append-only: hanya created_at.
            $table->timestamp('created_at')->nullable();

            $table->index(['subject_type', 'subject_id']);
            $table->index('user_id');
            $table->index('action');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
    }
};
