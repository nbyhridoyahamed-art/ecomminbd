<?php

namespace Tests\Feature\Delivery;

use App\Models\Courier;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Shipment;
use App\Models\Store;
use App\Models\User;
use App\Models\Warehouse;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CodSettlementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleAndPermissionSeeder::class);
    }

    private function admin(): User
    {
        $user = User::factory()->create();
        $user->assignRole('Super Admin');

        return $user;
    }

    private function deliveredCodShipment(Store $store, Courier $courier, int $codAmountMinor): Shipment
    {
        $customer = Customer::factory()->for($store)->create();
        $warehouse = Warehouse::factory()->for($store)->create();
        $order = Order::factory()->for($store)->for($customer)->for($warehouse)->create([
            'status' => 'delivered',
            'payment_method' => 'cod',
            'payment_status' => 'paid',
        ]);

        return Shipment::factory()->for($store)->for($order)->for($courier)->create([
            'status' => 'delivered',
            'cod_amount_collected' => $codAmountMinor,
            'delivered_at' => now(),
        ]);
    }

    public function test_a_settlement_can_be_recorded_covering_delivered_cod_shipments_and_marks_them_settled(): void
    {
        $admin = $this->admin();
        $store = Store::factory()->create();
        $courier = Courier::factory()->for($store)->create();
        $shipmentA = $this->deliveredCodShipment($store, $courier, 10000);
        $shipmentB = $this->deliveredCodShipment($store, $courier, 15000);

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/v1/cod-settlements', [
                'store_id' => $store->id,
                'courier_id' => $courier->id,
                'shipment_ids' => [$shipmentA->id, $shipmentB->id],
                'amount_received' => '240.00',
                'note' => 'Courier deducted a 10 taka handling fee.',
            ])
            ->assertCreated()
            ->assertJsonPath('data.amount_expected', 250)
            ->assertJsonPath('data.amount_received', 240)
            ->assertJsonCount(2, 'data.shipments');

        $this->assertDatabaseHas('shipments', ['id' => $shipmentA->id, 'cod_settled' => true]);
        $this->assertDatabaseHas('shipments', ['id' => $shipmentB->id, 'cod_settled' => true]);
    }

    public function test_a_settlement_cannot_include_an_already_settled_shipment(): void
    {
        $admin = $this->admin();
        $store = Store::factory()->create();
        $courier = Courier::factory()->for($store)->create();
        $shipment = $this->deliveredCodShipment($store, $courier, 10000);
        $shipment->update(['cod_settled' => true]);

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/v1/cod-settlements', [
                'store_id' => $store->id,
                'courier_id' => $courier->id,
                'shipment_ids' => [$shipment->id],
                'amount_received' => '100.00',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('shipment_ids');
    }

    public function test_a_settlement_cannot_include_a_shipment_that_is_not_delivered_or_not_cod(): void
    {
        $admin = $this->admin();
        $store = Store::factory()->create();
        $courier = Courier::factory()->for($store)->create();

        $customer = Customer::factory()->for($store)->create();
        $warehouse = Warehouse::factory()->for($store)->create();

        $inTransitOrder = Order::factory()->for($store)->for($customer)->for($warehouse)->create(['status' => 'shipped', 'payment_method' => 'cod']);
        $inTransitShipment = Shipment::factory()->for($store)->for($inTransitOrder)->for($courier)->create(['status' => 'in_transit']);

        $nonCodOrder = Order::factory()->for($store)->for($customer)->for($warehouse)->create(['status' => 'delivered', 'payment_method' => 'bkash']);
        $nonCodShipment = Shipment::factory()->for($store)->for($nonCodOrder)->for($courier)->create(['status' => 'delivered']);

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/v1/cod-settlements', [
                'store_id' => $store->id,
                'courier_id' => $courier->id,
                'shipment_ids' => [$inTransitShipment->id],
                'amount_received' => '100.00',
            ])
            ->assertUnprocessable();

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/v1/cod-settlements', [
                'store_id' => $store->id,
                'courier_id' => $courier->id,
                'shipment_ids' => [$nonCodShipment->id],
                'amount_received' => '100.00',
            ])
            ->assertUnprocessable();
    }

    public function test_a_user_without_cod_settlements_view_is_forbidden(): void
    {
        $viewer = User::factory()->create();
        $viewer->assignRole('Warehouse Staff');
        $store = Store::factory()->create();

        $this->actingAs($viewer, 'sanctum')
            ->getJson("/api/v1/cod-settlements?store_id={$store->id}")
            ->assertForbidden();
    }
}
