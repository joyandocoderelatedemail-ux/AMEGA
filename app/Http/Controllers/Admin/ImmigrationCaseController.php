<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\ImmigrationCases;
use Illuminate\Http\Request;

class ImmigrationCaseController extends Controller
{
    /**
     * Every service case across clients, by service and status: what still needs
     * requirements, what is filed and waiting on the BI, what was released. A search
     * takes a client's name or passport, or a case reference such as OS-2026-0014.
     */
    public function index(Request $request)
    {
        $kinds = ImmigrationCases::kinds();
        $kind = array_key_exists((string) $request->input('service'), $kinds) ? $request->input('service') : null;
        $status = array_key_exists((string) $request->input('status'), ImmigrationCases::STATUSES) ? $request->input('status') : 'open';
        $search = trim((string) $request->input('q'));

        return view('admin.immigration.cases', [
            'cases' => ImmigrationCases::search($kind, $status, $search),
            'counts' => ImmigrationCases::openCounts(),
            'kinds' => $kinds,
            'kind' => $kind,
            'status' => $status,
            'search' => $search,
        ]);
    }
}
