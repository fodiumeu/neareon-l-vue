<?php

namespace App\Http\Controllers;

use App\Enums\ContactRequestStatus;
use App\Enums\ProfileVisibility;
use App\Models\Follow;
use App\Models\Profile;
use App\Models\User;
use App\Services\DiscoverRankingService;
use App\Services\ProfileVisibilityService;
use App\Support\NextUserRoute;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DiscoverController extends Controller
{
    private const PER_PAGE = 12;

    public function __construct(
        private readonly ProfileVisibilityService $profileVisibility,
        private readonly DiscoverRankingService $ranking,
    ) {}

    /**
     * Show visible, filtered and ranked profiles.
     */
    public function index(Request $request): Response|RedirectResponse
    {
        $viewer = $request->user();
        $search = $request->string('q')->trim()->toString();
        $filters = [
            'region' => $request->string('region')->trim()->toString(),
            'language' => $request->string('language')->trim()->toString(),
            'interest' => $request->string('interest')->trim()->toString(),
        ];

        $viewerProfile = $viewer->profile()
            ->with(['languageOptions:id', 'interestOptions:id'])
            ->first();

        if ($viewerProfile === null) {
            return NextUserRoute::redirect($viewer);
        }

        $viewer->setRelation('profile', $viewerProfile);
        $viewer->loadMissing([
            'sentContactRequests' => fn ($query) => $query
                ->where('status', ContactRequestStatus::Pending->value)
                ->select(['id', 'sender_id', 'receiver_id', 'status']),
            'receivedContactRequests' => fn ($query) => $query
                ->where('status', ContactRequestStatus::Pending->value)
                ->select(['id', 'sender_id', 'receiver_id', 'status']),
            'blockingRelationships:id,blocker_id,blocked_id',
            'blockedByRelationships:id,blocker_id,blocked_id',
        ]);

        $visibleProfiles = $this->visibleProfilesQuery($viewer);
        $filterOptions = $this->filterOptions(
            $visibleProfiles,
            $viewer,
        );

        $query = clone $visibleProfiles;
        $this->applyFilters($query, $filters, $viewer);
        $this->applySearch($query, $search, $viewer);
        $this->addRelationshipState($query, $viewer);
        $this->ranking->applyToQuery(
            $query,
            $viewer,
            $viewerProfile,
            $viewerProfile->languageOptions->modelKeys(),
            $viewerProfile->interestOptions->modelKeys(),
        );

        $profiles = $query
            ->with(['user.profile', 'languageOptions', 'interestOptions'])
            ->orderByDesc('discover_score')
            ->orderByRaw('LOWER(profiles.display_name)')
            ->orderBy('profiles.id')
            ->paginate(self::PER_PAGE)
            ->withQueryString()
            ->through(fn (Profile $profile): array => $this->profileVisibility
                ->visibleProfileData(
                    $profile,
                    $viewer,
                    includeCommonalities: true,
                    isFollowing: (bool) $profile->is_following,
                    isFollowedBy: (bool) $profile->is_followed_by,
                ));

        return Inertia::render('Discover', [
            'backLink' => $this->backLink($request),
            'profiles' => Inertia::scroll($profiles),
            'search' => $search,
            'filters' => $filters,
            'filterOptions' => $filterOptions,
        ]);
    }

    /**
     * @return array{href: string, label: string, source: string|null}
     */
    private function backLink(Request $request): array
    {
        return match ($request->string('from')->toString()) {
            'home' => [
                'href' => route('dashboard', absolute: false),
                'label' => 'Zurück zu Home',
                'source' => 'home',
            ],
            'explore' => [
                'href' => route('explore.index', absolute: false),
                'label' => 'Zurück zu Entdecken',
                'source' => 'explore',
            ],
            default => [
                'href' => route('explore.index', absolute: false),
                'label' => 'Zurück zu Entdecken',
                'source' => null,
            ],
        };
    }

    private function visibleProfilesQuery(User $viewer): Builder
    {
        $query = Profile::query()
            ->where('profiles.user_id', '!=', $viewer->id)
            ->whereDoesntHave(
                'user.blockingRelationships',
                fn ($query) => $query->where('blocked_id', $viewer->id),
            )
            ->whereDoesntHave(
                'user.blockedByRelationships',
                fn ($query) => $query->where('blocker_id', $viewer->id),
            );

        return $query->where(function (Builder $query) use ($viewer): void {
            $query->whereIn('profiles.profile_visibility', [
                ProfileVisibility::Public->value,
                ProfileVisibility::Members->value,
            ])->orWhere(function (Builder $query) use ($viewer): void {
                $query->where(
                    'profiles.profile_visibility',
                    ProfileVisibility::Followers->value,
                );
                $this->whereViewerFollows($query, $viewer);
            })->orWhere(function (Builder $query) use ($viewer): void {
                $query->whereIn('profiles.profile_visibility', [
                    ProfileVisibility::Contacts->value,
                    ProfileVisibility::Mutuals->value,
                ]);
                $this->whereMutualFollow($query, $viewer);
            });
        });
    }

    /**
     * @return array{regions: list<string>, languages: list<string>, interests: list<string>}
     */
    private function filterOptions(Builder $visibleProfiles, User $viewer): array
    {
        $regions = clone $visibleProfiles;
        $this->whereFieldVisible(
            $regions,
            'profiles.region_visibility',
            $viewer,
        );

        $languages = clone $visibleProfiles;
        $this->whereFieldVisible(
            $languages,
            'profiles.languages_visibility',
            $viewer,
        );

        $interests = clone $visibleProfiles;
        $this->whereFieldVisible(
            $interests,
            'profiles.interests_visibility',
            $viewer,
        );

        return [
            'regions' => $regions
                ->whereNotNull('profiles.region')
                ->where('profiles.region', '!=', '')
                ->distinct()
                ->orderBy('profiles.region')
                ->pluck('profiles.region')
                ->all(),
            'languages' => $languages
                ->join(
                    'profile_languages',
                    'profile_languages.profile_id',
                    '=',
                    'profiles.id',
                )
                ->join(
                    'language_options',
                    'language_options.id',
                    '=',
                    'profile_languages.language_option_id',
                )
                ->distinct()
                ->orderBy('language_options.label')
                ->pluck('language_options.label')
                ->all(),
            'interests' => $interests
                ->join(
                    'profile_interests',
                    'profile_interests.profile_id',
                    '=',
                    'profiles.id',
                )
                ->join(
                    'interest_options',
                    'interest_options.id',
                    '=',
                    'profile_interests.interest_option_id',
                )
                ->distinct()
                ->orderBy('interest_options.label')
                ->pluck('interest_options.label')
                ->all(),
        ];
    }

    /**
     * @param  array{region: string, language: string, interest: string}  $filters
     */
    private function applyFilters(
        Builder $query,
        array $filters,
        User $viewer,
    ): void {
        if ($filters['region'] !== '') {
            $query->where(function (Builder $query) use ($filters, $viewer): void {
                $this->whereFieldVisible(
                    $query,
                    'profiles.region_visibility',
                    $viewer,
                );
                $query->whereRaw('LOWER(profiles.region) = ?', [
                    mb_strtolower($filters['region']),
                ]);
            });
        }

        if ($filters['language'] !== '') {
            $query->where(function (Builder $query) use ($filters, $viewer): void {
                $this->whereFieldVisible(
                    $query,
                    'profiles.languages_visibility',
                    $viewer,
                );
                $query->whereHas(
                    'languageOptions',
                    fn (Builder $query) => $query->whereRaw(
                        'LOWER(language_options.label) = ?',
                        [mb_strtolower($filters['language'])],
                    ),
                );
            });
        }

        if ($filters['interest'] !== '') {
            $query->where(function (Builder $query) use ($filters, $viewer): void {
                $this->whereFieldVisible(
                    $query,
                    'profiles.interests_visibility',
                    $viewer,
                );
                $query->whereHas(
                    'interestOptions',
                    fn (Builder $query) => $query->whereRaw(
                        'LOWER(interest_options.label) = ?',
                        [mb_strtolower($filters['interest'])],
                    ),
                );
            });
        }
    }

    private function applySearch(
        Builder $query,
        string $search,
        User $viewer,
    ): void {
        if ($search === '') {
            return;
        }

        $needle = '%'.mb_strtolower($search).'%';

        $query->where(function (Builder $query) use ($needle, $viewer): void {
            $query->whereRaw('LOWER(profiles.display_name) LIKE ?', [$needle])
                ->orWhereRaw('LOWER(profiles.username) LIKE ?', [$needle])
                ->orWhere(function (Builder $query) use ($needle, $viewer): void {
                    $this->whereFieldVisible(
                        $query,
                        'profiles.region_visibility',
                        $viewer,
                    );
                    $query->whereRaw('LOWER(profiles.region) LIKE ?', [$needle]);
                })
                ->orWhere(function (Builder $query) use ($needle, $viewer): void {
                    $this->whereFieldVisible(
                        $query,
                        'profiles.languages_visibility',
                        $viewer,
                    );
                    $query->whereHas(
                        'languageOptions',
                        fn (Builder $query) => $query->whereRaw(
                            'LOWER(language_options.label) LIKE ?',
                            [$needle],
                        ),
                    );
                })
                ->orWhere(function (Builder $query) use ($needle, $viewer): void {
                    $this->whereFieldVisible(
                        $query,
                        'profiles.interests_visibility',
                        $viewer,
                    );
                    $query->whereHas(
                        'interestOptions',
                        fn (Builder $query) => $query->whereRaw(
                            'LOWER(interest_options.label) LIKE ?',
                            [$needle],
                        ),
                    );
                });
        });
    }

    private function addRelationshipState(Builder $query, User $viewer): void
    {
        $query->addSelect([
            'is_following' => Follow::query()
                ->selectRaw('1')
                ->where('follower_id', $viewer->id)
                ->whereColumn('followed_id', 'profiles.user_id')
                ->limit(1),
            'is_followed_by' => Follow::query()
                ->selectRaw('1')
                ->whereColumn('follower_id', 'profiles.user_id')
                ->where('followed_id', $viewer->id)
                ->limit(1),
        ]);
    }

    private function whereFieldVisible(
        Builder $query,
        string $column,
        User $viewer,
    ): void {
        $query->where(function (Builder $query) use ($column, $viewer): void {
            $query->whereIn($column, [
                ProfileVisibility::Public->value,
                ProfileVisibility::Members->value,
            ])->orWhere(function (Builder $query) use ($column, $viewer): void {
                $query->where($column, ProfileVisibility::Followers->value);
                $this->whereViewerFollows($query, $viewer);
            })->orWhere(function (Builder $query) use ($column, $viewer): void {
                $query->whereIn($column, [
                    ProfileVisibility::Contacts->value,
                    ProfileVisibility::Mutuals->value,
                ]);
                $this->whereMutualFollow($query, $viewer);
            });
        });
    }

    private function whereViewerFollows(
        Builder $query,
        User $viewer,
    ): void {
        $query->whereExists(function ($query) use ($viewer): void {
            $query->selectRaw('1')
                ->from('follows')
                ->where('follows.follower_id', $viewer->id)
                ->whereColumn(
                    'follows.followed_id',
                    'profiles.user_id',
                );
        });
    }

    private function whereMutualFollow(
        Builder $query,
        User $viewer,
    ): void {
        $this->whereViewerFollows($query, $viewer);
        $query->whereExists(function ($query) use ($viewer): void {
            $query->selectRaw('1')
                ->from('follows')
                ->whereColumn(
                    'follows.follower_id',
                    'profiles.user_id',
                )
                ->where('follows.followed_id', $viewer->id);
        });
    }
}
