<?php

namespace App\Services;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Daftar & pengakhiran sesi aktif pengguna (FR-003).
 *
 * Dukungan daftar lintas perangkat bergantung pada session driver. Hanya driver
 * `database` yang menyimpan metadata yang dibutuhkan (id, IP, user agent,
 * last_activity). Driver lain tidak boleh diklaim mendukung daftar sesi.
 */
class ActiveSessionService
{
    private ?bool $supportsListing = null;

    /**
     * Apakah driver sesi saat ini menyimpan metadata sesi yang dapat dibaca.
     */
    public function supportsListing(): bool
    {
        if ($this->supportsListing !== null) {
            return $this->supportsListing;
        }

        if ((string) config('session.driver') !== 'database') {
            return $this->supportsListing = false;
        }

        return $this->supportsListing = Schema::hasTable($this->table());
    }

    /**
     * Daftar sesi milik pengguna, terbaru lebih dahulu.
     *
     * @return Collection<int, array{id: string, is_current: bool, ip_address: string|null, user_agent: string|null, last_activity: Carbon}>
     */
    public function forUser(User $user, ?string $currentSessionId = null): Collection
    {
        if (! $this->supportsListing()) {
            return collect();
        }

        return $this->connection()
            ->table($this->table())
            ->where('user_id', $user->getKey())
            ->orderByDesc('last_activity')
            ->get()
            ->map(function (object $row) use ($currentSessionId): array {
                $id = (string) $row->id;

                return [
                    'id' => $id,
                    'is_current' => $currentSessionId !== null && hash_equals($id, (string) $currentSessionId),
                    'ip_address' => $row->ip_address !== null ? (string) $row->ip_address : null,
                    'user_agent' => $row->user_agent !== null ? (string) $row->user_agent : null,
                    'last_activity' => Carbon::createFromTimestamp((int) $row->last_activity),
                ];
            });
    }

    /**
     * Akhiri semua sesi pengguna kecuali sesi yang sedang dipakai.
     *
     * @return int Jumlah sesi yang dihapus.
     */
    public function terminateOthers(User $user, string $currentSessionId): int
    {
        if (! $this->supportsListing()) {
            return 0;
        }

        return $this->connection()
            ->table($this->table())
            ->where('user_id', $user->getKey())
            ->where('id', '!=', $currentSessionId)
            ->delete();
    }

    /**
     * Akhiri seluruh sesi pengguna, termasuk sesi saat ini.
     *
     * @return int Jumlah sesi yang dihapus.
     */
    public function terminateAll(User $user): int
    {
        if (! $this->supportsListing()) {
            return 0;
        }

        return $this->connection()
            ->table($this->table())
            ->where('user_id', $user->getKey())
            ->delete();
    }

    private function table(): string
    {
        return (string) config('session.table', 'sessions');
    }

    private function connection(): ConnectionInterface
    {
        return DB::connection(config('session.connection'));
    }
}
