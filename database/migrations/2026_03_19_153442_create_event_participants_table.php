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
        Schema::create('event_participants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('event_id')->constrained('events')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('personnel_id')->nullable()->constrained('personnels')->nullOnDelete();
            $table->string('name')->nullable();
            $table->string('email')->nullable();
            $table->enum('role', ['organizer', 'speaker', 'attendee', 'guest'])->default('attendee');
            $table->enum('rsvp_status', ['pending', 'confirmed', 'declined'])->default('pending');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['company_id', 'event_id']);
            $table->index(['event_id', 'rsvp_status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('event_participants');
    }
};
