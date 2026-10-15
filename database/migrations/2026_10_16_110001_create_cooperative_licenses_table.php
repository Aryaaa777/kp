<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Izin usaha & izin usaha simpan pinjam (F3 — FR-050/051/060/061).
     *
     * Rancangan mengikuti `docs/DATA_DICTIONARY.md` §7 (satu tabel untuk dua jenis izin,
     * dibedakan kolom `license_type`).
     *
     * Catatan sementara (lihat `docs/DECISIONS.md` §5):
     * - `status` awal `pending`; perubahan status hanya melalui aksi verifikasi admin (FR-051/061),
     *   bukan lewat form. Nilai valid enum belum dikonfirmasi dinas (Q-004).
     * - Unik (`cooperative_id`, `license_type`, `nomor`) berlaku **termasuk data terarsip**
     *   (soft delete), konsisten dengan indeks unik basis data — asumsi sementara [ASUMSI].
     */
    public function up(): void
    {
        Schema::create('cooperative_licenses', function (Blueprint $table) {
            $table->id();

            $table->foreignId('cooperative_id')
                ->constrained('cooperatives')
                ->cascadeOnDelete();

            $table->enum('license_type', ['business', 'savings_loans'])->index();
            $table->string('nomor', 100);
            $table->date('tanggal_terbit');
            $table->date('tanggal_berlaku_sampai')->nullable()->index();
            $table->string('penerbit', 200)->nullable();

            // Status verifikasi izin (FR-051/061). Nilai valid belum dikonfirmasi dinas (Q-004).
            $table->enum('status', ['active', 'expired', 'revoked', 'pending'])->default('pending')->index();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->text('catatan')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->unique(
                ['cooperative_id', 'license_type', 'nomor'],
                'licenses_coop_type_nomor_unique',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cooperative_licenses');
    }
};
