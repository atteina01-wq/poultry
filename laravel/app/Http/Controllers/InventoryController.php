<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Inventory;
use App\Models\AuditLog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class InventoryController extends Controller
{
    public function index(Request $request)
    {
        $itemFilter = $request->query('item_filter', 'all');

        // KPIs
        $totalValuation = (float)DB::table('inventory')
            ->where('is_active', 1)
            ->selectRaw('SUM(GREATEST(0, quantity) * unit_cost) as val')
            ->value('val') ?: 0.0;

        $lowStockCount = (int)DB::table('inventory')
            ->where('is_active', 1)
            ->where('quantity', '>', 0)
            ->where('quantity', '<=', 10)
            ->count();

        $outOfStockCount = (int)DB::table('inventory')
            ->where('is_active', 1)
            ->where('quantity', '<=', 0)
            ->count();

        // Expiring Lots Monitoring (within 60 days)
        $expiringLots = [];
        try {
            $expiringLots = DB::table('inventory_lots as il')
                ->join('inventory as i', 'il.inventory_id', '=', 'i.inventory_id')
                ->select([
                    'il.lot_id',
                    'il.lot_number',
                    'il.quantity_remaining',
                    'il.expiry_date',
                    'i.item_name',
                    'i.category',
                    'i.unit',
                    'i.unit_cost',
                    DB::raw('DATEDIFF(il.expiry_date, CURDATE()) as days_until_expiry')
                ])
                ->whereNotNull('il.expiry_date')
                ->whereRaw('DATEDIFF(il.expiry_date, CURDATE()) <= 60')
                ->orderBy('days_until_expiry', 'asc')
                ->get();
        } catch (\Exception $e) {
            $expiringLots = collect();
        }

        // Current Stock List
        $currentStock = DB::table('inventory')
            ->where('is_active', 1)
            ->orderBy('category')
            ->orderBy('item_name')
            ->get();

        // All items for usage filter
        $allItems = DB::table('inventory')
            ->select('inventory_id', 'item_name', 'category')
            ->orderBy('category')
            ->orderBy('item_name')
            ->get();

        // Usage History & Stock Balance (Feed + Activities + Health Records)
        $feedQuery = DB::table('feed_consumption as f')
            ->join('batches as b', 'f.batch_id', '=', 'b.batch_id')
            ->join('inventory as i', 'f.inventory_id', '=', 'i.inventory_id')
            ->select([
                'f.date',
                'b.batch_code',
                'i.item_name',
                DB::raw("'Feeding' as type"),
                'f.quantity_used as quantity',
                'i.unit',
                'i.quantity as remaining_stock'
            ]);

        $actQuery = DB::table('activities as a')
            ->join('batches as b', 'a.batch_id', '=', 'b.batch_id')
            ->leftJoin('inventory as i', 'a.item_name', '=', 'i.item_name')
            ->whereIn('a.type', ['Health', 'Supply'])
            ->select([
                'a.date',
                'b.batch_code',
                'a.item_name',
                'a.type',
                'a.quantity',
                DB::raw("COALESCE(i.unit, 'units') as unit"),
                DB::raw("COALESCE(i.quantity, 0) as remaining_stock")
            ]);

        $hrQuery = DB::table('health_records as hr')
            ->join('batches as b', 'hr.batch_id', '=', 'b.batch_id')
            ->leftJoin('inventory as i', 'hr.medicine_name', '=', 'i.item_name')
            ->select([
                'hr.date',
                'b.batch_code',
                'hr.medicine_name as item_name',
                DB::raw("'Health' as type"),
                'hr.quantity_used as quantity',
                DB::raw("COALESCE(i.unit, 'units') as unit"),
                DB::raw("COALESCE(i.quantity, 0) as remaining_stock")
            ]);

        if ($itemFilter !== 'all') {
            $selectedItem = DB::table('inventory')->where('inventory_id', $itemFilter)->first();
            if ($selectedItem) {
                $feedQuery->where('f.inventory_id', $selectedItem->inventory_id);
                $actQuery->where('a.item_name', $selectedItem->item_name);
                $hrQuery->where('hr.medicine_name', $selectedItem->item_name);
            }
        }

        $combinedUsage = $feedQuery->unionAll($actQuery)->unionAll($hrQuery);
        $usageHistory = DB::query()->fromSub($combinedUsage, 'usage_data')
            ->orderBy('date', 'desc')
            ->paginate(10);

        return view('inventory.index', compact(
            'totalValuation',
            'lowStockCount',
            'outOfStockCount',
            'expiringLots',
            'currentStock',
            'allItems',
            'itemFilter',
            'usageHistory'
        ));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'item_name' => 'required|string|max:255',
            'category' => 'required|string|in:feed,medicine,vitamins,supply,limestone',
            'quantity' => 'required|numeric|min:0.01',
            'unit' => 'required|string|max:50',
            'cost' => 'required|numeric|min:0',
            'lot_number' => 'nullable|string|max:50',
            'supplier' => 'nullable|string|max:100',
            'received_date' => 'nullable|date',
            'expiry_date' => 'nullable|date'
        ]);

        $itemName = trim($validated['item_name']);
        $category = $validated['category'];
        $quantity = (float)$validated['quantity'];
        $cost = (float)$validated['cost'];

        DB::transaction(function () use ($validated, $itemName, $category, $quantity, $cost) {
            $existing = Inventory::where('item_name', $itemName)
                ->where('category', $category)
                ->first();

            if ($existing) {
                $itemId = $existing->inventory_id;
                $existing->update([
                    'previous_quantity' => $existing->quantity,
                    'quantity' => $existing->quantity + $quantity,
                    'unit_cost' => $cost
                ]);
                $action = 'restock';
            } else {
                $created = Inventory::create([
                    'item_name' => $itemName,
                    'category' => $category,
                    'quantity' => $quantity,
                    'previous_quantity' => 0,
                    'unit' => $validated['unit'],
                    'unit_cost' => $cost,
                    'is_active' => 1
                ]);
                $itemId = $created->inventory_id;
                $action = 'create';
            }

            // Record lot
            if (!empty($validated['lot_number'])) {
                DB::table('inventory_lots')->insert([
                    'inventory_id' => $itemId,
                    'lot_number' => $validated['lot_number'],
                    'quantity_received' => $quantity,
                    'quantity_remaining' => $quantity,
                    'unit_cost' => $cost,
                    'received_date' => $validated['received_date'] ?: date('Y-m-d'),
                    'expiry_date' => $validated['expiry_date'] ?: null,
                    'supplier' => $validated['supplier'] ?: null,
                    'created_at' => now()
                ]);
            }

            AuditLog::create([
                'user_id' => Auth::id() ?: 1,
                'action' => $action,
                'table_name' => 'inventory',
                'record_id' => $itemId,
                'new_values' => json_encode($validated),
                'ip_address' => request()->ip() ?? '127.0.0.1'
            ]);
        });

        return redirect()->route('inventory.index')->with('success', "Inventory item '{$itemName}' saved and lot tracked successfully!");
    }
}
