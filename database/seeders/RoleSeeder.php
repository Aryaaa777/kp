<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    /**
     * Seed daftar peran.
     *
     * Catatan: `masyarakat` adalah peran konteks publik (akses tanpa login).
     * Sesuai PRD §8, masyarakat tidak memiliki akun login, sehingga peran ini
     * disediakan untuk kelengkapan pemodelan dan tidak ditautkan ke akun mana pun.
     */
    public function run(): void
    {
        $roles = [
            [
                'name' => 'admin_dinas',
                'display_name' => 'Admin Dinas',
                'description' => 'Petugas dinas: mengelola, memverifikasi, dan mempublikasikan data koperasi.',
            ],
            [
                'name' => 'admin_wilayah',
                'display_name' => 'Admin Wilayah',
                'description' => 'Petugas dinas dengan kewenangan terbatas pada wilayah tertentu (FR-012, PROVISIONAL).',
            ],
            [
                'name' => 'pengelola_koperasi',
                'display_name' => 'Pengelola Koperasi',
                'description' => 'Perwakilan koperasi yang memperbarui data koperasinya untuk diverifikasi.',
            ],
            [
                'name' => 'masyarakat',
                'display_name' => 'Masyarakat',
                'description' => 'Konteks publik tanpa login (akses direktori dan statistik yang dipublikasikan).',
            ],
        ];

        foreach ($roles as $role) {
            Role::updateOrCreate(['name' => $role['name']], $role);
        }
    }
}
