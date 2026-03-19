<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('category_id')->nullable()->constrained('product_categories')->nullOnDelete();
            $table->string('code', 100);
            $table->string('name', 255);
            $table->text('description')->nullable();
            $table->enum('type', ['product', 'service', 'consumable'])->default('product');
            $table->string('unit', 50)->nullable();
            $table->decimal('purchase_price', 15, 2)->default(0);
            $table->decimal('sale_price', 15, 2)->default(0);
            $table->decimal('tax_rate', 5, 2)->default(0);
            $table->string('barcode', 100)->nullable();
            $table->string('image_path', 500)->nullable();
            $table->decimal('min_stock_level', 15, 4)->default(0);
            $table->decimal('max_stock_level', 15, 4)->nullable();
            $table->decimal('reorder_point', 15, 4)->default(0);
            $table->boolean('is_active')->default(true);
            $table->boolean('is_purchasable')->default(true);
            $table->boolean('is_sellable')->default(true);
            $table->boolean('is_stockable')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['company_id', 'code']);
            $table->index('company_id');
            $table->index('category_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
