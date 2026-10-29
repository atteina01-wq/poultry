<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\AuditLog;

class AuditLogController extends Controller
{
    public function index(Request $request)
    {
        $tableFilter = $request->query('table', 'all');

        $query = AuditLog::with('user');

        if ($tableFilter !== 'all') {
            $query->where('table_name', $tableFilter);
        }

        $logs = $query->orderBy('created_at', 'desc')->paginate(20);

        return view('audit.index', compact('logs', 'tableFilter'));
    }
}
