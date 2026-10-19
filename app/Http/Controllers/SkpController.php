<?php

namespace App\Http\Controllers;

use App\Kinetik\Contracts\SkpFlow;
use App\Kinetik\Exceptions\KipApiException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

/** "SKP Saya": the signed-in member's own SKP in kipApp. Nobody else's SKP is shown or changed here. */
class SkpController extends Controller
{
    public function __construct(private readonly SkpFlow $flow) {}

    public function index(Request $request): Response
    {
        abort_if($request->user()->employee === null, 403, 'Akun tidak terhubung ke data pegawai.');

        try {
            $overview = $this->flow->overview($request->user());
            $error = null;
        } catch (RuntimeException|KipApiException $e) {
            $overview = ['year' => (int) config('kinetik.kip.tahun'), 'yearly' => null, 'quarters' => []];
            $error = $e->getMessage();
        }

        return Inertia::render('Skp/Index', ['overview' => $overview, 'loadError' => $error]);
    }

    public function createQuarter(Request $request, int $quarter): RedirectResponse
    {
        abort_unless(in_array($quarter, [1, 2, 3, 4], true), 404);
        $data = $request->validate(['rk_ids' => ['required', 'array', 'min:1'], 'rk_ids.*' => ['string']]);

        return $this->run($request, fn () => $this->flow->createQuarter($request->user(), $quarter, $data['rk_ids']), "SKP triwulan {$quarter} dibuat di kipApp.");
    }

    public function addRks(Request $request, int $quarter): RedirectResponse
    {
        abort_unless(in_array($quarter, [1, 2, 3, 4], true), 404);
        $data = $request->validate(['rk_ids' => ['required', 'array', 'min:1'], 'rk_ids.*' => ['string']]);

        return $this->run($request, fn () => $this->flow->addRks($request->user(), $quarter, $data['rk_ids']), 'RK ditambahkan ke SKP triwulan.');
    }

    public function submit(Request $request): RedirectResponse
    {
        $data = $this->target($request);

        return $this->run($request, fn () => $this->flow->submit($request->user(), $data['kind'], $data['quarter'] ?? null),
            $data['kind'] === 'yearly' ? 'SKP Tahunan dikirim ke atasan untuk ditetapkan.' : 'SKP triwulan dikirim ke atasan untuk dinilai.');
    }

    public function cancel(Request $request): RedirectResponse
    {
        $data = $this->target($request);

        return $this->run($request, fn () => $this->flow->cancel($request->user(), $data['kind'], $data['quarter'] ?? null), 'Pengiriman SKP dibatalkan. SKP kembali ke Sedang dibuat.');
    }

    public function addIki(Request $request): RedirectResponse
    {
        abort_unless(config('kinetik.kip.iki_write'), 404);
        $data = $request->validate([
            'kind' => ['required', 'in:yearly,quarter'],
            'quarter' => ['required_if:kind,quarter', 'nullable', 'integer', 'between:1,4'],
            'rk_id' => ['required', 'string'],
            'text' => ['required', 'string', 'min:10', 'max:1000'],
        ]);

        return $this->run($request, fn () => $this->flow->addIki($request->user(), $data['kind'], $data['quarter'] ?? null, $data['rk_id'], $data['text']), 'IKI ditambahkan.');
    }

    public function deleteIki(Request $request): RedirectResponse
    {
        abort_unless(config('kinetik.kip.iki_write'), 404);
        $data = $request->validate([
            'kind' => ['required', 'in:yearly,quarter'],
            'quarter' => ['required_if:kind,quarter', 'nullable', 'integer', 'between:1,4'],
            'iki_id' => ['required', 'string'],
        ]);

        return $this->run($request, fn () => $this->flow->deleteIki($request->user(), $data['kind'], $data['quarter'] ?? null, $data['iki_id']), 'IKI dihapus.');
    }

    /** @return array{kind: string, quarter?: int} */
    private function target(Request $request): array
    {
        return $request->validate([
            'kind' => ['required', 'in:yearly,quarter'],
            'quarter' => ['required_if:kind,quarter', 'nullable', 'integer', 'between:1,4'],
        ]);
    }

    private function run(Request $request, callable $do, string $success): RedirectResponse
    {
        abort_if($request->user()->employee === null, 403);

        try {
            $do();
        } catch (RuntimeException|KipApiException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', $success);
    }
}
