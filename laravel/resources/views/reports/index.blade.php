@extends('layouts.app')

@section('title', 'Centralized Reports')

@section('content')
<style>
    .report-tabs .nav-link {
        color: var(--brand-dark, #334155);
        font-weight: 600;
        border-radius: 10px;
        padding: 9px 15px;
        font-size: 0.9rem;
        transition: all 0.2s ease;
        white-space: nowrap;
    }
    .report-tabs .nav-link.active {
        background-color: var(--brand-dark, #334155) !important;
        color: #fff !important;
        box-shadow: 0 4px 10px rgba(12, 56, 46, 0.25);
    }
    .filter-card {
        background: #f8f9fa;
        border: 1px solid #e9ecef;
    }
    .report-kpi-card {
        transition: transform 0.2s ease, box-shadow 0.2s ease;
    }
    .report-kpi-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 15px rgba(0,0,0,0.08) !important;
    }

    @media print {
        @page { size: landscape; margin: 10mm; }
        .sidebar, .navbar, nav, header, footer, .btn, .nav-pills, .filter-card, .no-print {
            display: none !important;
        }
        body, .main-content, .container-fluid {
            background: #fff !important;
            color: #000 !important;
            margin: 0 !important;
            padding: 0 !important;
            width: 100% !important;
        }
        .card {
            box-shadow: none !important;
            border: 1px solid #dee2e6 !important;
            page-break-inside: avoid;
            margin-bottom: 20px !important;
        }
        .table {
            width: 100% !important;
            border-collapse: collapse !important;
        }
        .table th, .table td {
            border: 1px solid #dee2e6 !important;
            padding: 6px 8px !important;
            font-size: 10pt !important;
        }
        .badge {
            border: 1px solid #666 !important;
            color: #000 !important;
            background: #fff !important;
        }
        .print-header {
            display: block !important;
            margin-bottom: 20px;
            border-bottom: 2px solid #334155;
            padding-bottom: 10px;
        }
        .tab-content > .tab-pane {
            display: block !important;
            opacity: 1 !important;
            visibility: visible !important;
        }
        .tab-content > .tab-pane:not(.active) {
            display: none !important;
        }
    }
    @media screen {
        .print-header {
            display: none;
        }
    }
</style>

<!-- Official Print Header -->
<div class="print-header">
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h2 class="mb-0 fw-bold" style="color: #334155;">ALQUIZAR POULTRY FARM</h2>
            <h5 class="mb-1 text-secondary">{{ $tabTitles[$activeTab] ?? 'Centralized Operations Report' }}</h5>
            <small class="text-muted">Chapter 1 Broiler Production, Flock Health, Feed, Inventory, Reservations & Financials</small>
        </div>
        <div class="text-end">
            <div><strong>Generated:</strong> {{ date('M d, Y h:i A') }}</div>
            <div><strong>Period:</strong> {{ $dateFrom ? date('M d, Y', strtotime($dateFrom)) : 'Start' }} &mdash; {{ $dateTo ? date('M d, Y', strtotime($dateTo)) : 'Present' }}</div>
            @if($batchFilter)
                @php
                    $bCode = 'Batch #' . $batchFilter;
                    foreach ($batchesList as $b) { if ($b->batch_id == $batchFilter) { $bCode = $b->batch_code; break; } }
                @endphp
                <div><strong>Filtered Batch:</strong> {{ $bCode }}</div>
            @endif
        </div>
    </div>
</div>

<!-- Screen Header -->
<div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4 no-print">
    <div>
        <h3 class="mb-0 fw-bold"><i class="fas fa-file-invoice me-2 text-success"></i>Centralized Farm Reports & Auditing</h3>
        <small class="text-muted">Alquizar Poultry Farm &mdash; Broiler Batches, Health, Feed, Inventory, Reservations, Returns, Expenses & Profitability</small>
    </div>
    <div class="d-flex gap-2">
        <button class="btn btn-outline-secondary rounded-pill btn-sm px-3 shadow-sm" onclick="window.print()">
            <i class="fas fa-print me-1"></i>Print Report
        </button>
    </div>
</div>

<!-- Global Date & Batch Filter Bar -->
<div class="card p-3 rounded-4 shadow-sm mb-4 filter-card no-print">
    <form method="GET" action="{{ route('reports.index') }}" class="row g-2 align-items-center">
        <input type="hidden" name="tab" id="activeTabInput" value="{{ $activeTab }}">
        
        <!-- Preset Date Buttons -->
        <div class="col-12 col-xl-4 col-lg-5">
            <div class="btn-group btn-group-sm w-100" role="group">
                <a href="{{ route('reports.index', array_merge(request()->except('preset'), ['preset' => 'all', 'tab' => $activeTab])) }}" class="btn {{ $datePreset === 'all' ? 'btn-success' : 'btn-outline-success' }}">All Time</a>
                <a href="{{ route('reports.index', array_merge(request()->except('preset'), ['preset' => 'today', 'tab' => $activeTab])) }}" class="btn {{ $datePreset === 'today' ? 'btn-success' : 'btn-outline-success' }}">Today</a>
                <a href="{{ route('reports.index', array_merge(request()->except('preset'), ['preset' => 'this_week', 'tab' => $activeTab])) }}" class="btn {{ $datePreset === 'this_week' ? 'btn-success' : 'btn-outline-success' }}">This Week</a>
                <a href="{{ route('reports.index', array_merge(request()->except('preset'), ['preset' => 'this_month', 'tab' => $activeTab])) }}" class="btn {{ $datePreset === 'this_month' ? 'btn-success' : 'btn-outline-success' }}">This Month</a>
            </div>
        </div>

        <!-- Custom Date Range -->
        <div class="col-6 col-xl-2 col-lg-2">
            <div class="input-group input-group-sm">
                <span class="input-group-text">From</span>
                <input type="date" name="date_from" class="form-control" value="{{ $dateFrom ?? '' }}">
            </div>
        </div>

        <div class="col-6 col-xl-2 col-lg-2">
            <div class="input-group input-group-sm">
                <span class="input-group-text">To</span>
                <input type="date" name="date_to" class="form-control" value="{{ $dateTo ?? '' }}">
            </div>
        </div>

        <!-- Batch Filter Dropdown -->
        <div class="col-6 col-xl-2 col-lg-2">
            <select name="batch_id" class="form-select form-select-sm">
                <option value="">-- All Batches --</option>
                @foreach($batchesList as $b)
                    <option value="{{ $b->batch_id }}" {{ $batchFilter == $b->batch_id ? 'selected' : '' }}>
                        {{ $b->batch_code }} ({{ ucfirst($b->status) }})
                    </option>
                @endforeach
            </select>
        </div>

        <!-- Status Filter Dropdown -->
        <div class="col-6 col-xl-1 col-lg-1">
            <select name="status" class="form-select form-select-sm">
                <option value="">-- Status --</option>
                <option value="active" {{ $statusFilter === 'active' ? 'selected' : '' }}>Active</option>
                <option value="harvested" {{ $statusFilter === 'harvested' ? 'selected' : '' }}>Harvested</option>
                <option value="closed" {{ $statusFilter === 'closed' ? 'selected' : '' }}>Closed</option>
                <option value="fulfilled" {{ $statusFilter === 'fulfilled' ? 'selected' : '' }}>Fulfilled</option>
                <option value="cancelled" {{ $statusFilter === 'cancelled' ? 'selected' : '' }}>Cancelled</option>
            </select>
        </div>

        <!-- Filter Submit Button -->
        <div class="col-12 col-xl-1 col-lg-12 text-end">
            <button type="submit" class="btn btn-success btn-sm w-100"><i class="fas fa-filter me-1"></i>Filter</button>
        </div>
    </form>
</div>

<!-- High-Level Financial KPI Bar -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="p-3 bg-white rounded-4 shadow-sm border-start border-4 border-success h-100 report-kpi-card">
            <small class="text-muted text-uppercase fw-bold">Total Contract Value</small>
            <h4 class="mb-0 fw-bold text-success">₱{{ number_format($financialReport['net_sales'], 2) }}</h4>
            <small class="text-muted">Total Active/Fulfilled</small>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="p-3 bg-white rounded-4 shadow-sm border-start border-4 border-info h-100 report-kpi-card">
            <small class="text-muted text-uppercase fw-bold">Advance Payments</small>
            <h4 class="mb-0 fw-bold text-info">₱{{ number_format($financialReport['advance_payments'] ?? 0, 2) }}</h4>
            <small class="text-muted">Deposits on bookings</small>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="p-3 bg-white rounded-4 shadow-sm border-start border-4 border-danger h-100 report-kpi-card">
            <small class="text-muted text-uppercase fw-bold">Total Expenses</small>
            <h4 class="mb-0 fw-bold text-danger">₱{{ number_format($financialReport['total_expenses'], 2) }}</h4>
            <small class="text-muted">Feed, Meds, Waste, Operations</small>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="p-3 bg-white rounded-4 shadow-sm border-start border-4 border-primary h-100 report-kpi-card">
            <small class="text-muted text-uppercase fw-bold">Net Operating Profit</small>
            <h4 class="mb-0 fw-bold {{ $financialReport['net_profit'] >= 0 ? 'text-primary' : 'text-danger' }}">
                ₱{{ number_format($financialReport['net_profit'], 2) }}
            </h4>
            <small class="text-muted">Net Revenue &minus; Expenses</small>
        </div>
    </div>
</div>

<!-- Report Category Tabs -->
<ul class="nav nav-pills report-tabs bg-white p-2 rounded-4 shadow-sm mb-4 flex-wrap gap-1 no-print" id="reportTab" role="tablist">
    <li class="nav-item">
        <button class="nav-link {{ $activeTab === 'production' ? 'active' : '' }}" onclick="setTab('production')" data-bs-toggle="pill" data-bs-target="#production-tab" type="button">
            <i class="fas fa-feather-pointed me-1"></i>Broiler Batches
        </button>
    </li>
    <li class="nav-item">
        <button class="nav-link {{ $activeTab === 'mortality' ? 'active' : '' }}" onclick="setTab('mortality')" data-bs-toggle="pill" data-bs-target="#mortality-tab" type="button">
            <i class="fas fa-skull me-1"></i>Mortality
        </button>
    </li>
    <li class="nav-item">
        <button class="nav-link {{ $activeTab === 'health' ? 'active' : '' }}" onclick="setTab('health')" data-bs-toggle="pill" data-bs-target="#health-tab" type="button">
            <i class="fas fa-heart-pulse me-1"></i>Health & Meds
        </button>
    </li>
    <li class="nav-item">
        <button class="nav-link {{ $activeTab === 'feed' ? 'active' : '' }}" onclick="setTab('feed')" data-bs-toggle="pill" data-bs-target="#feed-tab" type="button">
            <i class="fas fa-wheat-awn me-1"></i>Feed Consumption
        </button>
    </li>
    <li class="nav-item">
        <button class="nav-link {{ $activeTab === 'inventory' ? 'active' : '' }}" onclick="setTab('inventory')" data-bs-toggle="pill" data-bs-target="#inventory-tab" type="button">
            <i class="fas fa-warehouse me-1"></i>Stock Inventory
        </button>
    </li>
    <li class="nav-item">
        <button class="nav-link {{ $activeTab === 'reservations' ? 'active' : '' }}" onclick="setTab('reservations')" data-bs-toggle="pill" data-bs-target="#reservations-tab" type="button">
            <i class="fas fa-calendar-check me-1"></i>Reservations & Pickups
        </button>
    </li>
    <li class="nav-item">
        <button class="nav-link {{ $activeTab === 'returns' ? 'active' : '' }}" onclick="setTab('returns')" data-bs-toggle="pill" data-bs-target="#returns-tab" type="button">
            <i class="fas fa-arrow-rotate-left me-1"></i>Returns & Rejections
        </button>
    </li>
    <li class="nav-item">
        <button class="nav-link {{ $activeTab === 'expenses' ? 'active' : '' }}" onclick="setTab('expenses')" data-bs-toggle="pill" data-bs-target="#expenses-tab" type="button">
            <i class="fas fa-wallet me-1"></i>Expenses Ledger
        </button>
    </li>
    <li class="nav-item">
        <button class="nav-link {{ $activeTab === 'financial' ? 'active' : '' }}" onclick="setTab('financial')" data-bs-toggle="pill" data-bs-target="#financial-tab" type="button">
            <i class="fas fa-chart-line me-1"></i>Financial Analytics
        </button>
    </li>
    <li class="nav-item">
        <button class="nav-link {{ $activeTab === 'audit' ? 'active' : '' }}" onclick="setTab('audit')" data-bs-toggle="pill" data-bs-target="#audit-tab" type="button">
            <i class="fas fa-clipboard-check me-1"></i>Worker Audit Trail
        </button>
    </li>
</ul>

<div class="tab-content" id="reportTabContent">

    <!-- 1. Broiler Batches Production Report -->
    <div class="tab-pane fade {{ $activeTab === 'production' ? 'show active' : '' }}" id="production-tab" role="tabpanel">
        <div class="card shadow-sm border-0 rounded-4">
            <div class="card-body p-4">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="fw-bold mb-0"><i class="fas fa-feather-pointed me-2 text-success"></i>Broiler Batch Production & Performance</h5>
                    <span class="badge bg-light text-dark border">{{ count($productionReport) }} Batches</span>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Batch Code</th>
                                <th>Arrival Date</th>
                                <th>Breed / House</th>
                                <th>Expected Harvest</th>
                                <th class="text-end">Stocked</th>
                                <th class="text-end">Current Count</th>
                                <th class="text-end">Mortality</th>
                                <th class="text-end">Mortality %</th>
                                <th class="text-end">Survival %</th>
                                <th class="text-end">Feed Used</th>
                                <th class="text-center">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($productionReport as $p)
                            <tr>
                                <td><strong>{{ $p->batch_code }}</strong></td>
                                <td>{{ date('M d, Y', strtotime($p->arrival_date)) }}</td>
                                <td>
                                    <div><small class="fw-semibold">{{ $p->breed ?: 'Cobb 500' }}</small></div>
                                    <small class="text-muted">{{ $p->house_pen ?: 'Pen 1' }}</small>
                                </td>
                                <td>
                                    {!! !empty($p->expected_harvest_date) ? date('M d, Y', strtotime($p->expected_harvest_date)) : '<span class="text-muted">-</span>' !!}
                                </td>
                                <td class="text-end">{{ number_format($p->initial_quantity) }}</td>
                                <td class="text-end fw-bold text-success">{{ number_format($p->current_quantity) }}</td>
                                <td class="text-end text-danger">{{ number_format($p->total_mortality) }}</td>
                                <td class="text-end {{ $p->mortality_rate_pct > 5 ? 'text-danger fw-bold' : '' }}">
                                    {{ $p->mortality_rate_pct }}%
                                </td>
                                <td class="text-end fw-semibold">{{ $p->survival_rate_pct }}%</td>
                                <td class="text-end">{{ number_format($p->total_feed_used, 1) }} kg</td>
                                <td class="text-center">
                                    <span class="badge bg-{{ $p->status === 'active' ? 'success' : 'secondary' }} rounded-pill px-3">
                                        {{ strtoupper($p->status) }}
                                    </span>
                                </td>
                            </tr>
                            @empty
                            <tr><td colspan="11" class="text-center py-4 text-muted">No broiler batch production data found for the selected filter.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- 2. Mortality Report -->
    <div class="tab-pane fade {{ $activeTab === 'mortality' ? 'show active' : '' }}" id="mortality-tab" role="tabpanel">
        <div class="card shadow-sm border-0 rounded-4">
            <div class="card-body p-4">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="fw-bold mb-0"><i class="fas fa-skull me-2 text-danger"></i>Flock Mortality Log Records</h5>
                    @php $totalLossCount = $mortalityReport->sum('loss_count'); @endphp
                    <span class="badge bg-danger text-white rounded-pill px-3">{{ number_format($totalLossCount) }} Total Loss</span>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Date</th>
                                <th>Batch</th>
                                <th class="text-end">Loss Count</th>
                                <th>Reason / Observation</th>
                                <th>Logged By</th>
                                <th>Timestamp</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($mortalityReport as $m)
                            <tr>
                                <td>{{ date('M d, Y', strtotime($m->date)) }}</td>
                                <td><strong>{{ $m->batch_code }}</strong></td>
                                <td class="text-end fw-bold text-danger">{{ number_format($m->loss_count) }} bird(s)</td>
                                <td>{{ $m->reason ?: 'Routine loss / Unspecified' }}</td>
                                <td><small>{{ $m->recorded_by_user ?? 'Staff' }}</small></td>
                                <td><small class="text-muted">{{ date('M d, Y H:i', strtotime($m->created_at)) }}</small></td>
                            </tr>
                            @empty
                            <tr><td colspan="6" class="text-center py-4 text-muted">No mortality records found for this period.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- 3. Flock Health & Medication Report -->
    <div class="tab-pane fade {{ $activeTab === 'health' ? 'show active' : '' }}" id="health-tab" role="tabpanel">
        <div class="card shadow-sm border-0 rounded-4">
            <div class="card-body p-4">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="fw-bold mb-0"><i class="fas fa-heart-pulse me-2 text-danger"></i>Flock Health & Medication Records</h5>
                    <span class="badge bg-light text-dark border">{{ count($healthReport) }} Health Events</span>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Date</th>
                                <th>Batch</th>
                                <th>Medicine / Treatment</th>
                                <th>Type</th>
                                <th class="text-end">Quantity Used</th>
                                <th>Doctor Consultation</th>
                                <th>Diagnosis / Observation</th>
                                <th class="text-center">Status</th>
                                <th>Next Due Date</th>
                                <th>Logged By</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($healthReport as $h)
                                @php
                                    $hBadge = 'success';
                                    if (($h->status ?? '') === 'scheduled') $hBadge = 'warning text-dark';
                                    elseif (($h->status ?? '') === 'in_progress') $hBadge = 'info text-dark';
                                    elseif (($h->status ?? '') === 'cancelled') $hBadge = 'danger';
                                @endphp
                                <tr>
                                    <td>{{ date('M d, Y', strtotime($h->date)) }}</td>
                                    <td><strong>{{ $h->batch_code }}</strong></td>
                                    <td class="fw-semibold text-primary">{{ $h->medicine_name }}</td>
                                    <td>
                                        <span class="badge bg-{{ $h->type === 'vaccination' ? 'info text-dark' : 'primary' }} rounded-pill">
                                            {{ ucfirst($h->type ?? 'treatment') }}
                                        </span>
                                    </td>
                                    <td class="text-end">{{ $h->quantity_used ?? '-' }}</td>
                                    <td><small>{{ $h->doctor_consultation ?: '-' }}</small></td>
                                    <td>
                                        @if(!empty($h->diagnosis))<strong>{{ $h->diagnosis }}</strong><br>@endif
                                        <small class="text-muted">{{ $h->illness_observation ?: ($h->notes ?? 'None') }}</small>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge bg-{{ $hBadge }} rounded-pill px-2">{{ ucfirst(str_replace('_', ' ', $h->status ?? 'completed')) }}</span>
                                    </td>
                                    <td><small>{{ !empty($h->next_due_date) ? date('M d, Y', strtotime($h->next_due_date)) : '-' }}</small></td>
                                    <td><small>{{ $h->recorded_by_user ?? 'Staff' }}</small></td>
                                </tr>
                            @empty
                                <tr><td colspan="10" class="text-center py-4 text-muted">No health records found for this period.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- 4. Feed Consumption Report -->
    <div class="tab-pane fade {{ $activeTab === 'feed' ? 'show active' : '' }}" id="feed-tab" role="tabpanel">
        <div class="card shadow-sm border-0 rounded-4">
            <div class="card-body p-4">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="fw-bold mb-0"><i class="fas fa-wheat-awn me-2 text-warning"></i>Feed & Resource Consumption Report</h5>
                    @php
                        $totalFeedKg = $consumptionReport->sum('quantity_used');
                        $totalFeedCost = $consumptionReport->sum('total_cost');
                    @endphp
                    <div class="d-flex gap-2">
                        <span class="badge bg-warning text-dark rounded-pill px-3">{{ number_format($totalFeedKg, 1) }} kg Total</span>
                        <span class="badge bg-light text-dark border rounded-pill px-3">₱{{ number_format($totalFeedCost, 2) }} Total Cost</span>
                    </div>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Date</th>
                                <th>Batch</th>
                                <th>Feed Type</th>
                                <th>Stage</th>
                                <th class="text-end">Quantity Used</th>
                                <th>Unit</th>
                                <th class="text-end">Cost Snapshot</th>
                                <th class="text-end">Total Cost</th>
                                <th>Recorded By</th>
                                <th>Notes</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($consumptionReport as $c)
                            <tr>
                                <td>{{ date('M d, Y', strtotime($c->date)) }}</td>
                                <td><strong>{{ $c->batch_code }}</strong></td>
                                <td>{{ $c->feed_type }}</td>
                                <td><span class="badge bg-light text-dark border">{{ $c->feed_stage ?: 'Standard' }}</span></td>
                                <td class="text-end fw-bold text-success">{{ number_format($c->quantity_used, 2) }}</td>
                                <td>{{ $c->unit }}</td>
                                <td class="text-end">₱{{ number_format($c->unit_cost_snapshot, 2) }}</td>
                                <td class="text-end fw-bold">₱{{ number_format($c->total_cost, 2) }}</td>
                                <td><small>{{ $c->recorded_by_user ?? 'Staff' }}</small></td>
                                <td><small class="text-muted">{{ $c->notes ?? 'None' }}</small></td>
                            </tr>
                            @empty
                            <tr><td colspan="10" class="text-center py-4 text-muted">No consumption records found for this period.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- 5. Stock Inventory Report -->
    <div class="tab-pane fade {{ $activeTab === 'inventory' ? 'show active' : '' }}" id="inventory-tab" role="tabpanel">
        <div class="card shadow-sm border-0 rounded-4">
            <div class="card-body p-4">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="fw-bold mb-0"><i class="fas fa-warehouse me-2 text-primary"></i>Stock Inventory & Resource Levels</h5>
                    @php $totalInvVal = $inventoryReportItems->sum('total_value'); @endphp
                    <span class="badge bg-primary text-white rounded-pill px-3">Valuation: ₱{{ number_format($totalInvVal, 2) }}</span>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Item Name</th>
                                <th>Category</th>
                                <th class="text-end">Quantity on Hand</th>
                                <th>Unit</th>
                                <th class="text-end">Unit Cost</th>
                                <th class="text-end">Total Valuation</th>
                                <th class="text-center">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($inventoryReportItems as $item)
                                @php
                                    $badge = 'success';
                                    if ($item->stock_status === 'Out of Stock') $badge = 'danger';
                                    elseif ($item->stock_status === 'Low Stock') $badge = 'warning text-dark';
                                @endphp
                                <tr>
                                    <td><strong>{{ $item->item_name }}</strong></td>
                                    <td><span class="badge bg-light text-dark border">{{ ucfirst($item->category) }}</span></td>
                                    <td class="text-end fw-bold">{{ number_format($item->quantity, 2) }}</td>
                                    <td>{{ $item->unit }}</td>
                                    <td class="text-end">₱{{ number_format($item->unit_cost, 2) }}</td>
                                    <td class="text-end fw-bold text-success">₱{{ number_format($item->total_value, 2) }}</td>
                                    <td class="text-center">
                                        <span class="badge bg-{{ $badge }} rounded-pill px-3">{{ $item->stock_status }}</span>
                                    </td>
                                </tr>
                            @empty
                            <tr><td colspan="7" class="text-center py-4 text-muted">No inventory items found.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- 6. Broiler Reservations & Advance Bookings Report -->
    <div class="tab-pane fade {{ $activeTab === 'reservations' ? 'show active' : '' }}" id="reservations-tab" role="tabpanel">
        <div class="card shadow-sm border-0 rounded-4">
            <div class="card-body p-4">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="fw-bold mb-0"><i class="fas fa-calendar-check me-2 text-success"></i>Broiler Reservations, Pickups, Payments & Balances</h5>
                    @php
                        $totalReservedBirds = $reservationsReport->sum('reserved_quantity');
                        $totalAgreedAmt = $reservationsReport->sum('total_amount');
                    @endphp
                    <div class="d-flex gap-2">
                        <span class="badge bg-success text-white rounded-pill px-3">{{ number_format($totalReservedBirds) }} Birds</span>
                        <span class="badge bg-light text-dark border rounded-pill px-3">Total: ₱{{ number_format($totalAgreedAmt, 2) }}</span>
                    </div>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Reservation #</th>
                                <th>Customer</th>
                                <th>Batch</th>
                                <th class="text-end">Reserved Birds</th>
                                <th>Pickup Date</th>
                                <th class="text-end">Total Amount</th>
                                <th class="text-end">Advance Paid</th>
                                <th class="text-end">Balance Due</th>
                                <th class="text-end">Accepted</th>
                                <th class="text-end">Rejected</th>
                                <th class="text-center">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($reservationsReport as $res)
                                @php
                                    $resBadge = 'secondary';
                                    if ($res->status === 'active') $resBadge = 'primary';
                                    elseif ($res->status === 'fulfilled') $resBadge = 'success';
                                    elseif ($res->status === 'cancelled') $resBadge = 'danger';
                                    elseif ($res->status === 'expired') $resBadge = 'warning text-dark';
                                @endphp
                                <tr>
                                    <td><strong>{{ $res->reservation_number }}</strong></td>
                                    <td>
                                        <div><strong>{{ $res->customer_name }}</strong></div>
                                        <small class="text-muted">{{ $res->customer_phone ?? '' }}</small>
                                    </td>
                                    <td>{{ $res->batch_code ?? 'All / Unassigned' }}</td>
                                    <td class="text-end fw-bold">{{ number_format($res->reserved_quantity) }}</td>
                                    <td><strong>{{ date('M d, Y', strtotime($res->pickup_date)) }}</strong></td>
                                    <td class="text-end fw-bold">₱{{ number_format($res->total_amount, 2) }}</td>
                                    <td class="text-end text-success fw-semibold">₱{{ number_format($res->advance_payment, 2) }}</td>
                                    <td class="text-end text-danger fw-bold">₱{{ number_format($res->remaining_balance, 2) }}</td>
                                    <td class="text-end text-success fw-semibold">{{ number_format($res->accepted_quantity) }}</td>
                                    <td class="text-end text-danger fw-semibold">{{ number_format($res->rejected_quantity) }}</td>
                                    <td class="text-center">
                                        <span class="badge bg-{{ $resBadge }} rounded-pill px-3">{{ strtoupper($res->status) }}</span>
                                    </td>
                                </tr>
                            @empty
                            <tr><td colspan="11" class="text-center py-4 text-muted">No reservations found for this period.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- 7. Accepted, Returned & Rejected Chickens Report -->
    <div class="tab-pane fade {{ $activeTab === 'returns' ? 'show active' : '' }}" id="returns-tab" role="tabpanel">
        <div class="card shadow-sm border-0 rounded-4">
            <div class="card-body p-4">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="fw-bold mb-0"><i class="fas fa-arrow-rotate-left me-2 text-danger"></i>Accepted, Returned & Rejected Chickens Disposition</h5>
                    <span class="badge bg-danger text-white rounded-pill px-3">0 Total Returned/Rejected</span>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Return #</th>
                                <th>Order Ref</th>
                                <th>Customer Name</th>
                                <th>Return / Disposition Date</th>
                                <th class="text-end">Returned / Rejected Qty</th>
                                <th class="text-center">Condition Status</th>
                                <th>Reason / Notes</th>
                                <th>Processed By</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr><td colspan="8" class="text-center py-4 text-muted">No return or rejection records found for this period.</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- 8. Detailed Operational Expenses Ledger -->
    <div class="tab-pane fade {{ $activeTab === 'expenses' ? 'show active' : '' }}" id="expenses-tab" role="tabpanel">
        <div class="card shadow-sm border-0 rounded-4">
            <div class="card-body p-4">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="fw-bold mb-0"><i class="fas fa-wallet me-2 text-danger"></i>Operational Expenses Ledger</h5>
                    @php $totalExp = $expensesReport->sum('amount'); @endphp
                    <span class="badge bg-danger text-white rounded-pill px-3">Total: ₱{{ number_format($totalExp, 2) }}</span>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Date</th>
                                <th>Category</th>
                                <th>Description</th>
                                <th>Related Flock / Item</th>
                                <th class="text-end">Amount</th>
                                <th>Source</th>
                                <th>Recorded By</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($expensesReport as $exp)
                            <tr>
                                <td>{{ date('M d, Y', strtotime($exp->date)) }}</td>
                                <td><span class="badge bg-secondary text-uppercase px-2 py-1">{{ $exp->category }}</span></td>
                                <td>{{ $exp->description ?: '-' }}</td>
                                <td>
                                    @if(!empty($exp->batch_code))
                                        <span class="badge bg-light text-dark border"><i class="fas fa-feather-pointed me-1"></i>{{ $exp->batch_code }}</span>
                                    @elseif(!empty($exp->inventory_item_name))
                                        <span class="badge bg-light text-dark border"><i class="fas fa-box me-1"></i>{{ $exp->inventory_item_name }}</span>
                                    @else
                                        <span class="text-muted">-</span>
                                    @endif
                                </td>
                                <td class="text-end fw-bold text-danger">₱{{ number_format($exp->amount, 2) }}</td>
                                <td><small class="badge bg-light text-secondary border">{{ $exp->source_type ?? 'manual' }}</small></td>
                                <td><small>{{ $exp->recorded_by_user ?? 'Staff' }}</small></td>
                            </tr>
                            @empty
                            <tr><td colspan="7" class="text-center py-4 text-muted">No expense records found for the selected period.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- 9. Financial Analytics Report -->
    <div class="tab-pane fade {{ $activeTab === 'financial' ? 'show active' : '' }}" id="financial-tab" role="tabpanel">
        <div class="row g-4">
            <div class="col-12 col-lg-6">
                <div class="card shadow-sm border-0 rounded-4 h-100">
                    <div class="card-body p-4">
                        <h5 class="fw-bold mb-3"><i class="fas fa-wallet me-2 text-danger"></i>Expenses by Category</h5>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle">
                                <thead class="table-light">
                                    <tr>
                                        <th>Expense Category</th>
                                        <th class="text-end">Total (₱)</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($financialReport['expenses_by_category'] as $ec)
                                    <tr>
                                        <td><strong>{{ ucfirst($ec->category) }}</strong></td>
                                        <td class="text-end fw-bold text-danger">₱{{ number_format($ec->total, 2) }}</td>
                                    </tr>
                                    @empty
                                    <tr><td colspan="2" class="text-center py-3 text-muted">No expense records found.</td></tr>
                                    @endforelse
                                    <tr class="table-light">
                                        <td><strong>Total Operating Expenses</strong></td>
                                        <td class="text-end fw-bold text-danger">₱{{ number_format($financialReport['total_expenses'], 2) }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-12 col-lg-6">
                <div class="card shadow-sm border-0 rounded-4 h-100">
                    <div class="card-body p-4">
                        <h5 class="fw-bold mb-3"><i class="fas fa-coins me-2 text-success"></i>Financial Summary</h5>
                        <ul class="list-group list-group-flush">
                            <li class="list-group-item d-flex justify-content-between align-items-center py-3">
                                <span>Total Contract Value:</span>
                                <strong class="text-success fs-5">₱{{ number_format($financialReport['net_sales'], 2) }}</strong>
                            </li>
                            <li class="list-group-item d-flex justify-content-between align-items-center py-3">
                                <span>Advance Payments Collected:</span>
                                <strong class="text-info fs-5">₱{{ number_format($financialReport['advance_payments'] ?? 0, 2) }}</strong>
                            </li>
                            <li class="list-group-item d-flex justify-content-between align-items-center py-3">
                                <span>Completed / Fulfilled Value:</span>
                                <strong class="text-primary fs-5">₱{{ number_format($financialReport['completed_sales'], 2) }}</strong>
                            </li>
                            <li class="list-group-item d-flex justify-content-between align-items-center py-3">
                                <span>Total Operating Costs:</span>
                                <strong class="text-danger fs-5">&minus;₱{{ number_format($financialReport['total_expenses'], 2) }}</strong>
                            </li>
                            <li class="list-group-item d-flex justify-content-between align-items-center py-3 bg-light rounded-3 mt-2">
                                <span class="fw-bold">Net Profit / (Loss):</span>
                                <strong class="fs-4 {{ $financialReport['net_profit'] >= 0 ? 'text-success' : 'text-danger' }}">
                                    ₱{{ number_format($financialReport['net_profit'], 2) }}
                                </strong>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- 10. Worker Audit Trail Tab -->
    <div class="tab-pane fade {{ $activeTab === 'audit' ? 'show active' : '' }}" id="audit-tab" role="tabpanel">
        <div class="card shadow-sm border-0 rounded-4">
            <div class="card-body p-4">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="fw-bold mb-0"><i class="fas fa-clipboard-check me-2 text-primary"></i>Farm Operations & Worker Audit Trail</h5>
                    <span class="badge bg-light text-dark border">{{ count($workerReport) }} Actions Logged</span>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Date & Time</th>
                                <th>Worker</th>
                                <th>Batch</th>
                                <th>Task / Activity Type</th>
                                <th>Item / Resource</th>
                                <th class="text-end">Quantity</th>
                                <th>Remarks / Notes</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($workerReport as $act)
                            <tr>
                                <td>
                                    <div><small class="fw-bold">{{ date('M d, Y', strtotime($act->date)) }}</small></div>
                                    <small class="text-muted">{{ !empty($act->created_at) ? date('h:i A', strtotime($act->created_at)) : '' }}</small>
                                </td>
                                <td><strong>{{ $act->worker_name ?? 'Staff' }}</strong></td>
                                <td><strong>{{ $act->batch_code }}</strong></td>
                                <td><span class="badge bg-secondary rounded-pill">{{ $act->activity_type }}</span></td>
                                <td>{{ $act->item_name ?: '-' }}</td>
                                <td class="text-end fw-semibold">{{ $act->quantity > 0 ? number_format($act->quantity, 2) : '-' }}</td>
                                <td><small>{{ $act->description ?: 'None' }}</small></td>
                            </tr>
                            @empty
                            <tr><td colspan="7" class="text-center py-4 text-muted">No worker activity logs recorded yet.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

</div>
@endsection

@push('scripts')
<script>
function setTab(tabName) {
    document.getElementById('activeTabInput').value = tabName;
    const url = new URL(window.location);
    url.searchParams.set('tab', tabName);
    window.history.replaceState({}, '', url);
}
</script>
@endpush
