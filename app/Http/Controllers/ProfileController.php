<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Illuminate\View\View;

class ProfileController extends Controller
{
    /**
     * Display the user's profile form.
     */
    public function edit(Request $request): View
    {
        $user = $request->user()->load([
            'roleTypes.role',
            'roleTypes.type',
            'unitKerja.direktorat',
            'komite',
        ]);

        $activeRoleTypes = $user->roleTypes
            ->filter(fn ($roleType) => $roleType->pivot?->status === 'active')
            ->values();

        $roleLabels = $activeRoleTypes
            ->map(function ($roleType): string {
                $roleName = $roleType->role?->name ?? $roleType->name;

                if ($roleName === 'super_admin' || $roleType->name === 'super_admin') {
                    return 'Super Admin';
                }

                $roleLabel = $roleType->role?->display_name
                    ?: ucwords(str_replace('_', ' ', $roleName));
                $typeLabel = $roleType->type?->name;

                return trim($roleLabel . ' ' . $typeLabel);
            })
            ->unique()
            ->values();

        $activeUnits = $user->unitKerja
            ->filter(fn ($unit) => $unit->pivot?->status === 'active')
            ->values();

        $unitLabels = $activeUnits
            ->map(fn ($unit): string => trim(($unit->kode_unit ?? '-') . ' - ' . ($unit->nama_unit ?? '-')))
            ->unique()
            ->values();

        $direktoratLabels = $activeUnits
            ->map(fn ($unit): ?string => $unit->direktorat
                ? trim(($unit->direktorat->kode_direktorat ?? '-') . ' - ' . ($unit->direktorat->nama_direktorat ?? '-'))
                : null)
            ->filter()
            ->unique()
            ->values();

        $komiteLabels = $user->komite
            ->filter(fn ($komite) => $komite->pivot?->status === 'active')
            ->map(fn ($komite): string => trim(($komite->kode_komite ?? '-') . ' - ' . ($komite->nama_komite ?? '-')))
            ->unique()
            ->values();

        return view('profile.edit', [
            'user' => $user,
            'roleLabels' => $roleLabels,
            'direktoratLabels' => $direktoratLabels,
            'unitLabels' => $unitLabels,
            'komiteLabels' => $komiteLabels,
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $request->user()->update([
            'name' => $request->validated('name'),
        ]);

        return Redirect::route('profile.edit')->with('status', 'profile-updated');
    }

    /**
     * Delete the user's account.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $request->validateWithBag('userDeletion', [
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        Auth::logout();

        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Redirect::to('/');
    }
}
