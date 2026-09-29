<?php

namespace Tests\Feature\Delivery;

use App\Models\BdDistrict;
use App\Models\BdDivision;
use App\Models\DeliveryZone;
use App\Models\Store;
use App\Models\User;
use App\Support\DeliveryRateResolver;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeliveryZoneTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleAndPermissionSeeder::class);
    }

    private function deliveryManager(): User
    {
        $user = User::factory()->create();
        $user->assignRole('Delivery Manager');

        return $user;
    }

    /** @return array{division: BdDivision, district: BdDistrict} */
    private function location(): array
    {
        $division = BdDivision::create(['name_en' => 'Dhaka', 'name_bn' => 'ঢাকা', 'code' => 'DHK']);
        $district = BdDistrict::create(['bd_division_id' => $division->id, 'name_en' => 'Gazipur', 'name_bn' => 'গাজীপুর', 'code' => 'GAZ']);

        return compact('division', 'district');
    }

    public function test_delivery_zone_crud_is_gated_on_delivery_zones_permissions(): void
    {
        $store = Store::factory()->create();
        $manager = $this->deliveryManager();
        $orderManager = User::factory()->create();
        $orderManager->assignRole('Order Manager'); // no delivery_zones.* permissions at all

        $this->actingAs($orderManager, 'sanctum')->getJson('/api/v1/delivery-zones?store_id='.$store->id)->assertForbidden();

        $create = $this->actingAs($manager, 'sanctum')->postJson('/api/v1/delivery-zones', [
            'store_id' => $store->id,
            'name' => 'Inside Dhaka',
            'rates' => [['min_order_subtotal' => 0, 'rate_amount' => 60]],
        ])->assertCreated()->assertJsonPath('data.name', 'Inside Dhaka');

        $zoneId = $create->json('data.id');

        $this->actingAs($orderManager, 'sanctum')->putJson("/api/v1/delivery-zones/{$zoneId}", [
            'store_id' => $store->id,
            'name' => 'Renamed',
            'rates' => [['min_order_subtotal' => 0, 'rate_amount' => 60]],
        ])->assertForbidden();

        $this->actingAs($manager, 'sanctum')->deleteJson("/api/v1/delivery-zones/{$zoneId}")->assertOk();
        $this->assertDatabaseMissing('delivery_zones', ['id' => $zoneId]);
    }

    public function test_a_district_without_a_division_is_rejected(): void
    {
        $store = Store::factory()->create();
        $manager = $this->deliveryManager();
        ['district' => $district] = $this->location();

        $this->actingAs($manager, 'sanctum')->postJson('/api/v1/delivery-zones', [
            'store_id' => $store->id,
            'name' => 'Bad zone',
            'bd_district_id' => $district->id,
            'rates' => [['min_order_subtotal' => 0, 'rate_amount' => 60]],
        ])->assertStatus(422);
    }

    public function test_a_duplicate_zone_for_the_same_exact_location_is_rejected(): void
    {
        $store = Store::factory()->create();
        $manager = $this->deliveryManager();
        ['division' => $division] = $this->location();
        DeliveryZone::factory()->for($store)->create(['bd_division_id' => $division->id, 'bd_district_id' => null]);

        $this->actingAs($manager, 'sanctum')->postJson('/api/v1/delivery-zones', [
            'store_id' => $store->id,
            'name' => 'Also Dhaka',
            'bd_division_id' => $division->id,
            'rates' => [['min_order_subtotal' => 0, 'rate_amount' => 60]],
        ])->assertStatus(422);
    }

    public function test_a_second_default_zone_is_rejected(): void
    {
        $store = Store::factory()->create();
        $manager = $this->deliveryManager();
        DeliveryZone::factory()->for($store)->create(['name' => 'Everywhere else']);

        $this->actingAs($manager, 'sanctum')->postJson('/api/v1/delivery-zones', [
            'store_id' => $store->id,
            'name' => 'Another default',
            'rates' => [['min_order_subtotal' => 0, 'rate_amount' => 60]],
        ])->assertStatus(422);
    }

    public function test_rates_must_include_a_zero_minimum_tier(): void
    {
        $store = Store::factory()->create();
        $manager = $this->deliveryManager();

        $this->actingAs($manager, 'sanctum')->postJson('/api/v1/delivery-zones', [
            'store_id' => $store->id,
            'name' => 'No base tier',
            'rates' => [['min_order_subtotal' => 500, 'rate_amount' => 60]],
        ])->assertStatus(422);
    }

    public function test_rate_tiers_must_have_distinct_minimum_amounts(): void
    {
        $store = Store::factory()->create();
        $manager = $this->deliveryManager();

        $this->actingAs($manager, 'sanctum')->postJson('/api/v1/delivery-zones', [
            'store_id' => $store->id,
            'name' => 'Dupe tiers',
            'rates' => [
                ['min_order_subtotal' => 0, 'rate_amount' => 60],
                ['min_order_subtotal' => 0, 'rate_amount' => 0],
            ],
        ])->assertStatus(422);
    }

    public function test_quote_prefers_the_most_specific_zone_match(): void
    {
        $store = Store::factory()->create();
        ['division' => $division, 'district' => $district] = $this->location();

        DeliveryZone::factory()->for($store)->create(['name' => 'Default', 'bd_division_id' => null, 'bd_district_id' => null]);
        DeliveryZone::factory()->for($store)->create(['name' => 'Dhaka division', 'bd_division_id' => $division->id, 'bd_district_id' => null]);
        $districtZone = DeliveryZone::factory()->for($store)->create([
            'name' => 'Gazipur district', 'bd_division_id' => $division->id, 'bd_district_id' => $district->id,
        ]);
        $districtZone->rates()->update(['rate_amount' => 3000]);

        $resolved = DeliveryRateResolver::resolve($store->id, $division->id, $district->id, 100000);

        $this->assertSame($districtZone->id, $resolved['zone']->id);
        $this->assertSame(3000, $resolved['rate_amount']);
    }

    public function test_quote_falls_back_to_division_then_to_the_store_default(): void
    {
        $store = Store::factory()->create();
        ['division' => $division, 'district' => $district] = $this->location();
        $otherDistrict = BdDistrict::create(['bd_division_id' => $division->id, 'name_en' => 'Tangail', 'name_bn' => 'টাঙ্গাইল', 'code' => 'TNG']);

        $defaultZone = DeliveryZone::factory()->for($store)->create(['name' => 'Default']);
        $divisionZone = DeliveryZone::factory()->for($store)->create(['name' => 'Dhaka division', 'bd_division_id' => $division->id]);

        // No zone covers this exact district -> falls back to the division-wide zone.
        $resolved = DeliveryRateResolver::resolve($store->id, $division->id, $otherDistrict->id, 100000);
        $this->assertSame($divisionZone->id, $resolved['zone']->id);

        // A division this store has no zone for at all -> falls back to the store default.
        $resolved = DeliveryRateResolver::resolve($store->id, 999999, null, 100000);
        $this->assertSame($defaultZone->id, $resolved['zone']->id);
    }

    public function test_quote_picks_the_highest_tier_the_subtotal_meets(): void
    {
        $store = Store::factory()->create();
        $zone = DeliveryZone::factory()->for($store)->create(['name' => 'Default']);
        $zone->rates()->update(['rate_amount' => 6000]); // base tier: 60.00
        $zone->rates()->create(['min_order_subtotal_amount' => 200000, 'rate_amount' => 0, 'currency_code' => 'BDT']); // free over 2000.00

        $this->assertSame(6000, DeliveryRateResolver::resolve($store->id, null, null, 100000)['rate_amount']);
        $this->assertSame(0, DeliveryRateResolver::resolve($store->id, null, null, 250000)['rate_amount']);
    }

    public function test_quote_returns_null_when_no_zone_is_configured(): void
    {
        $store = Store::factory()->create();

        $this->assertNull(DeliveryRateResolver::resolve($store->id, null, null, 100000));
    }

    public function test_the_admin_quote_endpoint_is_reachable_by_any_staff_member(): void
    {
        $store = Store::factory()->create();
        DeliveryZone::factory()->for($store)->create(['name' => 'Default']);
        $orderManager = User::factory()->create();
        $orderManager->assignRole('Order Manager'); // no delivery_zones.* permissions

        $this->actingAs($orderManager, 'sanctum')
            ->getJson("/api/v1/delivery-zones/quote?store_id={$store->id}&subtotal=100")
            ->assertOk()
            ->assertJsonPath('data.shipping_amount', 60);
    }

    public function test_the_storefront_quote_endpoint_requires_no_authentication(): void
    {
        $store = Store::factory()->create(['status' => 'active']);
        DeliveryZone::factory()->for($store)->create(['name' => 'Default']);

        $this->getJson('/api/v1/storefront/delivery-zones/quote?subtotal=100')
            ->assertOk()
            ->assertJsonPath('data.shipping_amount', 60);
    }
}
