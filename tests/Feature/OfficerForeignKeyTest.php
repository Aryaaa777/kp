<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\TestCase;

/**
 * Membuktikan foreign key `officers.cooperative_id` → `cooperatives.id` terbentuk dengan
 * urutan migrasi yang benar, dan bahwa guard pada migrasi
 * `2026_10_16_100004_add_cooperative_foreign_key_to_officers_table` menerapkan kebijakan yang benar.
 *
 * Latar belakang: migrasi pembuatan `officers` (`2026_10_16_022847`) berjalan sebelum
 * `2026_10_16_100002_create_cooperatives_table`, sehingga FK inline menyebabkan `errno 150`
 * pada instalasi dari nol (MariaDB). Perbaikan memindahkan FK ke migrasi
 * `2026_10_16_100004_add_cooperative_foreign_key_to_officers_table`.
 *
 * Catatan keterbatasan: SQLite (lingkungan tes) **tidak** menegakkan keberadaan tabel induk saat
 * `CREATE TABLE` dan metadata FK-nya berbeda dari MariaDB, sehingga:
 * - urutan migrasi diperiksa lewat nama berkas (urutan yang dipakai Laravel);
 * - logika guard (`classify()`) diuji unit dengan metadata yang merepresentasikan struktur
 *   `Schema::getForeignKeys()` (bentuk yang sama di MySQL/MariaDB, lihat `MySqlProcessor`).
 * Perilaku nyata guard pada metadata MariaDB 10.4.32 dibuktikan terpisah (jalur skip) dan pada
 * pengujian statis/unit; klaim kompatibilitas MariaDB tidak diambil hanya dari SQLite.
 *
 * Masalah konkurensi/locking MariaDB tetap merupakan pengujian terpisah.
 */
class OfficerForeignKeyTest extends TestCase
{
    use RefreshDatabase;

    private const OFFICERS_MIGRATION = 'migrations/2026_10_16_022847_create_officers_table.php';

    private const FK_MIGRATION = 'migrations/2026_10_16_100004_add_cooperative_foreign_key_to_officers_table.php';

    // ---------------------------------------------------------------------
    // Integritas skema (SQLite)
    // ---------------------------------------------------------------------

    public function test_officers_cooperative_foreign_key_exists(): void
    {
        $foreignKey = collect(Schema::getForeignKeys('officers'))->first(
            fn (array $fk) => in_array('cooperative_id', $fk['columns'], true)
        );

        $this->assertNotNull($foreignKey, 'FK officers.cooperative_id tidak ditemukan.');
        $this->assertSame('cooperatives', $foreignKey['foreign_table']);
        $this->assertSame(['id'], $foreignKey['foreign_columns']);
        $this->assertSame('restrict', $foreignKey['on_delete']);
    }

    public function test_foreign_key_is_created_after_the_parent_cooperatives_table(): void
    {
        $migrations = collect(glob(database_path('migrations/*.php')))
            ->map(fn (string $path) => basename($path))
            ->values();

        $officersTable = $migrations->first(
            fn (string $name) => str_contains($name, 'create_officers_table')
        );
        $cooperativesTable = $migrations->first(
            fn (string $name) => str_contains($name, 'create_cooperatives_table')
        );
        $foreignKeyMigration = $migrations->first(
            fn (string $name) => str_contains($name, 'add_cooperative_foreign_key_to_officers_table')
        );

        $this->assertNotNull($officersTable, 'Migrasi create_officers_table tidak ditemukan.');
        $this->assertNotNull($cooperativesTable, 'Migrasi create_cooperatives_table tidak ditemukan.');
        $this->assertNotNull($foreignKeyMigration, 'Migrasi FK officers tidak ditemukan.');

        // Laravel menjalankan migrasi terurut berdasarkan nama berkas.
        $this->assertTrue(
            $officersTable < $cooperativesTable,
            'create_officers_table harus berjalan sebelum create_cooperatives_table.'
        );
        $this->assertTrue(
            $cooperativesTable < $foreignKeyMigration,
            'Migrasi FK officers harus berjalan setelah create_cooperatives_table.'
        );
    }

