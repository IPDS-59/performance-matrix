<?php

namespace App\Http\Controllers;

use App\Actions\Kinetik\BuildOrganizationTreeAction;
use Inertia\Inertia;
use Inertia\Response;

/** "Struktur Organisasi": satker, head, teams and their members as a tree. Everyone signed in can read it. */
class OrganizationController extends Controller
{
    public function index(BuildOrganizationTreeAction $action): Response
    {
        return Inertia::render('Organization/Index', ['tree' => $action->execute()]);
    }
}
