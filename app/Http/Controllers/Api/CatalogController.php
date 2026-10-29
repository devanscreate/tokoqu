<?php

namespace App\Http\Controllers\Api;

use App\Models\Category;
use App\Models\Product;
use App\Models\Register;
use App\Models\Setting;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;

class CatalogController
{
    protected function ensureProductImageColumn(): void
    {
        if (! Schema::hasColumn('products', 'image_url')) {
            Schema::table('products', function (Blueprint $table) {
                $table->text('image_url')->nullable();
            });
        }
    }
    /**
     * GET /api/v1/catalog?since=
     * Delta sync of products, categories, registers, settings (inactive/trashed products included so caches can remove them).
     */
    public function sync(Request $request): JsonResponse
    {
        $since = $request->query('since');
        $productsQuery = Product::query()->withTrashed()->with('category')->orderBy('name');

        if ($since) {
            $sinceDate = Carbon::parse((string) $since);
            $productsQuery->where('updated_at', '>=', $sinceDate);
        }

        $todayYymmdd = now()->timezone(config('app.timezone', 'Asia/Jakarta'))->format('ymd');
        $sequences = DB::table('invoice_sequences')
            ->where('date_yymmdd', $todayYymmdd)
            ->pluck('last_seq', 'register_id');

        return response()->json([
            'server_time' => now()->toIso8601String(),
            'settings' => Setting::getMap(),
            'registers' => Register::query()->orderBy('code')->get(),
            'categories' => Category::query()->orderBy('sort_order')->orderBy('name')->get(),
            'products' => $productsQuery->get(),
            'invoice_sequences' => $sequences,
        ]);
    }

    public function storeCategory(Request $request): JsonResponse
    {
        Gate::authorize('manage-catalog');

        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'sort_order' => ['nullable', 'integer'],
        ]);

        $category = Category::create([
            'name' => $data['name'],
            'sort_order' => (int) ($data['sort_order'] ?? 0),
        ]);

        return response()->json(['category' => $category], 201);
    }

    public function updateCategory(Request $request, Category $category): JsonResponse
    {
        Gate::authorize('manage-catalog');

        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'sort_order' => ['nullable', 'integer'],
        ]);

        $category->update([
            'name' => $data['name'],
            'sort_order' => (int) ($data['sort_order'] ?? $category->sort_order),
        ]);

        return response()->json(['category' => $category]);
    }

    public function storeProduct(Request $request): JsonResponse
    {
        Gate::authorize('manage-catalog');
        $this->ensureProductImageColumn();

        $data = $request->validate([
            'sku' => ['required', 'string', 'max:64', 'unique:products,sku'],
            'barcode' => ['nullable', 'string', 'max:64', 'unique:products,barcode'],
            'name' => ['required', 'string', 'max:200'],
            'image_url' => ['nullable', 'string', 'max:200000'],
            'category_id' => ['nullable', 'exists:categories,id'],
            'price' => ['required', 'integer', 'min:0'],
            'cost' => ['required', 'integer', 'min:0'],
            'track_stock' => ['boolean'],
            'allow_decimal_qty' => ['boolean'],
            'min_stock' => ['numeric', 'min:0'],
            'is_active' => ['boolean'],
        ]);

        // R3: Nobody edits stock_qty directly; starts at 0 and moves via stock_movements
        $product = Product::create(array_merge($data, [
            'stock_qty' => 0,
        ]));

        return response()->json(['product' => $product->load('category')], 201);
    }

    public function updateProduct(Request $request, Product $product): JsonResponse
    {
        Gate::authorize('manage-catalog');
        $this->ensureProductImageColumn();

        $data = $request->validate([
            'sku' => ['required', 'string', 'max:64', 'unique:products,sku,'.$product->id],
            'barcode' => ['nullable', 'string', 'max:64', 'unique:products,barcode,'.$product->id],
            'name' => ['required', 'string', 'max:200'],
            'image_url' => ['nullable', 'string', 'max:200000'],
            'category_id' => ['nullable', 'exists:categories,id'],
            'price' => ['required', 'integer', 'min:0'],
            'cost' => ['required', 'integer', 'min:0'],
            'track_stock' => ['boolean'],
            'allow_decimal_qty' => ['boolean'],
            'min_stock' => ['numeric', 'min:0'],
            'is_active' => ['boolean'],
        ]);

        $product->update($data);

        return response()->json(['product' => $product->fresh('category')]);
    }

    /**
     * W1.5 CSV Import: sku, barcode, name, category, price, cost, track_stock, min_stock
     */
    public function importCsv(Request $request): JsonResponse
    {
        Gate::authorize('manage-catalog');

        $request->validate([
            'rows' => ['required', 'array', 'min:1'],
            'rows.*.sku' => ['required', 'string', 'max:64'],
            'rows.*.name' => ['required', 'string', 'max:200'],
            'rows.*.price' => ['required', 'integer', 'min:0'],
            'rows.*.cost' => ['nullable', 'integer', 'min:0'],
        ]);

        $importedCount = 0;

        DB::transaction(function () use ($request, &$importedCount) {
            foreach ($request->input('rows') as $row) {
                $catId = null;
                if (! empty($row['category'])) {
                    $cat = Category::firstOrCreate(['name' => trim((string) $row['category'])]);
                    $catId = $cat->id;
                }

                Product::updateOrCreate(
                    ['sku' => trim((string) $row['sku'])],
                    [
                        'barcode' => ! empty($row['barcode']) ? trim((string) $row['barcode']) : null,
                        'name' => trim((string) $row['name']),
                        'category_id' => $catId,
                        'price' => (int) $row['price'],
                        'cost' => (int) ($row['cost'] ?? 0),
                        'track_stock' => isset($row['track_stock']) ? (bool) $row['track_stock'] : true,
                        'allow_decimal_qty' => isset($row['allow_decimal_qty']) ? (bool) $row['allow_decimal_qty'] : false,
                        'min_stock' => (float) ($row['min_stock'] ?? 0),
                        'is_active' => true,
                    ]
                );
                $importedCount++;
            }
        });

        return response()->json([
            'message' => "Berhasil mengimpor {$importedCount} produk.",
            'imported_count' => $importedCount,
        ]);
    }
}

