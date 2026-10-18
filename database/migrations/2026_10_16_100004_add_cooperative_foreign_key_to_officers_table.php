<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Menambahkan foreign key `officers.cooperative_id` → `cooperatives.id`
 * (`ON DELETE RESTRICT`, `ON UPDATE RESTRICT`).
 *
 * Mengapa dipisah dari `2026_10_16_022847_create_officers_table`:
 * Migrasi pembuatan `officers` memakai stempel waktu `2026_10_16_022847` yang berjalan
 * **sebelum** `2026_10_16_100002_create_cooperatives_table`. Membuat FK inline pada migrasi
 * `officers` karenanya menunjuk tabel induk yang belum ada → `errno 150` pada instalasi
 * dari nol (MariaDB). SQLite tidak menegakkan hal ini saat `CREATE TABLE`, sehingga masalah
 * hanya muncul pada MariaDB/MySQL.
 *
 * Migrasi ini diberi stempel waktu `2026_10_16_100004` sehingga berjalan **setelah** tabel
 * induk `cooperatives` (100002) dan pelengkapnya (100003) tersedia. Dengan urutan ini,
 * instalasi dari nol aman.
 *
 * Penjagaan (guard): penambahan FK dilewati **hanya jika** FK yang benar-benar sesuai sudah
 * ada. Kesesuaian dinilai dari **definisi** constraint (kolom anak, tabel & kolom referensi,
 * serta aturan ON DELETE/ON UPDATE) melalui `Schema::getForeignKeys()`, bukan sekadar
 * keberadaan FK pada kolom `cooperative_id` maupun kesamaan nama constraint. Bila ditemukan
 * FK pada kolom `cooperative_id` dengan definisi berbeda/ambigu, migrasi **berhenti** dengan
 * pesan diagnostik agar skema diperiksa manual (tidak diam-diam menambah maupun melewati).
 *
 * Kebijakan rollback: `down()` **sengaja tidak menghapus FK**. FK pada DB yang sudah ada
 * (mis. DB pengembangan) mungkin dibuat oleh versi lama migrasi `officers`, dan tidak ada
 * penanda persisten yang membedakan pemilik constraint. Menghapusnya berisiko menghilangkan
 * constraint lama yang bukan milik migrasi ini. Lihat catatan pada `down()` dan
 * `docs/DECISIONS.md §13`.
 *
 * Kompatibilitas tipe: `officers.cooperative_id` dibuat oleh `foreignId()` =
 * `bigint unsigned`, sama dengan `cooperatives.id` (`bigIncrements()` = `bigint unsigned`).
 * Nilai `on_update`/`on_delete` dari `Schema::getForeignKeys()` dinormalkan huruf kecil oleh
 * Laravel (`MySqlProcessor`), dan MariaDB melaporkan `RESTRICT` untuk constraint ini (terbukti
 * pada Tahap 36–38).
 *
 * @see docs/DATA_DICTIONARY.md §13 (officers)
 * @see docs/DECISIONS.md §13 (guard FK officers & kebijakan rollback)
 */
