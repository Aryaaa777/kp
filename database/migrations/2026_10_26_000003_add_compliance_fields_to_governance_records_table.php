<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Menambahkan checklist kepatuhan tata kelola (FR-091).
 *
 * PROVISIONAL — checklist dan ambang kepatuhan belum ditetapkan dinas (Q-007).
 * Status dihitung server-side dari checklist + jumlah rapat minimum di konfigurasi
 * `koperasi.governance_compliance`; catatan wajib bila ada deviasi.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('governance_records', function (Blueprint $table) {
            $table->boolean('rat_on_time')->nullable()->after('meeting_count')
                ->comment('RAT tepat waktu (checklist PROVISIONAL FR-091)');
            $table->boolean('financial_report_available')->nullable()->after('rat_on_time')
                ->comment('Laporan keuangan tersedia (checklist PROVISIONAL FR-091)');
            $table->boolean('basic_documents_complete')->nullable()->after('financial_report_available')
                ->comment('Dokumen dasar lengkap (checklist PROVISIONAL FR-091)');
            $table->string('compliance_status', 20)->nullable()->after('status')
                ->comment('patuh|deviasi (dihitung server-side, PROVISIONAL)');
            $table->text('compliance_notes')->nullable()->after('compliance_status')
                ->comment('Wajib diisi bila ada deviasi');
            $table->index('compliance_status');
        });
    }

    public function down(): void
    {
        Schema::table('governance_records', function (Blueprint $table) {
            $table->dropIndex(['compliance_status']);
            $table->dropColumn([
                'rat_on_time',
                'financial_report_available',
                'basic_documents_complete',
                'compliance_status',
                'compliance_notes',
            ]);
        });
    }
};
