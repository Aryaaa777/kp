<?php

namespace Tests\Feature\Admin;

use App\Models\Cooperative;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Status operasional koperasi (keputusan pemilik proyek — Tahap 106).
 *
 * Tiga nilai resmi: `aktif`, `tidak_aktif`, `bubar`. Kolom bersifat **nullable tanpa default**
 * sehingga data lama yang belum dikonfirmasi tetap `NULL` — sistem tidak menganggap semua
 * koperasi aktif dan tidak mengarang nilai.
 *
 * Catatan bukti: suite berjalan pada **SQLite `:memory:`**. Kelulusan di sini **bukan** bukti
 * kompatibilitas MariaDB/MySQL.
 */
class CooperativeStatusOperasionalTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->admin()->create();
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'nama' => 'Koperasi Status',
            'nomor_identitas' => 'KOP-STATUS-1',
            'jenis_koperasi' => 'Koperasi Uji',
            'bentuk_koperasi' => null,
            'tanggal_berdiri' => '2010-05-01',
            'region_id' => null,
            'alamat' => 'Jl. Contoh No. 1',
            'telephone' => '081234567890',
            'email' => 'status@example.test',
            'website' => null,
            'status_verifikasi' => 'draft',
            'status_operasional' => 'aktif',
        ], $overrides);
    }

    public function test_admin_can_store_each_approved_operational_status(): void
    {
        $admin = $this->admin();

        foreach (Cooperative::STATUS_OPERASIONAL as $index => $status) {
            $this->actingAs($admin)
                ->post(route('admin.koperasi.store'), $this->payload([
                    'nama' => 'Koperasi '.$status,
                    'nomor_identitas' => 'KOP-STATUS-'.$index,
                    'status_operasional' => $status,
                ]))
                ->assertSessionHasNoErrors();

            $this->assertDatabaseHas('cooperatives', [
                'nomor_identitas' => 'KOP-STATUS-'.$index,
                'status_operasional' => $status,
            ]);
        }
    }

    public function test_store_requires_operational_status(): void
    {
        $this->actingAs($this->admin())
            ->from(route('admin.koperasi.create'))
            ->post(route('admin.koperasi.store'), $this->payload(['status_operasional' => '']))
            ->assertSessionHasErrors('status_operasional');

        $this->assertSame(0, Cooperative::query()->count());
    }

    public function test_store_rejects_unknown_operational_status(): void
    {
        $this->actingAs($this->admin())
            ->from(route('admin.koperasi.create'))
            ->post(route('admin.koperasi.store'), $this->payload(['status_operasional' => 'pailit']))
            ->assertSessionHasErrors('status_operasional');

        $this->assertSame(0, Cooperative::query()->count());
    }

    public function test_update_allows_filling_legacy_null_status_without_forcing_it(): void
    {
        $legacy = Cooperative::factory()->create([
            'nama' => 'Koperasi Lama',
            'status_operasional' => null,
        ]);
        $admin = $this->admin();

        // Edit field lain tanpa mengisi status → tetap NULL, tidak dikarang.
        $this->actingAs($admin)
            ->put(route('admin.koperasi.update', $legacy), $this->payload([
                'nama' => 'Koperasi Lama',
                'nomor_identitas' => $legacy->nomor_identitas,
                'status_operasional' => '',
            ]))
            ->assertSessionHasNoErrors();

        $this->assertNull($legacy->fresh()->status_operasional);

        // Mekanisme pengisian: status dapat dilengkapi kemudian oleh admin.
        $this->actingAs($admin)
            ->put(route('admin.koperasi.update', $legacy), $this->payload([
                'nama' => 'Koperasi Lama',
                'nomor_identitas' => $legacy->nomor_identitas,
                'status_operasional' => 'tidak_aktif',
            ]))
            ->assertSessionHasNoErrors();

        $this->assertSame('tidak_aktif', $legacy->fresh()->status_operasional);
    }

    public function test_update_rejects_unknown_operational_status(): void
    {
        $cooperative = Cooperative::factory()->create(['status_operasional' => 'aktif']);

        $this->actingAs($this->admin())
            ->from(route('admin.koperasi.edit', $cooperative))
            ->put(route('admin.koperasi.update', $cooperative), $this->payload([
                'nomor_identitas' => $cooperative->nomor_identitas,
                'status_operasional' => 'pailit',
            ]))
            ->assertSessionHasErrors('status_operasional');

        $this->assertSame('aktif', $cooperative->fresh()->status_operasional);
    }

    public function test_update_cannot_silently_clear_an_existing_status(): void
    {
        $cooperative = Cooperative::factory()->create(['status_operasional' => 'bubar']);

        $this->actingAs($this->admin())
            ->from(route('admin.koperasi.edit', $cooperative))
            ->put(route('admin.koperasi.update', $cooperative), $this->payload([
                'nomor_identitas' => $cooperative->nomor_identitas,
                'status_operasional' => '',
            ]))
            ->assertSessionHasErrors('status_operasional');

        $this->assertSame('bubar', $cooperative->fresh()->status_operasional);
    }

    public function test_admin_detail_shows_status_or_explicit_not_set_label(): void
    {
        $admin = $this->admin();
        $withStatus = Cooperative::factory()->create(['status_operasional' => 'tidak_aktif']);
        $legacy = Cooperative::factory()->create(['status_operasional' => null]);

        $this->actingAs($admin)
            ->get(route('admin.koperasi.show', $withStatus))
            ->assertOk()
            ->assertSee('Status operasional')
            ->assertSee('Tidak Aktif');

        $this->actingAs($admin)
            ->get(route('admin.koperasi.show', $legacy))
            ->assertOk()
            ->assertSee('Status operasional')
            ->assertSee('Belum ditetapkan');
    }

    public function test_forms_offer_choices_and_mark_legacy_status_as_not_set(): void
    {
        $admin = $this->admin();
        $legacy = Cooperative::factory()->create(['status_operasional' => null]);

        $this->actingAs($admin)
            ->get(route('admin.koperasi.create'))
            ->assertOk()
            ->assertSee('Pilih status operasional')
            ->assertSee('Aktif')
            ->assertSee('Tidak Aktif')
            ->assertSee('Bubar');

        $this->actingAs($admin)
            ->get(route('admin.koperasi.edit', $legacy))
            ->assertOk()
            ->assertSee('Belum ditetapkan');
    }

    public function test_public_profile_shows_operational_status_only_when_set(): void
    {
        $withStatus = Cooperative::factory()->create([
            'nama' => 'Koperasi Publik Aktif',
            'status_verifikasi' => 'approved',
            'is_published' => true,
            'status_operasional' => 'aktif',
        ]);
        $legacy = Cooperative::factory()->create([
            'nama' => 'Koperasi Publik Lama',
            'status_verifikasi' => 'approved',
            'is_published' => true,
            'status_operasional' => null,
        ]);

        $this->get(route('publik.koperasi', ['slug' => $withStatus->slug]))
            ->assertOk()
            ->assertSee('Status Operasional')
            ->assertSee('Aktif');

        // Data lama tanpa status: tidak ada baris status & tidak ada nilai rekaan.
        // `status_verifikasi` = approved TIDAK boleh tampil sebagai status operasional.
        $this->get(route('publik.koperasi', ['slug' => $legacy->slug]))
            ->assertOk()
            ->assertDontSee('Status Operasional')
            ->assertDontSee('Disetujui');
    }

    public function test_public_directory_shows_operational_status(): void
    {
        Cooperative::factory()->create([
            'nama' => 'Koperasi Direktori Bubar',
            'status_verifikasi' => 'approved',
            'is_published' => true,
            'status_operasional' => 'bubar',
        ]);

        $this->get(route('publik.direktori'))
            ->assertOk()
            ->assertSee('Koperasi Direktori Bubar')
            ->assertSee('Status Operasional')
            ->assertSee('Bubar');
    }

    public function test_unknown_operational_status_is_rejected_by_database_and_hidden_by_model(): void
    {
        $cooperative = Cooperative::factory()->create([
            'status_verifikasi' => 'approved',
            'is_published' => true,
            'status_operasional' => 'aktif',
        ]);

        // Lapisan model: nilai tak dikenal tidak pernah menjadi label tampilan.
        $cooperative->status_operasional = 'pailit';
        $this->assertNull($cooperative->statusOperasionalLabel());

        // Lapisan database: kolom enum menolak nilai di luar tiga status resmi.
        // (Pada SQLite, Laravel membuat CHECK constraint; MySQL/MariaDB memakai ENUM.)
        $this->expectException(QueryException::class);
        DB::table('cooperatives')->where('id', $cooperative->id)->update([
            'status_operasional' => 'pailit',
        ]);
    }
}
