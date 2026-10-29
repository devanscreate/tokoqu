<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('products', 'image_url')) {
            Schema::table('products', function (Blueprint $table) {
                $table->text('image_url')->nullable()->after('name');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('products', 'image_url')) {
            Schema::table('products', function (Blueprint $table) {
                $table->dropColumn('image_url');
            });
        }
    }
};

