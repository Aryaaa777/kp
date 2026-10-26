<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

/**
 * Menambahkan `submissions.parent_submission_id` — **versioning pengajuan**.
 *
 * FR-031/FR-142: revisi atas pengajuan `needs_revision` membuat **pengajuan/versi baru**
 * yang terhubung ke versi induknya, sehingga versi lama beserta riwayat tinjauannya tetap
 * dapat ditelusuri (bukan tertimpa). Keputusan pemilik Tahap 63 (D-1 opsi B) — lihat
 * `docs/DECISIONS.md` §17.
 *
 * Sifat kolom:
 * - **Nullable** — record pengajuan lama (tanpa induk) tetap sah; `NULL` berarti "versi awal".
 * - `nullOnDelete` — bila induk benar-benar dihapus (hard delete; operasional memakai soft
 *   delete), versi anak dilepas tautannya alih-alih memblokir penghapusan.
 * - Indeks eksplisit `submissions_parent_submission_id_index` untuk query rantai versi.
 *
 * ## Idempotensi (Tahap 68, diperkuat Tahap 70)
 *
 * `up()` memeriksa **tiga komponen secara terpisah** — kolom, indeks, dan self-FK — sehingga
 * eksekusi ulang setelah kegagalan parsial **tidak** bisa dianggap selesai hanya karena kolom
 * sudah ada:
 * 1. kolom sudah ada → tidak dibuat ulang;
 * 2. indeks milik migrasi (`self::INDEX`) belum ada (walau kolom ada) → dibuat, tanpa menyentuh
 *    data/kolom;
 * 3. FK diklasifikasikan `classify()`: `missing` → dibuat; `present` → dilewati;
 *    `conflict` → migrasi **berhenti** dengan pesan diagnostik (tidak menghapus/menimpa
 *    constraint yang tidak dikenal).
 *
 * FK yang **ekuivalen** tetapi memakai nama constraint berbeda tetap dikenali sebagai `present`:
 * kesesuaian dinilai dari **definisi** (kolom, tabel/kolom referensi, `ON DELETE`/`ON UPDATE`),
 * bukan dari nama constraint.
 *
 * ## Kepemilikan indeks (Tahap 70)
 *
 * Berbeda dari FK, indeks dinilai dari **identitas**: **nama** `self::INDEX` **dan** definisi
 * (kolom tunggal `parent_submission_id`, non-unik, bukan primary key) via `classifyIndex()`:
 * `missing` → indeks dibuat; `present` → dilewati; `conflict` (nama sama, definisi berbeda) →
 * migrasi **berhenti** dengan diagnostik.
 *
 * Indeks dengan **nama lain tidak pernah** dianggap milik migrasi ini — walau definisinya mirip —
 * sehingga **tidak pernah dihapus** dan tidak dipakai sebagai pengganti. Konsekuensinya migrasi
 * membuat indeksnya sendiri walau terdapat indeks lain yang mirip; itu disengaja agar `down()`
 * hanya melepas objek yang benar-benar dibuatnya (tidak ada penghapusan spekulatif atas indeks
 * unik, komposit, atau primary key milik pihak lain).
 *
 * ## Tahap 80 — pravalidasi & verifikasi pascaoperasi (peringatan F-74-01/F-79-01)
 *
 * Seluruh konflik yang dapat diketahui dari metadata (**kolom, indeks, self-FK**) diperiksa
 * **sebelum** statement DDL pertama. Karena itu konflik tidak lagi meninggalkan skema parsial
 * seperti versi sebelumnya (yang membuat kolom lebih dulu lalu berhenti saat indeks/FK
 * menyimpang). Definisi kolom yang sudah ada ikut divalidasi (`classifyColumn()`, `$driver`-aware):
 * kolom bertipe keliru atau `NOT NULL` **tidak** lagi diterima sebagai `present`.
 *
 * Setelah DDL, migrasi membaca ulang metadata untuk objek yang baru dibuat (verifikasi
 * pascaoperasi). Ini **deteksi**, bukan rollback — DDL yang sudah berjalan tidak dibatalkan, tidak
 * ada penghapusan otomatis, dan tidak ada klaim bahwa operasi DDL bersifat atomik.
 *
 * ## Batas idempotensi
 *
 * Yang dijamin adalah keadaan yang **terdeteksi** oleh metadata skema driver (`hasColumn`,
 * `getIndexes`, `getForeignKeys`). Bila satu statement DDL gagal di tengah, menjalankan ulang
 * migrasi akan melengkapi komponen yang belum ada. Laravel **tidak** membungkus migrasi ini dalam
 * transaksi skema pada MySQL/MariaDB **maupun** SQLite (`Grammar::$transactions = false`), jadi
 * DDL yang sudah dieksekusi **tidak** di-rollback. Karena versi Laravel ini juga tidak menyediakan
 * kunci migrasi, **dua proses `migrate` tidak boleh dijalankan serentak** — jalankan migrasi
 * terserialisasi. Idempotensi mengurangi akibat kegagalan parsial, **bukan** menggantikan
 * serialisasi. Perilaku DDL nyata (khususnya pembuatan FK) **belum** diverifikasi pada MariaDB;
 * lihat `docs/DECISIONS.md` §20.7/§21.
 *
 * ## Penjagaan urutan tabel induk (Tahap 70)
 *
 * Bila tabel `submissions` belum ada, `up()` **kembali tanpa efek** — pola yang sama dengan
 * `2026_10_16_100004_add_cooperative_foreign_key_to_officers_table.php`. Pada urutan migrasi
 * proyek tabel itu sudah dibuat lebih dulu (`2026_10_16_130001_create_submissions_table`), jadi
 * kondisi ini praktis hanya mungkin bila migrasi dijalankan terpisah (mis. `migrate --path`).
 *
 * ## SQLite
 *
 * Kolom & indeks dibuat pada semua driver. Penambahan **self-FK sengaja dilewati** pada driver
 * SQLite (`supportsForeignKey()`) — walaupun Laravel 13 sebenarnya mendukung penambahan FK lewat
 * rebuild tabel (`SQLiteGrammar::getAlterCommands()` memuat `foreign`). Konsekuensinya self-FK
 * **tidak** dibuat dan **tidak** divalidasi oleh suite tes SQLite; validasinya memerlukan
 * MariaDB. Suite `ParentSubmissionForeignKeyTest` karena itu **SQLite-only**. Lihat
 * `docs/DECISIONS.md` §20.2/§21.
 *
 * ## Rollback (Tahap 70) — DESTRUKTIF terhadap tautan versi
 *
 * **`down()` menghapus kolom `parent_submission_id` → menghilangkan relasi rantai versi secara
 * permanen.** Baris pengajuan tetap ada, tetapi versi anak kehilangan tautan ke induknya dan
 * tidak ada mekanisme pemulihan otomatis. Karena itu `down()` hanya boleh dijalankan dengan
 * **persetujuan eksplisit** pemilik setelah mengevaluasi dampaknya terhadap data relasi.
 * *(Keselamatan teknis ≠ kelayakan bisnis.)*
 *
 * Urutan pelepasan: self-FK → indeks milik migrasi → kolom.
 * - FK dihapus **hanya** bila definisinya terverifikasi (`foreignKeysToDrop()`); FK menyimpang
 *   atau tanpa nama **tidak** dihapus.
 * - Indeks dihapus **hanya** bila namanya `self::INDEX` **dan** definisinya sesuai; bila nama itu
 *   dipakai ulang untuk definisi berbeda, `down()` **berhenti** dengan diagnostik (tidak
 *   menghapus objek yang kepemilikannya tidak dapat dibuktikan).
 * - Pada MariaDB, `DROP INDEX` dapat **gagal lebih awal** (`errno 1553` — index needed in a
 *   foreign key constraint) bila FK menyimpang masih bergantung pada indeks itu; kegagalan
 *   karenanya tidak selalu muncul di `dropColumn`.
 * - Menghapus kolom juga memengaruhi **indeks lain yang memuat kolom itu** sesuai perilaku driver
 *   (mis. MariaDB mengeluarkan kolom tersebut dari indeks komposit); ini konsekuensi `dropColumn`,
 *   bukan penghapusan eksplisit oleh migrasi.
 *
 * @see docs/DATA_DICTIONARY.md §14 (submissions)
 * @see docs/PRD.md §12 (workflow pengajuan)
 * @see docs/DECISIONS.md §21 (idempotensi Tahap 68 & kebijakan kepemilikan indeks Tahap 70)
 */
