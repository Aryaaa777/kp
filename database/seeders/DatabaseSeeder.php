<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     *
     * Hanya menyiapkan data referensi (peran). Tidak membuat akun dengan
     * password default. Untuk membuat akun admin, gunakan:
     *   php artisan user:create
     */
    public function run(): void
    {
        $this->call(RoleSeeder::class);
    }
}
