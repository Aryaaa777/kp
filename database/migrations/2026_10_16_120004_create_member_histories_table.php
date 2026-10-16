<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration tabel `member_histories` (riwayat keanggotaan, berbasis kejadian).
 *
 * FR-110: mencatat perubahan keanggotaan (admin penuh).
 * FR-111: riwayat keanggotaan koperasi.
 *
 * PROVISIONAL — MENUNGGU KONFIRMASI DINAS (Q-010):
 * - **Berbasis kejadian/perubahan** (bukan rekap tahunan). Ini menyimpang dari usulan awal
 *   Data Dictionary §12 (`period_year` + rekap total/aktif/masuk/keluar); penyimpangan
 *   didokumentasikan di `docs/DECISIONS.md` §10 dan `docs/DATA_DICTIONARY.md` §12.
 * - TIDAK mengumpulkan data anggota individual / NIK anggota / data pribadi anggota.
 *
 * @see docs/DATA_DICTIONARY.md §12 (member_histories)
 * @see docs/DECISIONS.md §10 (aturan provisional)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('member_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cooperative_id')
                ->constrained('cooperatives')
                ->restrictOnDelete()
                ->comment('FK koperasi (NN)');
            $table->date('event_date')->comment('Tanggal kejadian/perubahan');
            $table->string('change_type', 30)
                ->comment('PROVISIONAL: jenis perubahan (nilai dari config, belum resmi)');
            $table->unsignedInteger('members_added')->nullable()
                ->comment('Jumlah anggota bertambah (opsional)');
            $table->unsignedInteger('members_removed')->nullable()
                ->comment('Jumlah anggota berkurang (opsional)');
            $table->text('notes')->nullable()->comment('Catatan');
            $table->foreignId('recorded_by')
                ->constrained('users')
                ->restrictOnDelete()
                ->comment('Pencatat — diisi server-side');
            $table->timestamps();
            $table->softDeletes()->comment('Soft delete (BR-011: pengarsipan)');

            $table->index(['cooperative_id', 'event_date']);
            $table->index('change_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('member_histories');
    }
};
