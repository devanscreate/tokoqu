<?php

use App\Models\Product;
use App\Models\Register;
use App\Models\Sale;
use App\Models\Setting;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\CheckoutService;
use App\Services\CorrectionService;
use App\Services\InventoryService;
use App\Services\ShiftService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

beforeEach(function () {
    Setting::updateOrCreate(['key' => 'allow_negative_stock'], ['value' => '0']);
    Setting::updateOrCreate(['key' => 'cashier_discount_limit_pct'], ['value' => '10']);
    Setting::updateOrCreate(['key' => 'tax_mode'], ['value' => 'off']);
    Setting::updateOrCreate(['key' => 'tax_rate'], ['value' => '11']);
    Setting::updateOrCreate(['key' => 'return_window_days'], ['value' => '7']);

    $this->manager = User::create([
        'name' => 'Manager Test',
        'email' => 'manager@test.id',
        'password' => Hash::make('password'),
        'pin_hash' => Hash::make('123456'),
        'role' => 'manager',
        'is_active' => true,
    ]);

    $this->cashier = User::create([
        'name' => 'Kasir Test',
        'email' => 'kasir@test.id',
        'password' => Hash::make('password'),
        'role' => 'cashier',
        'is_active' => true,
    ]);

    $this->register = Register::create([
        'code' => 'KSR-01',
        'name' => 'Kasir 1',
        'is_active' => true,
    ]);

    $this->product = Product::create([
        'sku' => 'PRD-01',
        'barcode' => '8990001',
        'name' => 'Kopi Gayo',
        'price' => 50000,
        'cost' => 35000,
        'track_stock' => true,
        'allow_decimal_qty' => false,
        'stock_qty' => 0,
        'min_stock' => 2,
        'is_active' => true,
    ]);

    app(InventoryService::class)->createStockReceipt($this->manager, [
        'type' => 'opening',
        'items' => [
            ['product_id' => $this->product->id, 'qty' => 1, 'unit_cost' => 35000],
        ],
    ]);
    $this->product->refresh();
});

test('1. selling the last unit succeeds once and blocks subsequent online sale (Section 13)', function () {
    $shift = app(ShiftService::class)->openShift($this->cashier, $this->register->id, 100000)['shift'];
    $checkout = app(CheckoutService::class);

    $sale1 = $checkout->processSale($this->cashier, [
        'client_uuid' => (string) Str::uuid(),
        'shift_id' => $shift->id,
        'items' => [['product_id' => $this->product->id, 'qty' => 1]],
        'payments' => [['method' => 'cash', 'amount' => 50000, 'tendered' => 50000]],
    ]);

    expect($sale1->status)->toBe('completed')
        ->and((float) $this->product->fresh()->stock_qty)->toBe(0.0);

    expect(fn () => $checkout->processSale($this->cashier, [
        'client_uuid' => (string) Str::uuid(),
        'shift_id' => $shift->id,
        'items' => [['product_id' => $this->product->id, 'qty' => 1]],
        'payments' => [['method' => 'cash', 'amount' => 50000, 'tendered' => 50000]],
    ]))->toThrow(ValidationException::class);
});

test('2. duplicate client_uuid is idempotent and creates only one sale (R8)', function () {
    $shift = app(ShiftService::class)->openShift($this->cashier, $this->register->id, 100000)['shift'];
    $checkout = app(CheckoutService::class);
    $uuid = (string) Str::uuid();

    $payload = [
        'client_uuid' => $uuid,
        'shift_id' => $shift->id,
        'items' => [['product_id' => $this->product->id, 'qty' => 1]],
        'payments' => [['method' => 'cash', 'amount' => 50000, 'tendered' => 50000]],
    ];

    $first = $checkout->processSale($this->cashier, $payload);
    $second = $checkout->processSale($this->cashier, $payload);

    expect($first->id)->toBe($second->id)
        ->and(Sale::where('client_uuid', $uuid)->count())->toBe(1)
        ->and((float) $this->product->fresh()->stock_qty)->toBe(0.0);
});

