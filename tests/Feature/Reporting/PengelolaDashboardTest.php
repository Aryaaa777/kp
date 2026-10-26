<?php

namespace Tests\Feature\Reporting;

use App\Http\Requests\Cooperative\StoreCooperativeRequest;
use App\Models\Cooperative;
use App\Models\CooperativeLicense;
use App\Models\Submission;
use App\Models\User;
use App\Services\Reporting\CompletenessReport;
use App\Services\SubmissionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Dashboard pengelola koperasi (F6): kelengkapan FR-030 & permintaan perbaikan FR-031.
 *
 * Isolasi data diuji secara eksplisit: pengelola tidak boleh melihat pengajuan koperasi lain,
 * dan akun tanpa koperasi tidak boleh menghasilkan HTTP 500.
 */
class PengelolaDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_pengelola_required_fields_are_the_intersection_of_form_rules_and_whitelist(): void
    {
        $rules = (new StoreCooperativeRequest)->rules();

        $requiredFromForm = [];

        foreach ($rules as $field => $fieldRules) {
            if (in_array('required', $fieldRules, true)) {
                $requiredFromForm[] = $field;
            }
        }

        // Field wajib wewenang pengelola = field wajib form koperasi ∩ whitelist pengajuan (Q-006).
        $expected = array_values(array_intersect($requiredFromForm, array_keys(CompletenessReport::pengelolaFields())));
        sort($expected);

        $documented = CompletenessReport::pengelolaRequiredFields();
        sort($documented);

        $this->assertSame($expected, $documented);

        // `nomor_identitas` & `status_verifikasi` wajib pada form admin, tetapi BUKAN wewenang
        // pengelola (handler `koperasi_induk`) — karena itu tidak masuk perhitungan pengelola.
        $this->assertSame(['jenis_koperasi', 'nama'], $documented);
    }

    public function test_completeness_percentage_follows_documented_formula(): void
    {
        $cooperative = Cooperative::factory()->create([
            'nama' => 'Koperasi Lengkap',
            'jenis_koperasi' => 'Konsumen',
            'bentuk_koperasi' => null,
            'tanggal_berdiri' => null,
            'alamat' => null,
            'telephone' => null,
            'email' => null,
            'website' => null,
        ]);
        $user = User::factory()->pengelola($cooperative->id)->create();

        $fieldCount = count(CompletenessReport::pengelolaFields());

        $completeness = $this->actingAs($user)->get(route('pengelola.dashboard'))->assertOk()->viewData('completeness');

        // Denominator = field wewenang pengelola (bukan 7 modul berbobot setara).
        $this->assertSame($fieldCount, $completeness['total']);
        $this->assertSame(2, $completeness['filled']);
        $this->assertSame((int) round(2 / $fieldCount * 100), $completeness['percentage']);
        $this->assertSame(2, $completeness['required_filled']);
        $this->assertSame(2, $completeness['required_total']);
        $this->assertFalse($completeness['modules'][0]['complete']);

        // Mengisi sebagian field opsional menaikkan persentase.
        $cooperative->update(['alamat' => 'Jl. Contoh 1', 'email' => 'kop@contoh.id']);
        // Relasi `koperasi` di instance user yang sama masih tersimpan dari request sebelumnya.
        $user->unsetRelation('koperasi');

        $after = $this->actingAs($user)->get(route('pengelola.dashboard'))->assertOk()->viewData('completeness');

        $this->assertSame(4, $after['filled']);
        $this->assertSame((int) round(4 / $fieldCount * 100), $after['percentage']);
        $this->assertSame('4/'.$fieldCount.' field profil terisi', $after['modules'][0]['detail']);
        $this->assertFalse($after['modules'][0]['complete']);

        // Modul kewenangan admin dinas hanya dilaporkan (informasi), tidak masuk persentase.
        $this->assertCount(count(CompletenessReport::ADMIN_MODULES), $after['read_only']);

        $adminModules = collect($after['read_only'])->keyBy('key');

        $this->assertFalse($adminModules['kesehatan']['complete']);
        $this->assertStringContainsString('dikelola admin dinas', $adminModules['kesehatan']['detail']);

        CooperativeLicense::factory()->create(['cooperative_id' => $cooperative->id]);
        $user->unsetRelation('koperasi');

        $withLicense = $this->actingAs($user)->get(route('pengelola.dashboard'))->assertOk()->viewData('completeness');

        $this->assertTrue(collect($withLicense['read_only'])->keyBy('key')['perizinan']['complete']);
        // Menambah catatan kewenangan admin TIDAK mengubah persentase pengelola.
        $this->assertSame($after['percentage'], $withLicense['percentage']);
    }

    public function test_completeness_indicator_is_rendered_with_links_to_own_modules(): void
    {
        $cooperative = Cooperative::factory()->create();
        $user = User::factory()->pengelola($cooperative->id)->create();

        $this->actingAs($user)
            ->get(route('pengelola.dashboard'))
            ->assertOk()
            ->assertSee('Kelengkapan Data Profil Koperasi')
            ->assertSee(route('pengelola.profil'), false)
            ->assertSee(route('pengelola.izin.index'), false)
            ->assertSee(route('pengelola.anggota.index'), false)
            ->assertSee('dikelola admin dinas');
    }

    public function test_revision_and_rejection_requests_are_listed_with_reason_and_actions(): void
    {
        $cooperative = Cooperative::factory()->create();
        $user = User::factory()->pengelola($cooperative->id)->create();

        $needsRevision = Submission::factory()->create([
            'cooperative_id' => $cooperative->id,
            'submitted_by' => $user->id,
            'status' => 'needs_revision',
            'reason' => 'Nomor identitas tidak sesuai dokumen',
            'title' => 'Perubahan Alamat',
        ]);

        $rejected = Submission::factory()->create([
            'cooperative_id' => $cooperative->id,
            'submitted_by' => $user->id,
            'status' => 'rejected',
            'reason' => 'Dokumen pendukung tidak lengkap',
            'title' => 'Perubahan Telepon',
        ]);

        Submission::factory()->create([
            'cooperative_id' => $cooperative->id,
            'submitted_by' => $user->id,
            'status' => 'approved',
        ]);

        $response = $this->actingAs($user)->get(route('pengelola.dashboard'))->assertOk();

        $this->assertEqualsCanonicalizing(
            [$needsRevision->id, $rejected->id],
            $response->viewData('revisionRequests')->pluck('id')->all(),
        );

        $response->assertSee('Nomor identitas tidak sesuai dokumen');
        $response->assertSee('Dokumen pendukung tidak lengkap');

        // Aksi valid per status: `needs_revision` dapat dibuatkan versi perbaikan,
        // `rejected` bersifat terminal sehingga tidak punya aksi perbaikan.
        $response->assertSee(route('pengelola.pengajuan.create', ['revision' => $needsRevision->id]), false);
        $response->assertDontSee(route('pengelola.pengajuan.create', ['revision' => $rejected->id]), false);

        $this->assertTrue($needsRevision->isRevisable());
        $this->assertFalse($needsRevision->isEditable());
        $this->assertFalse($rejected->isEditable());

        $response->assertSee(route('pengelola.pengajuan.show', $rejected), false);
    }

    /**
     * S-1: kartu "Butuh Perbaikan" memakai definisi actionable yang **sama** dengan daftar:
     * `needs_revision` yang belum digantikan versi turunan + `rejected`. Versi turunan `draft`
     * tidak menambah angka, dan mengarsipkannya membuat induk actionable lagi.
     */
    public function test_revision_card_count_uses_the_same_actionable_definition_as_the_list(): void
    {
        $cooperative = Cooperative::factory()->create();
        $user = User::factory()->pengelola($cooperative->id)->create();
        $admin = User::factory()->admin()->create();

        $service = app(SubmissionService::class);

        // (1) Revisi diminta → pengajuan menjadi `needs_revision` (actionable).
        $inReview = Submission::factory()->underReview()->create([
            'cooperative_id' => $cooperative->id,
            'submitted_by' => $user->id,
        ]);
        $service->requestRevision($inReview, $admin, 'Lengkapi alamat.');

        // Penolakan bersifat terminal tetapi tetap actionable (FR-031).
        Submission::factory()->create([
            'cooperative_id' => $cooperative->id,
            'submitted_by' => $user->id,
            'status' => 'rejected',
        ]);

        // Status terminal lain tidak dihitung.
        Submission::factory()->create([
            'cooperative_id' => $cooperative->id,
            'submitted_by' => $user->id,
            'status' => 'approved',
        ]);

        $before = $this->actingAs($user)->get(route('pengelola.dashboard'))->assertOk();
        $this->assertSame(2, $before->viewData('revisionCount'));
        $this->assertSame(2, $before->viewData('revisionRequests')->count());

        // (2)+(3) Versi perbaikan dibuat & masih `draft` → induk tidak lagi actionable, dan draft
        // turunan (draft biasa) juga bukan "butuh perbaikan".
        $child = $service->createRevision($user, $inReview->fresh(), ['nama' => 'Koperasi Revisi']);
        $this->assertSame('draft', $child->status);

        $afterChild = $this->actingAs($user)->get(route('pengelola.dashboard'))->assertOk();
        $this->assertSame(1, $afterChild->viewData('revisionCount')); // hanya `rejected`
        $this->assertSame(1, $afterChild->viewData('revisionRequests')->count());

        // (4) Draft turunan diarsipkan → induk kembali dapat diperbaiki & dihitung lagi.
        $service->archive($child, $user);

        $afterArchive = $this->actingAs($user)->get(route('pengelola.dashboard'))->assertOk();
        $this->assertSame(2, $afterArchive->viewData('revisionCount'));
        $this->assertTrue($inReview->fresh()->isRevisable());
    }

    public function test_latest_submission_status_is_shown_for_own_cooperative(): void
    {
        $cooperative = Cooperative::factory()->create();
        $user = User::factory()->pengelola($cooperative->id)->create();

        $older = Submission::factory()->create([
            'cooperative_id' => $cooperative->id,
            'submitted_by' => $user->id,
            'status' => 'draft',
            'title' => 'Pengajuan Lama',
        ]);
        $older->forceFill(['updated_at' => now()->subDay()])->save();

        $latest = Submission::factory()->submitted()->create([
            'cooperative_id' => $cooperative->id,
            'submitted_by' => $user->id,
            'title' => 'Pengajuan Terbaru',
        ]);
        $latest->forceFill(['updated_at' => now()])->save();

        $response = $this->actingAs($user)->get(route('pengelola.dashboard'))->assertOk();

        $this->assertSame($latest->id, $response->viewData('latestSubmission')->id);
        $response->assertSee('Pengajuan Terbaru');
        $response->assertSee(route('pengelola.pengajuan.show', $latest), false);
    }

    public function test_pengelola_cannot_see_other_cooperative_private_data(): void
    {
        $own = Cooperative::factory()->create();
        $other = Cooperative::factory()->create();

        $user = User::factory()->pengelola($own->id)->create();

        Submission::factory()->create([
            'cooperative_id' => $other->id,
            'status' => 'needs_revision',
            'reason' => 'ALASAN-KOPERASI-LAIN',
            'title' => 'JUDUL-KOPERASI-LAIN',
        ]);

        CooperativeLicense::factory()->past()->create(['cooperative_id' => $other->id]);

        $response = $this->actingAs($user)->get(route('pengelola.dashboard'))->assertOk();

        $this->assertCount(0, $response->viewData('revisionRequests'));
        $this->assertNull($response->viewData('latestSubmission'));

        $response->assertDontSee('ALASAN-KOPERASI-LAIN');
        $response->assertDontSee('JUDUL-KOPERASI-LAIN');

        // Statistik koperasi lain tidak boleh muncul (indikator kelengkapan milik koperasi sendiri).
        $this->assertSame(
            count(CompletenessReport::pengelolaFields()),
            $response->viewData('completeness')['total'],
        );
    }

    public function test_pengelola_without_linked_cooperative_does_not_fail(): void
    {
        $user = User::factory()->pengelola(null)->create();

        $this->actingAs($user)
            ->get(route('pengelola.dashboard'))
            ->assertOk()
            ->assertViewHas('completeness', null)
            ->assertViewHas('cooperative', null)
            ->assertSee('belum ditautkan');
    }

    public function test_archived_cooperative_does_not_fail_and_shows_no_data(): void
    {
        $cooperative = Cooperative::factory()->create();
        $user = User::factory()->pengelola($cooperative->id)->create();

        $cooperative->update(['status_verifikasi' => 'archived']);
        $cooperative->delete();

        $this->actingAs($user)
            ->get(route('pengelola.dashboard'))
            ->assertOk()
            ->assertViewHas('completeness', null)
            ->assertSee('belum ditautkan');
    }

    public function test_guest_is_redirected_and_admin_is_forbidden(): void
    {
        $this->get(route('pengelola.dashboard'))->assertRedirect(route('login'));

        $cooperative = Cooperative::factory()->create();
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get(route('pengelola.dashboard'))->assertForbidden();

        $this->assertTrue($cooperative->exists());
    }
}
