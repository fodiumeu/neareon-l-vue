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
        Schema::create('events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('owner_id')->constrained('users')->cascadeOnDelete();
            $table
                ->foreignId('category_interest_option_id')
                ->nullable()
                ->constrained('interest_options')
                ->nullOnDelete();
            $table->string('title');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->timestamp('starts_at');
            $table->timestamp('ends_at')->nullable();
            $table->string('region')->nullable();
            $table->string('postal_code', 20)->nullable();
            $table->string('country_code', 2)->nullable();
            $table->string('visibility')->default('public');
            $table->string('status')->default('active');
            $table->unsignedInteger('max_attendees')->nullable();
            $table->timestamps();

            $table->index('owner_id');
            $table->index('category_interest_option_id');
            $table->index('visibility');
            $table->index('status');
            $table->index('starts_at');
            $table->index('region');
            $table->index('postal_code');
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
