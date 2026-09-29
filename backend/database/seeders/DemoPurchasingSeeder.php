<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\PurchaseReceipt;
use App\Models\PurchaseReceiptItem;
use App\Models\StockLevel;
use App\Models\StockMovement;
use App\Models\Store;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use Database\Seeders\Concerns\BackdatesTimestamps;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * 10 suppliers and 20 purchase orders spanning every status in the
 * approval workflow. The "received" ones are the store's only source of
 * opening stock — receiving is replayed exactly the way
 * PurchaseReceiptController does it (same StockMovement type and
 * before/after bookkeeping) so Inventory and Purchasing agree with each
 * other from the very first row.
 */
class DemoPurchasingSeeder extends Seeder
{
    use BackdatesTimestamps;

    private Store $store;

    private User $admin;

    public function run(): void
    {
        $this->store = Store::where('slug', 'eleventory-flagship-store')->firstOrFail();
        $this->admin = User::where('email', 'admin@eleventory.test')->firstOrFail();

        $suppliers = $this->createSuppliers();
        $products = Product::where('store_id', $this->store->id)->orderBy('id')->get();

        $dhaka = Warehouse::where('store_id', $this->store->id)->where('code', 'MAIN-DHK')->firstOrFail();
        $chattogram = Warehouse::where('store_id', $this->store->id)->where('code', 'BR-CTG')->firstOrFail();
        $sylhet = Warehouse::where('store_id', $this->store->id)->where('code', 'BR-SYL')->firstOrFail();

        $mainChunks = $products->chunk(5)->values(); // 10 chunks of 5 -> full 50-product coverage
        $ctgSubset = $products->slice(0, 15)->values()->chunk(5)->values(); // 3 chunks of 5
        $sylSubset = $products->slice(15, 10)->values()->chunk(5)->values(); // 2 chunks of 5

        $poNumber = 1;

        // Dhaka Main: 10 POs, fully received, one full pass over the catalog.
        foreach ($mainChunks as $chunk) {
            $this->createPurchaseOrder($poNumber++, $dhaka, $suppliers->random(), $chunk, 'received', 120, 300);
        }

        // Dhaka Main: an 11th PO re-ordering 5 already-stocked products (a realistic top-up reorder).
        $this->createPurchaseOrder($poNumber++, $dhaka, $suppliers->random(), $products->random(5), 'received', 80, 150);

        // Chattogram Branch: 3 POs, fully received, a 15-product regional subset.
        foreach ($ctgSubset as $chunk) {
            $this->createPurchaseOrder($poNumber++, $chattogram, $suppliers->random(), $chunk, 'received', 60, 150);
        }

        // Sylhet Branch: 2 POs, fully received, a 10-product regional subset.
        foreach ($sylSubset as $chunk) {
            $this->createPurchaseOrder($poNumber++, $sylhet, $suppliers->random(), $chunk, 'received', 60, 150);
        }

        // The remaining 4 POs exercise the rest of the approval workflow with no stock effect yet.
        $this->createPurchaseOrder($poNumber++, $dhaka, $suppliers->random(), $products->random(3), 'ordered', 50, 120);
        $this->createPurchaseOrder($poNumber++, $chattogram, $suppliers->random(), $products->random(3), 'pending_approval', 50, 120);
        $this->createPurchaseOrder($poNumber++, $dhaka, $suppliers->random(), $products->random(2), 'draft', 50, 120);
        $this->createPurchaseOrder($poNumber, $sylhet, $suppliers->random(), $products->random(2), 'cancelled', 50, 120);
    }

    /** @return Collection<int, Supplier> */
    private function createSuppliers(): Collection
    {
        $suppliers = [
            ['Karim Traders', 'Dhaka', 'net_30'],
            ['Bengal Wholesale Mart', 'Dhaka', 'net_15'],
            ['Chattogram Electronics Hub', 'Chattogram', 'net_30'],
            ['Dhaka Garments Supply Co.', 'Dhaka', 'due_on_receipt'],
            ['Green Delta Distributors', 'Dhaka', 'net_30'],
            ['Meghna Agro Foods', 'Cumilla', 'due_on_receipt'],
            ['Rupali Fashion Suppliers', 'Narayanganj', 'net_15'],
            ['Sundarban Import & Export', 'Chattogram', 'net_60'],
            ['Gazipur Textile Mills', 'Gazipur', 'net_30'],
            ['Padma Home Essentials', 'Dhaka', 'net_15'],
        ];

        return collect($suppliers)->map(function (array $row, int $index) {
            [$name, $city, $terms] = $row;
            $slugSafeName = strtolower(str_replace([' ', '.', '&'], ['', '', 'and'], $name));

            return Supplier::updateOrCreate(
                ['store_id' => $this->store->id, 'email' => "contact@{$slugSafeName}.test"],
                [
                    'name' => $name,
                    'contact_name' => ['Md. Aminul Islam', 'Rezaul Karim', 'Shirin Akter', 'Jahangir Alam', 'Nasima Begum'][$index % 5],
                    'phone' => '019'.str_pad((string) (10000000 + $index), 8, '0', STR_PAD_LEFT),
                    'address' => "{$city}, Bangladesh",
                    'payment_terms' => $terms,
                    'status' => 'active',
                ],
            );
        });
    }

