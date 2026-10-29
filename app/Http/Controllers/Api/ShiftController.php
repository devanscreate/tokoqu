<?php

namespace App\Http\Controllers\Api;

use App\Models\CashMovement;
use App\Models\Category;
use App\Models\Product;
use App\Models\Register;
use App\Models\Setting;
use App\Models\Shift;
use App\Models\User;
use App\Services\ShiftService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class ShiftController
{
    public function __construct(
        protected ShiftService $shiftService
    ) {}

    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $query = Shift::query()->with(['register', 'cashier', 'forcedBy'])->orderByDesc('opened_at');

        if (! $user->isManagerOrOwner()) {
            $query->where('cashier_id', $user->id);
        }

        return response()->json([
            'shifts' => $query->limit(50)->get(),
            'registers' => Register::query()->with(['openShift.cashier'])->orderBy('code')->get(),
        ]);
    }

    public function current(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $shift = Shift::query()
            ->with(['register', 'cashier', 'cashMovements.user'])
            ->where('cashier_id', $user->id)
            ->where('status', 'open')
            ->first();

        return response()->json([
            'shift' => $shift,
            'registers' => Register::query()->with(['openShift.cashier'])->where('is_active', true)->orderBy('code')->get(),
        ]);
    }

    /**
     * W3. Open shift
     */
    public function open(Request $request): JsonResponse
    {
        $data = $request->validate([
            'register_id' => ['required', 'integer', 'exists:registers,id'],
            'opening_cash' => ['required', 'integer', 'min:0'],
        ]);

        /** @var User $user */
        $user = $request->user();
        $result = $this->shiftService->openShift(
            $user,
            (int) $data['register_id'],
            (int) $data['opening_cash']
        );

        return response()->json([
            'shift' => $result['shift'],
            'last_invoice_seq' => $result['last_invoice_seq'],
            'settings' => Setting::getMap(),
            'categories' => Category::query()->orderBy('sort_order')->get(),
            'products' => Product::query()->where('is_active', true)->orderBy('name')->get(),
        ], 201);
    }

    /**
     * W9. Close shift (blind count by default)
     */
    public function close(Request $request, Shift $shift): JsonResponse
    {
        $data = $request->validate([
            'counted_cash' => ['required', 'integer', 'min:0'],
            'close_note' => ['nullable', 'string', 'max:500'],
            'has_unsynced_sales' => ['boolean'],
            'has_held_carts' => ['boolean'],
        ]);

        // W9 Precondition: register's outbox has no unsynced sales and no held carts
        if (! empty($data['has_unsynced_sales'])) {
            throw ValidationException::withMessages([
                'outbox' => ['Shift tidak dapat ditutup selama masih ada transaksi offline yang belum tersinkronisasi (W9).'],
            ]);
        }

        if (! empty($data['has_held_carts'])) {
            throw ValidationException::withMessages([
                'held_carts' => ['Shift tidak dapat ditutup selama masih ada keranjang ditahan (Hold). Selesaikan atau batalkan terlebih dahulu (W5.3 / W9).'],
            ]);
        }

        /** @var User $user */
        $user = $request->user();
        $result = $this->shiftService->closeShift(
            $shift,
            $user,
            (int) $data['counted_cash'],
            $data['close_note'] ?? null
        );

        return response()->json($result);
    }

    /**
     * W12. Force-close another user's shift (Manager / Owner)
     */
    public function forceClose(Request $request, Shift $shift): JsonResponse
    {
        Gate::authorize('force-close-shift');

        $data = $request->validate([
            'counted_cash' => ['required', 'integer', 'min:0'],
            'reason' => ['required', 'string', 'min:3', 'max:500'],
        ]);

        /** @var User $user */
        $user = $request->user();
        $result = $this->shiftService->forceCloseShift(
            $shift,
            $user,
            (int) $data['counted_cash'],
            $data['reason']
        );

        return response()->json($result);
    }

    /**
     * W8. Cash in / Cash out
     */
    public function storeCashMovement(Request $request): JsonResponse
    {
        $data = $request->validate([
            'shift_id' => ['required', 'integer', 'exists:shifts,id'],
            'type' => ['required', 'in:in,out'],
            'amount' => ['required', 'integer', 'min:1'],
            'reason' => ['required', 'string', 'min:3', 'max:255'],
        ]);

        $shift = Shift::query()->findOrFail($data['shift_id']);
        if ($shift->status !== 'open') {
            throw ValidationException::withMessages([
                'shift_id' => ['Kas masuk/keluar hanya dapat dicatat pada shift yang sedang terbuka (W8).'],
            ]);
        }

        /** @var User $user */
        $user = $request->user();
        $movement = CashMovement::create([
            'shift_id' => $shift->id,
            'type' => $data['type'],
            'amount' => (int) $data['amount'],
            'reason' => $data['reason'],
            'user_id' => $user->id,
            'created_at' => now(),
        ]);

        return response()->json([
            'cash_movement' => $movement->load('user'),
        ], 201);
    }

    public function showReport(Request $request, Shift $shift): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        if ($shift->cashier_id !== $user->id && ! $user->isManagerOrOwner()) {
            abort(403, 'Anda hanya dapat melihat laporan shift milik sendiri.');
        }

        $metrics = $this->shiftService->computeShiftMetrics($shift);

        return response()->json([
            'shift' => $shift->load(['register', 'cashier', 'forcedBy', 'cashMovements.user']),
            'z_report' => array_merge($metrics, [
                'counted_cash' => $shift->counted_cash,
                'difference' => $shift->difference,
            ]),
        ]);
    }
}

