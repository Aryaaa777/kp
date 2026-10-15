<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Dokumen pendukung, polimorfik (F3 — FR-070/071).
     *
     * Rancangan mengikuti `docs/DATA_DICTIONARY.md` §8. Pada F3 entitas terkait (`documentable`)
     * baru sebatas izin (`cooperative_licenses`); entitas lain menyusul pada fase lanjutan.
     *
     * Keamanan: `file_path` menunjuk berkas di disk **privat** (`storage/app/private/documents`)
     * dan hanya dapat diakses melalui controller ber-Policy (NFR-SEC-009/010).
     */
    public function up(): void
    {
        Schema::create('documents', function (Blueprint $table) {
            $table->id();

            $table->foreignId('cooperative_id')
                ->constrained('cooperatives')
                ->cascadeOnDelete();

            // Relasi polimorfik ke entitas terkait.
            $table->string('documentable_type', 150);
            $table->unsignedBigInteger('documentable_id');
            $table->index(['documentable_type', 'documentable_id'], 'documents_documentable_index');

            $table->string('category', 100)->index();
            $table->string('original_name', 255);
            $table->string('file_path', 500);
            $table->string('mime_type', 120);
            $table->unsignedBigInteger('size_bytes');
            $table->string('checksum', 128)->nullable();
            $table->unsignedInteger('version')->default(1);

            $table->foreignId('uploaded_by')
                ->constrained('users')
                ->restrictOnDelete();

            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('documents');
    }
};
