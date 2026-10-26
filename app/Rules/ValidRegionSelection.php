<?php

namespace App\Rules;

use App\Models\Cooperative;
use App\Models\Region;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Memastikan pilihan wilayah konsisten dengan hierarki resmi.
 *
 * Aturan:
 * - Bila tidak ada wilayah yang dipilih, seluruh field hierarki harus kosong.
 * - Bila wilayah dipilih, rantai provinsi → kabupaten/kota → kecamatan →
 *   desa/kelurahan harus cocok dengan data `regions`.
 * - Data lama yang `region_id`-nya belum berada di tingkat desa/kelurahan
 *   boleh dipertahankan tanpa perubahan (agar tidak menghapus data), tetapi
 *   tidak boleh diganti ke wilayah non-desa.
 */
class ValidRegionSelection implements ValidationRule
{
    /**
     * @param  array<string, mixed>  $input
     */
    public function __construct(
        private readonly array $input,
        private readonly ?Cooperative $cooperative = null,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $provinceId = $this->toId($this->input['province_id'] ?? null);
        $regencyId = $this->toId($this->input['regency_id'] ?? null);
        $districtId = $this->toId($this->input['district_id'] ?? null);

        if ($value === null || $value === '') {
            if ($provinceId !== null || $regencyId !== null || $districtId !== null) {
                $fail('Pilih desa/kelurahan, atau kosongkan seluruh pilihan wilayah.');
            }

            return;
        }

        $regionId = $this->toId($value);

        if ($regionId === null) {
            return; // Aturan `exists` menangani nilai yang bukan angka.
        }

        /** @var Region|null $region */
        $region = Region::query()->find($regionId);

        if ($region === null) {
            return; // Aturan `exists` menangani ID yang tidak ada.
        }

        if ($region->type !== 'village') {
            $isCurrentLegacyRegion = $this->cooperative !== null
                && (int) $this->cooperative->region_id === (int) $region->id;

            if (! $isCurrentLegacyRegion) {
                $fail('Wilayah harus dipilih sampai tingkat desa/kelurahan.');

                return;
            }
        }

        $expectedProvince = $region->ancestorOfType('province');
        $expectedRegency = $region->ancestorOfType('regency')
            ?? $region->ancestorOfType('city');
        $expectedDistrict = $region->ancestorOfType('district');

        if ($provinceId === null || $expectedProvince === null || $provinceId !== (int) $expectedProvince->id) {
            $fail('Provinsi yang dipilih tidak sesuai dengan wilayah desa/kelurahan.');

            return;
        }

        if ($region->type === 'province') {
            if ($regencyId !== null || $districtId !== null) {
                $fail('Kabupaten/kota dan kecamatan tidak boleh diisi untuk wilayah provinsi.');
            }

            return;
        }

        if ($regencyId === null || $expectedRegency === null || $regencyId !== (int) $expectedRegency->id) {
            $fail('Kabupaten/kota yang dipilih tidak sesuai dengan wilayah desa/kelurahan.');

            return;
        }

        if ($region->type === 'regency' || $region->type === 'city') {
            if ($districtId !== null) {
                $fail('Kecamatan tidak boleh diisi untuk wilayah kabupaten/kota.');
            }

            return;
        }

        if ($districtId === null || $expectedDistrict === null || $districtId !== (int) $expectedDistrict->id) {
            $fail('Kecamatan yang dipilih tidak sesuai dengan wilayah desa/kelurahan.');

            return;
        }

        if ($region->type === 'district') {
            return;
        }

        // Wilayah desa/kelurahan: rantai lengkap sudah tervalidasi.
        if ($region->type !== 'village') {
            $fail('Tingkat wilayah tidak dikenal.');
        }
    }

    private function toId(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_int($value)) {
            return $value;
        }

        if (is_string($value) && ctype_digit($value)) {
            return (int) $value;
        }

        return null;
    }
}
