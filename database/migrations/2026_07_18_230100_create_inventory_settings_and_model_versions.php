<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_settings', function (Blueprint $table) {
            $table->id();
            $table->string('scope_key', 64)->unique();
            $table->foreignId('product_id')->nullable()->unique()->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('lead_time_days')->default(3);
            $table->unsignedSmallInteger('review_period_days')->default(7);
            $table->double('service_level')->default(0.95);
            $table->unsignedSmallInteger('prediction_horizon_days')->default(1);
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('model_versions', function (Blueprint $table) {
            $table->id();
            $table->string('model_name');
            $table->string('model_version', 50);
            $table->string('schema_version', 20);
            $table->char('artifact_file_checksum', 64)->unique();
            $table->char('artifact_payload_checksum', 64)->nullable();
            $table->json('feature_order');
            $table->double('threshold');
            $table->json('metrics');
            $table->json('training_metadata');
            $table->json('manifest_snapshot');
            $table->boolean('is_active')->default(false)->index();
            $table->timestamps();

            $table->unique(
                ['model_name', 'model_version', 'artifact_file_checksum'],
                'model_versions_release_unique',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('model_versions');
        Schema::dropIfExists('inventory_settings');
    }
};
