<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Delivery\CodSettlementRequest;
use App\Http\Resources\CodSettlementResource;
use App\Models\CodSettlement;
use App\Models\Shipment;
use App\Support\ApiResponse;
use App\Support\Money;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class CodSettlementController extends Controller
{
    private const RELATIONS = ['courier', 'shipments.order', 'creator'];

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', CodSettlement::class);

        $perPage = min((int) $request->integer('per_page', 20), 100);

        $settlements = CodSettlement::query()
            ->with(self::RELATIONS)
            ->when($request->filled('store_id'), fn ($query) => $query->where('store_id', $request->integer('store_id')))
            ->when($request->filled('courier_id'), fn ($query) => $query->where('courier_id', $request->integer('courier_id')))
            ->latest()
            ->paginate($perPage);

        return ApiResponse::success(
            CodSettlementResource::collection($settlements),
            'COD settlements fetched successfully.',
            [
                'current_page' => $settlements->currentPage(),
                'per_page' => $settlements->perPage(),
                'total' => $settlements->total(),
                'last_page' => $settlements->lastPage(),
            ],
        );
    }

    public function store(CodSettlementRequest $request): JsonResponse
    {
        $this->authorize('create', CodSettlement::class);

        $data = $request->validated();

        try {
            $settlement = DB::transaction(function () use ($data, $request) {
                // Re-checked here (not just at validation time) under a row lock, so
                // two settlement requests racing on the same shipment can't both succeed.
                $shipments = Shipment::query()
                    ->whereIn('id', $data['shipment_ids'])
                    ->where('cod_settled', false)
                    ->lockForUpdate()
                    ->get();

                if ($shipments->count() !== count($data['shipment_ids'])) {
                    throw new RuntimeException('One or more shipments were already settled by another request.');
                }

                $settlement = CodSettlement::create([
                    'store_id' => $data['store_id'],
                    'courier_id' => $data['courier_id'],
                    'settlement_number' => 'CODS-'.now()->format('Ymd').'-'.Str::upper(Str::random(6)),
                    'amount_expected' => $shipments->sum('cod_amount_collected'),
                    'amount_received' => Money::fromDecimal($data['amount_received'])->amountMinor,
                    'note' => $data['note'] ?? null,
                    'created_by' => $request->user()->id,
                ]);

                $settlement->shipments()->attach($shipments->pluck('id'));
                Shipment::whereIn('id', $shipments->pluck('id'))->update(['cod_settled' => true]);

                return $settlement;
            });
        } catch (RuntimeException $exception) {
            return ApiResponse::error($exception->getMessage(), [], 422);
        }

        return ApiResponse::success(new CodSettlementResource($settlement->load(self::RELATIONS)), 'COD settlement recorded successfully.', status: 201);
    }

    public function show(CodSettlement $codSettlement): JsonResponse
    {
        $this->authorize('view', $codSettlement);

        return ApiResponse::success(new CodSettlementResource($codSettlement->load(self::RELATIONS)), 'COD settlement fetched successfully.');
    }
}