    public function test_officers_create_migration_does_not_define_the_foreign_key_inline(): void
    {
        $path = database_path(self::OFFICERS_MIGRATION);
        $source = file_get_contents($path);

        $this->assertStringNotContainsString(
            "->constrained('cooperatives')",
            $source,
            'Migrasi officers tidak boleh membuat FK inline ke cooperatives.'
        );
        $this->assertStringContainsString('foreignId(\'cooperative_id\')', $source);
    }

    // ---------------------------------------------------------------------
    // Logika guard: classify() (unit, tanpa DB)
    // ---------------------------------------------------------------------

    public function test_classify_present_when_expected_foreign_key_exists(): void
    {
        $this->assertSame('present', $this->guard()::classify([$this->expectedForeignKey()]));
    }

    public function test_classify_is_case_insensitive_for_referential_rules(): void
    {
        $foreignKey = $this->expectedForeignKey();
        $foreignKey['on_update'] = 'RESTRICT';
        $foreignKey['on_delete'] = 'RESTRICT';

        $this->assertSame('present', $this->guard()::classify([$foreignKey]));
    }

    public function test_classify_accepts_no_action_as_restrict_equivalent(): void
    {
        // MariaDB melaporkan RESTRICT; SQLite melaporkan NO ACTION saat klausa ON UPDATE tidak
        // ditulis eksplisit. Keduanya sama-sama memblokir operasi induk → dianggap sesuai.
        $foreignKey = $this->expectedForeignKey();
        $foreignKey['on_update'] = 'no action';
        $foreignKey['on_delete'] = 'no action';

        $this->assertSame('present', $this->guard()::classify([$foreignKey]));
    }

    public function test_classify_missing_when_no_foreign_key_on_column(): void
    {
        $otherColumn = $this->expectedForeignKey();
        $otherColumn['columns'] = ['name'];

        $guard = $this->guard();

        $this->assertSame('missing', $guard::classify([]), 'Metadata kosong harus dianggap belum ada FK.');
        $this->assertSame('missing', $guard::classify([$otherColumn]), 'FK pada kolom lain tidak relevan.');
        $this->assertSame('missing', $guard::classify([['columns' => null]]), 'Metadata cacat tidak boleh dianggap FK valid.');
    }

    public function test_classify_conflict_when_referenced_table_is_wrong(): void
    {
        $foreignKey = $this->expectedForeignKey();
        $foreignKey['foreign_table'] = 'regions';

        $this->assertSame('conflict', $this->guard()::classify([$foreignKey]));
    }

    public function test_classify_conflict_when_referenced_column_is_wrong(): void
    {
        $foreignKey = $this->expectedForeignKey();
        $foreignKey['foreign_columns'] = ['koperasi_id'];

        $this->assertSame('conflict', $this->guard()::classify([$foreignKey]));
    }

    public function test_classify_conflict_when_delete_rule_differs(): void
    {
        $foreignKey = $this->expectedForeignKey();
        $foreignKey['on_delete'] = 'set null';

        $this->assertSame('conflict', $this->guard()::classify([$foreignKey]));
    }

    public function test_classify_conflict_when_update_rule_differs(): void
    {
        $foreignKey = $this->expectedForeignKey();
        $foreignKey['on_update'] = 'cascade';

        $this->assertSame('conflict', $this->guard()::classify([$foreignKey]));
    }

    public function test_classify_conflict_when_referential_rules_are_missing(): void
    {
        $foreignKey = $this->expectedForeignKey();
        unset($foreignKey['on_delete'], $foreignKey['on_update']);

        $this->assertSame('conflict', $this->guard()::classify([$foreignKey]));
    }

