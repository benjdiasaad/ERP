<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contracts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('personnel_id')->constrained('personnels')->cascadeOnDelete();
            $table->string('reference')->nullable();
            $table->enum('type', ['CDI', 'CDD', 'stage', 'freelance', 'interim']);
            $table->enum('status', ['draft', 'active', 'expired', 'terminated', 'suspended'])->default('draft');
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->date('trial_period_end_date')->nullable();
            $table->decimal('salary', 15, 2);
            $table->string('salary_currency')->default('MAD');
            $table->decimal('working_hours_per_week', 5, 2)->nullable();
            $table->json('benefits')->nullable();
            $table->string('document_path')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('signed_at')->nullable();
            $table->timestamp('terminated_at')->nullable();
            $table->text('termination_reason')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contracts');
    }
};
