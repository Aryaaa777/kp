<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

/**
 * Reset password (FR-004).
 *
 * Catatan: mail memakai `MAIL_MAILER=array` pada test dan `Notification::fake()`,
 * sehingga tidak ada email sungguhan yang dikirim.
 */
class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_can_open_forgot_password_page(): void
    {
        $this->get(route('password.request'))
            ->assertOk()
            ->assertSee('Lupa password');
    }

    public function test_reset_link_request_is_generic_for_known_and_unknown_email(): void
    {
        Notification::fake();

        $user = User::factory()->admin()->create(['email' => 'known@example.test']);

        $this->post(route('password.email'), ['email' => $user->email])
            ->assertSessionHas('status');

        Notification::assertSentTo($user, ResetPassword::class);

        $this->post(route('password.email'), ['email' => 'unknown@example.test'])
            ->assertSessionHas('status');

        Notification::assertCount(1);
    }

    public function test_reset_token_is_stored_hashed_not_as_plain_text(): void
    {
        $user = User::factory()->admin()->create();

        $token = Password::broker()->createToken($user);

        $stored = DB::table('password_reset_tokens')->where('email', $user->email)->value('token');

        $this->assertNotNull($stored);
        $this->assertNotSame($token, $stored);
        $this->assertTrue(Hash::check($token, (string) $stored));
    }

    public function test_valid_token_resets_password_and_invalidates_sessions(): void
    {
        config(['session.driver' => 'database']);

        $user = User::factory()->admin()->create([
            'password' => 'password-lama',
            'remember_token' => 'token-lama',
        ]);

        DB::table('sessions')->insert([
            'id' => 'sesi-lama-pengguna',
            'user_id' => $user->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Test Browser',
            'payload' => 'payload',
            'last_activity' => now()->timestamp,
        ]);

        $token = Password::broker()->createToken($user);

        $this->post(route('password.store'), [
            'token' => $token,
            'email' => $user->email,
            'password' => 'password-baru-kuat',
            'password_confirmation' => 'password-baru-kuat',
        ])
            ->assertRedirect(route('login'))
            ->assertSessionHas('status');

        $user->refresh();

        $this->assertTrue(Hash::check('password-baru-kuat', $user->password));
        $this->assertFalse(Hash::check('password-lama', $user->password));
        $this->assertNotSame('token-lama', $user->remember_token);
        $this->assertDatabaseMissing('sessions', ['id' => 'sesi-lama-pengguna']);
    }

    public function test_expired_token_is_rejected(): void
    {
        $user = User::factory()->admin()->create(['password' => 'password-lama']);
        $token = Password::broker()->createToken($user);

        $this->travel(61)->minutes();

        $this->post(route('password.store'), [
            'token' => $token,
            'email' => $user->email,
            'password' => 'password-baru-kuat',
            'password_confirmation' => 'password-baru-kuat',
        ])->assertSessionHasErrors('email');

        $this->assertTrue(Hash::check('password-lama', $user->fresh()->password));
    }

    public function test_invalid_and_reused_tokens_are_rejected(): void
    {
        $user = User::factory()->admin()->create(['password' => 'password-lama']);

        $this->post(route('password.store'), [
            'token' => 'token-tidak-valid',
            'email' => $user->email,
            'password' => 'password-baru-kuat',
            'password_confirmation' => 'password-baru-kuat',
        ])->assertSessionHasErrors('email');

        $token = Password::broker()->createToken($user);

        $this->post(route('password.store'), [
            'token' => $token,
            'email' => $user->email,
            'password' => 'password-baru-kuat',
            'password_confirmation' => 'password-baru-kuat',
        ])->assertRedirect(route('login'));

        // Token yang sama tidak boleh dipakai dua kali.
        $this->post(route('password.store'), [
            'token' => $token,
            'email' => $user->email,
            'password' => 'password-lain-lagi',
            'password_confirmation' => 'password-lain-lagi',
        ])->assertSessionHasErrors('email');
    }

    public function test_password_reset_validates_weak_and_unconfirmed_password(): void
    {
        $user = User::factory()->admin()->create();
        $token = Password::broker()->createToken($user);

        $this->post(route('password.store'), [
            'token' => $token,
            'email' => $user->email,
            'password' => 'pendek',
            'password_confirmation' => 'berbeda',
        ])->assertSessionHasErrors('password');
    }

    public function test_reset_link_request_is_rate_limited(): void
    {
        Notification::fake();

        $user = User::factory()->admin()->create();

        for ($attempt = 0; $attempt < 3; $attempt++) {
            $this->post(route('password.email'), ['email' => $user->email])
                ->assertSessionHasNoErrors();
        }

        $this->post(route('password.email'), ['email' => $user->email])
            ->assertSessionHasErrors('email');
    }
}
