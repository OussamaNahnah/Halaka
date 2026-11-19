<?php

namespace Tests\Feature\Api;

use App\Actions\SaveTeacher;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class TeacherAuthTest extends TestCase
{
    use RefreshDatabase;

    private function teacher()
    {
        $admin = User::factory()->create();
        $role = Role::create(['name' => 'Administrator']);
        $admin->roles()->attach($role);
        $this->actingAs($admin);
        $teacher = app(SaveTeacher::class)->execute(['name' => 'Teacher', 'email' => 'teacher@example.test', 'password' => 'password123']);
        Auth::forgetGuards();

        return $teacher;
    }

    private function token(): string
    {
        return $this->postJson('/api/login', ['email' => 'teacher@example.test', 'password' => 'password123'])->assertOk()->assertJsonPath('token_type', 'Bearer')->json('access_token');
    }

    public function test_login_has_no_expiration_and_profile_exposes_only_public_account_fields(): void
    {
        $teacher = $this->teacher();
        $token = $this->token();
        $payload = json_decode(base64_decode(strtr(explode('.', $token)[1], '-_', '+/')), true);
        $this->assertArrayNotHasKey('exp', $payload);
        $this->assertArrayNotHasKey('password', $payload);
        $this->getJson('/api/profile', ['Authorization' => 'Bearer '.$token])->assertOk()->assertExactJson(['data' => ['id' => $teacher->user_id, 'teacher_id' => $teacher->id, 'name' => 'Teacher', 'email' => 'teacher@example.test']])->assertHeader('Cache-Control', 'no-store, private');
    }

    public function test_logout_is_persistent_even_after_cache_clear(): void
    {
        $this->teacher();
        $token = $this->token();
        $headers = ['Authorization' => 'Bearer '.$token];
        $this->postJson('/api/logout', [], $headers)->assertNoContent();
        Cache::flush();
        Auth::forgetGuards();
        $this->getJson('/api/profile', $headers)->assertUnauthorized();
        $this->assertDatabaseCount('jwt_revocations', 1);
    }

    public function test_non_teacher_archived_and_bad_credentials_are_rejected(): void
    {
        $teacher = $this->teacher();
        $this->postJson('/api/login', ['email' => 'teacher@example.test', 'password' => 'wrong'])->assertUnprocessable();
        $teacher->delete();
        $this->postJson('/api/login', ['email' => 'teacher@example.test', 'password' => 'password123'])->assertUnprocessable();
        $this->postJson('/api/login', ['email' => 'unknown@example.test', 'password' => 'password123'])->assertUnprocessable();
    }

    public function test_tokens_recheck_archive_roles_passwords_and_signature_on_every_request(): void
    {
        $teacher = $this->teacher();
        $token = $this->token();
        $headers = ['Authorization' => 'Bearer '.$token];
        $this->getJson('/api/profile', $headers)->assertOk();
        $teacher->delete();
        $this->getJson('/api/profile', $headers)->assertForbidden();
        $teacher->restore();
        $teacher->user->update(['password' => 'different-password']);
        $this->getJson('/api/profile', $headers)->assertUnauthorized();
        $this->getJson('/api/profile', ['Authorization' => 'Bearer '.substr($token, 0, -5).'abcde'])->assertUnauthorized();
        $this->getJson('/api/profile?token='.$token)->assertUnauthorized();
    }

    public function test_password_change_validates_current_password_and_revokes_all_old_tokens(): void
    {
        $teacher = $this->teacher();
        $one = $this->token();
        $two = $this->token();
        $headers = ['Authorization' => 'Bearer '.$one];
        $this->putJson('/api/profile/password', ['current_password' => 'bad', 'password' => 'new-password', 'password_confirmation' => 'new-password'], $headers)->assertUnprocessable();
        $this->putJson('/api/profile/password', ['current_password' => 'password123', 'password' => 'new-password', 'password_confirmation' => 'new-password'], $headers)->assertOk();
        $this->assertTrue(Hash::check('new-password', $teacher->user->fresh()->password));
        foreach ([$one, $two] as $token) {
            $this->getJson('/api/profile', ['Authorization' => 'Bearer '.$token])->assertUnauthorized();
        }
        $this->postJson('/api/login', ['email' => 'teacher@example.test', 'password' => 'new-password'])->assertOk();
    }

    public function test_login_is_rate_limited_and_guest_api_errors_are_json(): void
    {
        $this->get('/api/profile')->assertUnauthorized()->assertJsonStructure(['message']);
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/login', ['email' => 'missing@example.test', 'password' => 'wrong'])->assertUnprocessable();
        }
        $this->postJson('/api/login', ['email' => 'missing@example.test', 'password' => 'wrong'])->assertTooManyRequests();
    }

    public function test_teacher_role_is_required_and_token_has_no_time_limit(): void
    {
        $teacher = $this->teacher();
        $token = $this->token();
        $this->travel(5)->years();
        $this->getJson('/api/profile', ['Authorization' => 'Bearer '.$token])->assertOk();
        $this->travelBack();
        $teacher->user->roles()->detach();
        $this->getJson('/api/profile', ['Authorization' => 'Bearer '.$token])->assertForbidden();
        $this->postJson('/api/login', ['email' => 'teacher@example.test', 'password' => 'password123'])->assertUnprocessable();
    }
}
