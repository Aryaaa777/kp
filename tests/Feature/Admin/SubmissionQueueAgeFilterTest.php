<?php

namespace Tests\Feature\Admin;

use App\Models\Cooperative;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Filter "umur pengajuan" pada antrian verifikasi admin (FR-021, melengkapi F6).
 *
 * Dasar waktu = `updated_at` — kolom waktu yang sudah dipakai antrian (pengurutan & kolom
 * "Diperbarui"), bukan `created_at`. Ambang berupa angka hari yang diisi admin dan **inklusif**
 * pada batas; FR-021 tidak menetapkan kategori umur resmi (lihat docs/DECISIONS.md §16).
 *
 * Filter diterapkan di query server-side dan harus dapat dikombinasikan dengan `status`/`q`
 * serta dipertahankan saat berpindah halaman.
 */
class SubmissionQueueAgeFilterTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->admin()->create();
    }

    public function test_age_filter_narrows_the_queue_by_updated_at(): void
    {
        $this->travelTo(Carbon::parse('2026-06-15 09:00:00'));

        Submission::factory()->submitted()->create([
            'title' => 'LAMA-SEKALI',
            'updated_at' => now()->copy()->subDays(10),
        ]);
        Submission::factory()->submitted()->create([
            'title' => 'BARU-SEKALI',
            'updated_at' => now()->copy()->subDays(1),
        ]);

        $response = $this->actingAs($this->admin())
            ->get(route('admin.pengajuan.index', ['umur_min' => 5]))
            ->assertOk();

        $this->assertSame(1, $response->viewData('submissions')->total());
        $response->assertSee('LAMA-SEKALI');
        $response->assertDontSee('BARU-SEKALI');
        $this->assertSame('5', $response->viewData('filters')['umur_min']);
    }

    public function test_age_filter_uses_updated_at_not_created_at(): void
    {
        $this->travelTo(Carbon::parse('2026-06-15 09:00:00'));

        // Dibuat lama tetapi aktivitas terakhir baru → TIDAK dihitung berumur.
        Submission::factory()->submitted()->create([
            'title' => 'DIBUAT-LAMA-DIPERBARUI-BARU',
            'created_at' => now()->copy()->subDays(30),
            'updated_at' => now()->copy()->subDays(1),
        ]);

        // Dibuat baru tetapi aktivitas terakhir lama → dihitung berumur.
        Submission::factory()->submitted()->create([
            'title' => 'DIBUAT-BARU-DIPERBARUI-LAMA',
            'created_at' => now()->copy()->subDays(1),
            'updated_at' => now()->copy()->subDays(30),
        ]);

        $response = $this->actingAs($this->admin())
            ->get(route('admin.pengajuan.index', ['umur_min' => 5]))
            ->assertOk();

        $this->assertSame(1, $response->viewData('submissions')->total());
        $response->assertSee('DIBUAT-BARU-DIPERBARUI-LAMA');
        $response->assertDontSee('DIBUAT-LAMA-DIPERBARUI-BARU');
    }

    public function test_age_range_boundaries_are_inclusive(): void
    {
        $this->travelTo(Carbon::parse('2026-06-15 09:00:00'));

        // Umur tepat 5 hari (batas), 5 hari + 1 detik, dan 5 hari - 1 detik.
        Submission::factory()->submitted()->create([
            'title' => 'UMUR-TEPAT-5',
            'updated_at' => now()->copy()->subDays(5),
        ]);
        Submission::factory()->submitted()->create([
            'title' => 'UMUR-LEBIH-5',
            'updated_at' => now()->copy()->subDays(5)->subSecond(),
        ]);
        Submission::factory()->submitted()->create([
            'title' => 'UMUR-KURANG-5',
            'updated_at' => now()->copy()->subDays(5)->addSecond(),
        ]);

        // Batas bawah (`umur_min`) inklusif: tepat 5 hari ikut, kurang dari 5 hari tidak.
        $min = $this->actingAs($this->admin())
            ->get(route('admin.pengajuan.index', ['umur_min' => 5]))
            ->assertOk();

        $this->assertSame(2, $min->viewData('submissions')->total());
        $min->assertSee('UMUR-TEPAT-5');
        $min->assertSee('UMUR-LEBIH-5');
        $min->assertDontSee('UMUR-KURANG-5');

        // Batas atas (`umur_max`) inklusif: tepat 5 hari ikut, lebih dari 5 hari tidak.
        $max = $this->actingAs($this->admin())
            ->get(route('admin.pengajuan.index', ['umur_max' => 5]))
            ->assertOk();

        $this->assertSame(2, $max->viewData('submissions')->total());
        $max->assertSee('UMUR-TEPAT-5');
        $max->assertSee('UMUR-KURANG-5');
        $max->assertDontSee('UMUR-LEBIH-5');
    }

    public function test_age_filter_combines_with_status(): void
    {
        $this->travelTo(Carbon::parse('2026-06-15 09:00:00'));

        Submission::factory()->submitted()->create([
            'title' => 'LAMA-SUBMITTED',
            'updated_at' => now()->copy()->subDays(10),
        ]);
        Submission::factory()->approved()->create([
            'title' => 'LAMA-APPROVED',
            'updated_at' => now()->copy()->subDays(10),
        ]);
        Submission::factory()->submitted()->create([
            'title' => 'BARU-SUBMITTED',
            'updated_at' => now()->copy()->subDays(1),
        ]);

        $response = $this->actingAs($this->admin())
            ->get(route('admin.pengajuan.index', ['status' => 'submitted', 'umur_min' => 5]))
            ->assertOk();

        $this->assertSame(1, $response->viewData('submissions')->total());
        $response->assertSee('LAMA-SUBMITTED');
        $response->assertDontSee('LAMA-APPROVED');
        $response->assertDontSee('BARU-SUBMITTED');
    }

    public function test_age_filter_combines_with_search(): void
    {
        $this->travelTo(Carbon::parse('2026-06-15 09:00:00'));

        $melati = Cooperative::factory()->create(['nama' => 'Koperasi Melati']);
        $lain = Cooperative::factory()->create(['nama' => 'Koperasi Lain']);

        Submission::factory()->submitted()->create([
            'cooperative_id' => $melati->id,
            'title' => 'Pengajuan A',
            'updated_at' => now()->copy()->subDays(10),
        ]);
        Submission::factory()->submitted()->create([
            'cooperative_id' => $melati->id,
            'title' => 'Pengajuan B',
            'updated_at' => now()->copy()->subDays(1),
        ]);
        Submission::factory()->submitted()->create([
            'cooperative_id' => $lain->id,
            'title' => 'Pengajuan C',
            'updated_at' => now()->copy()->subDays(10),
        ]);

        $response = $this->actingAs($this->admin())
            ->get(route('admin.pengajuan.index', ['q' => 'Melati', 'umur_min' => 5]))
            ->assertOk();

        $this->assertSame(1, $response->viewData('submissions')->total());
        $response->assertSee('Pengajuan A');
        $response->assertDontSee('Pengajuan B');
        $response->assertDontSee('Pengajuan C');
    }

    public function test_submissions_outside_the_age_range_are_excluded(): void
    {
        $this->travelTo(Carbon::parse('2026-06-15 09:00:00'));

        Submission::factory()->submitted()->create([
            'title' => 'TERLALU-LAMA',
            'updated_at' => now()->copy()->subDays(10),
        ]);
        Submission::factory()->submitted()->create([
            'title' => 'DALAM-RENTANG',
            'updated_at' => now()->copy()->subDays(3),
        ]);
        Submission::factory()->submitted()->create([
            'title' => 'TERLALU-BARU',
            'updated_at' => now()->copy()->subDays(1),
        ]);

        $response = $this->actingAs($this->admin())
            ->get(route('admin.pengajuan.index', ['umur_min' => 2, 'umur_max' => 7]))
            ->assertOk();

        $this->assertSame(1, $response->viewData('submissions')->total());
        $response->assertSee('DALAM-RENTANG');
        $response->assertDontSee('TERLALU-LAMA');
        $response->assertDontSee('TERLALU-BARU');
    }

    public function test_age_filter_is_preserved_across_pagination(): void
    {
        $this->travelTo(Carbon::parse('2026-06-15 09:00:00'));

        Submission::factory()->submitted()->count(16)->create([
            'updated_at' => now()->copy()->subDays(10),
        ]);

        $response = $this->actingAs($this->admin())
            ->get(route('admin.pengajuan.index', ['umur_min' => 5]))
            ->assertOk();

        $paginator = $response->viewData('submissions');

        $this->assertSame(16, $paginator->total());
        $this->assertSame(15, $paginator->count());
        $this->assertTrue($paginator->hasPages());

        // Tautan pagination mempertahankan filter yang aktif.
        $response->assertSee('umur_min=5', false);
        $response->assertSee('page=2', false);
    }

    public function test_invalid_age_filter_values_are_ignored(): void
    {
        $this->travelTo(Carbon::parse('2026-06-15 09:00:00'));

        Submission::factory()->submitted()->count(3)->create([
            'updated_at' => now()->copy()->subDays(10),
        ]);

        foreach (['abc', '-5', '1.5', ''] as $invalid) {
            $response = $this->actingAs($this->admin())
                ->get(route('admin.pengajuan.index', ['umur_min' => $invalid]))
                ->assertOk();

            $this->assertSame(3, $response->viewData('submissions')->total());
            $this->assertSame('', $response->viewData('filters')['umur_min']);
        }
    }

    public function test_age_filter_keeps_existing_authorization(): void
    {
        $this->get(route('admin.pengajuan.index', ['umur_min' => 5]))
            ->assertRedirect(route('login'));

        $cooperative = Cooperative::factory()->create();
        $pengelola = User::factory()->pengelola($cooperative->id)->create();

        $this->actingAs($pengelola)
            ->get(route('admin.pengajuan.index', ['umur_min' => 5]))
            ->assertForbidden();

        $this->actingAs($this->admin())
            ->get(route('admin.pengajuan.index', ['umur_min' => 5]))
            ->assertOk();
    }
}
