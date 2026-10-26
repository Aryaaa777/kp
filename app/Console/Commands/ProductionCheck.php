<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Pemeriksaan kesiapan produksi yang aman dan read-only.
 *
 * Perintah ini tidak mengubah konfigurasi, data, atau berkas. Ia memeriksa
 * nilai konfigurasi yang wajib, konektivitas database, versi server, migrasi
 * tertunda, data referensi wilayah, dan keberadaan akun admin aktif.
 *
 * Pemeriksaan yang bergantung pada database dapat dilewati dengan
 * `--skip-database` (dipakai pada pengujian otomatis/CI tanpa DB).
 */
class ProductionCheck extends Command
{
    protected $signature = 'app:production-check
        {--json : Keluarkan hasil sebagai JSON}
        {--skip-database : Lewati pemeriksaan koneksi, migrasi, wilayah, dan akun}';

    protected $description = 'Memeriksa kesiapan konfigurasi dan database sebelum go-live';

    /**
     * @var list<array{id: string, label: string, status: string, message: string}>
     */
    private array $checks = [];

    public function handle(): int
    {
        $this->checks = [];

        $this->checkApplication();
        $this->checkSessions();
        $this->checkMailAndStorage();
        $this->checkSecurityHeaders();
        $this->checkRuntimeFiles();

        if (! $this->option('skip-database')) {
            $this->checkDatabase();
        } else {
            $this->add('database.skipped', 'Pemeriksaan database', 'warn', 'Dilewati dengan --skip-database.');
        }

        $this->render();

        return $this->failed() > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function checkApplication(): void
    {
        $environment = (string) config('app.env');
        $this->add(
            'app.environment',
            'APP_ENV',
            $environment === 'production' ? 'pass' : 'fail',
            $environment === 'production' ? 'production' : "Nilai saat ini: {$environment}.",
        );

        $this->add(
            'app.debug',
            'APP_DEBUG',
            config('app.debug') === false ? 'pass' : 'fail',
            config('app.debug') === false ? 'false' : 'Harus false di produksi.',
        );

        $key = (string) config('app.key');
        $this->add(
            'app.key',
            'APP_KEY',
            $key !== '' ? 'pass' : 'fail',
            $key !== '' ? 'Terisi.' : 'Jalankan `php artisan key:generate`.',
        );

        $url = (string) config('app.url');
        $parts = parse_url($url);
        $host = is_array($parts) ? strtolower((string) ($parts['host'] ?? '')) : '';
        $scheme = is_array($parts) ? strtolower((string) ($parts['scheme'] ?? '')) : '';
        $localHosts = ['localhost', '127.0.0.1', '::1', ''];
        $urlOk = $scheme === 'https' && ! in_array($host, $localHosts, true);

        $this->add(
            'app.url',
            'APP_URL',
            $urlOk ? 'pass' : 'fail',
            $urlOk ? $url : 'Harus URL HTTPS publik (bukan localhost).',
        );

        $timezone = (string) config('app.timezone');
        $this->add(
            'app.timezone',
            'APP_TIMEZONE',
            $timezone === 'Asia/Jakarta' ? 'pass' : 'fail',
            $timezone === 'Asia/Jakarta' ? 'Asia/Jakarta' : "Nilai saat ini: {$timezone}.",
        );

        $locale = (string) config('app.locale');
        $this->add(
            'app.locale',
            'APP_LOCALE',
            $locale === 'id' ? 'pass' : 'warn',
            $locale === 'id' ? 'id' : "Nilai saat ini: {$locale}; antarmuka utama berbahasa Indonesia.",
        );
    }

    private function checkSessions(): void
    {
        $driver = (string) config('session.driver');
        $this->add(
            'session.driver',
            'SESSION_DRIVER',
            $driver === 'database' ? 'pass' : 'fail',
            $driver === 'database'
                ? 'database'
                : "Nilai saat ini: {$driver}; FR-003 memerlukan database.",
        );

        $this->add(
            'session.secure',
            'SESSION_SECURE_COOKIE',
            config('session.secure') === true ? 'pass' : 'fail',
            config('session.secure') === true ? 'true' : 'Harus true di produksi HTTPS.',
        );

        $this->add(
            'session.http_only',
            'SESSION_HTTP_ONLY',
            config('session.http_only') === true ? 'pass' : 'fail',
            config('session.http_only') === true ? 'true' : 'Harus true.',
        );

        $sameSite = config('session.same_site');
        $this->add(
            'session.same_site',
            'SESSION_SAME_SITE',
            in_array($sameSite, ['lax', 'strict'], true) ? 'pass' : 'fail',
            is_string($sameSite) ? $sameSite : 'Nilai tidak valid.',
        );

        $idle = (int) config('session.idle_timeout', 0);
        $this->add(
            'session.idle_timeout',
            'SESSION_IDLE_TIMEOUT',
            $idle >= 1 ? 'pass' : 'fail',
            $idle >= 1 ? "{$idle} menit" : 'Minimal 1 menit.',
        );

        $encrypt = config('session.encrypt') === true;
        $this->add(
            'session.encrypt',
            'SESSION_ENCRYPT',
            $encrypt ? 'pass' : 'warn',
            $encrypt ? 'true' : 'Disarankan true untuk data sesi sensitif.',
        );
    }

    private function checkMailAndStorage(): void
    {
        $mailer = (string) config('mail.default');
        $this->add(
            'mail.default',
            'MAIL_MAILER',
            ! in_array($mailer, ['log', 'array'], true) ? 'pass' : 'fail',
            in_array($mailer, ['log', 'array'], true)
                ? "Nilai saat ini: {$mailer}; reset password tidak terkirim."
                : $mailer,
        );

        $from = (string) config('mail.from.address');
        $fromOk = filter_var($from, FILTER_VALIDATE_EMAIL) !== false
            && ! str_ends_with(strtolower($from), '@example.com');

        $this->add(
            'mail.from',
            'MAIL_FROM_ADDRESS',
            $fromOk ? 'pass' : 'fail',
            $fromOk ? $from : 'Gunakan alamat pengirim resmi, bukan contoh.',
        );

        $cache = (string) config('cache.default');
        $this->add(
            'cache.default',
            'CACHE_STORE',
            $cache !== 'array' && $cache !== 'null' ? 'pass' : 'fail',
            in_array($cache, ['array', 'null'], true)
                ? "Nilai saat ini: {$cache}; cache tidak persisten."
                : $cache,
        );

        $queue = (string) config('queue.default');
        $this->add(
            'queue.default',
            'QUEUE_CONNECTION',
            $queue !== 'sync' ? 'pass' : 'warn',
            $queue !== 'sync'
                ? $queue
                : 'sync dapat dipakai bila tidak ada job antrean; jalankan worker bila memakai database/redis.',
        );

        $disk = (string) config('koperasi.document_disk');
        $diskConfig = config("filesystems.disks.{$disk}");
        $diskOk = $disk !== 'public' && is_array($diskConfig);

        $this->add(
            'filesystem.document_disk',
            'Disk dokumen',
            $diskOk ? 'pass' : 'fail',
            $diskOk
                ? "{$disk} (privat)"
                : "Disk '{$disk}' tidak ada atau bersifat publik; dokumen wajib privat.",
        );
    }

    private function checkSecurityHeaders(): void
    {
        $this->add(
            'security.hsts',
            'HSTS',
            config('security.hsts.enabled') === true ? 'pass' : 'fail',
            config('security.hsts.enabled') === true
                ? 'Aktif (hanya dikirim pada HTTPS).'
                : 'Aktifkan SECURITY_HSTS_ENABLED=true.',
        );

        $this->add(
            'security.csp',
            'CSP',
            config('security.csp.enabled') === true ? 'pass' : 'fail',
            config('security.csp.enabled') === true
                ? 'Aktif.'
                : 'Aktifkan SECURITY_CSP_ENABLED=true setelah verifikasi aset.',
        );

        $policy = (string) config('security.csp.policy');
        $policyOk = str_contains($policy, "default-src 'self'")
            && str_contains($policy, "frame-ancestors 'none'")
            && ! preg_match('/script-src[^;]*unsafe-inline/', $policy);

        $this->add(
            'security.csp.policy',
            'Kebijakan CSP',
            $policyOk ? 'pass' : 'warn',
            $policyOk
                ? 'default-src self, frame-ancestors none, tanpa script unsafe-inline.'
                : 'Periksa kebijakan CSP; hindari script-src unsafe-inline.',
        );

        $xFrame = (string) config('security.headers.x_frame_options');
        $this->add(
            'security.x_frame_options',
            'X-Frame-Options',
            $xFrame !== '' ? 'pass' : 'fail',
            $xFrame !== '' ? $xFrame : 'Header belum diatur.',
        );
    }

    private function checkRuntimeFiles(): void
    {
        $paths = [
            'storage' => storage_path(),
            'storage.framework' => storage_path('framework'),
            'storage.logs' => storage_path('logs'),
            'bootstrap.cache' => base_path('bootstrap/cache'),
            'storage.app' => storage_path('app'),
        ];

        foreach ($paths as $id => $path) {
            $this->add(
                "runtime.{$id}",
                "Writable: {$id}",
                is_dir($path) && is_writable($path) ? 'pass' : 'fail',
                $path,
            );
        }

        $manifest = public_path('build/manifest.json');
        $this->add(
            'runtime.assets',
            'Aset produksi',
            is_file($manifest) ? 'pass' : 'fail',
            is_file($manifest)
                ? 'public/build/manifest.json ada.'
                : 'Jalankan `npm run build`.',
        );

        if (DIRECTORY_SEPARATOR === '/') {
            $envPath = base_path('.env');
            $perms = is_file($envPath) ? fileperms($envPath) : false;
            $tooOpen = $perms !== false && ($perms & 0004) !== 0;
            $this->add(
                'runtime.env_permissions',
                'Izin .env',
                $tooOpen ? 'warn' : 'pass',
                $tooOpen
                    ? 'File .env dapat dibaca semua pengguna; batasi ke pemilik/group service.'
                    : 'Tidak dapat dibaca publik (atau tidak diperiksa di Windows).',
            );
        }

        $this->add(
            'runtime.optimized',
            'Cache konfigurasi/route',
            app()->configurationIsCached() && app()->routesAreCached() ? 'pass' : 'warn',
            app()->configurationIsCached() && app()->routesAreCached()
                ? 'config:cache dan route:cache aktif.'
                : 'Jalankan config:cache, route:cache, view:cache pada langkah deploy.',
        );
    }

    private function checkDatabase(): void
    {
        $driver = (string) config('database.default');
        $driverOk = in_array($driver, ['mysql', 'mariadb'], true);

        $this->add(
            'database.driver',
            'DB_CONNECTION',
            $driverOk ? 'pass' : 'fail',
            $driverOk ? $driver : "Nilai saat ini: {$driver}; gunakan mysql/mariadb.",
        );

        if (! $driverOk) {
            return;
        }

        try {
            $connection = DB::connection();
            $connection->getPdo();

            $this->add('database.connection', 'Koneksi database', 'pass', 'Terhubung.');

            $versionRow = $connection->selectOne('select version() as version');
            $version = is_object($versionRow) ? (string) ($versionRow->version ?? '') : '';

            if ($version === '') {
                $this->add('database.version', 'Versi database', 'fail', 'Tidak dapat membaca versi server.');
            } else {
                $this->checkDatabaseVersion($version);
            }

            $this->checkPendingMigrations($connection);
            $this->checkRegions($connection);
            $this->checkActiveAdmin($connection);
        } catch (Throwable $exception) {
            $message = $this->sanitize($exception->getMessage());

            $this->add(
                'database.connection',
                'Koneksi database',
                'fail',
                "Gagal terhubung: {$message}",
            );
        }
    }

    private function checkDatabaseVersion(string $version): void
    {
        $isMariaDb = stripos($version, 'mariadb') !== false;
        $isMySql = stripos($version, 'mysql') !== false;
        $numeric = null;

        if (preg_match('/(\d+\.\d+\.\d+)/', $version, $matches) === 1) {
            $numeric = $matches[1];
        }

        if ($numeric === null) {
            $this->add('database.version', 'Versi database', 'fail', "Tidak dikenali: {$version}");

            return;
        }

        if ($isMariaDb) {
            $ok = version_compare($numeric, '10.11.0', '>=');
            $this->add(
                'database.version',
                'Versi database',
                $ok ? 'pass' : 'fail',
                "MariaDB {$numeric}; target minimal 10.11 LTS.",
            );

            return;
        }

        if ($isMySql) {
            $ok = version_compare($numeric, '8.0.0', '>=');
            $this->add(
                'database.version',
                'Versi database',
                $ok ? 'pass' : 'fail',
                "MySQL {$numeric}; target minimal 8.0.",
            );

            return;
        }

        $this->add('database.version', 'Versi database', 'fail', "Tidak dikenali: {$version}");
    }

    private function checkPendingMigrations(ConnectionInterface $connection): void
    {
        try {
            $files = array_keys(app('migrator')->getMigrationFiles(database_path('migrations')));
            $ran = $connection->table('migrations')->pluck('migration')->all();
            $pending = array_values(array_diff($files, $ran));

            $this->add(
                'database.migrations',
                'Migrasi',
                $pending === [] ? 'pass' : 'fail',
                $pending === []
                    ? 'Semua migrasi sudah dijalankan.'
                    : count($pending).' migrasi tertunda: '.implode(', ', array_slice($pending, 0, 5)),
            );
        } catch (Throwable $exception) {
            $this->add(
                'database.migrations',
                'Migrasi',
                'fail',
                'Tidak dapat memeriksa status migrasi: '.$this->sanitize($exception->getMessage()),
            );
        }
    }

    private function checkRegions(ConnectionInterface $connection): void
    {
        try {
            $total = (int) $connection->table('regions')->count();
            $expected = (int) config('koperasi.regions.expected_total', 6612);

            if ($total === 0) {
                $status = 'fail';
                $message = 'Tabel regions kosong; impor dataset wilayah resmi.';
            } elseif ($total !== $expected) {
                $status = 'warn';
                $message = "Jumlah regions {$total}, expected {$expected}; verifikasi dataset.";
            } else {
                $status = 'pass';
                $message = "{$total} baris.";
            }

            $this->add('database.regions', 'Data wilayah', $status, $message);
        } catch (Throwable $exception) {
            $this->add(
                'database.regions',
                'Data wilayah',
                'fail',
                'Tidak dapat membaca tabel regions: '.$this->sanitize($exception->getMessage()),
            );
        }
    }

    private function checkActiveAdmin(ConnectionInterface $connection): void
    {
        try {
            $total = (int) $connection->table('users')
                ->join('role_user', 'role_user.user_id', '=', 'users.id')
                ->join('roles', 'roles.id', '=', 'role_user.role_id')
                ->where('roles.name', 'admin_dinas')
                ->where('users.status', 'active')
                ->whereNull('users.deleted_at')
                ->count();

            $this->add(
                'database.admin',
                'Akun admin dinas aktif',
                $total > 0 ? 'pass' : 'fail',
                $total > 0 ? "{$total} akun." : 'Buat akun dengan `php artisan user:create`.',
            );
        } catch (Throwable $exception) {
            $this->add(
                'database.admin',
                'Akun admin dinas aktif',
                'fail',
                'Tidak dapat memeriksa akun: '.$this->sanitize($exception->getMessage()),
            );
        }
    }

    private function add(string $id, string $label, string $status, string $message): void
    {
        $this->checks[] = [
            'id' => $id,
            'label' => $label,
            'status' => $status,
            'message' => $message,
        ];
    }

    private function failed(): int
    {
        return count(array_filter($this->checks, static fn (array $check): bool => $check['status'] === 'fail'));
    }

    private function render(): void
    {
        $summary = [
            'pass' => count(array_filter($this->checks, static fn (array $check): bool => $check['status'] === 'pass')),
            'warn' => count(array_filter($this->checks, static fn (array $check): bool => $check['status'] === 'warn')),
            'fail' => $this->failed(),
        ];

        if ($this->option('json')) {
            $this->line((string) json_encode([
                'generated_at' => now()->toIso8601String(),
                'environment' => config('app.env'),
                'summary' => $summary,
                'checks' => $this->checks,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

            return;
        }

        $this->newLine();
        $this->info('Pemeriksaan kesiapan produksi');
        $this->line('Waktu: '.now()->toIso8601String());
        $this->newLine();

        $this->table(
            ['Status', 'Pemeriksaan', 'Keterangan'],
            array_map(static fn (array $check): array => [
                strtoupper($check['status']),
                $check['label'],
                $check['message'],
            ], $this->checks),
        );

        $this->line(sprintf(
            'Ringkasan: %d PASS, %d WARN, %d FAIL',
            $summary['pass'],
            $summary['warn'],
            $summary['fail'],
        ));

        if ($summary['fail'] > 0) {
            $this->error('BELUM siap produksi. Perbaiki item FAIL lalu ulangi.');
        } elseif ($summary['warn'] > 0) {
            $this->warn('Tidak ada FAIL, tetapi tinjau item WARN sebelum go-live.');
        } else {
            $this->info('Semua pemeriksaan wajib PASS.');
        }
    }

    private function sanitize(string $message): string
    {
        $message = preg_replace('/(password|passwd|pwd)\s*=\s*[^;\s]+/i', '$1=***', $message) ?? $message;
        $message = preg_replace('/:\/\/([^:@\s]+):([^@\s]+)@/', '://$1:***@', $message) ?? $message;

        return mb_strimwidth($message, 0, 300, '...');
    }
}
