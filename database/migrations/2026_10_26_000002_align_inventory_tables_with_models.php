<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The inventory models, services, requests and factories were written against
 * a richer schema than the original migrations created. This brings the
 * tables in line with the code.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('warehouses', function (Blueprint $table) {
            $table->string('city', 100)->nullable()->after('address');
            $table->string('state', 100)->nullable()->after('city');
            $table->string('country', 100)->nullable()->default('MA')->after('state');
            $table->string('postal_code', 20)->nullable()->after('country');
            $table->boolean('is_active')->default(true)->after('is_default');
            $table->text('notes')->nullable()->after('is_active');
        });

        Schema::table('stock_inventories', function (Blueprint $table) {
            $table->enum('status', ['draft', 'in_progress', 'validated', 'completed', 'cancelled'])->default('draft')->change();
            $table->date('count_date')->nullable()->change();
            $table->timestamp('counted_at')->nullable()->after('count_date');
            $table->timestamp('validated_at')->nullable()->after('counted_at');
            $table->foreignId('validated_by')->nullable()->after('validated_at')->constrained('users')->nullOnDelete();
        });

        Schema::table('stock_inventory_lines', function (Blueprint $table) {
            $table->foreignId('warehouse_id')->nullable()->after('product_id')->constrained('warehouses')->nullOnDelete();
            $table->decimal('counted_quantity', 15, 2)->default(0)->change();
            $table->decimal('variance', 15, 2)->default(0)->change();
            $table->decimal('variance_percentage', 8, 2)->default(0)->change();
        });

        // Manual movements (entries, exits, transfers typed by a user) have no source document.
        Schema::table('stock_movements', function (Blueprint $table) {
            $table->string('source_type')->nullable()->change();
            $table->unsignedBigInteger('source_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('stock_movements', function (Blueprint $table) {
            $table->string('source_type')->nullable(false)->change();
            $table->unsignedBigInteger('source_id')->nullable(false)->change();
        });

        Schema::table('stock_inventory_lines', function (Blueprint $table) {
            $table->dropConstrainedForeignId('warehouse_id');
        });

        Schema::table('stock_inventories', function (Blueprint $table) {
            $table->dropConstrainedForeignId('validated_by');
            $table->dropColumn(['counted_at', 'validated_at']);
            $table->enum('status', ['draft', 'in_progress', 'completed', 'cancelled'])->default('draft')->change();
        });

        Schema::table('warehouses', function (Blueprint $table) {
            $table->dropColumn(['city', 'state', 'country', 'postal_code', 'is_active', 'notes']);
        });
    }
};
