<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Menambahkan komponen skor kesehatan (FR-082).
 *
 * PROVISIONAL — formula resmi belum ditetapkan dinas (Q-007). Lima komponen dan
 * bobotnya diambil dari konfigurasi `koperasi.health_assessment`; kolom di sini
 * hanya menyimpan nilai komponen 0–100. Skor total & predikat dihitung di server
 * bila `auto_calculated = true`; input manual tetap dimungkinkan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('health_assessments', function (Blueprint $table) {
            $table->decimal('component_permodalan', 5, 2)->nullable()->after('assessment_date');
            $table->decimal('component_likuiditas', 5, 2)->nullable()->after('component_permodalan');
            $table->decimal('component_rentabilitas', 5, 2)->nullable()->after('component_likuiditas');
            $table->decimal('component_kualitas_aset', 5, 2)->nullable()->after('component_rentabilitas');
            $table->decimal('component_efisiensi', 5, 2)->nullable()->after('component_kualitas_aset');
            $table->boolean('auto_calculated')->default(false)->after('predicate')
                ->comment('Skor/predikat dihitung server-side dari komponen (PROVISIONAL)');
        });
    }

    public function down(): void
    {
        Schema::table('health_assessments', function (Blueprint $table) {
            $table->dropColumn([
                'component_permodalan',
                'component_likuiditas',
                'component_rentabilitas',
                'component_kualitas_aset',
                'component_efisiensi',
                'auto_calculated',
            ]);
        });
    }
};
