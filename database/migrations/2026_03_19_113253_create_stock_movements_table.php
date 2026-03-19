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
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('warehouse_id')->constrained('warehouses')->cascadeOnDelete();
            
            // Polymorphic source (ReceptionNote, DeliveryNote, StockInventory, etc.)
            $table->string('source_type'); // e.g., 'App\Models\Purchasing\ReceptionNote'
            $table->unsignedBigInteger('source_id');
            
            // Movement type: in, out, transfer, adjustment, return, initial
            $table->enum('type', ['in', 'out', 'transfer', 'adjustment', 'return', 'initial']);
            
            // Quantity moved (positive for in/adjustment, negative for out/return)
            $table->decimal('quantity', 15, 2);
            
            // Reference/notes
            $table->string('reference')->nullable();
            $table->text('notes')->nullable();
            
            // For transfers: destination warehouse
            $table->foreignId('destination_warehouse_id')->nullable()->constrained('warehouses')->cascadeOnDelete();
            
            // Metadata
            $table->string('created_by')->nullable(); // user ID or name
            $table->timestamps();
            
            // Indexes
            $table->index(['company_id', 'product_id', 'warehouse_id']);
            $table->index(['source_type', 'source_id']);
            $table->index('type');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
    }
};
