<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\FeedConsumption;
use App\Models\Batch;
use App\Models\Inventory;
use App\Models\AuditLog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class FeedController extends Controller
{
    public function index(Request $request)
    {
        $category = strtolower($request->query('cat', 'feed'));
        $targetCategory = ($category === 'limestone') ? 'limestone' : 'feed';

        $records = FeedConsumption::with(['batch', 'inventory', 'recorder'])
            ->whereHas('inventory', function ($q) use ($targetCategory) {
                $q->where('category', $targetCategory);
            })
            ->orderBy('date', 'desc')
            ->orderBy('consumption_id', 'desc')
            ->get();

        $batches = Batch::where('status', 'active')
            ->where(function ($q) {
                $q->where('flock_type', 'broiler')->orWhereNull('flock_type');
            })
            ->orderBy('batch_code')
            ->get();

        $inventoryItems = Inventory::where('category', $targetCategory)
            ->orderBy('item_name')
            ->get();

        return view('feed.index', compact('records', 'batches', 'inventoryItems', 'targetCategory'));
    }

    public function store(Request $request)
    {
        $qty = $request->input('qty', $request->input('quantity_used'));
        $request->merge(['quantity_used' => $qty]);

        $validated = $request->validate([
            'batch_id' => 'required|exists:batches,batch_id',
            'inventory_id' => 'required|exists:inventory,inventory_id',
            'quantity_used' => 'required|numeric|min:0.01',
            'date' => 'required|date',
            'feed_stage' => 'nullable|string|in:Pre-Starter,Starter,Grower,Finisher',
            'remarks' => 'nullable|string'
        ]);

        $batch = Batch::findOrFail($validated['batch_id']);
        $inventory = Inventory::findOrFail($validated['inventory_id']);

        if ($validated['quantity_used'] > $inventory->quantity) {
            return back()->withInput()->with('error', "Insufficient stock for {$inventory->item_name}! Available: " . number_format($inventory->quantity, 2) . " {$inventory->unit}, Requested: " . number_format($validated['quantity_used'], 2) . " {$inventory->unit}.");
        }

        DB::transaction(function () use ($validated, $inventory, $batch) {
            $record = FeedConsumption::create([
                'batch_id' => $validated['batch_id'],
                'inventory_id' => $validated['inventory_id'],
                'feed_stage' => $validated['feed_stage'] ?? null,
                'quantity_used' => $validated['quantity_used'],
                'date' => $validated['date'],
                'recorded_by' => Auth::id() ?: 1,
                'remarks' => $validated['remarks'] ?? null,
                'unit_cost_snapshot' => $inventory->unit_cost ?? 0.00
            ]);

            // Deduct inventory
            $inventory->update([
                'previous_quantity' => $inventory->quantity,
                'quantity' => $inventory->quantity - $validated['quantity_used']
            ]);

            // Audit Log
            AuditLog::create([
                'user_id' => Auth::id() ?: 1,
                'action' => 'create',
                'table_name' => 'feed_consumption',
                'record_id' => $record->consumption_id,
                'new_values' => json_encode([
                    'batch_code' => $batch->batch_code,
                    'item_name' => $inventory->item_name,
                    'quantity_used' => $validated['quantity_used'],
                    'date' => $validated['date'],
                    'feed_stage' => $validated['feed_stage'] ?? null
                ]),
                'ip_address' => request()->ip() ?? '127.0.0.1'
            ]);
        });

        $cat = ($inventory->category === 'limestone') ? 'limestone' : 'feed';
        $msg = ($cat === 'limestone') ? 'Limestone record updated and inventory deducted!' : 'Feed record updated and inventory deducted!';

        return redirect()->route('feed.index', ['cat' => $cat])->with('success', $msg);
    }
}
