<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recommendation_runs', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->string('idempotency_key', 100)->unique();
            $table->string('status', 20)->index();
            $table->foreignId('triggered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('model_version_id')->constrained()->restrictOnDelete();
            $table->foreignId('retry_of_id')->nullable()->constrained('recommendation_runs')->nullOnDelete();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->date('data_date')->nullable()->index();
            $table->unsignedInteger('total_products')->default(0);
            $table->unsignedInteger('processed_products')->default(0);
            $table->unsignedInteger('succeeded_products')->default(0);
            $table->unsignedInteger('failed_products')->default(0);
            $table->unsignedInteger('insufficient_products')->default(0);
            $table->json('parameter_snapshot');
            $table->text('error_summary')->nullable();
            $table->timestamps();
        });

        Schema::create('stock_recommendations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('recommendation_run_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->string('item_status', 30)->index();
            $table->date('data_date')->nullable()->index();
            $table->unsignedSmallInteger('history_count')->default(0);
            $table->double('current_stock')->nullable();
            $table->double('current_outgoing_stock')->nullable();
            $table->unsignedInteger('on_order_quantity')->default(0);
            $table->double('average_sales_7')->nullable();
            $table->double('std_sales_7')->nullable();
            $table->double('average_sales_30')->nullable();
            $table->double('std_sales_30')->nullable();
            $table->double('stock_coverage_days')->nullable();
            $table->unsignedTinyInteger('weekday')->nullable();
            $table->unsignedSmallInteger('horizon_days')->nullable();
            $table->double('inventory_position')->nullable();
            $table->double('projected_inventory')->nullable();
            $table->unsignedInteger('safety_stock')->nullable();
            $table->unsignedInteger('reorder_point')->nullable();
            $table->unsignedInteger('target_stock')->nullable();
            $table->double('joint_log_likelihood_0')->nullable();
            $table->double('joint_log_likelihood_1')->nullable();
            $table->double('model_probability_0')->nullable();
            $table->double('model_probability_positive')->nullable();
            $table->double('model_threshold')->nullable();
            $table->string('model_classification', 30)->nullable();
            $table->boolean('inventory_trigger')->nullable()->index();
            $table->string('final_recommendation', 30)->index();
            $table->unsignedInteger('recommended_quantity')->default(0);
            $table->json('reason_codes');
            $table->json('warnings');
            $table->json('feature_payload')->nullable();
            $table->timestamps();

            $table->unique(
                ['recommendation_run_id', 'product_id'],
                'stock_recommendations_run_product_unique',
            );
            $table->index(['product_id', 'data_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_recommendations');
        Schema::dropIfExists('recommendation_runs');
    }
};
