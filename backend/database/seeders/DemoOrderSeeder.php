<?php

namespace Database\Seeders;

use App\Models\BdDivision;
use App\Models\CodSettlement;
use App\Models\Coupon;
use App\Models\Courier;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Shipment;
use App\Models\StockLevel;
use App\Models\StockMovement;
use App\Models\Store;
use App\Models\User;
use App\Models\Warehouse;
use App\Support\CouponException;
use App\Support\CouponResolver;
use App\Support\OrderPlacement;
use Database\Seeders\Concerns\BackdatesTimestamps;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * 200 orders replaying the same lifecycle OrderController/ShipmentController
 * enforce (reserve -> convert to sale on ship -> deliver, or release on
 * cancel) so Inventory/Reports/Dashboard all agree with what Orders shows.
 * Payment method is weighted the way Bangladeshi e-commerce actually
 * skews (COD dominant); non-COD methods get a matching Payment record the
 * same way OrderPaymentController would produce.
 */
class DemoOrderSeeder extends Seeder
{
    use BackdatesTimestamps;

    private Store $store;

    private User $admin;

    /** @var array<string, int> "warehouseId:productId" => remaining available qty */
    private array $availability = [];

    /** @var array<int, array<int, int>> warehouseId => list of product ids stocked there */
    private array $productsByWarehouse = [];

    /** @var array<int, int> product_id => price_amount, cached across the whole run */
    private array $priceCache = [];

