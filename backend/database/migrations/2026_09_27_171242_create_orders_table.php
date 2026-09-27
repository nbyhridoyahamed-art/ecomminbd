<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('store_id')->constrained('stores')->cascadeOnDelete();
            $table->string('order_number');
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            // The warehouse fulfilling this order — where stock is reserved/decremented.
            $table->foreignId('warehouse_id')->constrained('warehouses')->cascadeOnDelete();
            // pending (stock reserved) -> processing -> shipped (reservation converts to a
            // real sale movement) -> delivered; cancelled only from pending/processing —
            // see OrderController.
            $table->string('status')->default('pending');
            $table->string('payment_method')->default('cod');
            $table->string('payment_status')->default('unpaid');
            $table->char('currency_code', 3)->default('BDT');
            // subtotal/total are NOT stored — computed from order_items in
            // OrderResource, same reasoning as PurchaseOrderResource::total_amount.
            $table->bigInteger('shipping_amount')->default(0);
            $table->bigInteger('discount_amount')->default(0);
            // Which saved address (if any) this came from, plus a snapshot of it —
            // the snapshot survives the customer later editing/deleting that address.
            $table->foreignId('customer_address_id')->nullable()->constrained('customer_addresses')->nullOnDelete();
            $table->string('shipping_recipient_name');
            $table->string('shipping_phone');
            $table->text('shipping_address_line');
            $table->foreignId('shipping_bd_division_id')->nullable()->constrained('bd_divisions')->nullOnDelete();
            $table->foreignId('shipping_bd_district_id')->nullable()->constrained('bd_districts')->nullOnDelete();
            $table->foreignId('shipping_bd_upazila_id')->nullable()->constrained('bd_upazilas')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['store_id', 'order_number']);
            $table->index(['store_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
