<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Data induk koperasi (F2).
     *
     * Penyimpangan sementara yang didokumentasikan (lihat `docs/DECISIONS.md`):
     * - `region_id` dibuat **nullable** (DATA_DICTIONARY menyebut NN) karena referensi wilayah
     *   belum tersedia (Q-012 belum dikonfirmasi). Mudah dinaikkan menjadi NN setelah wilayah di-seed.
     * - `archived_reason` ditambahkan agar alasan arsip (FR-043) tersimpan sebelum `activity_logs`
     *   dibuat pada F5.
     * - `jenis_koperasi` disimpan sebagai VARCHAR (bukan enum DB) karena daftar resmi belum dikonfirmasi (Q-003).
     */
    public function up(): void
    {
        Schema::create('cooperatives', function (Blueprint $table) {
            $table->id();

            // Identitas
            $table->string('nama', 200)->index();
            $table->string('slug', 220)->unique();
            $table->string('nomor_identitas', 100)->unique();
            $table->string('jenis_koperasi', 100)->index();
            $table->string('bentuk_koperasi', 100)->nullable();
            $table->date('tanggal_berdiri')->nullable();

            // Wilayah & kontak
            $table->foreignId('region_id')->nullable()->constrained('regions')->restrictOnDelete();
            $table->text('alamat')->nullable();
            $table->string('telephone', 30)->nullable();
            $table->string('email', 150)->nullable();
            $table->string('website', 200)->nullable();

            // Status
            $table->enum('status_verifikasi', [
                'draft',
                'submitted',
                'under_review',
                'needs_revision',
                'approved',
                'rejected',
                'archived',
            ])->default('draft')->index();
            $table->boolean('is_published')->default(false)->index();
            $table->timestamp('published_at')->nullable();
            $table->foreignId('published_by')->nullable()->constrained('users')->nullOnDelete();

            // Jejak aktor
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();

            // Arsip (FR-043) — sementara sampai activity_logs (F5)
            $table->text('archived_reason')->nullable();

            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cooperatives');
    }
};
