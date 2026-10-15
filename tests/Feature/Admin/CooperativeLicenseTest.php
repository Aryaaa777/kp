<?php

namespace Tests\Feature\Admin;

use App\Models\Cooperative;
use App\Models\CooperativeLicense;
use App\Models\Document;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * CRUD & verifikasi izin oleh admin dinas (FR-050/051/060/061).
 */
class CooperativeLicenseTest extends TestCase
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
            'license_type' => CooperativeLicense::TYPE_BUSINESS,
            'nomor' => 'IU-0001',
            'tanggal_terbit' => '2024-01-10',
            'tanggal_berlaku_sampai' => '2029-01-09',
            'penerbit' => 'Dinas Contoh',
            'catatan' => null,
        ], $overrides);
    }

    public function test_admin_can_view_license_index(): void
    {
        $cooperative = Cooperative::factory()->create();

        $this->actingAs($this->admin())
            ->get(route('admin.koperasi.izin.index', $cooperative))
            ->assertOk()
            ->assertSee('Perizinan');
    }

    public function test_admin_can_open_all_license_pages(): void
    {
        $cooperative = Cooperative::factory()->create();
        $license = CooperativeLicense::factory()->create([
            'cooperative_id' => $cooperative->id,
            'nomor' => 'IU-HALAMAN-1',
        ]);
        Document::factory()->forLicense($license)->create();
        $admin = $this->admin();

        // Memastikan seluruh blade (index/create/show/edit + form unggah & verifikasi) benar-benar ter-render.
        $this->actingAs($admin)->get(route('admin.koperasi.izin.index', $cooperative))
            ->assertOk()->assertSee('IU-HALAMAN-1');

        $this->actingAs($admin)->get(route('admin.koperasi.izin.create', $cooperative))
            ->assertOk()->assertSee('Tambah Izin');

        $this->actingAs($admin)->get(route('admin.koperasi.izin.show', [$cooperative, $license]))
            ->assertOk()->assertSee('IU-HALAMAN-1')->assertSee('Unggah Dokumen');

        $this->actingAs($admin)->get(route('admin.koperasi.izin.edit', [$cooperative, $license]))
            ->assertOk()->assertSee('Ubah Izin');
    }

    public function test_admin_can_create_license(): void
    {
        $cooperative = Cooperative::factory()->create();

        $response = $this->actingAs($this->admin())
            ->post(route('admin.koperasi.izin.store', $cooperative), $this->payload());

        $license = CooperativeLicense::firstOrFail();

        $response->assertRedirect(route('admin.koperasi.izin.show', [$cooperative, $license]));
        $this->assertSame($cooperative->id, $license->cooperative_id);
        $this->assertSame('IU-0001', $license->nomor);
        // Status awal selalu `pending` (belum diverifikasi).
        $this->assertSame('pending', $license->status);
        $this->assertNull($license->verified_by);
        $this->assertNull($license->verified_at);
    }

    public function test_nomor_is_unique_per_cooperative_and_type(): void
    {
        $cooperative = Cooperative::factory()->create();
        CooperativeLicense::factory()->create([
            'cooperative_id' => $cooperative->id,
            'license_type' => CooperativeLicense::TYPE_BUSINESS,
            'nomor' => 'IU-0001',
        ]);

        $this->actingAs($this->admin())
            ->from(route('admin.koperasi.izin.create', $cooperative))
            ->post(route('admin.koperasi.izin.store', $cooperative), $this->payload(['nomor' => 'IU-0001']))
            ->assertSessionHasErrors('nomor');
    }

    public function test_same_nomor_allowed_for_different_license_types(): void
    {
        $cooperative = Cooperative::factory()->create();
        CooperativeLicense::factory()->create([
            'cooperative_id' => $cooperative->id,
            'license_type' => CooperativeLicense::TYPE_BUSINESS,
            'nomor' => 'SAMA-1',
        ]);

        $this->actingAs($this->admin())
            ->post(route('admin.koperasi.izin.store', $cooperative), $this->payload([
                'license_type' => CooperativeLicense::TYPE_SAVINGS_LOANS,
                'nomor' => 'SAMA-1',
            ]))
            ->assertSessionHasNoErrors();

        $this->assertSame(2, CooperativeLicense::count());
    }

    public function test_same_nomor_allowed_for_different_cooperative(): void
    {
        $other = Cooperative::factory()->create();
        CooperativeLicense::factory()->create([
            'cooperative_id' => $other->id,
            'license_type' => CooperativeLicense::TYPE_BUSINESS,
            'nomor' => 'IU-0001',
        ]);

        $cooperative = Cooperative::factory()->create();

        $this->actingAs($this->admin())
            ->post(route('admin.koperasi.izin.store', $cooperative), $this->payload(['nomor' => 'IU-0001']))
            ->assertSessionHasNoErrors();
    }

    public function test_berlaku_before_terbit_is_rejected(): void
    {
        $cooperative = Cooperative::factory()->create();

        // AC-LIC-01
        $this->actingAs($this->admin())
            ->from(route('admin.koperasi.izin.create', $cooperative))
            ->post(route('admin.koperasi.izin.store', $cooperative), $this->payload([
                'tanggal_terbit' => '2024-06-01',
                'tanggal_berlaku_sampai' => '2024-05-31',
            ]))
            ->assertSessionHasErrors('tanggal_berlaku_sampai');

        $this->assertSame(0, CooperativeLicense::count());
    }

    public function test_nullable_berlaku_is_allowed(): void
    {
        $cooperative = Cooperative::factory()->create();

        $this->actingAs($this->admin())
            ->post(route('admin.koperasi.izin.store', $cooperative), $this->payload([
                'tanggal_berlaku_sampai' => null,
            ]))
            ->assertSessionHasNoErrors();

        $this->assertNull(CooperativeLicense::firstOrFail()->tanggal_berlaku_sampai);
    }

    public function test_store_requires_mandatory_fields(): void
    {
        $cooperative = Cooperative::factory()->create();

        $this->actingAs($this->admin())
            ->from(route('admin.koperasi.izin.create', $cooperative))
            ->post(route('admin.koperasi.izin.store', $cooperative), [])
            ->assertSessionHasErrors(['license_type', 'nomor', 'tanggal_terbit']);
    }

    public function test_admin_can_update_license_without_changing_status(): void
    {
        $cooperative = Cooperative::factory()->create();
        $license = CooperativeLicense::factory()->create(['cooperative_id' => $cooperative->id]);

        $this->actingAs($this->admin())
            ->put(route('admin.koperasi.izin.update', [$cooperative, $license]), $this->payload([
                'nomor' => 'IU-UB',
            ]))
            ->assertRedirect(route('admin.koperasi.izin.show', [$cooperative, $license]));

        $license->refresh();

        $this->assertSame('IU-UB', $license->nomor);
        // Status tetap `pending` — perubahan status hanya lewat aksi verifikasi.
        $this->assertSame('pending', $license->status);
    }

    public function test_admin_can_verify_license(): void
    {
        $cooperative = Cooperative::factory()->create();
        $license = CooperativeLicense::factory()->create(['cooperative_id' => $cooperative->id]);
        $admin = $this->admin();

        $this->actingAs($admin)
            ->post(route('admin.koperasi.izin.verify', [$cooperative, $license]), [
                'status' => 'active',
                'catatan' => 'Sesuai dokumen.',
            ])
            ->assertRedirect(route('admin.koperasi.izin.show', [$cooperative, $license]));

        $license->refresh();

        $this->assertSame('active', $license->status);
        $this->assertSame($admin->id, $license->verified_by);
        $this->assertNotNull($license->verified_at);
    }

    public function test_verify_rejects_unknown_and_pending_status(): void
    {
        $cooperative = Cooperative::factory()->create();
        $license = CooperativeLicense::factory()->create(['cooperative_id' => $cooperative->id]);

        $this->actingAs($this->admin())
            ->post(route('admin.koperasi.izin.verify', [$cooperative, $license]), ['status' => 'tidak-dikenal'])
            ->assertSessionHasErrors('status');

        $this->actingAs($this->admin())
            ->post(route('admin.koperasi.izin.verify', [$cooperative, $license]), ['status' => 'pending'])
            ->assertSessionHasErrors('status');

        $this->assertSame('pending', $license->fresh()->status);
    }

    public function test_admin_can_archive_license_soft_delete(): void
    {
        $cooperative = Cooperative::factory()->create();
        $license = CooperativeLicense::factory()->create(['cooperative_id' => $cooperative->id]);

        $this->actingAs($this->admin())
            ->delete(route('admin.koperasi.izin.destroy', [$cooperative, $license]))
            ->assertRedirect(route('admin.koperasi.izin.index', $cooperative));

        $this->assertSoftDeleted('cooperative_licenses', ['id' => $license->id]);
    }

    public function test_archived_license_detail_returns_not_found(): void
    {
        $cooperative = Cooperative::factory()->create();
        $license = CooperativeLicense::factory()->create(['cooperative_id' => $cooperative->id]);
        $license->delete();

        $this->actingAs($this->admin())
            ->get(route('admin.koperasi.izin.show', [$cooperative, $license]))
            ->assertNotFound();
    }

    public function test_license_of_another_cooperative_returns_not_found(): void
    {
        $cooperative = Cooperative::factory()->create();
        $other = Cooperative::factory()->create();
        $license = CooperativeLicense::factory()->create(['cooperative_id' => $other->id]);

        // Id izin koperasi lain tidak dapat diakses lewat id yang ditebak (404, bukan 403).
        $this->actingAs($this->admin())
            ->get(route('admin.koperasi.izin.show', [$cooperative, $license]))
            ->assertNotFound();
    }

    public function test_mass_assignment_cannot_set_cooperative_status_or_verifier(): void
    {
        $cooperative = Cooperative::factory()->create();
        $other = Cooperative::factory()->create();

        $this->actingAs($this->admin())
            ->post(route('admin.koperasi.izin.store', $cooperative), $this->payload([
                'cooperative_id' => $other->id,
                'status' => 'active',
                'verified_by' => 12345,
                'verified_at' => now()->toDateTimeString(),
            ]))
            ->assertSessionHasNoErrors();

        $license = CooperativeLicense::firstOrFail();

        $this->assertSame($cooperative->id, $license->cooperative_id);
        $this->assertSame('pending', $license->status);
        $this->assertNull($license->verified_by);
        $this->assertNull($license->verified_at);
    }

    public function test_expiry_is_identified_by_date(): void
    {
        $past = CooperativeLicense::factory()->past()->create();
        $noLimit = CooperativeLicense::factory()->create(['tanggal_berlaku_sampai' => null]);
        $future = CooperativeLicense::factory()->create(['tanggal_berlaku_sampai' => now()->addYear()]);

        $this->assertTrue($past->isExpired());
        $this->assertFalse($noLimit->isExpired());
        $this->assertFalse($future->isExpired());
    }
}
