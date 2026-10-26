<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Jejak penarikan publikasi koperasi (D-6 — Tahap 105).
 *
 * Keputusan pemilik proyek: saat publikasi ditarik, **jejak publikasi dipertahankan**.
 * Sistem harus merekam waktu & aktor publikasi maupun waktu & aktor penarikan.
 *
 * `published_at`/`published_by` sudah ada sejak F2 dan **tidak diubah** oleh migrasi ini.
 * Migrasi ini hanya menambahkan dua kolom **nullable** yang bersifat *additive*:
 * - `unpublished_at` — waktu penarikan terakhir;
 * - `unpublished_by` — aktor (users.id) yang menarik publikasi terakhir.
 *
 * Sifat & alasan desain:
 * - **Nullable** → seluruh baris lama tetap sah (`NULL` = belum pernah ditarik).
 * - **Tidak ada backfill/penghapusan data.** Riwayat lama tidak disentuh.
 * - **Kolom utama menyimpan siklus terakhir**; riwayat seluruh siklus publikasi/penarikan
 *   tersimpan append-only di `activity_logs` (`publish_cooperative`/`unpublish_cooperative`).
 *   Karena itu tabel riwayat terpisah belum diperlukan (D-6 butir 7).
 * - FK `unpublished_by` → `users.id` `ON DELETE SET NULL`, konsisten dengan `published_by`.
 *   Pada **SQLite** FK dilewati (sama seperti migrasi `parent_submission_id`): suite tes SQLite
 *   **bukan** bukti kompatibilitas MariaDB.
 *
 * Idempotensi: setiap objek (kolom & FK) diperiksa terpisah sebelum dibuat, sehingga eksekusi
 * ulang setelah kegagalan parsial tidak membuat ulang objek yang sudah ada dan tidak menghapus
 * apa pun. `down()` hanya melepas objek yang dibuat migrasi ini.
 *
 * @see docs/DECISIONS.md §27 (D-6)
 * @see docs/DATA_DICTIONARY.md §13 (cooperatives)
 */
return new class extends Migration
{
    public const TABLE = 'cooperatives';

    public const COLUMN_TIME = 'unpublished_at';

    public const COLUMN_ACTOR = 'unpublished_by';

    public function up(): void
    {
        if (! Schema::hasTable(self::TABLE)) {
            return;
        }

        if (! Schema::hasColumn(self::TABLE, self::COLUMN_TIME)) {
            Schema::table(self::TABLE, function (Blueprint $table): void {
                $table->timestamp(self::COLUMN_TIME)->nullable();
            });
        }

        if (! Schema::hasColumn(self::TABLE, self::COLUMN_ACTOR)) {
            Schema::table(self::TABLE, function (Blueprint $table): void {
                $table->unsignedBigInteger(self::COLUMN_ACTOR)->nullable();
            });
        }

        if ($this->supportsForeignKey() && ! $this->hasActorForeignKey()) {
            Schema::table(self::TABLE, function (Blueprint $table): void {
                $table->foreign(self::COLUMN_ACTOR)
                    ->references('id')
                    ->on('users')
                    ->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable(self::TABLE)) {
            return;
        }

        if ($this->supportsForeignKey() && $this->hasActorForeignKey()) {
            Schema::table(self::TABLE, function (Blueprint $table): void {
                $table->dropForeign([self::COLUMN_ACTOR]);
            });
        }

        // Kolom dihapus hanya bila benar-benar ada; tidak ada penghapusan data lain.
        $columns = array_values(array_filter(
            [self::COLUMN_ACTOR, self::COLUMN_TIME],
            static fn (string $column): bool => Schema::hasColumn(self::TABLE, $column),
        ));

        if ($columns === []) {
            return;
        }

        Schema::table(self::TABLE, function (Blueprint $table) use ($columns): void {
            $table->dropColumn($columns);
        });
    }

    /**
     * SQLite sengaja dilewati: FK ditambahkan lewat rebuild tabel dan perilakunya tidak
     * dijadikan bukti kompatibilitas MariaDB (lihat docblock).
     */
    private function supportsForeignKey(): bool
    {
        return Schema::getConnection()->getDriverName() !== 'sqlite';
    }

    private function hasActorForeignKey(): bool
    {
        foreach (Schema::getForeignKeys(self::TABLE) as $foreignKey) {
            if (in_array(self::COLUMN_ACTOR, $foreignKey['columns'] ?? [], true)) {
                return true;
            }
        }

        return false;
    }
};