    public function test_classify_conflict_when_foreign_key_is_composite(): void
    {
        $foreignKey = $this->expectedForeignKey();
        $foreignKey['columns'] = ['cooperative_id', 'position'];

        $this->assertSame('conflict', $this->guard()::classify([$foreignKey]));
    }

    public function test_classify_present_only_when_all_foreign_keys_on_column_match(): void
    {
        $guard = $this->guard();

        // Dua FK identik & benar → tetap "present".
        $this->assertSame('present', $guard::classify([
            $this->expectedForeignKey(),
            $this->expectedForeignKey(),
        ]));

        // Salah satu menyimpang → ambigu → "conflict".
        $wrong = $this->expectedForeignKey();
        $wrong['on_delete'] = 'cascade';
        $this->assertSame('conflict', $guard::classify([
            $this->expectedForeignKey(),
            $wrong,
        ]));
    }

    // ---------------------------------------------------------------------
    // Perilaku up()/down()
    // ---------------------------------------------------------------------

    public function test_up_is_idempotent_when_expected_foreign_key_already_exists(): void
    {
        $guard = $this->guard();

        // Skema SQLite hasil migrasi sudah memiliki FK yang benar → up() harus melewati (skip).
        $guard->up();
        $guard->up();

        $this->assertNotNull(
            collect(Schema::getForeignKeys('officers'))->first(
                fn (array $fk) => in_array('cooperative_id', $fk['columns'], true)
            ),
            'FK harus tetap ada setelah up() dijalankan berulang.'
        );
    }

    public function test_up_throws_diagnostic_when_foreign_key_conflicts(): void
    {
        $wrong = $this->expectedForeignKey();
        $wrong['foreign_table'] = 'regions';

        Schema::shouldReceive('hasTable')->once()->with('officers')->andReturn(true);
        Schema::shouldReceive('getForeignKeys')->once()->with('officers')->andReturn([$wrong]);
        Schema::shouldReceive('table')->never();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('tidak sesuai harapan');

        $this->guard()->up();
    }

    public function test_up_attempts_to_add_foreign_key_when_missing(): void
    {
        Schema::shouldReceive('hasTable')->once()->with('officers')->andReturn(true);
        Schema::shouldReceive('getForeignKeys')->once()->with('officers')->andReturn([]);
        // DDL tidak dieksekusi; cukup dibuktikan cabang penambahan FK dijalankan.
        Schema::shouldReceive('table')->once();

        $this->guard()->up();

        $this->assertTrue(true, 'Cabang penambahan FK dieksekusi saat FK belum ada.');
    }

    public function test_down_does_not_drop_existing_foreign_key(): void
    {
        // Kebijakan keamanan rollback: down() tidak boleh menghapus FK yang mungkin dibuat
        // oleh migrasi sebelumnya (lihat docs/DECISIONS.md §13).
        $this->guard()->down();

        $this->assertNotNull(
            collect(Schema::getForeignKeys('officers'))->first(
                fn (array $fk) => in_array('cooperative_id', $fk['columns'], true)
            ),
            'down() tidak boleh menghapus foreign key yang sudah ada.'
        );
    }

    // ---------------------------------------------------------------------
    // Helper
    // ---------------------------------------------------------------------

    /**
     * Instans implementasi migrasi yang sebenarnya (anonymous class).
     */
    private function guard(): object
    {
        return require database_path(self::FK_MIGRATION);
    }

    /**
     * Metadata FK yang sesuai rancangan (bentuk kembalian Schema::getForeignKeys()).
     *
     * @return array<string, mixed>
     */
    private function expectedForeignKey(): array
    {
        return [
            'name' => 'officers_cooperative_id_foreign',
            'columns' => ['cooperative_id'],
            'foreign_schema' => null,
            'foreign_table' => 'cooperatives',
            'foreign_columns' => ['id'],
            'on_update' => 'restrict',
            'on_delete' => 'restrict',
        ];
    }
}
