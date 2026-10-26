<?php

namespace Tests\Unit;

use App\Models\Cooperative;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Pembentukan slug koperasi & daftar segmen route tercadang (Tahap 88 — Opsi 1 + 1b).
 *
 * Menegakkan:
 * - slug normal tidak berubah perilakunya;
 * - nama yang menghasilkan segmen internal (`Profil`, `Anggota`, …) memperoleh alternatif
 *   `{base}-{n}` yang valid dan unik (segmen tercadang tidak pernah dipakai);
 * - keunikan tetap dijaga lewat pemeriksaan `slug` (termasuk data ter-soft-delete);
 * - pola constraint route publik hanya menolak segmen tercadang **yang sama persis**.
 *
 * Catatan bukti: suite berjalan pada **SQLite `:memory:`** — bukan bukti kompatibilitas MariaDB.
 */
class CooperativeSlugTest extends TestCase
{
    use RefreshDatabase;

    public function test_normal_name_gets_a_plain_slug(): void
    {
        $this->assertSame(
            'koperasi-sejahtera-bersama',
            Cooperative::generateUniqueSlug('Koperasi Sejahtera Bersama'),
        );
    }

    public function test_name_producing_a_reserved_segment_gets_a_valid_alternative(): void
    {
        foreach (['Profil', 'Anggota'] as $nama) {
            $slug = Cooperative::generateUniqueSlug($nama);

            $this->assertSame(Str::slug($nama).'-2', $slug);
            $this->assertFalse(Cooperative::isReservedSlug($slug), "Slug alternatif {$slug} tidak boleh tercadang.");
        }
    }

    public function test_capitalisation_and_separator_variants_are_detected_as_reserved(): void
    {
        $variants = ['PROFIL', 'Profil', 'profil', 'TATA KELOLA', 'Tata_Kelola', 'tata--kelola', 'Notifikasi'];

        foreach ($variants as $nama) {
            $this->assertTrue(Cooperative::isReservedSlug($nama), "Varian '{$nama}' harus terdeteksi tercadang.");
            $this->assertFalse(
                Cooperative::isReservedSlug(Cooperative::generateUniqueSlug($nama)),
                "Alternatif untuk '{$nama}' tidak boleh tercadang.",
            );
        }
    }

    public function test_hyphenated_name_keeps_its_shape_and_is_not_reserved(): void
    {
        $this->assertSame('koperasi-anggota-sejahtera', Cooperative::generateUniqueSlug('Koperasi Anggota Sejahtera'));
        $this->assertSame('tata-kelola', Str::slug('Tata - Kelola'));
        $this->assertFalse(Cooperative::isReservedSlug('anggota-sejahtera'));
        $this->assertFalse(Cooperative::isReservedSlug('profil-koperasi'));
        $this->assertFalse(Cooperative::isReservedSlug('profiled'));
    }

    public function test_taken_normal_base_uses_the_next_numeric_suffix(): void
    {
        Cooperative::factory()->create(['nama' => 'Koperasi Makmur', 'slug' => 'koperasi-makmur']);

        $this->assertSame('koperasi-makmur-2', Cooperative::generateUniqueSlug('Koperasi Makmur'));
    }

    public function test_reserved_base_with_taken_suffix_candidate_stays_unique(): void
    {
        Cooperative::factory()->create(['nama' => 'Koperasi Lain', 'slug' => 'profil-2']);

        $this->assertSame('profil-3', Cooperative::generateUniqueSlug('Profil'));
    }

    public function test_slug_of_soft_deleted_cooperative_is_still_reserved_for_uniqueness(): void
    {
        $archived = Cooperative::factory()->create(['nama' => 'Profil', 'slug' => null]);
        $this->assertSame('profil-2', $archived->slug);

        $archived->delete();

        $this->assertSame('profil-3', Cooperative::generateUniqueSlug('Profil'));
    }

    public function test_empty_name_falls_back_to_the_project_safe_slug(): void
    {
        $this->assertSame('koperasi', Cooperative::generateUniqueSlug(''));
        $this->assertSame('koperasi', Cooperative::generateUniqueSlug('   '));
        $this->assertSame('koperasi', Cooperative::generateUniqueSlug('!!!'));
        $this->assertFalse(Cooperative::isReservedSlug('koperasi'));

        Cooperative::factory()->create(['nama' => 'Koperasi Pertama', 'slug' => 'koperasi']);

        $this->assertSame('koperasi-2', Cooperative::generateUniqueSlug(''));
    }

    public function test_model_creation_uses_the_reserved_aware_generator(): void
    {
        $cooperative = Cooperative::factory()->create(['nama' => 'Profil', 'slug' => null]);

        $this->assertSame('profil-2', $cooperative->slug);
        $this->assertSame('profil-2', $cooperative->fresh()->slug);
    }

    public function test_public_slug_pattern_rejects_only_the_exact_reserved_segments(): void
    {
        $pattern = Cooperative::publicSlugPattern();

        foreach (Cooperative::RESERVED_SLUGS as $reserved) {
            $this->assertSame(
                0,
                preg_match('#^'.$pattern.'$#', $reserved),
                "Slug tercadang '{$reserved}' tidak boleh diterima route publik.",
            );
        }

        foreach (['koperasi-makmur', 'anggota-sejahtera', 'dashboard-koperasi', 'profiled', 'profil-koperasi', 'profil-2'] as $allowed) {
            $this->assertSame(
                1,
                preg_match('#^'.$pattern.'$#', $allowed),
                "Slug wajar '{$allowed}' harus tetap diterima route publik.",
            );
        }
    }
}
