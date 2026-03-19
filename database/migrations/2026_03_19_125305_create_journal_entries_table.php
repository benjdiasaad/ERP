<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('journal_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('reference')->unique();
            $table->text('description')->nullable();
            $table->date('entry_date');
            $table->date('posted_date')->nullable();
            $table->enum('status', ['draft', 'posted'])->default('draft');
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            // Indexes for efficient querying
            $table->index('company_id');
            $table->index('entry_date');
            $table->index('posted_date');
            $table->index('status');
            $table->index(['company_id', 'status']);
            $table->index(['company_id', 'entry_date']);
            $table->index('created_by');
            $table->index('updated_by');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('journal_entries');
    }
};
