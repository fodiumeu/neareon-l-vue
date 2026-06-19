<?php

use App\Enums\ContactPermission;
use App\Enums\FollowPermission;
use App\Enums\MessagePermission;
use App\Enums\OnlineStatusVisibility;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('profiles', function (Blueprint $table) {
            $table->string('follow_permission')
                ->default(FollowPermission::Everyone->value);
            $table->string('contact_permission')
                ->default(ContactPermission::Everyone->value);
            $table->string('message_permission')
                ->default(MessagePermission::ExistingConversations->value);
            $table->string('online_status_visibility')
                ->default(OnlineStatusVisibility::MutualContacts->value);
        });

        DB::table('profiles')
            ->where('profile_visibility', 'mutuals')
            ->update(['profile_visibility' => 'contacts']);

        DB::table('profiles')->update([
            'message_permission' => MessagePermission::ContactsOnly->value,
        ]);
    }

    public function down(): void
    {
        DB::table('profiles')
            ->where('profile_visibility', 'contacts')
            ->update(['profile_visibility' => 'mutuals']);

        Schema::table('profiles', function (Blueprint $table) {
            $table->dropColumn([
                'follow_permission',
                'contact_permission',
                'message_permission',
                'online_status_visibility',
            ]);
        });
    }
};
