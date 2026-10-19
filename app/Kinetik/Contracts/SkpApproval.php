<?php

namespace App\Kinetik\Contracts;

use App\Models\SkpStatus;
use App\Models\User;

/**
 * The atasan's own steps on a member's SKP, with the atasan's own kipApp token: set (tetapkan) a yearly
 * SKP, or send a SKP back to the member. Rating a quarter SKP stays in kipApp, it needs a score per RK.
 */
interface SkpApproval
{
    /** The kipApp pegawaiid of this atasan (stored on the employee the first time it is read). */
    public function pegawaiId(User $user): ?string;

    /** Set a yearly SKP that waits for the atasan: status 2 becomes 6 (Ditetapkan). */
    public function approve(User $atasan, SkpStatus $skp): void;

    /** Send a waiting SKP back to the member: it becomes Sedang dibuat again. */
    public function sendBack(User $atasan, SkpStatus $skp): void;
}