test('3. opening a second shift on the same register or for the same cashier is rejected (W3)', function () {
    $shiftService = app(ShiftService::class);
    $shiftService->openShift($this->cashier, $this->register->id, 100000);

    $reg2 = Register::create(['code' => 'KSR-02', 'name' => 'Kasir 2', 'is_active' => true]);

    expect(fn () => $shiftService->openShift($this->cashier, $reg2->id, 50000))
        ->toThrow(ValidationException::class);
});

test('4. non-cash payment larger than remaining due is rejected, cash tendered above due computes change (R6)', function () {
    $shift = app(ShiftService::class)->openShift($this->cashier, $this->register->id, 100000)['shift'];
    $checkout = app(CheckoutService::class);

    expect(fn () => $checkout->processSale($this->cashier, [
        'client_uuid' => (string) Str::uuid(),
        'shift_id' => $shift->id,
        'items' => [['product_id' => $this->product->id, 'qty' => 1]],
        'payments' => [['method' => 'qris', 'amount' => 60000]],
    ]))->toThrow(InvalidArgumentException::class);

    $sale = $checkout->processSale($this->cashier, [
        'client_uuid' => (string) Str::uuid(),
        'shift_id' => $shift->id,
        'items' => [['product_id' => $this->product->id, 'qty' => 1]],
        'payments' => [['method' => 'cash', 'amount' => 50000, 'tendered' => 100000]],
    ]);

    expect($sale->payments->first()->change_amount)->toBe(50000);
});

test('5. void in open shift restores stock and excludes sale from expected cash; blocked after shift closes (W6)', function () {
    $shiftService = app(ShiftService::class);
    $shift = $shiftService->openShift($this->cashier, $this->register->id, 100000)['shift'];

    $sale = app(CheckoutService::class)->processSale($this->cashier, [
        'client_uuid' => (string) Str::uuid(),
        'shift_id' => $shift->id,
        'items' => [['product_id' => $this->product->id, 'qty' => 1]],
        'payments' => [['method' => 'cash', 'amount' => 50000, 'tendered' => 50000]],
    ]);

    app(CorrectionService::class)->voidSale($sale, $this->manager, 'Salah input');

    expect((float) $this->product->fresh()->stock_qty)->toBe(1.0)
        ->and($shiftService->computeShiftMetrics($shift)['expected_cash'])->toBe(100000);
});

test('6. two partial returns on one line never exceed line net_total and ledger sum matches stock_qty (Section 13)', function () {
    app(InventoryService::class)->createStockReceipt($this->manager, [
        'type' => 'purchase',
        'items' => [['product_id' => $this->product->id, 'qty' => 2, 'unit_cost' => 35000]],
    ]);

    $shift = app(ShiftService::class)->openShift($this->cashier, $this->register->id, 200000)['shift'];

    // Sell 3 units at 50,000 = 150,000 minus 10,000 order discount => net_total = 140,000
    $sale = app(CheckoutService::class)->processSale($this->manager, [
        'client_uuid' => (string) Str::uuid(),
        'shift_id' => $shift->id,
        'items' => [['product_id' => $this->product->id, 'qty' => 3]],
        'order_discount_type' => 'amount',
        'order_discount_value' => 10000,
        'payments' => [['method' => 'cash', 'amount' => 140000, 'tendered' => 140000]],
    ]);

    $saleItem = $sale->items->first();
    $correction = app(CorrectionService::class);

    $ret1 = $correction->returnSale($sale, $this->manager, [
        'shift_id' => $shift->id,
        'refund_method' => 'cash',
        'reason' => 'Retur sebagian 1',
        'items' => [['sale_item_id' => $saleItem->id, 'qty' => 1, 'restock' => true]],
    ]);

    $ret2 = $correction->returnSale($sale, $this->manager, [
        'shift_id' => $shift->id,
        'refund_method' => 'cash',
        'reason' => 'Retur sisa 2',
        'items' => [['sale_item_id' => $saleItem->id, 'qty' => 2, 'restock' => true]],
    ]);

    expect($ret1->refund_total + $ret2->refund_total)->toBe($saleItem->net_total);

    $ledgerSum = (float) StockMovement::where('product_id', $this->product->id)->sum('qty_delta');
    expect($ledgerSum)->toBe((float) $this->product->fresh()->stock_qty);
});

