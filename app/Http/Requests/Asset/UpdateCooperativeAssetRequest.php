<?php

namespace App\Http\Requests\Asset;

use App\Models\CooperativeAsset;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validasi perubahan aset koperasi (FR-100).
 *
 * PROVISIONAL — MENUNGGU KONFIRMASI DINAS. `cooperative_id`/`recorded_by` tidak dapat diubah.
 *
 * @see docs/DATA_DICTIONARY.md §11 (cooperative_assets)
 */
class UpdateCooperativeAssetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $maxYear = (int) date('Y') + 1;
        $typeOptions = array_keys(CooperativeAsset::typeOptions());
        $conditionOptions = array_keys(CooperativeAsset::conditionOptions());

        $assetType = ['sometimes', 'required', 'string', 'max:100'];
        if ($typeOptions !== []) {
            $assetType = ['sometimes', 'required', 'string', Rule::in($typeOptions)];
        }

        $condition = ['nullable', 'string', 'max:30'];
        if ($conditionOptions !== []) {
            $condition = ['nullable', 'string', Rule::in($conditionOptions)];
        }

        return [
            'period_year' => ['sometimes', 'required', 'integer', 'min:1900', 'max:'.$maxYear],
            'asset_type' => $assetType,
            'asset_name' => ['nullable', 'string', 'max:200'],
            'quantity' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'value' => ['sometimes', 'required', 'numeric', 'min:0', 'max:9999999999999999'],
            'acquisition_date' => ['nullable', 'date', 'before_or_equal:today'],
            'condition' => $condition,
            'description' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'period_year' => 'tahun periode',
            'asset_type' => 'jenis aset',
            'asset_name' => 'nama aset',
            'quantity' => 'jumlah',
            'value' => 'nilai',
            'acquisition_date' => 'tanggal perolehan',
            'condition' => 'kondisi',
            'description' => 'keterangan',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'value.min' => 'Nilai aset tidak boleh negatif.',
            'acquisition_date.before_or_equal' => 'Tanggal perolehan tidak boleh di masa depan.',
        ];
    }
}
