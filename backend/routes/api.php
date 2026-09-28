<?php

use App\Http\Controllers\Api\V1\Account\AddressController as AccountAddressController;
use App\Http\Controllers\Api\V1\Account\AuthController as AccountAuthController;
use App\Http\Controllers\Api\V1\Account\OrderController as AccountOrderController;
use App\Http\Controllers\Api\V1\Account\ProfileController as AccountProfileController;
use App\Http\Controllers\Api\V1\Auth\AuthController;
use App\Http\Controllers\Api\V1\BrandController;
use App\Http\Controllers\Api\V1\CategoryController;
use App\Http\Controllers\Api\V1\CodSettlementController;
use App\Http\Controllers\Api\V1\CourierController;
use App\Http\Controllers\Api\V1\CurrencyController;
use App\Http\Controllers\Api\V1\CustomerAddressController;
use App\Http\Controllers\Api\V1\CustomerController;
use App\Http\Controllers\Api\V1\DashboardController;
use App\Http\Controllers\Api\V1\LocationController;
use App\Http\Controllers\Api\V1\NotificationController;
use App\Http\Controllers\Api\V1\OrderController;
use App\Http\Controllers\Api\V1\ProductAttributeController;
use App\Http\Controllers\Api\V1\ProductComponentController;
use App\Http\Controllers\Api\V1\ProductController;
use App\Http\Controllers\Api\V1\ProductImageController;
use App\Http\Controllers\Api\V1\ProductImportController;
use App\Http\Controllers\Api\V1\ProductVariantController;
use App\Http\Controllers\Api\V1\PurchaseOrderController;
use App\Http\Controllers\Api\V1\PurchaseReceiptController;
use App\Http\Controllers\Api\V1\PurchaseReturnController;
use App\Http\Controllers\Api\V1\ReportController;
use App\Http\Controllers\Api\V1\ReturnController;
use App\Http\Controllers\Api\V1\RoleController;
use App\Http\Controllers\Api\V1\ShipmentController;
use App\Http\Controllers\Api\V1\StockAdjustmentController;
use App\Http\Controllers\Api\V1\StockLevelController;
use App\Http\Controllers\Api\V1\StockMovementController;
use App\Http\Controllers\Api\V1\StockTransferController;
use App\Http\Controllers\Api\V1\StoreController;
use App\Http\Controllers\Api\V1\Storefront\BrandController as StorefrontBrandController;
use App\Http\Controllers\Api\V1\Storefront\CategoryController as StorefrontCategoryController;
use App\Http\Controllers\Api\V1\Storefront\CheckoutController as StorefrontCheckoutController;
use App\Http\Controllers\Api\V1\Storefront\ProductController as StorefrontProductController;
use App\Http\Controllers\Api\V1\Storefront\StoreController as StorefrontStoreController;
use App\Http\Controllers\Api\V1\SupplierController;
use App\Http\Controllers\Api\V1\UploadController;
use App\Http\Controllers\Api\V1\UserController;
use App\Http\Controllers\Api\V1\WarehouseController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::prefix('auth')->group(function () {
        Route::post('register', [AuthController::class, 'register'])->middleware('throttle:6,1');
        Route::post('login', [AuthController::class, 'login'])->middleware('throttle:6,1');
        Route::post('forgot-password', [AuthController::class, 'forgotPassword'])->middleware('throttle:6,1');
        Route::post('reset-password', [AuthController::class, 'resetPassword'])->middleware('throttle:6,1');

        Route::middleware(['auth:sanctum', 'staff'])->group(function () {
            Route::post('logout', [AuthController::class, 'logout']);
            Route::get('me', [AuthController::class, 'me']);
        });
    });

    // Public, unauthenticated storefront API — no auth:sanctum. Wave 1
    // serves whichever single store is active (see StorefrontController);
    // real multi-tenant domain routing is a documented Wave 2 concern.
    Route::prefix('storefront')->group(function () {
        Route::get('store', [StorefrontStoreController::class, 'show']);
        Route::get('products', [StorefrontProductController::class, 'index']);
        Route::get('products/{slug}', [StorefrontProductController::class, 'show']);
        Route::get('categories', [StorefrontCategoryController::class, 'index']);
        Route::get('categories/{slug}', [StorefrontCategoryController::class, 'show']);
        Route::get('brands', [StorefrontBrandController::class, 'index']);
        Route::get('brands/{slug}', [StorefrontBrandController::class, 'show']);

        // Same controller the admin app uses under auth:sanctum below —
        // Bangladesh division/district/upazila names are nationwide
        // reference data, not store-scoped or sensitive, so a guest
        // checkout form needs the identical cascading picker without a
        // token. Nothing here reads the authenticated user.
        Route::prefix('locations')->group(function () {
            Route::get('divisions', [LocationController::class, 'divisions']);
            Route::get('districts', [LocationController::class, 'districts']);
            Route::get('upazilas', [LocationController::class, 'upazilas']);
        });

        // Throttled like the other unauthenticated write endpoints
        // (auth/register, auth/login) — this creates a real order and
        // reserves real stock, so it needs the same abuse guard.
        Route::post('checkout', [StorefrontCheckoutController::class, 'store'])->middleware('throttle:15,1');
        Route::get('orders/{uuid}', [StorefrontCheckoutController::class, 'show']);
    });

    // Customer account (Phase 17 Wave 1) — a customer's own Sanctum
    // tokens, a completely separate space from staff's (see
    // EnsureCustomerUser/EnsureStaffUser). register/login are public like
    // auth/register|login above; everything else requires a customer
    // token specifically, not just any valid one.
    Route::prefix('account')->group(function () {
        Route::post('auth/register', [AccountAuthController::class, 'register'])->middleware('throttle:6,1');
        Route::post('auth/login', [AccountAuthController::class, 'login'])->middleware('throttle:6,1');

        Route::middleware(['auth:sanctum', 'customer'])->group(function () {
            Route::post('auth/logout', [AccountAuthController::class, 'logout']);
            Route::get('auth/me', [AccountAuthController::class, 'me']);

            Route::get('orders', [AccountOrderController::class, 'index']);
            Route::get('orders/{uuid}', [AccountOrderController::class, 'show']);

            Route::get('addresses', [AccountAddressController::class, 'index']);
            Route::post('addresses', [AccountAddressController::class, 'store']);
            Route::put('addresses/{address}', [AccountAddressController::class, 'update']);
            Route::delete('addresses/{address}', [AccountAddressController::class, 'destroy']);

            Route::put('profile', [AccountProfileController::class, 'update']);
        });
    });

    Route::middleware(['auth:sanctum', 'staff'])->group(function () {
        Route::apiResource('stores', StoreController::class);
        Route::apiResource('warehouses', WarehouseController::class);
        Route::apiResource('users', UserController::class);

        Route::get('currencies', [CurrencyController::class, 'index']);

        // Always the current staff user's own inbox — no permission to
        // gate beyond being authenticated, same reasoning as auth/me.
        Route::get('notifications', [NotificationController::class, 'index']);
        Route::post('notifications/read-all', [NotificationController::class, 'markAllRead']);
        Route::post('notifications/{id}/read', [NotificationController::class, 'markRead']);

        Route::get('roles', [RoleController::class, 'index']);
        Route::get('permissions', [RoleController::class, 'permissions']);
        Route::post('roles', [RoleController::class, 'store']);
        Route::put('roles/{role}', [RoleController::class, 'update']);
        Route::delete('roles/{role}', [RoleController::class, 'destroy']);

        Route::prefix('locations')->group(function () {
            Route::get('divisions', [LocationController::class, 'divisions']);
            Route::get('districts', [LocationController::class, 'districts']);
            Route::get('upazilas', [LocationController::class, 'upazilas']);
        });

        Route::post('uploads', [UploadController::class, 'store']);

        Route::apiResource('categories', CategoryController::class);
        Route::apiResource('brands', BrandController::class);

        // Registered before the products apiResource — otherwise its
        // GET products/{product} route would swallow "export" as a
        // product route-key first.
        Route::get('products/export', [ProductController::class, 'export']);
        Route::post('products/import', [ProductImportController::class, 'store']);
        Route::apiResource('products', ProductController::class);

        Route::post('products/{product}/images', [ProductImageController::class, 'store']);
        Route::delete('products/{product}/images/{image}', [ProductImageController::class, 'destroy']);
        Route::post('products/{product}/images/{image}/primary', [ProductImageController::class, 'markPrimary']);

        Route::apiResource('product-attributes', ProductAttributeController::class);
        Route::post('product-attributes/{productAttribute}/values', [ProductAttributeController::class, 'storeValue']);
        Route::put('product-attributes/{productAttribute}/values/{value}', [ProductAttributeController::class, 'updateValue']);
        Route::delete('product-attributes/{productAttribute}/values/{value}', [ProductAttributeController::class, 'destroyValue']);

        Route::post('products/{product}/variants/generate', [ProductVariantController::class, 'generate']);
        Route::put('products/{product}/variants/{variant}', [ProductVariantController::class, 'update']);
        Route::delete('products/{product}/variants/{variant}', [ProductVariantController::class, 'destroy']);

        Route::post('products/{product}/components', [ProductComponentController::class, 'store']);
        Route::put('products/{product}/components/{component}', [ProductComponentController::class, 'update']);
        Route::delete('products/{product}/components/{component}', [ProductComponentController::class, 'destroy']);

        Route::get('stock-levels', [StockLevelController::class, 'index']);
        Route::get('stock-levels/low-stock-count', [StockLevelController::class, 'lowStockCount']);
        Route::get('stock-movements', [StockMovementController::class, 'index']);
        Route::post('stock-adjustments', [StockAdjustmentController::class, 'store']);
        Route::get('stock-transfers', [StockTransferController::class, 'index']);
        Route::post('stock-transfers', [StockTransferController::class, 'store']);
        Route::get('stock-transfers/{stockTransfer}', [StockTransferController::class, 'show']);

        Route::apiResource('suppliers', SupplierController::class);

        Route::apiResource('purchase-orders', PurchaseOrderController::class);
        Route::post('purchase-orders/{purchaseOrder}/place', [PurchaseOrderController::class, 'place']);
        Route::post('purchase-orders/{purchaseOrder}/cancel', [PurchaseOrderController::class, 'cancel']);
        Route::post('purchase-orders/{purchaseOrder}/receipts', [PurchaseReceiptController::class, 'store']);
        Route::post('purchase-orders/{purchaseOrder}/returns', [PurchaseReturnController::class, 'store']);
        Route::get('purchase-returns', [PurchaseReturnController::class, 'index']);
        Route::get('purchase-returns/{purchaseReturn}', [PurchaseReturnController::class, 'show']);
        Route::post('purchase-returns/{purchaseReturn}/approve', [PurchaseReturnController::class, 'approve']);
        Route::post('purchase-returns/{purchaseReturn}/reject', [PurchaseReturnController::class, 'reject']);
        Route::post('purchase-returns/{purchaseReturn}/ship-back', [PurchaseReturnController::class, 'shipBack']);
        Route::post('purchase-returns/{purchaseReturn}/credit', [PurchaseReturnController::class, 'credit']);

        Route::apiResource('customers', CustomerController::class);
        Route::post('customers/{customer}/addresses', [CustomerAddressController::class, 'store']);
        Route::put('customers/{customer}/addresses/{address}', [CustomerAddressController::class, 'update']);
        Route::delete('customers/{customer}/addresses/{address}', [CustomerAddressController::class, 'destroy']);

        Route::apiResource('orders', OrderController::class)->except(['destroy']);
        Route::post('orders/{order}/process', [OrderController::class, 'process']);
        Route::post('orders/{order}/ship', [OrderController::class, 'ship']);
        Route::post('orders/{order}/deliver', [OrderController::class, 'deliver']);
        Route::post('orders/{order}/cancel', [OrderController::class, 'cancel']);

        Route::apiResource('couriers', CourierController::class);

        Route::post('orders/{order}/shipments', [ShipmentController::class, 'store']);
        Route::get('shipments', [ShipmentController::class, 'index']);
        Route::get('shipments/{shipment}', [ShipmentController::class, 'show']);
        Route::post('shipments/{shipment}/picked-up', [ShipmentController::class, 'pickedUp']);
        Route::post('shipments/{shipment}/in-transit', [ShipmentController::class, 'inTransit']);
        Route::post('shipments/{shipment}/delivered', [ShipmentController::class, 'delivered']);
        Route::post('shipments/{shipment}/failed', [ShipmentController::class, 'failed']);
        Route::post('shipments/{shipment}/returned', [ShipmentController::class, 'returned']);

        Route::get('cod-settlements', [CodSettlementController::class, 'index']);
        Route::post('cod-settlements', [CodSettlementController::class, 'store']);
        Route::get('cod-settlements/{codSettlement}', [CodSettlementController::class, 'show']);

        Route::post('orders/{order}/returns', [ReturnController::class, 'store']);
        Route::get('returns', [ReturnController::class, 'index']);
        Route::get('returns/{orderReturn}', [ReturnController::class, 'show']);
        Route::post('returns/{orderReturn}/approve', [ReturnController::class, 'approve']);
        Route::post('returns/{orderReturn}/reject', [ReturnController::class, 'reject']);
        Route::post('returns/{orderReturn}/receive', [ReturnController::class, 'receive']);
        Route::post('returns/{orderReturn}/refund', [ReturnController::class, 'refund']);

        Route::get('dashboard/sales-trend', [DashboardController::class, 'salesTrend']);
        Route::get('dashboard/order-status-breakdown', [DashboardController::class, 'orderStatusBreakdown']);

        Route::get('reports/sales', [ReportController::class, 'salesReport']);
        Route::get('reports/sales/export', [ReportController::class, 'salesReportExport']);
        Route::get('reports/sales/export-pdf', [ReportController::class, 'salesReportExportPdf']);
        Route::get('reports/products-performance', [ReportController::class, 'productPerformance']);
        Route::get('reports/products-performance/export', [ReportController::class, 'productPerformanceExport']);
        Route::get('reports/products-performance/export-pdf', [ReportController::class, 'productPerformanceExportPdf']);
        Route::get('reports/low-stock', [ReportController::class, 'lowStock']);
        Route::get('reports/low-stock/export', [ReportController::class, 'lowStockExport']);
        Route::get('reports/low-stock/export-pdf', [ReportController::class, 'lowStockExportPdf']);
    });
});