    public function run(): void
    {
        $this->store = Store::where('slug', 'eleventory-flagship-store')->firstOrFail();
        $this->admin = User::where('email', 'admin@eleventory.test')->firstOrFail();

        $warehouses = Warehouse::where('store_id', $this->store->id)->get()->keyBy('code');
        $dhaka = $warehouses['MAIN-DHK'];
        $chattogram = $warehouses['BR-CTG'];
        $sylhet = $warehouses['BR-SYL'];

        $this->loadAvailability([$dhaka->id, $chattogram->id, $sylhet->id]);

        $customers = Customer::with(['addresses' => fn ($q) => $q->where('is_default', true)])
            ->where('store_id', $this->store->id)
            ->get()
            ->filter(fn (Customer $c) => $c->addresses->isNotEmpty())
            ->values();

        $couriers = Courier::where('store_id', $this->store->id)->get();
        $activeCoupons = Coupon::where('store_id', $this->store->id)->where('status', 'active')->get();

        $divisionIds = BdDivision::whereIn('code', ['DHK', 'CTG', 'SYL'])->get()->keyBy('code')->map->id;

        $statusPlan = $this->shuffledPlan([
            'pending' => 20, 'processing' => 20, 'shipped' => 30, 'delivered' => 110, 'cancelled' => 20,
        ]);
        $paymentPlan = $this->shuffledPlan([
            'cod' => 130, 'bkash' => 30, 'nagad' => 20, 'rocket' => 10, 'card' => 6, 'bank_transfer' => 4,
        ]);
        $warehousePlan = $this->shuffledPlan([
            (string) $dhaka->id => 140, (string) $chattogram->id => 30, (string) $sylhet->id => 30,
        ]);

        $notesPool = [
            'Please call before delivery.', 'Leave with the security guard if not home.',
            'Deliver after 6pm on weekdays.', 'Please double-check the size before shipping.',
        ];

        for ($i = 0; $i < 200; $i++) {
            $status = $statusPlan[$i];
            $paymentMethod = $paymentPlan[$i];
            $warehouseId = (int) $warehousePlan[$i];
            $customer = $customers[$i % $customers->count()];
            $address = $customer->addresses->first();
            $courier = $couriers->random();
            $source = random_int(1, 100) <= 55 ? 'admin' : 'storefront';

            $items = $this->pickItems($warehouseId);

            if (empty($items)) {
                continue;
            }

            $subtotalMinor = array_sum(array_map(fn ($it) => $it['quantity'] * $it['unit_price_minor'], $items));

            $shippingMinor = match ($address->bd_division_id) {
                $divisionIds['DHK'] ?? null => 6000,
                $divisionIds['CTG'] ?? null => 8000,
                $divisionIds['SYL'] ?? null => 9000,
                default => 13000,
            };

            $coupon = null;
            $discountMinor = 0;

            if (random_int(1, 100) <= 15 && $activeCoupons->isNotEmpty()) {
                try {
                    $resolved = CouponResolver::resolve($this->store->id, $activeCoupons->random()->code, $subtotalMinor, $customer->id);
                    $coupon = $resolved['coupon'];
                    $discountMinor = $resolved['discount_amount'];
                } catch (CouponException) {
                    // Doesn't meet this coupon's minimum/limits — this order just doesn't use one.
                }
            }

            $createdAt = match ($status) {
                'pending' => now()->subHours(random_int(1, 72)),
                'processing' => now()->subDays(random_int(1, 5)),
                'shipped' => now()->subDays(random_int(3, 20)),
                'delivered' => now()->subDays(random_int(5, 175)),
                'cancelled' => now()->subDays(random_int(1, 60)),
            };

            DB::transaction(function () use (
                $items, $status, $paymentMethod, $warehouseId, $customer, $address, $courier, $source,
                $subtotalMinor, $shippingMinor, $coupon, $discountMinor, $createdAt, $i, $notesPool,
            ) {
                $order = Order::create([
                    'store_id' => $this->store->id,
                    'order_number' => 'ORD-'.$createdAt->format('Ymd').'-'.Str::upper(Str::random(4)).sprintf('%04d', $i),
                    'customer_id' => $customer->id,
                    'warehouse_id' => $warehouseId,
                    'status' => 'pending',
                    'payment_method' => $paymentMethod,
                    'payment_status' => 'unpaid',
                    'source' => $source,
                    'currency_code' => 'BDT',
                    'shipping_amount' => $shippingMinor,
                    'discount_amount' => $discountMinor,
                    'store_credit_amount' => 0,
                    'customer_address_id' => $address->id,
                    'shipping_recipient_name' => $address->recipient_name,
                    'shipping_phone' => $address->phone,
                    'shipping_address_line' => $address->address_line,
                    'shipping_bd_division_id' => $address->bd_division_id,
                    'shipping_bd_district_id' => $address->bd_district_id,
                    'shipping_bd_upazila_id' => $address->bd_upazila_id,
                    'notes' => random_int(1, 100) <= 20 ? $notesPool[array_rand($notesPool)] : null,
                    'created_by' => $source === 'admin' ? $this->admin->id : null,
                ]);
                $this->backdate($order, $createdAt);

                if ($coupon) {
                    CouponResolver::recordUsage($coupon, $order, $discountMinor);
                }

                OrderPlacement::syncItems($order, array_map(fn ($it) => [
                    'product_id' => $it['product_id'],
                    'quantity' => $it['quantity'],
                    'unit_price' => $it['unit_price_minor'] / 100,
                ], $items), 'BDT');
                OrderPlacement::reserveItems($order);

                $this->addHistory($order, null, 'pending', $createdAt);

                $isCod = $paymentMethod === 'cod';

                if (! $isCod) {
                    $payment = Payment::create([
                        'store_id' => $this->store->id,
                        'order_id' => $order->id,
                        'amount_amount' => $subtotalMinor + $shippingMinor - $discountMinor,
                        'currency_code' => 'BDT',
                        'method' => $paymentMethod,
                        'reference' => strtoupper(Str::random(10)),
                        'created_by' => $source === 'admin' ? $this->admin->id : null,
                    ]);
                    $this->backdate($payment, $createdAt->copy()->addMinutes(random_int(1, 20)));
                    $order->update(['payment_status' => 'paid']);
                }

                if ($status === 'pending') {
                    return;
                }

                if ($status === 'cancelled') {
                    $cancelAt = $createdAt->copy()->addHours(random_int(2, 48));
                    $order->load('items.components');
                    $this->releaseReservation($order);
                    $order->update(['status' => 'cancelled']);
                    $this->addHistory($order, 'pending', 'cancelled', $cancelAt);

                    return;
                }

                $processingAt = $createdAt->copy()->addHours(random_int(2, 12));
                $order->update(['status' => 'processing']);
                $this->addHistory($order, 'pending', 'processing', $processingAt);

                if ($status === 'processing') {
                    return;
                }

                $shippedAt = $processingAt->copy()->addHours(random_int(6, 36));
                $this->convertReservationToSale($order, $shippedAt);
                $order->update(['status' => 'shipped']);
                $this->addHistory($order, 'processing', 'shipped', $shippedAt);

                $shipmentStatus = $status === 'delivered'
                    ? 'delivered'
                    : ['pending_pickup', 'picked_up', 'in_transit'][array_rand(['pending_pickup', 'picked_up', 'in_transit'])];

                $deliveredAt = $shippedAt->copy()->addDays(random_int(1, 4));
                $codCollected = $isCod ? ($subtotalMinor + $shippingMinor - $discountMinor) : null;

                $shipment = Shipment::create([
                    'store_id' => $this->store->id,
                    'order_id' => $order->id,
                    'courier_id' => $courier->id,
                    'tracking_number' => strtoupper(Str::random(3)).'-'.$shippedAt->format('ymd').sprintf('%04d', $i),
                    'status' => 'pending_pickup',
                    'delivery_charge_amount' => $order->shipping_amount,
                    'cod_amount_collected' => $shipmentStatus === 'delivered' ? $codCollected : null,
                    'delivered_at' => $shipmentStatus === 'delivered' ? $deliveredAt : null,
                    'created_by' => $this->admin->id,
                ]);
                $this->backdate($shipment, $shippedAt);
                $this->addShipmentHistory($shipment, null, 'pending_pickup', $shippedAt);

                $subStatuses = ['pending_pickup', 'picked_up', 'in_transit', 'delivered'];
                $targetIndex = array_search($shipmentStatus, $subStatuses, true);

                for ($s = 1; $s <= $targetIndex; $s++) {
                    $at = $shippedAt->copy()->addHours($s * random_int(6, 24));
                    $this->addShipmentHistory($shipment, $subStatuses[$s - 1], $subStatuses[$s], $at);
                }

                if ($shipmentStatus === 'delivered') {
                    $order->update(['status' => 'delivered']);
                    $this->addHistory($order, 'shipped', 'delivered', $deliveredAt, 'Delivered by courier.');

                    if ($isCod) {
                        $order->update(['payment_status' => 'paid']);
                    }
                }
            });
        }

        $this->settleCodShipments();
    }

