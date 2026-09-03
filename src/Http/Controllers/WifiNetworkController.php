<?php

namespace Thevps\Vault\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Thevps\Vault\Models\CredentialGroup;
use Thevps\Vault\Models\WifiNetwork;
use Thevps\Vault\Vault;

/**
 * Wi-Fi directory. `index()` and `quick()` are open to every authenticated user for `public`
 * networks; `group`-visibility networks appear only to members of an attached group. Who may
 * create/edit/delete a `public` network is decided by config('vault.wifi_manage_gate');
 * `group` networks are managed by their group's 'manage' members.
 */
class WifiNetworkController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $myGroupIds = $this->myGroupIds($user);

        $networks = WifiNetwork::query()
            ->with('groups:id,name')
            ->where(fn ($q) => $q->where('visibility', 'public')
                ->orWhereHas('groups', fn ($g) => $g->whereIn('credential_groups.id', $myGroupIds)))
            ->orderBy('ssid')
            ->get();

        return Inertia::render(Vault::page('wifi/Index'), [
            'networks' => $networks->map(fn (WifiNetwork $network) => $this->present($network, $user))->values(),
            'canCreatePublic' => Vault::userCanManagePublicWifi($user),
            'manageableGroups' => $this->manageableGroups($user),
        ]);
    }

    /** Compact JSON list for the header quick-access widget (AppWifiQuick.vue). */
    public function quick(Request $request): JsonResponse
    {
        $user = $request->user();
        $myGroupIds = $this->myGroupIds($user);

        $networks = WifiNetwork::query()
            ->where(fn ($q) => $q->where('visibility', 'public')
                ->orWhereHas('groups', fn ($g) => $g->whereIn('credential_groups.id', $myGroupIds)))
            ->orderBy('ssid')
            ->get();

        return response()->json([
            'networks' => $networks->map(fn (WifiNetwork $network) => [
                'id' => $network->id,
                'ssid' => $network->ssid,
                'password' => $network->password,
                'security' => $network->security,
                'is_hidden' => $network->is_hidden,
                'location' => $network->location,
                'bands' => $network->bands ?? [],
                'max_band' => $network->maxBand(),
            ])->values(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $user = $request->user();
        $groupIds = $data['group_ids'] ?? [];

        if ($data['visibility'] === 'public') {
            abort_unless(Vault::userCanManagePublicWifi($user), 403);
        } else {
            abort_if($groupIds === [], 422, 'Оберіть хоча б одну групу для мережі з видимістю «Група».');
            foreach ($groupIds as $groupId) {
                $group = CredentialGroup::findOrFail($groupId);
                abort_unless($group->accessLevelFor($user) === CredentialGroup::ACCESS_MANAGE, 403);
            }
        }

        $network = WifiNetwork::create([
            ...collect($data)->except('group_ids')->all(),
            'created_by_id' => $user->getKey(),
        ]);
        if ($groupIds) {
            $network->groups()->attach($groupIds);
        }

        return back()->with('success', 'Мережу додано.');
    }

    public function update(Request $request, WifiNetwork $wifi_network): RedirectResponse
    {
        $this->authorizeManage($request->user(), $wifi_network);

        $data = $this->validated($request);
        $user = $request->user();
        $groupIds = $data['group_ids'] ?? [];

        if ($data['visibility'] !== 'public') {
            abort_if($groupIds === [], 422, 'Оберіть хоча б одну групу для мережі з видимістю «Група».');
        }

        $wifi_network->update(collect($data)->except('group_ids')->all());

        if (array_key_exists('group_ids', $data)) {
            foreach ($groupIds as $groupId) {
                $group = CredentialGroup::findOrFail($groupId);
                abort_unless($group->accessLevelFor($user) === CredentialGroup::ACCESS_MANAGE, 403);
            }
            $wifi_network->groups()->sync($groupIds);
        }

        return back()->with('success', 'Мережу збережено.');
    }

    public function destroy(Request $request, WifiNetwork $wifi_network): RedirectResponse
    {
        $this->authorizeManage($request->user(), $wifi_network);

        $wifi_network->delete();

        return back()->with('success', 'Мережу видалено.');
    }

    /**
     * A WLAN profile in the format `netsh wlan add profile` accepts — lets Windows import the
     * network with one file instead of typing the SSID/password.
     */
    public function windowsProfile(Request $request, WifiNetwork $wifi_network): HttpResponse
    {
        abort_unless($this->canView($request->user(), $wifi_network), 403);

        $filename = (Str::slug($wifi_network->ssid, '_') ?: 'wifi').'.xml';

        return response($this->buildWindowsProfileXml($wifi_network), 200, [
            'Content-Type' => 'application/xml',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'visibility' => ['required', Rule::in(['group', 'public'])],
            'ssid' => ['required', 'string', 'max:255'],
            'password' => ['nullable', 'string', 'max:255'],
            'security' => ['required', Rule::in([WifiNetwork::SECURITY_WPA, WifiNetwork::SECURITY_WEP, WifiNetwork::SECURITY_NONE])],
            'is_hidden' => ['required', 'boolean'],
            'bands' => ['nullable', 'array'],
            'bands.*' => [Rule::in(WifiNetwork::BANDS)],
            'location' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'group_ids' => ['sometimes', 'array'],
            'group_ids.*' => [Rule::exists('credential_groups', 'id')],
        ]);

        // No-password network — don't store anything even if the form left a stale value.
        if ($data['security'] === WifiNetwork::SECURITY_NONE) {
            $data['password'] = null;
        }

        $data['bands'] = array_values(array_unique($data['bands'] ?? []));

        return $data;
    }

    private function authorizeManage($user, WifiNetwork $network): void
    {
        if ($network->visibility === 'public') {
            abort_unless(Vault::userCanManagePublicWifi($user), 403);

            return;
        }

        abort_unless($network->canManage($user), 403);
    }

    private function canView($user, WifiNetwork $network): bool
    {
        return $network->visibility === 'public' || $network->canView($user);
    }

    private function myGroupIds($user): array
    {
        return DB::table('credential_group_members')
            ->where('user_id', $user->getKey())
            ->pluck('credential_group_id')
            ->all();
    }

    private function manageableGroups($user)
    {
        return CredentialGroup::query()
            ->whereHas('members', fn ($q) => $q->where('credential_group_members.user_id', $user->getKey())
                ->where('credential_group_members.access_level', CredentialGroup::ACCESS_MANAGE))
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    private function present(WifiNetwork $network, $user): array
    {
        return [
            'id' => $network->id,
            'ssid' => $network->ssid,
            'password' => $network->password,
            'security' => $network->security,
            'is_hidden' => $network->is_hidden,
            'bands' => $network->bands ?? [],
            'max_band' => $network->maxBand(),
            'location' => $network->location,
            'notes' => $network->notes,
            'visibility' => $network->visibility,
            'groups' => $network->groups->map(fn ($g) => ['id' => $g->id, 'name' => $g->name])->values(),
            'can_manage' => $network->visibility === 'public'
                ? Vault::userCanManagePublicWifi($user)
                : $network->canManage($user),
        ];
    }

    private function buildWindowsProfileXml(WifiNetwork $network): string
    {
        $xmlEscape = fn (?string $value) => htmlspecialchars($value ?? '', ENT_QUOTES | ENT_XML1, 'UTF-8');

        $name = $xmlEscape($network->ssid);
        $hex = strtoupper(bin2hex($network->ssid));
        $hasPassword = filled($network->password) && $network->security !== WifiNetwork::SECURITY_NONE;

        $security = $hasPassword
            ? "<authentication>WPA2PSK</authentication>\n                <encryption>AES</encryption>\n                <useOneX>false</useOneX>"
            : "<authentication>open</authentication>\n                <encryption>none</encryption>\n                <useOneX>false</useOneX>";

        $sharedKey = $hasPassword
            ? "<sharedKey>\n                <keyType>passPhrase</keyType>\n                <protected>false</protected>\n                <keyMaterial>{$xmlEscape($network->password)}</keyMaterial>\n            </sharedKey>"
            : '';

        return <<<XML
        <?xml version="1.0"?>
        <WLANProfile xmlns="http://www.microsoft.com/networking/WLAN/profile/v1">
            <name>{$name}</name>
            <SSIDConfig>
                <SSID>
                    <hex>{$hex}</hex>
                    <name>{$name}</name>
                </SSID>
            </SSIDConfig>
            <connectionType>ESS</connectionType>
            <connectionMode>auto</connectionMode>
            <MSM>
                <security>
                    <authEncryption>
                        {$security}
                    </authEncryption>
                    {$sharedKey}
                </security>
            </MSM>
        </WLANProfile>
        XML;
    }
}
