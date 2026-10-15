<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Referensi wilayah (provinsi → kabupaten/kota → kecamatan → desa).
     *
     * CATATAN: struktur tabel dibuat pada F2, tetapi **tidak di-seed** karena sumber & format
     * kode wilayah resmi belum dikonfirmasi dinas (Q-012, [NEEDS CONFIRMATION]).
     * Jangan mengisi data wilayah yang dikarang. Lihat `docs/DECISIONS.md`.
     */
    public function up(): void
    {
        Schema::create('regions', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('name', 150);
            $table->enum('type', ['province', 'regency', 'city', 'district', 'village'])->index();
            $table->foreignId('parent_id')->nullable()->constrained('regions')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('regions');
    }
};
