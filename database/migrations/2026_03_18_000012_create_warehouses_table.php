<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('warehouses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('code');
            $table->string('name');
            $table->text('address')->nullable();
            $table->foreignId('manager_id')->nullable()->constrained('personnels')->nullOnDelete();
            $table->boolean('is_default')->default(false);
            $table->timestamps();
            $table->softDeletes();

            // Indexes for query performance
            $table->index('company_id');
            $table->unique(['company_id', 'code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('warehouses');
    }
};
