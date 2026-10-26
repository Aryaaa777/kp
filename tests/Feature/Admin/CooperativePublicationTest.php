<?php

namespace Tests\Feature\Admin;

use App\Models\ActivityLog;
use App\Models\Cooperative;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Kontrol publikasi direktori publik oleh admin dinas (F7 — FR-190, AC-PUB-01).
 *
 * Menegakkan:
 * - Hanya admin dinas berwenang (middleware `role:admin_dinas` **dan** `CooperativePolicy::publish`).
 * - Syarat publikasi = aturan direktori publik (`config('koperasi.public.visible_statuses')`,
 *   saat ini `approved`) — status verifikasi tidak boleh diubah sebagai efek samping.
 * - `published_at`/`published_by` diisi sistem, tidak pernah dari input pengguna.
 * - **D-6**: penarikan mencatat `unpublished_at`/`unpublished_by`, jejak publikasi lama
 *   dipertahankan, penarikan berulang tidak menimpa jejak, dan riwayat siklus ada di audit log.
 * - Konsistensi dengan Tahap 84: koperasi yang ditarik langsung hilang dari direktori & profil.
 *
 * Catatan bukti: SQLite `:memory:` — **bukan** bukti kompatibilitas MariaDB.
 */
class CooperativePublicationTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->admin()->create();
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function cooperative(array $overrides = []): Cooperative
    {
        return Cooperative::factory()->create(array_merge([
            'status_verifikasi' => 'approved',
            'is_published' => false,
        ], $overrides));
    }

    public function test_admin_can_publish_an_approved_cooperative(): void
    {
        $admin = $this->admin();
        $cooperative = $this->cooperative(['nama' => 'Koperasi Siap Publik']);

        $this->actingAs($admin)
            ->from(route('admin.koperasi.show', $cooperative))
            ->post(route('admin.koperasi.publish', $cooperative))
            ->assertRedirect(route('admin.koperasi.show', $cooperative))
            ->assertSessionHas('status');

        $cooperative->refresh();

        $this->assertTrue($cooperative->is_published);
        $this->assertSame('approved', $cooperative->status_verifikasi);
    }

    public function test_publish_stamps_published_at_and_published_by_from_the_system(): void
    {
        $admin = $this->admin();
        $cooperative = $this->cooperative();

        $this->travelTo(Carbon::parse('2026-10-19 10:15:00'));

        $this->actingAs($admin)
            ->post(route('admin.koperasi.publish', $cooperative))
            ->assertRedirect(route('admin.koperasi.show', $cooperative));

        $cooperative->refresh();

        $this->assertTrue($cooperative->is_published);
        $this->assertNotNull($cooperative->published_at);
        $this->assertTrue($cooperative->published_at->equalTo(Carbon::parse('2026-10-19 10:15:00')));
        $this->assertSame($admin->id, (int) $cooperative->published_by);
        $this->assertSame($admin->id, (int) $cooperative->updated_by);

        $this->assertDatabaseHas('activity_logs', [
            'action' => 'publish_cooperative',
            'subject_type' => $cooperative->getMorphClass(),
            'subject_id' => $cooperative->id,
            'user_id' => $admin->id,
        ]);
    }

    public function test_admin_can_unpublish_and_status_verification_is_untouched(): void
    {
        $admin = $this->admin();
        $cooperative = $this->cooperative();

        $this->actingAs($admin)->post(route('admin.koperasi.publish', $cooperative));
        $cooperative->refresh();
        $publishedAt = $cooperative->published_at;

        $this->assertNotNull($publishedAt);

        $this->actingAs($admin)
            ->from(route('admin.koperasi.show', $cooperative))
            ->post(route('admin.koperasi.unpublish', $cooperative))
            ->assertRedirect(route('admin.koperasi.show', $cooperative))
            ->assertSessionHas('status');

        $cooperative->refresh();

        $this->assertFalse($cooperative->is_published);
        $this->assertSame('approved', $cooperative->status_verifikasi);
        $this->assertNull($cooperative->deleted_at);
        $this->assertNotSame('rejected', $cooperative->status_verifikasi);
        $this->assertNotSame('archived', $cooperative->status_verifikasi);

        $this->assertDatabaseHas('activity_logs', [
            'action' => 'unpublish_cooperative',
            'subject_id' => $cooperative->id,
        ]);
    }

    public function test_unpublish_keeps_the_last_publication_trace(): void
    {
        // D-6: jejak publikasi TIDAK dikosongkan saat publikasi ditarik.
        $admin = $this->admin();
        $cooperative = $this->cooperative();

        $this->actingAs($admin)->post(route('admin.koperasi.publish', $cooperative));
        $cooperative->refresh();
        $publishedAt = $cooperative->published_at;

        $this->actingAs($admin)->post(route('admin.koperasi.unpublish', $cooperative));
        $cooperative->refresh();

        $this->assertNotNull($cooperative->published_at);
        $this->assertTrue($publishedAt->equalTo($cooperative->published_at));
        $this->assertSame($admin->id, (int) $cooperative->published_by);
    }

    public function test_unpublish_stamps_unpublished_at_and_unpublished_by_from_the_system(): void
    {
        $admin = $this->admin();
        $cooperative = $this->cooperative();

        $this->travelTo(Carbon::parse('2026-10-19 10:15:00'));
        $this->actingAs($admin)->post(route('admin.koperasi.publish', $cooperative));

        $this->travelTo(Carbon::parse('2026-10-20 09:30:00'));
        $this->actingAs($admin)
            ->withSession(['auth.last_activity_at' => now()->timestamp])
            ->post(route('admin.koperasi.unpublish', $cooperative))
            ->assertRedirect(route('admin.koperasi.show', $cooperative));

        $cooperative->refresh();

        $this->assertFalse($cooperative->is_published);
        $this->assertNotNull($cooperative->unpublished_at);
        $this->assertTrue($cooperative->unpublished_at->equalTo(Carbon::parse('2026-10-20 09:30:00')));
        $this->assertSame($admin->id, (int) $cooperative->unpublished_by);

        // Jejak publikasi tetap utuh.
        $this->assertNotNull($cooperative->published_at);
        $this->assertTrue($cooperative->published_at->equalTo(Carbon::parse('2026-10-19 10:15:00')));
        $this->assertSame($admin->id, (int) $cooperative->published_by);

        $this->assertDatabaseHas('activity_logs', [
            'action' => 'unpublish_cooperative',
            'subject_id' => $cooperative->id,
            'user_id' => $admin->id,
        ]);

        // UI admin menampilkan jejak penarikan (bukan hanya tersimpan di DB).
        $this->actingAs($admin)
            ->get(route('admin.koperasi.show', $cooperative))
            ->assertOk()
            ->assertSee('Penarikan terakhir')
            ->assertSee('Ditarik oleh')
            ->assertSee($admin->name);
    }

    public function test_repeated_unpublish_does_not_overwrite_the_withdrawal_trace(): void
    {
        $admin = $this->admin();
        $cooperative = $this->cooperative();

        $this->travelTo(Carbon::parse('2026-10-19 10:15:00'));
        $this->actingAs($admin)->post(route('admin.koperasi.publish', $cooperative));

        $this->travelTo(Carbon::parse('2026-10-20 09:30:00'));
        $this->actingAs($admin)
            ->withSession(['auth.last_activity_at' => now()->timestamp])
            ->post(route('admin.koperasi.unpublish', $cooperative));
        $cooperative->refresh();

        $withdrawnAt = $cooperative->unpublished_at;
        $withdrawnBy = (int) $cooperative->unpublished_by;

        $this->assertNotNull($withdrawnAt);

        // Penarikan kedua kali: ditolak tanpa mengubah baris (jejak tidak ditimpa).
        $this->travelTo(Carbon::parse('2026-10-25 14:00:00'));
        $this->actingAs($admin)
            ->withSession(['auth.last_activity_at' => now()->timestamp])
            ->from(route('admin.koperasi.show', $cooperative))
            ->post(route('admin.koperasi.unpublish', $cooperative))
            ->assertRedirect(route('admin.koperasi.show', $cooperative))
            ->assertSessionHas('error');

        $cooperative->refresh();

        $this->assertFalse($cooperative->is_published);
        $this->assertTrue($withdrawnAt->equalTo($cooperative->unpublished_at));
        $this->assertSame($withdrawnBy, (int) $cooperative->unpublished_by);
    }

    public function test_republish_keeps_the_previous_withdrawal_trace_and_audit_history(): void
    {
        $admin = $this->admin();
        $cooperative = $this->cooperative();

        $this->travelTo(Carbon::parse('2026-10-19 10:15:00'));
        $this->actingAs($admin)->post(route('admin.koperasi.publish', $cooperative));

        $this->travelTo(Carbon::parse('2026-10-20 09:30:00'));
        $this->actingAs($admin)
            ->withSession(['auth.last_activity_at' => now()->timestamp])
            ->post(route('admin.koperasi.unpublish', $cooperative));
        $cooperative->refresh();

        $withdrawnAt = $cooperative->unpublished_at;
        $this->assertNotNull($withdrawnAt);

        // Publikasi ulang: siklus baru dicatat, jejak penarikan sebelumnya TIDAK dikosongkan.
        $this->travelTo(Carbon::parse('2026-10-22 08:00:00'));
        $this->actingAs($admin)
            ->withSession(['auth.last_activity_at' => now()->timestamp])
            ->post(route('admin.koperasi.publish', $cooperative));
        $cooperative->refresh();

        $this->assertTrue($cooperative->is_published);
        $this->assertTrue($cooperative->published_at->equalTo(Carbon::parse('2026-10-22 08:00:00')));
        $this->assertSame($admin->id, (int) $cooperative->published_by);
        $this->assertTrue($withdrawnAt->equalTo($cooperative->unpublished_at));
        $this->assertSame($admin->id, (int) $cooperative->unpublished_by);

        // Riwayat seluruh siklus tersimpan append-only di activity_logs.
        $this->assertSame(2, ActivityLog::query()
            ->where('action', 'publish_cooperative')
            ->where('subject_id', $cooperative->id)
            ->count());
        $this->assertSame(1, ActivityLog::query()
            ->where('action', 'unpublish_cooperative')
            ->where('subject_id', $cooperative->id)
            ->count());
    }

    public function test_published_cooperative_appears_publicly_and_unpublished_one_disappears(): void
    {
        $admin = $this->admin();
        $cooperative = $this->cooperative(['nama' => 'Koperasi Konsistensi Direktori']);
        $profileUrl = route('publik.koperasi', ['slug' => $cooperative->slug]);

        // Sebelum publikasi: tidak ada di direktori maupun profil.
        $this->get(route('publik.direktori'))->assertOk()->assertDontSee('Koperasi Konsistensi Direktori');
        $this->get($profileUrl)->assertNotFound();

        $this->actingAs($admin)->post(route('admin.koperasi.publish', $cooperative))->assertRedirect();

        $this->get(route('publik.direktori'))->assertOk()->assertSee('Koperasi Konsistensi Direktori');
        $this->get($profileUrl)->assertOk()->assertSee('Koperasi Konsistensi Direktori');

        $this->actingAs($admin)->post(route('admin.koperasi.unpublish', $cooperative))->assertRedirect();

        // Penarikan langsung berlaku: hilang dari direktori, pencarian, dan profil.
        $this->get(route('publik.direktori'))->assertOk()->assertDontSee('Koperasi Konsistensi Direktori');
        $this->get(route('publik.direktori', ['q' => 'Konsistensi Direktori']))->assertOk()->assertDontSee('Koperasi Konsistensi Direktori');
        $this->get($profileUrl)->assertNotFound();
    }

    public function test_published_cooperative_that_loses_approved_status_is_flagged_and_hidden_publicly(): void
    {
        $admin = $this->admin();
        $cooperative = $this->cooperative([
            'nama' => 'Koperasi Publikasi Tidak Konsisten',
            'is_published' => true,
            'published_at' => now(),
            'published_by' => $admin->id,
        ]);

        // Perubahan status di luar alur publikasi (mis. koreksi data) tidak otomatis
        // menarik publikasi, tetapi direktori publik tetap menyembunyikannya.
        $cooperative->update(['status_verifikasi' => 'draft']);

        $this->actingAs($admin)
            ->get(route('admin.koperasi.show', $cooperative))
            ->assertOk()
            ->assertSee('masih ditandai')
            ->assertSee('tidak tampil')
            ->assertSee('Tarik publikasi');

        $this->get(route('publik.direktori'))
            ->assertOk()
            ->assertDontSee('Koperasi Publikasi Tidak Konsisten');

        $this->get(route('publik.koperasi', ['slug' => $cooperative->slug]))
            ->assertNotFound();
    }

    public function test_cooperative_without_approved_status_cannot_be_published(): void
    {
        $admin = $this->admin();

        foreach (['draft', 'submitted', 'under_review', 'needs_revision', 'rejected', 'archived'] as $status) {
            $cooperative = $this->cooperative(['status_verifikasi' => $status]);
            $updatedAt = $cooperative->updated_at;

            $this->actingAs($admin)
                ->from(route('admin.koperasi.show', $cooperative))
                ->post(route('admin.koperasi.publish', $cooperative))
                ->assertSessionHas('error');

            $cooperative->refresh();

            $this->assertFalse($cooperative->is_published, "Status {$status} tidak boleh dapat dipublikasikan.");
            $this->assertNull($cooperative->published_at, "Status {$status} tidak boleh menstempel published_at.");
            $this->assertNull($cooperative->published_by, "Status {$status} tidak boleh menstempel published_by.");

            // Aksi yang gagal tidak boleh meninggalkan perubahan apa pun (termasuk jejak audit).
            $this->assertTrue($updatedAt->equalTo($cooperative->updated_at), "Status {$status}: aksi gagal tidak boleh menyentuh updated_at.");
            $this->assertNull($cooperative->updated_by, "Status {$status}: aksi gagal tidak boleh menyentuh updated_by.");
            $this->assertDatabaseMissing('activity_logs', [
                'action' => 'publish_cooperative',
                'subject_id' => $cooperative->id,
            ]);
        }
    }

    public function test_admin_eligibility_and_public_directory_share_one_configuration_source(): void
    {
        // Kelayakan publikasi TIDAK diduplikasi: kontrol admin & direktori publik membaca
        // `config('koperasi.public.visible_statuses')` yang sama, sehingga tidak bisa berbeda.
        config(['koperasi.public.visible_statuses' => ['approved', 'under_review']]);

        $admin = $this->admin();
        $cooperative = $this->cooperative([
            'status_verifikasi' => 'under_review',
            'nama' => 'Koperasi Sedang Ditinjau',
        ]);

        // Belum diterbitkan → belum tampil, meski statusnya termasuk daftar yang diizinkan.
        $this->get(route('publik.direktori'))->assertOk()->assertDontSee('Koperasi Sedang Ditinjau');

        $this->actingAs($admin)
            ->post(route('admin.koperasi.publish', $cooperative))
            ->assertRedirect(route('admin.koperasi.show', $cooperative))
            ->assertSessionHas('status');

        $cooperative->refresh();

        $this->assertTrue($cooperative->is_published);
        $this->assertSame('under_review', $cooperative->status_verifikasi);

        // Setelah diterbitkan → langsung tampil, membuktikan aturan kedua sisi identik.
        $this->get(route('publik.direktori'))->assertOk()->assertSee('Koperasi Sedang Ditinjau');
    }

    public function test_publishing_an_already_published_cooperative_is_rejected(): void
    {
        $admin = $this->admin();
        $cooperative = $this->cooperative([
            'is_published' => true,
            'published_at' => Carbon::parse('2026-01-02 08:00:00'),
            'published_by' => $admin->id,
        ]);

        $this->actingAs($admin)
            ->from(route('admin.koperasi.show', $cooperative))
            ->post(route('admin.koperasi.publish', $cooperative))
            ->assertSessionHas('error');

        $cooperative->refresh();

        $this->assertTrue($cooperative->published_at->equalTo(Carbon::parse('2026-01-02 08:00:00')));
    }

    public function test_unpublishing_a_not_published_cooperative_is_rejected(): void
    {
        $cooperative = $this->cooperative();

        $this->actingAs($this->admin())
            ->from(route('admin.koperasi.show', $cooperative))
            ->post(route('admin.koperasi.unpublish', $cooperative))
            ->assertSessionHas('error');

        $this->assertFalse($cooperative->refresh()->is_published);
    }

    public function test_guest_cannot_publish_or_unpublish(): void
    {
        $cooperative = $this->cooperative();

        $this->post(route('admin.koperasi.publish', $cooperative))->assertRedirect(route('login'));
        $this->post(route('admin.koperasi.unpublish', $cooperative))->assertRedirect(route('login'));

        $cooperative->refresh();

        $this->assertFalse($cooperative->is_published);
        $this->assertNull($cooperative->published_at);
        $this->assertNull($cooperative->published_by);
    }

    public function test_authenticated_user_without_admin_role_cannot_publish(): void
    {
        $cooperative = $this->cooperative();

        // Pengelola yang menaungi koperasi tersebut TETAP tidak berwenang mempublikasikan.
        $pengelola = User::factory()->pengelola($cooperative->id)->create();

        $this->actingAs($pengelola)
            ->post(route('admin.koperasi.publish', $cooperative))
            ->assertForbidden();

        $this->actingAs($pengelola)
            ->post(route('admin.koperasi.unpublish', $cooperative))
            ->assertForbidden();

        // Akun terautentikasi tanpa peran apa pun.
        $this->actingAs(User::factory()->create())
            ->post(route('admin.koperasi.publish', $cooperative))
            ->assertForbidden();

        $cooperative->refresh();

        $this->assertFalse($cooperative->is_published);
        $this->assertNull($cooperative->published_at);
        $this->assertNull($cooperative->published_by);
    }

    public function test_forged_publication_fields_in_the_publish_request_are_ignored(): void
    {
        $admin = $this->admin();
        $otherAdmin = $this->admin();
        $cooperative = $this->cooperative();

        $this->actingAs($admin)
            ->from(route('admin.koperasi.show', $cooperative))
            ->post(route('admin.koperasi.publish', $cooperative), [
                'is_published' => false,
                'published_at' => '2000-01-01 00:00:00',
                'published_by' => $otherAdmin->id,
                'status_verifikasi' => 'rejected',
            ])
            ->assertRedirect(route('admin.koperasi.show', $cooperative));

        $cooperative->refresh();

        $this->assertTrue($cooperative->is_published);
        $this->assertFalse($cooperative->published_at->equalTo(Carbon::parse('2000-01-01 00:00:00')));
        $this->assertSame($admin->id, (int) $cooperative->published_by);
        $this->assertSame('approved', $cooperative->status_verifikasi);
    }

    public function test_admin_update_form_cannot_forge_publication_fields(): void
    {
        $admin = $this->admin();
        $cooperative = $this->cooperative([
            'nama' => 'Koperasi Aman',
            'nomor_identitas' => 'KOP-AMAN-1',
        ]);

        $this->actingAs($admin)
            ->put(route('admin.koperasi.update', $cooperative), [
                'nama' => 'Koperasi Aman',
                'nomor_identitas' => 'KOP-AMAN-1',
                'jenis_koperasi' => 'Koperasi Uji',
                'status_verifikasi' => 'approved',
                'is_published' => true,
                'published_at' => now()->toDateTimeString(),
                'published_by' => $admin->id,
            ])
            ->assertRedirect(route('admin.koperasi.show', $cooperative));

        $cooperative->refresh();

        $this->assertFalse($cooperative->is_published);
        $this->assertNull($cooperative->published_at);
        $this->assertNull($cooperative->published_by);
    }

    public function test_archived_cooperative_cannot_be_published(): void
    {
        $cooperative = $this->cooperative();
        $cooperative->delete();

        $this->actingAs($this->admin())
            ->post(route('admin.koperasi.publish', $cooperative))
            ->assertNotFound();

        $this->assertFalse((bool) Cooperative::withTrashed()->findOrFail($cooperative->id)->is_published);
    }

    public function test_publication_endpoints_reject_get(): void
    {
        $cooperative = $this->cooperative();

        $this->actingAs($this->admin())
            ->get(route('admin.koperasi.publish', $cooperative))
            ->assertStatus(405);

        $this->actingAs($this->admin())
            ->get(route('admin.koperasi.unpublish', $cooperative))
            ->assertStatus(405);
    }

    public function test_existing_admin_cooperative_routes_still_work(): void
    {
        $admin = $this->admin();
        $cooperative = Cooperative::factory()->create(['nama' => 'Koperasi Regresi Admin']);

        $this->actingAs($admin)->get(route('admin.koperasi.index'))->assertOk()->assertSee('Koperasi Regresi Admin');
        $this->actingAs($admin)->get(route('admin.koperasi.show', $cooperative))->assertOk()->assertSee('Koperasi Regresi Admin');
        $this->actingAs($admin)->get(route('admin.koperasi.edit', $cooperative))->assertOk();
    }

    public function test_show_page_displays_publication_state(): void
    {
        $publisher = $this->admin();
        $viewer = $this->admin();
        $cooperative = $this->cooperative();

        $this->actingAs($viewer)
            ->get(route('admin.koperasi.show', $cooperative))
            ->assertOk()
            ->assertSee('Belum dipublikasikan')
            ->assertSee('Publikasikan');

        $this->actingAs($publisher)->post(route('admin.koperasi.publish', $cooperative));

        // Ditampilkan sebagai `viewer`, jadi nama yang muncul adalah **publisher**, bukan pengunjung.
        $this->actingAs($viewer)
            ->get(route('admin.koperasi.show', $cooperative))
            ->assertOk()
            ->assertSee('Dipublikasikan')
            ->assertSee('Tarik publikasi')
            ->assertSee($publisher->name);
    }

    public function test_show_page_explains_why_an_unapproved_cooperative_cannot_be_published(): void
    {
        $cooperative = $this->cooperative(['status_verifikasi' => 'draft']);

        $this->actingAs($this->admin())
            ->get(route('admin.koperasi.show', $cooperative))
            ->assertOk()
            ->assertSee('belum dapat dipublikasikan')
            ->assertDontSee('Publikasikan');
    }

    public function test_publication_hint_follows_the_configured_status_list(): void
    {
        $admin = $this->admin();
        $cooperative = $this->cooperative();

        // Default (Tahap 90): petunjuk diturunkan dari konfigurasi, bukan kalimat hardcoded.
        $this->assertSame(['Disetujui'], Cooperative::publicStatusLabels());

        $this->actingAs($admin)
            ->get(route('admin.koperasi.show', $cooperative))
            ->assertOk()
            ->assertSee('Hanya koperasi dengan status verifikasi Disetujui yang dapat dipublikasikan.')
            ->assertDontSee('Hanya koperasi berstatus disetujui yang dapat dipublikasikan.');

        // Konfigurasi berubah → petunjuk ikut berubah tanpa menyentuh kode view.
        // Urutan label mengikuti STATUS_LABELS (alur verifikasi resmi), bukan urutan array config.
        config(['koperasi.public.visible_statuses' => ['approved', 'under_review']]);

        $this->assertSame(['Dalam Pemeriksaan', 'Disetujui'], Cooperative::publicStatusLabels());

        $this->actingAs($admin)
            ->get(route('admin.koperasi.show', $cooperative))
            ->assertOk()
            ->assertSee('Hanya koperasi dengan status verifikasi Dalam Pemeriksaan atau Disetujui yang dapat dipublikasikan.')
            ->assertDontSee('Hanya koperasi berstatus disetujui yang dapat dipublikasikan.');
    }

    public function test_publication_hint_never_claims_a_status_without_an_official_label(): void
    {
        // Status tanpa label resmi: tidak ada label yang boleh ditampilkan sebagai klaim kelayakan.
        config(['koperasi.public.visible_statuses' => ['status_yang_tidak_ada']]);

        $this->assertSame([], Cooperative::publicStatusLabels());

        $cooperative = $this->cooperative();

        $this->actingAs($this->admin())
            ->get(route('admin.koperasi.show', $cooperative))
            ->assertOk()
            ->assertDontSee('Hanya koperasi dengan status verifikasi')
            ->assertDontSee('Hanya koperasi berstatus disetujui')
            ->assertSee('belum dapat dipublikasikan')
            ->assertDontSee('Publikasikan');

        // Konfigurasi kosong juga tidak menambah kelayakan apa pun dan tidak mengklaim status.
        config(['koperasi.public.visible_statuses' => []]);

        $this->assertSame([], Cooperative::publicStatusLabels());

        $this->actingAs($this->admin())
            ->get(route('admin.koperasi.show', $cooperative))
            ->assertOk()
            ->assertDontSee('Hanya koperasi dengan status verifikasi')
            ->assertDontSee('Publikasikan');
    }
}
