<?php

namespace Tests\Feature\Notifications;

use App\Models\Customer;
use App\Models\Order;
use App\Models\Store;
use App\Models\User;
use App\Models\Warehouse;
use App\Notifications\NewOrderPlacedNotification;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationInboxTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleAndPermissionSeeder::class);
    }

    private function staff(): User
    {
        $user = User::factory()->create();
        $user->assignRole('Super Admin');

        return $user;
    }

    private function notifyWithNewOrder(User $user): void
    {
        $store = Store::factory()->create();
        $order = Order::factory()->for($store)->for(Customer::factory()->for($store))->for(Warehouse::factory()->for($store))->create();

        $user->notify(new NewOrderPlacedNotification($order));
    }

    public function test_a_staff_user_sees_only_their_own_notifications(): void
    {
        $userA = $this->staff();
        $userB = $this->staff();

        $this->notifyWithNewOrder($userA);

        $this->actingAs($userA, 'sanctum')->getJson('/api/v1/notifications')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('meta.unread_count', 1);

        $this->actingAs($userB, 'sanctum')->getJson('/api/v1/notifications')
            ->assertOk()
            ->assertJsonCount(0, 'data')
            ->assertJsonPath('meta.unread_count', 0);
    }

    public function test_marking_a_notification_read(): void
    {
        $user = $this->staff();
        $this->notifyWithNewOrder($user);
        $id = $user->notifications()->firstOrFail()->id;

        $this->actingAs($user, 'sanctum')->postJson("/api/v1/notifications/{$id}/read")
            ->assertOk()
            ->assertJsonPath('data.id', $id);

        $this->assertNotNull($user->notifications()->firstOrFail()->read_at);

        $this->actingAs($user, 'sanctum')->getJson('/api/v1/notifications')
            ->assertJsonPath('meta.unread_count', 0);
    }

    public function test_marking_all_notifications_read(): void
    {
        $user = $this->staff();
        $this->notifyWithNewOrder($user);
        $this->notifyWithNewOrder($user);

        $this->actingAs($user, 'sanctum')->postJson('/api/v1/notifications/read-all')->assertOk();

        $this->assertSame(0, $user->unreadNotifications()->count());
    }

    public function test_a_user_cannot_mark_another_users_notification_as_read(): void
    {
        $userA = $this->staff();
        $userB = $this->staff();
        $this->notifyWithNewOrder($userA);
        $id = $userA->notifications()->firstOrFail()->id;

        $this->actingAs($userB, 'sanctum')->postJson("/api/v1/notifications/{$id}/read")->assertNotFound();

        $this->assertNull($userA->notifications()->firstOrFail()->read_at);
    }
}
