<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Kinetik\BuildDataIntegrityAction;
use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

class DataIntegrityController extends Controller
{
    public function index(BuildDataIntegrityAction $integrity): Response
    {
        return Inertia::render('Admin/DataIntegrity', ['checks' => $integrity->execute()]);
    }
}
