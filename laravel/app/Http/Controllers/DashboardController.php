<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Batch;
use App\Models\Reservation;
use App\Models\MortalityRecord;
use App\Models\Inventory;
use App\Models\HealthRecord;
use App\Models\Expense;
use App\Models\Activity;

class DashboardController extends Controller
{
    public function index()
    {
        // 1. Broiler Batch Summary
        $activeBatchesCount = Batch::where('status', 'active')->count();
        $totalLiveBirds = Batch::where('status', 'active')->sum('current_quantity');
        $totalStocked = Batch::where('status', 'active')->sum('initial_quantity');

        // Week mortality
        $weekMortality = abs((int)MortalityRecord::where('quantity_change', '<', 0)
            ->where('date', '>=', now()->subDays(7)->toDateString())
            ->sum('quantity_change'));

        $broilerBatches = [
            'total_active_batches' => $activeBatchesCount,
            'total_live_birds' => $totalLiveBirds,
            'total_stocked' => $totalStocked,
            'week_mortality' => $weekMortality,
        ];

        // 2. Reservations breakdown
        $reservations = [
            'total' => Reservation::count(),
            'active' => Reservation::where('status', 'active')->count(),
            'fulfilled' => Reservation::where('status', 'fulfilled')->count(),
            'cancelled' => Reservation::where('status', 'cancelled')->count(),
        ];

        // 3. Inventory Status
        $genInv = [
            'total_items' => Inventory::where('is_active', 1)->count(),
            'low_stock_items' => Inventory::where('is_active', 1)->whereNotNull('reorder_level')->whereColumn('quantity', '<=', 'reorder_level')->where('quantity', '>', 0)->count(),
            'out_of_stock_items' => Inventory::where('is_active', 1)->where('quantity', '<=', 0)->count(),
        ];

        // 4. Critical Health Alerts (rolling 24h mortality rate >= 1.0%)
        $healthAlerts = [];
        $activeFlocks = Batch::where('status', 'active')->get();
        foreach ($activeFlocks as $b) {
            $loss24h = abs((int)MortalityRecord::where('batch_id', $b->batch_id)
                ->where('quantity_change', '<', 0)
                ->where('created_at', '>=', now()->subHours(24))
                ->sum('quantity_change'));

            $preHeadcount = $b->current_quantity + $loss24h;
            if ($preHeadcount > 0 && $loss24h > 0) {
                $rate = ($loss24h / $preHeadcount) * 100;
                if ($rate >= 1.0) {
                    $healthAlerts[] = [
                        'batch_id' => $b->batch_id,
                        'batch_code' => $b->batch_code,
                        'total_loss' => $loss24h,
                        'pre_loss_headcount' => $preHeadcount,
                        'mortality_percentage' => round($rate, 2),
                    ];
                }
            }
        }

        // 5. Live Operations Feed (Unified Activities Stream)
        $recentActivities = $this->getRecentActivities(15);

        // 6. Charts Data
        $chartsData = $this->getChartsData();

        return view('dashboard', compact(
            'broilerBatches',
            'reservations',
            'genInv',
            'healthAlerts',
            'recentActivities',
            'chartsData'
        ));
    }

    private function getRecentActivities(int $limit = 15): array
    {
        return DB::select("
            (SELECT
                'reservation' as event_type,
                r.created_at as event_time,
                r.reservation_number as ref_code,
                c.name as party_name,
                CONCAT('Reservation ', UPPER(r.status), ' — pickup: ', DATE_FORMAT(r.pickup_date, '%b %d, %Y')) as description,
                r.status as status_badge
             FROM reservations r
             JOIN customers c ON r.customer_id = c.customer_id)
            UNION ALL
            (SELECT
                'expense' as event_type,
                e.created_at as event_time,
                CONCAT('EXP-', e.expense_id) as ref_code,
                COALESCE(b.batch_code, UPPER(e.category)) as party_name,
                CONCAT('Expense (', UPPER(e.category), '): ₱', FORMAT(e.amount, 2), ' — ', e.description) as description,
                e.category as status_badge
             FROM expenses e
             LEFT JOIN batches b ON e.batch_id = b.batch_id)
            UNION ALL
            (SELECT
                'mortality' as event_type,
                mr.created_at as event_time,
                b.batch_code as ref_code,
                b.batch_code as party_name,
                CONCAT('Mortality recorded: ', ABS(mr.quantity_change), ' bird(s) — ', COALESCE(mr.reason,'')) as description,
                'mortality' as status_badge
             FROM mortality_records mr
             JOIN batches b ON mr.batch_id = b.batch_id
             WHERE mr.quantity_change < 0)
            UNION ALL
            (SELECT
                'health' as event_type,
                hr.created_at as event_time,
                b.batch_code as ref_code,
                hr.medicine_name as party_name,
                CONCAT('Health/Med: ', hr.quantity_used, ' qty (', UPPER(hr.type), ')') as description,
                hr.type as status_badge
             FROM health_records hr
             JOIN batches b ON hr.batch_id = b.batch_id)
            UNION ALL
            (SELECT
                'batch' as event_type,
                b.created_at as event_time,
                b.batch_code as ref_code,
                COALESCE(b.supplier, 'Farm') as party_name,
                CONCAT('Batch ', b.batch_code, ': ', b.initial_quantity, ' birds arrived') as description,
                b.status as status_badge
             FROM batches b)
            UNION ALL
            (SELECT
                'activity' as event_type,
                CONCAT(a.date, ' 08:00:00') as event_time,
                CONCAT('ACT-', a.activity_id) as ref_code,
                COALESCE(b.batch_code, 'General') as party_name,
                CONCAT('Farm Activity (', a.type, '): ', COALESCE(a.item_name, ''), ' — ', COALESCE(a.remarks, '')) as description,
                LOWER(a.type) as status_badge
             FROM activities a
             LEFT JOIN batches b ON a.batch_id = b.batch_id)
            ORDER BY event_time DESC
            LIMIT ?
        ", [$limit]);
    }

    private function getChartsData(): array
    {
        // 1. Sales Trend
        $salesTrend = DB::select("
            SELECT 
                DATE_FORMAT(created_at, '%b %Y') as month_label,
                SUM(total_amount) as net_sales,
                SUM(CASE WHEN status = 'fulfilled' THEN total_amount ELSE 0 END) as delivered_sales
            FROM reservations
            WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
            GROUP BY YEAR(created_at), MONTH(created_at), DATE_FORMAT(created_at, '%b %Y')
            ORDER BY YEAR(created_at) ASC, MONTH(created_at) ASC
        ");

        // 2. Daily Mortality Trend (Last 14 days)
        $mortalityTrend = DB::select("
            SELECT
                date,
                DATE_FORMAT(date, '%b %d') as formatted_date,
                SUM(ABS(quantity_change)) as total_deaths
            FROM mortality_records
            WHERE quantity_change < 0
              AND date >= DATE_SUB(CURDATE(), INTERVAL 14 DAY)
            GROUP BY date
            ORDER BY date ASC
        ");

        // 3. Batch headcount overview
        $batchOverview = DB::select("
            SELECT
                batch_code,
                initial_quantity,
                current_quantity,
                (initial_quantity - current_quantity) as total_mortality,
                ROUND(((initial_quantity - current_quantity) / initial_quantity) * 100, 2) as mortality_rate_pct
            FROM batches
            WHERE status = 'active'
            ORDER BY arrival_date DESC
        ");

        return [
            'sales_trend' => $salesTrend,
            'mortality_trend' => $mortalityTrend,
            'batch_overview' => $batchOverview,
        ];
    }
}
