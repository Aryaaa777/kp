<?php

namespace Tests\Feature;

use App\Models\Cooperative;
use App\Models\Submission;
use App\Models\SubmissionItem;
use App\Models\User;
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

    public function test_pengelola_can_revise_and_resubmit_after_needs_revision(): void
    {
        $cooperative = Cooperative::factory()->create(['nama' => 'Koperasi Asli']);
        $pengelola = User::factory()->pengelola($cooperative->id)->create();

        $submission = Submission::factory()->create([
            'cooperative_id' => $cooperative->id,
            'submitted_by' => $pengelola->id,
            'status' => 'needs_revision',
            'reason' => 'Lengkapi alamat.',
        ]);
        SubmissionItem::factory()->create([
            'submission_id' => $submission->id,
            'field' => 'nama',
            'old_value' => 'Koperasi Asli',
            'new_value' => 'Koperasi Usulan',
        ]);

        // Halaman edit menampilkan catatan perbaikan (FR-142).
        $this->actingAs($pengelola)
            ->get(route('pengelola.pengajuan.edit', $submission))
            ->assertOk()
            ->assertSee('Lengkapi alamat.');

        $this->actingAs($pengelola)
            ->put(route('pengelola.pengajuan.update', $submission), [
                'nama' => 'Koperasi Usulan Revisi',
                'jenis_koperasi' => $cooperative->jenis_koperasi,
            ])
            ->assertRedirect(route('pengelola.pengajuan.show', $submission));

        $this->assertSame('Koperasi Usulan Revisi', $submission->fresh()->items->first()->new_value);

        $this->actingAs($pengelola)
            ->post(route('pengelola.pengajuan.submit', $submission))
            ->assertRedirect(route('pengelola.pengajuan.show', $submission));

        $this->assertSame('submitted', $submission->fresh()->status);
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
}
