<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Menambahkan riwayat versi & arsip dokumen (FR-072).
 *
 * Versi baru menunjuk versi sebelumnya lewat `previous_version_id`; versi lama
 * ditandai `superseded_at` (tidak dihapus, BR-011). Berkas fisik tetap disimpan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->foreignId('previous_version_id')
                ->nullable()
                ->after('version')
                ->constrained('documents')
                ->nullOnDelete()
                ->comment('Versi sebelumnya (FR-072)');
            $table->timestamp('superseded_at')->nullable()->after('previous_version_id')
                ->comment('Waktu dokumen digantikan versi lebih baru (FR-072)');
            $table->index('superseded_at');
        });
    }

    public function down(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->dropIndex(['superseded_at']);
            $table->dropConstrainedForeignId('previous_version_id');
            $table->dropColumn('superseded_at');
        });
    }
};
