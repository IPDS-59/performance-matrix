<?php

use App\Services\Kinetik\MemberPlanFlow;

function flowStates(int $rk, int $activities, int $unsent, array $plans): array
{
    return collect((new MemberPlanFlow)->steps($rk, $activities, $unsent, $plans))->pluck('state', 'key')->all();
}

const PLAN_OK = ['in_kipapp' => true, 'push_error' => null, 'status' => 'pushed'];

it('marks everything done for a member who is fully in kipApp and sent', function () {
    expect(flowStates(6, 8, 0, [PLAN_OK]))->toBe(['rk' => 'done', 'skp' => 'done', 'plan' => 'done', 'kipapp' => 'done', 'sent' => 'done']);
});

it('blocks on missing RK and says nothing it cannot know about the SKP', function () {
    $states = flowStates(0, 0, 0, []);

    expect($states['rk'])->toBe('blocked')
        ->and($states['skp'])->toBe('unknown')
        ->and($states['plan'])->toBe('todo')
        ->and($states['kipapp'])->toBe('unknown')
        ->and($states['sent'])->toBe('unknown');
});

it('blocks the SKP step when a push failed because the quarter SKP is missing', function () {
    $plans = [['in_kipapp' => false, 'push_error' => 'SKP triwulan 4 belum dibuat di kipApp. Buat SKP triwulan dulu.', 'status' => 'planned']];

    $steps = collect((new MemberPlanFlow)->steps(3, 0, 0, $plans))->keyBy('key');

    expect($steps['skp']['state'])->toBe('blocked')
        ->and($steps['skp']['hint'])->toContain('Perencanaan Kinerja > Periodik')
        ->and($steps['kipapp']['state'])->toBe('blocked');
});

it('waits for the start date when plans are not pushed yet and counts unsent kegiatan', function () {
    $plans = [['in_kipapp' => false, 'push_error' => null, 'status' => 'planned']];

    $steps = collect((new MemberPlanFlow)->steps(3, 5, 2, $plans))->keyBy('key');

    expect($steps['kipapp']['state'])->toBe('todo')
        ->and($steps['sent']['state'])->toBe('todo')
        ->and($steps['sent']['hint'])->toContain('2 kegiatan');
});
