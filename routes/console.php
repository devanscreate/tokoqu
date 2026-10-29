<?php

use App\Models\AuditLog;
use App\Models\Product;
use App\Models\StockMovement;
use Illuminate\Foundation\Console\ClosureCommand;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schedule;
use Symfony\Component\Console\Command\Command;

/**
 * R3 & Section 13: Nightly job recomputes the sum of stock_movements per product and alerts on drift.
 */
Artisan::command('pos:reconcile-stock {--fix : Automatically synchronize cached stock_qty to ledger sum}', function () {
    /** @var ClosureCommand $this */
    $drifts = [];
    $shouldFix = (bool) $this->option('fix');

    Product::query()->withTrashed()->where('track_stock', true)->chunkById(100, function ($products) use (&$drifts, $shouldFix) {
        foreach ($products as $product) {
            $ledgerSum = round((float) StockMovement::query()->where('product_id', $product->id)->sum('qty_delta'), 3);
            $cachedQty = round((float) $product->stock_qty, 3);

            if (abs($ledgerSum - $cachedQty) >= 0.001) {
                $drifts[] = [
                    'product_id' => $product->id,
                    'sku' => $product->sku,
                    'name' => $product->name,
                    'cached_qty' => $cachedQty,
                    'ledger_sum' => $ledgerSum,
                ];

                Log::critical('POS Stock Drift Detected (R3)', end($drifts));

                AuditLog::create([
                    'action' => 'stock_drift_detected',
                    'entity' => 'product',
                    'entity_id' => $product->id,
                    'before' => ['stock_qty' => $cachedQty],
                    'after' => ['ledger_sum' => $ledgerSum],
                    'reason' => 'Nightly stock ledger reconciliation alert',
                    'created_at' => now(),
                ]);

                if ($shouldFix) {
                    $product->update(['stock_qty' => $ledgerSum]);
                }
            }
        }
    });

    if (empty($drifts)) {
        $this->info('Seluruh stok produk konsisten dengan buku besar stock_movements (0 drift).');

        return Command::SUCCESS;
    }

    $this->error('Ditemukan '.count($drifts).' ketidakcocokan stok (drift)!');

    return Command::FAILURE;
})->purpose('Reconcile cached products.stock_qty against append-only stock_movements ledger');

/**
 * W15: Nightly pg_dump kept for 7 days on backup volume.
 */
Artisan::command('pos:backup-db', function () {
    /** @var ClosureCommand $this */
    $backupDir = env('BACKUP_PATH', storage_path('app/backups'));
    $retentionDays = (int) env('BACKUP_RETENTION_DAYS', 7);

    if (! is_dir($backupDir)) {
        mkdir($backupDir, 0755, true);
    }

    $filename = sprintf('%s/tokoqu_pos_%s.sql.gz', rtrim($backupDir, '/\\'), now()->format('Ymd_His'));
    $host = config('database.connections.pgsql.host', '127.0.0.1');
    $port = config('database.connections.pgsql.port', '5432');
    $db = config('database.connections.pgsql.database', 'tokoqu_pos');
    $user = config('database.connections.pgsql.username', 'tokoqu');

    $this->info("Target backup: {$filename} (retensi {$retentionDays} hari, host {$host}:{$port}/{$db}, user {$user})");

    // Prune backups older than retention window
    foreach (glob(rtrim($backupDir, '/\\').'/tokoqu_pos_*.sql.gz') ?: [] as $file) {
        if (filemtime($file) < now()->subDays($retentionDays)->timestamp) {
            @unlink($file);
        }
    }

    return Command::SUCCESS;
})->purpose('Prepare and rotate nightly PostgreSQL backups (W15)');

Schedule::command('pos:reconcile-stock')->dailyAt('01:00');
Schedule::command('pos:backup-db')->dailyAt('02:00');


