<?php

namespace Database\Factories;

use App\Models\Cooperative;
use App\Models\CooperativeLicense;
use App\Models\Document;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Document>
 */
class DocumentFactory extends Factory
{
    /**
     * @var class-string<Document>
     */
    protected $model = Document::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'cooperative_id' => Cooperative::factory(),
            'documentable_type' => CooperativeLicense::class,
            'documentable_id' => CooperativeLicense::factory(),
            'category' => 'izin_usaha',
            'original_name' => 'dokumen.pdf',
            'file_path' => 'documents/'.fake()->uuid().'.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => 1024,
            'checksum' => null,
            'version' => 1,
            'uploaded_by' => User::factory(),
        ];
    }

    /**
     * Tautkan dokumen ke izin tertentu (menyamakan pemilik koperasi & entitas polimorfik).
     */
    public function forLicense(CooperativeLicense $license): static
    {
        return $this->state(fn (): array => [
            'cooperative_id' => $license->cooperative_id,
            'documentable_type' => $license->getMorphClass(),
            'documentable_id' => $license->getKey(),
        ]);
    }
}
