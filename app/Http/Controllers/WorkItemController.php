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
        $employee = $request->user()->employee;
        $isProjectLeader = $employee !== null && $project->leader_id === $employee->id;

        if (! $isProjectLeader) {
            $this->authorize('create', WorkItem::class);
        }

        $validated = $request->validate([
            'number' => ['required', 'integer', 'min:1', "unique:work_items,number,NULL,id,project_id,{$project->id}"],
            'description' => ['required', 'string'],
            'target' => ['required', 'numeric', 'min:0.01'],
            'target_unit' => ['required', 'string', 'max:50'],
        ]);

        $project->workItems()->create($validated);

        return back()->with('success', 'Rincian kegiatan berhasil ditambahkan.');
    }

    public function update(Request $request, WorkItem $workItem): RedirectResponse
    {
        $employee = $request->user()->employee;
        $isProjectLeader = $employee !== null && $workItem->project->leader_id === $employee->id;

        if (! $isProjectLeader) {
            $this->authorize('update', $workItem);
        }

        $validated = $request->validate([
            'description' => ['required', 'string'],
            'target' => ['required', 'numeric', 'min:0.01'],
            'target_unit' => ['required', 'string', 'max:50'],
        ]);

        $workItem->update($validated);

        return back()->with('success', 'Rincian kegiatan berhasil diperbarui.');
    }

    public function destroy(WorkItem $workItem): RedirectResponse
    {
        $employee = request()->user()->employee;
        $isProjectLeader = $employee !== null && $workItem->project->leader_id === $employee->id;

        if (! $isProjectLeader) {
            $this->authorize('delete', $workItem);
        }

        $workItem->delete();

        return back()->with('success', 'Rincian kegiatan berhasil dihapus.');
    }
}
