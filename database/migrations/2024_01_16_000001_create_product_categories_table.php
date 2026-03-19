<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('product_categories')->nullOnDelete();
            $table->string('name', 255);
            $table->string('code', 50)->nullable();
            $table->text('description')->nullable();
            $table->string('image_path', 500)->nullable();
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->index('company_id');
            $table->index('parent_id');
        });

        // Partial unique index: (company_id, code) where code is not null
        // Using raw statement for PostgreSQL compatibility
        \Illuminate\Support\Facades\DB::statement(
            'CREATE UNIQUE INDEX product_categories_company_code_unique ON product_categories (company_id, code) WHERE code IS NOT NULL'
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('product_categories');
    }
};
