<?php

namespace Tests\Feature;

use App\Exceptions\SubmissionVersionException;
use App\Models\Cooperative;
use App\Models\Submission;
use App\Models\SubmissionItem;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Services\Submissions\Handlers\KoperasiIndukHandler;
use App\Services\SubmissionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

/**
 * Versioning pengajuan (FR-031/FR-142, keputusan Tahap 63 / D-1 opsi B).
 *
 * Revisi atas pengajuan `needs_revision` membuat **pengajuan baru** yang tertaut ke versi induk
 * (`parent_submission_id`); versi lama & riwayat tinjauannya tetap tersimpan. Beberapa pengajuan
 * aktif yang bertentangan untuk koperasi/modul yang sama dicegah.
 *
 * **Batasan bukti (Tahap 65):** pengujian berjalan di SQLite yang mengabaikan `lockForUpdate()`
 * (no-op), sehingga tes serialisasi di bawah membuktikan **aturan logika** (permintaan kedua
 * ditolak) dan **atomisitas transaksi**, bukan ketiadaan balapan di MySQL/MariaDB. Lihat
 * `docs/DECISIONS.md` §18.
 */
class SubmissionVersioningTest extends TestCase
{
    use RefreshDatabase;

    private function service(): SubmissionService
    {
        return app(SubmissionService::class);
    }

    public function test_revision_creates_a_linked_child_and_preserves_parent_history(): void
    {
        $cooperative = Cooperative::factory()->create(['nama' => 'Koperasi Asli']);
        $pengelola = User::factory()->pengelola($cooperative->id)->create();

        $parent = Submission::factory()->create([
            'cooperative_id' => $cooperative->id,
            'submitted_by' => $pengelola->id,
            'status' => 'needs_revision',
            'reason' => 'Lengkapi.',
        ]);
        SubmissionItem::factory()->create([
            'submission_id' => $parent->id,
            'field' => 'nama',
            'old_value' => 'Koperasi Asli',
            'new_value' => 'Usulan Lama',
        ]);

        $child = $this->service()->createRevision($pengelola, $parent, [
            'nama' => 'Usulan Baru',
            'jenis_koperasi' => $cooperative->jenis_koperasi,
        ]);

        $this->assertSame($parent->id, $child->parent_submission_id);
        $this->assertSame('draft', $child->status);
        $this->assertSame($parent->module, $child->module);
        $this->assertSame($parent->cooperative_id, $child->cooperative_id);
        $this->assertSame('Usulan Baru', $child->items->firstWhere('field', 'nama')->new_value);

        // Riwayat versi lama utuh (tidak tertimpa oleh revisi).
        $this->assertSame('Usulan Lama', $parent->fresh()->items->firstWhere('field', 'nama')->new_value);
        $this->assertSame('needs_revision', $parent->fresh()->status);
        $this->assertTrue($parent->fresh()->isSuperseded());

        $this->assertDatabaseHas('activity_logs', ['action' => 'create_submission_revision']);
    }

    public function test_revision_requires_a_needs_revision_parent(): void
    {
        $cooperative = Cooperative::factory()->create(['nama' => 'Koperasi Asli']);
        $pengelola = User::factory()->pengelola($cooperative->id)->create();

        $draft = Submission::factory()->create([
            'cooperative_id' => $cooperative->id,
            'submitted_by' => $pengelola->id,
            'status' => 'draft',
        ]);

        $this->expectException(SubmissionVersionException::class);

        $this->service()->createRevision($pengelola, $draft, ['nama' => 'Nama Baru']);
    }

    public function test_a_second_revision_is_rejected_after_the_first_child(): void
    {
        $cooperative = Cooperative::factory()->create(['nama' => 'Koperasi Asli']);
        $pengelola = User::factory()->pengelola($cooperative->id)->create();

        $parent = Submission::factory()->create([
            'cooperative_id' => $cooperative->id,
            'submitted_by' => $pengelola->id,
            'status' => 'needs_revision',
        ]);

        $this->service()->createRevision($pengelola, $parent, ['nama' => 'Versi 2']);

        $this->expectException(SubmissionVersionException::class);

        $this->service()->createRevision($pengelola, $parent->fresh(), ['nama' => 'Versi 3']);
    }

