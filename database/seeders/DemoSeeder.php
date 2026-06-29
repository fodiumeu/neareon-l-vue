<?php

namespace Database\Seeders;

use App\Enums\ContactPermission;
use App\Enums\ContactRequestStatus;
use App\Enums\FollowPermission;
use App\Enums\InternalNotificationType;
use App\Enums\MessagePermission;
use App\Enums\OnlineStatusVisibility;
use App\Enums\ProfileVisibility;
use App\Enums\UserRole;
use App\Models\ContactRequest;
use App\Models\Conversation;
use App\Models\ConversationParticipant;
use App\Models\Event;
use App\Models\EventAttendee;
use App\Models\Follow;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\InterestOption;
use App\Models\LanguageOption;
use App\Models\Message;
use App\Models\Profile;
use App\Models\User;
use App\Notifications\InternalNotification;
use Carbon\CarbonInterface;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class DemoSeeder extends Seeder
{
    private const PASSWORD = 'neareon-demo';

    /**
     * Seed a compact, synthetic MVP demo data set.
     */
    public function run(): void
    {
        if (app()->environment('production')) {
            throw new RuntimeException('Der DemoSeeder darf nicht in Production ausgeführt werden.');
        }

        $this->call([
            LanguageOptionSeeder::class,
            InterestOptionSeeder::class,
        ]);

        $languages = LanguageOption::query()->get()->keyBy('code');
        $interests = InterestOption::query()->get()->keyBy('slug');

        $users = $this->seedUsers($languages, $interests);

        $this->seedSocialGraph($users);
        $groups = $this->seedGroups($users, $interests);
        $events = $this->seedEvents($users, $interests);
        $conversation = $this->seedConversation($users);
        $this->seedNotifications($users, $groups, $events, $conversation);
    }

    /**
     * @param  Collection<string, LanguageOption>  $languages
     * @param  Collection<string, InterestOption>  $interests
     * @return array<string, User>
     */
    private function seedUsers($languages, $interests): array
    {
        $definitions = [
            'fodi' => [
                'name' => 'Fodi Demo',
                'email' => 'demo.fodi@neareon.test',
                'username' => 'demo_fodi',
                'display_name' => 'Fodi Demo',
                'region' => 'Hamburg',
                'bio' => 'Hamburger Demo-Profil fuer Community, Events und lokale Kontakte.',
                'languages' => ['de', 'hr'],
                'interests' => ['music', 'technology', 'events'],
                'role' => UserRole::Member,
            ],
            'mira' => [
                'name' => 'Mira Hamburg',
                'email' => 'demo.mira@neareon.test',
                'username' => 'demo_mira',
                'display_name' => 'Mira Hamburg',
                'region' => 'Hamburg',
                'bio' => 'Fotografie, Reisen und Kultur in der Nachbarschaft.',
                'languages' => ['de', 'en'],
                'interests' => ['photography', 'travel', 'culture'],
                'role' => UserRole::Member,
            ],
            'jonas' => [
                'name' => 'Jonas Berlin',
                'email' => 'demo.jonas@neareon.test',
                'username' => 'demo_jonas',
                'display_name' => 'Jonas Berlin',
                'region' => 'Berlin',
                'bio' => 'Sport, Community und Musik zwischen Kiez und Park.',
                'languages' => ['de', 'en'],
                'interests' => ['sport', 'community', 'music'],
                'role' => UserRole::Member,
            ],
            'lea' => [
                'name' => 'Lea Muenchen',
                'email' => 'demo.lea@neareon.test',
                'username' => 'demo_lea',
                'display_name' => 'Lea Muenchen',
                'region' => 'Muenchen',
                'bio' => 'Fitness, Essen gehen und Familie im Alltag.',
                'languages' => ['de'],
                'interests' => ['fitness', 'food-going-out', 'family'],
                'role' => UserRole::Member,
            ],
            'admin' => [
                'name' => 'Demo Admin',
                'email' => 'demo.admin@neareon.test',
                'username' => 'demo_admin',
                'display_name' => 'Demo Admin',
                'region' => 'Hamburg',
                'bio' => 'Optionaler Demo-Admin fuer administrative MVP-Pruefungen.',
                'languages' => ['de'],
                'interests' => ['community', 'events'],
                'role' => UserRole::Admin,
            ],
        ];

        $users = [];

        foreach ($definitions as $key => $definition) {
            $user = User::query()->updateOrCreate(
                ['email' => $definition['email']],
                [
                    'name' => $definition['name'],
                    'password' => self::PASSWORD,
                    'birthdate' => now()->subYears(30)->toDateString(),
                    'age_gate_passed_at' => now(),
                    'role' => $definition['role'],
                ],
            );

            $user->forceFill(['email_verified_at' => now()])->save();

            $profile = Profile::query()->updateOrCreate(
                ['user_id' => $user->id],
                [
                    'username' => $definition['username'],
                    'display_name' => $definition['display_name'],
                    'bio' => $definition['bio'],
                    'profile_photo_path' => null,
                    'region' => $definition['region'],
                    'profile_visibility' => ProfileVisibility::Public,
                    'follow_permission' => FollowPermission::Everyone,
                    'contact_permission' => ContactPermission::Everyone,
                    'message_permission' => MessagePermission::ExistingConversations,
                    'online_status_visibility' => OnlineStatusVisibility::MutualContacts,
                    'interests_visibility' => ProfileVisibility::Public,
                    'languages_visibility' => ProfileVisibility::Public,
                    'region_visibility' => ProfileVisibility::Public,
                    'social_counts_visibility' => ProfileVisibility::Public,
                ],
            );

            $languageSync = [];
            foreach ($definition['languages'] as $position => $code) {
                $languageSync[$languages->get($code)->id] = ['position' => $position + 1];
            }

            $profile->languageOptions()->sync($languageSync);
            $profile->interestOptions()->sync(
                collect($definition['interests'])
                    ->map(fn (string $slug): int => $interests->get($slug)->id)
                    ->all(),
            );

            $users[$key] = $user->fresh('profile');
        }

        return $users;
    }

    /**
     * @param  array<string, User>  $users
     */
    private function seedSocialGraph(array $users): void
    {
        $this->follow($users['fodi'], $users['mira']);
        $this->follow($users['mira'], $users['fodi']);
        $this->follow($users['fodi'], $users['jonas']);
        $this->follow($users['lea'], $users['fodi']);

        ContactRequest::query()->updateOrCreate(
            ['sender_id' => $users['jonas']->id, 'receiver_id' => $users['fodi']->id],
            [
                'message' => 'Hi Fodi, ich wuerde mich gern vernetzen.',
                'status' => ContactRequestStatus::Pending,
                'responded_at' => null,
            ],
        );

        ContactRequest::query()->updateOrCreate(
            ['sender_id' => $users['fodi']->id, 'receiver_id' => $users['lea']->id],
            [
                'message' => 'Hi Lea, lass uns NEAREON gemeinsam testen.',
                'status' => ContactRequestStatus::Pending,
                'responded_at' => null,
            ],
        );

        ContactRequest::query()->updateOrCreate(
            ['sender_id' => $users['mira']->id, 'receiver_id' => $users['fodi']->id],
            [
                'message' => 'Danke fuer den Austausch in Hamburg.',
                'status' => ContactRequestStatus::Accepted,
                'responded_at' => now()->subDay(),
            ],
        );
    }

    private function follow(User $follower, User $followed): void
    {
        Follow::query()->updateOrCreate([
            'follower_id' => $follower->id,
            'followed_id' => $followed->id,
        ]);
    }

    /**
     * @param  array<string, User>  $users
     * @param  Collection<string, InterestOption>  $interests
     * @return array<string, Group>
     */
    private function seedGroups(array $users, $interests): array
    {
        $groups = [
            'hamburg' => Group::query()->updateOrCreate(
                ['slug' => 'demo-hamburg-community'],
                [
                    'owner_id' => $users['fodi']->id,
                    'name' => 'Demo Hamburg Community',
                    'description' => 'Oeffentliche Demo-Gruppe fuer lokale Kontakte in Hamburg.',
                    'region' => 'Hamburg',
                    'postal_code' => '20095',
                    'country_code' => 'DE',
                    'category_interest_option_id' => $interests->get('community')->id,
                    'visibility' => Group::VISIBILITY_PUBLIC,
                    'status' => Group::STATUS_ACTIVE,
                ],
            ),
            'berlin' => Group::query()->updateOrCreate(
                ['slug' => 'demo-berlin-kulturtreff'],
                [
                    'owner_id' => $users['jonas']->id,
                    'name' => 'Demo Berlin Kulturtreff',
                    'description' => 'Anfragebasierte Demo-Gruppe fuer Kultur und Community in Berlin.',
                    'region' => 'Berlin',
                    'postal_code' => '10115',
                    'country_code' => 'DE',
                    'category_interest_option_id' => $interests->get('culture')->id,
                    'visibility' => Group::VISIBILITY_REQUEST,
                    'status' => Group::STATUS_ACTIVE,
                ],
            ),
            'private' => Group::query()->updateOrCreate(
                ['slug' => 'demo-private-tech'],
                [
                    'owner_id' => $users['fodi']->id,
                    'name' => 'Demo Private Tech Runde',
                    'description' => 'Private Demo-Gruppe fuer engere Tests und Technikthemen.',
                    'region' => 'Hamburg',
                    'postal_code' => '20357',
                    'country_code' => 'DE',
                    'category_interest_option_id' => $interests->get('technology')->id,
                    'visibility' => Group::VISIBILITY_PRIVATE,
                    'status' => Group::STATUS_ACTIVE,
                ],
            ),
        ];

        $this->groupMember($groups['hamburg'], $users['fodi'], GroupMember::ROLE_OWNER);
        $this->groupMember($groups['hamburg'], $users['mira'], GroupMember::ROLE_MODERATOR);
        $this->groupMember($groups['hamburg'], $users['jonas']);

        $this->groupMember($groups['berlin'], $users['jonas'], GroupMember::ROLE_OWNER);
        $this->groupMember($groups['berlin'], $users['fodi']);
        $this->groupMember($groups['berlin'], $users['mira'], GroupMember::ROLE_MEMBER, GroupMember::STATUS_PENDING);

        $this->groupMember($groups['private'], $users['fodi'], GroupMember::ROLE_OWNER);
        $this->groupMember($groups['private'], $users['lea'], GroupMember::ROLE_MODERATOR);
        $this->groupMember($groups['private'], $users['mira']);

        return $groups;
    }

    private function groupMember(
        Group $group,
        User $user,
        string $role = GroupMember::ROLE_MEMBER,
        string $status = GroupMember::STATUS_ACTIVE,
    ): void {
        GroupMember::query()->updateOrCreate(
            ['group_id' => $group->id, 'user_id' => $user->id],
            [
                'role' => $role,
                'status' => $status,
                'joined_at' => $status === GroupMember::STATUS_ACTIVE ? now()->subDays(2) : null,
            ],
        );
    }

    /**
     * @param  array<string, User>  $users
     * @param  Collection<string, InterestOption>  $interests
     * @return array<string, Event>
     */
    private function seedEvents(array $users, $interests): array
    {
        $events = [
            'public' => $this->event(
                'demo-hamburg-community-abend',
                $users['mira'],
                $interests->get('events'),
                'Demo Hamburg Community-Abend',
                'Oeffentliches Demo-Event fuer neue Kontakte in Hamburg.',
                now()->addDays(7)->setTime(18, 30),
                'Hamburg',
                Event::VISIBILITY_PUBLIC,
            ),
            'request' => $this->event(
                'demo-berlin-kulturabend',
                $users['fodi'],
                $interests->get('culture'),
                'Demo Berlin Kulturabend',
                'Anfragebasiertes Demo-Event mit offenen Teilnahme-Anfragen.',
                now()->addDays(10)->setTime(19, 0),
                'Berlin',
                Event::VISIBILITY_REQUEST,
            ),
            'owned' => $this->event(
                'demo-fodi-tech-cafe',
                $users['fodi'],
                $interests->get('technology'),
                'Demo Fodi Tech-Cafe',
                'Eigenes kommendes Demo-Event fuer das Home-Dashboard.',
                now()->addDays(14)->setTime(17, 0),
                'Hamburg',
                Event::VISIBILITY_PUBLIC,
            ),
            'cancelled' => $this->event(
                'demo-abgesagter-brunch',
                $users['fodi'],
                $interests->get('food-going-out'),
                'Demo Abgesagter Brunch',
                'Abgesagtes Demo-Event fuer Cancel-Zustaende.',
                now()->addDays(21)->setTime(11, 0),
                'Hamburg',
                Event::VISIBILITY_PUBLIC,
                Event::STATUS_CANCELLED,
            ),
            'past' => $this->event(
                'demo-vergangener-spaziergang',
                $users['mira'],
                $interests->get('community'),
                'Demo Vergangener Spaziergang',
                'Vergangenes Demo-Event fuer Listen- und Dashboard-Abgrenzung.',
                now()->subDays(7)->setTime(15, 0),
                'Hamburg',
                Event::VISIBILITY_PUBLIC,
            ),
        ];

        $this->eventAttendee($events['public'], $users['fodi']);
        $this->eventAttendee($events['public'], $users['jonas']);
        $this->eventAttendee($events['request'], $users['mira']);
        $this->eventAttendee($events['request'], $users['jonas'], EventAttendee::STATUS_PENDING);
        $this->eventAttendee($events['owned'], $users['mira']);
        $this->eventAttendee($events['cancelled'], $users['lea']);
        $this->eventAttendee($events['past'], $users['fodi']);

        return $events;
    }

    private function event(
        string $slug,
        User $owner,
        InterestOption $category,
        string $title,
        string $description,
        CarbonInterface $startsAt,
        string $region,
        string $visibility,
        string $status = Event::STATUS_ACTIVE,
    ): Event {
        return Event::query()->updateOrCreate(
            ['slug' => $slug],
            [
                'owner_id' => $owner->id,
                'category_interest_option_id' => $category->id,
                'title' => $title,
                'description' => $description,
                'starts_at' => $startsAt,
                'ends_at' => $startsAt->copy()->addHours(2),
                'region' => $region,
                'postal_code' => $region === 'Berlin' ? '10115' : '20095',
                'country_code' => 'DE',
                'visibility' => $visibility,
                'status' => $status,
                'max_attendees' => 24,
            ],
        );
    }

    private function eventAttendee(
        Event $event,
        User $user,
        string $status = EventAttendee::STATUS_ACTIVE,
    ): void {
        EventAttendee::query()->updateOrCreate(
            ['event_id' => $event->id, 'user_id' => $user->id],
            [
                'status' => $status,
                'joined_at' => $status === EventAttendee::STATUS_ACTIVE ? now()->subDay() : null,
            ],
        );
    }

    /**
     * @param  array<string, User>  $users
     */
    private function seedConversation(array $users): Conversation
    {
        $conversation = Conversation::query()
            ->whereHas('participants', fn ($query) => $query->where('user_id', $users['fodi']->id))
            ->whereHas('participants', fn ($query) => $query->where('user_id', $users['mira']->id))
            ->first() ?? Conversation::query()->create();

        $fodiParticipant = ConversationParticipant::query()->updateOrCreate(
            ['conversation_id' => $conversation->id, 'user_id' => $users['fodi']->id],
            ['joined_at' => now()->subDays(4)],
        );
        $miraParticipant = ConversationParticipant::query()->updateOrCreate(
            ['conversation_id' => $conversation->id, 'user_id' => $users['mira']->id],
            ['joined_at' => now()->subDays(4)],
        );

        $fodiParticipant->forceFill(['last_read_at' => now()->subDays(2)])->save();
        $miraParticipant->forceFill(['last_read_at' => now()->subHours(3)])->save();

        Message::query()->firstOrCreate(
            [
                'conversation_id' => $conversation->id,
                'sender_id' => $users['fodi']->id,
                'body' => 'Hi Mira, hast du Lust auf das Community-Event?',
            ],
        );

        Message::query()->firstOrCreate(
            [
                'conversation_id' => $conversation->id,
                'sender_id' => $users['mira']->id,
                'body' => 'Ja, klingt gut. Ich bringe noch eine Idee fuer Fotos mit.',
            ],
        );

        return $conversation;
    }

    /**
     * @param  array<string, User>  $users
     * @param  array<string, Group>  $groups
     * @param  array<string, Event>  $events
     */
    private function seedNotifications(
        array $users,
        array $groups,
        array $events,
        Conversation $conversation,
    ): void {
        $this->notification(
            '00000000-0000-4000-8000-000000000101',
            $users['fodi'],
            InternalNotificationType::ContactRequestReceived,
            'Neue Kontaktanfrage',
            'Jonas Berlin moechte sich mit dir vernetzen.',
            '/contact-requests',
            $users['jonas']->id,
        );

        $this->notification(
            '00000000-0000-4000-8000-000000000102',
            $users['jonas'],
            InternalNotificationType::GroupJoinRequestReceived,
            'Neue Gruppenanfrage',
            'Mira Hamburg moechte dem Berlin Kulturtreff beitreten.',
            '/my-groups',
            $users['mira']->id,
            null,
            ['group_id' => $groups['berlin']->id],
        );

        $this->notification(
            '00000000-0000-4000-8000-000000000103',
            $users['fodi'],
            InternalNotificationType::EventAttendanceRequestReceived,
            'Neue Event-Anfrage',
            'Jonas Berlin moechte am Berlin Kulturabend teilnehmen.',
            '/my-events',
            $users['jonas']->id,
            null,
            ['event_id' => $events['request']->id],
        );

        $this->notification(
            '00000000-0000-4000-8000-000000000104',
            $users['fodi'],
            InternalNotificationType::NewMessage,
            'Neue Nachricht',
            'Mira Hamburg hat dir geschrieben.',
            "/messages/{$conversation->id}",
            $users['mira']->id,
            $conversation->id,
        );
    }

    /**
     * @param  array<string, mixed>  $extraData
     */
    private function notification(
        string $id,
        User $recipient,
        InternalNotificationType $type,
        string $title,
        string $message,
        string $targetUrl,
        ?int $actorId = null,
        ?int $conversationId = null,
        array $extraData = [],
    ): void {
        DB::table('notifications')->updateOrInsert(
            ['id' => $id],
            [
                'type' => InternalNotification::class,
                'notifiable_type' => User::class,
                'notifiable_id' => $recipient->id,
                'data' => json_encode([
                    'type' => $type->value,
                    'title' => $title,
                    'message' => $message,
                    'target_url' => $targetUrl,
                    'actor_id' => $actorId,
                    'conversation_id' => $conversationId,
                ] + $extraData, JSON_THROW_ON_ERROR),
                'read_at' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );
    }
}
