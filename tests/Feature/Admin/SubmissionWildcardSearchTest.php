<?php

namespace Tests\Feature\Admin;

use App\Models\Cooperative;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tes regresi wildcard escaping untuk pencarian Submission (Tahap 103, GAP-03/04).
 *
 * Memastikan karakter `%`, `_`, dan `!` diperlakukan sebagai literal dalam:
 * - Pencarian kolom `title` milik submission.
 * - Pencarian relasi `cooperative.nama` dan `cooperative.nomor_identitas`.
 */
class SubmissionWildcardSearchTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->admin()->create();
    }

    public function test_search_treats_percent_literal_in_submission_title(): void
    {
        $cooperative = Cooperative::factory()->create(['nama' => 'Koperasi Uji']);

        Submission::factory()->create([
            'cooperative_id' => $cooperative->id,
            'title' => 'Pengajuan% perubahan data',
            'status' => 'submitted',
        ]);
        Submission::factory()->create([
            'cooperative_id' => $cooperative->id,
            'title' => 'Pengajuan perubahan data',
            'status' => 'submitted',
        ]);

        // Search "Pengajuan%" → hanya cocok literal, bukan wildcard
        $response = $this->actingAs($this->admin())
            ->get(route('admin.pengajuan.index', ['q' => 'Pengajuan%']))
            ->assertOk();

        $response->assertSee('Pengajuan% perubahan data');
        $response->assertDontSee('Pengajuan perubahan data');
    }

    public function test_search_treats_underscore_literal_in_submission_title(): void
    {
        $cooperative = Cooperative::factory()->create(['nama' => 'Koperasi Uji']);

        Submission::factory()->create([
            'cooperative_id' => $cooperative->id,
            'title' => 'Pengajuan_ perubahan data',
            'status' => 'submitted',
        ]);
        Submission::factory()->create([
            'cooperative_id' => $cooperative->id,
            'title' => 'Pengajuan perubahan data',
            'status' => 'submitted',
        ]);

        // Search "Pengajuan_" → hanya cocok literal
        $response = $this->actingAs($this->admin())
            ->get(route('admin.pengajuan.index', ['q' => 'Pengajuan_']))
            ->assertOk();

        $response->assertSee('Pengajuan_ perubahan data');
        $response->assertDontSee('Pengajuan perubahan data');
    }

    public function test_search_treats_exclamation_literal_in_submission_title(): void
    {
        $cooperative = Cooperative::factory()->create(['nama' => 'Koperasi Uji']);

        Submission::factory()->create([
            'cooperative_id' => $cooperative->id,
            'title' => 'Pengajuan! data penting',
            'status' => 'submitted',
        ]);
        Submission::factory()->create([
            'cooperative_id' => $cooperative->id,
            'title' => 'Pengajuan data penting',
            'status' => 'submitted',
        ]);

        // Search "Pengajuan!" → hanya cocok literal
        $response = $this->actingAs($this->admin())
            ->get(route('admin.pengajuan.index', ['q' => 'Pengajuan!']))
            ->assertOk();

        $response->assertSee('Pengajuan! data penting');
        $response->assertDontSee('Pengajuan data penting');
    }

    public function test_search_treats_percent_literal_in_cooperative_nama(): void
    {
        $coop1 = Cooperative::factory()->create(['nama' => 'Koperasi%Khusus']);
        $coop2 = Cooperative::factory()->create(['nama' => 'Koperasi Biasa']);

        Submission::factory()->create([
            'cooperative_id' => $coop1->id,
            'title' => 'Pengajuan A',
            'status' => 'submitted',
        ]);
        Submission::factory()->create([
            'cooperative_id' => $coop2->id,
            'title' => 'Pengajuan B',
            'status' => 'submitted',
        ]);

        // Search "Koperasi%" → hanya cocok cooperative dengan nama literal "Koperasi%Khusus"
        $response = $this->actingAs($this->admin())
            ->get(route('admin.pengajuan.index', ['q' => 'Koperasi%']))
            ->assertOk();

        $response->assertSee('Pengajuan A');
        $response->assertDontSee('Pengajuan B');
    }

    public function test_search_treats_underscore_literal_in_cooperative_nama(): void
    {
        $coop1 = Cooperative::factory()->create(['nama' => 'Koperasi_Besar']);
        $coop2 = Cooperative::factory()->create(['nama' => 'Koperasi Besar']);

        Submission::factory()->create([
            'cooperative_id' => $coop1->id,
            'title' => 'Pengajuan X',
            'status' => 'submitted',
        ]);
        Submission::factory()->create([
            'cooperative_id' => $coop2->id,
            'title' => 'Pengajuan Y',
            'status' => 'submitted',
        ]);

        // Search "Koperasi_" → hanya cocok literal
        $response = $this->actingAs($this->admin())
            ->get(route('admin.pengajuan.index', ['q' => 'Koperasi_']))
            ->assertOk();

        $response->assertSee('Pengajuan X');
        $response->assertDontSee('Pengajuan Y');
    }

    public function test_search_treats_percent_literal_in_cooperative_nomor_identitas(): void
    {
        $coop1 = Cooperative::factory()->create(['nomor_identitas' => 'KOP%123']);
        $coop2 = Cooperative::factory()->create(['nomor_identitas' => 'KOP456']);

        Submission::factory()->create([
            'cooperative_id' => $coop1->id,
            'title' => 'Perubahan 1',
            'status' => 'submitted',
        ]);
        Submission::factory()->create([
            'cooperative_id' => $coop2->id,
            'title' => 'Perubahan 2',
            'status' => 'submitted',
        ]);

        // Search "KOP%" → hanya cocok literal nomor_identitas
        $response = $this->actingAs($this->admin())
            ->get(route('admin.pengajuan.index', ['q' => 'KOP%']))
            ->assertOk();

        $response->assertSee('Perubahan 1');
        $response->assertDontSee('Perubahan 2');
    }

    public function test_search_normal_text_in_submission_still_works(): void
    {
        $cooperative = Cooperative::factory()->create(['nama' => 'Koperasi Uji']);

        Submission::factory()->create([
            'cooperative_id' => $cooperative->id,
            'title' => 'Pengajuan perubahan nama',
            'status' => 'submitted',
        ]);
        Submission::factory()->create([
            'cooperative_id' => $cooperative->id,
            'title' => 'Pengajuan perubahan alamat',
            'status' => 'submitted',
        ]);

        // Normal search tanpa special char tetap bekerja
        $response = $this->actingAs($this->admin())
            ->get(route('admin.pengajuan.index', ['q' => 'nama']))
            ->assertOk();

        $response->assertSee('Pengajuan perubahan nama');
        $response->assertDontSee('Pengajuan perubahan alamat');
    }

    public function test_search_with_status_filter_still_works(): void
    {
        $cooperative = Cooperative::factory()->create(['nama' => 'Koperasi Uji']);

        Submission::factory()->create([
            'cooperative_id' => $cooperative->id,
            'title' => 'Pengajuan submitted',
            'status' => 'submitted',
        ]);
        Submission::factory()->create([
            'cooperative_id' => $cooperative->id,
            'title' => 'Pengajuan draft',
            'status' => 'draft',
        ]);

        // Pencarian + filter status tetap bekerja
        $response = $this->actingAs($this->admin())
            ->get(route('admin.pengajuan.index', [
                'q' => 'Pengajuan',
                'status' => 'submitted',
            ]))
            ->assertOk();

        $response->assertSee('Pengajuan submitted');
        $response->assertDontSee('Pengajuan draft');
    }
}
