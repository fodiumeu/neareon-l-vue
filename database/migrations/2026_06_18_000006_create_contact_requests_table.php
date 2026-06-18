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
        Schema::create('contact_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sender_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('receiver_id')->constrained('users')->cascadeOnDelete();
            $table->string('message', 250)->nullable();
            $table->string('status')->default('pending');
            $table->timestamp('responded_at')->nullable();
            $table->timestamps();

            $table->unique(['sender_id', 'receiver_id']);
            $table->index('sender_id');
            $table->index('receiver_id');
        });

        if (DB::getDriverName() === 'sqlite') {
            DB::statement(
                "CREATE TRIGGER contact_requests_sender_receiver_insert
                BEFORE INSERT ON contact_requests
                WHEN NEW.sender_id = NEW.receiver_id
                BEGIN
                    SELECT RAISE(ABORT, 'A contact request sender and receiver must differ.');
                END",
            );
            DB::statement(
                "CREATE TRIGGER contact_requests_sender_receiver_update
                BEFORE UPDATE OF sender_id, receiver_id ON contact_requests
                WHEN NEW.sender_id = NEW.receiver_id
                BEGIN
                    SELECT RAISE(ABORT, 'A contact request sender and receiver must differ.');
                END",
            );
        } else {
            DB::statement(
                'ALTER TABLE contact_requests
                ADD CONSTRAINT contact_requests_sender_receiver_check
                CHECK (sender_id <> receiver_id)',
            );
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('contact_requests');
    }
};
