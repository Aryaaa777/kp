<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreUserRequest;
use App\Http\Requests\Admin\UpdateUserRequest;
use App\Http\Requests\Admin\UpdateUserStatusRequest;
use App\Models\Cooperative;
use App\Models\Region;
use App\Models\Role;
use App\Models\User;
use App\Services\ActiveSessionService;
use App\Services\ActivityLogger;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Throwable;

/**
 * Manajemen akun pengelola & admin dinas (US-009).
 *
 * Password tidak pernah ditampilkan atau disimpan sebagai teks biasa. Akun baru
 * dibuat dengan password acak yang tidak diketahui admin, lalu tautan reset
 * dikirim ke email akun. Admin juga dapat memicu tautan reset ulang.
 */
class AdminUserController extends Controller
{
    /**
     * @var array<string, string>
     */
    private const ROLE_LABELS = [
        'admin_dinas' => 'Admin Dinas',
        'admin_wilayah' => 'Admin Wilayah',
        'pengelola_koperasi' => 'Pengelola Koperasi',
    ];

    public function __construct(
        private readonly ActivityLogger $logger,
        private readonly ActiveSessionService $sessions,
    ) {}

    public function index(Request $request): View
    {
        Gate::authorize('manage-accounts');

        $filters = $this->filters($request);

        $users = User::query()
            ->with(['roles', 'koperasi', 'region'])
            ->when($filters['q'] !== '', function (Builder $query) use ($filters): void {
                $term = '%'.$filters['q'].'%';

                $query->where(function (Builder $query) use ($term): void {
                    $query->where('name', 'like', $term)
                        ->orWhere('email', 'like', $term)
                        ->orWhereHas('koperasi', fn (Builder $cooperative) => $cooperative->where('nama', 'like', $term));
                });
            })
            ->when($filters['role'] !== '', fn (Builder $query) => $query->whereHas(
                'roles',
                fn (Builder $role) => $role->where('name', $filters['role']),
            ))
            ->when($filters['status'] !== '', fn (Builder $query) => $query->where('status', $filters['status']))
            ->orderBy('name')
            ->orderBy('id')
            ->paginate(15)
            ->withQueryString();

        return view('admin.akun.index', [
            'users' => $users,
            'filters' => $filters,
            'roleLabels' => self::ROLE_LABELS,
        ]);
    }

    public function create(): View
    {
        Gate::authorize('manage-accounts');

        return view('admin.akun.create', [
            'user' => new User,
            'cooperatives' => $this->cooperatives(),
            'regions' => $this->regions(),
            'roleLabels' => self::ROLE_LABELS,
        ]);
    }

