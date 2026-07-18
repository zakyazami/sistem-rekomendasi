<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role', 20)->default('pemilik')->index();
        });

        $firstUserId = DB::table('users')->orderBy('id')->value('id');
        if ($firstUserId !== null) {
            DB::table('users')->where('id', $firstUserId)->update(['role' => 'admin']);
        }

        Schema::table('categories', function (Blueprint $table) {
            $table->unique('name');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->string('sku', 50)->nullable()->after('category_id');
            $table->unsignedInteger('on_order_quantity')->default(0)->after('minimum_stock');
            $table->index(['category_id', 'is_active']);
            $table->index('name');
        });

        DB::table('products')
            ->whereNull('sku')
            ->orderBy('id')
            ->get(['id'])
            ->each(fn (object $product) => DB::table('products')
                ->where('id', $product->id)
                ->update(['sku' => sprintf('BRK-LEGACY-%08d', $product->id)]));

        Schema::table('products', function (Blueprint $table) {
            $table->string('sku', 50)->nullable(false)->change();
            $table->unique('sku');
        });

        Schema::table('stock_histories', function (Blueprint $table) {
            $table->unique(['product_id', 'date']);
            $table->index('date');
            $table->index(['product_id', 'date']);
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE stock_histories MODIFY initial_stock INT UNSIGNED NOT NULL');
            DB::statement('ALTER TABLE stock_histories MODIFY incoming_stock INT UNSIGNED NOT NULL DEFAULT 0');
            DB::statement('ALTER TABLE stock_histories MODIFY outgoing_stock INT UNSIGNED NOT NULL DEFAULT 0');
            DB::statement('ALTER TABLE stock_histories MODIFY final_stock INT UNSIGNED NOT NULL');
            DB::statement('ALTER TABLE stock_histories ADD CONSTRAINT chk_stock_formula CHECK (final_stock = initial_stock + incoming_stock - outgoing_stock)');
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE stock_histories DROP CHECK chk_stock_formula');
        }

        Schema::table('stock_histories', function (Blueprint $table) {
            $table->dropUnique(['product_id', 'date']);
            $table->dropIndex(['date']);
            $table->dropIndex(['product_id', 'date']);
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex(['category_id', 'is_active']);
            $table->dropIndex(['name']);
            $table->dropUnique(['sku']);
            $table->dropColumn(['sku', 'on_order_quantity']);
        });

        Schema::table('categories', function (Blueprint $table) {
            $table->dropUnique(['name']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['role']);
            $table->dropColumn('role');
        });
    }
};