return new class extends Migration
{
    /** Kolom anak yang menjadi pemilik foreign key. */
    public const COLUMN = 'cooperative_id';

    /** Tabel induk yang direferensikan. */
    public const REFERENCED_TABLE = 'cooperatives';

    /** Kolom induk yang direferensikan. */
    public const REFERENCED_COLUMN = 'id';

    /**
     * Aturan referensial yang diterima sebagai "RESTRICT".
     *
     * `RESTRICT` dan `NO ACTION` setara secara fungsional: keduanya menolak penghapusan/
     * perubahan baris induk. MariaDB/InnoDB melaporkan `RESTRICT` untuk constraint ini,
     * sedangkan SQLite melaporkan `NO ACTION` bila klausa `ON UPDATE` tidak ditulis eksplisit
     * (`ON UPDATE` tidak diemit oleh `restrictOnDelete()` sehingga default engine berlaku).
     * Aturan lain (CASCADE / SET NULL / SET DEFAULT) tetap ditolak.
     *
     * Nilai dinormalkan Laravel ke huruf kecil (`MySqlProcessor`, `SQLiteProcessor`).
     */
    public const RULES = ['restrict', 'no action'];

    /** FK yang sesuai sudah ada → tidak ada yang perlu dikerjakan. */
    public const STATE_PRESENT = 'present';

    /** Tidak ada FK pada kolom anak → FK boleh ditambahkan. */
    public const STATE_MISSING = 'missing';

    /** Ada FK pada kolom anak tetapi definisinya tidak sesuai → periksa manual. */
    public const STATE_CONFLICT = 'conflict';

    public function up(): void
    {
        if (! Schema::hasTable('officers')) {
            return;
        }

        $foreignKeys = Schema::getForeignKeys('officers');
        $state = self::classify($foreignKeys);

        if ($state === self::STATE_PRESENT) {
            return;
        }

        if ($state === self::STATE_CONFLICT) {
            throw new RuntimeException(self::conflictMessage($foreignKeys));
        }

        // STATE_MISSING: belum ada FK pada kolom ini → tambahkan sesuai rancangan.
        Schema::table('officers', function (Blueprint $table) {
            $table->foreign(self::COLUMN)
                ->references(self::REFERENCED_COLUMN)
                ->on(self::REFERENCED_TABLE)
                ->restrictOnDelete();
        });
    }

    /**
     * Kebijakan rollback — sengaja TIDAK mengubah skema.
     *
     * Tidak ada penanda persisten yang membedakan FK yang dibuat oleh migrasi ini dari FK yang
     * sudah ada sebelum migrasi dijalankan (mis. dibuat versi lama migrasi `officers` pada DB
     * pengembangan). Karena itu `down()` tidak boleh menghapus FK: nama constraint yang sama
     * bukan bukti kepemilikan, sehingga penghapusan berisiko menghilangkan constraint lama.
     *
     * Konsekuensi (lihat docs/DECISIONS.md §13):
     * - Rollback penuh/instalasi dari nol: FK ikut hilang saat tabel `officers` (migrasi
     *   `022847`) di-rollback.
     * - Rollback parsial (hanya migrasi ini): FK yang benar tetap ada; menjalankan `migrate`
     *   lagi akan melewatinya lewat guard (idempoten, aman).
     */
    public function down(): void
    {
        //
    }

    /**
     * Klasifikasikan keadaan FK `officers.cooperative_id`.
     *
     * @param  array<int, array<string, mixed>>  $foreignKeys  hasil `Schema::getForeignKeys('officers')`
     * @return string salah satu dari STATE_PRESENT / STATE_MISSING / STATE_CONFLICT
     */
    public static function classify(array $foreignKeys): string
    {
        $candidates = array_values(array_filter(
            $foreignKeys,
            static function ($foreignKey): bool {
                return is_array($foreignKey)
                    && in_array(self::COLUMN, $foreignKey['columns'] ?? [], true);
            }
        ));

        if ($candidates === []) {
            return self::STATE_MISSING;
        }

        // Bila ada lebih dari satu FK pada kolom yang sama, semuanya harus sesuai definisi
        // harapan; satu saja yang menyimpang menjadikan kondisi ini ambigu → konflik.
        foreach ($candidates as $candidate) {
            if (! self::matchesExpectedDefinition($candidate)) {
                return self::STATE_CONFLICT;
            }
        }

        return self::STATE_PRESENT;
    }

    /**
     * Apakah definisi FK persis sesuai rancangan: satu kolom `cooperative_id` → `cooperatives.id`
     * dengan `ON DELETE` dan `ON UPDATE` yang bersifat memblokir (RESTRICT / NO ACTION).
     *
     * @param  array<string, mixed>  $foreignKey
     */
    private static function matchesExpectedDefinition(array $foreignKey): bool
    {
        return ($foreignKey['columns'] ?? []) === [self::COLUMN]
            && ($foreignKey['foreign_table'] ?? null) === self::REFERENCED_TABLE
            && ($foreignKey['foreign_columns'] ?? []) === [self::REFERENCED_COLUMN]
            && self::isExpectedRule($foreignKey['on_delete'] ?? null)
            && self::isExpectedRule($foreignKey['on_update'] ?? null);
    }

    /**
     * Apakah aturan referensial termasuk aturan yang memblokir (RESTRICT / NO ACTION)?
     */
    private static function isExpectedRule(mixed $rule): bool
    {
        return in_array(strtolower((string) $rule), self::RULES, true);
    }

    /**
     * Pesan diagnostik ketika ditemukan FK pada `cooperative_id` yang tidak sesuai harapan.
     *
     * @param  array<int, array<string, mixed>>  $foreignKeys
     */
    public static function conflictMessage(array $foreignKeys): string
    {
        $details = array_map(static function (array $foreignKey): string {
            return sprintf(
                '[nama=%s kolom=(%s) → %s.(%s) ON UPDATE %s ON DELETE %s]',
                $foreignKey['name'] ?? '(tanpa nama)',
                implode(',', $foreignKey['columns'] ?? []),
                $foreignKey['foreign_table'] ?? '?',
                implode(',', $foreignKey['foreign_columns'] ?? []),
                strtoupper((string) ($foreignKey['on_update'] ?? '?')),
                strtoupper((string) ($foreignKey['on_delete'] ?? '?'))
            );
        }, $foreignKeys);

        return 'Ditemukan foreign key pada kolom `'.self::COLUMN.'` tabel `officers` yang definisinya '
            .'tidak sesuai harapan (`'.self::COLUMN.'` → `'.self::REFERENCED_TABLE.'.'.self::REFERENCED_COLUMN
            .'`, ON DELETE/ON UPDATE salah satu dari '.implode('|', self::RULES).'). '
            .'Migrasi dihentikan agar skema diperiksa manual sebelum dilanjutkan. '
            .'FK yang ditemukan: '.implode(' ', $details);
    }
};
