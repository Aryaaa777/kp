<?php

namespace Tests\Feature\Console;

use App\Models\Cooperative;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CreateUserCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_an_admin_with_a_prompted_password(): void
    {
        $this->artisan('user:create')
            ->expectsQuestion('Nama pengguna', 'Budi Santoso')
            ->expectsQuestion('Email pengguna', 'budi@example.test')
            ->expectsQuestion('Peran', 'admin_dinas')
            ->expectsQuestion('Password (minimal 8 karakter)', 'rahasia-kuat')
            ->assertSuccessful();

        $user = User::where('email', 'budi@example.test')->first();

        $this->assertNotNull($user);
        $this->assertTrue($user->hasRole('admin_dinas'));
        $this->assertSame('active', $user->status);
        $this->assertNotSame('rahasia-kuat', $user->password);
        $this->assertTrue(Hash::check('rahasia-kuat', $user->password));
    }

    public function test_it_rejects_a_weak_password(): void
    {
        $this->artisan('user:create')
            ->expectsQuestion('Nama pengguna', 'Budi')
            ->expectsQuestion('Email pengguna', 'budi2@example.test')
            ->expectsQuestion('Peran', 'admin_dinas')
            ->expectsQuestion('Password (minimal 8 karakter)', 'pendek')
            ->assertExitCode(1);

        $this->assertDatabaseMissing('users', ['email' => 'budi2@example.test']);
    }

    public function test_it_prompts_for_koperasi_when_creating_a_pengelola(): void
    {
        $cooperative = Cooperative::factory()->create();

        $this->artisan('user:create')
            ->expectsQuestion('Nama pengguna', 'Dedi')
            ->expectsQuestion('Email pengguna', 'dedi@example.test')
            ->expectsQuestion('Peran', 'pengelola_koperasi')
            ->expectsQuestion('Password (minimal 8 karakter)', 'rahasia-kuat')
            ->expectsQuestion('ID koperasi (boleh dikosongkan)', (string) $cooperative->id)
            ->assertSuccessful();

        $user = User::where('email', 'dedi@example.test')->first();

        $this->assertNotNull($user);
        $this->assertTrue($user->hasRole('pengelola_koperasi'));
        $this->assertSame($cooperative->id, $user->koperasi_id);
    }

    public function test_it_rejects_an_unknown_koperasi_id(): void
    {
        $this->artisan('user:create')
            ->expectsQuestion('Nama pengguna', 'Dedi')
            ->expectsQuestion('Email pengguna', 'dedi@example.test')
            ->expectsQuestion('Peran', 'pengelola_koperasi')
            ->expectsQuestion('Password (minimal 8 karakter)', 'rahasia-kuat')
            ->expectsQuestion('ID koperasi (boleh dikosongkan)', '99999')
            ->assertExitCode(1);

        $this->assertDatabaseMissing('users', ['email' => 'dedi@example.test']);
    }

    public function test_it_allows_a_pengelola_without_koperasi(): void
    {
        $this->artisan('user:create')
            ->expectsQuestion('Nama pengguna', 'Dedi')
            ->expectsQuestion('Email pengguna', 'dedi@example.test')
            ->expectsQuestion('Peran', 'pengelola_koperasi')
            ->expectsQuestion('Password (minimal 8 karakter)', 'rahasia-kuat')
            ->expectsQuestion('ID koperasi (boleh dikosongkan)', '')
            ->assertSuccessful();

        $user = User::where('email', 'dedi@example.test')->first();

        $this->assertNotNull($user);
        $this->assertNull($user->koperasi_id);
    }
}
