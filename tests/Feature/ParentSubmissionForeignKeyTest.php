<?php

namespace Tests\Feature;

use App\Models\Cooperative;
use App\Models\User;
use Closure;
use Illuminate\Database\Connection;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Mockery;
use RuntimeException;
use Tests\TestCase;

/**
 * Menguji migrasi `2026_10_19_000001_add_parent_submission_id_to_submissions_table`
 * (Tahap 63/68/70) — terutama **idempotensi** `up()`, **kebijakan kepemilikan indeks**, dan
 * kebijakan `down()`.
 *
 * Latar belakang (temuan Tahap 67): `up()` lama berhenti (`return`) begitu kolom sudah ada,
 * sehingga bila statement FK gagal di eksekusi pertama, eksekusi ulang tercatat `Ran` **tanpa**
 * FK. Migrasi kini memeriksa **kolom, indeks, dan FK secara terpisah**.
 *
 * Tambahan Tahap 70 (audit Tahap 69):
 * - **F-69-01** indeks dinilai dari **nama + definisi** (`classifyIndex()`); indeks bernama lain
 *   tidak diadopsi maupun dihapus, dan nama milik migrasi dengan definisi berbeda (unik/komposit/
 *   primary key) → `down()` berhenti dengan diagnostik.
 * - **F-69-02** `down()` menghapus kolom `parent_submission_id` (tautan versi hilang permanen);
 *   tes membuktikan baris tetap ada sambil mengakui kolom relasi versi dihapus.
 * - **F-69-05** tabel induk belum ada → `up()` kembali tanpa efek (penjagaan urutan migrasi).
 *
 * Tambahan Tahap 80 (opsi D audit Tahap 79):
 * - **F-74-01/F-79-01** — pravalidasi kolom + indeks + FK dijalankan **sebelum** DDL pertama,
 *   sehingga konflik tidak lagi meninggalkan skema parsial (kolom lebih dulu dibuat).
 * - **F-79-02** — definisi kolom divalidasi (`classifyColumn()`); `NOT NULL` atau tipe yang tidak
 *   setara `submissions.id` dianggap `conflict`, bukan `present`.
 * - Verifikasi pascaoperasi atas objek yang baru dibuat (deteksi, **bukan** rollback).
 *
 * Tambahan Tahap 82 (penutupan A-81-03 audit Tahap 81):
 * - Kegagalan DDL ke-2/ke-3 **diteruskan apa adanya** — DDL berikutnya dan verifikasi
 *   pascaoperasi tidak dijalankan (mengunci sifat fail-fast; **bukan** klaim atomisitas DDL).
 * - Verifikasi pascaoperasi diuji juga ketika **indeks** dan **foreign key** tidak terbentuk.
 *
 * Batas bukti (penting):
 * - Suite berjalan pada **SQLite `:memory:`** dan merupakan **suite SQLite-only**: migrasi
 *   **sengaja** melewati pembuatan self-FK pada driver SQLite (`supportsForeignKey()`), sehingga
 *   tes skema **tidak** membuktikan FK. Tes yang mengandalkan keadaan driver diberi nama
 *   berawalan `test_sqlite_only_` agar tidak dikira lintas-driver.
 * - Logika keputusan FK (`classify()`, `foreignKeysToDrop()`) diuji **unit** dengan metadata
 *   berbentuk kembalian `Schema::getForeignKeys()` (bentuk yang sama di MySQL/MariaDB — lihat
 *   `MySqlProcessor`); logika kepemilikan indeks diuji **unit** lewat `classifyIndex()`.
 * - Cabang `up()`/`down()`/kebijakan driver diuji dengan **mock facade** `Schema` (tanpa DDL),
 *   termasuk menangkap perintah Blueprint, sehingga membuktikan **definisi yang diminta** dan
 *   urutan perintah — **bukan** perilaku DDL MariaDB.
 * - Yang **belum** terbukti (jangan dianggap tertutup): pembuatan FK nyata beserta
 *   `ON DELETE SET NULL` **pada runtime MariaDB**, `dropForeign`/`dropIndex` nyata pada MariaDB,
 *   dan perilaku `lockForUpdate()`. Semuanya menuntut verifikasi runtime berizin
 *   (`docs/DECISIONS.md` §20.7/§21.3).
 */
class ParentSubmissionForeignKeyTest extends TestCase
{
    use RefreshDatabase;

    private const MIGRATION = 'migrations/2026_10_19_000001_add_parent_submission_id_to_submissions_table.php';

    private const TABLE = 'submissions';

    private const COLUMN = 'parent_submission_id';

    private const INDEX = 'submissions_parent_submission_id_index';

    private const FOREIGN_KEY = 'submissions_parent_submission_id_foreign';

    // ---------------------------------------------------------------------
    // Integritas skema pada SQLite (kolom & indeks)
    // ---------------------------------------------------------------------

    public function test_migration_creates_the_nullable_column_and_index(): void
    {
        $this->assertTrue(Schema::hasColumn(self::TABLE, self::COLUMN));
        $this->assertTrue($this->hasColumnIndex(), 'Indeks pada parent_submission_id tidak ditemukan.');

        $column = collect(Schema::getColumns(self::TABLE))
            ->firstWhere('name', self::COLUMN);

        $this->assertNotNull($column, 'Metadata kolom parent_submission_id tidak ditemukan.');
        $this->assertTrue((bool) $column['nullable'], 'Kolom wajib nullable (baris lama = versi awal).');
        // SQLite memetakan BIGINT UNSIGNED → INTEGER (dynamic typing). Kesesuaian tipe dengan
        // PK MariaDB (`submissions.id` = BIGINT UNSIGNED) diverifikasi terpisah di level source.
        $this->assertContains(
            strtolower((string) $column['type_name']),
            ['integer', 'bigint'],
            'Tipe kolom harus bertipe bilangan bulat.'
        );
    }

