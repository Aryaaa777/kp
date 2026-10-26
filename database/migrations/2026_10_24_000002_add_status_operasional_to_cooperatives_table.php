<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Status operasional koperasi (keputusan pemilik proyek — Tahap 106).
 *
 * Nilai yang disetujui: **Aktif**, **Tidak Aktif**, **Bubar** (disimpan sebagai
 * `aktif` / `tidak_aktif` / `bubar`; label tampilan ada di `Cooperative::STATUS_OPERASIONAL_LABELS`).
 *
 * Sifat kolom:
 * - **Nullable tanpa default.** Data lama **tidak** diberi nilai karangan: `NULL` berarti
 *   "belum ditetapkan/belum dikonfirmasi" dan wajib dilengkapi pihak berwenang. Sistem tidak
 *   boleh menganggap semua koperasi aktif.
 * - **Additive** — tidak mengubah/menghapus kolom atau data lain.
 * - `enum` dipakai agar database ikut menolak nilai di luar tiga status resmi; validasi
 *   server-side tetap menjadi penjaga utama (`Store/UpdateCooperativeRequest`).
 *
 * `down()` menghapus kolom ini sehingga **menghilangkan** status operasional yang sudah diisi —
 * hanya boleh dijalankan dengan persetujuan eksplisit (pola kebijakan rollback proyek).
 *
 * @see docs/DECISIONS.md §29
 * @see docs/DATA_DICTIONARY.md §13 (cooperatives)
 */
return new class extends Migration
{
    public const TABLE = 'cooperatives';

    public const COLUMN = 'status_operasional';

    /** @var list<string> */
    public const VALUES = ['aktif', 'tidak_aktif', 'bubar'];

    public function up(): void
    {
        if (! Schema::hasTable(self::TABLE) || Schema::hasColumn(self::TABLE, self::COLUMN)) {
            return;
        }

        Schema::table(self::TABLE, function (Blueprint $table): void {
            $table->enum(self::COLUMN, self::VALUES)->nullable();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable(self::TABLE) || ! Schema::hasColumn(self::TABLE, self::COLUMN)) {
            return;
        }

        Schema::table(self::TABLE, function (Blueprint $table): void {
            $table->dropColumn(self::COLUMN);
        });
    }
};