    /** @return array<int, array{product_id:int, quantity:int, unit_price_minor:int}> */
    private function pickItems(int $warehouseId): array
    {
        $candidates = collect($this->productsByWarehouse[$warehouseId] ?? [])
            ->filter(fn (int $productId) => ($this->availability["{$warehouseId}:{$productId}"] ?? 0) > 0)
            ->shuffle()
            ->take(random_int(1, 4));

        $items = [];

        foreach ($candidates as $productId) {
            $key = "{$warehouseId}:{$productId}";
            $qty = min(random_int(1, 3), $this->availability[$key]);

            if ($qty <= 0) {
                continue;
            }

            $this->availability[$key] -= $qty;

            $items[] = [
                'product_id' => $productId,
                'quantity' => $qty,
                'unit_price_minor' => $this->productPrice($productId),
            ];
        }

        return $items;
    }

    private function productPrice(int $productId): int
    {
        return $this->priceCache[$productId] ??= Product::where('id', $productId)->value('price_amount');
    }

    /** @param  array<int, int>  $warehouseIds */
    private function loadAvailability(array $warehouseIds): void
    {
        $levels = StockLevel::whereIn('warehouse_id', $warehouseIds)->get();

        foreach ($levels as $level) {
            $this->availability["{$level->warehouse_id}:{$level->product_id}"] = $level->quantity - $level->quantity_reserved;
            $this->productsByWarehouse[$level->warehouse_id][] = $level->product_id;
        }
    }

    /** @param  array<string, int>  $counts */
    private function shuffledPlan(array $counts): array
    {
        $plan = [];

        foreach ($counts as $value => $count) {
            for ($i = 0; $i < $count; $i++) {
                $plan[] = $value;
            }
        }

        shuffle($plan);

        return $plan;
    }

