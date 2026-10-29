<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Expense;
use App\Models\Batch;
use App\Models\AuditLog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class ExpenseController extends Controller
{
    public function index(Request $request)
    {
        $categorySummary = DB::table('expenses')
            ->select('category', DB::raw('SUM(amount) as total_amount'), DB::raw('COUNT(*) as count'))
            ->groupBy('category')
            ->orderBy('total_amount', 'desc')
            ->get();

        $expenses = Expense::with(['batch', 'recorder', 'inventory'])
            ->orderBy('date', 'desc')
            ->orderBy('expense_id', 'desc')
            ->paginate(10);

        return view('expenses.index', compact('expenses', 'categorySummary'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'batch_id' => 'nullable|exists:batches,batch_id',
            'category' => 'required|string',
            'amount' => 'required|numeric|min:0.01',
            'description' => 'required|string|max:255',
            'date' => 'required|date'
        ]);

        $validated['recorded_by'] = Auth::id() ?: 1;
        $validated['source_type'] = 'manual';

        $expense = Expense::create($validated);

        AuditLog::create([
            'user_id' => Auth::id() ?: 1,
            'action' => 'create',
            'table_name' => 'expenses',
            'record_id' => $expense->expense_id,
            'new_values' => json_encode($validated),
            'ip_address' => request()->ip() ?? '127.0.0.1'
        ]);

        return redirect()->route('expenses.index')->with('success', 'Expense transaction logged successfully!');
    }
}
