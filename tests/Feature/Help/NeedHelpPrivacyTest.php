<?php

use App\Models\District;
use App\Models\NeedHelpRequest;
use App\Models\User;

beforeEach(function () {
    $this->withoutVite();
});

function createNeedHelpPrivacyRequest(User $owner, District $district, array $attributes = []): NeedHelpRequest
{
    return NeedHelpRequest::create(array_merge([
        'user_id' => $owner->id,
        'district_id' => $district->id,
        'latitude' => -8.1116,
        'longitude' => -79.0287,
        'address_reference' => 'Dirección particular confidencial',
        'help_type' => 'medical',
        'description' => 'Descripción privada con datos personales.',
        'urgency_level' => 'high',
        'status' => 'pending',
        'is_active' => true,
    ], $attributes));
}

test('authenticated users can see community help requests without private details', function () {
    $district = District::factory()->create();
    $owner = User::factory()->create();
    $viewer = User::factory()->create();
    $helpRequest = createNeedHelpPrivacyRequest($owner, $district);

    $this->actingAs($viewer)
        ->get(route('need-help.index'))
        ->assertOk()
        ->assertDontSee('Descripción privada con datos personales.')
        ->assertDontSee('Dirección particular confidencial')
        ->assertInertia(fn ($page) => $page
            ->component('help/Index')
            ->where('requests.0', [
                'district_name' => $district->name,
                'help_type' => 'medical',
                'urgency_level' => 'high',
                'status_label' => 'Pendiente',
                'created_at' => $helpRequest->created_at?->format('Y-m-d H:i'),
            ])
            ->where('myRequests', [])
            ->missing('requests.0.id')
            ->missing('requests.0.status')
            ->missing('requests.0.user_name')
            ->missing('requests.0.description')
            ->missing('requests.0.address_reference')
            ->missing('requests.0.latitude')
            ->missing('requests.0.longitude'));
});

test('owners can view their own private help request details', function () {
    $district = District::factory()->create();
    $owner = User::factory()->create();
    $helpRequest = createNeedHelpPrivacyRequest($owner, $district);

    $this->actingAs($owner)
        ->get(route('need-help.index'))
        ->assertInertia(fn ($page) => $page
            ->component('help/Index')
            ->where('myRequests.0.id', $helpRequest->id));

    $this->actingAs($owner)
        ->get(route('need-help.show', $helpRequest))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('help/Show')
            ->where('helpRequest.id', $helpRequest->id)
            ->where('helpRequest.district_name', $district->name)
            ->where('helpRequest.description', 'Descripción privada con datos personales.')
            ->where('helpRequest.address_reference', 'Dirección particular confidencial')
            ->where('helpRequest.latitude', -8.1116)
            ->where('helpRequest.longitude', -79.0287));
});

test('regular users cannot view another owners private help request details', function () {
    $district = District::factory()->create();
    $owner = User::factory()->create();
    $otherUser = User::factory()->create();
    $helpRequest = createNeedHelpPrivacyRequest($owner, $district);

    $this->actingAs($otherUser)
        ->get(route('need-help.show', $helpRequest))
        ->assertForbidden();
});

test('guests are denied and unverified owners can access help pages', function () {
    $district = District::factory()->create();
    $owner = User::factory()->create();
    $helpRequest = createNeedHelpPrivacyRequest($owner, $district);

    $this->get(route('need-help.index'))
        ->assertRedirect(route('login'));
    $this->get(route('need-help.show', $helpRequest))
        ->assertRedirect(route('login'));

    $unverifiedUser = User::factory()->unverified()->create();
    $unverifiedRequest = createNeedHelpPrivacyRequest($unverifiedUser, $district);

    $this->actingAs($unverifiedUser)
        ->get(route('need-help.index'))
        ->assertOk();
    $this->actingAs($unverifiedUser)
        ->get(route('need-help.show', $unverifiedRequest))
        ->assertOk();
    $this->actingAs($unverifiedUser)
        ->get(route('need-help.show', $helpRequest))
        ->assertForbidden();
});

test('admins retain access to detailed help requests in moderation', function () {
    $district = District::factory()->create();
    $owner = User::factory()->create();
    $admin = User::factory()->create(['role' => 'admin']);
    $helpRequest = createNeedHelpPrivacyRequest($owner, $district);

    $this->actingAs($admin)
        ->get(route('admin.moderation'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/Moderation')
            ->where('helpRequests.0.id', $helpRequest->id)
            ->where('helpRequests.0.description', 'Descripción privada con datos personales.')
            ->where('helpRequests.0.user_name', $owner->name));

    $this->actingAs($admin)
        ->get(route('need-help.show', $helpRequest))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('help/Show')
            ->where('helpRequest.address_reference', 'Dirección particular confidencial'));
});

test('regular users cannot access the administrative help moderation page', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('admin.moderation'))
        ->assertForbidden();
});
