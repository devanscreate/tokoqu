<?php

namespace App\Http\Controllers\Api;

use App\Models\AuditLog;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SaleReturn;
use App\Models\Shift;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class ReportController
{
    /**
     * GET /api/v1/reports/summary (W14)
     */
    public function summary(Request $request): JsonResponse
    {
        Gate::authorize('view-reports');

        $tz = config('app.timezone', 'Asia/Jakarta');
        $dateFrom = $request->query('from')
            ? Carbon::parse((string) $request->query('from'), $tz)->startOfDay()->utc()
            : now($tz)->startOfDay()->utc();
        $dateTo = $request->query('to')
            ? Carbon::parse((string) $request->query('to'), $tz)->endOfDay()->utc()
            : now($tz)->endOfDay()->utc();

        $nonVoidedSales = Sale::query()
            ->where('status', '!=', 'voided')
            ->whereBetween('created_at', [$dateFrom, $dateTo])
            ->get();

        $saleIds = $nonVoidedSales->pluck('id');

        // Returns subtract on their own date (W7 Note & W14)
        $returnsInPeriod = SaleReturn::query()
            ->whereBetween('created_at', [$dateFrom, $dateTo])
            ->sum('refund_total');

        $grossSales = (int) $nonVoidedSales->sum('subtotal');
        $discounts = (int) $nonVoidedSales->sum('discount_total');
        $tax = (int) $nonVoidedSales->sum('tax_total');
        $salesGrandTotal = (int) $nonVoidedSales->sum('grand_total');
        $netRevenueAfterReturns = $salesGrandTotal - (int) $returnsInPeriod;

        // Cost & Gross Margin from sale_items snapshots (R4, W14)
        $itemsSummary = SaleItem::query()
            ->whereIn('sale_id', $saleIds)
            ->selectRaw('
                product_id,
                name,
                sku,
                SUM(qty - returned_qty) as net_qty,
                SUM(net_total) as total_revenue,
                SUM((qty - returned_qty) * unit_cost) as total_cogs
            ')
            ->groupBy('product_id', 'name', 'sku')
            ->orderByDesc('total_revenue')
            ->get()
            ->map(function ($row) {
                $rev = (int) round((float) $row->total_revenue);
                $cogs = (int) round((float) $row->total_cogs);

                return [
                    'product_id' => $row->product_id,
                    'name' => $row->name,
                    'sku' => $row->sku,
                    'net_qty' => round((float) $row->net_qty, 3),
                    'revenue' => $rev,
                    'cogs' => $cogs,
                    'gross_margin' => $rev - $cogs,
                ];
            });

        $totalCogs = (int) $itemsSummary->sum('cogs');
        $grossMarginTotal = $netRevenueAfterReturns - $totalCogs;

        // Breakdown by payment method
        $byPaymentMethod = Payment::query()
            ->whereIn('sale_id', $saleIds)
            ->selectRaw('method, SUM(amount) as total_amount, COUNT(*) as tx_count')
            ->groupBy('method')
            ->get();

        // Breakdown by cashier
        $byCashier = Sale::query()
            ->join('users', 'sales.cashier_id', '=', 'users.id')
            ->where('sales.status', '!=', 'voided')
            ->whereBetween('sales.created_at', [$dateFrom, $dateTo])
            ->selectRaw('users.id as cashier_id, users.name as cashier_name, COUNT(sales.id) as tx_count, SUM(sales.grand_total) as total_sales')
            ->groupBy('users.id', 'users.name')
            ->get();

        // Breakdown by category
        $byCategory = SaleItem::query()
            ->join('sales', 'sale_items.sale_id', '=', 'sales.id')
            ->join('products', 'sale_items.product_id', '=', 'products.id')
            ->leftJoin('categories', 'products.category_id', '=', 'categories.id')
            ->where('sales.status', '!=', 'voided')
            ->whereBetween('sales.created_at', [$dateFrom, $dateTo])
            ->selectRaw("COALESCE(categories.name, 'Tanpa Kategori') as category_name, SUM(sale_items.qty - sale_items.returned_qty) as net_qty, SUM(sale_items.net_total) as total_revenue")
            ->groupBy('categories.name')
            ->orderByDesc('total_revenue')
            ->get();

        // Low stock alerts (stock_qty <= min_stock)
        $lowStockProducts = Product::query()
            ->with('category')
            ->where('is_active', true)
            ->where('track_stock', true)
            ->whereColumn('stock_qty', '<=', 'min_stock')
            ->orderBy('stock_qty')
            ->get();

        // Needs-review queue (W14: stock_conflict sales, late_adjusted shifts)
        $stockConflictSales = Sale::query()
            ->with(['items', 'register', 'cashier'])
            ->where('stock_conflict', true)
            ->orderByDesc('created_at')
            ->get();

        $lateAdjustedShifts = Shift::query()
            ->with(['register', 'cashier', 'forcedBy'])
            ->where('late_adjusted', true)
            ->orderByDesc('closed_at')
            ->get();

        $auditLogs = AuditLog::query()
            ->with(['user', 'approver'])
            ->orderByDesc('created_at')
            ->limit(50)
            ->get();

        return response()->json([
            'period' => [
                'from' => $dateFrom->copy()->timezone($tz)->toDateString(),
                'to' => $dateTo->copy()->timezone($tz)->toDateString(),
            ],
            'kpis' => [
                'transactions_count' => $nonVoidedSales->count(),
                'gross_sales' => $grossSales,
                'discounts_total' => $discounts,
                'tax_total' => $tax,
                'returns_total' => (int) $returnsInPeriod,
                'net_sales' => $netRevenueAfterReturns,
                'cogs_total' => $totalCogs,
                'gross_margin' => $grossMarginTotal,
            ],
            'by_product' => $itemsSummary->values(),
            'by_category' => $byCategory,
            'by_payment_method' => $byPaymentMethod,
            'by_cashier' => $byCashier,
            'low_stock_products' => $lowStockProducts,
            'review_queue' => [
                'stock_conflict_sales' => $stockConflictSales,
                'late_adjusted_shifts' => $lateAdjustedShifts,
            ],
            'audit_logs' => $auditLogs,
        ]);
    }

    public function resolveStockConflict(Request $request, Sale $sale): JsonResponse
    {
        Gate::authorize('manage-inventory');

        $sale->update(['stock_conflict' => false]);

        AuditLog::create([
            'user_id' => $request->user()->id,
            'action' => 'stock_conflict_resolved',
            'entity' => 'sale',
            'entity_id' => $sale->id,
            'reason' => $request->input('note', 'Konflik stok offline telah ditinjau oleh Manager'),
            'created_at' => now(),
        ]);

        return response()->json(['sale' => $sale]);
    }
}

