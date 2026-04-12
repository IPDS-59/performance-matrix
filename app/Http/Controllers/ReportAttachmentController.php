<?php

namespace App\Http\Controllers;

use App\Models\PerformanceReport;
use App\Models\ReportAttachment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportAttachmentController extends Controller
{
    public function store(Request $request, PerformanceReport $report): RedirectResponse
    {
        $employee = $request->user()->employee;
        abort_if(! $employee, 403, 'Akun belum terhubung ke data pegawai.');

        $isReporter = $report->reported_by === $employee->id;
        $isLead = $report->workItem->project->leader_id === $employee->id;
        abort_if(! $isReporter && ! $isLead, 403);

        $type = $request->input('type', 'link');

        if ($type === 'file') {
            $validated = $request->validate([
                'file' => ['required', 'file', 'max:10240', 'mimes:pdf,jpg,jpeg,png,webp'],
                'title' => ['nullable', 'string', 'max:255'],
            ]);

            $uploaded = $request->file('file');
            $path = $uploaded->store("attachments/{$report->period_year}/{$employee->id}", 'local');

            $report->attachments()->create([
                'type' => 'file',
                'file_path' => $path,
                'file_name' => $uploaded->getClientOriginalName(),
                'mime_type' => $uploaded->getMimeType(),
                'title' => $validated['title'] ?? null,
            ]);
        } else {
            $validated = $request->validate([
                'url' => ['required', 'url', 'max:2048'],
                'title' => ['nullable', 'string', 'max:255'],
            ]);

            $report->attachments()->create([
                'type' => 'link',
                'url' => $validated['url'],
                'title' => $validated['title'] ?? null,
            ]);
        }

        return back()->with('success', 'Bukti dukung berhasil ditambahkan.');
    }

    public function download(ReportAttachment $attachment): StreamedResponse
    {
        abort_if($attachment->type !== 'file' || ! $attachment->file_path, 404);

        $disk = Storage::disk('local');
        abort_if(! $disk->exists($attachment->file_path), 404);

        $disposition = $attachment->isImage() ? 'inline' : 'attachment';

        return $disk->response(
            $attachment->file_path,
            $attachment->file_name ?? basename($attachment->file_path),
            ['Content-Disposition' => $disposition],
        );
    }

    public function destroy(ReportAttachment $attachment): RedirectResponse
    {
        $employee = request()->user()->employee;
        abort_if(! $employee, 403);

        $isReporter = $attachment->report->reported_by === $employee->id;
        $isLead = $attachment->report->workItem->project->leader_id === $employee->id;
        abort_if(! $isReporter && ! $isLead, 403);

        $attachment->deleteFile();
        $attachment->delete();

        return back()->with('success', 'Bukti dukung berhasil dihapus.');
    }

    public function review(Request $request, ReportAttachment $attachment): RedirectResponse
    {
        $employee = $request->user()->employee;
        abort_if(! $employee, 403);

        $isLead = $attachment->report->workItem->project->leader_id === $employee->id;
        abort_if(! $isLead, 403);

        $validated = $request->validate([
            'status' => ['required', 'in:approved,rejected'],
            'review_note' => ['nullable', 'string', 'max:500'],
        ]);

        $attachment->update([
            'status' => $validated['status'],
            'review_note' => $validated['review_note'] ?? null,
            'reviewed_by' => $employee->id,
            'reviewed_at' => now(),
        ]);

        $msg = $validated['status'] === 'approved' ? 'Bukti dukung disetujui.' : 'Bukti dukung ditolak.';

        return back()->with('success', $msg);
    }
}
