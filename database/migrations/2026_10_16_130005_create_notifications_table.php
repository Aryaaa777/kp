<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration tabel `notifications` (Laravel, in-app) — F5.
 *
 * FR-201: notifikasi status pengajuan (in-app) saat disetujui/perlu perbaikan/ditolak.
 * Kanal email/WA **ditunda** (Q-011) — hanya kanal `database`.
 *
 * Struktur mengikuti migrasi standar Laravel (`php artisan notifications:table`).
 *
 * @see docs/DECISIONS.md §12 (F5 — notifikasi in-app)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->morphs('notifiable');
            $table->text('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
