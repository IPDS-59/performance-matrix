<?php

namespace App\Http\Controllers;

use App\Kinetik\FeatureFlags;
use App\Models\FeatureFlag;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** "Fitur": an admin switches the kipApp feature flags without editing .env. */
class FeatureFlagController extends Controller
{
    public function index(): Response
    {
        $saved = FeatureFlag::with('changer:id,name')->get()->keyBy('key');

        return Inertia::render('Admin/Features', [
            'flags' => collect(FeatureFlags::definitions())->map(fn ($d, $key) => [
                'key' => $key,
                ...$d,
                'enabled' => (bool) config("kinetik.kip.{$key}"),
                'overridden' => $saved->has($key),
                'changed_by' => $saved->get($key)?->changer?->name,
                'changed_at' => $saved->get($key)?->updated_at?->toIso8601String(),
            ])->values(),
        ]);
    }

    public function update(Request $request, string $key): RedirectResponse
    {
        $v = $request->validate(['enabled' => 'required|boolean']);
        FeatureFlags::set($key, $v['enabled'], $request->user()->id);

        return back()->with('success', FeatureFlags::definitions()[$key]['label'].($v['enabled'] ? ' dinyalakan.' : ' dimatikan.'));
    }

    public function reset(string $key): RedirectResponse
    {
        FeatureFlags::reset($key);

        return back()->with('success', 'Kembali mengikuti pengaturan server.');
    }
}
