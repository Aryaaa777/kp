<?php

namespace App\Console\Commands;

use App\Models\Role;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

/**
 * Membuat akun pengguna internal (admin dinas / pengelola koperasi).
 *
 * Password diminta secara interaktif melalui prompt tersembunyi dan TIDAK
 * pernah ditanam pada source code maupun seeder.
 */
class CreateUserCommand extends Command
{
    protected $signature = 'user:create
        {--name= : Nama pengguna}
        {--email= : Email pengguna}
        {--role= : Peran (admin_dinas atau pengelola_koperasi)}
        {--koperasi= : ID koperasi untuk pengelola}';

    protected $description = 'Membuat akun pengguna internal dengan password yang diminta secara interaktif';

    public function handle(): int
    {
        $name = $this->option('name') ?: $this->ask('Nama pengguna');
        $email = $this->option('email') ?: $this->ask('Email pengguna');
        $roleName = $this->option('role') ?: $this->choice(
            'Peran',
            ['admin_dinas', 'pengelola_koperasi'],
            0,
        );
        $password = $this->secret('Password (minimal 8 karakter)');

        $koperasiId = null;

        if ($roleName === 'pengelola_koperasi') {
            $koperasiId = $this->option('koperasi') ?: $this->ask('ID koperasi (boleh dikosongkan)');
            $koperasiId = (is_string($koperasiId) && trim($koperasiId) !== '') ? (int) $koperasiId : null;
        }

        $validator = Validator::make([
            'name' => $name,
            'email' => $email,
            'password' => $password,
            'role' => $roleName,
        ], [
            'name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'string', 'email', 'max:150', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
            'role' => ['required', 'in:admin_dinas,pengelola_koperasi'],
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        $role = Role::firstOrCreate(
            ['name' => $roleName],
            ['display_name' => $roleName === 'admin_dinas' ? 'Admin Dinas' : 'Pengelola Koperasi'],
        );

        $user = User::create([
            'name' => $name,
            'email' => $email,
            'password' => Hash::make($password),
            'status' => 'active',
            'koperasi_id' => $koperasiId,
        ]);

        $user->roles()->sync([$role->id]);

        $this->info("Akun '{$user->email}' berhasil dibuat dengan peran {$role->display_name}.");

        return self::SUCCESS;
    }
}
