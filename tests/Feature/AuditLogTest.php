<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Cooperative;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Tests\TestCase;

/**
 * Feature test audit trail (FR-150/151, AC-AUD-01/02).
 *
 * AC-AUD-01: perubahan sensitif mencatat aktor, waktu, entitas, nilai lama → baru.
 * AC-AUD-02: audit log tidak dapat diubah/dihapus dari UI (append-only).
 */
class AuditLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_is_audited(): void
    {
        $user = User::factory()->admin()->create(['email' => 'admin@example.test']);

        $this->post(route('login'), [
            'email' => 'admin@example.test',
            'password' => 'password',
        ]);

        $this->assertDatabaseHas('activity_logs', [
            'action' => 'login',
            'user_id' => $user->id,
        ]);
    }

    public function test_admin_can_view_audit_log_page(): void
    {
        $admin = User::factory()->admin()->create();
        ActivityLog::factory()->create(['action' => 'create_cooperative', 'user_id' => $admin->id]);

        $this->actingAs($admin)
            ->get(route('admin.audit-log.index'))
            ->assertOk()
            ->assertSee('create_cooperative');
    }

    public function test_activity_log_is_append_only_on_update(): void
    {
        $log = ActivityLog::factory()->create();

        $this->expectException(LogicException::class);
        $log->update(['action' => 'diubah']);
    }

    public function test_activity_log_is_append_only_on_delete(): void
    {
        $log = ActivityLog::factory()->create();

        $this->expectException(LogicException::class);
        $log->delete();
    }

    public function test_policy_denies_mutation_of_activity_log(): void
    {
        $admin = User::factory()->admin()->create();
        $log = ActivityLog::factory()->create();

        $this->assertTrue($admin->can('viewAny', ActivityLog::class));
        $this->assertFalse($admin->can('update', $log));
        $this->assertFalse($admin->can('delete', $log));
        $this->assertFalse($admin->can('create', ActivityLog::class));
    }

    public function test_cooperative_update_is_audited(): void
    {
        $admin = User::factory()->admin()->create();
        $cooperative = Cooperative::factory()->create(['nama' => 'Nama Awal']);

        $this->actingAs($admin)->put(route('admin.koperasi.update', $cooperative), [
            'nama' => 'Nama Diubah',
            'nomor_identitas' => $cooperative->nomor_identitas,
            'jenis_koperasi' => $cooperative->jenis_koperasi,
            'bentuk_koperasi' => $cooperative->bentuk_koperasi,
            'tanggal_berdiri' => optional($cooperative->tanggal_berdiri)->format('Y-m-d'),
            'alamat' => $cooperative->alamat,
            'telephone' => $cooperative->telephone,
            'email' => $cooperative->email,
            'website' => $cooperative->website,
            'status_verifikasi' => $cooperative->status_verifikasi,
        ]);

        $log = ActivityLog::query()->where('action', 'update_cooperative')->first();

        $this->assertNotNull($log);
        $this->assertSame('Nama Awal', $log->properties['before']['nama']);
        $this->assertSame('Nama Diubah', $log->properties['after']['nama']);
    }

    public function test_submission_creation_is_audited(): void
    {
        $cooperative = Cooperative::factory()->create(['nama' => 'Koperasi Asli']);
        $pengelola = User::factory()->pengelola($cooperative->id)->create();

        $this->actingAs($pengelola)->post(route('pengelola.pengajuan.store'), [
            'module' => 'koperasi_induk',
            'nama' => 'Koperasi Usulan',
            'jenis_koperasi' => $cooperative->jenis_koperasi,
        ]);

        $submission = Submission::query()->latest('id')->first();

        $this->assertDatabaseHas('activity_logs', [
            'action' => 'create_submission',
            'subject_type' => Submission::class,
            'subject_id' => $submission->id,
            'user_id' => $pengelola->id,
        ]);
    }
}
