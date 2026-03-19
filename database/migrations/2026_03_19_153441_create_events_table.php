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
        Schema::create('events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('event_category_id')->constrained('event_categories')->cascadeOnDelete();
            $table->string('reference')->unique();
            $table->string('title');
            $table->enum('type', ['meeting', 'conference', 'training', 'workshop', 'social', 'holiday']);
            $table->text('description')->nullable();
            $table->string('location')->nullable();
            $table->dateTime('start_date');
            $table->dateTime('end_date');
            $table->boolean('is_mandatory')->default(false);
            $table->decimal('budget', 15, 2)->nullable();
            $table->json('recurring_pattern')->nullable(); // { frequency: 'daily|weekly|monthly|yearly', interval: 1, end_date: '2026-12-31', days_of_week: [1,3,5] }
            $table->enum('status', ['planned', 'confirmed', 'in_progress', 'completed', 'cancelled', 'postponed'])->default('planned');
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            
            // Lifecycle tracking fields
            $table->foreignId('confirmed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('confirmed_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('cancelled_at')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->foreignId('completed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('postponed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('postponed_at')->nullable();
            $table->text('postponement_reason')->nullable();
            
            $table->timestamps();
            $table->softDeletes();

            $table->index(['company_id', 'status']);
            $table->index(['company_id', 'start_date']);
            $table->index('event_category_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('events');
    }
};
