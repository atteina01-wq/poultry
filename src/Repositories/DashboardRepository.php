<?php
namespace Poultry\Repositories;

use PDO;
use Poultry\Core\Database;

class DashboardRepository {
    private PDO $db;

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }

    public function getKPIs(): array {
        // 1. Active Broiler Batch Summary
        $stmtBroilers = $this->db->query("
            SELECT
                COUNT(*) as total_active_batches,
                COALESCE(SUM(current_quantity), 0) as total_live_birds,
                COALESCE(SUM(initial_quantity), 0) as total_stocked
            FROM batches
            WHERE status = 'active'
        ");
        $broilerStats = $stmtBroilers->fetch(PDO::FETCH_ASSOC);

        // 2. This week's total mortality (for KPI card)
        $stmtWeekMort = $this->db->query("
            SELECT COALESCE(SUM(ABS(quantity_change)), 0) as week_mortality
            FROM mortality_records
            WHERE quantity_change < 0
              AND date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
        ");
        $weekMortality = (int)$stmtWeekMort->fetchColumn();

        // Removed Orders Breakdown & Sales Calculations

        // 4. Reservations Breakdown
        $stmtRes = $this->db->query("
            SELECT 
                COUNT(*) as total_reservations,
                SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_reservations,
                SUM(CASE WHEN status = 'fulfilled' THEN 1 ELSE 0 END) as fulfilled_reservations,
                SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled_reservations,
                SUM(CASE WHEN status = 'expired' THEN 1 ELSE 0 END) as expired_reservations
            FROM reservations
        ");
        $resStats = $stmtRes->fetch(PDO::FETCH_ASSOC);

        // 5. General Inventory Status
        $totalInventoryItems = (int)($this->db->query("SELECT COUNT(*) FROM inventory WHERE is_active = 1")->fetchColumn() ?? 0);
        $lowStockInventory = (int)($this->db->query("SELECT COUNT(*) FROM inventory WHERE is_active = 1 AND reorder_level IS NOT NULL AND quantity <= reorder_level AND quantity > 0")->fetchColumn() ?? 0);
        $outOfStockInventory = (int)($this->db->query("SELECT COUNT(*) FROM inventory WHERE is_active = 1 AND quantity <= 0")->fetchColumn() ?? 0);

        // 6. Expiring inventory lots (within 30 days)
        $expiringLots = (int)($this->db->query("SELECT COUNT(*) FROM inventory_lots WHERE quantity_remaining > 0 AND expiry_date IS NOT NULL AND expiry_date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY) AND expiry_date >= CURDATE()")->fetchColumn() ?? 0);

        // 7. Customers Count
        $totalCustomers = (int)($this->db->query("SELECT COUNT(*) FROM customers WHERE is_active = 1")->fetchColumn() ?? 0);

        // 8. Health alerts — Chapter 1: 24h rolling window, 1.0% threshold
        $healthAlerts = $this->getHealthAlerts();

        return [
            'broiler_batches' => [
                'total_active_batches' => (int)$broilerStats['total_active_batches'],
                'total_live_birds'     => (int)$broilerStats['total_live_birds'],
                'total_stocked'        => (int)$broilerStats['total_stocked'],
                'week_mortality'       => $weekMortality,
            ],

            // Keep legacy key for backward compatibility with older dashboard cards
            'flocks' => [
                'total_active_batches' => (int)$broilerStats['total_active_batches'],
            ],

            'reservations' => [
                'total'     => (int)$resStats['total_reservations'],
                'active'    => (int)$resStats['active_reservations'],
                'fulfilled' => (int)$resStats['fulfilled_reservations'],
                'cancelled' => (int)$resStats['cancelled_reservations'],
                'expired'   => (int)$resStats['expired_reservations']
            ],

            'general_inventory' => [
                'total_items'        => $totalInventoryItems,
                'low_stock_items'    => $lowStockInventory,
                'out_of_stock_items' => $outOfStockInventory,
                'expiring_lots'      => $expiringLots,
            ],

            'customers_count' => $totalCustomers,
            'health_alerts'   => $healthAlerts
        ];
    }

    public function getHealthAlerts(?string $asOf = null): array {
        /**
         * Chapter 1 — Mortality & Health Alert System specification:
         *
         * Dynamic Rolling 24-Hour Window:
         *   Start = current date/time (NOW()) MINUS 24 hours
         *   End   = current date/time (NOW())
         *
         * Denominator ("Current Active Batch Headcount"):
         *   = batch.current_quantity + total losses recorded in the 24h window
         *   (i.e. headcount BEFORE the 24h losses are deducted)
         *
         * Threshold: rate >= 1.0% triggers a Critical Health Alert
         *   rate = (24h_loss / pre_loss_headcount) * 100
         */
        if ($asOf !== null) {
            $stmt = $this->db->prepare("
                SELECT
                    b.batch_id,
                    b.batch_code,
                    b.current_quantity,
                    b.flock_type,
                    ? AS window_end,
                    DATE_SUB(?, INTERVAL 24 HOUR) AS window_start,
                    COALESCE(w.loss_24h, 0) AS loss_24h
                FROM batches b
                JOIN (
                    SELECT
                        mr.batch_id,
                        SUM(ABS(mr.quantity_change)) AS loss_24h
                    FROM mortality_records mr
                    WHERE mr.quantity_change < 0
                      AND mr.created_at >= DATE_SUB(?, INTERVAL 24 HOUR)
                      AND mr.created_at <= ?
                    GROUP BY mr.batch_id
                ) w ON w.batch_id = b.batch_id
                WHERE b.status = 'active'
                  AND w.loss_24h > 0
            ");
            $stmt->execute([$asOf, $asOf, $asOf, $asOf]);
        } else {
            $stmt = $this->db->query("
                SELECT
                    b.batch_id,
                    b.batch_code,
                    b.current_quantity,
                    b.flock_type,
                    NOW() AS window_end,
                    DATE_SUB(NOW(), INTERVAL 24 HOUR) AS window_start,
                    COALESCE(w.loss_24h, 0) AS loss_24h
                FROM batches b
                JOIN (
                    SELECT
                        mr.batch_id,
                        SUM(ABS(mr.quantity_change)) AS loss_24h
                    FROM mortality_records mr
                    WHERE mr.quantity_change < 0
                      AND mr.created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
                      AND mr.created_at <= NOW()
                    GROUP BY mr.batch_id
                ) w ON w.batch_id = b.batch_id
                WHERE b.status = 'active'
                  AND w.loss_24h > 0
            ");
        }

        $alerts = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $loss24h = (int)$row['loss_24h'];
            // Denominator: headcount before the 24-hour losses were deducted
            $preHeadcount = (int)$row['current_quantity'] + $loss24h;
            if ($preHeadcount <= 0) continue;

            $rate = ($loss24h / $preHeadcount) * 100;

            // Chapter 1: alert when rate REACHES OR EXCEEDS 1.0%
            if ($rate >= 1.0) {
                $alerts[] = [
                    'batch_id'           => $row['batch_id'],
                    'batch_code'         => $row['batch_code'],
                    'flock_type'         => $row['flock_type'],
                    'total_loss'         => $loss24h,
                    'pre_loss_headcount' => $preHeadcount,
                    'mortality_percentage' => round($rate, 2),
                    'window_start'       => $row['window_start'],
                    'window_end'         => $row['window_end'],
                ];
            }
        }
        return $alerts;
    }

    public function getRecentActivities(int $limit = 15): array {
        $stmt = $this->db->prepare("
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
                CONCAT('Batch ', b.batch_code, ' (', UPPER(b.flock_type), '): ', b.initial_quantity, ' birds arrived') as description,
                b.status as status_badge
             FROM batches b)
            UNION ALL
            (SELECT
                'activity' as event_type,
                CONCAT(a.date, ' 08:00:00') as event_time,
                CONCAT('ACT-', a.activity_id) as ref_code,
                COALESCE(b.batch_code, 'General') as party_name,
                CONCAT('Farm Activity (', a.type, '): ', COALESCE(a.item_name, ''), ' ', IF(a.quantity > 0, CONCAT(a.quantity, ' qty'), ''), ' — ', COALESCE(a.remarks, '')) as description,
                LOWER(a.type) as status_badge
             FROM activities a
             LEFT JOIN batches b ON a.batch_id = b.batch_id)
            UNION ALL
            (SELECT
                'inventory' as event_type,
                i.created_at as event_time,
                i.sku as ref_code,
                i.item_name as party_name,
                CONCAT('Inventory: ', i.item_name, ' — ', i.quantity, ' ', COALESCE(i.unit,'units'), ' on hand') as description,
                i.category as status_badge
             FROM inventory i
             WHERE i.is_active = 1
             ORDER BY i.updated_at DESC
             LIMIT 5)
            ORDER BY event_time DESC
            LIMIT ?
        ");
        $stmt->bindValue(1, $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getCharts(): array {
        // 1. Sales Trend (Last 6 Months)
        $salesStmt = $this->db->query("
            SELECT 
                DATE_FORMAT(created_at, '%b %Y') as month_label,
                SUM(total_amount) as net_sales,
                SUM(CASE WHEN status = 'fulfilled' THEN total_amount ELSE 0 END) as delivered_sales
            FROM reservations
            WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
            GROUP BY YEAR(created_at), MONTH(created_at), DATE_FORMAT(created_at, '%b %Y')
            ORDER BY YEAR(created_at) ASC, MONTH(created_at) ASC
        ");
        $salesTrend = $salesStmt->fetchAll(PDO::FETCH_ASSOC);

        // 3. Daily Mortality Trend (Last 14 days) — replaces egg production chart
        $mortStmt = $this->db->query("
            SELECT
                date,
                DATE_FORMAT(date, '%b %d') as formatted_date,
                SUM(ABS(quantity_change)) as total_deaths,
                COUNT(DISTINCT batch_id) as batches_affected
            FROM mortality_records
            WHERE quantity_change < 0
              AND date >= DATE_SUB(CURDATE(), INTERVAL 14 DAY)
            GROUP BY date
            ORDER BY date ASC
        ");
        $mortalityTrend = $mortStmt->fetchAll(PDO::FETCH_ASSOC);

        // 4. Batch headcount overview
        $batchStmt = $this->db->query("
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
        $batchOverview = $batchStmt->fetchAll(PDO::FETCH_ASSOC);

        // 5. Reservations by Status
        $resStmt = $this->db->query("
            SELECT status, COUNT(*) as count
            FROM reservations
            GROUP BY status
        ");
        $reservationsByStatus = $resStmt->fetchAll(PDO::FETCH_ASSOC);

        return [
            'mortality_trend'      => $mortalityTrend,
            'batch_overview'       => $batchOverview,
            'reservations_by_status' => $reservationsByStatus,
            'sales_trend'          => $salesTrend
        ];
    }
}
