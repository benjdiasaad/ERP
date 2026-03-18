<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('delivery_note_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('delivery_note_id')->constrained('delivery_notes')->cascadeOnDelete();
            $table->foreignId('sales_order_line_id')->nullable()->constrained('sales_order_lines')->nullOnDelete();
            $table->unsignedBigInteger('product_id')->nullable();
            $table->string('description');
            $table->decimal('ordered_quantity', 10, 2)->default(0);
            $table->decimal('shipped_quantity', 10, 2)->default(0);
            $table->decimal('returned_quantity', 10, 2)->default(0);
            $table->string('unit')->nullable();
            $table->integer('sort_order')->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('delivery_note_id');
            $table->index('sales_order_line_id');
            $table->index('product_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('delivery_note_lines');
    }
};
