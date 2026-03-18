<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reception_note_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('reception_note_id')->constrained('reception_notes')->cascadeOnDelete();
            $table->foreignId('purchase_order_line_id')->nullable()->constrained('purchase_order_lines')->nullOnDelete();
            $table->unsignedBigInteger('product_id')->nullable();
            $table->string('description');
            $table->decimal('ordered_quantity', 15, 4)->default(0);
            $table->decimal('received_quantity', 15, 4)->default(0);
            $table->decimal('rejected_quantity', 15, 4)->default(0);
            $table->string('unit')->nullable();
            $table->text('notes')->nullable();
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->index('reception_note_id');
            $table->index('company_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reception_note_lines');
    }
};
