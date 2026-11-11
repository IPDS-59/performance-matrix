<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Kinetik\BuildDuplicateKegiatanAction;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** The admin reviews pairs of double kegiatan and asks each person to act; the admin deletes nothing here. */
class DuplicateKegiatanController extends Controller
{
    public function index(BuildDuplicateKegiatanAction $pairs): Response
    {
        return Inertia::render('Admin/DuplicateKegiatan', ['pairs' => $pairs->execute()]);
    }

    public function ask(Request $request, BuildDuplicateKegiatanAction $pairs): RedirectResponse
    {
        $keys = $request->validate(['keys' => ['required', 'array', 'min:1'], 'keys.*' => ['string', 'max:30']], ['keys.required' => 'Pilih dulu kegiatan yang mau diminta ditindaklanjuti.'])['keys'];
        $people = $pairs->ask($keys, $request->user()->name);

        return back()->with($people > 0 ? 'success' : 'error', $people > 0 ? "Permintaan dikirim ke {$people} orang lewat lonceng." : 'Tidak ada pegawai dengan akun yang bisa dikirimi permintaan.');
    }

    /** "Bukan ganda": the admin checked the pairs and they are two different pieces of work. */
    public function dismiss(Request $request, BuildDuplicateKegiatanAction $pairs): RedirectResponse
    {
        $keys = $request->validate(['keys' => ['required', 'array', 'min:1'], 'keys.*' => ['string', 'max:30']], ['keys.required' => 'Pilih dulu pasangan yang bukan ganda.'])['keys'];
        $marked = $pairs->dismiss($keys, $request->user()->id);

        return back()->with('success', "{$marked} pasangan ditandai bukan ganda.");
    }
}