    public function test_sqlite_only_skips_the_self_foreign_key_by_design(): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            $this->markTestSkipped('Perilaku khusus driver SQLite.');
        }

        // Tes SQLite-only (F-69-10): guard driver membuat self-FK tidak dibuat pada SQLite.
        // Bila kelak guard dihapus, tes ini akan gagal dan memaksa pembaruan dokumentasi.
        $this->assertNull(
            $this->foreignKeyMetadata(),
            'Migrasi sengaja melewati self-FK pada SQLite; validasi FK memerlukan MariaDB.'
        );
    }

    public function test_mysql_creates_the_self_foreign_key_with_set_null(): void
    {
        if (! in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            $this->markTestSkipped('Memerlukan koneksi MySQL/MariaDB.');
        }

        $foreignKey = $this->foreignKeyMetadata();

        $this->assertNotNull($foreignKey, 'Self-FK harus dibuat pada MySQL/MariaDB.');
        $this->assertSame(self::TABLE, $foreignKey['foreign_table']);
        $this->assertSame(['id'], $foreignKey['foreign_columns']);
        $this->assertSame('set null', strtolower((string) $foreignKey['on_delete']));
    }

    public function test_migration_source_declares_the_null_on_delete_foreign_key(): void
    {
        $source = file_get_contents(database_path(self::MIGRATION));

        $this->assertIsString($source);
        $this->assertStringContainsString(
            '$table->foreign(self::COLUMN, self::FOREIGN_KEY)',
            $source,
            'Constraint FK harus diberi nama eksplisit.'
        );
        $this->assertStringContainsString('->references(self::REFERENCED_COLUMN)', $source);
        $this->assertStringContainsString('->on(self::REFERENCED_TABLE)', $source);
        $this->assertStringContainsString(
            '->nullOnDelete()',
            $source,
            'FK wajib ON DELETE SET NULL (bukan CASCADE/RESTRICT).'
        );
    }

    public function test_requested_foreign_key_definition_matches_the_design(): void
    {
        // Membuktikan **definisi yang diminta** migrasi (Blueprint dijalankan tanpa DDL). Ini
        // melengkapi assertion source di atas; keduanya tetap **bukan** bukti DDL MariaDB.
        $this->mockExistingColumnAndIndex('mysql');
        Schema::shouldReceive('getForeignKeys')
            ->twice()
            ->with(self::TABLE)
            ->andReturn([], [$this->expectedForeignKey()]);

        $batches = $this->captureTableBatches(fn () => $this->migration()->up());

        $this->assertCount(1, $batches, 'Hanya satu statement: penambahan FK.');
        $foreign = $batches[0][0];

        $this->assertSame('foreign', $foreign['name']);
        $this->assertSame([self::COLUMN], $foreign['columns']);
        $this->assertSame(self::TABLE, $foreign['on']);
        $this->assertSame('id', $foreign['references']);
        $this->assertSame('set null', $foreign['onDelete']);
        $this->assertSame(self::FOREIGN_KEY, $foreign['index']);
    }

    // ---------------------------------------------------------------------
    // Idempotensi up() (SQLite)
    // ---------------------------------------------------------------------

    public function test_up_is_idempotent_and_does_not_lose_rows_or_valid_schema(): void
    {
        [$parentId] = $this->seedTwoSubmissions();

        $migration = $this->migration();
        $migration->up();
        $migration->up();

        $this->assertTrue(Schema::hasColumn(self::TABLE, self::COLUMN));
        $this->assertTrue($this->hasColumnIndex());
        $this->assertSame(2, DB::table(self::TABLE)->count(), 'Baris tidak boleh hilang.');
        $this->assertSame(
            $parentId,
            (int) DB::table(self::TABLE)->where('title', 'Versi turunan')->value('parent_submission_id'),
            'Nilai parent_submission_id yang sudah ada tidak boleh berubah.'
        );
        $this->assertNotNull($this->cooperativeForeignKey(), 'FK lama (cooperative_id) tidak boleh rusak.');
        $this->assertTrue(Schema::hasColumn(self::TABLE, 'status'));
    }

    public function test_up_returns_without_effect_when_the_referenced_table_is_absent(): void
    {
        // F-69-05: penjagaan urutan migrasi (pola sama dengan migrasi FK `officers`). Tabel
        // `submissions` dibuat lebih dulu (`2026_10_16_130001`), jadi kondisi ini hanya mungkin
        // bila migrasi dijalankan terpisah (mis. `migrate --path`).
        Schema::shouldReceive('hasTable')->once()->with(self::TABLE)->andReturn(false);
        Schema::shouldReceive('hasColumn')->never();
        Schema::shouldReceive('getColumns')->never();
        Schema::shouldReceive('getIndexes')->never();
        Schema::shouldReceive('getForeignKeys')->never();
        Schema::shouldReceive('table')->never();

        $this->migration()->up();

        $this->assertTrue(true, 'Tabel induk belum ada → up() kembali tanpa efek (bukan DDL).');
    }

    public function test_up_restores_a_missing_column_and_index(): void
    {
        $migration = $this->migration();
        $migration->down();

        $this->assertFalse(Schema::hasColumn(self::TABLE, self::COLUMN));
        $this->assertFalse($this->hasColumnIndex());

        $migration->up();

        $this->assertTrue(Schema::hasColumn(self::TABLE, self::COLUMN));
        $this->assertTrue($this->hasColumnIndex());
        $this->assertNotNull($this->cooperativeForeignKey(), 'FK lain tidak boleh rusak setelah down()/up().');
    }

    public function test_up_recreates_a_missing_index_when_the_column_already_exists(): void
    {
        $this->dropMigrationForeignKeyAndIndex();

        $this->assertTrue(Schema::hasColumn(self::TABLE, self::COLUMN));
        $this->assertFalse($this->hasColumnIndex());

        $this->migration()->up();

        $this->assertTrue(Schema::hasColumn(self::TABLE, self::COLUMN));
        $this->assertTrue($this->hasColumnIndex(), 'Indeks harus dibuat ulang tanpa menyentuh kolom.');

        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            $this->assertNotNull($this->foreignKeyMetadata(), 'FK harus dibuat ulang setelah indeks.');
        }
    }

    public function test_down_removes_column_and_index_but_keeps_other_schema(): void
    {
        $this->migration()->down();

        $this->assertFalse(Schema::hasColumn(self::TABLE, self::COLUMN));
        $this->assertFalse($this->hasColumnIndex());
        $this->assertNotNull($this->cooperativeForeignKey(), 'FK cooperative_id tidak boleh ikut terhapus.');
        $this->assertTrue(Schema::hasColumn(self::TABLE, 'module'));

        // down() idempoten: pemanggilan kedua tidak boleh melempar galat.
        $this->migration()->down();
        $this->assertNotNull($this->cooperativeForeignKey());
    }

    public function test_down_keeps_rows_but_drops_the_version_link_column(): void
    {
        // F-69-02: rollback **destruktif** terhadap tautan versi — baris tetap ada, kolom relasi
        // hilang. Tes ini mengunci kedua sisi konsekuensi tersebut.
        $this->seedTwoSubmissions();

        $this->migration()->down();

        $this->assertFalse(Schema::hasColumn(self::TABLE, self::COLUMN), 'Kolom tautan versi dihapus.');
        $this->assertSame(2, DB::table(self::TABLE)->count(), 'Baris pengajuan tetap ada.');
        $this->assertSame(2, DB::table(self::TABLE)->whereNotNull('title')->count());
    }

    // ---------------------------------------------------------------------
    // Cabang up(): mock facade (tanpa DDL)
    // ---------------------------------------------------------------------

    public function test_up_does_not_stop_at_the_column_when_the_foreign_key_is_missing(): void
    {
        // REGRESI Tahap 67: kolom + indeks sudah ada, FK belum. Sebelum perbaikan, up()
        // langsung return sehingga FK tidak pernah dibuat. Sekarang FK harus tetap diperiksa.
        $this->mockExistingColumnAndIndex('mysql');
        Schema::shouldReceive('getForeignKeys')
            ->twice()
            ->with(self::TABLE)
            ->andReturn([], [$this->expectedForeignKey()]);

        $tableCalls = 0;
        Schema::shouldReceive('table')
            ->once()
            ->with(self::TABLE, Mockery::type(Closure::class))
            ->andReturnUsing(function () use (&$tableCalls): void {
                $tableCalls++;
            });

        $this->migration()->up();

        $this->assertSame(1, $tableCalls, 'Penambahan FK harus dicoba walau kolom sudah ada.');
    }

    public function test_up_adds_the_missing_index_before_checking_the_foreign_key(): void
    {
        // F-69-07: membuktikan **urutan** perintah (indeks dibuat sebelum FK), bukan sekadar
        // jumlah pemanggilan — perintah Blueprint ditangkap dan urutannya diperiksa.
        Schema::shouldReceive('hasTable')->once()->with(self::TABLE)->andReturn(true);
        Schema::shouldReceive('hasColumn')->once()->with(self::TABLE, self::COLUMN)->andReturn(true);
        Schema::shouldReceive('getColumns')->once()->with(self::TABLE)->andReturn([$this->expectedColumn()]);
        Schema::shouldReceive('getIndexes')
            ->twice()
            ->with(self::TABLE)
            ->andReturn([], [$this->expectedIndex()]);
        Schema::shouldReceive('getConnection')->andReturn($this->connectionFor('mysql'));
        Schema::shouldReceive('getForeignKeys')
            ->twice()
            ->with(self::TABLE)
            ->andReturn([], [$this->expectedForeignKey()]);

        $batches = $this->captureTableBatches(fn () => $this->migration()->up());

        $this->assertSame(
            ['index', 'foreign'],
            array_map(static fn (array $batch): string => $batch[0]['name'], $batches),
            'Indeks harus dibuat sebelum foreign key.'
        );
        $this->assertSame(self::INDEX, $batches[0][0]['index']);
        $this->assertSame(self::FOREIGN_KEY, $batches[1][0]['index']);
    }

    public function test_up_creates_the_migration_index_when_only_a_differently_named_index_exists(): void
    {
        // F-69-01: indeks bernama lain bukan milik migrasi → tidak diadopsi sebagai pengganti.
        $other = array_merge($this->expectedIndex(), ['name' => 'operator_custom_index']);

        Schema::shouldReceive('hasTable')->once()->with(self::TABLE)->andReturn(true);
        Schema::shouldReceive('hasColumn')->once()->with(self::TABLE, self::COLUMN)->andReturn(true);
        Schema::shouldReceive('getColumns')->once()->with(self::TABLE)->andReturn([$this->expectedColumn('sqlite')]);
        Schema::shouldReceive('getIndexes')
            ->twice()
            ->with(self::TABLE)
            ->andReturn([$other], [$other, $this->expectedIndex()]);
        Schema::shouldReceive('getConnection')->andReturn($this->connectionFor('sqlite'));

        $batches = $this->captureTableBatches(fn () => $this->migration()->up());

        $this->assertCount(1, $batches, 'Indeks milik migrasi tetap dibuat.');
        $this->assertSame('index', $batches[0][0]['name']);
        $this->assertSame(self::INDEX, $batches[0][0]['index']);
    }

    public function test_up_throws_diagnostic_when_the_migration_index_name_reuses_another_definition(): void
    {
        // Nama indeks milik migrasi dipakai ulang untuk definisi berbeda → berhenti, tanpa DDL.
        Schema::shouldReceive('hasTable')->once()->with(self::TABLE)->andReturn(true);
        Schema::shouldReceive('hasColumn')->once()->with(self::TABLE, self::COLUMN)->andReturn(true);
        Schema::shouldReceive('getConnection')->andReturn($this->connectionFor('mysql'));
        Schema::shouldReceive('getColumns')->once()->with(self::TABLE)->andReturn([$this->expectedColumn()]);
        Schema::shouldReceive('getIndexes')->once()->with(self::TABLE)->andReturn([
            array_merge($this->expectedIndex(), ['unique' => true]),
        ]);
        Schema::shouldReceive('getForeignKeys')->never();
        Schema::shouldReceive('table')->never();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('definisi yang tidak sesuai harapan');

        $this->migration()->up();
    }

    public function test_up_skips_when_an_equivalent_foreign_key_exists_under_a_non_conventional_name(): void
    {
        $this->mockExistingColumnAndIndex('mysql');
        Schema::shouldReceive('getForeignKeys')
            ->once()
            ->with(self::TABLE)
            ->andReturn([$this->expectedForeignKey('legacy_custom_fk_name')]);
        Schema::shouldReceive('table')->never();

        $this->migration()->up();

        $this->assertTrue(true, 'FK ekuivalen bernama lain harus diterima tanpa DDL.');
    }

    public function test_up_throws_diagnostic_when_the_foreign_key_conflicts(): void
    {
        $conflicting = $this->expectedForeignKey();
        $conflicting['on_delete'] = 'cascade';

        $this->mockExistingColumnAndIndex('mysql');
        Schema::shouldReceive('getForeignKeys')->once()->with(self::TABLE)->andReturn([$conflicting]);
        Schema::shouldReceive('table')->never();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('tidak sesuai harapan');

        $this->migration()->up();
    }

    public function test_up_skips_foreign_key_on_sqlite_but_still_checks_index(): void
    {
        $this->mockExistingColumnAndIndex('sqlite');
        Schema::shouldReceive('getForeignKeys')->never();
        Schema::shouldReceive('table')->never();

        $this->migration()->up();

        $this->assertTrue(true, 'SQLite: kolom & indeks diperiksa, FK sengaja dilewati.');
    }

    // ---------------------------------------------------------------------
    // Tahap 80 — pravalidasi tanpa DDL (F-74-01/F-79-01)
    // ---------------------------------------------------------------------

    public function test_up_throws_before_any_ddl_when_the_index_conflicts_and_the_column_is_missing(): void
    {
        // REGRESI F-74-01: kolom BELUM ada. Implementasi lama membuat kolom lebih dulu, lalu
        // berhenti karena indeks menyimpang → skema parsial permanen. Kini konflik terdeteksi
        // sebelum DDL apa pun: `Schema::table()` tidak boleh dipanggil sama sekali.
        Schema::shouldReceive('hasTable')->once()->with(self::TABLE)->andReturn(true);
        Schema::shouldReceive('hasColumn')->once()->with(self::TABLE, self::COLUMN)->andReturn(false);
        Schema::shouldReceive('getConnection')->andReturn($this->connectionFor('mysql'));
        Schema::shouldReceive('getIndexes')->once()->with(self::TABLE)->andReturn([
            array_merge($this->expectedIndex(), ['unique' => true]),
        ]);
        Schema::shouldReceive('getColumns')->never();
        Schema::shouldReceive('getForeignKeys')->never();
        Schema::shouldReceive('table')->never();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('indeks bernama');

        $this->migration()->up();
    }

    public function test_up_throws_before_any_ddl_when_the_foreign_key_conflicts_and_the_column_is_missing(): void
    {
        // Sama untuk FK: kolom & indeks belum ada, FK menyimpang → berhenti sebelum DDL.
        $conflicting = $this->expectedForeignKey();
        $conflicting['on_delete'] = 'cascade';

        Schema::shouldReceive('hasTable')->once()->with(self::TABLE)->andReturn(true);
        Schema::shouldReceive('hasColumn')->once()->with(self::TABLE, self::COLUMN)->andReturn(false);
        Schema::shouldReceive('getConnection')->andReturn($this->connectionFor('mysql'));
        Schema::shouldReceive('getIndexes')->once()->with(self::TABLE)->andReturn([]);
        Schema::shouldReceive('getColumns')->never();
        Schema::shouldReceive('getForeignKeys')->once()->with(self::TABLE)->andReturn([$conflicting]);
        Schema::shouldReceive('table')->never();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('definisinya tidak sesuai harapan');

        $this->migration()->up();
    }

    public function test_up_throws_before_any_ddl_when_the_existing_column_is_not_nullable(): void
    {
        Schema::shouldReceive('hasTable')->once()->with(self::TABLE)->andReturn(true);
        Schema::shouldReceive('hasColumn')->once()->with(self::TABLE, self::COLUMN)->andReturn(true);
        Schema::shouldReceive('getConnection')->andReturn($this->connectionFor('mysql'));
        Schema::shouldReceive('getColumns')->once()->with(self::TABLE)->andReturn([
            array_merge($this->expectedColumn(), ['nullable' => false]),
        ]);
        Schema::shouldReceive('getIndexes')->never();
        Schema::shouldReceive('getForeignKeys')->never();
        Schema::shouldReceive('table')->never();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('nullable=tidak');

        $this->migration()->up();
    }

    public function test_up_throws_before_any_ddl_when_the_existing_column_has_an_incompatible_type(): void
    {
        // `int` (bukan `bigint unsigned`) tidak setara dengan `submissions.id` → konflik.
        Schema::shouldReceive('hasTable')->once()->with(self::TABLE)->andReturn(true);
        Schema::shouldReceive('hasColumn')->once()->with(self::TABLE, self::COLUMN)->andReturn(true);
        Schema::shouldReceive('getConnection')->andReturn($this->connectionFor('mysql'));
        Schema::shouldReceive('getColumns')->once()->with(self::TABLE)->andReturn([
            array_merge($this->expectedColumn(), ['type_name' => 'int', 'type' => 'int(11)']),
        ]);
        Schema::shouldReceive('getIndexes')->never();
        Schema::shouldReceive('table')->never();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('tipe=int(11)');

        $this->migration()->up();
    }

    public function test_up_accepts_an_existing_column_with_the_expected_definition(): void
    {
        // Kolom nullable BIGINT UNSIGNED + indeks + FK sudah benar → tanpa DDL sama sekali.
        $this->mockExistingColumnAndIndex('mysql');
        Schema::shouldReceive('getForeignKeys')->once()->with(self::TABLE)->andReturn([$this->expectedForeignKey()]);
        Schema::shouldReceive('table')->never();

        $this->migration()->up();

        $this->assertTrue(true, 'Definisi kolom sesuai → tidak ada DDL dan tidak ada konflik.');
    }

    public function test_classify_column_missing_present_and_conflict(): void
    {
        $migration = $this->migration();

        $this->assertSame('missing', $migration::classifyColumn([], 'mysql'));
        $this->assertSame('missing', $migration::classifyColumn([
            ['name' => 'cooperative_id', 'type_name' => 'bigint', 'type' => 'bigint(20) unsigned', 'nullable' => true],
        ], 'mysql'));

        $this->assertSame('present', $migration::classifyColumn([$this->expectedColumn('mysql')], 'mysql'));
        $this->assertSame('present', $migration::classifyColumn([$this->expectedColumn('sqlite')], 'sqlite'));

        $cases = [
            'not null' => ['nullable' => false],
            'tipe int' => ['type_name' => 'int', 'type' => 'int(11)'],
            'bigint tanpa unsigned' => ['type' => 'bigint(20)'],
            'tipe teks' => ['type_name' => 'varchar', 'type' => 'varchar(255)'],
        ];

        foreach ($cases as $label => $override) {
            $this->assertSame(
                'conflict',
                $migration::classifyColumn([array_merge($this->expectedColumn('mysql'), $override)], 'mysql'),
                "Kasus: {$label}"
            );
        }

        // Metadata SQLite tidak dianggap bukti kesetaraan MariaDB: tipe `integer` diterima sebagai
        // definisi SQLite yang sah, tetapi bukan berarti `BIGINT UNSIGNED` terverifikasi.
        $this->assertSame('conflict', $migration::classifyColumn([
            array_merge($this->expectedColumn('sqlite'), ['nullable' => false]),
        ], 'sqlite'));
    }

    public function test_up_completes_a_partial_schema_without_duplicating_the_index(): void
    {
        // Skema parsial (kolom ada, indeks hilang) → dilengkapi tanpa duplikasi walau dijalankan dua kali.
        $this->dropMigrationForeignKeyAndIndex();

        $this->migration()->up();
        $this->migration()->up();

        $matching = collect(Schema::getIndexes(self::TABLE))
            ->filter(fn (array $index): bool => ($index['columns'] ?? []) === [self::COLUMN]);

        $this->assertTrue(Schema::hasColumn(self::TABLE, self::COLUMN));
        $this->assertCount(1, $matching, 'Harus tepat satu indeks pada parent_submission_id (tanpa duplikasi).');
        $this->assertSame(
            self::INDEX,
            (string) collect(Schema::getIndexes(self::TABLE))->firstWhere('name', self::INDEX)['name'],
            'Indeks milik migrasi harus kembali dengan nama yang benar.'
        );

        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            $this->assertNotNull($this->foreignKeyMetadata(), 'FK harus kembali setelah indeks dipulihkan.');
        }
    }

    public function test_up_creates_the_column_then_index_then_foreign_key_when_all_are_missing(): void
    {
        Schema::shouldReceive('hasTable')->once()->with(self::TABLE)->andReturn(true);
        Schema::shouldReceive('hasColumn')->once()->with(self::TABLE, self::COLUMN)->andReturn(false);
        Schema::shouldReceive('getConnection')->andReturn($this->connectionFor('mysql'));
        Schema::shouldReceive('getIndexes')
            ->twice()
            ->with(self::TABLE)
            ->andReturn([], [$this->expectedIndex()]);
        Schema::shouldReceive('getForeignKeys')
            ->twice()
            ->with(self::TABLE)
            ->andReturn([], [$this->expectedForeignKey()]);
        Schema::shouldReceive('getColumns')
            ->once()
            ->with(self::TABLE)
            ->andReturn([$this->expectedColumn()]);

        $batches = $this->captureTableBatches(fn () => $this->migration()->up());

        $this->assertSame(
            [self::COLUMN, 'index', 'foreign'],
            array_map(static fn (array $batch): string => (string) $batch[0]['name'], $batches),
            'Urutan DDL harus kolom → indeks → foreign key.'
        );
        $this->assertSame('bigInteger', $batches[0][0]['type']);
        $this->assertTrue($batches[0][0]['nullable'], 'Kolom baru harus nullable (versi awal).');
        $this->assertSame(self::INDEX, $batches[1][0]['index']);
        $this->assertSame(self::FOREIGN_KEY, $batches[2][0]['index']);
    }

    public function test_up_post_verification_detects_an_object_that_was_not_created(): void
    {
        // DDL "dijalankan" tetapi metadata tetap melaporkan kolom tidak ada → deteksi (bukan
        // rollback), dengan diagnostik yang menyebut objek. Tidak ada penghapusan otomatis.
        Schema::shouldReceive('hasTable')->once()->with(self::TABLE)->andReturn(true);
        Schema::shouldReceive('hasColumn')->once()->with(self::TABLE, self::COLUMN)->andReturn(false);
        Schema::shouldReceive('getConnection')->andReturn($this->connectionFor('mysql'));
        Schema::shouldReceive('getIndexes')->andReturn([]);
        Schema::shouldReceive('getForeignKeys')->andReturn([]);
        Schema::shouldReceive('table')->times(3);
        Schema::shouldReceive('getColumns')->once()->with(self::TABLE)->andReturn([]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Verifikasi pascaoperasi gagal: kolom `parent_submission_id`');

        $this->migration()->up();
    }

    // ---------------------------------------------------------------------
    // Tahap 82 — kegagalan DDL & verifikasi pascaoperasi (penutupan A-81-03)
    // ---------------------------------------------------------------------

    public function test_up_propagates_the_index_ddl_failure_and_skips_the_remaining_steps(): void
    {
        // Skenario A: DDL kolom berjalan, DDL indeks melempar. Exception asli harus diteruskan
        // (tidak ditelan dan tidak diubah menjadi sukses); DDL ketiga (FK) dan verifikasi
        // pascaoperasi tidak dijalankan. Tidak ada klaim bahwa DDL kolom ikut dibatalkan.
        $calls = [];
        $this->mockMissingSchemaWithFailingDdl(2, 'ledakan DDL indeks', $calls);

        $thrown = null;

        try {
            $this->migration()->up();
        } catch (RuntimeException $exception) {
            $thrown = $exception;
        }

        $this->assertInstanceOf(RuntimeException::class, $thrown, 'up() harus meneruskan kegagalan DDL.');
        $this->assertSame('ledakan DDL indeks', $thrown->getMessage());
        $this->assertSame(
            [self::COLUMN, 'index'],
            $calls,
            'Hanya DDL kolom lalu indeks yang dicoba; DDL ketiga tidak dijalankan.'
        );
    }

    public function test_up_propagates_the_foreign_key_ddl_failure_and_skips_post_verification(): void
    {
        // Skenario B: DDL kolom + indeks berjalan, DDL FK melempar. Ketiga DDL dipanggil sesuai
        // urutan; exception diteruskan dan verifikasi pascaoperasi tidak dimulai sama sekali.
        $calls = [];
        $this->mockMissingSchemaWithFailingDdl(3, 'ledakan DDL foreign key', $calls);

        $thrown = null;

        try {
            $this->migration()->up();
        } catch (RuntimeException $exception) {
            $thrown = $exception;
        }

        $this->assertInstanceOf(RuntimeException::class, $thrown, 'up() harus meneruskan kegagalan DDL.');
        $this->assertSame('ledakan DDL foreign key', $thrown->getMessage());
        $this->assertSame(
            [self::COLUMN, 'index', 'foreign'],
            $calls,
            'Urutan DDL kolom → indeks → FK dicoba sampai DDL yang gagal.'
        );
    }

    public function test_up_post_verification_fails_when_the_index_was_not_created(): void
    {
        // Skenario C: seluruh DDL dipanggil, tetapi metadata sesudahnya tidak memuat indeks
        // migrasi → verifikasi pascaoperasi berhenti dengan diagnostik (deteksi, bukan rollback).
        Schema::shouldReceive('hasTable')->once()->with(self::TABLE)->andReturn(true);
        Schema::shouldReceive('hasColumn')->once()->with(self::TABLE, self::COLUMN)->andReturn(false);
        Schema::shouldReceive('getConnection')->andReturn($this->connectionFor('mysql'));
        Schema::shouldReceive('getColumns')
            ->once()
            ->with(self::TABLE)
            ->andReturn([$this->expectedColumn()]);
        Schema::shouldReceive('getIndexes')
            ->twice()
            ->with(self::TABLE)
            ->andReturn([], []);
        Schema::shouldReceive('getForeignKeys')->once()->with(self::TABLE)->andReturn([]);

        $calls = [];
        $this->recordTableCommands($calls);

        $thrown = null;

        try {
            $this->migration()->up();
        } catch (RuntimeException $exception) {
            $thrown = $exception;
        }

        $this->assertInstanceOf(RuntimeException::class, $thrown, 'Indeks tidak terbentuk harus terdeteksi.');
        $this->assertStringContainsString(
            'Verifikasi pascaoperasi gagal: indeks `'.self::INDEX.'`',
            $thrown->getMessage()
        );
        $this->assertStringContainsString(
            'tidak ada indeks bernama `'.self::INDEX.'`',
            $thrown->getMessage(),
            'Diagnostik harus menyebut keadaan metadata yang benar-benar terbaca.'
        );
        $this->assertSame(
            [self::COLUMN, 'index', 'foreign'],
            $calls,
            'Verifikasi berjalan setelah seluruh DDL; tidak ada DDL pemulihan/rollback.'
        );
    }

    public function test_up_post_verification_fails_when_the_foreign_key_was_not_created(): void
    {
        // Skenario D: seluruh DDL dipanggil, tetapi metadata sesudahnya tidak memuat FK → berhenti
        // dengan diagnostik; exception tidak ditelan dan tidak ada rollback otomatis.
        Schema::shouldReceive('hasTable')->once()->with(self::TABLE)->andReturn(true);
        Schema::shouldReceive('hasColumn')->once()->with(self::TABLE, self::COLUMN)->andReturn(false);
        Schema::shouldReceive('getConnection')->andReturn($this->connectionFor('mysql'));
        Schema::shouldReceive('getColumns')
            ->once()
            ->with(self::TABLE)
            ->andReturn([$this->expectedColumn()]);
        Schema::shouldReceive('getIndexes')
            ->twice()
            ->with(self::TABLE)
            ->andReturn([], [$this->expectedIndex()]);
        Schema::shouldReceive('getForeignKeys')
            ->twice()
            ->with(self::TABLE)
            ->andReturn([], []);

        $calls = [];
        $this->recordTableCommands($calls);

        $thrown = null;

        try {
            $this->migration()->up();
        } catch (RuntimeException $exception) {
            $thrown = $exception;
        }

        $this->assertInstanceOf(RuntimeException::class, $thrown, 'FK tidak terbentuk harus terdeteksi.');
        $this->assertStringContainsString(
            'Verifikasi pascaoperasi gagal: foreign key pada kolom `'.self::COLUMN.'`',
            $thrown->getMessage()
        );
        $this->assertStringContainsString(
            'tidak ada FK pada kolom `'.self::COLUMN.'`',
            $thrown->getMessage()
        );
        $this->assertSame(
            [self::COLUMN, 'index', 'foreign'],
            $calls,
            'Verifikasi berjalan setelah seluruh DDL; tidak ada DDL pemulihan/rollback.'
        );
    }

    // ---------------------------------------------------------------------
    // Cabang down(): mock facade (tanpa DDL)
    // ---------------------------------------------------------------------

    public function test_down_drops_the_migration_foreign_key_and_index_before_the_column(): void
    {
        $this->mockDownState('mysql', [$this->expectedForeignKey()], [$this->expectedIndex()]);

        $batches = $this->captureTableBatches(fn () => $this->migration()->down());

        $commands = array_map(static fn (array $batch): string => $batch[0]['name'], $batches);

        $this->assertSame(['dropForeign', 'dropIndex', 'dropColumn'], $commands);
        $this->assertSame(self::FOREIGN_KEY, $batches[0][0]['index'], 'Nama constraint dilaporkan driver.');
        $this->assertSame(self::INDEX, $batches[1][0]['index']);
    }

    public function test_down_does_not_release_a_foreign_key_with_a_conflicting_definition(): void
    {
        $conflicting = $this->expectedForeignKey();
        $conflicting['on_delete'] = 'cascade';

        $this->mockDownState('mysql', [$conflicting], [$this->expectedIndex()]);

        $batches = $this->captureTableBatches(fn () => $this->migration()->down());

        $this->assertSame(
            ['dropIndex', 'dropColumn'],
            array_map(static fn (array $batch): string => $batch[0]['name'], $batches),
            'FK menyimpang tidak boleh dihapus.'
        );
    }

    public function test_down_does_not_release_a_nameless_foreign_key(): void
    {
        $nameless = $this->expectedForeignKey();
        unset($nameless['name']);

        $this->mockDownState('mysql', [$nameless], [$this->expectedIndex()]);

        $batches = $this->captureTableBatches(fn () => $this->migration()->down());

        $this->assertSame(
            ['dropIndex', 'dropColumn'],
            array_map(static fn (array $batch): string => $batch[0]['name'], $batches),
            'FK tanpa nama tidak dapat dilepas dengan aman.'
        );
    }

    public function test_down_never_drops_indexes_owned_by_other_parties(): void
    {
        // F-69-01: indeks bernama lain — termasuk unik, komposit, dan primary key — tidak dihapus.
        $this->mockDownState('mysql', [], [
            array_merge($this->expectedIndex(), ['name' => 'operator_custom_index']),
            ['name' => 'operator_unique_index', 'columns' => [self::COLUMN], 'unique' => true, 'primary' => false],
            ['name' => 'operator_composite_index', 'columns' => [self::COLUMN, 'status'], 'unique' => false, 'primary' => false],
            ['name' => 'primary', 'columns' => [self::COLUMN], 'unique' => true, 'primary' => true],
        ]);

        $batches = $this->captureTableBatches(fn () => $this->migration()->down());

        $this->assertSame(
            ['dropColumn'],
            array_map(static fn (array $batch): string => $batch[0]['name'], $batches),
            'Hanya kolom yang dilepas; indeks pihak lain tidak disentuh.'
        );
    }

    public function test_down_stops_when_the_migration_index_name_reuses_another_definition(): void
    {
        $this->mockDownState('mysql', [], [
            array_merge($this->expectedIndex(), ['columns' => [self::COLUMN, 'status']]),
        ]);
        Schema::shouldReceive('table')->never();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('indeks bernama');

        $this->migration()->down();
    }

    public function test_down_on_sqlite_only_releases_the_index_then_the_column(): void
    {
        $this->mockDownState('sqlite', [], [$this->expectedIndex()]);

        $batches = $this->captureTableBatches(fn () => $this->migration()->down());

        $this->assertSame(
            ['dropIndex', 'dropColumn'],
            array_map(static fn (array $batch): string => $batch[0]['name'], $batches),
            'SQLite: FK tidak dibuat migrasi ini, jadi tidak ada dropForeign.'
        );
    }

    // ---------------------------------------------------------------------
    // classifyIndex() — unit, tanpa DB
    // ---------------------------------------------------------------------

    public function test_classify_index_present_when_the_migration_index_exists(): void
    {
        $this->assertSame('present', $this->migration()::classifyIndex([$this->expectedIndex()]));
    }

    public function test_classify_index_missing_when_no_index_uses_the_migration_name(): void
    {
        $migration = $this->migration();

        $this->assertSame('missing', $migration::classifyIndex([]));
        $this->assertSame('missing', $migration::classifyIndex([
            array_merge($this->expectedIndex(), ['name' => 'operator_custom_index']),
        ]), 'Definisi serupa tetapi nama lain → bukan milik migrasi.');
        $this->assertSame('missing', $migration::classifyIndex([
            ['name' => 'primary', 'columns' => ['id'], 'unique' => true, 'primary' => true],
        ]), 'Primary key tabel bukan indeks milik migrasi.');
    }

    public function test_classify_index_conflict_when_the_migration_name_reuses_another_definition(): void
    {
        $migration = $this->migration();

        $cases = [
            'unik' => ['unique' => true],
            'komposit' => ['columns' => [self::COLUMN, 'status']],
            'primary key' => ['primary' => true],
            'kolom lain' => ['columns' => ['cooperative_id']],
        ];

        foreach ($cases as $label => $override) {
            $this->assertSame(
                'conflict',
                $migration::classifyIndex([array_merge($this->expectedIndex(), $override)]),
                "Kasus: {$label}"
            );
        }
    }

    public function test_index_conflict_message_is_diagnostic(): void
    {
        $message = $this->migration()::indexConflictMessage([
            array_merge($this->expectedIndex(), ['unique' => true]),
        ]);

        $this->assertStringContainsString('definisi yang tidak sesuai harapan', $message);
        $this->assertStringContainsString(self::INDEX, $message);
        $this->assertStringContainsString('unik=ya', $message);
    }

    // ---------------------------------------------------------------------
    // classify() — unit, tanpa DB
    // ---------------------------------------------------------------------

    public function test_classify_present_when_expected_foreign_key_exists(): void
    {
        $this->assertSame('present', $this->migration()::classify([$this->expectedForeignKey()]));
    }

    public function test_classify_is_case_insensitive_for_referential_rules(): void
    {
        $foreignKey = $this->expectedForeignKey();
        $foreignKey['on_delete'] = 'SET NULL';
        $foreignKey['on_update'] = 'RESTRICT';

        $this->assertSame('present', $this->migration()::classify([$foreignKey]));
    }

    public function test_classify_accepts_no_action_as_default_update_rule(): void
    {
        $foreignKey = $this->expectedForeignKey();
        $foreignKey['on_update'] = 'no action';

        $this->assertSame('present', $this->migration()::classify([$foreignKey]));
    }

    public function test_classify_ignores_the_constraint_name(): void
    {
        $foreignKey = $this->expectedForeignKey('manual_name_created_by_operator');

        $this->assertSame('present', $this->migration()::classify([$foreignKey]));
    }

    public function test_classify_missing_when_no_foreign_key_on_column(): void
    {
        $otherColumn = $this->expectedForeignKey();
        $otherColumn['columns'] = ['cooperative_id'];

        $guard = $this->migration();

        $this->assertSame('missing', $guard::classify([]));
        $this->assertSame('missing', $guard::classify([$otherColumn]));
        $this->assertSame('missing', $guard::classify([['columns' => null]]));
    }

    public function test_classify_conflict_when_definition_differs(): void
    {
        $guard = $this->migration();

        $cases = [
            'tabel referensi' => ['foreign_table' => 'cooperatives'],
            'kolom referensi' => ['foreign_columns' => ['koperasi_id']],
            'komposit' => ['columns' => [self::COLUMN, 'status']],
            'on delete restrict' => ['on_delete' => 'restrict'],
            'on delete cascade' => ['on_delete' => 'cascade'],
            'on update cascade' => ['on_update' => 'cascade'],
            'aturan hilang' => ['on_delete' => null, 'on_update' => null],
        ];

        foreach ($cases as $label => $override) {
            $foreignKey = array_merge($this->expectedForeignKey(), $override);
            $this->assertSame('conflict', $guard::classify([$foreignKey]), "Kasus: {$label}");
        }
    }

    public function test_classify_conflict_when_one_of_multiple_foreign_keys_differs(): void
    {
        $guard = $this->migration();

        $this->assertSame('present', $guard::classify([
            $this->expectedForeignKey(),
            $this->expectedForeignKey('second_name'),
        ]));

        $wrong = $this->expectedForeignKey('third_name');
        $wrong['on_delete'] = 'cascade';

        $this->assertSame('conflict', $guard::classify([
            $this->expectedForeignKey(),
            $wrong,
        ]));
    }

    // ---------------------------------------------------------------------
    // foreignKeysToDrop() — unit, tanpa DB
    // ---------------------------------------------------------------------

    public function test_foreign_keys_to_drop_uses_the_actual_reported_name(): void
    {
        $guard = $this->migration();

        $this->assertSame(
            [self::FOREIGN_KEY],
            $guard::foreignKeysToDrop([$this->expectedForeignKey()])
        );

        $this->assertSame(
            ['manual_name_created_by_operator'],
            $guard::foreignKeysToDrop([$this->expectedForeignKey('manual_name_created_by_operator')]),
            'Nama constraint manual harus dipakai apa adanya (bukan asumsi nama konvensional).'
        );
    }

    public function test_foreign_keys_to_drop_refuses_missing_or_conflicting_keys(): void
    {
        $guard = $this->migration();

        $this->assertSame([], $guard::foreignKeysToDrop([]));

        $conflicting = $this->expectedForeignKey();
        $conflicting['on_delete'] = 'cascade';
        $this->assertSame([], $guard::foreignKeysToDrop([$conflicting]), 'FK menyimpang tidak boleh dihapus.');

        $nameless = $this->expectedForeignKey();
        unset($nameless['name']);
        $this->assertSame([], $guard::foreignKeysToDrop([$nameless]), 'Tanpa nama, penghapusan tidak aman.');
    }

    // ---------------------------------------------------------------------
    // Helper
    // ---------------------------------------------------------------------

    /**
     * Mock skema "kolom + indeks + FK semuanya belum ada" pada driver MySQL, dengan `Schema::table`
     * yang mencatat urutan perintah dan melempar pada panggilan ke-`$failAt` (Tahap 82).
     *
     * `getColumns()` tidak boleh dipanggil: kolom belum ada → pravalidasi melewatinya, dan
     * verifikasi pascaoperasi tidak akan tercapai karena DDL gagal lebih dulu.
     *
     * @param  list<string>  $calls
     */
    private function mockMissingSchemaWithFailingDdl(int $failAt, string $message, array &$calls): void
    {
        Schema::shouldReceive('hasTable')->once()->with(self::TABLE)->andReturn(true);
        Schema::shouldReceive('hasColumn')->once()->with(self::TABLE, self::COLUMN)->andReturn(false);
        Schema::shouldReceive('getConnection')->andReturn($this->connectionFor('mysql'));
        Schema::shouldReceive('getIndexes')->once()->with(self::TABLE)->andReturn([]);
        Schema::shouldReceive('getForeignKeys')->once()->with(self::TABLE)->andReturn([]);
        Schema::shouldReceive('getColumns')->never();

        $this->recordTableCommands($calls, $failAt, $message);
    }

    /**
     * Mock `Schema::table()` tanpa DDL: nama perintah Blueprint tiap panggilan dicatat ke `$calls`.
     *
     * Bila `$failAt` diisi, panggilan ke-`$failAt` melempar `$message` setelah namanya tercatat —
     * meniru kegagalan statement DDL pada driver nyata.
     *
     * @param  list<string>  $calls
     */
    private function recordTableCommands(array &$calls, ?int $failAt = null, string $message = ''): void
    {
        Schema::shouldReceive('table')
            ->zeroOrMoreTimes()
            ->with(self::TABLE, Mockery::type(Closure::class))
            ->andReturnUsing(function (string $table, Closure $callback) use (&$calls, $failAt, $message): void {
                $calls[] = $this->firstCommandName($callback);

                if ($failAt !== null && count($calls) === $failAt) {
                    throw new RuntimeException($message);
                }
            });
    }

    /**
     * Nama perintah Blueprint pertama yang diminta closure migrasi — tanpa mengeksekusi DDL.
     */
    private function firstCommandName(Closure $callback): string
    {
        $blueprint = new Blueprint(DB::connection(), self::TABLE, $callback);

        return (string) $blueprint->getCommands()[0]->name;
    }

    /**
     * Instans implementasi migrasi yang sebenarnya (anonymous class).
     */
    private function migration(): object
    {
        return require database_path(self::MIGRATION);
    }

    /**
     * Siapkan ekspektasi facade: kolom + indeks sudah ada, driver ditentukan pemanggil.
     */
    private function mockExistingColumnAndIndex(string $driver): void
    {
        Schema::shouldReceive('hasTable')->once()->with(self::TABLE)->andReturn(true);
        Schema::shouldReceive('hasColumn')->once()->with(self::TABLE, self::COLUMN)->andReturn(true);
        Schema::shouldReceive('getColumns')->once()->with(self::TABLE)->andReturn([$this->expectedColumn($driver)]);
        Schema::shouldReceive('getIndexes')->once()->with(self::TABLE)->andReturn([$this->expectedIndex()]);
        Schema::shouldReceive('getConnection')->andReturn($this->connectionFor($driver));
    }

    /**
     * Siapkan ekspektasi facade untuk `down()` pada SQLite/MariaDB mock.
     *
     * @param  array<int, array<string, mixed>>  $foreignKeys
     * @param  array<int, array<string, mixed>>  $indexes
     */
    private function mockDownState(string $driver, array $foreignKeys, array $indexes): void
    {
        Schema::shouldReceive('hasTable')->once()->with(self::TABLE)->andReturn(true);
        Schema::shouldReceive('hasColumn')->once()->with(self::TABLE, self::COLUMN)->andReturn(true);
        Schema::shouldReceive('getConnection')->andReturn($this->connectionFor($driver));

        if ($driver === 'sqlite') {
            Schema::shouldReceive('getForeignKeys')->never();
        } else {
            Schema::shouldReceive('getForeignKeys')->once()->with(self::TABLE)->andReturn($foreignKeys);
        }

        Schema::shouldReceive('getIndexes')->once()->with(self::TABLE)->andReturn($indexes);
    }

    /**
     * Jalankan `$action` dengan `Schema::table` dimock: setiap Blueprint yang dibuat migrasi
     * dieksekusi agar daftar perintahnya dapat diperiksa **tanpa DDL**.
     *
     * @param  callable():void  $action
     * @return list<list<array<string, mixed>>>
     */
    private function captureTableBatches(callable $action): array
    {
        $batches = [];

        Schema::shouldReceive('table')
            ->zeroOrMoreTimes()
            ->with(self::TABLE, Mockery::type(Closure::class))
            ->andReturnUsing(function (string $table, Closure $callback) use (&$batches): void {
                // Blueprint sungguhan (koneksi SQLite tes) agar perintah yang diminta tercatat;
                // `build()` tidak pernah dipanggil → tidak ada DDL yang dieksekusi.
                $blueprint = new Blueprint(DB::connection(), $table, $callback);

                $batches[] = array_map(
                    static function ($command): array {
                        return [
                            'name' => (string) $command->name,
                            'index' => $command->index ?? null,
                            'columns' => $command->columns ?? null,
                            'on' => $command->on ?? null,
                            'references' => $command->references ?? null,
                            'onDelete' => $command->onDelete ?? null,
                            'type' => $command->type ?? null,
                            'nullable' => $command->nullable ?? null,
                        ];
                    },
                    $blueprint->getCommands()
                );
            });

        $action();

        return $batches;
    }

    private function connectionFor(string $driver): Connection
    {
        $connection = Mockery::mock(Connection::class);
        $connection->shouldReceive('getDriverName')->andReturn($driver);

        return $connection;
    }

    /**
     * @return array<string, mixed>
     */
    private function expectedIndex(): array
    {
        return [
            'name' => self::INDEX,
            'columns' => [self::COLUMN],
            'type' => 'index',
            'unique' => false,
            'primary' => false,
        ];
    }

    /**
     * Metadata kolom sesuai rancangan (bentuk kembalian `Schema::getColumns()`, Tahap 80).
     *
     * MySQL/MariaDB: `type_name` = `data_type`, `type` = `column_type` (`bigint(20) unsigned`).
     * SQLite: tipe dinamis — `unsignedBigInteger` dipetakan menjadi `integer` tanpa atribut UNSIGNED.
     *
     * @return array<string, mixed>
     */
    private function expectedColumn(string $driver = 'mysql'): array
    {
        $isSqlite = $driver === 'sqlite';

        return [
            'name' => self::COLUMN,
            'type_name' => $isSqlite ? 'integer' : 'bigint',
            'type' => $isSqlite ? 'integer' : 'bigint(20) unsigned',
            'collation' => null,
            'nullable' => true,
            'default' => null,
            'auto_increment' => false,
            'comment' => null,
            'generation' => null,
        ];
    }

    /**
     * Metadata FK sesuai rancangan (bentuk kembalian `Schema::getForeignKeys()`).
     *
     * @return array<string, mixed>
     */
    private function expectedForeignKey(string $name = self::FOREIGN_KEY): array
    {
        return [
            'name' => $name,
            'columns' => [self::COLUMN],
            'foreign_schema' => null,
            'foreign_table' => self::TABLE,
            'foreign_columns' => ['id'],
            'on_update' => 'restrict',
            'on_delete' => 'set null',
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function foreignKeyMetadata(): ?array
    {
        return collect(Schema::getForeignKeys(self::TABLE))->first(
            fn (array $foreignKey) => in_array(self::COLUMN, $foreignKey['columns'], true)
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    private function cooperativeForeignKey(): ?array
    {
        return collect(Schema::getForeignKeys(self::TABLE))->first(
            fn (array $foreignKey) => in_array('cooperative_id', $foreignKey['columns'], true)
        );
    }

    private function hasColumnIndex(): bool
    {
        return collect(Schema::getIndexes(self::TABLE))->contains(
            fn (array $index) => ($index['columns'] ?? []) === [self::COLUMN]
        );
    }

    /**
     * Melepas FK lalu indeks milik migrasi untuk mensimulasikan skema parsial.
     *
     * MariaDB menolak menghapus indeks yang masih dipakai FK (errno 1553), jadi
     * FK harus dilepas lebih dulu. SQLite tidak memiliki self-FK dari migrasi ini.
     */
    private function dropMigrationForeignKeyAndIndex(): void
    {
        $foreignKey = $this->foreignKeyMetadata();

        if ($foreignKey !== null && isset($foreignKey['name'])) {
            Schema::table(self::TABLE, function (Blueprint $table) use ($foreignKey): void {
                $table->dropForeign((string) $foreignKey['name']);
            });
        }

        if ($this->hasColumnIndex()) {
            Schema::table(self::TABLE, function (Blueprint $table): void {
                $table->dropIndex(self::INDEX);
            });
        }
    }

    /**
     * Dua baris submissions (induk & versi turunan) dengan kolom milik migrasi ini terisi.
     *
     * @return array{0: int, 1: int}
     */
    private function seedTwoSubmissions(): array
    {
        $cooperative = Cooperative::factory()->create();
        $user = User::factory()->create();

        $base = [
            'cooperative_id' => $cooperative->id,
            'submitted_by' => $user->id,
            'module' => 'koperasi_induk',
            'status' => 'needs_revision',
            'created_at' => now(),
            'updated_at' => now(),
        ];

        $parentId = DB::table(self::TABLE)->insertGetId($base + ['title' => 'Versi awal']);

        $childId = DB::table(self::TABLE)->insertGetId($base + [
            'title' => 'Versi turunan',
            'parent_submission_id' => $parentId,
        ]);

        return [(int) $parentId, (int) $childId];
    }
}