    /** @param  Collection<int, Product>  $items */
    private function createPurchaseOrder(
        int $sequence,
        Warehouse $warehouse,
        Supplier $supplier,
        $items,
        string $status,
        int $minQty,
        int $maxQty,
    ): void {
        $createdAt = now()->subDays(random_int(5, 150));

        $order = PurchaseOrder::create([
            'store_id' => $this->store->id,
            'warehouse_id' => $warehouse->id,
            'supplier_id' => $supplier->id,
            'po_number' => 'PO-'.$createdAt->format('Ymd').'-'.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT),
            'status' => 'draft',
            'currency_code' => 'BDT',
            'created_by' => $this->admin->id,
        ]);
        $this->backdate($order, $createdAt);

        $isReceived = $status === 'received';
        $orderItems = [];

        foreach ($items as $product) {
            $qtyOrdered = random_int($minQty, $maxQty);

            $orderItems[] = $order->items()->create([
                'product_id' => $product->id,
                'quantity_ordered' => $qtyOrdered,
                'quantity_received' => $isReceived ? $qtyOrdered : 0,
                'unit_cost_amount' => $product->cost_price_amount,
            ]);
        }

        $history = ['draft'];

        if (in_array($status, ['pending_approval', 'ordered', 'received', 'cancelled'], true)) {
            $history[] = $status === 'cancelled' ? 'cancelled' : 'pending_approval';
        }

        if (in_array($status, ['ordered', 'received'], true)) {
            $history[] = 'ordered';
        }

        if ($isReceived) {
            $history[] = 'received';
        }

        $this->recordStatusHistory($order, $history, $createdAt);
        $order->update(['status' => $status]);

        if ($isReceived) {
            $this->receiveOrder($order, $orderItems, $createdAt->copy()->addDays(random_int(2, 6)));
        }
    }

    /** @param  array<int, string>  $transitions */
    private function recordStatusHistory(PurchaseOrder $order, array $transitions, Carbon $startedAt): void
    {
        $from = null;

        foreach ($transitions as $index => $to) {
            $entry = $order->statusHistory()->create([
                'from_status' => $from,
                'to_status' => $to,
                'created_by' => $this->admin->id,
            ]);
            $this->backdate($entry, $startedAt->copy()->addDays($index));
            $from = $to;
        }
    }

    /** @param  array<int, PurchaseOrderItem>  $orderItems */
    private function receiveOrder(PurchaseOrder $order, array $orderItems, Carbon $receivedAt): void
    {
        $receipt = PurchaseReceipt::create([
            'store_id' => $this->store->id,
            'purchase_order_id' => $order->id,
            'receipt_number' => 'GRN-'.$receivedAt->format('Ymd').'-'.Str::upper(Str::random(6)),
            'note' => 'Full receipt against '.$order->po_number,
            'received_by' => $this->admin->id,
        ]);
        $this->backdate($receipt, $receivedAt);

        foreach ($orderItems as $orderItem) {
            PurchaseReceiptItem::create([
                'purchase_receipt_id' => $receipt->id,
                'purchase_order_item_id' => $orderItem->id,
                'quantity_received' => $orderItem->quantity_received,
            ]);

            $level = StockLevel::query()
                ->where('product_id', $orderItem->product_id)
                ->where('warehouse_id', $order->warehouse_id)
                ->whereNull('product_variant_id')
                ->first();

            $before = $level?->quantity ?? 0;
            $after = $before + $orderItem->quantity_received;

            if ($level) {
                $level->update(['quantity' => $after]);
            } else {
                StockLevel::create([
                    'product_id' => $orderItem->product_id,
                    'warehouse_id' => $order->warehouse_id,
                    'quantity' => $after,
                    'quantity_reserved' => 0,
                ]);
            }

            $movement = StockMovement::create([
                'store_id' => $this->store->id,
                'product_id' => $orderItem->product_id,
                'warehouse_id' => $order->warehouse_id,
                'type' => 'purchase_receipt',
                'quantity' => $orderItem->quantity_received,
                'quantity_before' => $before,
                'quantity_after' => $after,
                'reference_type' => PurchaseReceipt::class,
                'reference_id' => $receipt->id,
                'created_by' => $this->admin->id,
            ]);
            $this->backdate($movement, $receivedAt);
        }
    }
}