    public function test_a_fresh_draft_is_blocked_while_an_active_submission_exists(): void
    {
        $cooperative = Cooperative::factory()->create(['nama' => 'Koperasi Asli']);
        $pengelola = User::factory()->pengelola($cooperative->id)->create();

        Submission::factory()->submitted()->create([
            'cooperative_id' => $cooperative->id,
            'submitted_by' => $pengelola->id,
        ]);

        $this->expectException(SubmissionVersionException::class);

        $this->service()->createDraft($pengelola, $cooperative, 'koperasi_induk', ['nama' => 'Nama Baru']);
    }

    public function test_a_fresh_draft_is_blocked_while_an_unrevised_needs_revision_exists(): void
    {
        $cooperative = Cooperative::factory()->create(['nama' => 'Koperasi Asli']);
        $pengelola = User::factory()->pengelola($cooperative->id)->create();

        Submission::factory()->create([
            'cooperative_id' => $cooperative->id,
            'submitted_by' => $pengelola->id,
            'status' => 'needs_revision',
        ]);

        $this->expectException(SubmissionVersionException::class);

        $this->service()->createDraft($pengelola, $cooperative, 'koperasi_induk', ['nama' => 'Nama Baru']);
    }

    public function test_a_fresh_draft_is_allowed_after_the_previous_version_is_superseded_and_closed(): void
    {
        $cooperative = Cooperative::factory()->create(['nama' => 'Koperasi Asli']);
        $pengelola = User::factory()->pengelola($cooperative->id)->create();
        $admin = User::factory()->admin()->create();

        $parent = Submission::factory()->create([
            'cooperative_id' => $cooperative->id,
            'submitted_by' => $pengelola->id,
            'status' => 'needs_revision',
        ]);

        $child = $this->service()->createRevision($pengelola, $parent, ['nama' => 'Koperasi Revisi']);

        $this->service()->submit($child, $pengelola);
        $this->service()->startReview($child, $admin);
        $this->service()->approve($child, $admin);

        // Induk sudah digantikan & versi turunan selesai → tidak lagi menghambat pengajuan baru.
        $fresh = $this->service()->createDraft($pengelola, $cooperative->fresh(), 'koperasi_induk', [
            'nama' => 'Koperasi Berikutnya',
        ]);

        $this->assertSame('draft', $fresh->status);
        $this->assertNull($fresh->parent_submission_id);

        // Statistik yang penting: hanya ada satu pengajuan aktif untuk koperasi/modul ini.
        $this->assertSame('approved', $child->fresh()->status);
    }

    public function test_a_fresh_draft_is_allowed_after_a_previous_rejection(): void
    {
        $cooperative = Cooperative::factory()->create(['nama' => 'Koperasi Asli']);
        $pengelola = User::factory()->pengelola($cooperative->id)->create();

        Submission::factory()->rejected()->create([
            'cooperative_id' => $cooperative->id,
            'submitted_by' => $pengelola->id,
        ]);

        $fresh = $this->service()->createDraft($pengelola, $cooperative, 'koperasi_induk', ['nama' => 'Nama Baru']);

        $this->assertSame('draft', $fresh->status);
    }

    public function test_pengelola_cannot_revise_another_cooperative_submission(): void
    {
        $cooperativeA = Cooperative::factory()->create();
        $cooperativeB = Cooperative::factory()->create();
        $pengelolaA = User::factory()->pengelola($cooperativeA->id)->create();

        $parentB = Submission::factory()->create([
            'cooperative_id' => $cooperativeB->id,
            'status' => 'needs_revision',
        ]);

        // Anti-IDOR: formulir versi perbaikan koperasi lain → 403.
        $this->actingAs($pengelolaA)
            ->get(route('pengelola.pengajuan.create', ['revision' => $parentB->id]))
            ->assertForbidden();

        $this->actingAs($pengelolaA)
            ->post(route('pengelola.pengajuan.store'), [
                'module' => 'koperasi_induk',
                'parent_submission_id' => $parentB->id,
                'nama' => 'Nama Baru',
                'jenis_koperasi' => 'Konsumen',
            ])
            ->assertForbidden();

        $this->assertSame(0, Submission::query()->where('parent_submission_id', $parentB->id)->count());
    }

