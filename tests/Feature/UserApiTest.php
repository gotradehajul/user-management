<?php

namespace Tests\Feature;

use App\Mail\AdminNewUserNotificationMail;
use App\Mail\UserCreatedMail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class UserApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_user_creates_record_and_sends_two_emails(): void
    {
        Mail::fake();

        User::factory()->create([
            'role' => 'admin',
            'email' => 'admin@example.com',
            'active' => true,
        ]);

        $response = $this->postJson('/api/users', [
            'email' => 'john@example.com',
            'password' => 'password123',
            'name' => 'John Doe',
        ]);

        $response->assertCreated()
            ->assertJsonStructure([
                'id',
                'email',
                'name',
                'created_at',
            ])
            ->assertJsonMissingPath('password');

        $this->assertDatabaseHas('users', [
            'email' => 'john@example.com',
            'name' => 'John Doe',
            'role' => 'user',
            'active' => 1,
        ]);

        Mail::assertSent(UserCreatedMail::class, function (UserCreatedMail $mail) {
            return $mail->hasTo('john@example.com');
        });

        Mail::assertSent(AdminNewUserNotificationMail::class, function (AdminNewUserNotificationMail $mail) {
            return $mail->hasTo('admin@example.com');
        });
    }

    public function test_create_user_validates_required_fields(): void
    {
        $response = $this->postJson('/api/users', [
            'email' => 'invalid-email',
            'password' => '123',
            'name' => 'Jo',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['email', 'password', 'name']);
    }

    public function test_get_users_requires_authentication(): void
    {
        $this->getJson('/api/users')->assertUnauthorized();
    }

    public function test_get_users_supports_search_sort_and_orders_count(): void
    {
        $authUser = User::factory()->create([
            'role' => 'admin',
            'email' => 'auth@internal.test',
        ]);
        $older = User::factory()->create([
            'name' => 'Alpha Name',
            'email' => 'alpha@example.com',
            'created_at' => now()->subDays(2),
        ]);
        $newer = User::factory()->create([
            'name' => 'Beta Name',
            'email' => 'beta@example.com',
            'created_at' => now()->subDay(),
        ]);
        $inactive = User::factory()->create([
            'name' => 'Inactive Name',
            'active' => false,
        ]);

        $older->orders()->createMany([[], []]);
        $newer->orders()->createMany([[]]);
        $inactive->orders()->createMany([[], [], []]);

        $response = $this
            ->actingAs($authUser)
            ->getJson('/api/users?search=example.com&sortBy=name&page=1');

        $response->assertOk()
            ->assertJsonPath('page', 1)
            ->assertJsonCount(2, 'users')
            ->assertJsonPath('users.0.email', 'alpha@example.com')
            ->assertJsonPath('users.0.orders_count', 2)
            ->assertJsonPath('users.1.email', 'beta@example.com')
            ->assertJsonPath('users.1.orders_count', 1)
            ->assertJsonMissing([
                ['email' => $inactive->email],
            ]);
    }

    public function test_get_users_returns_can_edit_based_on_role_rules(): void
    {
        $manager = User::factory()->create(['role' => 'manager']);
        $targetUser = User::factory()->create(['role' => 'user', 'name' => 'Target User']);
        $targetManager = User::factory()->create(['role' => 'manager', 'name' => 'Target Manager']);

        $managerResponse = $this->actingAs($manager)->getJson('/api/users?search=Target');

        $managerResponse->assertOk()
            ->assertJsonCount(2, 'users');

        $managerUsers = collect($managerResponse->json('users'));
        $targetUserEntry = $managerUsers->firstWhere('id', $targetUser->id);
        $targetManagerEntry = $managerUsers->firstWhere('id', $targetManager->id);

        $this->assertTrue($targetUserEntry['can_edit']);
        $this->assertFalse($targetManagerEntry['can_edit']);

        $user = User::factory()->create(['role' => 'user']);
        $userResponse = $this->actingAs($user)->getJson('/api/users');

        $thisEntry = collect($userResponse->json('users'))
            ->firstWhere('id', $user->id);
        $targetEntry = collect($userResponse->json('users'))
            ->firstWhere('id', $targetUser->id);

        $this->assertTrue($thisEntry['can_edit']);
        $this->assertFalse($targetEntry['can_edit']);
    }

    public function test_update_user_with_middleware_allows_and_blocks_by_role_rules(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $manager = User::factory()->create(['role' => 'manager']);
        $normalUser = User::factory()->create(['role' => 'user', 'name' => 'Normal User']);
        $otherManager = User::factory()->create(['role' => 'manager', 'name' => 'Other Manager']);

        $this->actingAs($admin)
            ->patchJson("/api/users/{$otherManager->id}", ['name' => 'Updated By Admin'])
            ->assertOk()
            ->assertJsonPath('name', 'Updated By Admin');

        $this->actingAs($manager)
            ->patchJson("/api/users/{$normalUser->id}", ['name' => 'Updated By Manager'])
            ->assertOk()
            ->assertJsonPath('name', 'Updated By Manager');

        $this->actingAs($manager)
            ->patchJson("/api/users/{$otherManager->id}", ['name' => 'Should Fail'])
            ->assertForbidden();

        $this->actingAs($normalUser)
            ->patchJson("/api/users/{$normalUser->id}", ['name' => 'Updated Self'])
            ->assertOk()
            ->assertJsonPath('name', 'Updated Self');

        $this->actingAs($normalUser)
            ->patchJson("/api/users/{$manager->id}", ['name' => 'Should Also Fail'])
            ->assertForbidden();
    }
}
