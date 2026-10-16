<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration tabel `submissions` (pengajuan perubahan data resmi) — F5.
 *
 * FR-140: pengelola membuat & mengirim pengajuan.
 * FR-141/142: admin meninjau, menyetujui, meminta perbaikan, atau menolak.
 * FR-143: perubahan diterapkan ke data resmi saat `approved`.
 *
 * Status mengikuti PRD §12.2 (matriks transisi resmi). Daftar status **tetap** dan
 * didefinisikan pada PRD, sehingga disimpan sebagai ENUM (konsisten dengan
 * `cooperatives.status_verifikasi`).
 *
 * Q-004 (prosedur verifikasi multi-tahap) masih terbuka → MVP memakai **satu tingkat**
 * verifikasi oleh `admin_dinas`. Lihat `docs/DECISIONS.md` §12.
 *
 * @see docs/DATA_DICTIONARY.md §14 (submissions)
 * @see docs/PRD.md §12 (workflow pengajuan)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('submissions', function (Blueprint $table) {
            $table->id();

            $table->foreignId('cooperative_id')->constrained('cooperatives')->restrictOnDelete();
            $table->foreignId('submitted_by')->constrained('users')->restrictOnDelete();

            // Modul data yang diajukan (mis. `koperasi_induk`). Whitelist ada di config/koperasi.php (Q-006).
            $table->string('module', 50);
            $table->string('title', 200)->nullable();

            $table->enum('status', [
                'draft',
                'submitted',
                'under_review',
                'needs_revision',
                'approved',
                'rejected',
                'archived',
            ])->default('draft');

            // Alasan tolak/perbaikan terakhir (FR-142).
            $table->text('reason')->nullable();

            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('applied_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index('cooperative_id');
            $table->index('status');
            $table->index('module');
            $table->index('submitted_by');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('submissions');
    }
};
