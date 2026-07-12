<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            // Relasi ke kategori
            $table->foreignId('category_id')
                ->constrained()
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            // Nama Barang
            $table->string('name');

            // Fast / Medium / Slow / Very Slow
            $table->enum('moving_type',[
                'FAST',
                'MEDIUM',
                'SLOW',
                'VERY_SLOW'
            ]);

            // Stok minimum
            $table->unsignedInteger('minimum_stock')->default(10);

            // Status aktif
            $table->boolean('is_active')->default(true);

            // Keterangan
            $table->text('description')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
