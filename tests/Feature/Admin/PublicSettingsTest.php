<?php

namespace Tests\Feature\Admin;

use App\Models\Cooperative;
use App\Models\Region;
use App\Models\Setting;
use App\Models\User;
use App\Services\PublicFieldSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Pengaturan field publik (FR-191) dan pencegahan kebocoran data.
 */
class PublicSettingsTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->admin()->create();
    }

    private function published(array $overrides = []): Cooperative
    {
        return Cooperative::factory()->create(array_merge([
            'status_verifikasi' => 'approved',
            'is_published' => true,
        ], $overrides));
    }

    public function test_pengelola_cannot_access_public_settings(): void
    {
        $cooperative = Cooperative::factory()->create();
        $pengelola = User::factory()->pengelola($cooperative->id)->create();

        $this->actingAs($pengelola)
            ->get(route('admin.pengaturan.publik'))
            ->assertForbidden();

        $this->actingAs($pengelola)
            ->put(route('admin.pengaturan.publik.update'), ['fields' => ['nama']])
            ->assertForbidden();
    }

    public function test_admin_can_view_allowlisted_settings(): void
    {
        $this->actingAs($this->admin())
            ->get(route('admin.pengaturan.publik'))
            ->assertOk()
            ->assertSee('Pengaturan Field Publik')
            ->assertSee('Nomor badan hukum')
            ->assertSee('Status operasional')
            ->assertDontSee('value="nik"', false)
            ->assertDontSee('value="omzet"', false);
    }

    public function test_admin_can_update_fields_and_public_profile_respects_subset(): void
    {
        $region = Region::factory()->create(['name' => 'Jawa Barat Rahasia']);
        $cooperative = $this->published([
            'nama' => 'Koperasi Subset Publik',
            'nomor_identitas' => 'KOP-SUBSET-1',
            'jenis_koperasi' => 'Koperasi Konsumen',
            'website' => 'https://subset.example.test',
            'status_operasional' => 'aktif',
            'email' => 'subset@example.test',
            'telephone' => '081234567890',
            'region_id' => $region->id,
        ]);

        $this->actingAs($this->admin())
            ->put(route('admin.pengaturan.publik.update'), [
                'fields' => ['nama', 'nomor_identitas'],
            ])
            ->assertSessionHas('status');

        $this->assertDatabaseHas('settings', ['key' => PublicFieldSettings::SETTING_KEY]);

        $this->get(route('publik.koperasi', ['slug' => $cooperative->slug]))
            ->assertOk()
            ->assertSee('Koperasi Subset Publik')
            ->assertSee('KOP-SUBSET-1')
            ->assertDontSee('Koperasi Konsumen')
            ->assertDontSee('https://subset.example.test')
            ->assertDontSee('Status Operasional')
            ->assertDontSee('Jawa Barat Rahasia');
    }

    public function test_admin_can_disable_region_and_contact_fields(): void
    {
        $region = Region::factory()->create(['name' => 'Wilayah Tersembunyi']);
        $cooperative = $this->published([
            'nama' => 'Koperasi Tanpa Kontak Publik',
            'email' => 'kontak@example.test',
            'telephone' => '081234567890',
            'region_id' => $region->id,
        ]);

        $this->actingAs($this->admin())
            ->put(route('admin.pengaturan.publik.update'), [
                'fields' => ['nama'],
            ])
            ->assertSessionHas('status');

        $this->get(route('publik.koperasi', ['slug' => $cooperative->slug]))
            ->assertOk()
            ->assertSee('Koperasi Tanpa Kontak Publik')
            ->assertDontSee('Wilayah Tersembunyi')
            ->assertDontSee('Alamat')
            ->assertDontSee('Email')
            ->assertDontSee('Telepon')
            ->assertDontSee('ko***@example.test');
    }

    public function test_disallowed_field_is_rejected_by_server_validation(): void
    {
        $this->actingAs($this->admin())
            ->put(route('admin.pengaturan.publik.update'), [
                'fields' => ['nama', 'nik', 'omzet'],
            ])
            ->assertSessionHasErrors();

        $this->assertDatabaseMissing('settings', ['key' => PublicFieldSettings::SETTING_KEY]);
    }

    public function test_config_cannot_expand_the_q008_allowlist(): void
    {
        config(['koperasi.public.allowed_fields' => ['nama', 'nik', 'omzet']]);

        $this->assertSame(['nama'], app(PublicFieldSettings::class)->allowedFields());

        $this->actingAs($this->admin())
            ->put(route('admin.pengaturan.publik.update'), [
                'fields' => ['nama', 'nik'],
            ])
            ->assertSessionHasErrors();
    }

    public function test_unknown_fields_stored_directly_are_ignored_and_never_leak(): void
    {
        Setting::create([
            'key' => PublicFieldSettings::SETTING_KEY,
            'value' => json_encode([
                'fields' => ['nama', 'nik', 'omzet', 'data_anggota'],
                'show_region' => true,
                'contact' => ['email' => true, 'telephone' => true],
            ], JSON_THROW_ON_ERROR),
        ]);

        $cooperative = $this->published([
            'nama' => 'Koperasi Aman Dari Setting',
            'nomor_identitas' => 'KOP-SETTING-AMAN',
        ]);

        $response = $this->get(route('publik.direktori'))->assertOk();
        $presented = $response->viewData('koperasi')->getCollection()->first();

        $this->assertArrayHasKey('nama', $presented);
        $this->assertArrayNotHasKey('nik', $presented);
        $this->assertArrayNotHasKey('omzet', $presented);
        $this->assertArrayNotHasKey('data_anggota', $presented);
        $response->assertDontSee('KOP-SETTING-AMAN');
    }

    public function test_defaults_are_safe_when_no_setting_exists(): void
    {
        $settings = app(PublicFieldSettings::class)->current();

        $this->assertSame([
            'nama',
            'nomor_identitas',
            'tanggal_berdiri',
            'jenis_koperasi',
            'status_operasional',
            'website',
        ], $settings['fields']);
        $this->assertTrue($settings['show_region']);
        $this->assertTrue($settings['contact']['email']);
        $this->assertTrue($settings['contact']['telephone']);
    }
}
