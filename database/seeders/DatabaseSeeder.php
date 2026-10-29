<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use App\Models\Register;
use App\Models\Setting;
use App\Models\StockMovement;
use App\Models\StockReceipt;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {
            // 1. Default Store Settings (W1.2)
            $defaultSettings = [
                'store_name' => 'TOKOQU RETAIL',
                'store_address' => 'Jl. Jend. Sudirman No. 88, Jakarta Pusat',
                'store_phone' => '021-5550199',
                'timezone' => 'Asia/Jakarta',
                'receipt_footer' => 'Terima kasih telah berbelanja di Tokoqu.',
                'tax_mode' => 'off', // off | inclusive | exclusive (A5)
                'tax_rate' => '11',
                'return_window_days' => '7',
                'cashier_discount_limit_pct' => '10',
                'allow_negative_stock' => '0',
            ];

            foreach ($defaultSettings as $key => $value) {
                Setting::updateOrCreate(['key' => $key], ['value' => $value, 'updated_at' => now()]);
            }

            // 2. Default Registers (W1.3)
            Register::firstOrCreate(['code' => 'KSR-01'], ['name' => 'Kasir Utama 01', 'is_active' => true]);
            Register::firstOrCreate(['code' => 'KSR-02'], ['name' => 'Kasir Ekspres 02', 'is_active' => true]);

            // 3. Default Users (W1.4)
            $owner = User::firstOrCreate(
                ['email' => 'owner@tokoqu.id'],
                [
                    'name' => 'Hendra Wijaya (Owner)',
                    'password' => Hash::make('password'),
                    'pin_hash' => Hash::make('111111'),
                    'role' => 'owner',
                    'is_active' => true,
                ]
            );

            User::firstOrCreate(
                ['email' => 'manager@tokoqu.id'],
                [
                    'name' => 'Ratna Sari (Manager)',
                    'password' => Hash::make('password'),
                    'pin_hash' => Hash::make('123456'),
                    'role' => 'manager',
                    'is_active' => true,
                ]
            );

            User::firstOrCreate(
                ['email' => 'kasir@tokoqu.id'],
                [
                    'name' => 'Budi Santoso (Kasir)',
                    'password' => Hash::make('password'),
                    'pin_hash' => null,
                    'role' => 'cashier',
                    'is_active' => true,
                ]
            );

            // 4. Categories & Products (W1.5)
            $catMinuman = Category::firstOrCreate(['name' => 'Minuman & Kopi'], ['sort_order' => 1]);
            $catSembako = Category::firstOrCreate(['name' => 'Bahan Pokok'], ['sort_order' => 2]);
            $catCamilan = Category::firstOrCreate(['name' => 'Makanan Ringan'], ['sort_order' => 3]);
            $catKebutuhan = Category::firstOrCreate(['name' => 'Kebutuhan Rumah'], ['sort_order' => 4]);

            $catalog = [
                ['sku' => 'MNM-001', 'barcode' => '899100100001', 'name' => 'Kopi Arabika Gayo 250g', 'category_id' => $catMinuman->id, 'price' => 65000, 'cost' => 48000, 'track_stock' => true, 'allow_decimal_qty' => false, 'min_stock' => 10, 'opening_qty' => 40],
                ['sku' => 'MNM-002', 'barcode' => '899100100002', 'name' => 'Teh Melati Premium 100g', 'category_id' => $catMinuman->id, 'price' => 24000, 'cost' => 16500, 'track_stock' => true, 'allow_decimal_qty' => false, 'min_stock' => 15, 'opening_qty' => 50],
                ['sku' => 'MNM-003', 'barcode' => '899100100003', 'name' => 'Air Mineral Botol 600ml', 'category_id' => $catMinuman->id, 'price' => 4500, 'cost' => 2800, 'track_stock' => true, 'allow_decimal_qty' => false, 'min_stock' => 24, 'opening_qty' => 120],
                ['sku' => 'SMB-001', 'barcode' => '899200200001', 'name' => 'Beras Pandan Wangi Curah (kg)', 'category_id' => $catSembako->id, 'price' => 16500, 'cost' => 14200, 'track_stock' => true, 'allow_decimal_qty' => true, 'min_stock' => 25, 'opening_qty' => 100],
                ['sku' => 'SMB-002', 'barcode' => '899200200002', 'name' => 'Minyak Goreng Sawit 1L', 'category_id' => $catSembako->id, 'price' => 19500, 'cost' => 17000, 'track_stock' => true, 'allow_decimal_qty' => false, 'min_stock' => 20, 'opening_qty' => 60],
                ['sku' => 'SMB-003', 'barcode' => '899200200003', 'name' => 'Gula Pasir Kristal 1kg', 'category_id' => $catSembako->id, 'price' => 17500, 'cost' => 15200, 'track_stock' => true, 'allow_decimal_qty' => false, 'min_stock' => 20, 'opening_qty' => 80],
                ['sku' => 'CML-001', 'barcode' => '899300300001', 'name' => 'Biskuit Mentega Kaleng 300g', 'category_id' => $catCamilan->id, 'price' => 42000, 'cost' => 31000, 'track_stock' => true, 'allow_decimal_qty' => false, 'min_stock' => 8, 'opening_qty' => 30],
                ['sku' => 'CML-002', 'barcode' => '899300300002', 'name' => 'Cokelat Hitam 70% 80g', 'category_id' => $catCamilan->id, 'price' => 28000, 'cost' => 20000, 'track_stock' => true, 'allow_decimal_qty' => false, 'min_stock' => 10, 'opening_qty' => 45],
                ['sku' => 'KBT-001', 'barcode' => '899400400001', 'name' => 'Sabun Cuci Piring 750ml', 'category_id' => $catKebutuhan->id, 'price' => 15500, 'cost' => 11800, 'track_stock' => true, 'allow_decimal_qty' => false, 'min_stock' => 12, 'opening_qty' => 36],
                ['sku' => 'KBT-002', 'barcode' => '899400400002', 'name' => 'Kantong Belanja Kain Eksklusif', 'category_id' => $catKebutuhan->id, 'price' => 10000, 'cost' => 6000, 'track_stock' => false, 'allow_decimal_qty' => false, 'min_stock' => 0, 'opening_qty' => 0],
            ];

            if (StockReceipt::where('receipt_no', 'OPENING-0001')->doesntExist()) {
                $receipt = StockReceipt::create([
                    'receipt_no' => 'OPENING-0001',
                    'type' => 'opening',
                    'supplier_name' => 'Saldo Awal Gudang',
                    'reference' => 'INIT-SETUP',
                    'note' => 'Stok awal pembukaan toko (W1.6)',
                    'user_id' => $owner->id,
                    'created_at' => now(),
                ]);

                foreach ($catalog as $item) {
                    $openingQty = $item['opening_qty'];
                    unset($item['opening_qty']);

                    $product = Product::create(array_merge($item, [
                        'stock_qty' => $openingQty,
                        'is_active' => true,
                    ]));

                    if ($product->track_stock && $openingQty > 0) {
                        $receipt->items()->create([
                            'product_id' => $product->id,
                            'qty' => $openingQty,
                            'unit_cost' => $product->cost,
                        ]);

                        StockMovement::create([
                            'product_id' => $product->id,
                            'type' => 'opening',
                            'qty_delta' => $openingQty,
                            'ref_type' => 'stock_receipt',
                            'ref_id' => $receipt->id,
                            'user_id' => $owner->id,
                            'note' => 'Stok awal pembukaan toko',
                            'created_at' => now(),
                        ]);
                    }
                }
            }
        });
    }
}

