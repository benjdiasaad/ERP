<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_inventory_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_inventory_id')->constrained('stock_inventories')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->decimal('theoretical_quantity', 15, 2);
            $table->decimal('counted_quantity', 15, 2);
            $table->decimal('variance', 15, 2);
            $table->decimal('variance_percentage', 5, 2);
            $table->text('notes')->nullable();
            $table->timestamps();

            // Indexes for query performance
            $table->index('stock_inventory_id');
            $table->index('product_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_inventory_lines');
    }
};
