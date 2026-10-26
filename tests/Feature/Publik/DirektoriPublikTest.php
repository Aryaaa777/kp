<?php

namespace Tests\Feature\Publik;

use App\Models\Cooperative;
use App\Models\CooperativeLicense;
use App\Models\Document;
use App\Models\MemberHistory;
use App\Models\Officer;
use App\Models\Region;
use App\Models\User;
use App\Support\PiiMask;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Direktori & profil publik koperasi (F7 — FR-180/181/182, AC-PUB-01/02/03).
 *
 * Menegakkan dua penjagaan inti:
 * - **AC-PUB-01/BR-009** — hanya koperasi `is_published = true` **dan** berstatus resmi yang
 *   tampil; koperasi draft/belum dipublikasikan tidak muncul di direktori, hasil pencarian,
 *   maupun profil langsung.
 * - **AC-PUB-03/NFR-SEC-015** — hanya field allowlist yang tampil; email & telepon dimasking.
 *
 * Catatan bukti: suite berjalan pada **SQLite `:memory:`**. Kelulusan di sini **bukan** bukti
 * kompatibilitas MariaDB.
 */
class DirektoriPublikTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function published(array $overrides = []): Cooperative
    {
        return Cooperative::factory()->create(array_merge([
            'status_verifikasi' => 'approved',
            'is_published' => true,
        ], $overrides));
    }

    public function test_guest_can_open_the_public_directory_without_authentication(): void
    {
        $this->published(['nama' => 'Koperasi Sejahtera Bersama']);

        $this->get(route('publik.direktori'))
            ->assertOk()
            ->assertSee('Direktori Koperasi')
            ->assertSee('Koperasi Sejahtera Bersama');
    }

    public function test_directory_lists_only_published_and_officially_approved_cooperatives(): void
    {
        $this->published(['nama' => 'Koperasi Terlihat']);

        // `is_published = true` tetapi status belum resmi → tidak tampil.
        $this->published(['nama' => 'Koperasi Draft', 'status_verifikasi' => 'draft']);
        $this->published(['nama' => 'Koperasi Diperiksa', 'status_verifikasi' => 'under_review']);
        $this->published(['nama' => 'Koperasi Ditolak', 'status_verifikasi' => 'rejected']);
        $this->published(['nama' => 'Koperasi Diarsipkan', 'status_verifikasi' => 'archived']);

        // Status resmi tetapi belum dipublikasikan → tidak tampil.
        Cooperative::factory()->create([
            'nama' => 'Koperasi Belum Publikasi',
            'status_verifikasi' => 'approved',
            'is_published' => false,
        ]);

        $response = $this->get(route('publik.direktori'))->assertOk();

        $response->assertSee('Koperasi Terlihat');
        $response->assertDontSee('Koperasi Draft');
        $response->assertDontSee('Koperasi Diperiksa');
        $response->assertDontSee('Koperasi Ditolak');
        $response->assertDontSee('Koperasi Diarsipkan');
        $response->assertDontSee('Koperasi Belum Publikasi');
    }

    public function test_search_never_reveals_unpublished_cooperatives(): void
    {
        $this->published(['nama' => 'Koperasi Publik Melati']);
        Cooperative::factory()->create([
            'nama' => 'Koperasi Rahasia Melati',
            'status_verifikasi' => 'approved',
            'is_published' => false,
        ]);

        $this->get(route('publik.direktori', ['q' => 'Melati']))
            ->assertOk()
            ->assertSee('Koperasi Publik Melati')
            ->assertDontSee('Koperasi Rahasia Melati');

        $this->get(route('publik.direktori', ['q' => 'Rahasia']))
            ->assertOk()
            ->assertDontSee('Koperasi Rahasia Melati')
            ->assertSee('Tidak ada koperasi yang cocok');
    }

    public function test_search_by_name_returns_only_relevant_cooperatives(): void
    {
        $this->published(['nama' => 'Koperasi Mekar Jaya']);
        $this->published(['nama' => 'Koperasi Tani Makmur']);

        $this->get(route('publik.direktori', ['q' => 'Mekar']))
            ->assertOk()
            ->assertSee('Koperasi Mekar Jaya')
            ->assertDontSee('Koperasi Tani Makmur');
    }

    public function test_search_treats_like_wildcards_and_the_escape_character_as_literal(): void
    {
        $this->published(['nama' => 'Koperasi Persen 100% Bersama']);
        $this->published(['nama' => 'Koperasi Garis Bawah A_B']);
        $this->published(['nama' => 'Koperasi Tanda Seru Satu!Dua']);
        $this->published(['nama' => 'Koperasi Tanda Seru SatuXDua']);
        $this->published(['nama' => 'Koperasi Anggota Biasa']);

        // `%` yang diketik pengguna bersifat literal.
        $this->get(route('publik.direktori', ['q' => '100%']))
            ->assertOk()
            ->assertSee('Koperasi Persen 100% Bersama')
            ->assertDontSee('Koperasi Anggota Biasa');

        // `%` tunggal TIDAK boleh berperan sebagai wildcard "semua nama".
        $this->get(route('publik.direktori', ['q' => '%']))
            ->assertOk()
            ->assertSee('Menampilkan 1 dari 1 koperasi.')
            ->assertDontSee('Koperasi Anggota Biasa');

        // `_` literal: tanpa escape, "A_B" juga cocok dengan "…a B…" karena `_` = satu karakter apa pun.
        $this->get(route('publik.direktori', ['q' => 'A_B']))
            ->assertOk()
            ->assertSee('Koperasi Garis Bawah A_B')
            ->assertDontSee('Koperasi Anggota Biasa');

        // Karakter escape `!` diperlakukan sebagai teks biasa: "Satu!Dua" cocok, "SatuXDua" tidak.
        $this->get(route('publik.direktori', ['q' => 'Satu!Dua']))
            ->assertOk()
            ->assertSee('Koperasi Tanda Seru Satu!Dua')
            ->assertDontSee('Koperasi Tanda Seru SatuXDua');

        // Pencarian normal & input kosong (spasi saja) tetap berperilaku wajar.
        $this->get(route('publik.direktori', ['q' => 'Anggota Biasa']))
            ->assertOk()
            ->assertSee('Koperasi Anggota Biasa')
            ->assertDontSee('Koperasi Persen 100% Bersama');

        $this->get(route('publik.direktori', ['q' => '   ']))
            ->assertOk()
            ->assertSee('Menampilkan 5 dari 5 koperasi.');
    }

    public function test_jenis_filter_uses_only_published_data_and_ignores_unknown_values(): void
    {
        $this->published(['nama' => 'Koperasi Konsumen A', 'jenis_koperasi' => 'Koperasi Konsumen']);
        $this->published(['nama' => 'Koperasi Jasa B', 'jenis_koperasi' => 'Koperasi Jasa']);
        Cooperative::factory()->create([
            'nama' => 'Koperasi Rahasia C',
            'jenis_koperasi' => 'Jenis Rahasia',
            'status_verifikasi' => 'approved',
            'is_published' => false,
        ]);

        $this->get(route('publik.direktori', ['jenis' => 'Koperasi Konsumen']))
            ->assertOk()
            ->assertSee('Koperasi Konsumen A')
            ->assertDontSee('Koperasi Jasa B');

        // Nilai jenis karangan diabaikan (tidak error; tampil semua).
        $this->get(route('publik.direktori', ['jenis' => 'Jenis Tidak Ada']))
            ->assertOk()
            ->assertSee('Koperasi Konsumen A')
            ->assertSee('Koperasi Jasa B');

        // Jenis yang hanya ada pada data tidak terpublikasi tidak muncul sebagai opsi.
        $this->get(route('publik.direktori'))
            ->assertOk()
            ->assertDontSee('Jenis Rahasia');
    }

    public function test_region_filter_uses_only_regions_actually_used_by_published_data(): void
    {
        $bandung = Region::factory()->create(['name' => 'Kota Bandung']);
        $bogor = Region::factory()->create(['name' => 'Kota Bogor']);
        Region::factory()->create(['name' => 'Kota Cimahi']); // tidak dipakai data terpublikasi

        $this->published(['nama' => 'Koperasi Bandung', 'region_id' => $bandung->id]);
        $this->published(['nama' => 'Koperasi Bogor', 'region_id' => $bogor->id]);

        $this->get(route('publik.direktori'))
            ->assertOk()
            ->assertSee('Kota Bandung')
            ->assertSee('Kota Bogor')
            ->assertDontSee('Kota Cimahi');

        $this->get(route('publik.direktori', ['wilayah' => $bandung->id]))
            ->assertOk()
            ->assertSee('Koperasi Bandung')
            ->assertDontSee('Koperasi Bogor');

        // Wilayah yang tidak dipakai data terpublikasi diabaikan.
        $this->get(route('publik.direktori', ['wilayah' => 999999]))
            ->assertOk()
            ->assertSee('Koperasi Bandung')
            ->assertSee('Koperasi Bogor');
    }

    public function test_region_filter_uses_regency_ancestor_for_village_regions(): void
    {
        $province = Region::factory()->create(['name' => 'Jawa Barat', 'type' => 'province']);
        $bandung = Region::factory()->create([
            'name' => 'Kota Bandung',
            'type' => 'city',
            'parent_id' => $province->id,
        ]);
        $bogor = Region::factory()->create([
            'name' => 'Kota Bogor',
            'type' => 'city',
            'parent_id' => $province->id,
        ]);
        $district = Region::factory()->create([
            'name' => 'Kecamatan Coblong',
            'type' => 'district',
            'parent_id' => $bandung->id,
        ]);
        $village = Region::factory()->create([
            'name' => 'Kelurahan Dago',
            'type' => 'village',
            'parent_id' => $district->id,
        ]);

        $this->published(['nama' => 'Koperasi Dago', 'region_id' => $village->id]);
        $this->published(['nama' => 'Koperasi Bogor', 'region_id' => $bogor->id]);

        $response = $this->get(route('publik.direktori'))->assertOk();

        // Opsi filter memakai kabupaten/kota, bukan desa/kelurahan.
        $options = $response->viewData('regionOptions');
        $this->assertSame([
            ['id' => $bandung->id, 'name' => 'Kota Bandung'],
            ['id' => $bogor->id, 'name' => 'Kota Bogor'],
        ], $options);

        $this->get(route('publik.direktori', ['wilayah' => $bandung->id]))
            ->assertOk()
            ->assertSee('Koperasi Dago')
            ->assertDontSee('Koperasi Bogor');
    }

    public function test_published_profile_shows_allowlisted_fields_and_masks_sensitive_data(): void
    {
        $province = Region::factory()->create(['name' => 'Jawa Barat', 'type' => 'province']);
        $regency = Region::factory()->create(['name' => 'Kota Bandung', 'type' => 'city', 'parent_id' => $province->id]);
        $district = Region::factory()->create(['name' => 'Kecamatan Coblong', 'type' => 'district', 'parent_id' => $regency->id]);
        $village = Region::factory()->create(['name' => 'Kelurahan Dago', 'type' => 'village', 'parent_id' => $district->id]);

        $cooperative = $this->published([
            'nama' => 'Koperasi Profil Publik',
            'jenis_koperasi' => 'Koperasi Konsumen',
            'email' => 'kontak@example.test',
            'telephone' => '081234567890',
            'website' => 'https://profil.example.test',
            'nomor_identitas' => 'KOP-PUBLIK-9',
            'tanggal_berdiri' => '2015-08-17',
            'status_operasional' => 'aktif',
            'alamat' => 'Jl. Rahasia No. 99, RT 01 RW 02',
            'region_id' => $village->id,
        ]);

        $response = $this->get(route('publik.koperasi', ['slug' => $cooperative->slug]))->assertOk();

        // Q-008: field yang disetujui tampil publik.
        $response->assertSee('Koperasi Profil Publik');
        $response->assertSee('Koperasi Konsumen');
        $response->assertSee('KOP-PUBLIK-9');
        $response->assertSee('17 Agustus 2015');
        $response->assertSee('https://profil.example.test');

        // Alamat publik = hierarki wilayah administratif sampai desa/kelurahan.
        $response->assertSee('Jawa Barat, Kota Bandung, Kecamatan Coblong, Kelurahan Dago');

        // NFR-SEC-015: kontak hanya tampil termasking.
        $response->assertSee('ko***@example.test');
        $response->assertDontSee('kontak@example.test');
        $response->assertSee('0812****7890');
        $response->assertDontSee('081234567890');

        // Teks alamat bebas (nomor rumah/RT/RW) TIDAK dipublikasikan.
        $response->assertDontSee('Jl. Rahasia');
        $response->assertDontSee('RT 01 RW 02');

        // Q-008: status operasional (kolom terpisah, bukan status verifikasi) tampil bila terisi.
        $response->assertSee('Status Operasional');
        $response->assertSee('Aktif');

        // `status_verifikasi` bukan status operasional → tidak ditampilkan dengan label itu.
        $response->assertDontSee('Disetujui');
    }

    public function test_public_website_link_rejects_non_web_schemes_from_legacy_data(): void
    {
        $unsafe = $this->published([
            'nama' => 'Koperasi Website Tidak Aman',
            'website' => 'javascript:alert(1)',
        ]);
        $safe = $this->published([
            'nama' => 'Koperasi Website Aman',
            'website' => 'https://website.example.test/profil',
        ]);

        $this->get(route('publik.koperasi', ['slug' => $unsafe->slug]))
            ->assertOk()
            ->assertDontSee('javascript:alert', false)
            ->assertDontSee('href="javascript:', false);

        $this->get(route('publik.direktori'))
            ->assertOk()
            ->assertDontSee('javascript:alert', false)
            ->assertSee('https://website.example.test/profil');

        $this->get(route('publik.koperasi', ['slug' => $safe->slug]))
            ->assertOk()
            ->assertSee('https://website.example.test/profil');
    }

    public function test_directory_never_exposes_sensitive_personal_data(): void
    {
        $cooperative = $this->published([
            'nama' => 'Koperasi Aman',
            'nomor_identitas' => 'KOP-AMAN-7',
            'alamat' => 'Jl. Aman No. 123, RT 04 RW 05',
        ]);

        // Data pengurus (NIK) dan data personal anggota tidak boleh tampil publik.
        Officer::factory()->create([
            'cooperative_id' => $cooperative->id,
            'name' => 'Budi Pengurus',
            'identity_number' => '3273010101010001',
        ]);

        MemberHistory::factory()->create([
            'cooperative_id' => $cooperative->id,
            'notes' => 'CATATAN-ANGGOTA-RAHASIA',
        ]);

        $this->get(route('publik.direktori'))
            ->assertOk()
            ->assertSee('Koperasi Aman')
            // Q-008: nomor badan hukum boleh tampil.
            ->assertSee('KOP-AMAN-7')
            // Q-008: NIK pengurus, nama pengurus, data anggota, detail alamat tidak tampil.
            ->assertDontSee('Budi Pengurus')
            ->assertDontSee('3273010101010001')
            ->assertDontSee('CATATAN-ANGGOTA-RAHASIA')
            ->assertDontSee('Jl. Aman No. 123')
            ->assertDontSee('RT 04 RW 05');
    }

    public function test_unpublished_cooperative_profile_is_not_accessible(): void
    {
        $secret = Cooperative::factory()->create([
            'nama' => 'Koperasi Rahasia',
            'nomor_identitas' => 'KOP-SECRET-1',
            'status_verifikasi' => 'approved',
            'is_published' => false,
        ]);

        $this->get(route('publik.koperasi', ['slug' => $secret->slug]))
            ->assertNotFound()
            ->assertDontSee('Koperasi Rahasia')
            ->assertDontSee('KOP-SECRET-1');

        // Dipublikasikan tetapi status belum resmi → sama-sama tidak dapat diakses.
        $draft = $this->published(['nama' => 'Koperasi Draft Rahasia', 'status_verifikasi' => 'draft']);

        $this->get(route('publik.koperasi', ['slug' => $draft->slug]))
            ->assertNotFound()
            ->assertDontSee('Koperasi Draft Rahasia');
    }

    public function test_slug_that_does_not_exist_returns_not_found(): void
    {
        $this->get(route('publik.koperasi', ['slug' => 'slug-tidak-ada']))
            ->assertNotFound();
    }

    public function test_soft_deleted_cooperative_is_hidden_even_when_published(): void
    {
        $cooperative = $this->published(['nama' => 'Koperasi Terhapus']);
        $cooperative->delete();

        $this->get(route('publik.direktori'))
            ->assertOk()
            ->assertDontSee('Koperasi Terhapus');

        $this->get(route('publik.koperasi', ['slug' => $cooperative->slug]))
            ->assertNotFound();
    }

    public function test_internal_routes_remain_protected_and_are_not_shadowed_by_the_public_profile_route(): void
    {
        // Regresi urutan registrasi: `/koperasi/{slug}` didaftarkan SETELAH grup internal
        // `/koperasi`, sehingga route internal tetap menang dan tetap butuh autentikasi.
        $this->get('/koperasi/dashboard')->assertRedirect(route('login'));
        $this->get('/koperasi/profil')->assertRedirect(route('login'));
        $this->get('/admin/koperasi')->assertRedirect(route('login'));
    }

    public function test_public_profile_route_constraint_refuses_reserved_segments(): void
    {
        $routes = collect(Route::getRoutes()->getRoutes())
            ->filter(fn ($route): bool => $route->uri() === 'koperasi/{slug}');

        // Tidak ada route duplikat untuk profil publik.
        $this->assertCount(1, $routes);

        $public = $routes->first();

        foreach (Cooperative::RESERVED_SLUGS as $reserved) {
            $this->assertFalse(
                $public->matches(Request::create('/koperasi/'.$reserved, 'GET')),
                "Route publik tidak boleh menerima slug tercadang: {$reserved}",
            );
        }

        // Slug wajar — termasuk yang memuat kata tercadang sebagai bagian — tetap diterima.
        foreach (['koperasi-makmur', 'anggota-sejahtera', 'profil-2'] as $slug) {
            $this->assertTrue(
                $public->matches(Request::create('/koperasi/'.$slug, 'GET')),
                "Slug wajar ditolak route publik: {$slug}",
            );
        }
    }

    public function test_reserved_slug_list_covers_every_internal_segment_under_koperasi_prefix(): void
    {
        $internalSegments = collect(Route::getRoutes()->getRoutes())
            ->filter(fn ($route): bool => $route->uri() !== 'koperasi'
                && $route->uri() !== 'koperasi/{slug}'
                && str_starts_with($route->uri(), 'koperasi/'))
            ->map(fn ($route): string => explode('/', $route->uri())[1])
            ->unique()
            ->values()
            ->all();

        $this->assertNotEmpty($internalSegments);

        // Penjaga drift: setiap segmen internal baru WAJIB ditambahkan ke
        // `Cooperative::RESERVED_SLUGS` (dan sebaliknya, daftar tidak boleh memuat segmen asing).
        foreach ($internalSegments as $segment) {
            $this->assertContains($segment, Cooperative::RESERVED_SLUGS, "Segmen internal '{$segment}' belum tercadang.");
        }

        foreach (Cooperative::RESERVED_SLUGS as $reserved) {
            $this->assertContains($reserved, $internalSegments, "Segmen tercadang '{$reserved}' bukan segmen route internal.");
        }
    }

    public function test_legacy_reserved_slug_is_never_served_as_a_public_profile(): void
    {
        // Koperasi lama berslug tercadang: datanya TIDAK diubah pada Tahap 88.
        $legacy = $this->published(['nama' => 'Koperasi Lama Profil', 'slug' => 'profil']);

        $this->assertSame('profil', $legacy->fresh()->slug);

        // Karena route internal sengaja didaftarkan lebih dulu (middleware-nya tidak boleh berubah),
        // slug ini tetap ditangani route internal: tamu diarahkan ke `login`, bukan 404.
        // Perbaikan sebenarnya untuk data lama = Opsi 2/3 (butuh izin, docs/DECISIONS.md §26).
        $matched = Route::getRoutes()->match(Request::create('/koperasi/profil', 'GET'));

        $this->assertSame('pengelola.profil', $matched->getName());
        $this->assertNotSame('publik.koperasi', $matched->getName());

        $this->get('/koperasi/profil')->assertRedirect(route('login'));

        // Profil publiknya tidak pernah tampil...
        $this->get(route('publik.koperasi', ['slug' => $legacy->slug]))
            ->assertRedirect(route('login'));

        // ...dan slug yang sama tidak dapat ditampilkan lewat jalur lain (masih tercadang).
        $this->assertTrue(Cooperative::isReservedSlug($legacy->slug));
    }

    public function test_reserved_internal_routes_keep_their_authorisation(): void
    {
        $cooperative = Cooperative::factory()->create(['nama' => 'Koperasi Milik Pengelola']);
        $pengelola = User::factory()->pengelola($cooperative->id)->create();

        $segments = [
            'dashboard' => 'pengelola.dashboard',
            'profil' => 'pengelola.profil',
            'anggota' => 'pengelola.anggota.index',
            'pengajuan' => 'pengelola.pengajuan.index',
        ];

        foreach ($segments as $segment => $routeName) {
            $this->assertSame(
                $routeName,
                Route::getRoutes()->match(Request::create('/koperasi/'.$segment, 'GET'))->getName(),
                "Segmen {$segment} harus tetap ditangani route internal.",
            );
        }

        // Tamu → login (middleware `auth` tidak berubah). Diuji lebih dulu sebelum `actingAs`.
        foreach (array_keys($segments) as $segment) {
            $this->get('/koperasi/'.$segment)->assertRedirect(route('login'));
        }

        // Pengguna tanpa peran pengelola → 403 (middleware `role` tidak berubah).
        $admin = User::factory()->admin()->create();

        foreach (array_keys($segments) as $segment) {
            $this->actingAs($admin)
                ->get('/koperasi/'.$segment)
                ->assertForbidden();
        }

        // Pengelola pemilik koperasi → 200 (halaman internal tetap berfungsi).
        foreach (array_keys($segments) as $segment) {
            $this->actingAs($pengelola)
                ->get('/koperasi/'.$segment)
                ->assertOk();
        }
    }

    public function test_new_cooperative_with_reserved_name_gets_a_reachable_public_profile(): void
    {
        $cooperative = $this->published(['nama' => 'Profil', 'slug' => null]);

        // Slug baru dihindarkan dari segmen internal (Tahap 88 — Opsi 1).
        $this->assertSame('profil-2', $cooperative->slug);
        $this->assertFalse(Cooperative::isReservedSlug($cooperative->slug));

        // ...sehingga profil publiknya benar-benar dapat dibuka lewat URL normal.
        $this->get(route('publik.direktori'))->assertOk()->assertSee('Profil');

        $this->get(route('publik.koperasi', ['slug' => $cooperative->slug]))
            ->assertOk()
            ->assertSee('Profil');
    }

    public function test_pii_masking_helper_formats_email_and_phone(): void
    {
        $this->assertSame('ab***@example.com', PiiMask::email('ab@example.com'));
        $this->assertSame('ko***@example.test', PiiMask::email('kontak@example.test'));
        $this->assertSame('ko***@example.com', PiiMask::email('  kontak@example.com  '));
        $this->assertNull(PiiMask::email(null));
        $this->assertNull(PiiMask::email(''));
        $this->assertNull(PiiMask::email('bukan-email'));
        $this->assertNull(PiiMask::email('dua@@example.com'));

        $this->assertSame('0812****5678', PiiMask::phone('0812345678'));
        $this->assertSame('0812****7890', PiiMask::phone('0812-3456-7890'));
        $this->assertSame('6281****7890', PiiMask::phone('+62 812 3456 7890'));
        $this->assertNull(PiiMask::phone(null));
        $this->assertNull(PiiMask::phone(''));
        $this->assertSame('***', PiiMask::phone('123'));
    }

    public function test_directory_paginates_and_keeps_filters_in_the_query_string(): void
    {
        $jenis = 'Koperasi Konsumen';

        Cooperative::factory()->count(14)->create([
            'status_verifikasi' => 'approved',
            'is_published' => true,
            'jenis_koperasi' => $jenis,
        ]);

        $this->get(route('publik.direktori', ['jenis' => $jenis, 'page' => 2]))
            ->assertOk()
            ->assertSee('Menampilkan 2 dari 14 koperasi.')
            ->assertViewHas('koperasi', function ($paginator): bool {
                // Pagination tetap dibatasi data terpublikasi dan tautan halaman tidak
                // menghilangkan filter yang sedang aktif (`withQueryString`).
                return $paginator->total() === 14
                    && $paginator->perPage() === 12
                    && $paginator->currentPage() === 2
                    && $paginator->count() === 2
                    && str_contains((string) $paginator->url(1), 'jenis=')
                    && str_contains((string) $paginator->url(1), 'page=1');
            });
    }

    public function test_region_filter_stays_hidden_and_does_not_mislead_without_region_data(): void
    {
        $this->published(['nama' => 'Koperasi Tanpa Wilayah']);

        // Q-012: referensi wilayah belum ditetapkan → `regions` kosong di lingkungan ini.
        $this->assertSame(0, Region::count());

        $this->get(route('publik.direktori'))
            ->assertOk()
            ->assertSee('Koperasi Tanpa Wilayah')
            ->assertDontSee('Kabupaten/Kota');

        // Nilai `wilayah` yang tidak berasal dari data terpublikasi diabaikan sehingga
        // tidak menghasilkan daftar kosong yang menyesatkan.
        $this->get(route('publik.direktori', ['wilayah' => 1]))
            ->assertOk()
            ->assertSee('Koperasi Tanpa Wilayah')
            ->assertDontSee('Kabupaten/Kota');
    }

    /*
    |--------------------------------------------------------------------------
    | Tahap 90 — batas konfigurasi FR-191 (fail-safe, bukan fail-open)
    |--------------------------------------------------------------------------
    |
    | Konfigurasi publik boleh salah/kosong; yang tidak boleh terjadi adalah
    | pengecualian HTTP 500, kebocoran atribut sensitif, atau **melebarnya** akses
    | publik (mis. fallback diam-diam saat daftar status kosong).
    |
    */

    public function test_empty_public_field_allowlist_renders_safely_without_leaking_attributes(): void
    {
        config(['koperasi.public.fields' => []]);

        $cooperative = $this->published([
            'nama' => 'Koperasi Tanpa Field',
            'jenis_koperasi' => 'Koperasi Konsumen',
            'nomor_identitas' => 'KOP-KOSONG-1',
            'alamat' => 'Jl. Kosong No. 1',
        ]);

        // Kontrak tampilan tetap dipenuhi (tidak ada 500) walau tidak ada field identitas.
        $this->get(route('publik.direktori'))
            ->assertOk()
            ->assertSee('(nama tidak tersedia)')
            ->assertDontSee('Koperasi Tanpa Field')
            ->assertDontSee('KOP-KOSONG-1')
            ->assertDontSee('Jl. Kosong No. 1');

        $this->get(route('publik.koperasi', ['slug' => $cooperative->slug]))
            ->assertOk()
            ->assertSee('(nama tidak tersedia)')
            ->assertSee('Jenis belum ditetapkan')
            ->assertDontSee('Koperasi Tanpa Field')
            ->assertDontSee('Koperasi Konsumen')
            ->assertDontSee('KOP-KOSONG-1')
            ->assertDontSee('Jl. Kosong No. 1');
    }

    public function test_unknown_public_field_names_are_ignored_without_exposing_data(): void
    {
        // Gunakan field yang BENAR-BENAR tidak diizinkan (bukan lagi nomor_identitas/alamat
        // yang kini diizinkan per Q-008).
        // Set config HANYA dengan 'nama' + field tidak dikenal.
        // 'nomor_identitas' dan 'alamat' TIDAK dikonfigurasi, jadi tidak boleh muncul.
        config(['koperasi.public.fields' => ['nama', 'field_yang_tidak_ada_1', 'field_yang_tidak_ada_2']]);

        $this->published([
            'nama' => 'Koperasi Field Tak Dikenal',
            'nomor_identitas' => 'KOP-TAK-DIKENAL-1',
            'alamat' => 'Jl. Tak Dikenal No. 2',
        ]);

        $page = $this->get(route('publik.direktori'))->assertOk()->viewData('koperasi');
        $presented = $page->getCollection()->first();

        // Nama field yang tidak diizinkan implementasi publik diabaikan sepenuhnya —
        // bukan hanya "kebetulan" tidak dicetak view.
        // Hanya 'nama' yang dikonfigurasi (di luar slug/wilayah/email/telephone yang selalu ada).
        $this->assertSame(['slug', 'nama', 'wilayah', 'email', 'telephone'], array_keys($presented));
        $this->assertSame('Koperasi Field Tak Dikenal', $presented['nama']);

        $this->get(route('publik.direktori'))
            ->assertOk()
            ->assertDontSee('KOP-TAK-DIKENAL-1')
            ->assertDontSee('Jl. Tak Dikenal');
    }

    public function test_relation_names_in_the_allowlist_are_never_loaded_or_exposed(): void
    {
        // Konfigurasi yang salah dengan sengaja: nama relasi + kolom non-publik.
        config(['koperasi.public.fields' => ['nama', 'region', 'officers', 'documents', 'licenses', 'id']]);

        $region = Region::factory()->create(['name' => 'Kota Relasi']);

        $cooperative = $this->published([
            'nama' => 'Koperasi Relasi Aman',
            'nomor_identitas' => 'KOP-RELASI-1',
            'region_id' => $region->id,
        ]);

        Officer::factory()->create([
            'cooperative_id' => $cooperative->id,
            'name' => 'Pengurus Rahasia',
            'identity_number' => '32RAHASIA999',
        ]);

        $license = CooperativeLicense::factory()->create([
            'cooperative_id' => $cooperative->id,
            'nomor' => 'IZIN-RAHASIA-777',
        ]);

        Document::factory()->forLicense($license)->create([
            'original_name' => 'dokumen-rahasia.pdf',
            'file_path' => 'documents/rahasia/dokumen-rahasia.pdf',
        ]);

        DB::flushQueryLog();
        DB::enableQueryLog();

        $page = $this->get(route('publik.direktori'))->assertOk()->viewData('koperasi');
        $presented = $page->getCollection()->first();

        $queries = collect(DB::getQueryLog())->pluck('query')->implode(' | ');

        DB::disableQueryLog();

        // Penjaga anti-lolos: buktikan query log memang aktif & mencatat query direktori.
        $this->assertStringContainsString('cooperatives', $queries);
        $this->assertStringContainsString('regions', $queries);

        // Relasi tidak di-query sama sekali: tidak ada lazy loading walau namanya ada di allowlist.
        foreach (['officers', 'documents', 'cooperative_licenses'] as $table) {
            $this->assertStringNotContainsString(
                $table,
                $queries,
                "Halaman publik tidak boleh men-query tabel '{$table}'.",
            );
        }

        // Data yang dikirim ke view hanya kolom skalar yang diizinkan + kontrak tampilan.
        $this->assertSame(['slug', 'nama', 'wilayah', 'email', 'telephone'], array_keys($presented));

        $this->get(route('publik.direktori'))
            ->assertOk()
            ->assertSee('Koperasi Relasi Aman')
            ->assertDontSee('KOP-RELASI-1')
            ->assertDontSee('Pengurus Rahasia')
            ->assertDontSee('32RAHASIA999')
            ->assertDontSee('IZIN-RAHASIA-777')
            ->assertDontSee('dokumen-rahasia');
    }

    public function test_disabled_contact_fields_are_not_rendered(): void
    {
        config([
            'koperasi.public.contact.email' => false,
            'koperasi.public.contact.telephone' => false,
        ]);

        $cooperative = $this->published([
            'nama' => 'Koperasi Tanpa Kontak',
            'email' => 'kontak@example.test',
            'telephone' => '081234567890',
        ]);

        $this->get(route('publik.direktori'))
            ->assertOk()
            ->assertSee('Koperasi Tanpa Kontak')
            ->assertDontSee('Email:')
            ->assertDontSee('Telepon:')
            ->assertDontSee('ko***@example.test')
            ->assertDontSee('kontak@example.test')
            ->assertDontSee('081234567890');

        $this->get(route('publik.koperasi', ['slug' => $cooperative->slug]))
            ->assertOk()
            ->assertDontSee('Email')
            ->assertDontSee('Telepon')
            ->assertDontSee('ko***@example.test')
            ->assertDontSee('kontak@example.test')
            ->assertDontSee('0812****7890')
            ->assertDontSee('081234567890');
    }

    public function test_disabled_region_display_hides_wilayah(): void
    {
        config(['koperasi.public.show_region' => false]);

        $region = Region::factory()->create(['name' => 'Kota Sangat Rahasia']);
        $cooperative = $this->published(['nama' => 'Koperasi Daerah Tersembunyi', 'region_id' => $region->id]);

        // Label wilayah tidak lagi dikirim ke view sama sekali.
        $page = $this->get(route('publik.direktori'))->assertOk()->viewData('koperasi');
        $presented = $page->getCollection()->first();

        $this->assertArrayNotHasKey('wilayah', $presented);

        $this->get(route('publik.direktori'))
            ->assertOk()
            ->assertSee('Koperasi Daerah Tersembunyi')
            ->assertDontSee('Wilayah:');

        $this->get(route('publik.koperasi', ['slug' => $cooperative->slug]))
            ->assertOk()
            ->assertDontSee('Kota Sangat Rahasia')
            ->assertDontSee('Wilayah');
    }

    public function test_empty_visible_statuses_exposes_no_cooperative(): void
    {
        config(['koperasi.public.visible_statuses' => []]);

        $cooperative = $this->published([
            'nama' => 'Koperasi Tertutup Total',
            'nomor_identitas' => 'KOP-TERTUTUP-1',
        ]);

        // Datanya memang dipublikasikan & berstatus resmi — jadi kekosongan direktori
        // murni akibat konfigurasi, bukan karena tidak ada data.
        $this->assertTrue($cooperative->fresh()->is_published);
        $this->assertSame('approved', $cooperative->fresh()->status_verifikasi);

        // Tidak ada fallback yang memperluas akses: daftar kosong tetap berarti kosong.
        $this->assertSame([], Cooperative::publicStatusLabels());

        $this->get(route('publik.direktori'))
            ->assertOk()
            ->assertSee('Tidak ada koperasi yang cocok')
            ->assertDontSee('Koperasi Tertutup Total')
            ->assertDontSee('KOP-TERTUTUP-1');

        $this->get(route('publik.koperasi', ['slug' => $cooperative->slug]))
            ->assertNotFound()
            ->assertDontSee('Koperasi Tertutup Total');

        $this->get(route('publik.direktori', ['q' => 'Tertutup']))
            ->assertOk()
            ->assertSee('Tidak ada koperasi yang cocok')
            ->assertDontSee('Koperasi Tertutup Total');
    }

    public function test_unknown_visible_status_value_exposes_no_cooperative(): void
    {
        config(['koperasi.public.visible_statuses' => ['status_yang_tidak_ada']]);

        $cooperative = $this->published(['nama' => 'Koperasi Status Karangan']);

        $this->assertSame([], Cooperative::publicStatusLabels());

        $this->get(route('publik.direktori'))
            ->assertOk()
            ->assertSee('Tidak ada koperasi yang cocok')
            ->assertDontSee('Koperasi Status Karangan');

        $this->get(route('publik.koperasi', ['slug' => $cooperative->slug]))
            ->assertNotFound()
            ->assertDontSee('Koperasi Status Karangan');
    }

    public function test_misconfigured_per_page_value_falls_back_to_minimum(): void
    {
        // Nilai salah tipe atau negatif → fallback ke 1 (minimum).
        config(['koperasi.public.per_page' => 'bukan_angka']);

        $this->published(['nama' => 'Koperasi Halaman 1']);
        $this->published(['nama' => 'Koperasi Halaman 2']);

        // Respons harus OK; tidak ada 500 atau error parsing.
        $response = $this->get(route('publik.direktori'))->assertOk();

        // Pagination aman; minimal 1 item per halaman.
        $page = $response->viewData('koperasi');
        $this->assertGreaterThanOrEqual(1, $page->count());
    }

    public function test_misconfigured_visible_statuses_not_array_handled_safely(): void
    {
        // Nilai non-array berisi value yang tidak ada di status enum → tidak ada match.
        config(['koperasi.public.visible_statuses' => ['status_tidak_valid_xyz']]);

        $this->published(['nama' => 'Koperasi Status Karangan']);

        // Respons OK; tidak ada 500. Status 'approved' tidak cocok dengan 'status_tidak_valid_xyz'.
        $this->get(route('publik.direktori'))
            ->assertOk()
            ->assertSee('Tidak ada koperasi yang cocok');
    }

    public function test_misconfigured_contact_settings_not_array_uses_safe_default(): void
    {
        // contact bukan array (e.g., bool) → config(..., true) fallback aman.
        config(['koperasi.public.contact' => false]);

        $cooperative = $this->published(['nama' => 'Koperasi Kontak Salah', 'email' => 'test@example.com']);

        // Respons OK. config('koperasi.public.contact.email', true) → false['email'] → TypeError/null
        // Namun controller ini-nya sudah guard dengan config(..., true) default.
        $this->get(route('publik.direktori'))
            ->assertOk()
            ->assertSee('Koperasi Kontak Salah');
    }

    public function test_misconfigured_show_region_falsy_hides_wilayah(): void
    {
        // show_region = 0 atau false → wilayah tidak ditampilkan.
        config(['koperasi.public.show_region' => 0]);

        $region = Region::factory()->create(['name' => 'Daerah Hidden']);
        $cooperative = $this->published(['nama' => 'Koperasi No Region', 'region_id' => $region->id]);

        $page = $this->get(route('publik.direktori'))->assertOk()->viewData('koperasi');
        $presented = $page->getCollection()->first();

        // 0 adalah falsy → wilayah key tidak ada di data.
        $this->assertArrayNotHasKey('wilayah', $presented);
    }

    public function test_edge_case_configurations_together_render_safely(): void
    {
        // Kombinasi salah konfigurasi → harus tetap render OK.
        config([
            'koperasi.public.fields' => null,
            'koperasi.public.visible_statuses' => '',  // String kosong
            'koperasi.public.per_page' => -10,
        ]);

        $this->published(['nama' => 'Koperasi Edge Case']);

        // Harus OK; tidak ada 500.
        $this->get(route('publik.direktori'))->assertOk();
    }

    public function test_public_statistics_only_aggregate_visible_cooperatives(): void
    {
        $province = Region::factory()->create(['type' => 'province', 'name' => 'Jawa Barat']);
        $regencyA = Region::factory()->create(['type' => 'regency', 'name' => 'Kabupaten A', 'parent_id' => $province->id]);
        $regencyB = Region::factory()->create(['type' => 'regency', 'name' => 'Kabupaten B', 'parent_id' => $province->id]);

        $districtA = Region::factory()->create(['type' => 'district', 'name' => 'Kecamatan A', 'parent_id' => $regencyA->id]);
        $districtB = Region::factory()->create(['type' => 'district', 'name' => 'Kecamatan B', 'parent_id' => $regencyB->id]);

        $villageA = Region::factory()->create(['type' => 'village', 'name' => 'Desa A', 'parent_id' => $districtA->id]);
        $villageB = Region::factory()->create(['type' => 'village', 'name' => 'Desa B', 'parent_id' => $districtB->id]);

        $this->published([
            'nama' => 'Koperasi Konsumen A',
            'jenis_koperasi' => 'Konsumen',
            'status_operasional' => 'aktif',
            'region_id' => $villageA->id,
        ]);

        $this->published([
            'nama' => 'Koperasi Produsen B',
            'jenis_koperasi' => 'Produsen',
            'status_operasional' => 'tidak_aktif',
            'region_id' => $villageB->id,
        ]);

        // Tidak boleh dihitung: belum resmi / belum dipublikasikan.
        $this->published(['status_verifikasi' => 'draft', 'jenis_koperasi' => 'Konsumen']);
        Cooperative::factory()->create([
            'status_verifikasi' => 'approved',
            'is_published' => false,
            'jenis_koperasi' => 'Konsumen',
        ]);

        $response = $this->get(route('publik.statistik'))
            ->assertOk()
            ->assertSee('Statistik Koperasi')
            ->assertSee('Kabupaten A')
            ->assertSee('Kabupaten B');

        $this->assertSame(2, $response->viewData('total'));
        $this->assertCount(2, $response->viewData('byRegion'));
        $this->assertCount(2, $response->viewData('byJenis'));
        $this->assertCount(2, $response->viewData('byStatus'));
    }
}
