<?php

namespace Tests\Browser;

use App\Models\User;
use App\Models\WorkItem;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

/**
 * Browser tests for work item assignment ("Tertentu" vs "Semua" mode).
 *
 * Logs in as hespri@bpssulteng.id (a team lead) and opens the first project
 * in the "Tim Saya" tab. Member counts are derived from the created work item's
 * actual project — not hardcoded — so the tests work regardless of seed data.
 *
 * All test work items are prefixed [DUSK] so setUp/tearDown can clean them.
 */
class WorkItemAssignmentTest extends DuskTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        WorkItem::where('description', 'like', '[DUSK]%')->delete();
    }

    protected function tearDown(): void
    {
        WorkItem::where('description', 'like', '[DUSK]%')->delete();
        parent::tearDown();
    }

    private function loginAndOpenTeamTab(Browser $browser): void
    {
        $user = User::where('email', 'hespri@bpssulteng.id')->firstOrFail();
        $browser->loginAs($user)
            ->visit('/performance?year=2026&month=4')
            ->waitFor('[role="tablist"]', 10)
            // "Tim Saya" is the second tab trigger
            ->click('[role="tab"]:nth-child(2)')
            ->waitFor('[role="tab"][data-state="active"]:nth-child(2)', 10)
            ->pause(600); // let tab content render
    }

    private function openFirstAddForm(Browser $browser): void
    {
        // Click the first visible "+ Tambah Kegiatan" button (dashed border style)
        $browser->script(
            '[...document.querySelectorAll("button")].find(b => b.textContent.trim() === "+ Tambah Kegiatan")?.click()'
        );
        $browser->waitFor('[role="radiogroup"]', 5);
    }

    /**
     * After switching to "Tertentu" mode all member checkboxes must be CHECKED.
     * This validates the reactive(Set) fix — ref(Set) causes them to render unchecked.
     */
    public function test_specific_mode_checkboxes_start_checked(): void
    {
        $this->browse(function (Browser $browser) {
            $this->loginAndOpenTeamTab($browser);
            $this->openFirstAddForm($browser);

            // Switch to Tertentu
            $browser->click('#add-specific')
                ->pause(800)
                ->screenshot('after-tertentu-click');

            $states = $browser->script(
                'return [...document.querySelectorAll("button[role=\'checkbox\']")]' .
                '.map(cb => cb.getAttribute("data-state"))'
            )[0];

            $this->assertNotEmpty($states, 'No checkboxes rendered after switching to Tertentu mode');
            foreach ($states as $state) {
                $this->assertSame(
                    'checked', $state,
                    "Expected checkbox data-state=checked but got '$state'. " .
                    "The reactive(Set) fix may not have been applied correctly."
                );
            }
        });
    }

    /**
     * Unchecking one member in "Tertentu" mode then submitting must create
     * a work item with exactly (memberCount - 1) assignments.
     */
    public function test_specific_mode_excludes_unchecked_member(): void
    {
        $this->browse(function (Browser $browser) {
            $this->loginAndOpenTeamTab($browser);
            $this->openFirstAddForm($browser);

            $browser->type('textarea', '[DUSK] Specific assignment test')
                // Switch to Tertentu
                ->click('#add-specific')
                ->pause(600);

            // Verify checkboxes rendered in Tertentu mode
            $states = $browser->script(
                'return [...document.querySelectorAll("button[role=\'checkbox\']")]' .
                '.map(cb => cb.getAttribute("data-state"))'
            )[0];
            $this->assertNotEmpty($states, 'No checkboxes rendered in Tertentu mode');
            $this->assertGreaterThan(1, count($states), 'Need at least 2 members to test exclusion');

            // Uncheck the first checkbox (exclude first member)
            $browser->script(
                'document.querySelectorAll("button[role=\'checkbox\']")[0]?.click()'
            );
            $browser->pause(400);

            // Verify first checkbox is now unchecked
            $statesAfter = $browser->script(
                'return [...document.querySelectorAll("button[role=\'checkbox\']")]' .
                '.map(cb => cb.getAttribute("data-state"))'
            )[0];
            $this->assertSame('unchecked', $statesAfter[0], 'Checkbox did not toggle to unchecked after click');

            // Submit
            $browser->script(
                '[...document.querySelectorAll("button")].find(b => b.textContent.trim() === "Tambah")?.click()'
            );
            $browser->pause(2500); // wait for Inertia submit + page update

            $item = WorkItem::where('description', '[DUSK] Specific assignment test')
                ->latest('id')->first();

            $this->assertNotNull($item, 'Work item was not created');

            // Use the actual project's member count (not a hardcoded project ID)
            $memberCount = $item->project->members()->count();
            $this->assertSame(
                $memberCount - 1,
                $item->assignments()->count(),
                "Expected {$memberCount} - 1 = " . ($memberCount - 1) . " assignments (1 excluded), " .
                "got " . $item->assignments()->count() . ". " .
                "Bug: Tertentu mode still assigns to all members."
            );
        });
    }

    /**
     * "Semua" mode (default) must assign ALL project members.
     */
    public function test_all_mode_assigns_all_members(): void
    {
        $this->browse(function (Browser $browser) {
            $this->loginAndOpenTeamTab($browser);
            $this->openFirstAddForm($browser);

            $browser->type('textarea', '[DUSK] All members assignment test');

            // Leave Semua as default — no radio click
            $browser->script(
                '[...document.querySelectorAll("button")].find(b => b.textContent.trim() === "Tambah")?.click()'
            );
            $browser->pause(2500);

            $item = WorkItem::where('description', '[DUSK] All members assignment test')
                ->latest('id')->first();

            $this->assertNotNull($item, 'Work item was not created');

            // Use the actual project's member count (not a hardcoded project ID)
            $memberCount = $item->project->members()->count();
            $this->assertSame(
                $memberCount,
                $item->assignments()->count(),
                "Expected all {$memberCount} members in Semua mode, got {$item->assignments()->count()}"
            );
        });
    }
}
