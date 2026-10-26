<?php

namespace Tests\Feature\Admin;

use App\Models\Cooperative;
use App\Models\Region;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * CRUD data induk koperasi oleh admin dinas (FR-040, FR-043, FR-160).
 */
class CooperativeCrudTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->admin()->create();
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'nama' => 'Koperasi Makmur',
            'nomor_identitas' => 'KOP-0001',
            'jenis_koperasi' => 'Koperasi Uji',
            'bentuk_koperasi' => null,
            'tanggal_berdiri' => '2010-05-01',
            'region_id' => null,
            'alamat' => 'Jl. Contoh No. 1',
            'telephone' => '081234567890',
            'email' => 'makmur@example.test',
            'website' => 'https://makmur.example.test',
            'status_verifikasi' => 'draft',
            // Status operasional wajib untuk koperasi baru (Tahap 106).
            'status_operasional' => 'aktif',
        ], $overrides);
    }

    public function test_admin_can_view_index(): void
    {
        $this->actingAs($this->admin())
            ->get(route('admin.koperasi.index'))
            ->assertOk()
            ->assertSee('Data Koperasi');
    }

    public function test_admin_can_create_cooperative(): void
    {
        $admin = $this->admin();

        $response = $this->actingAs($admin)
            ->post(route('admin.koperasi.store'), $this->payload());

        $cooperative = Cooperative::firstOrFail();

        $response->assertRedirect(route('admin.koperasi.show', $cooperative));
        $this->assertSame('Koperasi Makmur', $cooperative->nama);
        $this->assertSame('koperasi-makmur', $cooperative->slug);
        $this->assertSame($admin->id, $cooperative->created_by);
        $this->assertSame($admin->id, $cooperative->updated_by);
    }

    public function test_slug_is_unique_for_same_name(): void
    {
        Cooperative::factory()->create(['nama' => 'Koperasi Sama']);
        Cooperative::factory()->create(['nama' => 'Koperasi Sama']);

        $slugs = Cooperative::pluck('slug');

        $this->assertCount(2, $slugs->unique());
    }

    public function test_store_rejects_duplicate_nomor_identitas(): void
    {
        Cooperative::factory()->create(['nomor_identitas' => 'KOP-0001']);

        $this->actingAs($this->admin())
            ->from(route('admin.koperasi.create'))
            ->post(route('admin.koperasi.store'), $this->payload(['nama' => 'Koperasi Lain']))
            ->assertSessionHasErrors('nomor_identitas');

        $this->assertSame(1, Cooperative::where('nomor_identitas', 'KOP-0001')->count());
    }

    public function test_store_requires_mandatory_fields(): void
    {
        $this->actingAs($this->admin())
            ->from(route('admin.koperasi.create'))
            ->post(route('admin.koperasi.store'), [])
            ->assertSessionHasErrors(['nama', 'nomor_identitas', 'jenis_koperasi', 'status_verifikasi', 'status_operasional']);
    }

    public function test_store_rejects_invalid_formats(): void
    {
        $this->actingAs($this->admin())
            ->from(route('admin.koperasi.create'))
            ->post(route('admin.koperasi.store'), $this->payload([
                'email' => 'bukan-email',
                'website' => 'bukan-url',
                'tanggal_berdiri' => now()->addYear()->format('Y-m-d'),
                'status_verifikasi' => 'tidak-dikenal',
                'status_operasional' => 'tidak-dikenal',
            ]))
            ->assertSessionHasErrors(['email', 'website', 'tanggal_berdiri', 'status_verifikasi', 'status_operasional']);
    }

    public function test_store_rejects_website_with_non_web_scheme(): void
    {
        $admin = $this->admin();

        // Rule `url` bawaan Laravel menerima banyak skema non-web (`data://`, `file://`, `ftp://`)
        // yang tidak layak ditautkan dari halaman publik → skema dibatasi ke http/https (Tahap 87).
        $rejected = [
            'javascript:alert(1)',
            'javascript://contoh.id/%0aalert(1)',
            'data://contoh.id/teks',
            'ftp://contoh.id/berkas',
            'file://contoh.id/berkas',
        ];

        foreach ($rejected as $index => $website) {
            $this->actingAs($admin)
                ->from(route('admin.koperasi.create'))
                ->post(route('admin.koperasi.store'), $this->payload([
                    'nama' => 'Koperasi Skema '.$index,
                    'nomor_identitas' => 'KOP-SKEMA-'.$index,
                    'website' => $website,
                ]))
                ->assertSessionHasErrors('website');
        }

        $this->assertSame(0, Cooperative::query()->count());
    }

    public function test_store_accepts_http_and_https_website(): void
    {
        $admin = $this->admin();

        foreach (['http://contoh.id', 'https://contoh.id/profil'] as $index => $website) {
            $this->actingAs($admin)
                ->post(route('admin.koperasi.store'), $this->payload([
                    'nama' => 'Koperasi Web '.$index,
                    'nomor_identitas' => 'KOP-WEB-'.$index,
                    'website' => $website,
                ]))
                ->assertSessionHasNoErrors();
        }

        $this->assertSame(2, Cooperative::query()->count());
    }

    public function test_update_rejects_website_with_non_web_scheme(): void
    {
        $cooperative = Cooperative::factory()->create([
            'nama' => 'Koperasi Web Lama',
            'nomor_identitas' => 'KOP-WEB-LAMA',
            'website' => 'https://lama.example.test',
        ]);

        $this->actingAs($this->admin())
            ->from(route('admin.koperasi.edit', $cooperative))
            ->put(route('admin.koperasi.update', $cooperative), $this->payload([
                'nama' => 'Koperasi Web Lama',
                'nomor_identitas' => 'KOP-WEB-LAMA',
                'website' => 'data://contoh.id/teks',
            ]))
            ->assertSessionHasErrors('website');

        // Validasi gagal tidak boleh mengubah data yang sudah ada.
        $this->assertSame('https://lama.example.test', $cooperative->fresh()->website);
    }

    public function test_admin_can_update_cooperative(): void
    {
        $cooperative = Cooperative::factory()->create([
            'nama' => 'Nama Lama',
            'nomor_identitas' => 'KOP-9000',
        ]);
        $admin = $this->admin();
        $originalSlug = $cooperative->slug;

        $this->actingAs($admin)
            ->put(route('admin.koperasi.update', $cooperative), $this->payload([
                'nama' => 'Nama Baru',
                'nomor_identitas' => 'KOP-9000', // nomor sendiri tidak dianggap duplikat
            ]))
            ->assertRedirect(route('admin.koperasi.show', $cooperative));

        $cooperative->refresh();

        $this->assertSame('Nama Baru', $cooperative->nama);
        $this->assertSame($originalSlug, $cooperative->slug); // slug stabil, tidak ikut berubah
        $this->assertSame($admin->id, $cooperative->updated_by);
    }

    public function test_update_rejects_other_cooperative_nomor_identitas(): void
    {
        Cooperative::factory()->create(['nomor_identitas' => 'KOP-1111']);
        $cooperative = Cooperative::factory()->create(['nomor_identitas' => 'KOP-2222']);

        $this->actingAs($this->admin())
            ->from(route('admin.koperasi.edit', $cooperative))
            ->put(route('admin.koperasi.update', $cooperative), $this->payload([
                'nomor_identitas' => 'KOP-1111',
            ]))
            ->assertSessionHasErrors('nomor_identitas');
    }

    public function test_admin_can_archive_cooperative_with_reason(): void
    {
        $cooperative = Cooperative::factory()->create();

        $this->actingAs($this->admin())
            ->delete(route('admin.koperasi.destroy', $cooperative), [
                'archived_reason' => 'Sudah tidak aktif',
            ])
            ->assertRedirect(route('admin.koperasi.index'));

        $this->assertSoftDeleted('cooperatives', ['id' => $cooperative->id]);

        $archived = Cooperative::withTrashed()->findOrFail($cooperative->id);

        $this->assertSame('archived', $archived->status_verifikasi);
        $this->assertSame('Sudah tidak aktif', $archived->archived_reason);
    }

    public function test_archive_requires_reason(): void
    {
        $cooperative = Cooperative::factory()->create();

        $this->actingAs($this->admin())
            ->delete(route('admin.koperasi.destroy', $cooperative), [])
            ->assertSessionHasErrors('archived_reason');

        $this->assertNotSoftDeleted('cooperatives', ['id' => $cooperative->id]);
    }

    public function test_archived_cooperative_not_listed(): void
    {
        $cooperative = Cooperative::factory()->create(['nama' => 'Koperasi Terarsip Unik']);
        $cooperative->delete();

        $this->actingAs($this->admin())
            ->get(route('admin.koperasi.index'))
            ->assertOk()
            ->assertDontSee('Koperasi Terarsip Unik');
    }

    public function test_archived_cooperative_detail_returns_not_found(): void
    {
        $cooperative = Cooperative::factory()->create();
        $cooperative->delete();

        $this->actingAs($this->admin())
            ->get(route('admin.koperasi.show', $cooperative))
            ->assertNotFound();
    }

    public function test_archived_cooperative_edit_and_update_return_not_found(): void
    {
        $cooperative = Cooperative::factory()->create();
        $cooperative->delete();

        $this->actingAs($this->admin())
            ->get(route('admin.koperasi.edit', $cooperative))
            ->assertNotFound();

        $this->actingAs($this->admin())
            ->put(route('admin.koperasi.update', $cooperative), $this->payload())
            ->assertNotFound();

        $this->assertDatabaseHas('cooperatives', [
            'id' => $cooperative->id,
            'nama' => $cooperative->nama,
        ]);
    }

    public function test_archive_does_not_permanently_delete_data(): void
    {
        $cooperative = Cooperative::factory()->create(['nama' => 'Koperasi Tidak Hilang']);

        $this->actingAs($this->admin())
            ->delete(route('admin.koperasi.destroy', $cooperative), [
                'archived_reason' => 'Diarsipkan untuk uji',
            ]);

        // Baris masih ada (soft delete), bukan dihapus permanen.
        $this->assertDatabaseHas('cooperatives', [
            'id' => $cooperative->id,
            'nama' => 'Koperasi Tidak Hilang',
        ]);

        $restored = Cooperative::withTrashed()->findOrFail($cooperative->id);

        $this->assertNotNull($restored->deleted_at);
    }

    public function test_hard_deleting_cooperative_unlinks_linked_user(): void
    {
        $cooperative = Cooperative::factory()->create();
        $user = User::factory()->pengelola($cooperative->id)->create();

        // Hard delete hanya relevan untuk memverifikasi aturan FK (ON DELETE SET NULL);
        // operasional aplikasi selalu memakai soft delete (tanpa endpoint hard delete).
        $cooperative->forceDelete();

        $this->assertNull($user->fresh()->koperasi_id);
    }

    public function test_form_cannot_set_archived_status_directly(): void
    {
        $this->actingAs($this->admin())
            ->from(route('admin.koperasi.create'))
            ->post(route('admin.koperasi.store'), $this->payload(['status_verifikasi' => 'archived']))
            ->assertSessionHasErrors('status_verifikasi');

        $this->assertDatabaseMissing('cooperatives', ['nomor_identitas' => 'KOP-0001']);
    }

    public function test_create_form_does_not_offer_archived_option(): void
    {
        $this->actingAs($this->admin())
            ->get(route('admin.koperasi.create'))
            ->assertOk()
            ->assertDontSee('value="archived"', false);
    }

    public function test_unknown_region_id_is_rejected(): void
    {
        $this->actingAs($this->admin())
            ->from(route('admin.koperasi.create'))
            ->post(route('admin.koperasi.store'), $this->payload(['region_id' => 99999]))
            ->assertSessionHasErrors('region_id');
    }

    public function test_region_can_be_assigned_and_displayed(): void
    {
        $province = Region::factory()->create([
            'name' => 'Jawa Barat',
            'type' => 'province',
            'parent_id' => null,
        ]);
        $regency = Region::factory()->create([
            'name' => 'Kabupaten Contoh',
            'type' => 'regency',
            'parent_id' => $province->id,
        ]);
        $district = Region::factory()->create([
            'name' => 'Kecamatan Contoh',
            'type' => 'district',
            'parent_id' => $regency->id,
        ]);
        $village = Region::factory()->create([
            'name' => 'Desa Contoh',
            'type' => 'village',
            'parent_id' => $district->id,
        ]);

        $this->actingAs($this->admin())
            ->post(route('admin.koperasi.store'), $this->payload([
                'province_id' => $province->id,
                'regency_id' => $regency->id,
                'district_id' => $district->id,
                'region_id' => $village->id,
            ]))
            ->assertRedirect();

        $cooperative = Cooperative::firstOrFail();

        $this->assertSame($village->id, $cooperative->region_id);

        $this->actingAs($this->admin())
            ->get(route('admin.koperasi.show', $cooperative))
            ->assertOk()
            ->assertSee('Desa Contoh')
            ->assertSee('Jawa Barat > Kabupaten Contoh > Kecamatan Contoh > Desa Contoh');
    }

    public function test_region_hierarchy_must_match_selected_village(): void
    {
        $province = Region::factory()->create(['type' => 'province', 'parent_id' => null]);
        $regency = Region::factory()->create(['type' => 'regency', 'parent_id' => $province->id]);
        $otherRegency = Region::factory()->create(['type' => 'regency', 'parent_id' => $province->id]);
        $district = Region::factory()->create(['type' => 'district', 'parent_id' => $regency->id]);
        $village = Region::factory()->create(['type' => 'village', 'parent_id' => $district->id]);

        $this->actingAs($this->admin())
            ->from(route('admin.koperasi.create'))
            ->post(route('admin.koperasi.store'), $this->payload([
                'province_id' => $province->id,
                'regency_id' => $otherRegency->id,
                'district_id' => $district->id,
                'region_id' => $village->id,
            ]))
            ->assertSessionHasErrors('region_id');

        $this->assertDatabaseCount('cooperatives', 0);
    }

    public function test_new_cooperative_requires_village_level_region(): void
    {
        $province = Region::factory()->create(['type' => 'province', 'parent_id' => null]);
        $regency = Region::factory()->create(['type' => 'regency', 'parent_id' => $province->id]);

        $this->actingAs($this->admin())
            ->from(route('admin.koperasi.create'))
            ->post(route('admin.koperasi.store'), $this->payload([
                'province_id' => $province->id,
                'regency_id' => $regency->id,
                'region_id' => $regency->id,
            ]))
            ->assertSessionHasErrors('region_id');

        $this->assertDatabaseCount('cooperatives', 0);
    }

    public function test_legacy_non_village_region_can_be_kept_on_update(): void
    {
        $province = Region::factory()->create(['type' => 'province', 'parent_id' => null]);
        $regency = Region::factory()->create(['type' => 'regency', 'parent_id' => $province->id]);
        $cooperative = Cooperative::factory()->create([
            'region_id' => $regency->id,
            'status_operasional' => 'aktif',
        ]);

        $this->actingAs($this->admin())
            ->put(route('admin.koperasi.update', $cooperative), $this->payload([
                'nama' => $cooperative->nama,
                'nomor_identitas' => $cooperative->nomor_identitas,
                'province_id' => $province->id,
                'regency_id' => $regency->id,
                'region_id' => $regency->id,
            ]))
            ->assertRedirect(route('admin.koperasi.show', $cooperative));

        $this->assertSame($regency->id, $cooperative->fresh()->region_id);
    }

    public function test_create_form_renders_cascading_region_dropdowns(): void
    {
        $province = Region::factory()->create([
            'name' => 'Jawa Barat',
            'type' => 'province',
            'parent_id' => null,
        ]);
        $regency = Region::factory()->create([
            'name' => 'Kabupaten Contoh',
            'type' => 'regency',
            'parent_id' => $province->id,
        ]);
        $district = Region::factory()->create([
            'name' => 'Kecamatan Contoh',
            'type' => 'district',
            'parent_id' => $regency->id,
        ]);
        Region::factory()->create([
            'name' => 'Desa Contoh',
            'type' => 'village',
            'parent_id' => $district->id,
        ]);

        $this->actingAs($this->admin())
            ->get(route('admin.koperasi.create'))
            ->assertOk()
            ->assertSee('name="province_id"', false)
            ->assertSee('name="regency_id"', false)
            ->assertSee('name="district_id"', false)
            ->assertSee('name="region_id"', false)
            ->assertSee('Jawa Barat')
            ->assertSee('Kabupaten Contoh')
            ->assertSee('Kecamatan Contoh')
            ->assertSee('Desa Contoh');
    }

    public function test_search_matches_nama_and_nomor_identitas(): void
    {
        Cooperative::factory()->create(['nama' => 'Koperasi Alpha', 'nomor_identitas' => 'AAA-1']);
        Cooperative::factory()->create(['nama' => 'Koperasi Beta', 'nomor_identitas' => 'BBB-2']);

        $this->actingAs($this->admin())
            ->get(route('admin.koperasi.index', ['q' => 'Alpha']))
            ->assertOk()
            ->assertSee('Koperasi Alpha')
            ->assertDontSee('Koperasi Beta');

        $this->actingAs($this->admin())
            ->get(route('admin.koperasi.index', ['q' => 'BBB-2']))
            ->assertOk()
            ->assertSee('Koperasi Beta')
            ->assertDontSee('Koperasi Alpha');
    }

    public function test_filter_by_status(): void
    {
        Cooperative::factory()->create(['nama' => 'Koperasi Disetujui', 'status_verifikasi' => 'approved']);
        Cooperative::factory()->create(['nama' => 'Koperasi Draft', 'status_verifikasi' => 'draft']);

        $this->actingAs($this->admin())
            ->get(route('admin.koperasi.index', ['status_verifikasi' => 'approved']))
            ->assertOk()
            ->assertSee('Koperasi Disetujui')
            ->assertDontSee('Koperasi Draft');
    }

    public function test_invalid_sort_column_falls_back_safely(): void
    {
        Cooperative::factory()->count(2)->create();

        $this->actingAs($this->admin())
            ->get(route('admin.koperasi.index', ['sort' => 'password;drop table users', 'direction' => 'desc']))
            ->assertOk();
    }

    public function test_index_paginates(): void
    {
        Cooperative::factory()->count(15)->create();

        $this->actingAs($this->admin())
            ->get(route('admin.koperasi.index'))
            ->assertOk()
            ->assertViewHas('cooperatives', fn ($paginator) => $paginator->count() === 10 && $paginator->total() === 15);

        $this->actingAs($this->admin())
            ->get(route('admin.koperasi.index', ['page' => 2]))
            ->assertOk()
            ->assertViewHas('cooperatives', fn ($paginator) => $paginator->count() === 5);
    }

    public function test_show_displays_cooperative(): void
    {
        $cooperative = Cooperative::factory()->create(['nama' => 'Koperasi Tampil']);

        $this->actingAs($this->admin())
            ->get(route('admin.koperasi.show', $cooperative))
            ->assertOk()
            ->assertSee('Koperasi Tampil')
            ->assertSee($cooperative->nomor_identitas);
    }

    public function test_search_treats_percent_as_literal_not_wildcard(): void
    {
        $coop1 = Cooperative::factory()->create([
            'nama' => 'Koperasi A',
            'nomor_identitas' => 'ABC123456',
        ]);
        $coop2 = Cooperative::factory()->create([
            'nama' => 'Koperasi B',
            'nomor_identitas' => 'ABC%789',
        ]);

        $response = $this->actingAs($this->admin())
            ->get(route('admin.koperasi.index', ['q' => 'ABC%']))
            ->assertOk();

        $response->assertSee($coop2->nama);
        $response->assertDontSee($coop1->nama);
    }

    public function test_search_treats_underscore_as_literal_not_wildcard(): void
    {
        $coop1 = Cooperative::factory()->create([
            'nama' => 'Koperasi Match',
            'nomor_identitas' => 'XYZ123456',
        ]);
        $coop2 = Cooperative::factory()->create([
            'nama' => 'Koperasi Underscore',
            'nomor_identitas' => 'XYZ_789',
        ]);

        $response = $this->actingAs($this->admin())
            ->get(route('admin.koperasi.index', ['q' => 'XYZ_']))
            ->assertOk();

        $response->assertSee($coop2->nama);
        $response->assertDontSee($coop1->nama);
    }

    public function test_search_treats_exclamation_as_literal(): void
    {
        $coop1 = Cooperative::factory()->create([
            'nama' => 'Koperasi Normal',
            'nomor_identitas' => 'DEF123456',
        ]);
        $coop2 = Cooperative::factory()->create([
            'nama' => 'Koperasi Escape',
            'nomor_identitas' => 'DEF!456',
        ]);

        $response = $this->actingAs($this->admin())
            ->get(route('admin.koperasi.index', ['q' => 'DEF!']))
            ->assertOk();

        $response->assertSee($coop2->nama);
        $response->assertDontSee($coop1->nama);
    }

    public function test_search_by_nama_with_special_chars(): void
    {
        $coop1 = Cooperative::factory()->create([
            'nama' => 'Koperasi Normal',
        ]);
        $coop2 = Cooperative::factory()->create([
            'nama' => 'Koperasi%Khusus',
        ]);

        $response = $this->actingAs($this->admin())
            ->get(route('admin.koperasi.index', ['q' => 'Koperasi%']))
            ->assertOk();

        $response->assertSee($coop2->nama);
        $response->assertDontSee($coop1->nama);
    }

    public function test_search_normal_text_still_works(): void
    {
        $coop1 = Cooperative::factory()->create([
            'nama' => 'Koperasi Makmur',
            'nomor_identitas' => 'MAK001',
        ]);
        $coop2 = Cooperative::factory()->create([
            'nama' => 'Koperasi Maju',
            'nomor_identitas' => 'MAJ001',
        ]);

        $response = $this->actingAs($this->admin())
            ->get(route('admin.koperasi.index', ['q' => 'Makmur']))
            ->assertOk();

        $response->assertSee('Koperasi Makmur');
        $response->assertDontSee('Koperasi Maju');

        $response = $this->actingAs($this->admin())
            ->get(route('admin.koperasi.index', ['q' => 'MAJ']))
            ->assertOk();

        $response->assertSee('Koperasi Maju');
        $response->assertDontSee('Koperasi Makmur');
    }

    public function test_search_with_status_filter_still_works(): void
    {
        $coop1 = Cooperative::factory()->create([
            'nama' => 'Koperasi Draft',
            'status_verifikasi' => 'draft',
        ]);
        $coop2 = Cooperative::factory()->create([
            'nama' => 'Koperasi Approved',
            'status_verifikasi' => 'approved',
        ]);

        $response = $this->actingAs($this->admin())
            ->get(route('admin.koperasi.index', [
                'q' => 'Koperasi',
                'status_verifikasi' => 'approved',
            ]))
            ->assertOk();

        $response->assertSee($coop2->nama);
        $response->assertDontSee($coop1->nama);
    }
}
