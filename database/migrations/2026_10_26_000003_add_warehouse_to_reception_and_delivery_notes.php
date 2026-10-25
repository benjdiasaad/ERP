<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Where goods are received into / shipped from, so receptions and deliveries
 * can update stock levels. Nullable: notes without a warehouse don't move stock.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reception_notes', function (Blueprint $table) {
            $table->foreignId('warehouse_id')->nullable()->after('supplier_id')->constrained('warehouses')->nullOnDelete();
        });

        Schema::table('delivery_notes', function (Blueprint $table) {
            $table->foreignId('warehouse_id')->nullable()->after('customer_id')->constrained('warehouses')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('delivery_notes', fn (Blueprint $table) => $table->dropConstrainedForeignId('warehouse_id'));
        Schema::table('reception_notes', fn (Blueprint $table) => $table->dropConstrainedForeignId('warehouse_id'));
    }
};
