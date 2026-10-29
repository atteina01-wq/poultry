<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\HealthRecord;
use App\Models\Batch;
use App\Models\Inventory;
use App\Models\AuditLog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class HealthRecordController extends Controller
{
    public function index(Request $request)
    {
        $filterBatch = (int)$request->query('batch_id', 0);
        $filterStatus = trim($request->query('status', ''));
        $filterType = trim($request->query('type', ''));
        $filterDateFrom = trim($request->query('date_from', ''));
        $filterDateTo = trim($request->query('date_to', ''));

        $query = HealthRecord::with(['batch', 'inventory', 'recorder']);

        if ($filterBatch > 0) {
            $query->where('batch_id', $filterBatch);
        }
        if (!empty($filterStatus)) {
            $query->where('status', strtolower($filterStatus));
        }
        if (!empty($filterType)) {
            $query->where('type', strtolower($filterType));
        }
        if (!empty($filterDateFrom)) {
            $query->whereDate('date', '>=', $filterDateFrom);
        }
        if (!empty($filterDateTo)) {
            $query->whereDate('date', '<=', $filterDateTo);
        }

        $records = $query->orderBy('date', 'desc')->orderBy('health_id', 'desc')->paginate(10);
        $totalRecordsCount = $records->total();

        // Active batches
        $batches = Batch::where('status', 'active')
            ->where(function ($q) {
                $q->where('flock_type', 'broiler')->orWhereNull('flock_type');
            })
            ->orderBy('arrival_date', 'desc')
            ->get();

        // All batches for filter dropdown
        $filterBatches = Batch::where(function ($q) {
                $q->where('flock_type', 'broiler')->orWhereNull('flock_type');
            })
            ->orderBy('arrival_date', 'desc')
            ->get();

        // Inventory items for medicine select
        $inventoryItems = Inventory::whereIn('category', ['medicine', 'vitamins', 'supply'])
            ->where('is_active', 1)
            ->orderBy('item_name')
            ->get();

        return view('health.index', compact(
            'records',
            'batches',
            'filterBatches',
            'inventoryItems',
            'filterBatch',
            'filterStatus',
            'filterType',
            'filterDateFrom',
            'filterDateTo',
            'totalRecordsCount'
        ));
    }

    public function store(Request $request)
    {
        $medicine = trim($request->input('medicine', $request->input('medicine_name', '')));
        $qty = (float)$request->input('qty', $request->input('quantity_used', 0));
        $remarks = trim($request->input('remarks', $request->input('notes', '')));

        $request->merge([
            'medicine_name' => $medicine,
            'quantity_used' => $qty,
            'notes' => $remarks,
            'type' => 'Treatment'
        ]);

        $validated = $request->validate([
            'batch_id' => 'required|exists:batches,batch_id',
            'medicine_name' => 'required|string|max:255',
            'quantity_used' => 'required|numeric|min:0.01',
            'number_of_chickens_treated' => 'nullable|integer|min:0',
            'date' => 'required|date',
            'status' => 'required|in:scheduled,in_progress,completed,cancelled',
            'illness_observation' => 'nullable|string|max:255',
            'diagnosis' => 'nullable|string|max:255',
            'doctor_consultation' => 'nullable|string|max:255',
            'notes' => 'nullable|string'
        ]);

        $batch = Batch::findOrFail($validated['batch_id']);

        DB::transaction(function () use ($validated, $batch) {
            // Deduct stock if matching medicine item in inventory
            $item = Inventory::where('item_name', $validated['medicine_name'])
                ->lockForUpdate()
                ->first();

            $inventoryId = null;
            if ($item) {
                $inventoryId = $item->inventory_id;
                if ($item->quantity >= $validated['quantity_used']) {
                    $item->update([
                        'previous_quantity' => $item->quantity,
                        'quantity' => max(0, $item->quantity - $validated['quantity_used'])
                    ]);
                }
            }

            $userId = Auth::id() ?: 1;

            $record = HealthRecord::create([
                'batch_id' => $validated['batch_id'],
                'type' => 'Treatment',
                'medicine_name' => $validated['medicine_name'],
                'inventory_id' => $inventoryId,
                'number_of_chickens_treated' => $validated['number_of_chickens_treated'] ?: null,
                'quantity_used' => $validated['quantity_used'],
                'illness_observation' => $validated['illness_observation'] ?: null,
                'diagnosis' => $validated['diagnosis'] ?: null,
                'doctor_consultation' => $validated['doctor_consultation'] ?: null,
                'date' => $validated['date'],
                'status' => $validated['status'],
                'notes' => $validated['notes'] ?: null,
                'recorded_by' => $userId
            ]);

            AuditLog::create([
                'user_id' => $userId,
                'action' => 'create',
                'table_name' => 'health_records',
                'record_id' => $record->health_id,
                'new_values' => json_encode([
                    'batch_code' => $batch->batch_code,
                    'medicine_name' => $validated['medicine_name'],
                    'quantity_used' => $validated['quantity_used'],
                    'date' => $validated['date'],
                    'status' => $validated['status']
                ]),
                'ip_address' => request()->ip() ?? '127.0.0.1'
            ]);
        });

        return redirect()->route('health.index')->with('success', 'Health & medication record saved successfully!');
    }

    public function updateStatus(Request $request)
    {
        $validated = $request->validate([
            'health_id' => 'required|exists:health_records,health_id',
            'status' => 'required|in:scheduled,in_progress,completed,cancelled'
        ]);

        $record = HealthRecord::findOrFail($validated['health_id']);
        $record->update(['status' => $validated['status']]);

        AuditLog::create([
            'user_id' => Auth::id() ?: 1,
            'action' => 'update',
            'table_name' => 'health_records',
            'record_id' => $record->health_id,
            'new_values' => json_encode(['status' => $validated['status']]),
            'ip_address' => request()->ip() ?? '127.0.0.1'
        ]);

        return redirect()->route('health.index')->with('success', 'Treatment status updated to ' . ucfirst(str_replace('_', ' ', $validated['status'])) . ' successfully!');
    }
}