    private function addHistory(Order $order, ?string $from, string $to, Carbon $at, ?string $note = null): void
    {
        $entry = $order->statusHistory()->create([
            'from_status' => $from,
            'to_status' => $to,
            'note' => $note,
            'created_by' => $this->admin->id,
        ]);
        $this->backdate($entry, $at);
    }

    private function addShipmentHistory(Shipment $shipment, ?string $from, string $to, Carbon $at): void
    {
        $entry = $shipment->statusHistory()->create([
            'from_status' => $from,
            'to_status' => $to,
            'created_by' => $this->admin->id,
        ]);
        $this->backdate($entry, $at);
        $shipment->update(['status' => $to]);
    }

    /** Mirrors OrderController::releaseReservation() — frees reserved stock without touching on-hand quantity. */
    private function releaseReservation(Order $order): void
    {
        foreach ($order->items as $item) {
            foreach ($item->resolvedComponents() as $component) {
                $level = StockLevel::query()
                    ->where('product_id', $component->product_id)
                    ->where('product_variant_id', $component->product_variant_id)
                    ->where('warehouse_id', $order->warehouse_id)
                    ->first();

                $level?->update(['quantity_reserved' => max(0, $level->quantity_reserved - $component->quantity)]);
            }
        }
    }

    /** Mirrors OrderController::ship() — converts a reservation into a real, on-hand decrement with a ledger entry. */
    private function convertReservationToSale(Order $order, Carbon $at): void
    {
        foreach ($order->items()->with('components')->get() as $item) {
            foreach ($item->resolvedComponents() as $component) {
                $level = StockLevel::query()
                    ->where('product_id', $component->product_id)
                    ->where('product_variant_id', $component->product_variant_id)
                    ->where('warehouse_id', $order->warehouse_id)
                    ->first();

                $before = $level?->quantity ?? 0;
                $after = $before - $component->quantity;

                $level->update([
                    'quantity' => $after,
                    'quantity_reserved' => $level->quantity_reserved - $component->quantity,
                ]);

                $movement = StockMovement::create([
                    'store_id' => $order->store_id,
                    'product_id' => $component->product_id,
                    'product_variant_id' => $component->product_variant_id,
                    'warehouse_id' => $order->warehouse_id,
                    'type' => 'sale',
                    'quantity' => $component->quantity,
                    'quantity_before' => $before,
                    'quantity_after' => $after,
                    'reference_type' => Order::class,
                    'reference_id' => $order->id,
                    'created_by' => $this->admin->id,
                ]);
                $this->backdate($movement, $at);
            }
        }
    }

    /** Batches delivered COD shipments (older than a week) into courier settlements, most fully reconciled. */
    private function settleCodShipments(): void
    {
        $shipments = Shipment::where('status', 'delivered')
            ->whereNotNull('cod_amount_collected')
            ->where('delivered_at', '<=', now()->subDays(7))
            ->whereHas('order', fn ($q) => $q->where('payment_method', 'cod'))
            ->get()
            ->groupBy('courier_id');

        $settlementNumber = 1;

        foreach ($shipments as $courierId => $courierShipments) {
            foreach ($courierShipments->chunk(8) as $chunk) {
                $expected = (int) $chunk->sum('cod_amount_collected');
                $fullySettled = random_int(1, 100) <= 80;
                $received = $fullySettled ? $expected : (int) round($expected * 0.6);
                $settledAt = $chunk->max('delivered_at')->copy()->addDays(random_int(2, 5));

                $settlement = CodSettlement::create([
                    'store_id' => $this->store->id,
                    'courier_id' => $courierId,
                    'settlement_number' => 'CODS-'.$settledAt->format('Ymd').'-'.sprintf('%04d', $settlementNumber++),
                    'amount_expected' => $expected,
                    'amount_received' => $received,
                    'note' => $fullySettled ? null : 'Balance pending from courier.',
                    'created_by' => $this->admin->id,
                ]);
                $this->backdate($settlement, $settledAt);

                $settlement->shipments()->attach($chunk->pluck('id'));
                Shipment::whereIn('id', $chunk->pluck('id'))->update(['cod_settled' => true]);
            }
        }
    }
}
