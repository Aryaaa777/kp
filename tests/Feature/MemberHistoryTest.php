<?php

namespace Tests\Feature;

use App\Models\Cooperative;
use App\Models\MemberHistory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Feature test modul riwayat keanggotaan koperasi (FR-110/111).
 *
 * PROVISIONAL — pencatatan per kejadian/perubahan (bukan rekap tahunan). Lihat DECISIONS §10.
 * Tidak mengumpulkan data anggota individual/NIK.
 */
class MemberHistoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_login(): void
    {
        $cooperative = Cooperative::factory()->create();

        $this->get(route('admin.koperasi.anggota.index', $cooperative))->assertRedirect(route('login'));
        $this->get(route('pengelola.anggota.index'))->assertRedirect(route('login'));
    }

    public function test_admin_can_view_member_history_index(): void
    {
        $admin = User::factory()->admin()->create();
        $cooperative = Cooperative::factory()->create();
        MemberHistory::factory()->penambahan()->create([
            'cooperative_id' => $cooperative->id,
            'event_date' => '2024-03-01',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.koperasi.anggota.index', $cooperative))
            ->assertOk()
            ->assertSee('Penambahan anggota');
    }

    public function test_admin_can_create_member_history(): void
    {
        $admin = User::factory()->admin()->create();
        $cooperative = Cooperative::factory()->create();

        $this->actingAs($admin)
            ->post(route('admin.koperasi.anggota.store', $cooperative), [
                'event_date' => '2024-03-01',
                'change_type' => 'penambahan',
                'members_added' => 25,
                'notes' => 'Data uji.',
            ])
            ->assertRedirect(route('admin.koperasi.anggota.index', $cooperative));

        $this->assertDatabaseHas('member_histories', [
            'cooperative_id' => $cooperative->id,
            'change_type' => 'penambahan',
            'members_added' => 25,
            'recorded_by' => $admin->id,
        ]);
    }

    public function test_cooperative_id_from_input_is_ignored(): void
    {
        $admin = User::factory()->admin()->create();
        $cooperative = Cooperative::factory()->create();
        $other = Cooperative::factory()->create();

        $this->actingAs($admin)->post(route('admin.koperasi.anggota.store', $cooperative), [
            'cooperative_id' => $other->id,
            'event_date' => '2024-03-01',
            'change_type' => 'penambahan',
            'members_added' => 10,
        ]);

        $this->assertDatabaseHas('member_histories', ['cooperative_id' => $cooperative->id]);
        $this->assertDatabaseMissing('member_histories', ['cooperative_id' => $other->id]);
    }

    public function test_store_validates_required_fields(): void
    {
        $admin = User::factory()->admin()->create();
        $cooperative = Cooperative::factory()->create();

        $this->actingAs($admin)
            ->from(route('admin.koperasi.anggota.create', $cooperative))
            ->post(route('admin.koperasi.anggota.store', $cooperative), [])
            ->assertSessionHasErrors(['event_date', 'change_type']);
    }

    public function test_at_least_one_quantity_is_required(): void
    {
        $admin = User::factory()->admin()->create();
        $cooperative = Cooperative::factory()->create();

        $this->actingAs($admin)
            ->from(route('admin.koperasi.anggota.create', $cooperative))
            ->post(route('admin.koperasi.anggota.store', $cooperative), [
                'event_date' => '2024-03-01',
                'change_type' => 'penambahan',
                'members_added' => null,
                'members_removed' => null,
            ])
            ->assertSessionHasErrors(['members_added', 'members_removed']);
    }

    public function test_invalid_change_type_is_rejected(): void
    {
        $admin = User::factory()->admin()->create();
        $cooperative = Cooperative::factory()->create();

        $this->actingAs($admin)
            ->from(route('admin.koperasi.anggota.create', $cooperative))
            ->post(route('admin.koperasi.anggota.store', $cooperative), [
                'event_date' => '2024-03-01',
                'change_type' => 'jenis-tidak-dikenal',
                'members_added' => 10,
            ])
            ->assertSessionHasErrors('change_type');
    }

    public function test_future_event_date_is_rejected(): void
    {
        $admin = User::factory()->admin()->create();
        $cooperative = Cooperative::factory()->create();

        $this->actingAs($admin)
            ->from(route('admin.koperasi.anggota.create', $cooperative))
            ->post(route('admin.koperasi.anggota.store', $cooperative), [
                'event_date' => now()->addDay()->toDateString(),
                'change_type' => 'penambahan',
                'members_added' => 10,
            ])
            ->assertSessionHasErrors('event_date');
    }

    public function test_history_shows_timeline_across_events(): void
    {
        // AC-PER-03: riwayat menampilkan beberapa kejadian (urutan terbaru dulu).
        $admin = User::factory()->admin()->create();
        $cooperative = Cooperative::factory()->create();

        MemberHistory::factory()->penambahan()->create([
            'cooperative_id' => $cooperative->id,
            'event_date' => '2024-01-10',
            'members_added' => 15,
        ]);
        MemberHistory::factory()->pengurangan()->create([
            'cooperative_id' => $cooperative->id,
            'event_date' => '2024-08-15',
            'members_removed' => 3,
        ]);

        $response = $this->actingAs($admin)
            ->get(route('admin.koperasi.anggota.index', $cooperative))
            ->assertOk();

        $response->assertSeeInOrder(['15 Agt 2024', '10 Jan 2024']);
    }

    public function test_admin_can_update_member_history(): void
    {
        $admin = User::factory()->admin()->create();
        $cooperative = Cooperative::factory()->create();
        $history = MemberHistory::factory()->penambahan()->create(['cooperative_id' => $cooperative->id]);

        $this->actingAs($admin)
            ->put(route('admin.koperasi.anggota.update', [$cooperative, $history]), [
                'members_added' => 42,
                'notes' => 'Diperbarui.',
            ])
            ->assertRedirect(route('admin.koperasi.anggota.index', $cooperative));

        $this->assertDatabaseHas('member_histories', [
            'id' => $history->id,
            'members_added' => 42,
        ]);
    }

    public function test_admin_can_archive_member_history(): void
    {
        $admin = User::factory()->admin()->create();
        $cooperative = Cooperative::factory()->create();
        $history = MemberHistory::factory()->create(['cooperative_id' => $cooperative->id]);

        $this->actingAs($admin)
            ->delete(route('admin.koperasi.anggota.destroy', [$cooperative, $history]))
            ->assertRedirect(route('admin.koperasi.anggota.index', $cooperative));

        $this->assertSoftDeleted('member_histories', ['id' => $history->id]);
    }

    public function test_cross_cooperative_scoped_binding_returns_404(): void
    {
        $admin = User::factory()->admin()->create();
        $cooperativeA = Cooperative::factory()->create();
        $cooperativeB = Cooperative::factory()->create();
        $memberB = MemberHistory::factory()->create(['cooperative_id' => $cooperativeB->id]);

        $this->actingAs($admin)
            ->get(route('admin.koperasi.anggota.show', [$cooperativeA, $memberB]))
            ->assertNotFound();
    }

    public function test_pengelola_can_view_own_member_history(): void
    {
        $cooperative = Cooperative::factory()->create();
        $pengelola = User::factory()->pengelola($cooperative->id)->create();
        $history = MemberHistory::factory()->create(['cooperative_id' => $cooperative->id]);

        $this->actingAs($pengelola)->get(route('pengelola.anggota.index'))->assertOk();
        $this->actingAs($pengelola)->get(route('pengelola.anggota.show', $history))->assertOk();
    }

    public function test_pengelola_cannot_view_other_cooperative_member_history(): void
    {
        $cooperative = Cooperative::factory()->create();
        $other = Cooperative::factory()->create();
        $pengelola = User::factory()->pengelola($cooperative->id)->create();
        $foreign = MemberHistory::factory()->create(['cooperative_id' => $other->id]);

        $this->actingAs($pengelola)
            ->get(route('pengelola.anggota.show', $foreign))
            ->assertForbidden();
    }

    public function test_pengelola_cannot_write_member_history(): void
    {
        $cooperative = Cooperative::factory()->create();
        $pengelola = User::factory()->pengelola($cooperative->id)->create();

        $this->actingAs($pengelola)
            ->post(route('admin.koperasi.anggota.store', $cooperative), [
                'event_date' => '2024-03-01',
                'change_type' => 'penambahan',
                'members_added' => 10,
            ])
            ->assertForbidden();
    }

    public function test_unlinked_pengelola_index_is_forbidden(): void
    {
        $pengelola = User::factory()->pengelola(null)->create();

        $this->actingAs($pengelola)->get(route('pengelola.anggota.index'))->assertForbidden();
    }

    public function test_store_rejects_both_quantities_zero(): void
    {
        $admin = User::factory()->admin()->create();
        $cooperative = Cooperative::factory()->create();

        $this->actingAs($admin)
            ->from(route('admin.koperasi.anggota.create', $cooperative))
            ->post(route('admin.koperasi.anggota.store', $cooperative), [
                'event_date' => '2024-03-01',
                'change_type' => 'penambahan',
                'members_added' => 0,
                'members_removed' => 0,
            ])
            ->assertSessionHasErrors(['members_added', 'members_removed']);

        $this->assertSame(0, MemberHistory::count());
    }

    public function test_store_rejects_both_quantities_empty(): void
    {
        $admin = User::factory()->admin()->create();
        $cooperative = Cooperative::factory()->create();

        $this->actingAs($admin)
            ->from(route('admin.koperasi.anggota.create', $cooperative))
            ->post(route('admin.koperasi.anggota.store', $cooperative), [
                'event_date' => '2024-03-01',
                'change_type' => 'penambahan',
                'members_added' => '',
                'members_removed' => '',
            ])
            ->assertSessionHasErrors(['members_added', 'members_removed']);

        $this->assertSame(0, MemberHistory::count());
    }

    public function test_update_rejects_both_quantities_empty(): void
    {
        $admin = User::factory()->admin()->create();
        $cooperative = Cooperative::factory()->create();
        $history = MemberHistory::factory()->penambahan()->create([
            'cooperative_id' => $cooperative->id,
            'members_added' => 10,
            'members_removed' => null,
        ]);

        $this->actingAs($admin)
            ->from(route('admin.koperasi.anggota.edit', [$cooperative, $history]))
            ->put(route('admin.koperasi.anggota.update', [$cooperative, $history]), [
                'members_added' => '',
                'members_removed' => '',
            ])
            ->assertSessionHasErrors(['members_added', 'members_removed']);

        $this->assertDatabaseHas('member_histories', ['id' => $history->id, 'members_added' => 10]);
    }

    public function test_update_rejects_both_quantities_zero(): void
    {
        $admin = User::factory()->admin()->create();
        $cooperative = Cooperative::factory()->create();
        $history = MemberHistory::factory()->penambahan()->create(['cooperative_id' => $cooperative->id]);

        $this->actingAs($admin)
            ->from(route('admin.koperasi.anggota.edit', [$cooperative, $history]))
            ->put(route('admin.koperasi.anggota.update', [$cooperative, $history]), [
                'members_added' => 0,
                'members_removed' => 0,
            ])
            ->assertSessionHasErrors(['members_added', 'members_removed']);
    }

    public function test_update_accepts_zero_and_positive_quantities(): void
    {
        $admin = User::factory()->admin()->create();
        $cooperative = Cooperative::factory()->create();
        $history = MemberHistory::factory()->penambahan()->create(['cooperative_id' => $cooperative->id]);

        $this->actingAs($admin)
            ->put(route('admin.koperasi.anggota.update', [$cooperative, $history]), [
                'members_added' => 0,
                'members_removed' => 5,
            ])
            ->assertRedirect(route('admin.koperasi.anggota.index', $cooperative));

        $this->assertDatabaseHas('member_histories', [
            'id' => $history->id,
            'members_added' => 0,
            'members_removed' => 5,
        ]);
    }

    public function test_update_accepts_both_positive_quantities(): void
    {
        $admin = User::factory()->admin()->create();
        $cooperative = Cooperative::factory()->create();
        $history = MemberHistory::factory()->penambahan()->create(['cooperative_id' => $cooperative->id]);

        $this->actingAs($admin)
            ->put(route('admin.koperasi.anggota.update', [$cooperative, $history]), [
                'members_added' => 3,
                'members_removed' => 2,
            ])
            ->assertRedirect(route('admin.koperasi.anggota.index', $cooperative));

        $this->assertDatabaseHas('member_histories', [
            'id' => $history->id,
            'members_added' => 3,
            'members_removed' => 2,
        ]);
    }

    public function test_update_clearing_one_quantity_keeps_the_other(): void
    {
        $admin = User::factory()->admin()->create();
        $cooperative = Cooperative::factory()->create();
        $history = MemberHistory::factory()->penambahan()->create([
            'cooperative_id' => $cooperative->id,
            'members_added' => 10,
            'members_removed' => 4,
        ]);

        // Mengosongkan `members_added`; `members_removed` tidak dikirim → tetap 4 (nilai efektif).
        $this->actingAs($admin)
            ->put(route('admin.koperasi.anggota.update', [$cooperative, $history]), [
                'members_added' => '',
            ])
            ->assertRedirect(route('admin.koperasi.anggota.index', $cooperative));

        $this->assertDatabaseHas('member_histories', [
            'id' => $history->id,
            'members_added' => null,
            'members_removed' => 4,
        ]);
    }

    public function test_update_rejects_negative_quantity(): void
    {
        $admin = User::factory()->admin()->create();
        $cooperative = Cooperative::factory()->create();
        $history = MemberHistory::factory()->penambahan()->create(['cooperative_id' => $cooperative->id]);

        $this->actingAs($admin)
            ->from(route('admin.koperasi.anggota.edit', [$cooperative, $history]))
            ->put(route('admin.koperasi.anggota.update', [$cooperative, $history]), [
                'members_added' => -1,
                'members_removed' => 5,
            ])
            ->assertSessionHasErrors('members_added');
    }

    public function test_update_rejects_future_event_date(): void
    {
        $admin = User::factory()->admin()->create();
        $cooperative = Cooperative::factory()->create();
        $history = MemberHistory::factory()->penambahan()->create(['cooperative_id' => $cooperative->id]);

        $this->actingAs($admin)
            ->from(route('admin.koperasi.anggota.edit', [$cooperative, $history]))
            ->put(route('admin.koperasi.anggota.update', [$cooperative, $history]), [
                'event_date' => now()->addDay()->toDateString(),
            ])
            ->assertSessionHasErrors('event_date');
    }
}
