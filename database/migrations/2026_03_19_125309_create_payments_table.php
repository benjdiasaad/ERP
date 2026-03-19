<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            
            // Polymorphic payable (Invoice or PurchaseInvoice)
            $table->morphs('payable');
            
            // Direction: incoming (from customer) or outgoing (to supplier)
            $table->enum('direction', ['incoming', 'outgoing']);
            
            // Payment details
            $table->decimal('amount', 15, 2);
            $table->foreignId('payment_method_id')->nullable()->constrained('payment_methods')->nullOnDelete();
            $table->foreignId('bank_account_id')->nullable()->constrained('bank_accounts')->nullOnDelete();
            
            // Reference and dates
            $table->string('reference')->nullable();
            $table->date('payment_date');
            $table->text('notes')->nullable();
            
            // Status: pending, confirmed, cancelled
            $table->enum('status', ['pending', 'confirmed', 'cancelled'])->default('pending');
            
            // Tracking
            $table->timestamp('confirmed_at')->nullable();
            $table->foreignId('confirmed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            
            // Indexes
            $table->index(['company_id', 'payable_type', 'payable_id']);
            $table->index(['company_id', 'direction']);
            $table->index(['company_id', 'status']);
            $table->index('payment_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
