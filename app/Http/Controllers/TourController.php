<?php

namespace App\Http\Controllers;

use App\Models\TourCompletion;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/** Remembers that the signed-in user has seen a page tour. */
class TourController extends Controller
{
    public function complete(Request $request, string $key): Response
    {
        abort_unless(preg_match('/^[a-z0-9._-]{1,100}$/', $key) === 1, 404);

        TourCompletion::firstOrCreate(['user_id' => $request->user()->id, 'tour_key' => $key]);

        return response()->noContent();
    }

    /** Let the tour of a page open by itself again, from the "Panduan" menu. */
    public function reset(Request $request): Response
    {
        TourCompletion::where('user_id', $request->user()->id)->delete();

        return response()->noContent();
    }
}
