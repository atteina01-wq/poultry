<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Batch;
use App\Models\Reservation;
use App\Models\Expense;
use App\Models\MortalityRecord;
use App\Models\FeedConsumption;
use App\Models\HealthRecord;
use App\Models\Inventory;
use App\Models\Activity;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    public function index(Request $request)
    {
        $datePreset = $request->query('preset', 'all');
        $dateFrom = $request->query('date_from');
        $dateTo = $request->query('date_to');
        $batchFilter = $request->query('batch_id') ? (int)$request->query('batch_id') : null;
        $statusFilter = $request->query('status');
        $activeTab = $request->query('tab', 'production');

        if ($datePreset === 'today') {
            $dateFrom = date('Y-m-d');
            $dateTo = date('Y-m-d');
        } elseif ($datePreset === 'this_week') {
            $dateFrom = date('Y-m-d', strtotime('monday this week'));
            $dateTo = date('Y-m-d', strtotime('sunday this week'));
        } elseif ($datePreset === 'this_month') {
            $dateFrom = date('Y-m-01');
            $dateTo = date('Y-m-t');
        }

        // Batches List for filter dropdown
        $batchesList = DB::table('batches')
            ->select('batch_id', 'batch_code', 'status')
            ->orderBy('arrival_date', 'desc')
            ->orderBy('batch_id', 'desc')
            ->get();

        // 1. High-Level Financial KPI Bar
        $resQuery = DB::table('reservations');
        if ($dateFrom) $resQuery->whereDate('pickup_date', '>=', $dateFrom);
        if ($dateTo) $resQuery->whereDate('pickup_date', '<=', $dateTo);
        if ($batchFilter) $resQuery->where('batch_id', $batchFilter);

        $netSales = (float)$resQuery->whereIn('status', ['active', 'fulfilled'])->sum('total_amount');
        $advancePayments = (float)$resQuery->whereIn('status', ['active', 'fulfilled'])->sum('advance_payment');
        $completedSales = (float)$resQuery->where('status', 'fulfilled')->sum('total_amount');

        $expQuery = DB::table('expenses');
        if ($dateFrom) $expQuery->whereDate('date', '>=', $dateFrom);
        if ($dateTo) $expQuery->whereDate('date', '<=', $dateTo);
        if ($batchFilter) $expQuery->where('batch_id', $batchFilter);

        $totalExpenses = (float)$expQuery->sum('amount');
        $netProfit = $netSales - $totalExpenses;

        $expensesByCategory = DB::table('expenses')
            ->select('category', DB::raw('SUM(amount) as total'))
            ->when($dateFrom, fn($q) => $q->whereDate('date', '>=', $dateFrom))
            ->when($dateTo, fn($q) => $q->whereDate('date', '<=', $dateTo))
            ->when($batchFilter, fn($q) => $q->where('batch_id', $batchFilter))
            ->groupBy('category')
            ->orderBy('total', 'desc')
            ->get();

        $financialReport = [
            'net_sales' => $netSales,
            'advance_payments' => $advancePayments,
            'completed_sales' => $completedSales,
            'total_expenses' => $totalExpenses,
            'net_profit' => $netProfit,
            'expenses_by_category' => $expensesByCategory
        ];

        // 2. Production Report
        $prodQuery = DB::table('batches as b')
            ->leftJoin(DB::raw('(SELECT batch_id, ABS(SUM(quantity_change)) as total_mortality FROM mortality_records WHERE quantity_change < 0 GROUP BY batch_id) as m'), 'b.batch_id', '=', 'm.batch_id')
            ->leftJoin(DB::raw('(SELECT batch_id, SUM(quantity_used) as total_feed_used FROM feed_consumption GROUP BY batch_id) as fc'), 'b.batch_id', '=', 'fc.batch_id')
            ->select([
                'b.batch_id',
                'b.batch_code',
                'b.flock_type',
                'b.breed',
                'b.house_pen',
                'b.initial_quantity',
                'b.current_quantity',
                'b.status',
                'b.arrival_date',
                'b.expected_harvest_date',
                DB::raw('COALESCE(m.total_mortality, 0) as total_mortality'),
                DB::raw('ROUND((COALESCE(m.total_mortality, 0) / NULLIF(b.initial_quantity, 0)) * 100, 2) as mortality_rate_pct'),
                DB::raw('ROUND(((b.initial_quantity - COALESCE(m.total_mortality, 0)) / NULLIF(b.initial_quantity, 0)) * 100, 2) as survival_rate_pct'),
                DB::raw('COALESCE(fc.total_feed_used, 0) as total_feed_used')
            ]);
        if ($dateFrom) $prodQuery->whereDate('b.arrival_date', '>=', $dateFrom);
        if ($dateTo) $prodQuery->whereDate('b.arrival_date', '<=', $dateTo);
        if ($batchFilter) $prodQuery->where('b.batch_id', $batchFilter);
        if ($statusFilter && $statusFilter !== 'all') $prodQuery->where('b.status', $statusFilter);
        $productionReport = $prodQuery->orderBy('b.arrival_date', 'desc')->get();

        // 3. Mortality Report
        $mortQuery = DB::table('mortality_records as m')
            ->join('batches as b', 'm.batch_id', '=', 'b.batch_id')
            ->leftJoin('users as u', 'm.recorded_by', '=', 'u.user_id')
            ->select([
                'm.date',
                'b.batch_code',
                DB::raw('ABS(m.quantity_change) as loss_count'),
                'm.reason',
                DB::raw("COALESCE(u.full_name, u.username, 'Staff') as recorded_by_user"),
                'm.created_at'
            ])
            ->where('m.quantity_change', '<', 0);
        if ($dateFrom) $mortQuery->whereDate('m.date', '>=', $dateFrom);
        if ($dateTo) $mortQuery->whereDate('m.date', '<=', $dateTo);
        if ($batchFilter) $mortQuery->where('m.batch_id', $batchFilter);
        $mortalityReport = $mortQuery->orderBy('m.date', 'desc')->get();

        // 4. Health Report
        $hQuery = DB::table('health_records as hr')
            ->join('batches as b', 'hr.batch_id', '=', 'b.batch_id')
            ->leftJoin('users as u', 'hr.recorded_by', '=', 'u.user_id')
            ->select([
                'hr.date',
                'b.batch_code',
                'hr.medicine_name',
                'hr.type',
                'hr.quantity_used',
                'hr.doctor_consultation',
                'hr.diagnosis',
                'hr.illness_observation',
                'hr.notes',
                'hr.status',
                'hr.next_due_date',
                DB::raw("COALESCE(u.full_name, u.username, 'Staff') as recorded_by_user")
            ]);
        if ($dateFrom) $hQuery->whereDate('hr.date', '>=', $dateFrom);
        if ($dateTo) $hQuery->whereDate('hr.date', '<=', $dateTo);
        if ($batchFilter) $hQuery->where('hr.batch_id', $batchFilter);
        if ($statusFilter && $statusFilter !== 'all') $hQuery->where('hr.status', $statusFilter);
        $healthReport = $hQuery->orderBy('hr.date', 'desc')->get();

        // 5. Feed Consumption Report
        $fcQuery = DB::table('feed_consumption as fc')
            ->join('batches as b', 'fc.batch_id', '=', 'b.batch_id')
            ->leftJoin('inventory as i', 'fc.inventory_id', '=', 'i.inventory_id')
            ->leftJoin('users as u', 'fc.recorded_by', '=', 'u.user_id')
            ->select([
                'fc.date',
                'b.batch_code',
                DB::raw("COALESCE(i.item_name, 'Standard Broiler Feed') as feed_type"),
                'fc.feed_stage',
                'fc.quantity_used',
                DB::raw("COALESCE(i.unit, 'kg') as unit"),
                'fc.unit_cost_snapshot',
                DB::raw('(fc.quantity_used * COALESCE(fc.unit_cost_snapshot, 0)) as total_cost'),
                DB::raw("COALESCE(u.full_name, u.username, 'Staff') as recorded_by_user"),
                'fc.remarks as notes'
            ]);
        if ($dateFrom) $fcQuery->whereDate('fc.date', '>=', $dateFrom);
        if ($dateTo) $fcQuery->whereDate('fc.date', '<=', $dateTo);
        if ($batchFilter) $fcQuery->where('fc.batch_id', $batchFilter);
        $consumptionReport = $fcQuery->orderBy('fc.date', 'desc')->get();

        // 6. Inventory Report
        $invQuery = DB::table('inventory')
            ->select([
                'item_name',
                'category',
                'quantity',
                'unit',
                'unit_cost',
                DB::raw('(quantity * unit_cost) as total_value'),
                DB::raw("CASE WHEN quantity <= 0 THEN 'Out of Stock' WHEN quantity <= 10 THEN 'Low Stock' ELSE 'In Stock' END as stock_status")
            ])
            ->where('is_active', 1);
        $inventoryReportItems = $invQuery->orderBy('category')->orderBy('item_name')->get();

        // 7. Reservations Report
        $resRepQuery = DB::table('reservations as r')
            ->leftJoin('customers as c', 'r.customer_id', '=', 'c.customer_id')
            ->leftJoin('batches as b', 'r.batch_id', '=', 'b.batch_id')
            ->select([
                'r.reservation_number',
                DB::raw("COALESCE(c.name, 'Direct Client') as customer_name"),
                'c.phone as customer_phone',
                'b.batch_code',
                DB::raw('COALESCE(r.quantity_birds, 0) as reserved_quantity'),
                'r.pickup_date',
                'r.total_amount',
                'r.advance_payment',
                DB::raw('(r.total_amount - COALESCE(r.advance_payment, 0)) as remaining_balance'),
                DB::raw('COALESCE(r.accepted_quantity, 0) as accepted_quantity'),
                DB::raw('COALESCE(r.rejected_quantity, 0) as rejected_quantity'),
                'r.status'
            ]);
        if ($dateFrom) $resRepQuery->whereDate('r.pickup_date', '>=', $dateFrom);
        if ($dateTo) $resRepQuery->whereDate('r.pickup_date', '<=', $dateTo);
        if ($batchFilter) $resRepQuery->where('r.batch_id', $batchFilter);
        if ($statusFilter && $statusFilter !== 'all') $resRepQuery->where('r.status', $statusFilter);
        $reservationsReport = $resRepQuery->orderBy('r.pickup_date', 'desc')->get();

        // 8. Returns Report
        $returnsReport = collect(); // Default empty if returns table not tracked

        // 9. Expenses Report
        $expRepQuery = DB::table('expenses as e')
            ->leftJoin('batches as b', 'e.batch_id', '=', 'b.batch_id')
            ->leftJoin('inventory as i', 'e.inventory_id', '=', 'i.inventory_id')
            ->leftJoin('users as u', 'e.recorded_by', '=', 'u.user_id')
            ->select([
                'e.date',
                'e.category',
                'e.description',
                'b.batch_code',
                'i.item_name as inventory_item_name',
                'e.amount',
                'e.source_type',
                DB::raw("COALESCE(u.full_name, u.username, 'Staff') as recorded_by_user")
            ]);
        if ($dateFrom) $expRepQuery->whereDate('e.date', '>=', $dateFrom);
        if ($dateTo) $expRepQuery->whereDate('e.date', '<=', $dateTo);
        if ($batchFilter) $expRepQuery->where('e.batch_id', $batchFilter);
        $expensesReport = $expRepQuery->orderBy('e.date', 'desc')->get();

        // 10. Worker Activity Report
        $actRepQuery = DB::table('activities as a')
            ->leftJoin('batches as b', 'a.batch_id', '=', 'b.batch_id')
            ->leftJoin('users as u', 'a.user_id', '=', 'u.user_id')
            ->select([
                'a.date',
                'a.created_at',
                DB::raw("COALESCE(u.full_name, u.username, 'Staff') as worker_name"),
                DB::raw("COALESCE(b.batch_code, 'General') as batch_code"),
                'a.type as activity_type',
                'a.item_name',
                'a.quantity',
                'a.remarks as description'
            ]);
        if ($dateFrom) $actRepQuery->whereDate('a.date', '>=', $dateFrom);
        if ($dateTo) $actRepQuery->whereDate('a.date', '<=', $dateTo);
        if ($batchFilter) $actRepQuery->where('a.batch_id', $batchFilter);
        $workerReport = $actRepQuery->orderBy('a.created_at', 'desc')->get();

        $tabTitles = [
            'production'   => 'Broiler Batch Production & Performance',
            'mortality'    => 'Flock Mortality Records',
            'health'       => 'Flock Health & Medication',
            'feed'         => 'Feed Consumption & Utilization',
            'inventory'    => 'Stock Inventory & Resource Levels',
            'reservations' => 'Broiler Reservations & Advance Bookings',
            'returns'      => 'Accepted, Returned & Rejected Chickens',
            'expenses'     => 'Operational Expenses Ledger',
            'financial'    => 'Financials, Revenues & Profitability',
            'audit'        => 'Worker Operations & Audit Trail'
        ];

        return view('reports.index', compact(
            'datePreset',
            'dateFrom',
            'dateTo',
            'batchFilter',
            'statusFilter',
            'activeTab',
            'batchesList',
            'financialReport',
            'productionReport',
            'mortalityReport',
            'healthReport',
            'consumptionReport',
            'inventoryReportItems',
            'reservationsReport',
            'returnsReport',
            'expensesReport',
            'workerReport',
            'tabTitles'
        ));
    }
}
