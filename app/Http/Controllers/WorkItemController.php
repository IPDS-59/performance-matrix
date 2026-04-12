<?php

namespace App\Http\Controllers;

use App\Actions\WorkItem\SyncWorkItemAssignmentsAction;
use App\Models\Project;
use App\Models\WorkItem;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class WorkItemController extends Controller
{
    public function __construct(private readonly SyncWorkItemAssignmentsAction $syncAssignments) {}

    public function store(Request $request, Project $project): RedirectResponse
    {
        $employee = $request->user()->employee;
        $isProjectLeader = $employee !== null && $project->leader_id === $employee->id;

        if (! $isProjectLeader) {
            $this->authorize('create', WorkItem::class);
        }

        $validated = $this->validateWorkItem($request, $project);

        $workItem = $project->workItems()->create([
            'number' => $validated['number'],
            'description' => $validated['description'],
            'target' => $validated['target'],
            'target_unit' => $validated['target_unit'],
        ]);

        $this->syncAssignments->execute($workItem, $project, $validated);

        return back()->with('success', 'Rincian kegiatan berhasil ditambahkan.');
    }

    public function update(Request $request, WorkItem $workItem): RedirectResponse
    {
        $employee = $request->user()->employee;
        $isProjectLeader = $employee !== null && $workItem->project->leader_id === $employee->id;

        if (! $isProjectLeader) {
            $this->authorize('update', $workItem);
        }

        $validated = $this->validateWorkItem($request, $workItem->project, $workItem);

        $workItem->update([
            'description' => $validated['description'],
            'target' => $validated['target'],
            'target_unit' => $validated['target_unit'],
        ]);

        $this->syncAssignments->execute($workItem, $workItem->project, $validated);

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

    private function validateWorkItem(Request $request, Project $project, ?WorkItem $ignore = null): array
    {
        $rules = [
            'description' => ['required', 'string'],
            'target' => ['required', 'integer', 'min:1'],
            'target_unit' => ['required', 'string', 'max:50'],
            'assign_to' => ['required', 'in:all,specific'],
            'assignments' => ['required_if:assign_to,specific', 'array'],
            'assignments.*.employee_id' => ['required', 'exists:employees,id'],
            'assignments.*.target' => ['required', 'integer', 'min:1'],
            'assignments.*.target_unit' => ['required', 'string', 'max:50'],
        ];

        // number is only set at creation time
        if ($ignore === null) {
            $rules['number'] = ['required', 'integer', 'min:1',
                "unique:work_items,number,NULL,id,project_id,{$project->id}"];
        }

        return $request->validate($rules);
    }
}
