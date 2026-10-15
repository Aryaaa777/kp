<?php

namespace Tests\Feature\Authorization;

use App\Models\Cooperative;
use App\Models\CooperativeLicense;
use App\Models\Document;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Isolasi data & otorisasi modul perizinan/dokumen (FR-011, FR-051/061, FR-071).
 */
class PerizinanIsolationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake((string) config('koperasi.document_disk', 'local'));
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $cooperative = Cooperative::factory()->create();
        $license = CooperativeLicense::factory()->create(['cooperative_id' => $cooperative->id]);
        $document = Document::factory()->forLicense($license)->create();

        $this->get(route('admin.koperasi.izin.index', $cooperative))->assertRedirect(route('login'));
        $this->get(route('admin.koperasi.izin.create', $cooperative))->assertRedirect(route('login'));
        $this->get(route('admin.koperasi.izin.show', [$cooperative, $license]))->assertRedirect(route('login'));
        $this->get(route('pengelola.izin.index'))->assertRedirect(route('login'));
        $this->get(route('pengelola.izin.show', $license))->assertRedirect(route('login'));
        $this->get(route('dokumen.unduh', $document))->assertRedirect(route('login'));
    }

    public function test_pengelola_sees_only_own_cooperative_licenses(): void
    {
        $own = Cooperative::factory()->create();
        CooperativeLicense::factory()->create(['cooperative_id' => $own->id, 'nomor' => 'IU-MILIK-SAYA']);

        $other = Cooperative::factory()->create();
        CooperativeLicense::factory()->create(['cooperative_id' => $other->id, 'nomor' => 'IU-MILIK-ORANG-LAIN']);

        $user = User::factory()->pengelola($own->id)->create();

        $this->actingAs($user)
            ->get(route('pengelola.izin.index'))
            ->assertOk()
            ->assertSee('IU-MILIK-SAYA')
            ->assertDontSee('IU-MILIK-ORANG-LAIN');
    }

    public function test_pengelola_can_open_own_license_detail(): void
    {
        $own = Cooperative::factory()->create();
        $license = CooperativeLicense::factory()->create([
            'cooperative_id' => $own->id,
            'nomor' => 'IU-PGL-1',
        ]);
        Document::factory()->forLicense($license)->create();
        $user = User::factory()->pengelola($own->id)->create();

        // Pengelola boleh melihat detail izin miliknya (hanya baca, tanpa form unggah/verifikasi).
        $this->actingAs($user)
            ->get(route('pengelola.izin.show', $license))
            ->assertOk()
            ->assertSee('IU-PGL-1')
            ->assertDontSee('Unggah Dokumen')
            ->assertDontSee('Simpan Verifikasi');
    }

    public function test_pengelola_cannot_open_foreign_license_detail(): void
    {
        $own = Cooperative::factory()->create();
        $foreign = Cooperative::factory()->create();
        $foreignLicense = CooperativeLicense::factory()->create(['cooperative_id' => $foreign->id]);

        $user = User::factory()->pengelola($own->id)->create();

        $this->actingAs($user)
            ->get(route('pengelola.izin.show', $foreignLicense))
            ->assertForbidden();
    }

    public function test_pengelola_cannot_access_admin_license_routes(): void
    {
        $cooperative = Cooperative::factory()->create();
        $license = CooperativeLicense::factory()->create(['cooperative_id' => $cooperative->id]);
        $user = User::factory()->pengelola($cooperative->id)->create();

        $this->actingAs($user)->get(route('admin.koperasi.izin.index', $cooperative))->assertForbidden();
        $this->actingAs($user)->get(route('admin.koperasi.izin.create', $cooperative))->assertForbidden();
        $this->actingAs($user)->get(route('admin.koperasi.izin.show', [$cooperative, $license]))->assertForbidden();
        $this->actingAs($user)->post(route('admin.koperasi.izin.store', $cooperative), [])->assertForbidden();
        $this->actingAs($user)->put(route('admin.koperasi.izin.update', [$cooperative, $license]), [])->assertForbidden();
        $this->actingAs($user)->delete(route('admin.koperasi.izin.destroy', [$cooperative, $license]))->assertForbidden();
        $this->actingAs($user)
            ->post(route('admin.koperasi.izin.verify', [$cooperative, $license]), ['status' => 'active'])
            ->assertForbidden();

        $this->assertSame('pending', $license->fresh()->status);
    }

    public function test_pengelola_cannot_upload_document(): void
    {
        $cooperative = Cooperative::factory()->create();
        $license = CooperativeLicense::factory()->create(['cooperative_id' => $cooperative->id]);
        $user = User::factory()->pengelola($cooperative->id)->create();

        $this->actingAs($user)
            ->post(route('admin.koperasi.izin.dokumen.store', [$cooperative, $license]), [
                'category' => 'izin_usaha',
                'file' => UploadedFile::fake()->create('berkas.pdf', 10, 'application/pdf'),
            ])
            ->assertForbidden();

        $this->assertSame(0, Document::count());
    }

    public function test_pengelola_cannot_archive_document(): void
    {
        $cooperative = Cooperative::factory()->create();
        $license = CooperativeLicense::factory()->create(['cooperative_id' => $cooperative->id]);
        $document = Document::factory()->forLicense($license)->create();
        $user = User::factory()->pengelola($cooperative->id)->create();

        $this->actingAs($user)
            ->delete(route('admin.dokumen.destroy', $document))
            ->assertForbidden();

        $this->assertNotSoftDeleted('documents', ['id' => $document->id]);
    }

    public function test_pengelola_without_cooperative_sees_unlinked_page(): void
    {
        $user = User::factory()->pengelola(null)->create();

        $this->actingAs($user)
            ->get(route('pengelola.izin.index'))
            ->assertOk()
            ->assertSee('belum terhubung');
    }

    public function test_policy_matrix_for_license(): void
    {
        $own = Cooperative::factory()->create();
        $foreign = Cooperative::factory()->create();
        $ownLicense = CooperativeLicense::factory()->create(['cooperative_id' => $own->id]);
        $foreignLicense = CooperativeLicense::factory()->create(['cooperative_id' => $foreign->id]);

        $pengelola = User::factory()->pengelola($own->id)->create();
        $admin = User::factory()->admin()->create();

        $this->assertTrue($pengelola->can('view', $ownLicense));
        $this->assertFalse($pengelola->can('view', $foreignLicense));
        $this->assertFalse($pengelola->can('create', CooperativeLicense::class));
        $this->assertFalse($pengelola->can('update', $ownLicense));
        $this->assertFalse($pengelola->can('delete', $ownLicense));
        $this->assertFalse($pengelola->can('verify', $ownLicense));
        $this->assertFalse($pengelola->can('viewAny', CooperativeLicense::class));

        $this->assertTrue($admin->can('viewAny', CooperativeLicense::class));
        $this->assertTrue($admin->can('view', $foreignLicense));
        $this->assertTrue($admin->can('create', CooperativeLicense::class));
        $this->assertTrue($admin->can('update', $foreignLicense));
        $this->assertTrue($admin->can('delete', $foreignLicense));
        $this->assertTrue($admin->can('verify', $foreignLicense));
    }

    public function test_policy_matrix_for_document(): void
    {
        $own = Cooperative::factory()->create();
        $foreign = Cooperative::factory()->create();
        $ownLicense = CooperativeLicense::factory()->create(['cooperative_id' => $own->id]);
        $foreignLicense = CooperativeLicense::factory()->create(['cooperative_id' => $foreign->id]);
        $ownDocument = Document::factory()->forLicense($ownLicense)->create();
        $foreignDocument = Document::factory()->forLicense($foreignLicense)->create();

        $pengelola = User::factory()->pengelola($own->id)->create();
        $admin = User::factory()->admin()->create();

        $this->assertTrue($pengelola->can('view', $ownDocument));
        $this->assertTrue($pengelola->can('download', $ownDocument));
        $this->assertFalse($pengelola->can('download', $foreignDocument));
        $this->assertFalse($pengelola->can('create', Document::class));
        $this->assertFalse($pengelola->can('delete', $ownDocument));

        $this->assertTrue($admin->can('download', $foreignDocument));
        $this->assertTrue($admin->can('create', Document::class));
        $this->assertTrue($admin->can('delete', $foreignDocument));
    }
}
