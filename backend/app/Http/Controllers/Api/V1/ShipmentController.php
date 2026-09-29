<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Delivery\ShipmentDeliveredRequest;
use App\Http\Requests\Delivery\ShipmentRequest;
use App\Http\Resources\ShipmentResource;
use App\Models\Order;
use App\Models\Product;
use App\Models\Shipment;
use App\Models\StockLevel;
use App\Models\StockMovement;
use App\Support\ApiResponse;
use App\Support\InsufficientStockException;
use App\Support\Money;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ShipmentController extends Controller
{
    // Same reasoning as OrderController::RELATIONS — every response
    // representing "a shipment" loads the same relations so a frontend
    // cache write from any status-transition action never drops a field.
    private const RELATIONS = ['order.customer', 'order.items.product', 'courier', 'creator', 'statusHistory.creator'];

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Shipment::class);

        $perPage = min((int) $request->integer('per_page', 20), 100);

        $shipments = Shipment::query()
            ->with(self::RELATIONS)
            ->when($request->filled('store_id'), fn ($query) => $query->where('store_id', $request->integer('store_id')))
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->when($request->filled('courier_id'), fn ($query) => $query->where('courier_id', $request->integer('courier_id')))
            ->latest()
            ->paginate($perPage);

        return ApiResponse::success(
            ShipmentResource::collection($shipments),
            'Shipments fetched successfully.',
            [
                'current_page' => $shipments->currentPage(),
                'per_page' => $shipments->perPage(),
                'total' => $shipments->total(),
                'last_page' => $shipments->lastPage(),
            ],
        );
    }

    public function store(ShipmentRequest $request, Order $order): JsonResponse
    {
        $this->authorize('create', Shipment::class);

        if ($order->status !== 'shipped') {
            return ApiResponse::error('Only shipped orders can be assigned a shipment.', [], 422);
        }

        // Wave 2: an order can have more than one shipment over its lifetime
        // — re-dispatching after the prior one failed to arrive. A shipment
        // that's still active (anything short of failed_delivery/
        // returned_to_seller) still blocks a second one, same as Wave 1.
        $previous = $order->shipments()->latest('id')->first();

        if ($previous && ! in_array($previous->status, ['failed_delivery', 'returned_to_seller'], true)) {
            return ApiResponse::error('This order already has an active shipment.', [], 422);
        }

        $data = $request->validated();

        try {
            $shipment = DB::transaction(function () use ($data, $order, $request, $previous) {
                $shipment = Shipment::create([
                    'store_id' => $order->store_id,
                    'order_id' => $order->id,
                    'courier_id' => $data['courier_id'],
                    'tracking_number' => $data['tracking_number'],
                    'status' => 'pending_pickup',
                    'delivery_charge_amount' => isset($data['delivery_charge'])
                        ? Money::fromDecimal($data['delivery_charge'], $order->currency_code)->amountMinor
                        : $order->shipping_amount,
                    'notes' => $data['notes'] ?? null,
                    'created_by' => $request->user()->id,
                ]);

                $shipment->statusHistory()->create([
                    'from_status' => null,
                    'to_status' => 'pending_pickup',
                    'created_by' => $request->user()->id,
                ]);

                // A prior shipment that made it all the way back to the
                // seller already had its stock restocked (see returned()
                // below) — re-dispatching sends the same physical goods out
                // again, so stock needs decrementing a second time, the same
                // real-world effect Order.ship() had originally. A prior
                // shipment that's only failed_delivery (never physically
                // returned) never had its stock reversed, so re-dispatching
                // it needs no stock change — it's already correctly "out".
                if ($previous?->status === 'returned_to_seller') {
                    $this->redecrementStock($order, $shipment, $request->user()->id);
                }

                return $shipment;
            });
        } catch (InsufficientStockException $exception) {
            return ApiResponse::error($exception->getMessage(), [], 422);
        }

        return ApiResponse::success(new ShipmentResource($shipment->load(self::RELATIONS)), 'Shipment created successfully.', status: 201);
    }

    private function redecrementStock(Order $order, Shipment $shipment, int $userId): void
    {
        $order->load('items.components');

        foreach ($order->items as $item) {
            foreach ($item->resolvedComponents() as $component) {
                $level = StockLevel::query()
                    ->where('product_id', $component->product_id)
                    ->where('product_variant_id', $component->product_variant_id)
                    ->where('warehouse_id', $order->warehouse_id)
                    ->lockForUpdate()
                    ->first();

                $before = $level?->quantity ?? 0;
                $after = $before - $component->quantity;

                if ($after < 0) {
                    $product = Product::findOrFail($component->product_id);
                    throw new InsufficientStockException("Stock for \"{$product->name}\" is no longer sufficient to re-dispatch this order.");
                }

                $level->update(['quantity' => $after]);

                StockMovement::create([
                    'store_id' => $shipment->store_id,
                    'product_id' => $component->product_id,
                    'product_variant_id' => $component->product_variant_id,
                    'warehouse_id' => $order->warehouse_id,
                    'type' => 'sale',
                    'quantity' => $component->quantity,
                    'quantity_before' => $before,
                    'quantity_after' => $after,
                    'reference_type' => Shipment::class,
                    'reference_id' => $shipment->id,
                    'created_by' => $userId,
                ]);
            }
        }
    }

    public function show(Shipment $shipment): JsonResponse
    {
        $this->authorize('view', $shipment);

        return ApiResponse::success(new ShipmentResource($shipment->load(self::RELATIONS)), 'Shipment fetched successfully.');
    }

    public function pickedUp(Shipment $shipment): JsonResponse
    {
        $this->authorize('update', $shipment);

        if ($shipment->status !== 'pending_pickup') {
            return ApiResponse::error('Only a shipment pending pickup can be marked picked up.', [], 422);
        }

        $this->transition($shipment, 'picked_up');

        return ApiResponse::success(new ShipmentResource($shipment->load(self::RELATIONS)), 'Shipment marked picked up.');
    }

    public function inTransit(Shipment $shipment): JsonResponse
    {
        $this->authorize('update', $shipment);

        if ($shipment->status !== 'picked_up') {
            return ApiResponse::error('Only a picked-up shipment can be marked in transit.', [], 422);
        }

        $this->transition($shipment, 'in_transit');

        return ApiResponse::success(new ShipmentResource($shipment->load(self::RELATIONS)), 'Shipment marked in transit.');
    }

    public function delivered(ShipmentDeliveredRequest $request, Shipment $shipment): JsonResponse
    {
        $this->authorize('update', $shipment);

        if (! in_array($shipment->status, ['picked_up', 'in_transit'], true)) {
            return ApiResponse::error('Only a picked-up or in-transit shipment can be marked delivered.', [], 422);
        }

        $data = $request->validated();

        DB::transaction(function () use ($data, $shipment, $request) {
            $order = $shipment->order()->with('items')->first();
            $codAmount = null;

            if ($order->payment_method === 'cod') {
                $codAmount = isset($data['cod_amount_collected'])
                    ? Money::fromDecimal($data['cod_amount_collected'], $order->currency_code)->amountMinor
                    : $order->totalAmount();
            }

            $fromShipmentStatus = $shipment->status;

            $shipment->update([
                'status' => 'delivered',
                'delivered_at' => now(),
                'cod_amount_collected' => $codAmount,
            ]);

            $shipment->statusHistory()->create([
                'from_status' => $fromShipmentStatus,
                'to_status' => 'delivered',
                'note' => $data['note'] ?? null,
                'created_by' => $request->user()->id,
            ]);

            if ($order->status !== 'delivered') {
                $fromStatus = $order->status;
                $order->update(['status' => 'delivered']);

                if ($order->payment_method === 'cod') {
                    $order->update(['payment_status' => 'paid']);
                }

                $order->statusHistory()->create([
                    'from_status' => $fromStatus,
                    'to_status' => 'delivered',
                    'note' => 'Delivered by courier.',
                    'created_by' => $request->user()->id,
                ]);
            }
        });

        return ApiResponse::success(new ShipmentResource($shipment->load(self::RELATIONS)), 'Shipment marked delivered.');
    }

    public function failed(Request $request, Shipment $shipment): JsonResponse
    {
        $this->authorize('update', $shipment);

        if (! in_array($shipment->status, ['pending_pickup', 'picked_up', 'in_transit'], true)) {
            return ApiResponse::error('This shipment cannot be marked as a failed delivery.', [], 422);
        }

        $this->transition($shipment, 'failed_delivery', $request->input('note'));

        return ApiResponse::success(new ShipmentResource($shipment->load(self::RELATIONS)), 'Shipment marked as a failed delivery.');
    }

    public function returned(Request $request, Shipment $shipment): JsonResponse
    {
        $this->authorize('update', $shipment);

        if ($shipment->status !== 'failed_delivery') {
            return ApiResponse::error('Only a failed delivery can be marked returned to seller.', [], 422);
        }

        DB::transaction(function () use ($request, $shipment) {
            $order = $shipment->order()->with('items.components')->first();

            // Order.ship() already converted the reservation into a real
            // `sale` movement and decremented on-hand quantity before this
            // shipment ever existed — since the goods never reached the
            // customer and are physically back, that decrement needs
            // reversing. (Reopening the order's own status, e.g. back to
            // pending/processing or to cancelled, is a Wave 2 problem —
            // Wave 1 only guarantees the stock side is correct.) The whole
            // shipment failed, not a partial return, so every component's
            // full snapshotted quantity is restocked — no proration.
            foreach ($order->items as $item) {
                foreach ($item->resolvedComponents() as $component) {
                    $level = StockLevel::query()
                        ->where('product_id', $component->product_id)
                        ->where('product_variant_id', $component->product_variant_id)
                        ->where('warehouse_id', $order->warehouse_id)
                        ->lockForUpdate()
                        ->first();

                    $before = $level?->quantity ?? 0;
                    $after = $before + $component->quantity;

                    $level
                        ? $level->update(['quantity' => $after])
                        : StockLevel::create([
                            'product_id' => $component->product_id,
                            'product_variant_id' => $component->product_variant_id,
                            'warehouse_id' => $order->warehouse_id,
                            'quantity' => $after,
                        ]);

                    StockMovement::create([
                        'store_id' => $shipment->store_id,
                        'product_id' => $component->product_id,
                        'product_variant_id' => $component->product_variant_id,
                        'warehouse_id' => $order->warehouse_id,
                        'type' => 'return',
                        'quantity' => $component->quantity,
                        'quantity_before' => $before,
                        'quantity_after' => $after,
                        'reference_type' => Shipment::class,
                        'reference_id' => $shipment->id,
                        'created_by' => $request->user()->id,
                    ]);
                }
            }

            $fromStatus = $shipment->status;
            $shipment->update(['status' => 'returned_to_seller']);

            $shipment->statusHistory()->create([
                'from_status' => $fromStatus,
                'to_status' => 'returned_to_seller',
                'note' => $request->input('note'),
                'created_by' => $request->user()->id,
            ]);
        });

        return ApiResponse::success(new ShipmentResource($shipment->load(self::RELATIONS)), 'Shipment marked returned to seller.');
    }

    private function transition(Shipment $shipment, string $toStatus, ?string $note = null): void
    {
        DB::transaction(function () use ($shipment, $toStatus, $note) {
            $fromStatus = $shipment->status;
            $shipment->update(['status' => $toStatus]);

            $shipment->statusHistory()->create([
                'from_status' => $fromStatus,
                'to_status' => $toStatus,
                'note' => $note,
                'created_by' => request()->user()->id,
            ]);
        });
    }
}
