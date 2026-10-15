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
            ->assertSessionHasErrors(['nama', 'nomor_identitas', 'jenis_koperasi', 'status_verifikasi']);
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
            ]))
            ->assertSessionHasErrors(['email', 'website', 'tanggal_berdiri', 'status_verifikasi']);
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
        $region = Region::factory()->create(['name' => 'Kabupaten Contoh']);

        $this->actingAs($this->admin())
            ->post(route('admin.koperasi.store'), $this->payload(['region_id' => $region->id]))
            ->assertRedirect();

        $cooperative = Cooperative::firstOrFail();

        $this->assertSame($region->id, $cooperative->region_id);

        $this->actingAs($this->admin())
            ->get(route('admin.koperasi.show', $cooperative))
            ->assertOk()
            ->assertSee('Kabupaten Contoh');
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
}
