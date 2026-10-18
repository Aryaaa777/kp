<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration tabel `officers` (susunan pengurus koperasi).
 *
 * FR-120: CRUD susunan pengurus & periode jabatan.
 * FR-121: Status aktif/nonaktif pengurus.
 *
 * Implementasi F4 PARTIAL — hanya modul pengurus. Modul kesehatan, tata kelola, aset,
 * dan anggota menunggu konfirmasi Q-010 (periodisitas tahunan/semesteran/bulanan).
 *
 * CATATAN URUTAN FK: foreign key `cooperative_id` → `cooperatives` **tidak** dibuat di sini,
 * karena tabel `cooperatives` baru dibuat oleh migrasi `2026_10_16_100002` (timestamp lebih
 * besar) sehingga FK inline menyebabkan `errno 150` pada instalasi dari nol (MariaDB). FK
 * ditambahkan oleh `2026_10_16_100004_add_cooperative_foreign_key_to_officers_table`.
 *
 * @see docs/DATA_DICTIONARY.md §13 (officers)
 * @see docs/PRD.md FR-120, FR-121
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('officers', function (Blueprint $table) {
            $table->id();

            // Kolom FK koperasi (NN). Constraint `cooperative_id` → `cooperatives(id)`
            // (ON DELETE RESTRICT) ditambahkan pada migrasi `2026_10_16_100004`, yaitu
            // setelah tabel induk `cooperatives` dibuat (lihat catatan urutan FK di atas).
            $table->foreignId('cooperative_id')
                ->comment('FK koperasi (NN) — constraint ditambahkan pada migrasi 100004');
            $table->string('name', 150)->comment('Nama pengurus');
            $table->string('position', 100)->comment('Jabatan');
            $table->string('identity_number', 50)->nullable()
                ->comment('Nomor identitas (data pribadi, akses terbatas, tidak dipublikasikan — NFR-SEC-015)');
            $table->date('period_start')->comment('Tanggal mulai menjabat');
            $table->date('period_end')->nullable()->comment('Tanggal akhir menjabat (null = masih menjabat)');
            $table->boolean('is_active')->default(true)->comment('Status aktif (FR-121)');
            $table->timestamps();
            $table->softDeletes()->comment('Soft delete (BR-011)');

            // Index untuk performa query
            $table->index('cooperative_id');
            $table->index('position');
            $table->index('is_active');

            // Constraint validasi: period_end >= period_start akan divalidasi di aplikasi
            // (tidak ada CHECK constraint native di MySQL 5.7, tetapi MySQL 8.0.16+ mendukung)
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('officers');
    }
};
