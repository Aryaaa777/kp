<?php

namespace Tests\Feature;

use App\Exceptions\SubmissionTransitionException;
use App\Models\Cooperative;
use App\Models\Submission;
use App\Models\SubmissionItem;
use App\Models\User;
use App\Services\SubmissionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Feature test alur pengelola: buat draft, kirim, perbaiki (needs_revision), arsipkan (FR-140/142).
 *
 * @see docs/PRD.md §12, docs/USER_FLOWS.md FLOW-PGL-02/04
 */
class PengelolaSubmissionTest extends TestCase
{
    use RefreshDatabase;

    public function test_pengelola_without_cooperative_is_forbidden(): void
    {
        $pengelola = User::factory()->pengelola(null)->create();

        $this->actingAs($pengelola)->get(route('pengelola.pengajuan.index'))->assertForbidden();
        $this->actingAs($pengelola)->get(route('pengelola.pengajuan.create'))->assertForbidden();
    }

    public function test_create_form_shows_official_values(): void
    {
        $cooperative = Cooperative::factory()->create(['nama' => 'Koperasi Contoh']);
        $pengelola = User::factory()->pengelola($cooperative->id)->create();

        $this->actingAs($pengelola)
            ->get(route('pengelola.pengajuan.create'))
            ->assertOk()
            ->assertSee('Koperasi Contoh')
            ->assertSee('Usulan Perubahan');
    }

    public function test_pengelola_can_store_draft(): void
    {
        $cooperative = Cooperative::factory()->create(['nama' => 'Koperasi Asli']);
        $pengelola = User::factory()->pengelola($cooperative->id)->create();

        $response = $this->actingAs($pengelola)->post(route('pengelola.pengajuan.store'), [
            'module' => 'koperasi_induk',
            'nama' => 'Koperasi Usulan',
            'jenis_koperasi' => $cooperative->jenis_koperasi,
            'email' => $cooperative->email,
        ]);

        $submission = Submission::query()->latest('id')->first();

        $this->assertNotNull($submission);
        $response->assertRedirect(route('pengelola.pengajuan.show', $submission));

        $this->assertSame('draft', $submission->status);
        $this->assertSame($cooperative->id, $submission->cooperative_id);
        $this->assertSame($pengelola->id, $submission->submitted_by);
        $this->assertSame('Koperasi Asli', $cooperative->fresh()->nama);
    }

    public function test_store_without_changes_returns_error(): void
    {
        $cooperative = Cooperative::factory()->create(['nama' => 'Koperasi Asli']);
        $pengelola = User::factory()->pengelola($cooperative->id)->create();

        $this->actingAs($pengelola)
            ->from(route('pengelola.pengajuan.create'))
            ->post(route('pengelola.pengajuan.store'), [
                'module' => 'koperasi_induk',
                'nama' => 'Koperasi Asli',
                'jenis_koperasi' => $cooperative->jenis_koperasi,
            ])
            ->assertSessionHasErrors('module');

        $this->assertSame(0, Submission::query()->count());
    }

    public function test_store_with_unknown_module_is_rejected(): void
    {
        $cooperative = Cooperative::factory()->create();
        $pengelola = User::factory()->pengelola($cooperative->id)->create();

        $this->actingAs($pengelola)
            ->from(route('pengelola.pengajuan.create'))
            ->post(route('pengelola.pengajuan.store'), [
                'module' => 'modul_asing',
                'nama' => 'Nama Baru',
            ])
            ->assertSessionHasErrors('module');
    }

