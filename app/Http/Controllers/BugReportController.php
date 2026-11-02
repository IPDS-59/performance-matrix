<?php

namespace App\Http\Controllers;

use App\Models\BugReport;
use App\Models\User;
use App\Notifications\KinetikNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/** Anyone signed in reports a problem; admins read the list, set a status and leave a note. */
class BugReportController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Admin/BugReports', [
            'reports' => BugReport::with('user:id,name,email')->latest()->limit(200)->get()->map(fn (BugReport $r) => [
                'id' => $r->id,
                'title' => $r->title,
                'description' => $r->description,
                'page_url' => $r->page_url,
                'status' => $r->status,
                'admin_note' => $r->admin_note,
                'reporter' => $r->user?->name ?? '-',
                'reporter_email' => $r->user?->email,
                'created_at' => $r->created_at?->toIso8601String(),
            ]),
            'statuses' => BugReport::STATUSES,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'min:5', 'max:150'],
            'description' => ['required', 'string', 'min:10', 'max:3000'],
            'page_url' => ['nullable', 'string', 'max:500'],
        ]);

        $report = BugReport::create([...$data, 'user_id' => $request->user()->id, 'user_agent' => Str::limit((string) $request->userAgent(), 300, '')]);

        User::role('admin')->get()->each(fn (User $admin) => $admin->notify(new KinetikNotification(
            'bug_report',
            ($request->user()->name).' melaporkan masalah: '.Str::limit($report->title, 100),
            route('admin.bug-reports'),
        )));

        return back()->with('success', 'Laporan terkirim ke admin. Terima kasih.');
    }

    public function update(Request $request, BugReport $report): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', 'in:'.implode(',', array_keys(BugReport::STATUSES))],
            'admin_note' => ['nullable', 'string', 'max:2000'],
        ]);
        $report->update($data);

        return back()->with('success', 'Laporan diperbarui.');
    }
}
