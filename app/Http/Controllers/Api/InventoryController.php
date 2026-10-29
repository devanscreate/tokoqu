<?php

namespace App\Http\Controllers\Api;

use App\Models\Product;
use App\Models\StockAdjustment;
use App\Models\StockMovement;
use App\Models\StockReceipt;
use App\Models\User;
use App\Services\InventoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class InventoryController
{
    public function __construct(
        protected InventoryService $inventoryService
    ) {}

    public function index(): JsonResponse
    {
        Gate::authorize('manage-inventory');

        return response()->json([
            'receipts' => StockReceipt::query()->with(['items.product', 'user'])->orderByDesc('created_at')->limit(30)->get(),
            'adjustments' => StockAdjustment::query()->with(['items.product', 'user'])->orderByDesc('created_at')->limit(30)->get(),
        ]);
    }

    /**
     * POST /api/v1/stock-receipts (W10)
     */
    public function storeReceipt(Request $request): JsonResponse
    {
        Gate::authorize('manage-inventory');

        $data = $request->validate([
            'type' => ['nullable', 'in:opening,purchase'],
            'supplier_name' => ['nullable', 'string', 'max:150'],
            'reference' => ['nullable', 'string', 'max:100'],
            'note' => ['nullable', 'string', 'max:500'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.qty' => ['required', 'numeric', 'gt:0'],
            'items.*.unit_cost' => ['required', 'integer', 'min:0'],
        ]);

        /** @var User $user */
        $user = $request->user();
        $receipt = $this->inventoryService->createStockReceipt($user, $data);

        return response()->json([
            'receipt' => $receipt,
        ], 201);
    }

    /**
     * POST /api/v1/stock-adjustments (W11)
     */
    public function storeAdjustment(Request $request): JsonResponse
    {
        Gate::authorize('manage-inventory');

        $data = $request->validate([
            'reason' => ['required', 'in:opname,damaged,expired,lost,correction'],
            'note' => ['nullable', 'string', 'max:500'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.counted_qty' => ['required', 'numeric', 'min:0'],
            'items.*.expected_system_qty' => ['nullable', 'numeric'],
        ]);

        /** @var User $user */
        $user = $request->user();
        $adjustment = $this->inventoryService->createStockAdjustment($user, $data);

        return response()->json([
            'adjustment' => $adjustment,
        ], 201);
    }

    /**
     * GET /api/v1/products/{product}/stock-card (W14)
     */
    public function stockCard(Product $product): JsonResponse
    {
        Gate::authorize('manage-inventory');

        $movements = StockMovement::query()
            ->with('user')
            ->where('product_id', $product->id)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit(100)
            ->get();

        $ledgerSum = (float) StockMovement::query()
            ->where('product_id', $product->id)
            ->sum('qty_delta');

        return response()->json([
            'product' => $product,
            'ledger_sum' => round($ledgerSum, 3),
            'cached_stock_qty' => round((float) $product->stock_qty, 3),
            'is_reconciled' => abs($ledgerSum - (float) $product->stock_qty) < 0.0001,
            'movements' => $movements,
        ]);
    }
}

