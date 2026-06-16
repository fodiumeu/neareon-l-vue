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
        Schema::create('profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('username')->unique();
            $table->string('display_name');
            $table->text('bio')->nullable();
            $table->string('region')->nullable()->index();
            $table->json('languages')->nullable();
            $table->json('interests')->nullable();
            $table->string('profile_visibility')->default('public')->index();
            $table->string('interests_visibility')->default('public');
            $table->string('languages_visibility')->default('public');
            $table->string('region_visibility')->default('mutuals');
            $table->string('social_counts_visibility')->default('public');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('profiles');
    }
};