    public function test_pengajuan_rejects_website_with_non_web_scheme(): void
    {
        $cooperative = Cooperative::factory()->create(['nama' => 'Koperasi Asli']);
        $pengelola = User::factory()->pengelola($cooperative->id)->create();

        // Nilai pengajuan dapat menjadi data resmi (lalu dipublikasikan), sehingga aturan
        // handler modul juga dibatasi ke http/https (Tahap 87).
        foreach (['javascript:alert(1)', 'data://contoh.id/teks', 'ftp://contoh.id/berkas'] as $website) {
            $this->actingAs($pengelola)
                ->from(route('pengelola.pengajuan.create'))
                ->post(route('pengelola.pengajuan.store'), [
                    'module' => 'koperasi_induk',
                    'nama' => 'Koperasi Usulan',
                    'website' => $website,
                ])
                ->assertSessionHasErrors('website');
        }

        $this->assertSame(0, Submission::query()->count());

        // http/https tetap diterima.
        $this->actingAs($pengelola)
            ->post(route('pengelola.pengajuan.store'), [
                'module' => 'koperasi_induk',
                'nama' => 'Koperasi Usulan',
                'jenis_koperasi' => $cooperative->jenis_koperasi,
                'website' => 'https://usulan.example.test',
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame(1, Submission::query()->count());
    }

    public function test_pengelola_can_submit_draft(): void
    {
        $cooperative = Cooperative::factory()->create(['nama' => 'Koperasi Asli']);
        $pengelola = User::factory()->pengelola($cooperative->id)->create();

        $submission = Submission::factory()->create([
            'cooperative_id' => $cooperative->id,
            'submitted_by' => $pengelola->id,
            'status' => 'draft',
        ]);
        SubmissionItem::factory()->create(['submission_id' => $submission->id]);

        $this->actingAs($pengelola)
            ->post(route('pengelola.pengajuan.submit', $submission))
            ->assertRedirect(route('pengelola.pengajuan.show', $submission));

        $this->assertSame('submitted', $submission->fresh()->status);
    }

    public function test_pengelola_creates_a_new_version_to_revise_after_needs_revision(): void
    {
        $cooperative = Cooperative::factory()->create(['nama' => 'Koperasi Asli']);
        $pengelola = User::factory()->pengelola($cooperative->id)->create();

        $parent = Submission::factory()->create([
            'cooperative_id' => $cooperative->id,
            'submitted_by' => $pengelola->id,
            'status' => 'needs_revision',
            'reason' => 'Lengkapi alamat.',
        ]);
        SubmissionItem::factory()->create([
            'submission_id' => $parent->id,
            'field' => 'nama',
            'old_value' => 'Koperasi Asli',
            'new_value' => 'Koperasi Usulan',
        ]);

        // Halaman versi perbaikan menampilkan catatan perbaikan + usulan versi lama (FR-142).
        $this->actingAs($pengelola)
            ->get(route('pengelola.pengajuan.create', ['revision' => $parent->id]))
            ->assertOk()
            ->assertSee('Lengkapi alamat.')
            ->assertSee('Koperasi Usulan');

        $this->actingAs($pengelola)
            ->post(route('pengelola.pengajuan.store'), [
                'module' => 'koperasi_induk',
                'parent_submission_id' => $parent->id,
                'nama' => 'Koperasi Usulan Revisi',
                'jenis_koperasi' => $cooperative->jenis_koperasi,
            ])
            ->assertRedirect();

        $child = Submission::query()->latest('id')->first();

        // Revisi membuat **pengajuan baru** yang tertaut ke versi induk (FR-031/FR-142).
        $this->assertNotSame($parent->id, $child->id);
        $this->assertSame($parent->id, $child->parent_submission_id);
        $this->assertSame('draft', $child->status);
        $this->assertTrue($child->isRevision());

        // Versi lama tetap utuh sebagai riwayat (tidak tertimpa).
        $this->assertSame('needs_revision', $parent->fresh()->status);
        $this->assertSame('Koperasi Usulan', $parent->fresh()->items->first()->new_value);
        $this->assertTrue($parent->fresh()->isSuperseded());

        // Versi baru dapat dikirim untuk verifikasi.
        $this->actingAs($pengelola)
            ->post(route('pengelola.pengajuan.submit', $child))
            ->assertRedirect(route('pengelola.pengajuan.show', $child));

        $this->assertSame('submitted', $child->fresh()->status);
    }

    public function test_pengelola_can_archive_draft(): void
    {
        $cooperative = Cooperative::factory()->create();
        $pengelola = User::factory()->pengelola($cooperative->id)->create();

        $submission = Submission::factory()->create([
            'cooperative_id' => $cooperative->id,
            'submitted_by' => $pengelola->id,
            'status' => 'draft',
        ]);

        $this->actingAs($pengelola)
            ->post(route('pengelola.pengajuan.archive', $submission))
            ->assertRedirect(route('pengelola.pengajuan.index'));

        // F5-10 (L1): arsip = status `archived` + soft delete.
        $archived = Submission::withTrashed()->findOrFail($submission->id);
        $this->assertSame('archived', $archived->status);
        $this->assertNotNull($archived->deleted_at);
        $this->assertNull(Submission::query()->find($submission->id));
    }

    /**
     * F-74-03: `SubmissionTransitionException` (termasuk turunannya) pada jalur revisi harus
     * ditangani sebagai respons validasi — bukan HTTP 500.
     */
    public function test_revision_path_handles_transition_exception_as_validation_error(): void
    {
        $cooperative = Cooperative::factory()->create(['nama' => 'Koperasi Asli']);
        $pengelola = User::factory()->pengelola($cooperative->id)->create();

        $parent = Submission::factory()->create([
            'cooperative_id' => $cooperative->id,
            'submitted_by' => $pengelola->id,
            'status' => 'needs_revision',
            'reason' => 'Lengkapi alamat.',
        ]);

        // Kasus tepi: layanan melempar transisi tak sah (mis. koperasi target tak tersedia) saat
        // transaksi revisi berjalan. Tanpa catch yang diperluas, exception ini lolos → HTTP 500.
        $this->mock(SubmissionService::class, function ($mock) use ($parent): void {
            $mock->shouldReceive('createRevision')
                ->once()
                ->andThrow(SubmissionTransitionException::targetUnavailable($parent));
        });

        $this->actingAs($pengelola)
            ->from(route('pengelola.pengajuan.create', ['revision' => $parent->id]))
            ->post(route('pengelola.pengajuan.store'), [
                'module' => 'koperasi_induk',
                'parent_submission_id' => $parent->id,
                'nama' => 'Koperasi Usulan Revisi',
                'jenis_koperasi' => $cooperative->jenis_koperasi,
            ])
            ->assertRedirect(route('pengelola.pengajuan.create', ['revision' => $parent->id]))
            ->assertSessionHasErrors('module');

        // Tidak ada versi baru yang terbuat; induk tetap utuh.
        $this->assertSame(1, Submission::query()->count());
        $this->assertSame('needs_revision', $parent->fresh()->status);
    }
}