    public function test_needs_revision_submission_cannot_be_edited_or_submitted_in_place(): void
    {
        $cooperative = Cooperative::factory()->create();
        $pengelola = User::factory()->pengelola($cooperative->id)->create();

        $submission = Submission::factory()->create([
            'cooperative_id' => $cooperative->id,
            'submitted_by' => $pengelola->id,
            'status' => 'needs_revision',
        ]);

        $this->assertFalse($submission->isEditable());
        $this->assertTrue($submission->isRevisable());

        // Matriks transisi (PRD §12.2, D-1): record `needs_revision` hanya bisa diarsipkan.
        $this->assertFalse($submission->canTransitionTo('submitted'));
        $this->assertTrue($submission->canTransitionTo('archived'));

        $this->actingAs($pengelola)
            ->get(route('pengelola.pengajuan.edit', $submission))
            ->assertForbidden();

        $this->actingAs($pengelola)
            ->post(route('pengelola.pengajuan.submit', $submission))
            ->assertForbidden();

        $this->assertSame('needs_revision', $submission->fresh()->status);
    }

    public function test_version_chain_is_visible_in_detail_views(): void
    {
        $cooperative = Cooperative::factory()->create(['nama' => 'Koperasi Asli']);
        $pengelola = User::factory()->pengelola($cooperative->id)->create();
        $admin = User::factory()->admin()->create();

        $parent = Submission::factory()->create([
            'cooperative_id' => $cooperative->id,
            'submitted_by' => $pengelola->id,
            'status' => 'needs_revision',
        ]);

        $child = $this->service()->createRevision($pengelola, $parent, ['nama' => 'Nama Baru']);

        $this->actingAs($pengelola)
            ->get(route('pengelola.pengajuan.show', $child))
            ->assertOk()
            ->assertSee('Rantai Versi Pengajuan')
            ->assertSee(route('pengelola.pengajuan.show', $parent), false);

        $this->actingAs($pengelola)
            ->get(route('pengelola.pengajuan.show', $parent))
            ->assertOk()
            ->assertSee('Rantai Versi Pengajuan')
            ->assertSee(route('pengelola.pengajuan.show', $child), false);

        $this->actingAs($admin)
            ->get(route('admin.pengajuan.show', $child))
            ->assertOk()
            ->assertSee('Rantai Versi Pengajuan')
            ->assertSee(route('admin.pengajuan.show', $parent), false);
    }

    public function test_legacy_submission_without_parent_is_unaffected(): void
    {
        $cooperative = Cooperative::factory()->create();
        $pengelola = User::factory()->pengelola($cooperative->id)->create();

        $legacy = Submission::factory()->create([
            'cooperative_id' => $cooperative->id,
            'submitted_by' => $pengelola->id,
            'status' => 'needs_revision',
        ]);

        $this->assertNull($legacy->parent_submission_id);
        $this->assertFalse($legacy->isRevision());
        $this->assertFalse($legacy->isSuperseded());
        $this->assertTrue($legacy->isActive());
        $this->assertTrue($legacy->isRevisable());

        $this->actingAs($pengelola)
            ->get(route('pengelola.pengajuan.show', $legacy))
            ->assertOk();
    }

