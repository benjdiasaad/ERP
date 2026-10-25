<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Approved purchase requests are converted into purchase orders; the original
 * status list had no value for that outcome.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_requests', function (Blueprint $table) {
            $table->enum('status', ['draft', 'submitted', 'approved', 'rejected', 'cancelled', 'converted'])->default('draft')->change();
        });
    }

    public function down(): void
    {
        Schema::table('purchase_requests', function (Blueprint $table) {
            $table->enum('status', ['draft', 'submitted', 'approved', 'rejected', 'cancelled'])->default('draft')->change();
        });
    }
};
