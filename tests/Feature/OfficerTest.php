<?php

namespace Tests\Feature;

use App\Models\Cooperative;
use App\Models\Officer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Feature test untuk modul pengurus koperasi (officers).
 *
 * FR-120: CRUD susunan pengurus & periode jabatan.
 * FR-121: Status aktif/nonaktif pengurus.
 *
 * Cakupan testing:
 * - Admin dapat mengelola pengurus sesuai kewenangannya.
 * - Pengelola hanya dapat melihat pengurus koperasinya sendiri (read-only di F4).
 * - Akses lintas koperasi ditolak (anti-IDOR).
 * - Validasi tanggal akhir >= tanggal mulai.
 * - Data jabatan lama tetap tersimpan saat periode baru ditambahkan.
 * - Soft delete berfungsi.
 * - Nomor identitas tidak bocor ke pengguna yang tidak berwenang.
 *
 * @see docs/PRD.md FR-120, FR-121
 */
class OfficerTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_officers_index(): void
    {
        $admin = User::factory()->admin()->create();
        $cooperative = Cooperative::factory()->create();
        Officer::factory()->count(3)->for($cooperative)->create();

        $response = $this->actingAs($admin)
            ->get(route('admin.koperasi.pengurus.index', $cooperative));

        $response->assertOk();
        $response->assertViewIs('admin.koperasi.pengurus.index');
        $response->assertViewHas('officers');
    }

    public function test_admin_can_create_officer(): void
    {
        $admin = User::factory()->admin()->create();
        $cooperative = Cooperative::factory()->create();

        $response = $this->actingAs($admin)
            ->post(route('admin.koperasi.pengurus.store', $cooperative), [
                'name' => 'Budi Santoso',
                'position' => 'Ketua',
                'identity_number' => '3201234567890123',
                'period_start' => '2020-01-01',
                'period_end' => null,
                'is_active' => true,
            ]);

        $response->assertRedirect(route('admin.koperasi.pengurus.index', $cooperative));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('officers', [
            'cooperative_id' => $cooperative->id,
            'name' => 'Budi Santoso',
            'position' => 'Ketua',
            'identity_number' => '3201234567890123',
            'is_active' => 1, // Boolean disimpan sebagai integer
        ]);

        // Verifikasi tanggal terpisah
        $officer = Officer::where('name', 'Budi Santoso')->first();
        $this->assertNotNull($officer);
        $this->assertEquals('2020-01-01', $officer->period_start->format('Y-m-d'));
        $this->assertNull($officer->period_end);
    }

    public function test_admin_can_update_officer(): void
    {
        $admin = User::factory()->admin()->create();
        $cooperative = Cooperative::factory()->create();
        $officer = Officer::factory()->for($cooperative)->create([
            'name' => 'Old Name',
            'position' => 'Sekretaris',
        ]);

        $response = $this->actingAs($admin)
            ->put(route('admin.koperasi.pengurus.update', [$cooperative, $officer]), [
                'name' => 'New Name',
                'position' => 'Ketua',
                'period_start' => $officer->period_start->format('Y-m-d'),
                'is_active' => false,
            ]);

        $response->assertRedirect(route('admin.koperasi.pengurus.index', $cooperative));

        $this->assertDatabaseHas('officers', [
            'id' => $officer->id,
            'name' => 'New Name',
            'position' => 'Ketua',
            'is_active' => false,
        ]);
    }

    public function test_admin_can_soft_delete_officer(): void
    {
        $admin = User::factory()->admin()->create();
        $cooperative = Cooperative::factory()->create();
        $officer = Officer::factory()->for($cooperative)->create();

        $response = $this->actingAs($admin)
            ->delete(route('admin.koperasi.pengurus.destroy', [$cooperative, $officer]));

        $response->assertRedirect(route('admin.koperasi.pengurus.index', $cooperative));

        $this->assertSoftDeleted('officers', [
            'id' => $officer->id,
        ]);
    }

    public function test_period_end_must_be_after_or_equal_period_start(): void
    {
        $admin = User::factory()->admin()->create();
        $cooperative = Cooperative::factory()->create();

        $response = $this->actingAs($admin)
            ->post(route('admin.koperasi.pengurus.store', $cooperative), [
                'name' => 'Test',
                'position' => 'Ketua',
                'period_start' => '2024-12-31',
                'period_end' => '2024-01-01', // Sebelum period_start
                'is_active' => true,
            ]);

        $response->assertSessionHasErrors('period_end');
    }

    public function test_period_start_cannot_be_in_future(): void
    {
        $admin = User::factory()->admin()->create();
        $cooperative = Cooperative::factory()->create();

        $response = $this->actingAs($admin)
            ->post(route('admin.koperasi.pengurus.store', $cooperative), [
                'name' => 'Test',
                'position' => 'Ketua',
                'period_start' => now()->addYear()->format('Y-m-d'), // Masa depan
                'is_active' => true,
            ]);

        $response->assertSessionHasErrors('period_start');
    }

    public function test_pengelola_can_view_their_cooperative_officers(): void
    {
        $cooperative = Cooperative::factory()->create();
        $pengelola = User::factory()->pengelola()->create(['koperasi_id' => $cooperative->id]);
        Officer::factory()->count(2)->for($cooperative)->create();

        $response = $this->actingAs($pengelola)
            ->get(route('pengelola.pengurus.index'));

        $response->assertOk();
        $response->assertViewIs('pengelola.pengurus.index');
        $response->assertViewHas('officers');
    }

    public function test_pengelola_can_view_officer_detail_of_their_cooperative(): void
    {
        $cooperative = Cooperative::factory()->create();
        $pengelola = User::factory()->pengelola()->create(['koperasi_id' => $cooperative->id]);
        $officer = Officer::factory()->for($cooperative)->create();

        $response = $this->actingAs($pengelola)
            ->get(route('pengelola.pengurus.show', $officer));

        $response->assertOk();
        $response->assertViewIs('pengelola.pengurus.show');
    }

    public function test_pengelola_cannot_view_other_cooperative_officer(): void
    {
        $cooperativeA = Cooperative::factory()->create();
        $cooperativeB = Cooperative::factory()->create();
        $pengelola = User::factory()->pengelola()->create(['koperasi_id' => $cooperativeA->id]);
        $officerB = Officer::factory()->for($cooperativeB)->create();

        $response = $this->actingAs($pengelola)
            ->get(route('pengelola.pengurus.show', $officerB));

        $response->assertForbidden(); // Policy check: cooperative_id mismatch
    }

    public function test_pengelola_cannot_create_officer(): void
    {
        $cooperative = Cooperative::factory()->create();
        $pengelola = User::factory()->pengelola()->create(['koperasi_id' => $cooperative->id]);

        $response = $this->actingAs($pengelola)
            ->post(route('admin.koperasi.pengurus.store', $cooperative), [
                'name' => 'Test',
                'position' => 'Ketua',
                'period_start' => '2024-01-01',
                'is_active' => true,
            ]);

        // Middleware role:admin_dinas akan menolak
        $response->assertForbidden();
    }

    public function test_pengelola_cannot_update_officer(): void
    {
        $cooperative = Cooperative::factory()->create();
        $pengelola = User::factory()->pengelola()->create(['koperasi_id' => $cooperative->id]);
        $officer = Officer::factory()->for($cooperative)->create();

        $response = $this->actingAs($pengelola)
            ->put(route('admin.koperasi.pengurus.update', [$cooperative, $officer]), [
                'name' => 'New Name',
                'position' => 'Ketua',
                'period_start' => $officer->period_start->format('Y-m-d'),
            ]);

        $response->assertForbidden();
    }

    public function test_pengelola_cannot_delete_officer(): void
    {
        $cooperative = Cooperative::factory()->create();
        $pengelola = User::factory()->pengelola()->create(['koperasi_id' => $cooperative->id]);
        $officer = Officer::factory()->for($cooperative)->create();

        $response = $this->actingAs($pengelola)
            ->delete(route('admin.koperasi.pengurus.destroy', [$cooperative, $officer]));

        $response->assertForbidden();
    }

    public function test_guest_cannot_access_officers(): void
    {
        $cooperative = Cooperative::factory()->create();
        $officer = Officer::factory()->for($cooperative)->create();

        $response = $this->get(route('admin.koperasi.pengurus.index', $cooperative));
        $response->assertRedirect(route('login'));

        $response = $this->get(route('pengelola.pengurus.index'));
        $response->assertRedirect(route('login'));
    }

    public function test_scoped_binding_prevents_accessing_officer_from_other_cooperative(): void
    {
        $admin = User::factory()->admin()->create();
        $cooperativeA = Cooperative::factory()->create();
        $cooperativeB = Cooperative::factory()->create();
        $officerB = Officer::factory()->for($cooperativeB)->create();

        // Coba akses officer B melalui URL cooperative A
        $response = $this->actingAs($admin)
            ->get(route('admin.koperasi.pengurus.show', [$cooperativeA, $officerB]));

        // Scoped binding akan mengembalikan 404 (tidak menemukan officer di cooperative A)
        $response->assertNotFound();
    }

    public function test_historical_officers_are_preserved_when_adding_new_period(): void
    {
        $admin = User::factory()->admin()->create();
        $cooperative = Cooperative::factory()->create();

        // Tambah pengurus periode pertama
        $officer1 = Officer::factory()->for($cooperative)->create([
            'name' => 'Ketua Periode 1',
            'position' => 'Ketua',
            'period_start' => '2020-01-01',
            'period_end' => '2022-12-31',
        ]);

        // Tambah pengurus periode kedua (orang yang sama atau berbeda)
        $officer2 = Officer::factory()->for($cooperative)->create([
            'name' => 'Ketua Periode 2',
            'position' => 'Ketua',
            'period_start' => '2023-01-01',
            'period_end' => null,
        ]);

        // Pastikan kedua record tetap ada (tidak tertimpa)
        $this->assertDatabaseHas('officers', ['id' => $officer1->id]);
        $this->assertDatabaseHas('officers', ['id' => $officer2->id]);

        // Pastikan ada 2 pengurus Ketua di database
        $this->assertEquals(2, Officer::where('cooperative_id', $cooperative->id)->count());
    }

    public function test_identity_number_is_masked_in_model_method(): void
    {
        $officer = Officer::factory()->create([
            'identity_number' => '3201234567890123',
        ]);

        $masked = $officer->maskedIdentityNumber();

        // Format: 3201***********0123
        $this->assertStringStartsWith('3201', $masked);
        $this->assertStringEndsWith('0123', $masked);
        $this->assertStringContainsString('****', $masked);
        $this->assertNotEquals('3201234567890123', $masked);
    }

    public function test_officer_model_helper_methods(): void
    {
        // Pengurus yang masih menjabat
        $currentOfficer = Officer::factory()->currentlyServing()->create();
        $this->assertTrue($currentOfficer->isCurrentlyServing());
        $this->assertNull($currentOfficer->tenureDays());

        // Pengurus yang sudah selesai
        $completedOfficer = Officer::factory()->completed()->create();
        $this->assertFalse($completedOfficer->isCurrentlyServing());
        $this->assertIsInt($completedOfficer->tenureDays());
        $this->assertGreaterThanOrEqual(0, $completedOfficer->tenureDays());
    }

    public function test_cooperative_id_cannot_be_changed_via_mass_assignment(): void
    {
        $admin = User::factory()->admin()->create();
        $cooperativeA = Cooperative::factory()->create();
        $cooperativeB = Cooperative::factory()->create();
        $officer = Officer::factory()->for($cooperativeA)->create();

        // Coba ubah cooperative_id via update (seharusnya diabaikan)
        $response = $this->actingAs($admin)
            ->put(route('admin.koperasi.pengurus.update', [$cooperativeA, $officer]), [
                'cooperative_id' => $cooperativeB->id, // Coba ubah
                'name' => 'Test',
                'position' => 'Ketua',
                'period_start' => $officer->period_start->format('Y-m-d'),
            ]);

        // cooperative_id harus tetap A (tidak berubah)
        $this->assertDatabaseHas('officers', [
            'id' => $officer->id,
            'cooperative_id' => $cooperativeA->id, // Masih A
        ]);
    }

    public function test_identity_number_is_not_rendered_in_edit_form(): void
    {
        $admin = User::factory()->admin()->create();
        $cooperative = Cooperative::factory()->create();
        $officer = Officer::factory()->for($cooperative)->create([
            'identity_number' => '3201234567890123',
        ]);

        $response = $this->actingAs($admin)
            ->get(route('admin.koperasi.pengurus.edit', [$cooperative, $officer]));

        $response->assertOk();

        // Pastikan NIK asli tidak ada di HTML response (BR-012: keamanan data pribadi)
        $response->assertDontSee('3201234567890123');
        $response->assertSee('NIK tersimpan'); // Placeholder yang aman
    }

    public function test_empty_identity_number_preserves_old_value(): void
    {
        $admin = User::factory()->admin()->create();
        $cooperative = Cooperative::factory()->create();
        $officer = Officer::factory()->for($cooperative)->create([
            'name' => 'Test Officer',
            'position' => 'Ketua',
            'identity_number' => '3201234567890123',
        ]);

        // Update tanpa mengirim identity_number (field kosong)
        $response = $this->actingAs($admin)
            ->put(route('admin.koperasi.pengurus.update', [$cooperative, $officer]), [
                'name' => 'Updated Name',
                'position' => 'Ketua',
                'period_start' => $officer->period_start->format('Y-m-d'),
                'identity_number' => '', // Kosong
            ]);

        $response->assertRedirect(route('admin.koperasi.pengurus.index', $cooperative));

        // NIK lama harus tetap dipertahankan (BR-012)
        $this->assertDatabaseHas('officers', [
            'id' => $officer->id,
            'name' => 'Updated Name',
            'identity_number' => '3201234567890123', // Tetap
        ]);
    }

    public function test_new_identity_number_updates_correctly(): void
    {
        $admin = User::factory()->admin()->create();
        $cooperative = Cooperative::factory()->create();
        $officer = Officer::factory()->for($cooperative)->create([
            'name' => 'Test Officer',
            'position' => 'Ketua',
            'identity_number' => '3201234567890123',
        ]);

        // Update dengan NIK baru
        $response = $this->actingAs($admin)
            ->put(route('admin.koperasi.pengurus.update', [$cooperative, $officer]), [
                'name' => 'Test Officer',
                'position' => 'Ketua',
                'period_start' => $officer->period_start->format('Y-m-d'),
                'identity_number' => '3301987654321098', // NIK baru
            ]);

        $response->assertRedirect(route('admin.koperasi.pengurus.index', $cooperative));

        // NIK baru harus tersimpan
        $this->assertDatabaseHas('officers', [
            'id' => $officer->id,
            'identity_number' => '3301987654321098', // Updated
        ]);

        // NIK lama tidak ada lagi
        $this->assertDatabaseMissing('officers', [
            'id' => $officer->id,
            'identity_number' => '3201234567890123',
        ]);
    }
}
