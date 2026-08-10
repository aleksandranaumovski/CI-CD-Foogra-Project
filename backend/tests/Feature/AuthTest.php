<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // The login controller throttles per email+IP; a leftover counter from
        // a previous test would fail the next one for the wrong reason.
        RateLimiter::clear('login:test@foogra.test|127.0.0.1');
    }

    public function test_a_visitor_can_register_and_receives_a_token(): void
    {
        $response = $this->postJson("{$this->api}/auth/register", [
            'name' => 'New Diner',
            'email' => 'new@foogra.test',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
        ]);

        $response->assertCreated()
            ->assertJsonPath('user.email', 'new@foogra.test')
            ->assertJsonPath('user.role', 'customer')
            ->assertJsonStructure(['message', 'token', 'user' => ['id', 'name', 'email', 'role']]);

        $this->assertDatabaseHas('users', ['email' => 'new@foogra.test', 'role' => 'customer']);
    }

    public function test_a_visitor_can_register_as_a_restaurant_owner(): void
    {
        $this->postJson("{$this->api}/auth/register", [
            'name' => 'New Owner',
            'email' => 'owner@foogra.test',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
            'role' => 'owner',
        ])->assertCreated()->assertJsonPath('user.role', 'owner');
    }

    public function test_registration_cannot_self_assign_the_admin_role(): void
    {
        $this->postJson("{$this->api}/auth/register", [
            'name' => 'Sneaky',
            'email' => 'sneaky@foogra.test',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
            'role' => 'admin',
        ])->assertStatus(422)->assertJsonValidationErrors('role');

        $this->assertDatabaseMissing('users', ['email' => 'sneaky@foogra.test']);
    }

    public function test_registration_rejects_a_duplicate_email(): void
    {
        User::factory()->create(['email' => 'taken@foogra.test']);

        $this->postJson("{$this->api}/auth/register", [
            'name' => 'Someone',
            'email' => 'taken@foogra.test',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
        ])->assertStatus(422)->assertJsonValidationErrors('email');
    }

    public function test_registration_rejects_a_weak_password(): void
    {
        $this->postJson("{$this->api}/auth/register", [
            'name' => 'Someone',
            'email' => 'weak@foogra.test',
            'password' => 'short',
            'password_confirmation' => 'short',
        ])->assertStatus(422)->assertJsonValidationErrors('password');
    }

    public function test_a_user_can_sign_in_with_correct_credentials(): void
    {
        User::factory()->create([
            'email' => 'test@foogra.test',
            'password' => Hash::make('secret123'),
        ]);

        $this->postJson("{$this->api}/auth/login", [
            'email' => 'test@foogra.test',
            'password' => 'secret123',
        ])->assertOk()->assertJsonStructure(['message', 'token', 'user']);
    }

    public function test_sign_in_fails_with_a_wrong_password(): void
    {
        User::factory()->create([
            'email' => 'test@foogra.test',
            'password' => Hash::make('secret123'),
        ]);

        $this->postJson("{$this->api}/auth/login", [
            'email' => 'test@foogra.test',
            'password' => 'wrong-password',
        ])->assertStatus(422)->assertJsonValidationErrors('email');
    }

    public function test_sign_in_does_not_reveal_whether_an_account_exists(): void
    {
        $unknown = $this->postJson("{$this->api}/auth/login", [
            'email' => 'nobody@foogra.test',
            'password' => 'secret123',
        ]);

        User::factory()->create(['email' => 'test@foogra.test', 'password' => Hash::make('secret123')]);

        $wrongPassword = $this->postJson("{$this->api}/auth/login", [
            'email' => 'test@foogra.test',
            'password' => 'nope',
        ]);

        $this->assertSame(
            $unknown->json('errors.email'),
            $wrongPassword->json('errors.email'),
            'Both failures must return an identical message.'
        );
    }

    public function test_repeated_failed_sign_ins_are_throttled(): void
    {
        User::factory()->create(['email' => 'test@foogra.test', 'password' => Hash::make('secret123')]);

        foreach (range(1, 5) as $ignored) {
            $this->postJson("{$this->api}/auth/login", [
                'email' => 'test@foogra.test',
                'password' => 'wrong',
            ])->assertStatus(422);
        }

        // Even the correct password is refused once the limiter trips. The
        // countdown in the message is not asserted — it ticks between requests.
        $response = $this->postJson("{$this->api}/auth/login", [
            'email' => 'test@foogra.test',
            'password' => 'secret123',
        ])->assertStatus(422);

        $this->assertStringContainsString(
            'Too many sign-in attempts',
            $response->json('errors.email.0')
        );
    }

    public function test_the_current_user_endpoint_requires_a_token(): void
    {
        $this->getJson("{$this->api}/auth/me")->assertUnauthorized();
    }

    public function test_the_current_user_endpoint_returns_the_signed_in_user(): void
    {
        $user = $this->actingAsUser($this->customer(['name' => 'Chris Customer']));

        $this->getJson("{$this->api}/auth/me")
            ->assertOk()
            ->assertJsonPath('user.id', $user->id)
            ->assertJsonPath('user.name', 'Chris Customer');
    }

    public function test_a_user_can_update_their_profile(): void
    {
        $user = $this->actingAsUser($this->customer());

        $this->patchJson("{$this->api}/auth/profile", [
            'name' => 'Renamed Person',
            'phone' => '+44 20 1234 5678',
        ])->assertOk()->assertJsonPath('user.name', 'Renamed Person');

        $this->assertDatabaseHas('users', ['id' => $user->id, 'name' => 'Renamed Person']);
    }

    /**
     * A seeded demo avatar lives in the frontend's public folder, not on the
     * upload disk. Resolving it as an upload produced a /storage/img/... URL
     * that 403s, so every reviewer on the detail page had a broken portrait.
     */
    public function test_a_seeded_template_avatar_resolves_to_the_public_folder(): void
    {
        $user = $this->actingAsUser($this->customer(['avatar_path' => 'img/avatar_2.svg']));

        $this->getJson("{$this->api}/auth/me")
            ->assertOk()
            ->assertJsonPath('user.avatar_url', '/img/avatar_2.svg');

        $this->assertSame('/img/avatar_2.svg', $user->fresh()->avatar_url);
    }

    public function test_an_uploaded_avatar_still_resolves_to_the_storage_disk(): void
    {
        $user = $this->actingAsUser($this->customer(['avatar_path' => 'avatars/abc-123.jpg']));

        $url = $user->fresh()->avatar_url;

        $this->assertStringContainsString('/storage/avatars/abc-123.jpg', (string) $url);
    }

    public function test_an_absolute_avatar_url_is_passed_through_untouched(): void
    {
        $user = $this->actingAsUser($this->customer([
            'avatar_path' => 'https://example.test/portrait.jpg',
        ]));

        $this->assertSame('https://example.test/portrait.jpg', $user->fresh()->avatar_url);
    }

    public function test_a_user_without_an_avatar_reports_null(): void
    {
        $user = $this->actingAsUser($this->customer(['avatar_path' => null]));

        $this->assertNull($user->fresh()->avatar_url);
    }

    public function test_changing_the_email_clears_the_verification_timestamp(): void
    {
        $user = $this->actingAsUser($this->customer(['email_verified_at' => now()]));

        $this->patchJson("{$this->api}/auth/profile", ['email' => 'moved@foogra.test'])->assertOk();

        $this->assertNull($user->fresh()->email_verified_at);
    }

    public function test_a_user_can_change_their_password_with_the_current_one(): void
    {
        $user = $this->actingAsUser($this->customer(['password' => Hash::make('secret123')]));

        $this->putJson("{$this->api}/auth/password", [
            'current_password' => 'secret123',
            'password' => 'brandnew456',
            'password_confirmation' => 'brandnew456',
        ])->assertOk();

        $this->assertTrue(Hash::check('brandnew456', $user->fresh()->password));
    }

    public function test_changing_the_password_requires_the_current_one(): void
    {
        $this->actingAsUser($this->customer(['password' => Hash::make('secret123')]));

        $this->putJson("{$this->api}/auth/password", [
            'current_password' => 'not-it',
            'password' => 'brandnew456',
            'password_confirmation' => 'brandnew456',
        ])->assertStatus(422)->assertJsonValidationErrors('current_password');
    }

    public function test_a_forgotten_password_request_never_reveals_whether_the_address_exists(): void
    {
        Notification::fake();

        $known = $this->postJson("{$this->api}/auth/forgot-password", ['email' => 'nobody@foogra.test']);

        $known->assertOk()->assertJsonPath(
            'message',
            'If that address is registered, a reset link is on its way.'
        );
    }

    public function test_signing_out_revokes_only_the_current_token(): void
    {
        $user = $this->customer();
        $keep = $user->createToken('other-device')->plainTextToken;
        $current = $user->createToken('this-device')->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$current}")
            ->postJson("{$this->api}/auth/logout")
            ->assertOk();

        $this->assertSame(1, $user->tokens()->count(), 'The other device should stay signed in.');
        $this->assertNotEmpty($keep);
    }

    public function test_role_helpers_reflect_the_stored_role(): void
    {
        $this->assertTrue($this->admin()->isAdmin());
        $this->assertTrue($this->owner()->isOwner());
        $this->assertTrue($this->customer()->isCustomer());
        $this->assertTrue($this->owner()->isStaff());
        $this->assertFalse($this->customer()->isStaff());
        $this->assertSame(UserRole::Admin, $this->admin()->role);
    }
}
