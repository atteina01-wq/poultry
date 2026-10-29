<?php 
include('database.php'); 
include('db_check.php'); 
include('header.php'); 

$dashService = new \Poultry\Services\DashboardService();
$kpis = $dashService->getKPIs();
$chartsData = $dashService->getCharts();
$recentActivities = $dashService->getRecentActivities(15);

$health_alerts = $kpis['health_alerts'] ?? [];
$reservations = $kpis['reservations'];
$genInv       = $kpis['general_inventory'];
$broilerBatches = $kpis['broiler_batches'] ?? ['total_active_batches'=>0,'total_live_birds'=>0,'total_stocked'=>0,'week_mortality'=>0];
?>

<style>
    .dashboard-tabs-wrapper {
        background: rgba(0, 0, 0, 0.28);
        padding: 6px;
        border-radius: 999px;
        border: 1px solid var(--border);
        box-shadow: var(--shadow-sm);
        display: inline-flex;
        gap: 4px;
        backdrop-filter: blur(12px);
    }
    .nav-pills .nav-link {
        color: var(--text-muted) !important;
        font-weight: 600;
        transition: all 0.25s ease;
        border: 1px solid transparent;
        font-size: 13.5px;
        border-radius: 999px;
        padding: 8px 18px;
    }
    .nav-pills .nav-link.active {
        background: linear-gradient(135deg, rgba(61, 255, 154, 0.28), rgba(242, 182, 50, 0.18)) !important;
        color: #fff !important;
        border-color: rgba(61, 255, 154, 0.4);
        box-shadow: 0 0 20px rgba(61, 255, 154, 0.25);
    }
    .nav-pills .nav-link:not(.active):hover {
        color: #fff !important;
        background: rgba(61, 255, 154, 0.08) !important;
    }

    .kpi-card {
        transition: transform 0.3s ease, box-shadow 0.3s ease;
        position: relative;
        overflow: hidden;
    }
    .kpi-card::after {
        content: '';
        position: absolute;
        top: 0; right: 0; bottom: 0;
        width: 90px;
        background: linear-gradient(90deg, transparent, rgba(255,255,255,0.06));
        transform: skewX(-15deg) translateX(-160px);
        transition: transform 0.6s;
        pointer-events: none;
    }
    .kpi-card:hover::after {
        transform: skewX(-15deg) translateX(280px);
    }

    .pulse-animation { animation: pulse-neon 2s infinite; }
    @keyframes pulse-neon {
        0%, 100% { transform: scale(0.95); opacity: 1; filter: drop-shadow(0 0 4px #ff4d6d); }
        50% { transform: scale(1.12); opacity: 0.85; filter: drop-shadow(0 0 12px #ff4d6d); }
    }
    .cursor-pointer { cursor: pointer; }

    .activity-feed-wrapper {
        border-radius: var(--radius-md);
        background: rgba(0, 0, 0, 0.22);
        border: 1px solid var(--border);
    }
    .timeline-item {
        position: relative;
        padding-left: 28px;
        padding-bottom: 16px;
        border-left: 2px solid rgba(61, 255, 154, 0.18);
        margin-left: 14px;
        transition: border-color 0.2s ease;
    }
    .timeline-item:last-child {
        border-left-color: transparent;
        padding-bottom: 0;
    }
    .timeline-item:hover {
        border-left-color: var(--primary);
    }
    .timeline-dot {
        position: absolute;
        left: -6px;
        top: 10px;
        width: 10px;
        height: 10px;
        border-radius: 50%;
        border: 2px solid #0c1c16;
        box-shadow: 0 0 10px currentColor;
    }
    .ops-glass-row {
        background: rgba(255, 255, 255, 0.04) !important;
        border: 1px solid rgba(61, 255, 154, 0.12) !important;
        border-radius: 14px !important;
        box-shadow: none !important;
        transition: border-color 0.2s ease, box-shadow 0.2s ease;
    }
    .ops-glass-row:hover {
        border-color: rgba(61, 255, 154, 0.35) !important;
        box-shadow: 0 0 18px rgba(61, 255, 154, 0.12) !important;
    }
    .ops-glass-row .border-top {
        border-color: rgba(255, 255, 255, 0.08) !important;
    }

    .chart-container-premium {
        border-radius: var(--radius-md);
        padding: 24px;
        transition: box-shadow 0.3s ease, border-color 0.3s ease;
    }
    .chart-container-premium:hover {
        border-color: rgba(61, 255, 154, 0.3) !important;
        box-shadow: 0 0 28px rgba(61, 255, 154, 0.12);
    }

    .dash-welcome-title {
        font-family: 'Bricolage Grotesque', sans-serif;
        font-weight: 700;
        letter-spacing: -0.02em;
    }
    .neon-alert-card {
        background: rgba(255, 77, 109, 0.12) !important;
        border: 1px solid rgba(255, 77, 109, 0.35) !important;
        border-left: 3px solid #ff4d6d !important;
        box-shadow: 0 0 24px rgba(255, 77, 109, 0.15);
        border-radius: var(--radius-md) !important;
    }
    .batch-chip {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        padding: 12px 14px;
        margin-bottom: 8px;
        border-radius: 14px;
        background: rgba(255, 255, 255, 0.04);
        border: 1px solid rgba(61, 255, 154, 0.12);
    }
    .batch-chip:last-child { margin-bottom: 0; }

    /* Analytics chart panels */
    .analytics-chart-card {
        position: relative;
        overflow: hidden;
        border-radius: var(--radius-md);
        padding: 22px 22px 18px;
        height: 100%;
        display: flex;
        flex-direction: column;
    }
    .analytics-chart-card::before {
        content: '';
        position: absolute;
        inset: 0 auto auto 0;
        width: 100%;
        height: 2px;
        background: linear-gradient(90deg, transparent, var(--primary), var(--accent), transparent);
        opacity: 0.7;
        pointer-events: none;
    }
    .analytics-chart-card .chart-kicker {
        font-size: 11px;
        font-weight: 700;
        letter-spacing: 0.08em;
        text-transform: uppercase;
        color: var(--accent);
        margin-bottom: 4px;
    }
    .analytics-chart-card .chart-title {
        font-family: 'Bricolage Grotesque', sans-serif;
        font-size: 1.05rem;
        font-weight: 700;
        color: var(--text);
        margin: 0 0 4px;
        display: flex;
        align-items: center;
        gap: 10px;
    }
    .analytics-chart-card .chart-title i {
        width: 32px;
        height: 32px;
        border-radius: 10px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 14px;
        background: rgba(61, 255, 154, 0.12);
        color: var(--primary);
        border: 1px solid rgba(61, 255, 154, 0.28);
        box-shadow: 0 0 16px rgba(61, 255, 154, 0.2);
        flex-shrink: 0;
    }
    .analytics-chart-card .chart-title i.tone-danger {
        background: rgba(255, 77, 109, 0.12);
        color: #ff8da3;
        border-color: rgba(255, 77, 109, 0.35);
        box-shadow: 0 0 16px rgba(255, 77, 109, 0.22);
    }
    .analytics-chart-card .chart-title i.tone-cyan {
        background: rgba(62, 224, 255, 0.12);
        color: var(--neon-cyan, #3ee0ff);
        border-color: rgba(62, 224, 255, 0.35);
        box-shadow: 0 0 16px rgba(62, 224, 255, 0.22);
    }
    .analytics-chart-card .chart-title i.tone-gold {
        background: rgba(242, 182, 50, 0.12);
        color: var(--accent);
        border-color: rgba(242, 182, 50, 0.4);
        box-shadow: 0 0 16px rgba(242, 182, 50, 0.22);
    }
    .analytics-chart-card .chart-sub {
        font-size: 12.5px;
        color: var(--text-muted);
        margin-bottom: 14px;
    }
    .analytics-chart-card .chart-canvas-wrap {
        position: relative;
        flex: 1 1 auto;
        min-height: 280px;
        width: 100%;
    }
</style>

<div class="d-flex justify-content-start align-items-center flex-wrap gap-3 mb-4">
    <div class="dashboard-tabs-wrapper">
        <ul class="nav nav-pills" id="pills-tab" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="btnDashboard" onclick="switchView('dashboard')" type="button">
                    <i class="fas fa-layer-group me-2"></i>Dashboard
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="btnAnalytics" onclick="switchView('analytics')" type="button">
                    <i class="fas fa-chart-line me-2"></i>Analytics
                </button>
            </li>
        </ul>
    </div>
</div>

<div id="dashboardView" class="animate__animated animate__fadeIn">
    <!-- Health Alerts Section -->
    <?php if (!empty($health_alerts)): ?>
    <div class="row mb-4" id="healthAlertContainer">
        <div class="col-12">
            <div id="fullAlert" class="card p-3 border-0 neon-alert-card" role="alert">
                <div class="d-flex justify-content-between align-items-start">
                    <div class="d-flex gap-3">
                        <i class="fas fa-exclamation-triangle text-danger mt-1"></i>
                        <div>
                            <h6 class="text-danger mb-1 fw-bold">Critical Health Alert: Elevated Mortality</h6>
                            <p class="mb-2 small text-danger" style="opacity: 0.9;">The following batches have reached or exceeded the 1.0% mortality threshold within a rolling 24-hour window:</p>
                            <ul class="mb-0 small text-danger ps-3" style="opacity: 0.9;">
                                <?php foreach ($health_alerts as $alert): ?>
                                    <li>
                                        <strong>Batch <?php echo htmlspecialchars($alert['batch_code']); ?></strong>:
                                        <?php echo $alert['total_loss']; ?> bird(s) lost
                                        (<?php echo $alert['mortality_percentage']; ?>% of <?php echo number_format($alert['pre_loss_headcount']); ?>)
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    </div>
                    <button type="button" class="btn btn-sm" onclick="toggleAlert(true)" style="color: var(--danger);">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            </div>
            
            <div id="minimizedAlert" class="d-none">
                <div class="d-flex align-items-center p-2 px-3 cursor-pointer badge bg-danger" onclick="toggleAlert(false)">
                    <i class="fas fa-exclamation-triangle me-2 pulse-animation"></i>
                    <span class="fw-bold small">Health Alert: <?php echo count($health_alerts); ?> batch(es) &ge;1.0%</span>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Top KPI Cards -->
    <div class="row g-2 g-md-4">
        <div class="col-6 col-md-3">
            <div class="kpi-card" style="border-top: 4px solid var(--primary);">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="kpi-title">Active Batches</div>
                        <div class="kpi-value"><?php echo $broilerBatches['total_active_batches']; ?></div>
                        <div class="kpi-subtitle"><?php echo number_format($broilerBatches['total_live_birds']); ?> live birds</div>
                    </div>
                    <div class="kpi-icon-wrapper" style="background-color: var(--primary-soft); color: var(--primary);"><i class="fas fa-layer-group"></i></div>
                </div>
            </div>
        </div>
        
        <div class="col-6 col-md-3">
            <div class="kpi-card" style="border-top: 4px solid var(--danger);">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="kpi-title">Weekly Mortality</div>
                        <div class="kpi-value text-danger"><?php echo number_format($broilerBatches['week_mortality']); ?></div>
                        <div class="kpi-subtitle">birds lost (7 days)</div>
                    </div>
                    <div class="kpi-icon-wrapper" style="background-color: var(--danger-bg); color: var(--danger);"><i class="fas fa-skull"></i></div>
                </div>
            </div>
        </div>

        <div class="col-6 col-md-3">
            <div class="kpi-card" style="border-top: 4px solid var(--info);">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="kpi-title">Reservations</div>
                        <div class="kpi-value"><?php echo $reservations['total']; ?></div>
                        <div class="kpi-subtitle"><span class="text-success fw-medium"><?php echo $reservations['active']; ?> active</span></div>
                    </div>
                    <div class="kpi-icon-wrapper" style="background-color: var(--info-bg); color: var(--info);"><i class="fas fa-calendar-check"></i></div>
                </div>
            </div>
        </div>

        <div class="col-6 col-md-3">
            <div class="kpi-card" style="border-top: 4px solid var(--warning);">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="kpi-title">Inventory Status</div>
                        <div class="kpi-value"><?php echo $genInv['total_items']; ?></div>
                        <div class="kpi-subtitle <?php echo $genInv['low_stock_items'] > 0 ? 'text-warning fw-bold' : ''; ?>"><?php echo $genInv['low_stock_items']; ?> low stock</div>
                    </div>
                    <div class="kpi-icon-wrapper" style="background-color: var(--warning-bg); color: var(--warning);"><i class="fas fa-warehouse"></i></div>
                </div>
            </div>
        </div>
    </div>


    <!-- Activity Stream and Reservations Summary -->
    <div class="row mt-2 mt-md-4 g-3 g-md-4 align-items-stretch">
        <!-- Live Activity Stream -->
        <div class="col-md-8">
            <div class="card h-100 border-0 shadow-sm d-flex flex-column">
                <div class="card-body p-3 p-md-4 d-flex flex-column">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h5 class="section-title mb-0"><i class="fas fa-bolt text-warning"></i>Live Operations Feed</h5>
                        <a href="activities.php" class="btn btn-sm btn-outline-secondary rounded-pill px-3">View All</a>
                    </div>

                    <div class="activity-feed-wrapper p-3 flex-grow-1 d-flex flex-column position-relative" style="min-height: 400px; overflow: hidden;">
                        <!-- Watermark to fill empty visual space -->
                        <div class="position-absolute d-flex flex-column justify-content-center align-items-center w-100 h-100" style="top: 0; left: 0; z-index: 0; opacity: 0.03; pointer-events: none;">
                            <i class="fas fa-chart-network" style="font-size: 150px;"></i>
                        </div>
                        
                        <div class="activity-feed overflow-auto custom-scrollbar pe-2 flex-grow-1 position-relative" style="z-index: 1;">
                            <?php if (empty($recentActivities)): ?>
                                <div class="text-center py-5 text-muted d-flex flex-column justify-content-center h-100">
                                    <div class="mb-3"><i class="fas fa-clipboard-list fs-1 opacity-25"></i></div>
                                    <h6 class="fw-medium">No recent operations</h6>
                                    <p class="small mb-0">Operational activities will appear here once recorded.</p>
                                </div>
                            <?php else: ?>
                            <?php foreach ($recentActivities as $act):
                                $eventType = $act['event_type'];
                                $status    = $act['status_badge'];

                                $color = 'info';
                                if ($eventType === 'reservation') $color = ($status === 'cancelled') ? 'danger' : 'info';
                                elseif ($eventType === 'mortality') $color = 'danger';
                                elseif ($eventType === 'health') $color = 'warning';
                                elseif ($eventType === 'inventory') $color = 'info';
                                elseif ($eventType === 'payment') $color = 'success';
                                elseif ($eventType === 'expense') $color = 'warning';
                                elseif ($eventType === 'batch') $color = 'primary';
                                
                                $dotColor = "var(--$color)";
                            ?>
                                <div class="timeline-item">
                                    <div class="timeline-dot" style="background-color: <?php echo $dotColor; ?>; color: <?php echo $dotColor; ?>;"></div>
                                    <div class="d-flex flex-column p-3 ops-glass-row">
                                        <div class="d-flex justify-content-between align-items-start mb-2">
                                            <span class="fw-semibold" style="font-size: 14px; color: var(--text);">
                                                <?php echo htmlspecialchars($act['party_name']); ?>
                                            </span>
                                            <span class="badge bg-<?php echo $color; ?> px-2 py-1 rounded-pill" style="font-size: 10px;"><?php echo strtoupper($status); ?></span>
                                        </div>
                                        <p class="mb-2" style="font-size: 13px; color: var(--text-muted);">
                                            <?php echo htmlspecialchars($act['description']); ?>
                                        </p>
                                        <div class="d-flex align-items-center gap-3 mt-1 pt-2 border-top">
                                            <small class="text-muted fw-medium" style="font-size: 11px;">
                                                <i class="far fa-clock me-1"></i><?php echo date('M d, g:i A', strtotime($act['event_time'])); ?>
                                            </small>
                                            <small class="text-muted fw-medium" style="font-size: 11px;">
                                                <i class="fas fa-hashtag me-1"></i><?php echo htmlspecialchars($act['ref_code']); ?>
                                            </small>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Batch Headcount & Mortality Summary -->
        <div class="col-md-4">
            <div class="card h-100 border-0 shadow-sm">
                <div class="card-body p-4">
                    <div class="mb-4">
                        <h5 class="section-title"><i class="fas fa-layer-group text-primary"></i>Current Broiler Counts</h5>
                        <div style="font-size: 14px;">
                            <?php if (empty($chartsData['batch_overview'])): ?>
                                <div class="text-muted py-3 text-center">No active batches.</div>
                            <?php else: ?>
                                <?php foreach ($chartsData['batch_overview'] as $batch): ?>
                                <div class="batch-chip">
                                    <span class="fw-medium">Batch <?php echo htmlspecialchars($batch['batch_code']); ?></span>
                                    <span class="badge bg-primary rounded-pill px-3 py-2"><?php echo number_format($batch['current_quantity']); ?> birds</span>
                                </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="mt-4">
                        <h5 class="section-title"><i class="fas fa-skull-crossbones text-danger"></i>Mortality by Batch</h5>
                        <div class="mb-4" style="font-size: 14px;">
                            <?php if (empty($chartsData['batch_overview'])): ?>
                                <div class="text-muted py-3 text-center">No active batches.</div>
                            <?php else: ?>
                                <?php foreach ($chartsData['batch_overview'] as $batch): ?>
                                <div class="batch-chip">
                                    <span class="fw-medium">Batch <?php echo htmlspecialchars($batch['batch_code']); ?></span>
                                    <span class="badge bg-danger rounded-pill px-3 py-2"><?php echo number_format($batch['total_mortality']); ?> lost</span>
                                </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="mt-4 pt-3 border-top">
                        <h5 class="section-title"><i class="fas fa-chart-line text-primary"></i>Daily Mortality Trend</h5>
                        <div style="position: relative; height: 220px; width: 100%; margin-top: 16px;">
                            <canvas id="mortalityTrendChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Analytics Tab View -->
<div id="analyticsView" class="d-none animate__animated animate__fadeIn">
    <div class="row g-3 g-md-4 mb-3 mb-md-4">
        <div class="col-md-6">
            <div class="chart-container-premium analytics-chart-card">
                <div class="chart-kicker">Integrator Revenue</div>
                <h5 class="chart-title"><i class="fas fa-peso-sign tone-cyan"></i>Flock Sales Pulse</h5>
                <p class="chart-sub">Net vs delivered reservation sales for the last 6 months.</p>
                <div class="chart-canvas-wrap">
                    <canvas id="salesTrendChart"></canvas>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="chart-container-premium analytics-chart-card">
                <div class="chart-kicker">Flock Health</div>
                <h5 class="chart-title"><i class="fas fa-heartbeat tone-danger"></i>Daily Mortality Spike</h5>
                <p class="chart-sub">Birds lost per day across active broiler houses (last 14 days).</p>
                <div class="chart-canvas-wrap">
                    <canvas id="analyticsMortalityChart"></canvas>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3 g-md-4">
        <div class="col-md-6">
            <div class="chart-container-premium analytics-chart-card">
                <div class="chart-kicker">Reservation Pipeline</div>
                <h5 class="chart-title"><i class="fas fa-clipboard-list tone-gold"></i>Booking Status Mix</h5>
                <p class="chart-sub">How integrator reservations are distributed by workflow status.</p>
                <div class="chart-canvas-wrap">
                    <canvas id="reservationsStatusChart"></canvas>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="chart-container-premium analytics-chart-card">
                <div class="chart-kicker">Broiler Houses</div>
                <h5 class="chart-title"><i class="fas fa-drumstick-bite"></i>Live Headcount vs Loss</h5>
                <p class="chart-sub">Current live birds compared with cumulative mortality per active flock.</p>
                <div class="chart-canvas-wrap">
                    <canvas id="batchOverviewChart"></canvas>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
function switchView(view) {
    const dashboardView = document.getElementById('dashboardView');
    const analyticsView = document.getElementById('analyticsView');
    const btnDashboard = document.getElementById('btnDashboard');
    const btnAnalytics = document.getElementById('btnAnalytics');
    
    if (view === 'dashboard') {
        dashboardView.classList.remove('d-none');
        analyticsView.classList.add('d-none');
        btnDashboard.classList.add('active');
        btnAnalytics.classList.remove('active');
    } else {
        dashboardView.classList.add('d-none');
        analyticsView.classList.remove('d-none');
        btnAnalytics.classList.add('active');
        btnDashboard.classList.remove('active');
        initAnalyticsCharts();
    }
}

let analyticsChartsInitialized = false;

function initDashboardCharts() {
    const mortData  = <?php echo json_encode($chartsData['mortality_trend'] ?? []); ?>;
    
    // 2. Daily Mortality Trend Chart (On Main Dashboard)
    const mortCtx = document.getElementById('mortalityTrendChart').getContext('2d');
    
    const isNeon = document.body.getAttribute('data-theme') === 'neon-farm';
    let gradient = mortCtx.createLinearGradient(0, 0, 0, 220);
    gradient.addColorStop(0, isNeon ? 'rgba(255, 77, 109, 0.45)' : 'rgba(181, 87, 75, 0.5)');
    gradient.addColorStop(1, isNeon ? 'rgba(255, 77, 109, 0.02)' : 'rgba(181, 87, 75, 0.05)');
    const tickColor = isNeon ? '#8fa89a' : '#666';
    const gridColor = isNeon ? 'rgba(61, 255, 154, 0.08)' : 'rgba(0,0,0,0.05)';
    const lineColor = isNeon ? '#ff4d6d' : '#B5574B';

    new Chart(mortCtx, {
        type: 'line',
        data: {
            labels: mortData.map(d => d.formatted_date),
            datasets: [{
                label: 'Birds Lost',
                data: mortData.map(d => d.total_deaths),
                borderColor: lineColor,
                backgroundColor: gradient,
                borderWidth: 2,
                pointBackgroundColor: lineColor,
                pointRadius: 4,
                pointHoverRadius: 6,
                fill: true,
                tension: 0.4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    grid: { color: gridColor },
                    ticks: { color: tickColor }
                },
                x: {
                    grid: { display: false },
                    ticks: { color: tickColor }
                }
            }
        }
    });
}

function initAnalyticsCharts() {
    if (analyticsChartsInitialized) return;
    analyticsChartsInitialized = true;

    const isNeon = document.body.getAttribute('data-theme') === 'neon-farm';
    const tickColor = isNeon ? '#8fa89a' : '#6F7568';
    const gridColor = isNeon ? 'rgba(61, 255, 154, 0.1)' : 'rgba(0,0,0,0.06)';
    const legendColor = isNeon ? '#eef7f1' : '#1E2B23';
    const tooltipBg = isNeon ? 'rgba(6, 20, 14, 0.94)' : 'rgba(30, 43, 35, 0.92)';
    const tooltipBorder = isNeon ? 'rgba(61, 255, 154, 0.35)' : 'rgba(46, 91, 67, 0.25)';

    const neonPalette = {
        emerald: '#3dff9a',
        cyan: '#3ee0ff',
        gold: '#f2b632',
        amber: '#ffb020',
        rose: '#ff4d6d',
        sage: '#6b8f7c'
    };

    const sharedTooltip = {
        backgroundColor: tooltipBg,
        titleColor: '#fff',
        bodyColor: isNeon ? '#c8e6d4' : '#f5f5f5',
        borderColor: tooltipBorder,
        borderWidth: 1,
        padding: 12,
        cornerRadius: 12,
        displayColors: true,
        boxPadding: 4
    };

    const sharedLegend = {
        labels: {
            color: legendColor,
            usePointStyle: true,
            pointStyle: 'circle',
            padding: 16,
            font: { family: 'Figtree', size: 12, weight: '600' }
        }
    };

    const axisDefaults = {
        y: {
            beginAtZero: true,
            border: { display: false },
            grid: { color: gridColor, drawBorder: false },
            ticks: {
                color: tickColor,
                font: { family: 'Figtree', size: 11 },
                padding: 8
            }
        },
        x: {
            border: { display: false },
            grid: { display: false },
            ticks: {
                color: tickColor,
                font: { family: 'Figtree', size: 11 },
                maxRotation: 0,
                autoSkipPadding: 8
            }
        }
    };

    const batchData = <?php echo json_encode($chartsData['batch_overview'] ?? []); ?>;
    const resStatusData = <?php echo json_encode($chartsData['reservations_by_status'] ?? []); ?>;
    const salesData = <?php echo json_encode($chartsData['sales_trend'] ?? []); ?>;
    const mortData = <?php echo json_encode($chartsData['mortality_trend'] ?? []); ?>;

    // 1) Flock Sales Pulse — smooth dual area line
    const salesCtx = document.getElementById('salesTrendChart').getContext('2d');
    const salesGrad = salesCtx.createLinearGradient(0, 0, 0, 280);
    salesGrad.addColorStop(0, isNeon ? 'rgba(62, 224, 255, 0.35)' : 'rgba(13, 110, 253, 0.22)');
    salesGrad.addColorStop(1, isNeon ? 'rgba(62, 224, 255, 0.01)' : 'rgba(13, 110, 253, 0.02)');
    const deliveredGrad = salesCtx.createLinearGradient(0, 0, 0, 280);
    deliveredGrad.addColorStop(0, isNeon ? 'rgba(61, 255, 154, 0.28)' : 'rgba(25, 135, 84, 0.2)');
    deliveredGrad.addColorStop(1, isNeon ? 'rgba(61, 255, 154, 0.01)' : 'rgba(25, 135, 84, 0.02)');

    new Chart(salesCtx, {
        type: 'line',
        data: {
            labels: salesData.map(d => d.month_label),
            datasets: [{
                label: 'Net Reservation Sales (₱)',
                data: salesData.map(d => d.net_sales),
                borderColor: isNeon ? neonPalette.cyan : '#0d6efd',
                backgroundColor: salesGrad,
                borderWidth: 2.5,
                pointBackgroundColor: isNeon ? neonPalette.cyan : '#0d6efd',
                pointBorderColor: isNeon ? '#0c1c16' : '#fff',
                pointBorderWidth: 2,
                pointRadius: 4,
                pointHoverRadius: 7,
                fill: true,
                tension: 0.45
            }, {
                label: 'Delivered / Harvested (₱)',
                data: salesData.map(d => d.delivered_sales),
                borderColor: isNeon ? neonPalette.emerald : '#198754',
                backgroundColor: deliveredGrad,
                borderWidth: 2.5,
                pointBackgroundColor: isNeon ? neonPalette.emerald : '#198754',
                pointBorderColor: isNeon ? '#0c1c16' : '#fff',
                pointBorderWidth: 2,
                pointRadius: 4,
                pointHoverRadius: 7,
                fill: true,
                tension: 0.45
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: { mode: 'index', intersect: false },
            plugins: {
                legend: { position: 'bottom', ...sharedLegend },
                tooltip: {
                    ...sharedTooltip,
                    callbacks: {
                        label: (ctx) => ` ${ctx.dataset.label}: ₱${Number(ctx.parsed.y || 0).toLocaleString('en-PH', { minimumFractionDigits: 2 })}`
                    }
                }
            },
            scales: {
                ...axisDefaults,
                y: {
                    ...axisDefaults.y,
                    ticks: {
                        ...axisDefaults.y.ticks,
                        callback: (v) => '₱' + Number(v).toLocaleString('en-PH', { notation: 'compact' })
                    }
                }
            }
        }
    });

    // 2) Daily Mortality Spike — rounded neon bars
    const analyticsMortCtx = document.getElementById('analyticsMortalityChart').getContext('2d');
    const mortBarGrad = analyticsMortCtx.createLinearGradient(0, 0, 0, 280);
    mortBarGrad.addColorStop(0, isNeon ? 'rgba(255, 77, 109, 0.95)' : 'rgba(220, 53, 69, 0.9)');
    mortBarGrad.addColorStop(1, isNeon ? 'rgba(255, 77, 109, 0.25)' : 'rgba(220, 53, 69, 0.35)');

    new Chart(analyticsMortCtx, {
        type: 'bar',
        data: {
            labels: mortData.map(d => d.formatted_date),
            datasets: [{
                label: 'Birds Lost',
                data: mortData.map(d => d.total_deaths),
                backgroundColor: mortBarGrad,
                borderColor: isNeon ? 'rgba(255, 141, 163, 0.65)' : 'rgba(220, 53, 69, 0.8)',
                borderWidth: 1,
                borderRadius: 10,
                borderSkipped: false,
                maxBarThickness: 28
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: {
                    ...sharedTooltip,
                    callbacks: {
                        label: (ctx) => ` ${ctx.parsed.y} bird(s) lost`
                    }
                }
            },
            scales: axisDefaults
        }
    });

    // 3) Booking Status Mix — ring chart (pipeline)
    const resStatusCtx = document.getElementById('reservationsStatusChart').getContext('2d');
    const statusColors = isNeon
        ? [neonPalette.emerald, neonPalette.cyan, neonPalette.gold, neonPalette.amber, neonPalette.rose, neonPalette.sage]
        : ['#198754', '#0d6efd', '#ffc107', '#fd7e14', '#dc3545', '#6c757d'];

    new Chart(resStatusCtx, {
        type: 'doughnut',
        data: {
            labels: resStatusData.map(d => String(d.status || 'unknown').replace(/_/g, ' ').toUpperCase()),
            datasets: [{
                data: resStatusData.map(d => d.count),
                backgroundColor: statusColors,
                borderWidth: isNeon ? 3 : 2,
                borderColor: isNeon ? '#0a1612' : '#fff',
                hoverOffset: 10,
                hoverBorderColor: isNeon ? neonPalette.emerald : '#2E5B43'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            cutout: '68%',
            plugins: {
                legend: {
                    position: 'right',
                    ...sharedLegend
                },
                tooltip: {
                    ...sharedTooltip,
                    callbacks: {
                        label: (ctx) => {
                            const total = ctx.dataset.data.reduce((a, b) => a + Number(b || 0), 0) || 1;
                            const val = Number(ctx.parsed || 0);
                            const pct = ((val / total) * 100).toFixed(1);
                            return ` ${ctx.label}: ${val} booking(s) (${pct}%)`;
                        }
                    }
                }
            }
        }
    });

    // 4) Live Headcount vs Loss — horizontal flock bars
    const batchCtx = document.getElementById('batchOverviewChart').getContext('2d');
    new Chart(batchCtx, {
        type: 'bar',
        data: {
            labels: batchData.map(d => d.batch_code),
            datasets: [{
                label: 'Live Birds',
                data: batchData.map(d => d.current_quantity),
                backgroundColor: isNeon ? 'rgba(61, 255, 154, 0.75)' : 'rgba(25, 135, 84, 0.85)',
                borderColor: isNeon ? neonPalette.emerald : '#198754',
                borderWidth: 1,
                borderRadius: 8,
                borderSkipped: false,
                maxBarThickness: 22
            }, {
                label: 'Mortality (lost)',
                data: batchData.map(d => d.total_mortality),
                backgroundColor: isNeon ? 'rgba(255, 77, 109, 0.7)' : 'rgba(220, 53, 69, 0.8)',
                borderColor: isNeon ? neonPalette.rose : '#dc3545',
                borderWidth: 1,
                borderRadius: 8,
                borderSkipped: false,
                maxBarThickness: 22
            }]
        },
        options: {
            indexAxis: 'y',
            responsive: true,
            maintainAspectRatio: false,
            interaction: { mode: 'index', intersect: false },
            plugins: {
                legend: { position: 'bottom', ...sharedLegend },
                tooltip: {
                    ...sharedTooltip,
                    callbacks: {
                        label: (ctx) => ` ${ctx.dataset.label}: ${Number(ctx.parsed.x || 0).toLocaleString()} birds`
                    }
                }
            },
            scales: {
                x: {
                    beginAtZero: true,
                    stacked: false,
                    border: { display: false },
                    grid: { color: gridColor, drawBorder: false },
                    ticks: {
                        color: tickColor,
                        font: { family: 'Figtree', size: 11 },
                        callback: (v) => Number(v).toLocaleString()
                    }
                },
                y: {
                    border: { display: false },
                    grid: { display: false },
                    ticks: {
                        color: tickColor,
                        font: { family: 'Figtree', size: 11, weight: '600' }
                    }
                }
            }
        }
    });
}

function toggleAlert(minimize) {
    const fullAlert = document.getElementById('fullAlert');
    const minimizedAlert = document.getElementById('minimizedAlert');
    if (!fullAlert || !minimizedAlert) return;
    
    if (minimize) {
        fullAlert.classList.add('d-none');
        minimizedAlert.classList.remove('d-none');
        sessionStorage.setItem('healthAlertMinimized', 'true');
    } else {
        fullAlert.classList.remove('d-none');
        minimizedAlert.classList.add('d-none');
        sessionStorage.setItem('healthAlertMinimized', 'false');
    }
}

document.addEventListener('DOMContentLoaded', function() {
    if (sessionStorage.getItem('healthAlertMinimized') === 'true') {
        toggleAlert(true);
    }
    
    // Initialize Dashboard charts immediately on load
    initDashboardCharts();
});
</script>

<?php include('footer.php'); ?>
