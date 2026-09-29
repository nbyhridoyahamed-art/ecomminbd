<?php

namespace Database\Factories;

use App\Models\PurchaseOrder;
use App\Models\Store;
use App\Models\Supplier;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<PurchaseOrder>
 */
class PurchaseOrderFactory extends Factory
{
    protected $model = PurchaseOrder::class;

    public function definition(): array
    {
        return [
            'store_id' => Store::factory(),
            'warehouse_id' => Warehouse::factory(),
            'supplier_id' => Supplier::factory(),
            'po_number' => 'PO-'.now()->format('Ymd').'-'.Str::upper(Str::random(6)),
            'status' => 'draft',
            'currency_code' => 'BDT',
        ];
    }

    public function pendingApproval(): static
    {
        return $this->state(['status' => 'pending_approval']);
    }

    public function ordered(): static
    {
        return $this->state(['status' => 'ordered']);
    }
}
