<?php

namespace Thevps\Vault\Tests;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Thevps\Vault\Events\CredentialAccessGranted;
use Thevps\Vault\Events\CredentialGroupAccessGranted;
use Thevps\Vault\Models\Credential;
use Thevps\Vault\Models\CredentialGroup;
use Thevps\Vault\Models\WifiNetwork;
use Thevps\Vault\Support\FaviconFetcher;

class VaultFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_group_lifecycle_and_union_access(): void
    {
        Event::fake([CredentialGroupAccessGranted::class]);
        Http::fake();

        $owner = TestUser::create(['name' => 'Owner']);
        $viewer = TestUser::create(['name' => 'Viewer']);
        $this->actingAs($owner);

        $this->post(route('password-groups.store'), ['name' => 'Hosting'])->assertRedirect();
        $groupA = CredentialGroup::firstOrFail();
        $this->assertSame(CredentialGroup::ACCESS_MANAGE, $groupA->accessLevelFor($owner));

        $this->post(route('password-groups.store'), ['name' => 'Domains'])->assertRedirect();
        $groupB = CredentialGroup::where('name', 'Domains')->firstOrFail();

        $this->post(route('passwords.store'), [
            'visibility' => 'group',
            'name' => 'cPanel',
            'password' => 's3cret',
            'group_ids' => [$groupA->id, $groupB->id],
        ])->assertRedirect();
        $credential = Credential::firstOrFail();

        // Stranger with no membership — 403.
        $this->actingAs($viewer)->get(route('passwords.show', $credential))->assertForbidden();

        // view in A, edit in B => union is 'edit'.
        $this->actingAs($owner);
        $this->post(route('password-groups.members.store', $groupA), ['user_id' => $viewer->id, 'access_level' => 'view'])->assertRedirect();
        $this->post(route('password-groups.members.store', $groupB), ['user_id' => $viewer->id, 'access_level' => 'edit'])->assertRedirect();
        Event::assertDispatchedTimes(CredentialGroupAccessGranted::class, 2);

        $credential->load('groups');
        $this->assertSame('edit', $credential->accessLevelFor($viewer));
        $this->assertTrue($credential->canEdit($viewer));
        $this->assertFalse($credential->canManage($viewer));

        // Viewer (edit) can open + edit fields...
        $this->actingAs($viewer)->get(route('passwords.show', $credential))->assertOk();
        $this->put(route('passwords.update', $credential), ['visibility' => 'group', 'name' => 'cPanel edited'])->assertRedirect();
        $this->assertSame('cPanel edited', $credential->refresh()->name);

        // ...but not change the group set (needs 'manage').
        $this->put(route('passwords.update', $credential), [
            'visibility' => 'group',
            'name' => 'cPanel edited',
            'group_ids' => [$groupA->id],
        ])->assertForbidden();
        $this->assertEqualsCanonicalizing(
            [$groupA->id, $groupB->id],
            $credential->groups()->pluck('credential_groups.id')->all(),
        );
    }

    public function test_direct_access_can_be_granted_at_creation_time(): void
    {
        Event::fake([CredentialAccessGranted::class]);
        Http::fake();

        $owner = TestUser::create(['name' => 'Owner']);
        $grantee = TestUser::create(['name' => 'Grantee']);
        $this->actingAs($owner);

        $this->post(route('passwords.store'), [
            'visibility' => 'public',
            'name' => 'Created with direct access',
            'password' => 'x',
            'direct_access' => [
                ['user_id' => $grantee->id, 'access_level' => 'edit'],
                ['user_id' => $grantee->id, 'access_level' => 'edit'], // duplicate — must not 500
                ['user_id' => null, 'access_level' => 'view'], // blank row from the UI — ignored
            ],
        ])->assertRedirect();

        $credential = Credential::firstOrFail();
        Event::assertDispatchedTimes(CredentialAccessGranted::class, 1);
        $this->assertSame('edit', $credential->accessLevelFor($grantee));
        $this->assertCount(1, $credential->directUsers()->wherePivot('user_id', $grantee->id)->get());
    }

    public function test_direct_user_access_grants_view_without_any_group(): void
    {
        Event::fake([CredentialAccessGranted::class]);
        Http::fake();

        $owner = TestUser::create(['name' => 'Owner']);
        $stranger = TestUser::create(['name' => 'Stranger']);
        $this->actingAs($owner);

        $this->post(route('passwords.store'), [
            'visibility' => 'public',
            'name' => 'Direct share',
            'password' => 'x',
        ])->assertRedirect();
        $credential = Credential::firstOrFail();

        // Not a group member, and this credential is public anyway — but exercise the direct
        // grant on a 'group' visibility credential with zero groups to prove it alone is enough.
        $this->post(route('password-groups.store'), ['name' => 'unused'])->assertRedirect();
        $this->post(route('passwords.store'), [
            'visibility' => 'group',
            'name' => 'Solo secret',
            'password' => 'y',
            'group_ids' => [CredentialGroup::where('name', 'unused')->value('id')],
        ])->assertRedirect();
        $solo = Credential::where('name', 'Solo secret')->firstOrFail();

        // Stranger has no access at all yet.
        $this->assertNull($solo->accessLevelFor($stranger));

        $this->post(route('passwords.access.store', $solo), ['user_id' => $stranger->id, 'access_level' => 'edit'])->assertRedirect();
        Event::assertDispatchedTimes(CredentialAccessGranted::class, 1);

        $solo->load('directUsers');
        $this->assertSame('edit', $solo->accessLevelFor($stranger));
        $this->assertTrue($solo->canEdit($stranger));
        $this->assertFalse($solo->canManage($stranger));

        $this->actingAs($stranger)->get(route('passwords.show', $solo))->assertOk();

        // Owner can change the level and revoke it again.
        $this->actingAs($owner);
        $this->put(route('passwords.access.update', [$solo, $stranger]), ['access_level' => 'view'])->assertRedirect();
        $this->assertSame('view', $solo->refresh()->load('directUsers')->accessLevelFor($stranger));

        $this->delete(route('passwords.access.destroy', [$solo, $stranger]))->assertRedirect();
        $this->assertNull($solo->refresh()->load('directUsers')->accessLevelFor($stranger));

        // A non-manager (edit-level, via direct grant) cannot grant access to others.
        $this->post(route('passwords.access.store', $solo), ['user_id' => $stranger->id, 'access_level' => 'view'])->assertRedirect();
        $viewer = TestUser::create(['name' => 'Viewer']);
        $this->actingAs($stranger)->post(route('passwords.access.store', $solo), ['user_id' => $viewer->id, 'access_level' => 'view'])
            ->assertForbidden();
    }

    public function test_deleting_a_group_is_allowed_when_the_credential_keeps_direct_access(): void
    {
        Http::fake();

        $owner = TestUser::create(['name' => 'Owner']);
        $grantee = TestUser::create(['name' => 'Grantee']);
        $this->actingAs($owner);

        $this->post(route('password-groups.store'), ['name' => 'Only'])->assertRedirect();
        $group = CredentialGroup::firstOrFail();
        $this->post(route('passwords.store'), ['visibility' => 'group', 'name' => 'x', 'group_ids' => [$group->id]])->assertRedirect();
        $credential = Credential::firstOrFail();

        $this->post(route('passwords.access.store', $credential), ['user_id' => $grantee->id, 'access_level' => 'view'])->assertRedirect();

        // Even though this is the credential's only group, it isn't orphaned — Grantee still
        // sees it via direct access — so deletion is NOT blocked.
        $this->delete(route('password-groups.destroy', $group))->assertRedirect();
        $this->assertDatabaseCount('credential_groups', 0);
        $this->assertSame('view', $credential->refresh()->load('directUsers')->accessLevelFor($grantee));
    }

    public function test_public_credential_is_visible_to_everyone_but_editable_only_by_creator_or_group(): void
    {
        Http::fake();

        $owner = TestUser::create(['name' => 'Owner']);
        $stranger = TestUser::create(['name' => 'Stranger']);
        $this->actingAs($owner);

        $this->post(route('passwords.store'), [
            'visibility' => 'public',
            'name' => 'Shared FTP',
            'password' => 'pw',
        ])->assertRedirect();
        $credential = Credential::firstOrFail();
        $this->assertSame([], $credential->groups()->pluck('credential_groups.id')->all());

        // Everyone authenticated sees it and may view.
        $this->actingAs($stranger)->get(route('passwords.index'))->assertOk();
        $this->get(route('passwords.show', $credential))->assertOk();
        $this->assertSame('view', $credential->accessLevelFor($stranger));

        // Stranger cannot edit; the creator can (and manage).
        $this->put(route('passwords.update', $credential), ['visibility' => 'public', 'name' => 'hijacked'])->assertForbidden();
        $this->assertSame('manage', $credential->accessLevelFor($owner));
        $this->actingAs($owner)->put(route('passwords.update', $credential), ['visibility' => 'public', 'name' => 'renamed'])->assertRedirect();
        $this->assertSame('renamed', $credential->refresh()->name);
    }

    /**
     * "Copy password" straight from the list (host's passwords/Index.vue row menu) — same ACL
     * as show(), reachable without a full page navigation.
     */
    public function test_reveal_returns_the_password_for_anyone_with_view_access(): void
    {
        Event::fake([CredentialGroupAccessGranted::class]);

        $owner = TestUser::create(['name' => 'Owner']);
        $viewer = TestUser::create(['name' => 'Viewer']);
        $stranger = TestUser::create(['name' => 'Stranger']);
        $this->actingAs($owner);

        $this->post(route('password-groups.store'), ['name' => 'Hosting'])->assertRedirect();
        $group = CredentialGroup::firstOrFail();
        $this->post(route('passwords.store'), [
            'visibility' => 'group',
            'name' => 'cPanel',
            'password' => 's3cret',
            'group_ids' => [$group->id],
        ])->assertRedirect();
        $credential = Credential::firstOrFail();
        $this->post(route('password-groups.members.store', $group), ['user_id' => $viewer->id, 'access_level' => 'view'])->assertRedirect();

        $this->actingAs($viewer)->getJson(route('passwords.reveal', $credential))
            ->assertOk()
            ->assertJson(['password' => 's3cret']);

        $this->actingAs($stranger)->getJson(route('passwords.reveal', $credential))->assertForbidden();
    }

    public function test_wifi_public_quick_list_bands_and_windows_profile(): void
    {
        $owner = TestUser::create(['name' => 'Owner']);
        $other = TestUser::create(['name' => 'Other']);
        $this->actingAs($owner);

        $this->post(route('wifi.store'), [
            'visibility' => 'public',
            'ssid' => 'Office-5G',
            'password' => 'joinme',
            'security' => 'WPA',
            'is_hidden' => false,
            'bands' => ['2.4', '5'],
            'location' => 'Reception',
        ])->assertRedirect();

        $network = WifiNetwork::firstOrFail();
        $this->assertSame(['2.4', '5'], $network->bands);
        $this->assertSame('5', $network->maxBand());

        // Visible to any authenticated user via the quick endpoint.
        $response = $this->actingAs($other)->getJson(route('wifi.quick'));
        $response->assertOk()->assertJsonPath('networks.0.ssid', 'Office-5G')->assertJsonPath('networks.0.max_band', '5');

        $profile = $this->get(route('wifi.windows-profile', $network));
        $profile->assertOk();
        $this->assertStringContainsString('attachment; filename="office_5g.xml"', $profile->headers->get('Content-Disposition'));
        $this->assertStringContainsString('<name>Office-5G</name>', $profile->getContent());
    }

    public function test_group_wifi_is_hidden_from_non_members(): void
    {
        $owner = TestUser::create(['name' => 'Owner']);
        $stranger = TestUser::create(['name' => 'Stranger']);
        $this->actingAs($owner);

        $this->post(route('password-groups.store'), ['name' => 'IT'])->assertRedirect();
        $group = CredentialGroup::firstOrFail();

        $this->post(route('wifi.store'), [
            'visibility' => 'group',
            'ssid' => 'Server-Room',
            'password' => 'x',
            'security' => 'WPA',
            'is_hidden' => true,
            'group_ids' => [$group->id],
        ])->assertRedirect();

        $this->getJson(route('wifi.quick'))->assertOk()->assertJsonCount(1, 'networks');
        $this->actingAs($stranger)->getJson(route('wifi.quick'))->assertOk()->assertJsonCount(0, 'networks');
    }

    public function test_favicon_fetcher_refuses_private_and_loopback_hosts(): void
    {
        Http::fake();

        $this->assertNull(FaviconFetcher::fetch('http://127.0.0.1/admin'));
        $this->assertNull(FaviconFetcher::fetch('http://localhost/'));
        $this->assertNull(FaviconFetcher::fetch('http://10.0.0.5/'));

        Http::assertNothingSent();
    }

    public function test_deleting_a_group_that_would_orphan_a_credential_is_blocked(): void
    {
        Http::fake();

        $owner = TestUser::create(['name' => 'Owner']);
        $this->actingAs($owner);

        $this->post(route('password-groups.store'), ['name' => 'Only'])->assertRedirect();
        $group = CredentialGroup::firstOrFail();
        $this->post(route('passwords.store'), ['visibility' => 'group', 'name' => 'x', 'group_ids' => [$group->id]])->assertRedirect();

        $this->delete(route('password-groups.destroy', $group))->assertRedirect();
        $this->assertDatabaseCount('credential_groups', 1);
    }

    public function test_institution_resolver_scopes_and_stamps(): void
    {
        Http::fake();

        $owner = TestUser::create(['name' => 'Owner']);
        $this->actingAs($owner);

        config(['vault.institution_resolver' => fn () => 7]);
        $this->post(route('password-groups.store'), ['name' => 'Tenant 7'])->assertRedirect();
        $group = CredentialGroup::withoutGlobalScopes()->firstOrFail();
        $this->assertSame(7, (int) $group->institution_id);

        config(['vault.institution_resolver' => fn () => 9]);
        $this->assertSame(0, CredentialGroup::count());

        config(['vault.institution_resolver' => null]);
        $this->assertSame(1, CredentialGroup::count());
    }
}
