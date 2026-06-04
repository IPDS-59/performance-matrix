<?php

namespace App\Http\Controllers;

use App\Actions\Kinetik\SyncKipActivitiesAction;
use App\Kinetik\Contracts\KipActivitySource;
use App\Kinetik\KipTokenInfo;
use App\Models\Employee;
use App\Models\KipActivity;
use App\Models\KipCredential;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class KipIntegrationController extends Controller
{
    public function index(Request $request): Response
    {
        $credential = KipCredential::current();

        return Inertia::render('Kinetik/KipIntegration', [
            'credential' => $credential ? [
                'account_nip' => $credential->account_nip,
                'account_name' => $credential->account_name,
                'expires_at' => $credential->expires_at?->toIso8601String(),
                'is_expired' => $credential->isExpired(),
                'updated_at' => $credential->updated_at?->toIso8601String(),
                'updated_by' => $credential->updatedBy?->name,
            ] : null,
            'stats' => [
                'employees_with_nip' => Employee::where('is_active', true)->whereNotNull('nip_lama')->count(),
                'employees_total' => Employee::where('is_active', true)->count(),
                'activities_synced' => KipActivity::count(),
                'last_fetched_at' => KipActivity::max('fetched_at'),
            ],
        ]);
    }

    public function storeToken(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'token' => ['required', 'string', 'max:8192'],
        ]);

        $token = KipTokenInfo::normalize($validated['token']);
        $info = KipTokenInfo::fromToken($token);

        KipCredential::create([
            'token' => $token,
            'account_nip' => $info->nip,
            'account_name' => $info->email,
            'expires_at' => $info->expiresAt,
            'updated_by' => $request->user()->id,
        ]);

        $when = $info->expiresAt ? ' Berlaku hingga '.$info->expiresAt->translatedFormat('d M Y H:i').'.' : '';

        return back()->with('success', 'Token kipApp berhasil disimpan.'.$when);
    }

    public function syncAll(
        Request $request,
        KipActivitySource $source,
        SyncKipActivitiesAction $action,
    ): RedirectResponse {
        if (KipCredential::current() === null && empty(config('kinetik.kip.token'))) {
            return back()->with('error', 'Belum ada token kipApp. Simpan token terlebih dahulu.');
        }

        $employees = Employee::where('is_active', true)
            ->whereNotNull('nip_lama')
            ->get();

        try {
            $upserted = $action->execute($source, $employees);
        } catch (Throwable $e) {
            return back()->with('error', 'Sinkronisasi gagal: '.$e->getMessage());
        }

        return back()->with('success', "Sinkronisasi selesai. {$upserted} kegiatan diperbarui untuk {$employees->count()} pegawai.");
    }
}
