<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\WasteRecord;
use App\Models\Inventory;
use App\Models\Batch;
use App\Models\Expense;
use App\Models\AuditLog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class WasteController extends Controller
{
    public function index()
    {
        $inventoryItems = Inventory::where('is_active', 1)
            ->orderBy('category')
            ->orderBy('item_name')
            ->get();

        $batches = Batch::where('status', 'active')
            ->orderBy('batch_code')
            ->get();

        $wasteRecords = WasteRecord::with(['inventory', 'batch', 'recorder'])
            ->orderBy('waste_date', 'desc')
            ->orderBy('waste_id', 'desc')
            ->get();

        return view('waste.index', compact('inventoryItems', 'batches', 'wasteRecords'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'waste_type' => 'required|in:supply,medicine,other',
            'waste_date' => 'required|date',
            'quantity' => 'required|numeric|min:0.01',
            'unit' => 'required|string|max:20',
            'reason' => 'required|in:broken,expired,spoiled,damaged,cull,other',
            'inventory_id' => 'nullable|exists:inventory,inventory_id',
            'batch_id' => 'nullable|exists:batches,batch_id',
            'estimated_cost' => 'nullable|numeric|min:0',
            'remarks' => 'nullable|string'
        ]);

        if (in_array($validated['waste_type'], ['supply', 'medicine']) && empty($validated['inventory_id'])) {
            return back()->withInput()->with('error', "Please select an inventory item for {$validated['waste_type']} waste.");
        }

        $userId = Auth::id() ?: 1;

        DB::transaction(function () use ($validated, $userId) {
            $inventory = null;
            if (!empty($validated['inventory_id'])) {
                $inventory = Inventory::lockForUpdate()->find($validated['inventory_id']);
                if ($inventory) {
                    $inventory->update([
                        'previous_quantity' => $inventory->quantity,
                        'quantity' => max(0, $inventory->quantity - $validated['quantity'])
                    ]);
                }
            }

            $expenseId = null;
            if (!empty($validated['estimated_cost']) && $validated['estimated_cost'] > 0) {
                $expense = Expense::create([
                    'batch_id' => $validated['batch_id'] ?? null,
                    'inventory_id' => $validated['inventory_id'] ?? null,
                    'category' => 'other',
                    'amount' => $validated['estimated_cost'],
                    'description' => "Waste Loss: {$validated['quantity']} {$validated['unit']} ({$validated['reason']})",
                    'date' => $validated['waste_date'],
                    'source_type' => 'waste',
                    'recorded_by' => $userId
                ]);
                $expenseId = $expense->expense_id;
            }

            $waste = WasteRecord::create([
                'waste_date' => $validated['waste_date'],
                'waste_type' => $validated['waste_type'],
                'inventory_id' => $validated['inventory_id'] ?? null,
                'batch_id' => $validated['batch_id'] ?? null,
                'quantity' => $validated['quantity'],
                'unit' => $validated['unit'],
                'reason' => $validated['reason'],
                'estimated_cost' => $validated['estimated_cost'] ?? null,
                'expense_id' => $expenseId,
                'recorded_by' => $userId,
                'remarks' => $validated['remarks'] ?? null,
                'created_at' => now()
            ]);

            AuditLog::create([
                'user_id' => $userId,
                'action' => 'create',
                'table_name' => 'waste_records',
                'record_id' => $waste->waste_id,
                'new_values' => json_encode($validated),
                'ip_address' => request()->ip() ?? '127.0.0.1'
            ]);
        });

        return redirect()->route('waste.index')->with('success', 'Waste record logged successfully! Farm inventory has been updated.');
    }
}
