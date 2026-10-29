<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->string('pin_hash')->nullable();
            $table->string('role', 20)->default('cashier'); // owner, manager, cashier
            $table->boolean('is_active')->default(true);
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });

        Schema::create('cache', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->mediumText('value');
            $table->integer('expiration');
        });

        Schema::create('cache_locks', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->string('owner');
            $table->integer('expiration');
        });

        Schema::create('jobs', function (Blueprint $table) {
            $table->id();
            $table->string('queue')->index();
            $table->longText('payload');
            $table->unsignedTinyInteger('attempts');
            $table->unsignedInteger('reserved_at')->nullable();
            $table->unsignedInteger('available_at');
            $table->unsignedInteger('created_at');
        });

        Schema::create('registers', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('name', 100);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('sku', 64)->unique();
            $table->string('barcode', 64)->nullable()->unique();
            $table->string('name', 200);
            $table->foreignId('category_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->bigInteger('price'); // R1: Rupiah integer
            $table->bigInteger('cost')->default(0);
            $table->boolean('track_stock')->default(true);
            $table->boolean('allow_decimal_qty')->default(false);
            $table->decimal('stock_qty', 12, 3)->default(0);
            $table->decimal('min_stock', 12, 3)->default(0);
            $table->boolean('is_active')->default(true);
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('shifts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('register_id')->constrained('registers');
            $table->foreignId('cashier_id')->constrained('users');
            $table->timestampTz('opened_at');
            $table->bigInteger('opening_cash')->default(0);
            $table->timestampTz('closed_at')->nullable();
            $table->bigInteger('counted_cash')->nullable();
            $table->bigInteger('expected_cash')->nullable();
            $table->bigInteger('difference')->nullable();
            $table->string('status', 20)->default('open'); // open, closed
            $table->text('close_note')->nullable();
            $table->foreignId('forced_by')->nullable()->constrained('users');
            $table->text('force_reason')->nullable();
            $table->boolean('late_adjusted')->default(false);
            $table->timestamps();
        });

        // Partial unique indexes for one-open-shift rules (Section 6 & 7)
        DB::statement("CREATE UNIQUE INDEX shifts_one_open_per_register ON shifts (register_id) WHERE status = 'open'");
        DB::statement("CREATE UNIQUE INDEX shifts_one_open_per_cashier ON shifts (cashier_id) WHERE status = 'open'");

        Schema::create('invoice_sequences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('register_id')->constrained('registers');
            $table->string('date_yymmdd', 6);
            $table->unsignedInteger('last_seq')->default(0);
            $table->unique(['register_id', 'date_yymmdd']);
        });

        Schema::create('sales', function (Blueprint $table) {
            $table->id();
            $table->uuid('client_uuid')->unique();
            $table->string('invoice_no', 50)->unique();
            $table->foreignId('shift_id')->constrained('shifts');
            $table->foreignId('register_id')->constrained('registers');
            $table->foreignId('cashier_id')->constrained('users');
            $table->string('status', 30)->default('completed'); // completed, voided, partially_returned, returned
            $table->bigInteger('subtotal');
            $table->bigInteger('discount_total')->default(0); // sum of line discounts + order discount
            $table->bigInteger('order_discount_amount')->default(0);
            $table->string('tax_mode', 20)->default('off'); // off, inclusive, exclusive
            $table->decimal('tax_rate', 5, 2)->default(0);
            $table->bigInteger('tax_total')->default(0);
            $table->bigInteger('grand_total');
            $table->boolean('is_offline')->default(false);
            $table->boolean('stock_conflict')->default(false);
            $table->timestampTz('voided_at')->nullable();
            $table->foreignId('voided_by')->nullable()->constrained('users');
            $table->text('void_reason')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users');
            $table->timestampTz('created_at');
            $table->timestampTz('received_at');
        });

        Schema::create('sale_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_id')->constrained('sales')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products');
            $table->string('name', 200);
            $table->string('sku', 64);
            $table->decimal('qty', 12, 3);
            $table->bigInteger('unit_price');
            $table->bigInteger('unit_cost');
            $table->bigInteger('line_discount')->default(0);
            $table->bigInteger('order_discount_alloc')->default(0);
            $table->bigInteger('net_total');
            $table->decimal('returned_qty', 12, 3)->default(0);
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_id')->constrained('sales')->cascadeOnDelete();
            $table->string('method', 30); // cash, qris, transfer, card
            $table->bigInteger('amount');
            $table->bigInteger('tendered')->nullable();
            $table->bigInteger('change_amount')->default(0);
            $table->string('reference', 100)->nullable();
            $table->boolean('needs_manual_reversal')->default(false);
        });

        Schema::create('sale_returns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_id')->constrained('sales');
            $table->foreignId('shift_id')->nullable()->constrained('shifts');
            $table->string('return_no', 50)->unique();
            $table->string('refund_method', 30); // cash, qris, transfer, card
            $table->bigInteger('refund_total');
            $table->text('reason');
            $table->foreignId('user_id')->constrained('users');
            $table->foreignId('approved_by')->nullable()->constrained('users');
            $table->timestampTz('created_at');
        });

        Schema::create('sale_return_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_return_id')->constrained('sale_returns')->cascadeOnDelete();
            $table->foreignId('sale_item_id')->constrained('sale_items');
            $table->foreignId('product_id')->constrained('products');
            $table->decimal('qty', 12, 3);
            $table->bigInteger('refund_amount');
            $table->boolean('restock')->default(true);
        });

        Schema::create('cash_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shift_id')->constrained('shifts');
            $table->string('type', 10); // in, out
            $table->bigInteger('amount');
            $table->string('reason', 255);
            $table->foreignId('user_id')->constrained('users');
            $table->timestampTz('created_at');
        });

        Schema::create('stock_receipts', function (Blueprint $table) {
            $table->id();
            $table->string('receipt_no', 50)->unique();
            $table->string('type', 20)->default('purchase'); // opening, purchase
            $table->string('supplier_name', 150)->nullable();
            $table->string('reference', 100)->nullable();
            $table->text('note')->nullable();
            $table->foreignId('user_id')->constrained('users');
            $table->timestampTz('created_at');
        });

        Schema::create('stock_receipt_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_receipt_id')->constrained('stock_receipts')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products');
            $table->decimal('qty', 12, 3);
            $table->bigInteger('unit_cost');
        });

        Schema::create('stock_adjustments', function (Blueprint $table) {
            $table->id();
            $table->string('adjustment_no', 50)->unique();
            $table->string('reason', 40); // opname, damaged, expired, lost, correction
            $table->text('note')->nullable();
            $table->foreignId('user_id')->constrained('users');
            $table->timestampTz('created_at');
        });

        Schema::create('stock_adjustment_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_adjustment_id')->constrained('stock_adjustments')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products');
            $table->decimal('system_qty', 12, 3);
            $table->decimal('counted_qty', 12, 3);
            $table->decimal('delta', 12, 3);
        });

        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products');
            $table->string('type', 30); // opening, purchase, sale, void, return, adjustment
            $table->decimal('qty_delta', 12, 3);
            $table->string('ref_type', 50);
            $table->unsignedBigInteger('ref_id');
            $table->foreignId('user_id')->nullable()->constrained('users');
            $table->string('note', 255)->nullable();
            $table->timestampTz('created_at');
            $table->index(['product_id', 'created_at']);
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users');
            $table->string('action', 80);
            $table->string('entity', 80);
            $table->unsignedBigInteger('entity_id')->nullable();
            $table->json('before')->nullable();
            $table->json('after')->nullable();
            $table->text('reason')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users');
            $table->timestampTz('created_at');
        });

        Schema::create('manager_approvals', function (Blueprint $table) {
            $table->id();
            $table->string('token', 64)->unique();
            $table->foreignId('manager_id')->constrained('users');
            $table->foreignId('requested_by')->constrained('users');
            $table->string('action', 50); // discount, void, return
            $table->string('reason', 255);
            $table->timestampTz('used_at')->nullable();
            $table->timestampTz('expires_at');
            $table->timestampTz('created_at');
        });

        Schema::create('settings', function (Blueprint $table) {
            $table->string('key', 80)->primary();
            $table->text('value')->nullable();
            $table->timestampTz('updated_at')->nullable();
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE products ADD CONSTRAINT products_non_negative_money CHECK (price >= 0 AND cost >= 0)');
            DB::statement('ALTER TABLE sale_items ADD CONSTRAINT sale_items_qty_check CHECK (qty > 0 AND returned_qty >= 0 AND returned_qty <= qty)');
            DB::statement('ALTER TABLE payments ADD CONSTRAINT payments_amount_positive CHECK (amount > 0)');
            DB::statement('ALTER TABLE cash_movements ADD CONSTRAINT cash_movements_amount_positive CHECK (amount > 0)');
            DB::statement('ALTER TABLE stock_movements ADD CONSTRAINT stock_movements_delta_nonzero CHECK (qty_delta <> 0)');

            DB::unprepared("
                CREATE OR REPLACE FUNCTION prevent_append_only_mutation()
                RETURNS TRIGGER AS $$
                BEGIN
                    RAISE EXCEPTION 'Table % is append-only; UPDATE and DELETE are forbidden.', TG_TABLE_NAME;
                END;
                $$ LANGUAGE plpgsql;

                CREATE TRIGGER trg_stock_movements_immutable
                BEFORE UPDATE OR DELETE ON stock_movements
                FOR EACH ROW EXECUTE FUNCTION prevent_append_only_mutation();

                CREATE TRIGGER trg_audit_logs_immutable
                BEFORE UPDATE OR DELETE ON audit_logs
                FOR EACH ROW EXECUTE FUNCTION prevent_append_only_mutation();
            ");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::unprepared('
                DROP TRIGGER IF EXISTS trg_stock_movements_immutable ON stock_movements;
                DROP TRIGGER IF EXISTS trg_audit_logs_immutable ON audit_logs;
                DROP FUNCTION IF EXISTS prevent_append_only_mutation();
            ');
        }

        Schema::dropIfExists('settings');
        Schema::dropIfExists('manager_approvals');
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('stock_movements');
        Schema::dropIfExists('stock_adjustment_items');
        Schema::dropIfExists('stock_adjustments');
        Schema::dropIfExists('stock_receipt_items');
        Schema::dropIfExists('stock_receipts');
        Schema::dropIfExists('cash_movements');
        Schema::dropIfExists('sale_return_items');
        Schema::dropIfExists('sale_returns');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('sale_items');
        Schema::dropIfExists('sales');
        Schema::dropIfExists('invoice_sequences');
        Schema::dropIfExists('shifts');
        Schema::dropIfExists('products');
        Schema::dropIfExists('categories');
        Schema::dropIfExists('registers');
        Schema::dropIfExists('jobs');
        Schema::dropIfExists('cache_locks');
        Schema::dropIfExists('cache');
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('users');
    }
};

