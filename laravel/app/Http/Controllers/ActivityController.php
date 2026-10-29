<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Activity;
use App\Models\Batch;
use App\Models\Inventory;
use App\Models\FeedConsumption;
use App\Models\AuditLog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class ActivityController extends Controller
{
    public function index(Request $request)
    {
        $batches = Batch::where('status', 'active')->orderBy('batch_code')->get();

        $activities = Activity::with(['batch', 'user'])
            ->whereNotNull('type')
            ->where('type', '!=', '')
            ->where('type', '!=', 'None')
            ->orderBy('created_at', 'desc')
            ->orderBy('activity_id', 'desc')
            ->limit(50)
            ->get();

        return view('activities.index', compact('activities', 'batches'));
    }

    public function getInventoryItems(Request $request)
    {
        $type = $request->query('type');
        $query = Inventory::where('is_active', 1);

        switch ($type) {
            case 'Feeding':
                $query->where('category', 'feed');
                break;
            case 'Health':
            case 'Health Inspection':
                $query->whereIn('category', ['medicine', 'vitamins']);
                break;
            case 'Supply':
                $query->whereIn('category', ['supply', 'vitamins', 'limestone']);
                break;
            default:
                return response()->json([]);
        }

        $items = $query->orderBy('item_name')->get(['inventory_id', 'item_name', 'quantity', 'unit']);
        return response()->json($items);
    }

    public function store(Request $request)
    {
        $batchId = $request->input('batch_id') ? (int)$request->input('batch_id') : null;
        $type = trim($request->input('type', 'General'));
        $date = $request->input('date', date('Y-m-d'));
        $remarks = trim($request->input('remarks', ''));
        $qty = (float)$request->input('quantity', 0.0);
        $itemName = trim($request->input('item_name', ''));
        $inventoryId = $request->input('inventory_id') ? (int)$request->input('inventory_id') : null;
        $userId = Auth::id() ?: 1;

        try {
            DB::transaction(function () use ($batchId, $type, $date, $remarks, $qty, $itemName, $inventoryId, $userId) {
                $activityItemName = $itemName;

                if ($type === 'Feeding') {
                    if ($inventoryId) {
                        $inv = Inventory::lockForUpdate()->find($inventoryId);
                        if (!$inv) {
                            throw new \Exception("Selected feed inventory item not found.");
                        }
                        if ($qty > $inv->quantity) {
                            throw new \Exception("Insufficient feed inventory! Available: {$inv->quantity} {$inv->unit}, Requested: {$qty} {$inv->unit}.");
                        }
                        $activityItemName = $inv->item_name;

                        // Deduct inventory
                        $inv->update([
                            'previous_quantity' => $inv->quantity,
                            'quantity' => $inv->quantity - $qty
                        ]);

                        // Sync with feed_consumption
                        FeedConsumption::create([
                            'batch_id' => $batchId,
                            'inventory_id' => $inventoryId,
                            'quantity_used' => $qty,
                            'date' => $date,
                            'recorded_by' => $userId,
                            'remarks' => $remarks
                        ]);
                    } elseif (empty($activityItemName)) {
                        $activityItemName = 'Broiler Feed';
                    }
                } elseif ($type === 'Cleaning') {
                    if (empty($activityItemName)) {
                        $activityItemName = 'Facility Cleaning';
                    }
                } elseif ($type === 'Health Inspection') {
                    if ($inventoryId) {
                        $inv = Inventory::lockForUpdate()->find($inventoryId);
                        if ($inv && $qty > 0) {
                            if ($qty > $inv->quantity) {
                                throw new \Exception("Insufficient inventory stock! Available: {$inv->quantity} {$inv->unit}, Requested: {$qty} {$inv->unit}.");
                            }
                            $activityItemName = $inv->item_name;
                            $inv->update([
                                'previous_quantity' => $inv->quantity,
                                'quantity' => $inv->quantity - $qty
                            ]);
                        }
                    }
                    if (empty($activityItemName)) {
                        $activityItemName = 'Health Inspection';
                    }
                } elseif ($type === 'Supply') {
                    if ($inventoryId) {
                        $inv = Inventory::lockForUpdate()->find($inventoryId);
                        if (!$inv) {
                            throw new \Exception("Selected supply inventory item not found.");
                        }
                        if ($qty > $inv->quantity) {
                            throw new \Exception("Insufficient supply inventory! Available: {$inv->quantity} {$inv->unit}, Requested: {$qty} {$inv->unit}.");
                        }
                        $activityItemName = $inv->item_name;
                        $inv->update([
                            'previous_quantity' => $inv->quantity,
                            'quantity' => $inv->quantity - $qty
                        ]);
                    }
                    if (empty($activityItemName)) {
                        $activityItemName = 'Farm Supply';
                    }
                } elseif ($type === 'Weight Sampling') {
                    if (empty($activityItemName)) {
                        $activityItemName = 'Flock Weight Sampling';
                    }
                } else {
                    if (empty($activityItemName)) {
                        $activityItemName = 'General Farm Task';
                    }
                }

                $activity = Activity::create([
                    'batch_id' => $batchId,
                    'user_id' => $userId,
                    'type' => $type,
                    'item_name' => $activityItemName,
                    'quantity' => $qty,
                    'date' => $date,
                    'remarks' => $remarks,
                    'status' => 'completed'
                ]);

                AuditLog::create([
                    'user_id' => $userId,
                    'action' => 'create',
                    'table_name' => 'activities',
                    'record_id' => $activity->activity_id,
                    'new_values' => json_encode([
                        'batch_id' => $batchId,
                        'user_id' => $userId,
                        'type' => $type,
                        'item_name' => $activityItemName,
                        'quantity' => $qty,
                        'date' => $date,
                        'remarks' => $remarks
                    ]),
                    'ip_address' => request()->ip() ?? '127.0.0.1'
                ]);
            });

            return redirect()->route('activities.index')->with('success', "{$type} task recorded successfully!");
        } catch (\Exception $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }
    }

    public function complete($id)
    {
        $activity = Activity::findOrFail($id);
        $activity->update([
            'status' => 'completed',
            'user_id' => Auth::id() ?: 1
        ]);

        return redirect()->route('activities.index')->with('success', 'Activity marked as completed!');
    }
}
