<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Menambahkan field akun yang dibutuhkan F1:
     * - phone          : kontak akun
     * - koperasi_id    : relasi akun pengelola -> koperasi (FK ke `cooperatives` ditambahkan pada F2)
     * - status         : status akun (active/inactive)
     * - last_login_at  : jejak waktu login terakhir
     * - deleted_at     : soft delete (sesuai DATA_DICTIONARY)
     *
     * Catatan: `region_id` (pembatasan wilayah) sengaja ditunda karena bersifat FASE-2
     * dan bergantung pada tabel `regions` yang dibuat pada F2.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('phone', 30)->nullable()->after('password');
            $table->unsignedBigInteger('koperasi_id')->nullable()->after('phone');
            $table->enum('status', ['active', 'inactive'])->default('active')->after('koperasi_id');
            $table->timestamp('last_login_at')->nullable()->after('status');
            $table->softDeletes();

            $table->index('koperasi_id');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['koperasi_id']);
            $table->dropIndex(['status']);
            $table->dropSoftDeletes();
            $table->dropColumn(['phone', 'koperasi_id', 'status', 'last_login_at']);
        });
    }
};
