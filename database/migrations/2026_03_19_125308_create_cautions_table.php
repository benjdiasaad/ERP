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
        Schema::create('cautions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('caution_type_id')->constrained('caution_types')->cascadeOnDelete();
            
            // Direction: given (caution we give to customer/partner) or received (caution we receive from supplier)
            $table->enum('direction', ['given', 'received']);
            
            // Partner type: customer, supplier, other
            $table->enum('partner_type', ['customer', 'supplier', 'other']);
            
            // Partner ID (nullable for 'other' type)
            $table->unsignedBigInteger('partner_id')->nullable();
            
            // Polymorphic related (Contract, PurchaseOrder, SalesOrder, Project, etc.)
            $table->string('related_type')->nullable(); // e.g., 'App\Models\Purchasing\PurchaseOrder'
            $table->unsignedBigInteger('related_id')->nullable();
            
            // Amount
            $table->decimal('amount', 15, 2);
            
            // Currency (default to company currency, but can be overridden)
            $table->string('currency')->default('MAD');
            
            // Status lifecycle: draft → active → partially_returned/returned/expired/forfeited
            $table->enum('status', ['draft', 'active', 'partially_returned', 'returned', 'expired', 'forfeited'])->default('draft');
            
            // Dates
            $table->date('issue_date')->nullable();
            $table->date('expiry_date')->nullable();
            $table->date('return_date')->nullable();
            
            // Return tracking
            $table->decimal('amount_returned', 15, 2)->default(0);
            $table->decimal('amount_forfeited', 15, 2)->default(0);
            
            // Bank information (for caution held in bank)
            $table->string('bank_name')->nullable();
            $table->string('bank_account')->nullable();
            $table->string('bank_reference')->nullable(); // e.g., check number, transfer reference
            
            // Document reference/notes
            $table->string('document_reference')->nullable();
            $table->text('notes')->nullable();
            
            // Metadata
            $table->string('created_by')->nullable(); // user ID or name
            $table->string('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
            
            // Indexes
            $table->index(['company_id', 'direction']);
            $table->index(['company_id', 'partner_type', 'partner_id']);
            $table->index(['company_id', 'status']);
            $table->index(['related_type', 'related_id']);
            $table->index('expiry_date');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cautions');
    }
};
