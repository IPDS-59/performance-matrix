<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\WorkItem;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class WorkItemController extends Controller
{
    public function store(Request $request, Project $project): RedirectResponse
    {
        $this->authorize('create', WorkItem::class);

        $validated = $request->validate([
            'number' => ['required', 'integer', 'min:1', "unique:work_items,number,NULL,id,project_id,{$project->id}"],
            'description' => ['required', 'string'],
        ]);

        $project->workItems()->create($validated);

        return back()->with('success', 'Rincian kegiatan berhasil ditambahkan.');
    }

    public function update(Request $request, WorkItem $workItem): RedirectResponse
    {
        $this->authorize('update', $workItem);

        $validated = $request->validate([
            'description' => ['required', 'string'],
        ]);

        $workItem->update($validated);

        return back()->with('success', 'Rincian kegiatan berhasil diperbarui.');
    }

    public function destroy(WorkItem $workItem): RedirectResponse
    {
        $this->authorize('delete', $workItem);

        $workItem->delete();

        return back()->with('success', 'Rincian kegiatan berhasil dihapus.');
    }
}
