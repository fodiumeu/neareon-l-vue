<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('blocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('blocker_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('blocked_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['blocker_id', 'blocked_id']);
            $table->index('blocker_id');
            $table->index('blocked_id');
        });

        if (DB::getDriverName() === 'sqlite') {
            DB::statement(
                "CREATE TRIGGER blocks_blocker_blocked_insert
                BEFORE INSERT ON blocks
                WHEN NEW.blocker_id = NEW.blocked_id
                BEGIN
                    SELECT RAISE(ABORT, 'A block blocker and blocked user must differ.');
                END",
            );
            DB::statement(
                "CREATE TRIGGER blocks_blocker_blocked_update
                BEFORE UPDATE OF blocker_id, blocked_id ON blocks
                WHEN NEW.blocker_id = NEW.blocked_id
                BEGIN
                    SELECT RAISE(ABORT, 'A block blocker and blocked user must differ.');
                END",
            );
        } else {
            DB::statement(
                'ALTER TABLE blocks
                ADD CONSTRAINT blocks_blocker_blocked_check
                CHECK (blocker_id <> blocked_id)',
            );
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('blocks');
    }
};
