<?php

use App\Models\Group;
use App\Models\GroupMember;
use App\Models\InterestOption;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('guests cannot open the groups index', function () {
    $this->get(route('groups.index'))
        ->assertRedirect(route('login'));
});

test('groups index shows public and request groups but hides unrelated private groups', function () {
    $viewer = User::factory()->create();
    createOnboardedProfile($viewer);

    $publicGroup = Group::factory()->create([
        'name' => 'Public Community',
        'slug' => 'public-community',
        'region' => 'Hamburg',
        'postal_code' => '20095',
        'country_code' => 'DE',
        'visibility' => Group::VISIBILITY_PUBLIC,
    ]);
    $category = InterestOption::query()->create([
        'slug' => 'group-index-category',
        'label' => 'Kochen',
        'is_active' => true,
    ]);
    $publicGroup->forceFill([
        'category_interest_option_id' => $category->id,
    ])->save();
    $requestGroup = Group::factory()->create([
        'name' => 'Request Community',
        'slug' => 'request-community',
        'visibility' => Group::VISIBILITY_REQUEST,
    ]);
    Group::factory()->create([
        'name' => 'Private Community',
        'slug' => 'private-community',
        'visibility' => Group::VISIBILITY_PRIVATE,
    ]);

    $this->actingAs($viewer)
        ->get(route('groups.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Groups/Index')
            ->has('groups.data', 2)
            ->where('groups.data.0.name', $publicGroup->name)
            ->where('groups.data.0.region', 'Hamburg')
            ->where('groups.data.0.postal_code', '20095')
            ->where('groups.data.0.country_code', 'DE')
            ->where('groups.data.0.visibility_label', 'Öffentlich')
            ->where('groups.data.0.category.label', 'Kochen')
            ->where('groups.data.0.can_join', true)
            ->where('groups.data.0.join_label', 'Gruppe beitreten')
            ->where('groups.data.0.url', route('groups.show', [
                'group' => $publicGroup->slug,
                'from' => 'groups',
            ]))
            ->where('groups.data.1.name', $requestGroup->name)
            ->where('groups.data.1.category', null)
            ->where('groups.data.1.can_join', true)
            ->where('groups.data.1.join_label', 'Beitrittsanfrage senden')
            ->where('groups.data.1.url', route('groups.show', [
                'group' => $requestGroup->slug,
                'from' => 'groups',
            ]))
            ->where('groups.data.1.visibility_label', 'Anfrage'),
        );
});

test('groups index exposes pending and active viewer membership status', function () {
    $viewer = User::factory()->create();
    createOnboardedProfile($viewer);
    $activeGroup = Group::factory()->create([
        'name' => 'Active Public Group',
        'slug' => 'active-public-group',
        'visibility' => Group::VISIBILITY_PUBLIC,
        'created_at' => now()->subMinute(),
    ]);
    $pendingGroup = Group::factory()->create([
        'name' => 'Pending Request Group',
        'slug' => 'pending-request-group',
        'visibility' => Group::VISIBILITY_REQUEST,
        'created_at' => now(),
    ]);
    GroupMember::factory()
        ->for($activeGroup)
        ->for($viewer)
        ->create([
            'status' => GroupMember::STATUS_ACTIVE,
        ]);
    GroupMember::factory()
        ->for($pendingGroup)
        ->for($viewer)
        ->create([
            'status' => GroupMember::STATUS_PENDING,
            'joined_at' => null,
        ]);

    $this->actingAs($viewer)
        ->get(route('groups.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Groups/Index')
            ->has('groups.data', 2)
            ->where('groups.data.0.name', 'Pending Request Group')
            ->where('groups.data.0.can_join', false)
            ->where('groups.data.0.viewer_membership_status', GroupMember::STATUS_PENDING)
            ->where('groups.data.0.membership.status_label', 'Anfrage ausstehend')
            ->where('groups.data.1.name', 'Active Public Group')
            ->where('groups.data.1.can_join', false)
            ->where('groups.data.1.viewer_membership_status', GroupMember::STATUS_ACTIVE)
            ->where('groups.data.1.membership.role_label', 'Mitglied'),
        );
});

