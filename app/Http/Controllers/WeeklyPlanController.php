<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResolvesTeams;
use App\Models\Employee;
use App\Models\Team;
use App\Models\WeeklyFocus;
use App\Notifications\KinetikNotification;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/** The PJ's focus for a member's week. The member reads it on "Rekap Mingguan". */
class WeeklyPlanController extends Controller
{
    use ResolvesTeams;

    /**
     * The PJ sets (or clears, with an empty text) a member's focus for a week.
     */
    public function storeFocus(Request $request): RedirectResponse
    {
        $employee = $request->user()->employee;
        abort_if($employee === null, 403, 'Akun tidak terhubung ke data pegawai.');

        $validated = $request->validate([
            'team_id' => ['required', 'integer', 'exists:teams,id'],
            'employee_id' => ['required', 'integer', 'exists:employees,id'],
            'week_start' => ['required', 'date'],
            'body' => ['nullable', 'string', 'max:2000'],
        ]);

        $team = Team::findOrFail($validated['team_id']);
        abort_unless($this->isPj($employee, $team->id), 403, 'Hanya PJ yang dapat mengisi fokus minggu ini.');
        abort_unless($team->members()->where('employees.id', $validated['employee_id'])->exists(), 422, 'Pegawai ini bukan anggota tim.');

        $key = [
            'team_id' => $team->id,
            'employee_id' => (int) $validated['employee_id'],
        ];
        $weekStart = Carbon::parse($validated['week_start'])->startOfWeek(Carbon::MONDAY)->toDateString();
        $existing = WeeklyFocus::where($key)->whereDate('week_start', $weekStart)->first();
        $body = trim((string) ($validated['body'] ?? ''));

        if ($body === '') {
            $existing?->delete();

            return back()->with('success', 'Fokus minggu ini dihapus.');
        }

        $changed = $existing?->body !== $body;
        if ($existing) {
            $existing->update(['body' => $body, 'created_by' => $employee->id]);
        } else {
            WeeklyFocus::create([...$key, 'week_start' => $weekStart, 'body' => $body, 'created_by' => $employee->id]);
        }

        $member = Employee::find($key['employee_id']);
        if ($changed && $member?->user && $member->user_id !== $request->user()->id) {
            $member->user->notify(new KinetikNotification(
                'weekly_focus',
                ($employee->display_name ?? $employee->name).' mengisi fokus Anda untuk minggu '.Carbon::parse($weekStart)->locale('id')->translatedFormat('j M').': '.Str::limit($body, 140),
                route('weekly.index', ['week' => $weekStart]),
            ));
        }

        return back()->with('success', 'Fokus minggu ini disimpan.');
    }
}
