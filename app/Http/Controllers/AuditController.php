<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use Illuminate\Http\Request;

class AuditController extends Controller
{
    public function index(Request $request)
    {
        abort_unless($request->user()->isAdmin(), 403);
        $entries = AuditLog::query()->with('actor')->latest('id')->paginate(30);

        return view('audit.index', compact('entries'));
    }
}