    public function test_needs_revision_notification_link_still_resolves_after_creating_a_version(): void
    {
        $cooperative = Cooperative::factory()->create();
        $pengelola = User::factory()->pengelola($cooperative->id)->create();
        $admin = User::factory()->admin()->create();

        $submission = Submission::factory()->underReview()->create([
            'cooperative_id' => $cooperative->id,
            'submitted_by' => $pengelola->id,
        ]);

        $this->service()->requestRevision($submission, $admin, 'Perbaiki alamat.');

        $notification = $pengelola->notifications()->first();
        $this->assertNotNull($notification);
        $this->assertSame(route('pengelola.pengajuan.show', $submission), $notification->data['url']);

        $this->service()->createRevision($pengelola, $submission->fresh(), ['nama' => 'Nama Baru']);

        // Tautan notifikasi lama tetap dapat dibuka (bukan 404) walaupun versi sudah digantikan.
        $this->actingAs($pengelola)->get($notification->data['url'])->assertOk();
    }

    /**
     * T-1: dua permintaan draft berurutan pada koperasi/modul yang sama tidak boleh sama-sama
     * menghasilkan pengajuan aktif (permintaan kedua ditolak).
     */
    public function test_two_draft_requests_for_the_same_module_do_not_both_succeed(): void
    {
        $cooperative = Cooperative::factory()->create();
        $pengelola = User::factory()->pengelola($cooperative->id)->create();

        $first = $this->service()->createDraft($pengelola, $cooperative, 'koperasi_induk', ['nama' => 'Nama Pertama']);

        $this->assertSame('draft', $first->status);
        $this->assertSame(1, Submission::query()->where('cooperative_id', $cooperative->id)->count());

        $this->expectException(SubmissionVersionException::class);

        $this->service()->createDraft($pengelola, $cooperative, 'koperasi_induk', ['nama' => 'Nama Kedua']);
    }

    /**
     * T-1: modul yang berbeda pada koperasi yang sama tidak saling menghambat.
     */
    public function test_different_modules_on_the_same_cooperative_do_not_block_each_other(): void
    {
        config(['koperasi.submission_modules' => [
            'koperasi_induk' => ['label' => 'Data Induk', 'handler' => KoperasiIndukHandler::class],
            'izin' => ['label' => 'Perizinan', 'handler' => KoperasiIndukHandler::class],
        ]]);

        $cooperative = Cooperative::factory()->create();
        $pengelola = User::factory()->pengelola($cooperative->id)->create();

        $induk = $this->service()->createDraft($pengelola, $cooperative, 'koperasi_induk', ['nama' => 'Nama Induk']);
        $izin = $this->service()->createDraft($pengelola, $cooperative, 'izin', ['nama' => 'Nama Izin']);

        $this->assertNotSame($induk->id, $izin->id);
        $this->assertSame(2, Submission::query()->where('cooperative_id', $cooperative->id)->count());
    }

    /**
     * T-1: pengajuan aktif milik koperasi lain tidak menghambat (konflik dibatasi per koperasi).
     */
    public function test_active_submission_of_another_cooperative_does_not_block(): void
    {
        $cooperativeA = Cooperative::factory()->create();
        $cooperativeB = Cooperative::factory()->create();
        $pengelolaB = User::factory()->pengelola($cooperativeB->id)->create();

        Submission::factory()->submitted()->create(['cooperative_id' => $cooperativeA->id]);

        $draft = $this->service()->createDraft($pengelolaB, $cooperativeB, 'koperasi_induk', ['nama' => 'Nama B']);

        $this->assertSame($cooperativeB->id, $draft->cooperative_id);
        $this->assertSame('draft', $draft->status);
    }

