<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * As for inventory, the finance and caution models, services and factories were
 * written against a different schema than the original migrations created.
 * This brings bank accounts, journal entries and cautions in line with the code.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bank_accounts', function (Blueprint $table) {
            $table->boolean('is_active')->default(true)->after('balance');
        });

        Schema::table('journal_entries', function (Blueprint $table) {
            $table->dropUnique(['reference']);
            $table->dropIndex(['posted_date']);
        });

        Schema::table('journal_entries', function (Blueprint $table) {
            $table->renameColumn('entry_date', 'date');
            $table->dropColumn('posted_date');
        });

        Schema::table('journal_entries', function (Blueprint $table) {
            // References are numbered per company.
            $table->unique(['company_id', 'reference']);
            $table->enum('status', ['draft', 'posted', 'cancelled'])->default('draft')->change();
            $table->decimal('total_debit', 15, 2)->default(0)->after('status');
            $table->decimal('total_credit', 15, 2)->default(0)->after('total_debit');
            $table->timestamp('posted_at')->nullable()->after('total_credit');
            $table->foreignId('posted_by')->nullable()->after('posted_at')->constrained('users')->nullOnDelete();
            $table->timestamp('cancelled_at')->nullable()->after('posted_by');
            $table->foreignId('cancelled_by')->nullable()->after('cancelled_at')->constrained('users')->nullOnDelete();
            $table->text('cancellation_reason')->nullable()->after('cancelled_by');
            $table->unsignedBigInteger('created_by')->nullable()->change();
        });

        Schema::table('journal_entry_lines', function (Blueprint $table) {
            $table->foreignId('company_id')->nullable()->after('id')->constrained('companies')->cascadeOnDelete();
            $table->unsignedInteger('sort_order')->default(0)->after('description');
        });

        // The partner is polymorphic: the column holds the model class (Customer, Supplier),
        // null for a partner that is neither.
        Schema::table('cautions', function (Blueprint $table) {
            $table->string('partner_type')->nullable()->change();
            $table->enum('status', ['draft', 'active', 'partially_returned', 'returned', 'expired', 'forfeited', 'cancelled'])->default('draft')->change();
        });

        DB::table('cautions')->where('partner_type', 'customer')->update(['partner_type' => 'App\\Models\\Sales\\Customer']);
        DB::table('cautions')->where('partner_type', 'supplier')->update(['partner_type' => 'App\\Models\\Purchasing\\Supplier']);
        DB::table('cautions')->where('partner_type', 'other')->update(['partner_type' => null, 'partner_id' => null]);

        Schema::table('caution_histories', function (Blueprint $table) {
            $table->string('action', 30)->default('created')->change();
            $table->decimal('amount', 15, 2)->nullable()->after('action');
            $table->string('previous_status', 30)->nullable()->after('amount');
            $table->string('new_status', 30)->nullable()->after('previous_status');
        });
    }

    public function down(): void
    {
        Schema::table('caution_histories', function (Blueprint $table) {
            $table->dropColumn(['amount', 'previous_status', 'new_status']);
        });

        Schema::table('journal_entry_lines', function (Blueprint $table) {
            $table->dropConstrainedForeignId('company_id');
            $table->dropColumn('sort_order');
        });

        Schema::table('journal_entries', function (Blueprint $table) {
            $table->dropUnique(['company_id', 'reference']);
            $table->dropConstrainedForeignId('posted_by');
            $table->dropConstrainedForeignId('cancelled_by');
            $table->dropColumn(['total_debit', 'total_credit', 'posted_at', 'cancelled_at', 'cancellation_reason']);
        });

        Schema::table('journal_entries', function (Blueprint $table) {
            $table->renameColumn('date', 'entry_date');
            $table->date('posted_date')->nullable()->index();
            $table->unique('reference');
        });

        Schema::table('bank_accounts', function (Blueprint $table) {
            $table->dropColumn('is_active');
        });
    }
};
