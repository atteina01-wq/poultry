@extends('layouts.app')

@section('title', 'Dashboard & Farm Analytics')

@push('styles')
<style>
    /* Enhanced Tab Navigation */
    .dashboard-tabs-wrapper {
        background-color: var(--surface, #FBF9F4);
        padding: 6px;
        border-radius: var(--radius-md, 14px);
        border: 1px solid var(--border, #DDD6C4);
        box-shadow: var(--shadow-sm, 0 4px 12px rgba(30, 43, 35, 0.05));
        display: inline-flex;
        gap: 4px;
    }
    .nav-pills .nav-link {
        color: var(--text-muted, #728076) !important;
        font-weight: 600;
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        border: 1px solid transparent;
        font-size: 14px;
        border-radius: var(--radius-sm, 8px);
        padding: 8px 20px;
    }
    .nav-pills .nav-link.active {
        background-color: var(--primary-soft, #DDECE3) !important;
        color: var(--primary, #2E5B43) !important;
        box-shadow: 0 2px 4px rgba(46, 91, 67, 0.1);
        border-color: rgba(46, 91, 67, 0.2);
    }
    .nav-pills .nav-link:not(.active):hover {
        color: var(--text, #1E2B23) !important;
        background-color: var(--surface-alt, #F4F0E6) !important;
    }

    /* Premium KPI Cards */
    .kpi-card {
        background: #ffffff;
        border-radius: var(--radius-md, 14px);
        border: 1px solid var(--border, #DDD6C4);
        padding: 24px;
        box-shadow: var(--shadow-sm, 0 4px 12px rgba(30, 43, 35, 0.05));
        transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1), box-shadow 0.3s ease;
        position: relative;
        overflow: hidden;
    }
    .kpi-card:hover {
        transform: translateY(-4px);
        box-shadow: 0 12px 24px rgba(60,50,30,0.08);
    }
    .kpi-title {
        font-size: 12.5px;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        color: var(--text-muted, #728076);
        margin-bottom: 6px;
    }
    .kpi-value {
        font-size: 28px;
        font-weight: 700;
        color: var(--text, #1E2B23);
        line-height: 1.2;
    }
    .kpi-subtitle {
        font-size: 13px;
        color: var(--text-muted, #728076);
        margin-top: 4px;
    }
    .kpi-icon-wrapper {
        width: 48px;
        height: 48px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 20px;
    }

    /* Activity Feed Styling */
    .activity-feed-wrapper {
        border-radius: var(--radius-md, 14px);
        background: linear-gradient(180deg, var(--surface, #FBF9F4) 0%, var(--surface-alt, #F4F0E6) 100%);
        border: 1px solid var(--border, #DDD6C4);
    }
    .timeline-item {
        position: relative;
        padding-left: 28px;
        padding-bottom: 20px;
        border-left: 2px solid var(--border, #DDD6C4);
        margin-left: 14px;
        transition: all 0.2s ease;
    }
    .timeline-item:last-child {
        border-left-color: transparent;
        padding-bottom: 0;
    }
    .timeline-item:hover {
        border-left-color: var(--primary, #2E5B43);
    }
    .timeline-dot {
        position: absolute;
        left: -6px;
        top: 4px;
        width: 10px;
        height: 10px;
        border-radius: 50%;
        border: 2px solid var(--surface, #FBF9F4);
    }

    /* Chart Containers */
    .chart-container-premium {
        background-color: var(--surface, #FBF9F4);
        border: 1px solid var(--border, #DDD6C4);
        border-radius: var(--radius-md, 14px);
        box-shadow: var(--shadow-sm, 0 4px 12px rgba(30, 43, 35, 0.05));
        padding: 24px;
    }
</style>
@endpush

@section('content')
<div class="container-fluid px-0">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
        <div>
            <h4 class="mb-1" style="color: var(--text, #1E2B23); font-weight: 600;">
                Welcome back, {{ auth()->user()->full_name ?? auth()->user()->username }}!
            </h4>
            <p class="mb-0 text-muted" style="font-size: 14px;">Here's what's happening at Alquizar Poultry Farm today.</p>
        </div>
        
        <div class="dashboard-tabs-wrapper">
            <ul class="nav nav-pills" id="pills-tab" role="tablist">
                <li class="nav-item">
                    <button class="nav-link active" id="btnDashboard" onclick="switchView('dashboard')" type="button">
                        <i class="fas fa-layer-group me-2"></i>Dashboard
                    </button>
                </li>
                <li class="nav-item">
                    <button class="nav-link" id="btnAnalytics" onclick="switchView('analytics')" type="button">
                        <i class="fas fa-chart-line me-2"></i>Analytics
                    </button>
                </li>
            </ul>
        </div>
    </div>

    <!-- DASHBOARD VIEW -->
    <div id="dashboardView">
        <!-- Health Alerts Section -->
        @if(!empty($healthAlerts))
        <div class="row mb-4" id="healthAlertContainer">
            <div class="col-12">
                <div class="card p-3 border-0" style="background-color: var(--danger-bg, #F5E1DD); border-left: 4px solid var(--danger, #B03A2E) !important; border-radius: 10px;">
                    <div class="d-flex justify-content-between align-items-start">
                        <div class="d-flex gap-3">
                            <i class="fas fa-exclamation-triangle text-danger mt-1 fs-5"></i>
                            <div>
                                <h6 class="text-danger mb-1 fw-bold">Critical Health Alert: Elevated Mortality Rate (&ge; 1.0%)</h6>
                                <p class="mb-2 small text-danger" style="opacity: 0.95;">The following active flocks require veterinary attention:</p>
                                <ul class="mb-0 small text-danger ps-3">
                                    @foreach($healthAlerts as $alert)
                                        <li>
                                            <strong>Batch {{ $alert['batch_code'] }}</strong>:
                                            {{ $alert['total_loss'] }} bird(s) lost in 24h
                                            ({{ $alert['mortality_percentage'] }}% of {{ number_format($alert['pre_loss_headcount']) }} heads)
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        @endif

        <!-- Top KPI Cards -->
        <div class="row g-4">
            <div class="col-12 col-sm-6 col-xl-3">
                <div class="kpi-card" style="border-top: 4px solid var(--primary, #2E5B43);">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <div class="kpi-title">Active Batches</div>
                            <div class="kpi-value text-primary">{{ $broilerBatches['total_active_batches'] }}</div>
                            <div class="kpi-subtitle">{{ number_format($broilerBatches['total_live_birds']) }} live birds</div>
                        </div>
                        <div class="kpi-icon-wrapper" style="background-color: var(--primary-soft, #DDECE3); color: var(--primary, #2E5B43);">
                            <i class="fas fa-layer-group"></i>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-12 col-sm-6 col-xl-3">
                <div class="kpi-card" style="border-top: 4px solid var(--danger, #B03A2E);">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <div class="kpi-title">Weekly Mortality</div>
                            <div class="kpi-value text-danger">{{ number_format($broilerBatches['week_mortality']) }}</div>
                            <div class="kpi-subtitle">birds lost (last 7 days)</div>
                        </div>
                        <div class="kpi-icon-wrapper" style="background-color: var(--danger-bg, #F5E1DD); color: var(--danger, #B03A2E);">
                            <i class="fas fa-skull"></i>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-12 col-sm-6 col-xl-3">
                <div class="kpi-card" style="border-top: 4px solid #0284c7;">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <div class="kpi-title">Reservations</div>
                            <div class="kpi-value">{{ $reservations['total'] }}</div>
                            <div class="kpi-subtitle"><span class="text-success fw-semibold">{{ $reservations['active'] }} active</span> &bull; {{ $reservations['fulfilled'] }} fulfilled</div>
                        </div>
                        <div class="kpi-icon-wrapper" style="background-color: #e0f2fe; color: #0284c7;">
                            <i class="fas fa-calendar-check"></i>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-12 col-sm-6 col-xl-3">
                <div class="kpi-card" style="border-top: 4px solid #d97706;">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <div class="kpi-title">Inventory Status</div>
                            <div class="kpi-value">{{ $genInv['total_items'] }}</div>
                            <div class="kpi-subtitle {{ $genInv['low_stock_items'] > 0 ? 'text-warning fw-bold' : '' }}">
                                {{ $genInv['low_stock_items'] }} low stock items
                            </div>
                        </div>
                        <div class="kpi-icon-wrapper" style="background-color: #fef3c7; color: #d97706;">
                            <i class="fas fa-warehouse"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Activity Stream and Headcount Summary -->
        <div class="row mt-4 g-4 align-items-stretch">
            <!-- Live Activity Stream -->
            <div class="col-md-8">
                <div class="card h-100 border-0 shadow-sm rounded-4 p-4 bg-white">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h5 class="fw-bold mb-0 text-dark">
                            <i class="fas fa-bolt text-warning me-2"></i>Live Operations Feed
                        </h5>
                        <a href="{{ route('activities.index') }}" class="btn btn-sm btn-outline-secondary rounded-pill px-3">View All</a>
                    </div>

                    <div class="activity-feed-wrapper p-3 flex-grow-1" style="min-height: 400px; max-height: 520px; overflow-y: auto;">
                        @forelse($recentActivities as $act)
                            @php
                                $color = match($act->event_type) {
                                    'reservation' => ($act->status_badge === 'cancelled' ? 'danger' : 'info'),
                                    'mortality' => 'danger',
                                    'health' => 'warning',
                                    'inventory' => 'info',
                                    'batch' => 'primary',
                                    'expense' => 'warning',
                                    default => 'secondary'
                                };
                            @endphp
                            <div class="timeline-item">
                                <div class="timeline-dot bg-{{ $color }}"></div>
                                <div class="bg-white p-3 rounded-3 shadow-sm border border-light-subtle">
                                    <div class="d-flex justify-content-between align-items-start mb-2">
                                        <span class="fw-semibold text-dark" style="font-size: 14px;">
                                            {{ $act->party_name }}
                                        </span>
                                        <span class="badge bg-{{ $color }} px-2 py-1 rounded-pill text-uppercase" style="font-size: 10px;">
                                            {{ $act->status_badge }}
                                        </span>
                                    </div>
                                    <p class="mb-2 text-secondary small">
                                        {{ $act->description }}
                                    </p>
                                    <div class="d-flex align-items-center gap-3 pt-2 border-top">
                                        <small class="text-muted" style="font-size: 11px;">
                                            <i class="far fa-clock me-1"></i>{{ date('M d, g:i A', strtotime($act->event_time)) }}
                                        </small>
                                        <small class="text-muted" style="font-size: 11px;">
                                            <i class="fas fa-hashtag me-1"></i>{{ $act->ref_code }}
                                        </small>
                                    </div>
                                </div>
                            </div>
                        @empty
                            <div class="text-center py-5 text-muted">
                                <i class="fas fa-clipboard-list fs-1 opacity-25 d-block mb-3"></i>
                                <h6 class="fw-medium">No recent operations</h6>
                                <p class="small mb-0">Operational activities will appear here once recorded.</p>
                            </div>
                        @endforelse
                    </div>
                </div>
            </div>

            <!-- Batch Headcount & Daily Mortality Trend -->
            <div class="col-md-4">
                <div class="card h-100 border-0 shadow-sm rounded-4 p-4 bg-white">
                    <div class="mb-4">
                        <h6 class="fw-bold mb-3 text-dark">
                            <i class="fas fa-layer-group text-primary me-2"></i>Current Broiler Counts
                        </h6>
                        <ul class="list-group list-group-flush rounded-3 border" style="font-size: 14px;">
                            @forelse($chartsData['batch_overview'] as $batch)
                                <li class="list-group-item d-flex justify-content-between align-items-center py-2 px-3 bg-light-subtle">
                                    <span class="fw-medium text-dark">Batch {{ $batch->batch_code }}</span>
                                    <span class="badge bg-primary rounded-pill px-3 py-1">{{ number_format($batch->current_quantity) }} birds</span>
                                </li>
                            @empty
                                <li class="list-group-item text-muted py-3 border-0 text-center">No active batches.</li>
                            @endforelse
                        </ul>
                    </div>

                    <div class="mb-4">
                        <h6 class="fw-bold mb-3 text-dark">
                            <i class="fas fa-skull text-danger me-2"></i>Cumulative Mortality
                        </h6>
                        <ul class="list-group list-group-flush rounded-3 border" style="font-size: 14px;">
                            @forelse($chartsData['batch_overview'] as $batch)
                                <li class="list-group-item d-flex justify-content-between align-items-center py-2 px-3 bg-light-subtle">
                                    <span class="fw-medium text-dark">Batch {{ $batch->batch_code }}</span>
                                    <span class="badge bg-danger rounded-pill px-3 py-1">-{{ number_format($batch->total_mortality) }} lost</span>
                                </li>
                            @empty
                                <li class="list-group-item text-muted py-3 border-0 text-center">No active batches.</li>
                            @endforelse
                        </ul>
                    </div>

                    <div class="pt-3 border-top">
                        <h6 class="fw-bold mb-3 text-dark">
                            <i class="fas fa-chart-line text-primary me-2"></i>Daily Mortality Trend (14 Days)
                        </h6>
                        <div style="height: 200px; width: 100%;">
                            <canvas id="mortalityTrendChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ANALYTICS TAB VIEW -->
    <div id="analyticsView" class="d-none">
        <div class="row g-4 mb-4">
            <div class="col-md-6">
                <div class="chart-container-premium h-100 bg-white">
                    <h5 class="fw-bold mb-3 text-dark">
                        <i class="fas fa-chart-line text-primary me-2"></i>Reservation Sales Trend (6 Months)
                    </h5>
                    <div style="height: 300px; width: 100%;">
                        <canvas id="salesTrendChart"></canvas>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="chart-container-premium h-100 bg-white">
                    <h5 class="fw-bold mb-3 text-dark">
                        <i class="fas fa-skull text-danger me-2"></i>Daily Mortality Trend (14 Days)
                    </h5>
                    <div style="height: 300px; width: 100%;">
                        <canvas id="analyticsMortalityChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    function switchView(viewName) {
        const dashView = document.getElementById('dashboardView');
        const analView = document.getElementById('analyticsView');
        const btnDash = document.getElementById('btnDashboard');
        const btnAnal = document.getElementById('btnAnalytics');

        if (viewName === 'analytics') {
            dashView.classList.add('d-none');
            analView.classList.remove('d-none');
            btnDash.classList.remove('active');
            btnAnal.classList.add('active');
        } else {
            analView.classList.add('d-none');
            dashView.classList.remove('d-none');
            btnAnal.classList.remove('active');
            btnDash.classList.add('active');
        }
    }

    // Chart.js Data Initialization
    const mortData = @json($chartsData['mortality_trend']);
    const salesData = @json($chartsData['sales_trend']);

    // 1. Dashboard Mini Mortality Trend
    const ctxMort = document.getElementById('mortalityTrendChart');
    if (ctxMort && mortData.length > 0) {
        new Chart(ctxMort, {
            type: 'line',
            data: {
                labels: mortData.map(d => d.formatted_date),
                datasets: [{
                    label: 'Birds Lost',
                    data: mortData.map(d => d.total_deaths),
                    borderColor: '#b03a2e',
                    backgroundColor: 'rgba(176, 58, 46, 0.1)',
                    fill: true,
                    tension: 0.3
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: { y: { beginAtZero: true } }
            }
        });
    }

    // 2. Sales Trend Chart
    const ctxSales = document.getElementById('salesTrendChart');
    if (ctxSales && salesData.length > 0) {
        new Chart(ctxSales, {
            type: 'bar',
            data: {
                labels: salesData.map(d => d.month_label),
                datasets: [{
                    label: 'Net Sales (₱)',
                    data: salesData.map(d => d.net_sales),
                    backgroundColor: '#2e5b43',
                    borderRadius: 6
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: { y: { beginAtZero: true } }
            }
        });
    }

    // 3. Analytics Mortality Chart
    const ctxAnalMort = document.getElementById('analyticsMortalityChart');
    if (ctxAnalMort && mortData.length > 0) {
        new Chart(ctxAnalMort, {
            type: 'line',
            data: {
                labels: mortData.map(d => d.formatted_date),
                datasets: [{
                    label: 'Birds Lost',
                    data: mortData.map(d => d.total_deaths),
                    borderColor: '#b03a2e',
                    backgroundColor: 'rgba(176, 58, 46, 0.15)',
                    fill: true,
                    tension: 0.3
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: { y: { beginAtZero: true } }
            }
        });
    }
</script>
@endpush