return new class extends Migration
{
    /** Kolom anak yang menjadi pemilik foreign key (self-reference). */
    public const COLUMN = 'parent_submission_id';

    /** Tabel induk yang direferensikan (self). */
    public const REFERENCED_TABLE = 'submissions';

    /** Kolom yang direferensikan pada tabel induk. */
    public const REFERENCED_COLUMN = 'id';

    /**
     * Nama indeks eksplisit pada kolom anak — **identitas kepemilikan** indeks yang dibuat
     * migrasi ini (dipakai `up()` untuk mendeteksi dan `down()` untuk melepas, keduanya lewat
     * `classifyIndex()`).
     */
    public const INDEX = 'submissions_parent_submission_id_index';

    /** Nama constraint FK bila dibuat oleh migrasi ini. */
    public const FOREIGN_KEY = 'submissions_parent_submission_id_foreign';

    /** Objek (FK/indeks) yang diharapkan belum ada → boleh dibuat. */
    public const STATE_MISSING = 'missing';

    /** Objek sudah ada dengan definisi penuh sesuai rancangan → tidak perlu tindakan. */
    public const STATE_PRESENT = 'present';

    /** Objek ada (nama/milik migrasi) tetapi definisinya menyimpang → periksa manual. */
    public const STATE_CONFLICT = 'conflict';

    /**
     * Aturan `ON UPDATE` yang setara dengan default MySQL/MariaDB (klausa tidak ditulis
     * eksplisit oleh `nullOnDelete()`). `NO ACTION` diterima karena diperlakukan sama seperti
     * `RESTRICT` oleh kedua driver.
     *
     * @var list<string>
     */
    public const ON_UPDATE_RULES = ['restrict', 'no action'];

    public function up(): void
    {
        if (! Schema::hasTable(self::REFERENCED_TABLE)) {
            return;
        }

        $driver = Schema::getConnection()->getDriverName();
        $supportsForeignKey = $this->supportsForeignKey();

        // ------------------------------------------------------------------
        // Fase 1 — PRAVALIDASI (tanpa DDL). Seluruh konflik yang dapat diketahui
        // dari metadata diperiksa **sebelum** statement pertama, agar konflik tidak
        // meninggalkan skema parsial (temuan F-74-01 / F-79-01). Keberadaan nama
        // objek **tidak** disamakan dengan kesesuaian definisinya.
        // ------------------------------------------------------------------

        // 1a. Kolom — divalidasi lewat metadata penuh (nullable + tipe), bukan hanya `hasColumn`.
        if (Schema::hasColumn(self::REFERENCED_TABLE, self::COLUMN)) {
            $columns = Schema::getColumns(self::REFERENCED_TABLE);
            $columnState = self::classifyColumn($columns, $driver);

            if ($columnState === self::STATE_CONFLICT) {
                throw new RuntimeException(self::columnConflictMessage($columns));
            }
        } else {
            $columnState = self::STATE_MISSING;
        }

        // 1b. Indeks — dinilai dari kepemilikan (nama + definisi), terpisah dari kolom.
        $indexes = Schema::getIndexes(self::REFERENCED_TABLE);
        $indexState = self::classifyIndex($indexes);

        if ($indexState === self::STATE_CONFLICT) {
            throw new RuntimeException(self::indexConflictMessage($indexes));
        }

        // 1c. Self-FK — diperiksa terpisah; TIDAK boleh di-skip hanya karena kolom sudah ada.
        $foreignKeyState = null;

        if ($supportsForeignKey) {
            $foreignKeys = Schema::getForeignKeys(self::REFERENCED_TABLE);
            $foreignKeyState = self::classify($foreignKeys);

            if ($foreignKeyState === self::STATE_CONFLICT) {
                throw new RuntimeException(self::conflictMessage($foreignKeys));
            }
        }

        // ------------------------------------------------------------------
        // Fase 2 — DDL. Satu `Schema::table()` per perubahan; urutan eksplisit:
        // kolom → indeks → foreign key.
        // ------------------------------------------------------------------
        $applied = [];

        if ($columnState === self::STATE_MISSING) {
            Schema::table(self::REFERENCED_TABLE, function (Blueprint $table): void {
                $table->unsignedBigInteger(self::COLUMN)->nullable()->after('id');
            });

            $applied[] = 'column';
        }

        if ($indexState === self::STATE_MISSING) {
            Schema::table(self::REFERENCED_TABLE, function (Blueprint $table): void {
                $table->index(self::COLUMN, self::INDEX);
            });

            $applied[] = 'index';
        }

        if ($supportsForeignKey && $foreignKeyState === self::STATE_MISSING) {
            Schema::table(self::REFERENCED_TABLE, function (Blueprint $table): void {
                $table->foreign(self::COLUMN, self::FOREIGN_KEY)
                    ->references(self::REFERENCED_COLUMN)
                    ->on(self::REFERENCED_TABLE)
                    ->nullOnDelete();
            });

            $applied[] = 'foreign';
        }

        // ------------------------------------------------------------------
        // Fase 3 — VERIFIKASI PASCAOPERASI. Deteksi, **bukan** rollback: DDL yang
        // sudah berjalan tidak dapat dibatalkan (Laravel tidak membungkus migrasi
        // dalam transaksi skema). Bila objek tidak terbentuk sesuai harapan,
        // migrasi berhenti dengan diagnostik — tidak ada penghapusan otomatis.
        // ------------------------------------------------------------------
        if ($applied !== []) {
            self::verifyAppliedObjects($applied, $driver, $supportsForeignKey);
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable(self::REFERENCED_TABLE)
            || ! Schema::hasColumn(self::REFERENCED_TABLE, self::COLUMN)) {
            return;
        }

        if ($this->supportsForeignKey()) {
            foreach (self::foreignKeysToDrop(Schema::getForeignKeys(self::REFERENCED_TABLE)) as $name) {
                Schema::table(self::REFERENCED_TABLE, function (Blueprint $table) use ($name): void {
                    $table->dropForeign($name);
                });
            }
        }

        // Indeks dilepas eksplisit (setelah FK) — diperlukan karena SQLite (≥ 3.35) menolak
        // `ALTER TABLE ... DROP COLUMN` bila kolom masih terindeks. Hanya indeks milik migrasi
        // (nama `self::INDEX` **dan** definisi sesuai) yang dilepas; indeks pihak lain tidak
        // disentuh, dan nama yang dipakai ulang untuk definisi berbeda → berhenti dengan
        // diagnostik (kepemilikan tidak dapat dibuktikan).
        $indexes = Schema::getIndexes(self::REFERENCED_TABLE);
        $indexState = self::classifyIndex($indexes);

        if ($indexState === self::STATE_CONFLICT) {
            throw new RuntimeException(self::indexConflictMessage($indexes));
        }

        if ($indexState === self::STATE_PRESENT) {
            Schema::table(self::REFERENCED_TABLE, function (Blueprint $table): void {
                $table->dropIndex(self::INDEX);
            });
        }

        // Kolom dihapus setelah FK/indeks (bila ada & sesuai) dilepas. Bila masih ada FK atau
        // indeks yang sengaja tidak dilepas (menyimpang / bukan milik migrasi), statement ini —
        // atau `DROP INDEX` di atas pada MariaDB — gagal keras → skema diperiksa manual.
        Schema::table(self::REFERENCED_TABLE, function (Blueprint $table): void {
            $table->dropColumn(self::COLUMN);
        });
    }

    /**
     * Apakah driver ini boleh ditambahi self-FK oleh migrasi ini?
     *
     * SQLite **sengaja** dilewati: suite tes berjalan pada SQLite dan proyek tidak menjadikan
     * perilaku FK SQLite sebagai bukti kompatibilitas MariaDB. Lihat docblock & §20.2.
     */
    private function supportsForeignKey(): bool
    {
        return Schema::getConnection()->getDriverName() !== 'sqlite';
    }

    /**
     * Klasifikasikan keadaan indeks pada `parent_submission_id` dari sudut **kepemilikan**.
     *
     * Kepemilikan = **nama** `self::INDEX` **dan** definisi (kolom tunggal `parent_submission_id`,
     * non-unik, bukan primary key). Indeks bernama lain **tidak pernah** dianggap milik migrasi
     * ini — walau definisinya mirip — sehingga tidak diadopsi maupun dihapus.
     *
     * @param  array<int, array<string, mixed>>  $indexes  hasil `Schema::getIndexes('submissions')`
     * @return string salah satu dari STATE_MISSING / STATE_PRESENT / STATE_CONFLICT
     */
    public static function classifyIndex(array $indexes): string
    {
        $named = array_values(array_filter(
            $indexes,
            static function ($index): bool {
                return is_array($index)
                    && isset($index['name'])
                    && (string) $index['name'] === self::INDEX;
            }
        ));

        if ($named === []) {
            return self::STATE_MISSING;
        }

        foreach ($named as $index) {
            if (! self::matchesExpectedIndexDefinition($index)) {
                return self::STATE_CONFLICT;
            }
        }

        return self::STATE_PRESENT;
    }

    /**
     * Apakah definisi indeks persis seperti yang dibuat migrasi ini (kolom tunggal, non-unik,
     * bukan primary key)?
     *
     * @param  array<string, mixed>  $index
     */
    private static function matchesExpectedIndexDefinition(array $index): bool
    {
        return ($index['columns'] ?? []) === [self::COLUMN]
            && ! ($index['unique'] ?? false)
            && ! ($index['primary'] ?? false);
    }

    /**
     * Klasifikasikan keadaan kolom `parent_submission_id` dari metadata driver.
     *
     * `present` hanya bila **definisi** sesuai rancangan: NULLABLE dan bertipe setara identifier
     * `submissions.id`. Keberadaan nama kolom semata **tidak** cukup (temuan F-79-02).
     *
     * @param  array<int, array<string, mixed>>  $columns  hasil `Schema::getColumns('submissions')`
     * @param  string  $driver  nama driver aktif (`Schema::getConnection()->getDriverName()`)
     * @return string salah satu dari STATE_MISSING / STATE_PRESENT / STATE_CONFLICT
     */
    public static function classifyColumn(array $columns, string $driver): string
    {
        $named = array_values(array_filter(
            $columns,
            static function ($column): bool {
                return is_array($column)
                    && isset($column['name'])
                    && (string) $column['name'] === self::COLUMN;
            }
        ));

        if ($named === []) {
            return self::STATE_MISSING;
        }

        foreach ($named as $column) {
            if (! self::matchesExpectedColumnDefinition($column, $driver)) {
                return self::STATE_CONFLICT;
            }
        }

        return self::STATE_PRESENT;
    }

    /**
     * Apakah definisi kolom sesuai rancangan: **nullable** dan bertipe setara identifier
     * `submissions.id` (`BIGINT UNSIGNED`).
     *
     * Metadata yang dipakai adalah yang benar-benar dikembalikan Laravel:
     * - MySQL/MariaDB (`MySqlProcessor::processColumns`): `type_name` = `data_type` (`bigint`),
     *   `type` = `column_type` (`bigint(20) unsigned`), `nullable` = `bool`.
     * - SQLite (`SQLiteProcessor::processColumns`): `type_name` dieja lowercase (`integer` untuk
     *   `unsignedBigInteger`), `type` = tipe deklarasi, `nullable` = `bool`. SQLite bertipe dinamis
     *   dan **tidak** menyimpan atribut UNSIGNED → tabel ini **tidak** dianggap bukti kesetaraan
     *   dengan MariaDB.
     *
     * Posisi kolom (`AFTER id`) tidak diperiksa: `MySqlProcessor` tidak mengembalikan
     * `ORDINAL_POSITION`, dan SQLite mengabaikan klausa posisi — verifikasinya hanya lewat
     * `information_schema` pada prosedur MariaDB.
     *
     * @param  array<string, mixed>  $column
     */
    private static function matchesExpectedColumnDefinition(array $column, string $driver): bool
    {
        if (($column['nullable'] ?? false) !== true) {
            return false;
        }

        $typeName = strtolower((string) ($column['type_name'] ?? ''));
        $type = strtolower((string) ($column['type'] ?? ''));

        if ($driver === 'sqlite') {
            return in_array($typeName, ['integer', 'bigint'], true);
        }

        // MySQL/MariaDB (satu-satunya driver SQL yang didukung proyek): `submissions.id`
        // adalah BIGINT UNSIGNED — tipe lain (mis. `int`) membuat FK tidak sah.
        return $typeName === 'bigint' && str_contains($type, 'unsigned');
    }

    /**
     * Pesan diagnostik ketika kolom `parent_submission_id` ada dengan definisi menyimpang.
     *
     * @param  array<int, array<string, mixed>>  $columns
     */
    public static function columnConflictMessage(array $columns): string
    {
        return 'Ditemukan kolom `'.self::COLUMN.'` pada tabel `'.self::REFERENCED_TABLE
            .'` dengan definisi yang tidak sesuai harapan (harus NULLABLE dan bertipe setara '
            .'identifier `'.self::REFERENCED_TABLE.'.'.self::REFERENCED_COLUMN.'`, yaitu '
            .'BIGINT UNSIGNED pada MySQL/MariaDB). Migrasi dihentikan agar skema diperiksa manual '
            .'sebelum dilanjutkan; kolom **tidak** diubah maupun dihapus. Kolom yang ditemukan: '
            .self::describeColumns($columns);
    }

    /**
     * Pesan diagnostik ketika nama indeks `self::INDEX` ternyata dipakai untuk definisi lain.
     *
     * @param  array<int, array<string, mixed>>  $indexes
     */
    public static function indexConflictMessage(array $indexes): string
    {
        $details = array_map(static function (array $index): string {
            return sprintf(
                '[nama=%s kolom=(%s) unik=%s primary=%s]',
                $index['name'] ?? '(tanpa nama)',
                implode(',', $index['columns'] ?? []),
                ($index['unique'] ?? false) ? 'ya' : 'tidak',
                ($index['primary'] ?? false) ? 'ya' : 'tidak'
            );
        }, $indexes);

        return 'Ditemukan indeks bernama `'.self::INDEX.'` pada tabel `'.self::REFERENCED_TABLE
            .'` dengan definisi yang tidak sesuai harapan (kolom tunggal `'.self::COLUMN
            .'`, non-unik, bukan primary key). Migrasi dihentikan agar skema diperiksa manual '
            .'sebelum dilanjutkan; indeks **tidak** dihapus. Indeks yang ditemukan: '
            .implode(' ', $details);
    }

    /**
     * Klasifikasikan keadaan self-FK pada `parent_submission_id`.
     *
     * @param  array<int, array<string, mixed>>  $foreignKeys  hasil `Schema::getForeignKeys('submissions')`
     * @return string salah satu dari STATE_MISSING / STATE_PRESENT / STATE_CONFLICT
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
     * Nama constraint FK yang **aman** dilepas pada `down()`.
     *
     * Hanya FK dengan definisi sesuai rancangan yang dikembalikan, memakai nama yang benar-benar
     * dilaporkan driver. Kondisi `missing`/`conflict` → daftar kosong (tidak ada yang dihapus).
     *
     * @param  array<int, array<string, mixed>>  $foreignKeys
     * @return list<string>
     */
    public static function foreignKeysToDrop(array $foreignKeys): array
    {
        if (self::classify($foreignKeys) !== self::STATE_PRESENT) {
            return [];
        }

        $names = [];

        foreach ($foreignKeys as $foreignKey) {
            if (in_array(self::COLUMN, $foreignKey['columns'] ?? [], true)
                && isset($foreignKey['name'])) {
                $names[] = (string) $foreignKey['name'];
            }
        }

        return $names;
    }

    /**
     * Apakah definisi FK persis sesuai rancangan: satu kolom `parent_submission_id` →
     * `submissions.id`, `ON DELETE SET NULL`, dan `ON UPDATE` memblokir (default driver).
     *
     * @param  array<string, mixed>  $foreignKey
     */
    private static function matchesExpectedDefinition(array $foreignKey): bool
    {
        return ($foreignKey['columns'] ?? []) === [self::COLUMN]
            && ($foreignKey['foreign_table'] ?? null) === self::REFERENCED_TABLE
            && ($foreignKey['foreign_columns'] ?? []) === [self::REFERENCED_COLUMN]
            && strtolower((string) ($foreignKey['on_delete'] ?? '')) === 'set null'
            && in_array(
                strtolower((string) ($foreignKey['on_update'] ?? '')),
                self::ON_UPDATE_RULES,
                true
            );
    }

    /**
     * Pesan diagnostik ketika ditemukan FK pada kolom ini yang tidak sesuai harapan.
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

        return 'Ditemukan foreign key pada kolom `'.self::COLUMN.'` tabel `'.self::REFERENCED_TABLE
            .'` yang definisinya tidak sesuai harapan (`'.self::COLUMN.'` → `'.self::REFERENCED_TABLE
            .'.'.self::REFERENCED_COLUMN.'`, ON DELETE SET NULL, ON UPDATE salah satu dari '
            .implode('|', self::ON_UPDATE_RULES).'). Migrasi dihentikan agar skema diperiksa manual '
            .'sebelum dilanjutkan. FK yang ditemukan: '.implode(' ', $details);
    }

    /**
     * Verifikasi pascaoperasi — **deteksi**, bukan pemulihan.
     *
     * Membaca ulang metadata hanya untuk objek yang benar-benar baru saja dibuat, lalu memastikan
     * hasilnya benar-benar `present` sesuai definisi. Bila tidak, migrasi berhenti dengan
     * diagnostik (tidak ada exception yang ditelan dan tidak ada penghapusan otomatis). Ini tidak
     * membatalkan DDL yang sudah berjalan dan tidak menyatakan operasi DDL bersifat atomik.
     *
     * @param  list<string>  $applied  objek yang baru dibuat: `column`, `index`, dan/atau `foreign`
     */
    private static function verifyAppliedObjects(array $applied, string $driver, bool $supportsForeignKey): void
    {
        if (in_array('column', $applied, true)) {
            $columns = Schema::getColumns(self::REFERENCED_TABLE);

            if (self::classifyColumn($columns, $driver) !== self::STATE_PRESENT) {
                throw new RuntimeException(self::verificationMessage(
                    'kolom `'.self::COLUMN.'`',
                    self::describeColumns($columns)
                ));
            }
        }

        if (in_array('index', $applied, true)) {
            $indexes = Schema::getIndexes(self::REFERENCED_TABLE);

            if (self::classifyIndex($indexes) !== self::STATE_PRESENT) {
                throw new RuntimeException(self::verificationMessage(
                    'indeks `'.self::INDEX.'`',
                    self::describeIndexes($indexes)
                ));
            }
        }

        if ($supportsForeignKey && in_array('foreign', $applied, true)) {
            $foreignKeys = Schema::getForeignKeys(self::REFERENCED_TABLE);

            if (self::classify($foreignKeys) !== self::STATE_PRESENT) {
                throw new RuntimeException(self::verificationMessage(
                    'foreign key pada kolom `'.self::COLUMN.'`',
                    self::describeForeignKeys($foreignKeys)
                ));
            }
        }
    }

    /**
     * Pesan diagnostik verifikasi pascaoperasi.
     */
    private static function verificationMessage(string $object, string $detail): string
    {
        return 'Verifikasi pascaoperasi gagal: '.$object.' pada tabel `'.self::REFERENCED_TABLE
            .'` tidak sesuai harapan setelah DDL dijalankan. Ini **deteksi**, bukan rollback — DDL '
            .'yang sudah berjalan tidak dibatalkan. Periksa skema secara manual sebelum melanjutkan. '
            .'Kondisi yang terbaca: '.$detail;
    }

    /**
     * Ringkas metadata kolom `self::COLUMN` untuk pesan diagnostik.
     *
     * @param  array<int, array<string, mixed>>  $columns
     */
    private static function describeColumns(array $columns): string
    {
        $named = array_values(array_filter(
            $columns,
            static function ($column): bool {
                return is_array($column) && ($column['name'] ?? null) === self::COLUMN;
            }
        ));

        if ($named === []) {
            return '(kolom `'.self::COLUMN.'` tidak ditemukan)';
        }

        return implode(' ', array_map(static function (array $column): string {
            return sprintf(
                '[nama=%s tipe=%s nullable=%s]',
                $column['name'] ?? '(tanpa nama)',
                $column['type'] ?? ($column['type_name'] ?? '?'),
                ($column['nullable'] ?? false) === true ? 'ya' : 'tidak'
            );
        }, $named));
    }

    /**
     * Ringkas metadata indeks bernama `self::INDEX` untuk pesan diagnostik.
     *
     * @param  array<int, array<string, mixed>>  $indexes
     */
    private static function describeIndexes(array $indexes): string
    {
        $named = array_values(array_filter(
            $indexes,
            static function ($index): bool {
                return is_array($index) && ($index['name'] ?? null) === self::INDEX;
            }
        ));

        if ($named === []) {
            return '(tidak ada indeks bernama `'.self::INDEX.'`)';
        }

        return implode(' ', array_map(static function (array $index): string {
            return sprintf(
                '[nama=%s kolom=(%s) unik=%s primary=%s]',
                $index['name'] ?? '(tanpa nama)',
                implode(',', $index['columns'] ?? []),
                ($index['unique'] ?? false) ? 'ya' : 'tidak',
                ($index['primary'] ?? false) ? 'ya' : 'tidak'
            );
        }, $named));
    }

    /**
     * Ringkas metadata FK pada kolom `self::COLUMN` untuk pesan diagnostik.
     *
     * @param  array<int, array<string, mixed>>  $foreignKeys
     */
    private static function describeForeignKeys(array $foreignKeys): string
    {
        $candidates = array_values(array_filter(
            $foreignKeys,
            static function ($foreignKey): bool {
                return is_array($foreignKey)
                    && in_array(self::COLUMN, $foreignKey['columns'] ?? [], true);
            }
        ));

        if ($candidates === []) {
            return '(tidak ada FK pada kolom `'.self::COLUMN.'`)';
        }

        return implode(' ', array_map(static function (array $foreignKey): string {
            return sprintf(
                '[nama=%s kolom=(%s) → %s.(%s) ON UPDATE %s ON DELETE %s]',
                $foreignKey['name'] ?? '(tanpa nama)',
                implode(',', $foreignKey['columns'] ?? []),
                $foreignKey['foreign_table'] ?? '?',
                implode(',', $foreignKey['foreign_columns'] ?? []),
                strtoupper((string) ($foreignKey['on_update'] ?? '?')),
                strtoupper((string) ($foreignKey['on_delete'] ?? '?'))
            );
        }, $candidates));
    }
};
