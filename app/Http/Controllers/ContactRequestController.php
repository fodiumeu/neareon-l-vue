<?php

namespace App\Http\Controllers;

use App\Enums\ContactRequestStatus;
use App\Http\Requests\StoreContactRequestRequest;
use App\Models\ContactRequest;
use App\Models\User;
use App\Services\ConversationService;
use App\Services\InternalNotificationService;
use App\Services\PrivacyService;
use App\Services\ProfileVisibilityService;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class ContactRequestController extends Controller
{
    public function __construct(
        private readonly ConversationService $conversations,
        private readonly PrivacyService $privacy,
        private readonly InternalNotificationService $notifications,
        private readonly ProfileVisibilityService $profileVisibility,
    ) {}

    /**
     * Show the authenticated user's pending received contact requests.
     */
    public function index(Request $request): Response
    {
        $viewer = $request->user();
        $viewer->loadMissing([
            'profile.languageOptions',
            'profile.interestOptions',
        ]);

        $contactRequests = $viewer
            ->receivedContactRequests()
            ->where('status', ContactRequestStatus::Pending->value)
            ->with([
                'sender.profile.languageOptions',
                'sender.profile.interestOptions',
            ])
            ->latest()
            ->get();
        [$followingIds, $followedByIds] = $this->relationshipIds(
            $viewer,
            $contactRequests->pluck('sender_id'),
        );

        $contactRequests = $contactRequests
            ->map(function (ContactRequest $contactRequest) use (
                $viewer,
                $followingIds,
                $followedByIds,
            ): array {
                $sender = $contactRequest->sender;
                $sender->profile?->setRelation('user', $sender);
                $visibleProfile = $sender->profile === null
                    ? []
                    : $this->profileVisibility->visibleProfileData(
                        $sender->profile,
                        $viewer,
                        includeCommonalities: true,
                        isFollowing: $followingIds->contains($sender->id),
                        isFollowedBy: $followedByIds->contains($sender->id),
                        commonLanguagesLimit: PHP_INT_MAX,
                        commonInterestsLimit: PHP_INT_MAX,
                    );

                return [
                    'id' => $contactRequest->id,
                    'message' => $contactRequest->message,
                    'created_at' => $contactRequest->created_at->toIso8601String(),
                    'common_languages' => $visibleProfile['common_languages'] ?? [],
                    'common_interests' => $visibleProfile['common_interests'] ?? [],
                    'sender' => [
                        'display_name' => $sender->profile?->display_name
                            ?? $sender->name,
                        'username' => $sender->profile?->username,
                        'profile_photo_url' => $sender->profile?->profilePhotoUrl(),
                    ],
                ];
            });

        return Inertia::render('ContactRequests/Index', [
            'backLink' => $this->backLink($request),
            'contactRequests' => $contactRequests,
        ]);
    }

    /**
     * @return array{href: string, label: string}
     */
    private function backLink(Request $request): array
    {
        return $request->string('from')->toString() === 'home'
            ? [
                'href' => route('dashboard', absolute: false),
                'label' => 'Zurück zu Home',
            ]
            : [
                'href' => route('community.index', absolute: false),
                'label' => 'Zurück zur Community',
            ];
    }

    /**
     * Show the authenticated user's sent contact requests.
     */
    public function sent(Request $request): Response
    {
        $viewer = $request->user();
        $viewer->loadMissing([
            'profile.languageOptions',
            'profile.interestOptions',
        ]);

        $contactRequests = $viewer
            ->sentContactRequests()
            ->select([
                'id',
                'sender_id',
                'receiver_id',
                'message',
                'status',
                'created_at',
            ])
            ->with([
                'receiver:id,name',
                'receiver.profile.languageOptions',
                'receiver.profile.interestOptions',
            ])
            ->latest()
            ->get();
        [$followingIds, $followedByIds] = $this->relationshipIds(
            $viewer,
            $contactRequests->pluck('receiver_id'),
        );

        $contactRequests = $contactRequests
            ->map(function (ContactRequest $contactRequest) use (
                $viewer,
                $followingIds,
                $followedByIds,
            ): array {
                $receiver = $contactRequest->receiver;
                $receiver->profile?->setRelation('user', $receiver);
                $visibleProfile = $receiver->profile === null
                    ? []
                    : $this->profileVisibility->visibleProfileData(
                        $receiver->profile,
                        $viewer,
                        includeCommonalities: true,
                        isFollowing: $followingIds->contains($receiver->id),
                        isFollowedBy: $followedByIds->contains($receiver->id),
                        commonLanguagesLimit: PHP_INT_MAX,
                        commonInterestsLimit: PHP_INT_MAX,
                    );

                return [
                    'id' => $contactRequest->id,
                    'message' => $contactRequest->message,
                    'status' => $contactRequest->status->value,
                    'created_at' => $contactRequest->created_at->toIso8601String(),
                    'common_languages' => $visibleProfile['common_languages'] ?? [],
                    'common_interests' => $visibleProfile['common_interests'] ?? [],
                    'receiver' => [
                        'display_name' => $receiver->profile?->display_name
                            ?? $receiver->name,
                        'username' => $receiver->profile?->username,
                        'profile_photo_url' => $receiver->profile?->profilePhotoUrl(),
                    ],
                ];
            });

        return Inertia::render('ContactRequests/Sent', [
            'contactRequests' => $contactRequests,
        ]);
    }

    /**
     * Store a new contact request.
     */
    public function store(StoreContactRequestRequest $request): RedirectResponse
    {
        $sender = $request->user();
        $receiver = User::query()->findOrFail($request->integer('receiver_id'));

        if ($sender->is($receiver)) {
            return back()->with('error', 'Du kannst dir nicht selbst eine Kontaktanfrage senden.');
        }

        abort_if($sender->hasBlockWith($receiver), HttpResponse::HTTP_FORBIDDEN);
        abort_unless(
            $this->privacy->canSendContactRequest($sender, $receiver),
            HttpResponse::HTTP_FORBIDDEN,
        );

        if ($sender->isMutualWith($receiver)) {
            return back()->with('error', 'Ihr folgt euch bereits gegenseitig.');
        }

        try {
            $result = DB::transaction(function () use ($request, $sender, $receiver): string {
                User::query()
                    ->whereKey([$sender->id, $receiver->id])
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get();

                $requestsBetweenUsers = ContactRequest::query()
                    ->betweenUsers($sender, $receiver)
                    ->lockForUpdate()
                    ->get();

                $pendingRequest = $requestsBetweenUsers->first(
                    fn (ContactRequest $contactRequest): bool => $contactRequest->status === ContactRequestStatus::Pending,
                );

                if ($pendingRequest !== null) {
                    return $pendingRequest->sender_id === $sender->id
                        ? 'already_sent'
                        : 'already_received';
                }

                $existingRequest = $requestsBetweenUsers->first(
                    fn (ContactRequest $contactRequest): bool => $contactRequest->sender_id === $sender->id
                        && $contactRequest->receiver_id === $receiver->id,
                ) ?? $requestsBetweenUsers->first();

                $attributes = [
                    'sender_id' => $sender->id,
                    'receiver_id' => $receiver->id,
                    'message' => $request->validated('message'),
                    'status' => ContactRequestStatus::Pending,
                    'responded_at' => null,
                ];

                if ($existingRequest !== null) {
                    $existingRequest->update($attributes);

                    return 'reactivated';
                }

                ContactRequest::query()->create($attributes);

                return 'created';
            });
        } catch (QueryException $exception) {
            if (! $this->isContactRequestPairUniqueViolation($exception)) {
                throw $exception;
            }

            $pendingRequest = ContactRequest::query()
                ->betweenUsers($sender, $receiver)
                ->where('status', ContactRequestStatus::Pending->value)
                ->first();

            if ($pendingRequest === null) {
                throw $exception;
            }

            $result = $pendingRequest->sender_id === $sender->id
                ? 'already_sent'
                : 'already_received';
        }

        if ($result === 'already_sent') {
            return back()->with('error', 'Du hast diesem Benutzer bereits eine Kontaktanfrage gesendet.');
        }

        if ($result === 'already_received') {
            return back()->with('error', 'Dieser Benutzer hat dir bereits eine Kontaktanfrage gesendet.');
        }

        $this->notifications->contactRequestReceived($sender, $receiver);

        return back()->with('success', 'Kontaktanfrage gesendet.');
    }

    /**
     * Accept a pending received contact request.
     */
    public function accept(Request $request, ContactRequest $contactRequest): RedirectResponse
    {
        $this->respondTo($request, $contactRequest, ContactRequestStatus::Accepted);

        return back()->with('success', 'Kontaktanfrage angenommen.');
    }

    /**
     * Decline a pending received contact request.
     */
    public function decline(Request $request, ContactRequest $contactRequest): RedirectResponse
    {
        $this->respondTo($request, $contactRequest, ContactRequestStatus::Declined);

        return back()->with('success', 'Kontaktanfrage abgelehnt.');
    }

    private function respondTo(
        Request $request,
        ContactRequest $contactRequest,
        ContactRequestStatus $status,
    ): void {
        DB::transaction(function () use ($request, $contactRequest, $status): void {
            $lockedContactRequest = ContactRequest::query()
                ->lockForUpdate()
                ->findOrFail($contactRequest->id);

            abort_unless(
                $lockedContactRequest->receiver_id === $request->user()->id,
                HttpResponse::HTTP_FORBIDDEN,
            );

            abort_if(
                $lockedContactRequest->sender
                    ->hasBlockWith($lockedContactRequest->receiver),
                HttpResponse::HTTP_FORBIDDEN,
            );

            abort_unless(
                $lockedContactRequest->status === ContactRequestStatus::Pending,
                HttpResponse::HTTP_CONFLICT,
            );

            $lockedContactRequest->update([
                'status' => $status,
                'responded_at' => now(),
            ]);

            if ($status === ContactRequestStatus::Accepted) {
                $senderFollow = $lockedContactRequest->sender
                    ->followingRelationships()
                    ->firstOrCreate([
                        'followed_id' => $lockedContactRequest->receiver_id,
                    ]);

                $receiverFollow = $lockedContactRequest->receiver
                    ->followingRelationships()
                    ->firstOrCreate([
                        'followed_id' => $lockedContactRequest->sender_id,
                    ]);

                if ($senderFollow->wasRecentlyCreated) {
                    $this->notifications->newFollower(
                        $lockedContactRequest->sender,
                        $lockedContactRequest->receiver,
                    );
                }

                if ($receiverFollow->wasRecentlyCreated) {
                    $this->notifications->newFollower(
                        $lockedContactRequest->receiver,
                        $lockedContactRequest->sender,
                    );
                }

                $this->conversations->getOrCreateDirectConversation(
                    $lockedContactRequest->sender,
                    $lockedContactRequest->receiver,
                );
            }

            $this->notifications->contactRequestResponded(
                $lockedContactRequest,
                $status,
            );
        });
    }

    private function isContactRequestPairUniqueViolation(
        QueryException $exception,
    ): bool {
        $message = strtolower($exception->getMessage());

        return str_contains(
            $message,
            'contact_requests.sender_id, contact_requests.receiver_id',
        ) || str_contains(
            $message,
            'contact_requests_sender_id_receiver_id_unique',
        );
    }

    /**
     * @param  Collection<int, int>  $userIds
     * @return array{Collection<int, int>, Collection<int, int>}
     */
    private function relationshipIds(User $viewer, Collection $userIds): array
    {
        $userIds = $userIds->unique()->values();

        if ($userIds->isEmpty()) {
            return [collect(), collect()];
        }

        return [
            $viewer->followingRelationships()
                ->whereIn('followed_id', $userIds)
                ->pluck('followed_id'),
            $viewer->followerRelationships()
                ->whereIn('follower_id', $userIds)
                ->pluck('follower_id'),
        ];
    }
}
