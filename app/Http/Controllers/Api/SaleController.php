<?php

namespace App\Http\Controllers\Api;

use App\Models\Sale;
use App\Services\ApprovalService;
use App\Services\CheckoutService;
use App\Services\CorrectionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Throwable;

class SaleController
{
    public function __construct(
        protected CheckoutService $checkoutService,
        protected CorrectionService $correctionService,
        protected ApprovalService $approvalService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $query = Sale::query()
            ->with(['items', 'payments', 'returns.items', 'register', 'cashier', 'shift'])
            ->orderByDesc('created_at');

        if ($invoice = $request->query('invoice')) {
            $query->where('invoice_no', 'like', '%'.trim((string) $invoice).'%');
        }

        if ($shiftId = $request->query('shift_id')) {
            $query->where('shift_id', (int) $shiftId);
        }

        return response()->json([
            'sales' => $query->limit(50)->get(),
        ]);
    }

    /**
     * POST /api/v1/sales (W4 Checkout)
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'client_uuid' => ['required', 'uuid'],
            'shift_id' => ['required', 'integer', 'exists:shifts,id'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer'],
            'items.*.qty' => ['required', 'numeric', 'gt:0'],
            'items.*.line_discount' => ['nullable', 'integer', 'min:0'],
            'order_discount_type' => ['nullable', 'in:amount,percent'],
            'order_discount_value' => ['nullable', 'numeric', 'min:0'],
            'expected_grand_total' => ['nullable', 'integer', 'min:0'],
            'approval_token' => ['nullable', 'string'],
            'payments' => ['required', 'array', 'min:1'],
            'payments.*.method' => ['required', 'in:cash,qris,transfer,card'],
            'payments.*.amount' => ['required', 'integer', 'min:1'],
            'payments.*.tendered' => ['nullable', 'integer', 'min:0'],
            'payments.*.reference' => ['nullable', 'string', 'max:100'],
        ]);

        $sale = $this->checkoutService->processSale($request->user(), $data, false);

        return response()->json([
            'sale' => $sale,
        ], 201);
    }

    /**
     * POST /api/v1/sales/batch (W13 Offline outbox sync with per-item status)
     */
    public function batchSync(Request $request): JsonResponse
    {
        $data = $request->validate([
            'sales' => ['required', 'array', 'min:1'],
            'sales.*.client_uuid' => ['required', 'uuid'],
        ]);

        $results = [];

        foreach ($data['sales'] as $salePayload) {
            $uuid = (string) $salePayload['client_uuid'];
            $alreadyExisted = Sale::query()->where('client_uuid', $uuid)->exists();

            try {
                $sale = $this->checkoutService->processSale($request->user(), $salePayload, true);
                $results[] = [
                    'client_uuid' => $uuid,
                    'status' => $alreadyExisted ? 'duplicate' : 'created',
                    'invoice_no' => $sale->invoice_no,
                    'sale_id' => $sale->id,
                    'stock_conflict' => $sale->stock_conflict,
                ];
            } catch (ValidationException $ve) {
                $results[] = [
                    'client_uuid' => $uuid,
                    'status' => 'rejected',
                    'reason' => collect($ve->errors())->flatten()->implode(' '),
                ];
            } catch (Throwable $e) {
                $results[] = [
                    'client_uuid' => $uuid,
                    'status' => 'rejected',
                    'reason' => $e->getMessage(),
                ];
            }
        }

        return response()->json([
            'results' => $results,
        ]);
    }

    /**
     * POST /api/v1/sales/{sale}/void (W6)
     */
    public function void(Request $request, Sale $sale): JsonResponse
    {
        $data = $request->validate([
            'reason' => ['required', 'string', 'min:3', 'max:255'],
            'approval_token' => ['nullable', 'string'],
        ]);

        $voidedSale = $this->correctionService->voidSale(
            $sale,
            $request->user(),
            $data['reason'],
            $data['approval_token'] ?? null
        );

        return response()->json([
            'sale' => $voidedSale,
        ]);
    }

    /**
     * POST /api/v1/sales/{sale}/returns (W7)
     */
    public function returnSale(Request $request, Sale $sale): JsonResponse
    {
        $data = $request->validate([
            'shift_id' => ['nullable', 'integer', 'exists:shifts,id'],
            'refund_method' => ['required', 'in:cash,qris,transfer,card'],
            'reason' => ['required', 'string', 'min:3', 'max:255'],
            'approval_token' => ['nullable', 'string'],
            'override_window' => ['nullable', 'boolean'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.sale_item_id' => ['required', 'integer'],
            'items.*.qty' => ['required', 'numeric', 'gt:0'],
            'items.*.restock' => ['required', 'boolean'],
        ]);

        $saleReturn = $this->correctionService->returnSale($sale, $request->user(), $data);

        return response()->json([
            'sale_return' => $saleReturn,
            'sale' => $sale->fresh(['items', 'payments', 'returns.items']),
        ], 201);
    }

    /**
     * POST /api/v1/approvals (Verify Manager PIN for discount/void/return)
     */
    public function verifyApproval(Request $request): JsonResponse
    {
        $data = $request->validate([
            'pin' => ['required', 'string', 'min:4', 'max:12'],
            'action' => ['required', 'in:discount,void,return'],
            'reason' => ['required', 'string', 'min:3', 'max:255'],
            'manager_id' => ['nullable', 'integer', 'exists:users,id'],
        ]);

        $approval = $this->approvalService->verifyPinAndCreateToken(
            $request->user(),
            $data['pin'],
            $data['action'],
            $data['reason'],
            isset($data['manager_id']) ? (int) $data['manager_id'] : null
        );

        return response()->json([
            'approval_token' => $approval->token,
            'manager_name' => $approval->manager->name,
            'expires_at' => $approval->expires_at->toIso8601String(),
        ]);
    }
}

