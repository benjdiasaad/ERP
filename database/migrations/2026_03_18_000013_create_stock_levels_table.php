<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_levels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('warehouse_id')->constrained('warehouses')->cascadeOnDelete();
            $table->decimal('quantity_on_hand', 15, 2)->default(0);
            $table->decimal('quantity_reserved', 15, 2)->default(0);
            $table->decimal('quantity_available', 15, 2)->default(0);
            $table->decimal('minimum_level', 15, 2)->default(0);
            $table->decimal('maximum_level', 15, 2)->default(0);
            $table->decimal('reorder_point', 15, 2)->default(0);
            $table->timestamp('last_counted_at')->nullable();
            $table->timestamp('last_movement_at')->nullable();
            $table->timestamps();

            // Unique constraint on company_id, product_id, warehouse_id
            $table->unique(['company_id', 'product_id', 'warehouse_id']);

            // Indexes for query performance
            $table->index('company_id');
            $table->index('product_id');
            $table->index('warehouse_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_levels');
    }
};