    public function store(StoreUserRequest $request): RedirectResponse
    {
        Gate::authorize('manage-accounts');

        $data = $request->validated();
        $roleName = (string) $data['role'];
        $koperasiId = $roleName === 'pengelola_koperasi' ? ($data['koperasi_id'] ?? null) : null;
        $regionId = $roleName === 'admin_wilayah' ? ($data['region_id'] ?? null) : null;

        $user = DB::transaction(function () use ($data, $roleName, $koperasiId, $regionId): User {
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                // Password acak; cast `hashed` menyimpannya sebagai hash. Nilai ini
                // tidak pernah ditampilkan dan tidak dapat dipakai admin untuk login.
                'password' => Str::random(40),
                'koperasi_id' => $koperasiId,
                'region_id' => $regionId,
                'status' => 'active',
            ]);

            $user->roles()->sync([$this->role($roleName)->getKey()]);

            return $user;
        });

        $this->logger->log(
            'create_user',
            $user,
            $request->user(),
            ['role' => $roleName, 'koperasi_id' => $koperasiId, 'region_id' => $regionId],
            'Akun pengguna dibuat oleh admin dinas.',
        );

        $sent = $this->sendResetLink($user);

        return redirect()
            ->route('admin.akun.index')
            ->with('status', $sent
                ? 'Akun berhasil dibuat. Tautan reset password telah dikirim ke email akun.'
                : 'Akun berhasil dibuat, tetapi tautan reset belum terkirim. Gunakan tombol "Kirim tautan reset".');
    }

    public function edit(User $user): View
    {
        Gate::authorize('manage-accounts');

        $user->load(['roles', 'koperasi', 'region']);

        return view('admin.akun.edit', [
            'user' => $user,
            'cooperatives' => $this->cooperatives(),
            'regions' => $this->regions(),
            'roleLabels' => self::ROLE_LABELS,
        ]);
    }

    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        Gate::authorize('manage-accounts');

        $data = $request->validated();
        $roleName = (string) $data['role'];

        if ($request->user()->is($user) && $roleName !== 'admin_dinas') {
            return back()
                ->withInput()
                ->withErrors(['role' => 'Anda tidak dapat mengubah peran akun sendiri.']);
        }

        $koperasiId = $roleName === 'pengelola_koperasi' ? ($data['koperasi_id'] ?? null) : null;
        $regionId = $roleName === 'admin_wilayah' ? ($data['region_id'] ?? null) : null;
        $before = [
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->roles->first()?->name,
            'koperasi_id' => $user->koperasi_id,
            'region_id' => $user->region_id,
        ];

        DB::transaction(function () use ($user, $data, $roleName, $koperasiId, $regionId): void {
            $user->update([
                'name' => $data['name'],
                'email' => $data['email'],
                'koperasi_id' => $koperasiId,
                'region_id' => $regionId,
            ]);

            $user->roles()->sync([$this->role($roleName)->getKey()]);
        });

        $user->refresh()->load(['roles', 'koperasi', 'region']);

        $this->logger->log(
            'update_user',
            $user,
            $request->user(),
            [
                'before' => $before,
                'after' => [
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->roles->first()?->name,
                    'koperasi_id' => $user->koperasi_id,
                    'region_id' => $user->region_id,
                ],
            ],
            'Akun pengguna diperbarui oleh admin dinas.',
        );

        return redirect()
            ->route('admin.akun.index')
            ->with('status', 'Akun pengguna berhasil diperbarui.');
    }

    public function updateStatus(UpdateUserStatusRequest $request, User $user): RedirectResponse
    {
        Gate::authorize('manage-accounts');

        $status = (string) $request->validated('status');

        if ($request->user()->is($user)) {
            return back()->withErrors(['status' => 'Anda tidak dapat mengubah status akun sendiri.']);
        }

        if ($status === 'inactive' && $user->hasRole('admin_dinas')) {
            $activeAdmins = User::query()
                ->where('status', 'active')
                ->whereHas('roles', fn (Builder $role) => $role->where('name', 'admin_dinas'))
                ->count();

            if ($activeAdmins <= 1) {
                return back()->withErrors([
                    'status' => 'Akun admin aktif terakhir tidak dapat dinonaktifkan.',
                ]);
            }
        }

        $user->update(['status' => $status]);

        if ($status === 'inactive') {
            // Putus sesi yang masih berjalan bila driver mendukungnya.
            $this->sessions->terminateAll($user);
        }

        $this->logger->log(
            $status === 'active' ? 'activate_user' : 'deactivate_user',
            $user,
            $request->user(),
            ['status' => $status],
            $status === 'active' ? 'Akun pengguna diaktifkan.' : 'Akun pengguna dinonaktifkan.',
        );

        return back()->with('status', $status === 'active'
            ? 'Akun pengguna diaktifkan.'
            : 'Akun pengguna dinonaktifkan dan sesi aktifnya diputus.');
    }

    public function sendReset(User $user): RedirectResponse
    {
        Gate::authorize('manage-accounts');

        $sent = $this->sendResetLink($user);

        $this->logger->log(
            'request_user_password_reset',
            $user,
            request()->user(),
            [],
            'Admin meminta tautan reset password untuk akun pengguna.',
        );

        return back()->with(
            $sent ? 'status' : 'error',
            $sent
                ? 'Tautan reset password telah dikirim ke email akun.'
                : 'Tautan reset belum dapat dikirim. Periksa konfigurasi email atau coba lagi nanti.',
        );
    }

    /**
     * @return array{q: string, role: string, status: string}
     */
    private function filters(Request $request): array
    {
        $q = trim((string) $request->query('q', ''));
        $role = (string) $request->query('role', '');
        $status = (string) $request->query('status', '');

        if (! array_key_exists($role, self::ROLE_LABELS)) {
            $role = '';
        }

        if (! in_array($status, ['active', 'inactive'], true)) {
            $status = '';
        }

        return ['q' => $q, 'role' => $role, 'status' => $status];
    }

    /**
     * @return Collection<int, Cooperative>
     */
    private function cooperatives(): Collection
    {
        return Cooperative::query()
            ->orderBy('nama')
            ->get(['id', 'nama']);
    }

    /**
     * Pilihan wilayah untuk admin_wilayah (provinsi/kabupaten/kota).
     *
     * @return Collection<int, Region>
     */
    private function regions(): Collection
    {
        return Region::query()
            ->whereIn('type', ['province', 'regency', 'city'])
            ->orderBy('type')
            ->orderBy('name')
            ->get(['id', 'name', 'type']);
    }

    private function role(string $name): Role
    {
        return Role::query()->firstOrCreate(
            ['name' => $name],
            ['display_name' => self::ROLE_LABELS[$name] ?? $name],
        );
    }

    private function sendResetLink(User $user): bool
    {
        try {
            $status = Password::sendResetLink(['email' => $user->email]);

            return $status === Password::RESET_LINK_SENT;
        } catch (Throwable $exception) {
            report($exception);

            return false;
        }
    }
}
