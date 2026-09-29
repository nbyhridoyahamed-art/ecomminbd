<?php

namespace Tests\Feature\Purchasing;

use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\Store;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SupplierLedgerTest extends TestCase
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

    /** An ordered PO with one item ($unitCost x $quantity), ready to receive against. */
    private function orderedWithItem(Store $store, Supplier $supplier, int $quantity = 10, int $unitCostMinor = 10000): array
    {
        $warehouse = Warehouse::factory()->for($store)->create();
        $product = Product::factory()->for($store)->create();
        $order = PurchaseOrder::factory()->for($store)->for($warehouse)->for($supplier)->ordered()->create();
        $item = $order->items()->create([
            'product_id' => $product->id,
            'quantity_ordered' => $quantity,
            'unit_cost_amount' => $unitCostMinor,
        ]);

        return compact('warehouse', 'product', 'order', 'item');
    }

    public function test_ledger_lists_a_debit_for_each_receipt_and_computes_a_running_balance(): void
    {
        $admin = $this->admin();
        $store = Store::factory()->create();
        $supplier = Supplier::factory()->for($store)->create();
        ['order' => $order, 'item' => $item] = $this->orderedWithItem($store, $supplier, quantity: 10, unitCostMinor: 10000);

        $this->actingAs($admin, 'sanctum')->postJson("/api/v1/purchase-orders/{$order->id}/receipts", [
            'items' => [['purchase_order_item_id' => $item->id, 'quantity_received' => 10]],
        ])->assertCreated();

        $response = $this->actingAs($admin, 'sanctum')
            ->getJson("/api/v1/suppliers/{$supplier->id}/ledger")
            ->assertOk();

        $response->assertJsonPath('data.currency_code', 'BDT')
            ->assertJsonPath('data.balance_amount', 1000)
            ->assertJsonCount(1, 'data.entries')
            ->assertJsonPath('data.entries.0.type', 'receipt')
            ->assertJsonPath('data.entries.0.debit_amount', 1000)
            ->assertJsonPath('data.entries.0.running_balance', 1000);
    }

    public function test_recording_a_payment_adds_a_credit_and_reduces_the_balance(): void
    {
        $admin = $this->admin();
        $store = Store::factory()->create();
        $supplier = Supplier::factory()->for($store)->create();
        ['order' => $order, 'item' => $item] = $this->orderedWithItem($store, $supplier, quantity: 10, unitCostMinor: 10000);

        $this->actingAs($admin, 'sanctum')->postJson("/api/v1/purchase-orders/{$order->id}/receipts", [
            'items' => [['purchase_order_item_id' => $item->id, 'quantity_received' => 10]],
        ])->assertCreated();

        $this->actingAs($admin, 'sanctum')->postJson("/api/v1/suppliers/{$supplier->id}/payments", [
            'purchase_order_id' => $order->id,
            'amount' => '400.00',
            'method' => 'bank_transfer',
            'reference' => 'TXN-001',
        ])->assertCreated()->assertJsonPath('data.amount', 400);

        $this->assertDatabaseHas('supplier_payments', [
            'supplier_id' => $supplier->id,
            'purchase_order_id' => $order->id,
            'amount_amount' => 40000,
            'method' => 'bank_transfer',
            'reference' => 'TXN-001',
        ]);

        $response = $this->actingAs($admin, 'sanctum')
            ->getJson("/api/v1/suppliers/{$supplier->id}/ledger")
            ->assertOk();

        $response->assertJsonPath('data.balance_amount', 600)
            ->assertJsonCount(2, 'data.entries')
            ->assertJsonPath('data.entries.1.type', 'payment')
            ->assertJsonPath('data.entries.1.credit_amount', 400)
            ->assertJsonPath('data.entries.1.running_balance', 600);
    }

    public function test_a_credited_purchase_return_reduces_the_balance(): void
    {
        $admin = $this->admin();
        $store = Store::factory()->create();
        $supplier = Supplier::factory()->for($store)->create();
        ['order' => $order, 'item' => $item] = $this->orderedWithItem($store, $supplier, quantity: 10, unitCostMinor: 10000);

        $this->actingAs($admin, 'sanctum')->postJson("/api/v1/purchase-orders/{$order->id}/receipts", [
            'items' => [['purchase_order_item_id' => $item->id, 'quantity_received' => 10]],
        ])->assertCreated();

        $return = $this->actingAs($admin, 'sanctum')->postJson("/api/v1/purchase-orders/{$order->id}/returns", [
            'items' => [['purchase_order_item_id' => $item->id, 'quantity' => 2]],
            'reason' => 'Damaged in transit',
        ])->assertCreated()->json('data');

        $this->actingAs($admin, 'sanctum')->postJson("/api/v1/purchase-returns/{$return['id']}/approve")->assertOk();
        $this->actingAs($admin, 'sanctum')->postJson("/api/v1/purchase-returns/{$return['id']}/ship-back")->assertOk();
        $this->actingAs($admin, 'sanctum')->postJson("/api/v1/purchase-returns/{$return['id']}/credit")->assertOk();

        $response = $this->actingAs($admin, 'sanctum')
            ->getJson("/api/v1/suppliers/{$supplier->id}/ledger")
            ->assertOk();

        // 10 units received @ 100.00 = 1000.00 debit; 2 units credited back @ 100.00 = 200.00 credit.
        $response->assertJsonPath('data.balance_amount', 800)
            ->assertJsonCount(2, 'data.entries')
            ->assertJsonPath('data.entries.1.type', 'credit')
            ->assertJsonPath('data.entries.1.credit_amount', 200);
    }

    public function test_only_a_user_with_suppliers_pay_can_record_a_payment(): void
    {
        $store = Store::factory()->create();
        $supplier = Supplier::factory()->for($store)->create();

        // Purchase Manager can create/manage purchase orders and suppliers but
        // deliberately cannot record a payment — a real separation between
        // procurement and disbursement (see DEVELOPMENT_ROADMAP.md's Phase 7
        // Wave 2 scope note).
        $purchaser = User::factory()->create();
        $purchaser->assignRole('Purchase Manager');
        $this->actingAs($purchaser, 'sanctum')
            ->postJson("/api/v1/suppliers/{$supplier->id}/payments", ['amount' => '50.00', 'method' => 'cash'])
            ->assertForbidden();

        $accountant = User::factory()->create();
        $accountant->assignRole('Accountant');
        $this->actingAs($accountant, 'sanctum')
            ->postJson("/api/v1/suppliers/{$supplier->id}/payments", ['amount' => '50.00', 'method' => 'cash'])
            ->assertCreated();
    }

    public function test_viewing_the_ledger_requires_suppliers_view(): void
    {
        $store = Store::factory()->create();
        $supplier = Supplier::factory()->for($store)->create();

        $contentManager = User::factory()->create();
        $contentManager->assignRole('Content Manager');
        $this->actingAs($contentManager, 'sanctum')
            ->getJson("/api/v1/suppliers/{$supplier->id}/ledger")
            ->assertForbidden();
    }
}