test('groups discover index hides private groups even when the viewer is an active member', function () {
    $viewer = User::factory()->create();
    createOnboardedProfile($viewer);
    $group = Group::factory()->create([
        'name' => 'Member Private Group',
        'slug' => 'member-private-group',
        'visibility' => Group::VISIBILITY_PRIVATE,
    ]);
    GroupMember::factory()
        ->for($group)
        ->for($viewer)
        ->create([
            'role' => GroupMember::ROLE_MEMBER,
            'status' => GroupMember::STATUS_ACTIVE,
        ]);

    $this->actingAs($viewer)
        ->get(route('groups.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Groups/Index')
            ->has('groups.data', 0),
        );
});

test('groups index paginates visible groups', function () {
    $viewer = User::factory()->create();
    createOnboardedProfile($viewer);

    Group::factory()->count(13)->create([
        'visibility' => Group::VISIBILITY_PUBLIC,
    ]);

    $this->actingAs($viewer)
        ->get(route('groups.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Groups/Index')
            ->has('groups.data', 12)
            ->where('groups.last_page', 2),
        );
});

test('groups index searches visible groups by name description region postal code and category', function () {
    $viewer = User::factory()->create();
    createOnboardedProfile($viewer);
    $category = InterestOption::query()->create([
        'slug' => 'urban-gardening',
        'label' => 'Urban Gardening',
        'sort_order' => 1,
        'is_active' => true,
    ]);

    $nameGroup = Group::factory()->create([
        'name' => 'Nordic Makers',
        'description' => 'Treffen für kreative Projekte.',
        'region' => 'Kiel',
        'postal_code' => '24103',
        'visibility' => Group::VISIBILITY_PUBLIC,
    ]);
    $descriptionGroup = Group::factory()->create([
        'name' => 'Creative Circle',
        'description' => 'Gemeinsam nachhaltige Nachbarschaften gestalten.',
        'region' => 'Bremen',
        'postal_code' => '28195',
        'visibility' => Group::VISIBILITY_PUBLIC,
    ]);
    $regionGroup = Group::factory()->create([
        'name' => 'Local Boardgames',
        'description' => 'Spieleabend in der Community.',
        'region' => 'Hamburg',
        'postal_code' => '20095',
        'visibility' => Group::VISIBILITY_REQUEST,
    ]);
    $postalCodeGroup = Group::factory()->create([
        'name' => 'Morning Runners',
        'description' => 'Laufen im Park.',
        'region' => 'Berlin',
        'postal_code' => '10145',
        'visibility' => Group::VISIBILITY_PUBLIC,
    ]);
    $categoryGroup = Group::factory()->create([
        'name' => 'Garden Friends',
        'description' => 'Pflanzen tauschen und Wissen teilen.',
        'region' => 'Hannover',
        'postal_code' => '30159',
        'visibility' => Group::VISIBILITY_PUBLIC,
        'category_interest_option_id' => $category->id,
    ]);
    Group::factory()->create([
        'name' => 'Secret Filter Match',
        'description' => 'Hamburg 10145 Urban Gardening',
        'region' => 'Secret Region',
        'postal_code' => '99999',
        'visibility' => Group::VISIBILITY_PRIVATE,
        'category_interest_option_id' => $category->id,
    ]);

    foreach ([
        ['query' => 'Nordic', 'expectedName' => $nameGroup->name],
        ['query' => 'nachhaltige', 'expectedName' => $descriptionGroup->name],
        ['query' => 'Hamburg', 'expectedName' => $regionGroup->name],
        ['query' => '10145', 'expectedName' => $postalCodeGroup->name],
        ['query' => 'Urban Gardening', 'expectedName' => $categoryGroup->name],
    ] as $searchCase) {
        $this->actingAs($viewer)
            ->get(route('groups.index', ['q' => $searchCase['query']]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Groups/Index')
                ->where('filters.q', $searchCase['query'])
                ->has('groups.data', 1)
                ->where('groups.data.0.name', $searchCase['expectedName']),
            );
    }

    $this->actingAs($viewer)
        ->get(route('groups.index', ['q' => 'Secret Filter Match']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Groups/Index')
            ->has('groups.data', 0),
        );
});

test('groups index filters by region without leaking private only region options', function () {
    $viewer = User::factory()->create();
    createOnboardedProfile($viewer);

    $hamburgPublic = Group::factory()->create([
        'name' => 'Hamburg Public',
        'region' => 'Hamburg',
        'visibility' => Group::VISIBILITY_PUBLIC,
    ]);
    $hamburgRequest = Group::factory()->create([
        'name' => 'Hamburg Request',
        'region' => 'Hamburg',
        'visibility' => Group::VISIBILITY_REQUEST,
        'created_at' => now()->subMinute(),
    ]);
    Group::factory()->create([
        'name' => 'Berlin Public',
        'region' => 'Berlin',
        'visibility' => Group::VISIBILITY_PUBLIC,
    ]);
    Group::factory()->create([
        'name' => 'Private Region Group',
        'region' => 'Atlantis',
        'visibility' => Group::VISIBILITY_PRIVATE,
    ]);

    $this->actingAs($viewer)
        ->get(route('groups.index', ['region' => 'Hamburg']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Groups/Index')
            ->where('filters.region', 'Hamburg')
            ->has('groups.data', 2)
            ->where('groups.data.0.name', $hamburgPublic->name)
            ->where('groups.data.1.name', $hamburgRequest->name)
            ->where('filterOptions.regions.0', 'Berlin')
            ->where('filterOptions.regions.1', 'Hamburg')
            ->missing('filterOptions.regions.2'),
        );
});

test('groups index filters by active visible category and hides inactive or private only category options', function () {
    $viewer = User::factory()->create();
    createOnboardedProfile($viewer);
    $sport = InterestOption::query()->create([
        'slug' => 'sport',
        'label' => 'Sport',
        'sort_order' => 1,
        'is_active' => true,
    ]);
    $art = InterestOption::query()->create([
        'slug' => 'kunst',
        'label' => 'Kunst',
        'sort_order' => 2,
        'is_active' => true,
    ]);
    $inactive = InterestOption::query()->create([
        'slug' => 'inaktiv',
        'label' => 'Inaktiv',
        'sort_order' => 3,
        'is_active' => false,
    ]);
    $privateOnly = InterestOption::query()->create([
        'slug' => 'privat',
        'label' => 'Privat',
        'sort_order' => 4,
        'is_active' => true,
    ]);

    $sportGroup = Group::factory()->create([
        'name' => 'Sport Group',
        'visibility' => Group::VISIBILITY_PUBLIC,
        'category_interest_option_id' => $sport->id,
    ]);
    Group::factory()->create([
        'name' => 'Art Group',
        'visibility' => Group::VISIBILITY_REQUEST,
        'category_interest_option_id' => $art->id,
    ]);
    Group::factory()->create([
        'name' => 'Inactive Category Group',
        'visibility' => Group::VISIBILITY_PUBLIC,
        'category_interest_option_id' => $inactive->id,
    ]);
    Group::factory()->create([
        'name' => 'Private Category Group',
        'visibility' => Group::VISIBILITY_PRIVATE,
        'category_interest_option_id' => $privateOnly->id,
    ]);
    Group::factory()->create([
        'name' => 'No Category Group',
        'visibility' => Group::VISIBILITY_PUBLIC,
    ]);

    $this->actingAs($viewer)
        ->get(route('groups.index', ['category' => 'sport']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Groups/Index')
            ->where('filters.category', 'sport')
            ->has('groups.data', 1)
            ->where('groups.data.0.name', $sportGroup->name)
            ->where('filterOptions.categories.0.slug', 'sport')
            ->where('filterOptions.categories.1.slug', 'kunst')
            ->missing('filterOptions.categories.2'),
        );
});

test('groups index filters by visibility and ignores private visibility safely', function () {
    $viewer = User::factory()->create();
    createOnboardedProfile($viewer);

    $publicGroup = Group::factory()->create([
        'name' => 'Public Group',
        'visibility' => Group::VISIBILITY_PUBLIC,
    ]);
    $requestGroup = Group::factory()->create([
        'name' => 'Request Group',
        'visibility' => Group::VISIBILITY_REQUEST,
        'created_at' => now()->subMinute(),
    ]);
    Group::factory()->create([
        'name' => 'Private Group',
        'visibility' => Group::VISIBILITY_PRIVATE,
    ]);

    $this->actingAs($viewer)
        ->get(route('groups.index', ['visibility' => Group::VISIBILITY_PUBLIC]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Groups/Index')
            ->where('filters.visibility', Group::VISIBILITY_PUBLIC)
            ->has('groups.data', 1)
            ->where('groups.data.0.name', $publicGroup->name),
        );

    $this->actingAs($viewer)
        ->get(route('groups.index', ['visibility' => Group::VISIBILITY_REQUEST]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Groups/Index')
            ->where('filters.visibility', Group::VISIBILITY_REQUEST)
            ->has('groups.data', 1)
            ->where('groups.data.0.name', $requestGroup->name),
        );

    $this->actingAs($viewer)
        ->get(route('groups.index', ['visibility' => Group::VISIBILITY_PRIVATE]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Groups/Index')
            ->where('filters.visibility', '')
            ->has('groups.data', 2),
        );
});

test('groups index combines search region category and visibility filters', function () {
    $viewer = User::factory()->create();
    createOnboardedProfile($viewer);
    $category = InterestOption::query()->create([
        'slug' => 'familie',
        'label' => 'Familie',
        'sort_order' => 1,
        'is_active' => true,
    ]);
    $otherCategory = InterestOption::query()->create([
        'slug' => 'musik',
        'label' => 'Musik',
        'sort_order' => 2,
        'is_active' => true,
    ]);

    $matching = Group::factory()->create([
        'name' => 'Yoga Familie Hamburg',
        'description' => 'Ruhige Treffen am Wochenende.',
        'region' => 'Hamburg',
        'visibility' => Group::VISIBILITY_PUBLIC,
        'category_interest_option_id' => $category->id,
    ]);
    Group::factory()->create([
        'name' => 'Yoga Familie Berlin',
        'region' => 'Berlin',
        'visibility' => Group::VISIBILITY_PUBLIC,
        'category_interest_option_id' => $category->id,
    ]);
    Group::factory()->create([
        'name' => 'Yoga Familie Hamburg Anfrage',
        'region' => 'Hamburg',
        'visibility' => Group::VISIBILITY_REQUEST,
        'category_interest_option_id' => $category->id,
    ]);
    Group::factory()->create([
        'name' => 'Yoga Musik Hamburg',
        'region' => 'Hamburg',
        'visibility' => Group::VISIBILITY_PUBLIC,
        'category_interest_option_id' => $otherCategory->id,
    ]);

    $this->actingAs($viewer)
        ->get(route('groups.index', [
            'q' => 'Yoga',
            'region' => 'Hamburg',
            'category' => 'familie',
            'visibility' => Group::VISIBILITY_PUBLIC,
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Groups/Index')
            ->has('groups.data', 1)
            ->where('groups.data.0.name', $matching->name),
        );
});

test('groups index page includes empty state card and read only group actions', function () {
    $page = file_get_contents(resource_path('js/pages/Groups/Index.vue'));

    expect($page)
        ->toContain('Gruppen entdecken')
        ->toContain('← Zurück zu Entdecken')
        ->toContain('href="/explore"')
        ->toContain('Entdecke öffentliche und offene Gruppen')
        ->toContain('class="group-filter-controls"')
        ->toContain('Gruppen durchsuchen')
        ->toContain('Name, Beschreibung, Region oder PLZ')
        ->toContain('@input="handleSearchInput"')
        ->toContain('setTimeout(runSearch, 350)')
        ->toContain("reset: ['groups']")
        ->toContain('Alle Regionen')
        ->toContain('Alle Kategorien')
        ->toContain('Alle sichtbaren Gruppen')
        ->toContain('Filter zurücksetzen')
        ->toContain('Keine passenden Gruppen gefunden')
        ->toContain('Alle Gruppen anzeigen')
        ->toContain('Noch keine Gruppen zum Entdecken sichtbar.')
        ->toContain('group.postal_code')
        ->toContain('PLZ {{ group.postal_code }}')
        ->toContain('group.category')
        ->toContain('group.category.label')
        ->toContain('Gruppe ansehen')
        ->toContain('Anfrage gesendet')
        ->toContain('group.can_join')
        ->toContain('group.join_label')
        ->toContain('group.join_url')
        ->toContain('name="return_to"')
        ->toContain('value="groups"')
        ->toContain('visibility_label')
        ->toContain('member_count')
        ->not->toContain('Gruppe erstellen')
        ->not->toContain('Gruppe bearbeiten')
        ->not->toContain('Gruppe verlassen');
});