    /**
     * T-1: setelah draft turunan diarsipkan, induk kembali dapat diperbaiki (aturan yang sudah ada).
     */
    public function test_parent_becomes_revisable_again_after_the_revision_draft_is_archived(): void
    {
        $cooperative = Cooperative::factory()->create(['nama' => 'Koperasi Asli']);
        $pengelola = User::factory()->pengelola($cooperative->id)->create();

        $parent = Submission::factory()->create([
            'cooperative_id' => $cooperative->id,
            'submitted_by' => $pengelola->id,
            'status' => 'needs_revision',
        ]);

        $child = $this->service()->createRevision($pengelola, $parent, ['nama' => 'Versi 2']);

        $this->assertTrue($parent->fresh()->isSuperseded());
        $this->assertFalse($parent->fresh()->isRevisable());

        // Arsipkan draft turunan → induk kembali actionable.
        $this->service()->archive($child, $pengelola);

        $this->assertFalse($parent->fresh()->isSuperseded());
        $this->assertTrue($parent->fresh()->isRevisable());

        $second = $this->service()->createRevision($pengelola, $parent->fresh(), ['nama' => 'Versi 3']);

        $this->assertSame($parent->id, $second->parent_submission_id);
        // Dua versi pernah dibuat; hanya satu yang masih aktif (yang lain ter-soft-delete).
        $this->assertSame(2, Submission::withTrashed()->where('parent_submission_id', $parent->id)->count());
        $this->assertSame(1, Submission::query()->where('parent_submission_id', $parent->id)->count());
    }

    /**
     * T-1: kegagalan di tengah transaksi tidak meninggalkan record parsial (rollback).
     */
    public function test_creation_rolls_back_when_a_step_fails_inside_the_transaction(): void
    {
        $cooperative = Cooperative::factory()->create();
        $pengelola = User::factory()->pengelola($cooperative->id)->create();

        $this->mock(ActivityLogger::class, function ($mock): void {
            $mock->shouldReceive('log')->andThrow(new RuntimeException('audit gagal'));
        });

        try {
            $this->service()->createDraft($pengelola, $cooperative, 'koperasi_induk', ['nama' => 'Nama Baru']);
            $this->fail('Pembuatan seharusnya gagal di dalam transaksi.');
        } catch (RuntimeException $exception) {
            $this->assertSame('audit gagal', $exception->getMessage());
        }

        // Rollback penuh: tidak ada submission/items/reviews yang tertinggal.
        $this->assertDatabaseCount('submissions', 0);
        $this->assertDatabaseCount('submission_items', 0);
        $this->assertDatabaseCount('submission_reviews', 0);
    }

    /**
     * R-6 (Tahap 64): riwayat tinjauan versi induk tetap tersimpan & tampil setelah versi
     * turunan dibuat (bukan hanya di audit log).
     */
    public function test_parent_review_history_remains_visible_after_a_version_is_created(): void
    {
        $cooperative = Cooperative::factory()->create();
        $pengelola = User::factory()->pengelola($cooperative->id)->create();
        $admin = User::factory()->admin()->create();

        $submission = Submission::factory()->underReview()->create([
            'cooperative_id' => $cooperative->id,
            'submitted_by' => $pengelola->id,
        ]);

        $this->service()->requestRevision($submission, $admin, 'Lengkapi alamat resmi.');
        $this->service()->createRevision($pengelola, $submission->fresh(), ['nama' => 'Nama Baru']);

        $this->actingAs($pengelola)
            ->get(route('pengelola.pengajuan.show', $submission))
            ->assertOk()
            ->assertSee('Riwayat Status')
            ->assertSee('Lengkapi alamat resmi.');

        $parent = $submission->fresh();
        $this->assertSame(1, $parent->reviews()->count());
        $this->assertSame('Lengkapi alamat resmi.', $parent->reviews()->first()->note);
    }

    /**
     * R-6 (Tahap 64): versi turunan (ID baru) tidak membuka akses lintas koperasi.
     */
    public function test_pengelola_cannot_view_another_cooperative_revision_version(): void
    {
        $cooperativeA = Cooperative::factory()->create();
        $cooperativeB = Cooperative::factory()->create();
        $pengelolaA = User::factory()->pengelola($cooperativeA->id)->create();
        $pengelolaB = User::factory()->pengelola($cooperativeB->id)->create();

        $parentB = Submission::factory()->create([
            'cooperative_id' => $cooperativeB->id,
            'submitted_by' => $pengelolaB->id,
            'status' => 'needs_revision',
        ]);

        $childB = $this->service()->createRevision($pengelolaB, $parentB, ['nama' => 'Nama B']);

        $this->actingAs($pengelolaA)
            ->get(route('pengelola.pengajuan.show', $childB))
            ->assertForbidden();
    }
}
