<?php

namespace App\Jobs;

use App\Actions\Kinetik\SyncMemberActivitiesAction;
use App\Models\User;
use Illuminate\Foundation\Bus\Dispatchable;
use Throwable;

/**
 * First sync after a member connects their SSO account. Dispatched after the
 * response so it never slows the login, and any failure is only reported: the
 * member can press "Sinkronkan data saya" later.
 */
class SyncMyActivitiesJob
{
    use Dispatchable;

    public function __construct(private readonly int $userId) {}

    public function handle(SyncMemberActivitiesAction $sync): void
    {
        try {
            if ($user = User::find($this->userId)) {
                $sync->execute($user);
            }
        } catch (Throwable $e) {
            report($e);
        }
    }
}
