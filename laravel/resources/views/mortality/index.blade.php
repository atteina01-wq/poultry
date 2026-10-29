@extends('layouts.app')

@section('title', 'Mortality & Batch Tracker')

@section('content')
<div class="page-grid">
    <!-- Left Column: Form & Summary Card -->
    <div>
        <div class="card form-container h-100">
            <div class="card-body d-flex flex-column">
                <h3 class="section-title"><i class="fas fa-clipboard-list text-primary"></i>Record Mortality</h3>

                @if(session('success'))
                    <div class="alert alert-success d-flex align-items-center gap-2 shadow-sm rounded-3 py-2 px-3 mb-3 border border-success-subtle">
                        <i class="fas fa-check-circle text-success fs-5"></i>
                        <div>
                            <div class="fw-semibold text-success-emphasis">Mortality Entry Recorded</div>
                            <div class="small text-muted">{{ session('success') }}</div>
                        </div>
                    </div>
                @endif
                @if(session('error'))
                    <div class="alert alert-danger d-flex align-items-center gap-2 shadow-sm rounded-3 py-2 px-3 mb-3 border border-danger-subtle">
                        <i class="fas fa-circle-exclamation text-danger fs-5"></i>
                        <div>
                            <div class="fw-semibold text-danger-emphasis">Unable to Record Mortality</div>
                            <div class="small text-muted">{{ session('error') }}</div>
                        </div>
                    </div>
                @endif

                <form method="POST" action="{{ route('mortality.store') }}" id="mortalityForm" onsubmit="event.preventDefault(); initiateMortalityConfirmation();">
                    @csrf
                    <input type="hidden" name="save_mortality" value="1">
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold">Select Broiler Batch</label>
                        <select name="batch_id" id="batchSelect" class="form-select" required onchange="calculateAutoLoss()">
                            <option value="">-- Choose Batch --</option>
                            @foreach($activeBatches as $b)
                                @php
                                    $pen = !empty($b->house_pen) ? ' - ' . $b->house_pen : '';
                                    $cost = $b->chick_unit_cost ?? 38.00;
                                @endphp
                                <option value="{{ $b->batch_id }}" data-qty="{{ $b->current_quantity }}" data-arrival="{{ $b->arrival_date }}" data-cost="{{ $cost }}">
                                    {{ $b->batch_code }}{{ $pen }} ({{ number_format($b->current_quantity) }} heads left)
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">Number of Mortality (Heads)</label>
                        <input type="number" name="quantity" id="mortQty" class="form-control" placeholder="e.g., 5" min="1" required oninput="calculateAutoLoss()">
                        <div id="qtyHeadcountFeedback" class="small mt-1"></div>
                        <small class="text-muted">Enter the number of broilers lost. Headcount will be safely deducted from flock inventory.</small>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">Reason / Cause</label>
                        <input type="text" name="reason" id="mortReason" class="form-control" placeholder="e.g., Heat stress, Smothering, Respiratory" required>
                    </div>

                    <div class="mb-3">
                        <div class="d-flex justify-content-between align-items-center">
                            <label class="form-label fw-bold mb-0">Financial Loss Impact (₱)</label>
                            <span class="badge bg-light text-primary border" id="autoCalcBadge">Auto-Calculated</span>
                        </div>
                        <input type="number" step="0.01" name="financial_value" id="financialValueInput" class="form-control mt-1" placeholder="0.00">
                        <div id="lossBreakdownText" class="small text-muted mt-1">Estimated loss based on Day-Old Chick cost + accumulated feed & meds.</div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">Date of Occurrence</label>
                        <input type="date" name="date" id="mortDate" class="form-control" value="{{ date('Y-m-d') }}" required onchange="calculateAutoLoss()">
                    </div>

                    <div class="form-actions-sticky mt-auto d-grid">
                        <button type="submit" id="saveMortalityBtn" class="btn btn-danger w-100 py-2 fw-semibold shadow-sm">
                            <i class="fas fa-clipboard-check me-2"></i>Review & Record Mortality
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Mortality Summary Card -->
        <div class="card p-4 shadow-sm border-0 rounded-4 mt-4">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="text-primary fw-bold mb-0"><i class="fas fa-chart-pie me-2"></i>Mortality Summary</h5>
                <select id="summaryPeriodSelect" class="form-select form-select-sm rounded-pill" style="width: auto;" onchange="updateSummaryPeriod(this.value)">
                    <option value="daily" {{ $summaryPeriod == 'daily' ? 'selected' : '' }}>Daily</option>
                    <option value="weekly" {{ $summaryPeriod == 'weekly' ? 'selected' : '' }}>Weekly</option>
                    <option value="monthly" {{ $summaryPeriod == 'monthly' ? 'selected' : '' }}>Monthly</option>
                    <option value="yearly" {{ $summaryPeriod == 'yearly' ? 'selected' : '' }}>Yearly</option>
                </select>
            </div>
            <div class="table-responsive custom-scrollbar" style="max-height: 350px; overflow-y: auto;">
                <table class="table table-sm align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>Period</th>
                            <th class="text-center">Loss (Heads)</th>
                            <th class="text-end">Financial Loss</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($summaryRecords as $d)
                            @php
                                $displayPeriod = "";
                                switch($summaryPeriod) {
                                    case 'weekly':
                                        $weekStart = date('M d', strtotime($d->period_start));
                                        $weekEnd = date('M d, Y', strtotime($d->period_start . ' +6 days'));
                                        $displayPeriod = "$weekStart - $weekEnd";
                                        break;
                                    case 'monthly':
                                        $displayPeriod = date('F Y', strtotime($d->period_start));
                                        break;
                                    case 'yearly':
                                        $displayPeriod = date('Y', strtotime($d->period_start));
                                        break;
                                    default:
                                        $displayPeriod = date('M d, Y', strtotime($d->period_start));
                                        break;
                                }
                            @endphp
                            <tr>
                                <td><span class="small fw-bold text-muted">{{ $displayPeriod }}</span></td>
                                <td class="fw-bold text-danger text-center">-{{ number_format($d->total_loss) }} <small>heads</small></td>
                                <td class="fw-bold text-dark text-end">₱{{ number_format($d->total_financial_loss, 2) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="text-center py-4 text-muted small">No mortality records found.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            
            @if($summaryRecords->hasPages())
                <div class="mt-3">
                    {{ $summaryRecords->appends(request()->except('page'))->links() }}
                </div>
            @endif
        </div>
    </div>

    <!-- Right Column: Calendar / Monthly View & Recent Logs -->
    <div style="min-width: 0;">
        <div class="card h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
                    <h3 class="section-title mb-0"><i class="fas fa-calendar-alt text-primary"></i>Daily Mortality Logs</h3>
                    <div class="d-flex gap-2">
                        <form action="{{ route('mortality.index') }}" method="GET" class="d-flex flex-wrap gap-2">
                            <input type="hidden" name="summary_period" value="{{ $summaryPeriod }}">
                            <select name="batch_filter" class="form-select form-select-sm" onchange="this.form.submit()">
                                <option value="">-- All Batches --</option>
                                @foreach($allBatches as $ab)
                                    <option value="{{ $ab->batch_id }}" {{ $batchFilter == $ab->batch_id ? 'selected' : '' }}>
                                        {{ $ab->batch_code }}
                                    </option>
                                @endforeach
                            </select>
                            <select name="month" class="form-select form-select-sm" onchange="this.form.submit()">
                                @for($m=1; $m<=12; $m++)
                                    <option value="{{ $m }}" {{ $m == $month ? 'selected' : '' }}>
                                        {{ date('F', mktime(0,0,0,$m,1)) }}
                                    </option>
                                @endfor
                            </select>
                            <select name="year" class="form-select form-select-sm" onchange="this.form.submit()">
                                @for($y=date('Y'); $y>=date('Y')-2; $y--)
                                    <option value="{{ $y }}" {{ $y == $year ? 'selected' : '' }}>
                                        {{ $y }}
                                    </option>
                                @endfor
                            </select>
                        </form>
                    </div>
                </div>

                @if($viewCount === 0)
                    <div class="alert alert-light border shadow-sm rounded-3 py-2 px-3 mb-3 d-flex align-items-center justify-content-between flex-wrap gap-2">
                        <div class="d-flex align-items-center gap-2 text-muted small">
                            <i class="fas fa-circle-info text-primary fs-5"></i>
                            <span>No mortality records found for <strong>{{ date('F Y', mktime(0,0,0,$month,1,$year)) }}</strong>{{ $batchFilter ? ' under this batch filter' : '' }}.</span>
                        </div>
                        <div class="d-flex gap-2">
                            @if($batchFilter)
                                <a href="{{ route('mortality.index', ['month' => $month, 'year' => $year]) }}" class="btn btn-sm btn-outline-secondary rounded-pill px-3">
                                    <i class="fas fa-filter-circle-xmark me-1"></i>View All Batches
                                </a>
                            @endif
                            @if($latestM && ($latestM != $month || $latestY != $year))
                                <a href="{{ route('mortality.index', ['month' => $latestM, 'year' => $latestY]) }}" class="btn btn-sm btn-primary rounded-pill px-3">
                                    <i class="fas fa-calendar-check me-1"></i>View Latest Records ({{ date('F Y', mktime(0,0,0,$latestM,1,$latestY)) }})
                                </a>
                            @endif
                        </div>
                    </div>
                @endif

                <!-- Calendar Grid -->
                <div class="table-responsive custom-scrollbar pb-2">
                    <div class="calendar-container" style="min-width: 580px;">
                        <div class="row g-2 text-center fw-bold mb-2">
                            <div class="col" style="width: 14.28%;">Sun</div>
                            <div class="col" style="width: 14.28%;">Mon</div>
                            <div class="col" style="width: 14.28%;">Tue</div>
                            <div class="col" style="width: 14.28%;">Wed</div>
                            <div class="col" style="width: 14.28%;">Thu</div>
                            <div class="col" style="width: 14.28%;">Fri</div>
                            <div class="col" style="width: 14.28%;">Sat</div>
                        </div>
                    
                        <div class="row g-2">
                            @for($i=0; $i<$firstDay; $i++)
                                <div class="col" style="width: 14.28%; min-height: 120px;">
                                    <div class="card h-100 border-0 bg-light opacity-25"></div>
                                </div>
                            @endfor

                            @for($day=1; $day<=$daysInMonth; $day++)
                                @php
                                    $currDate = sprintf("%04d-%02d-%02d", $year, $month, $day);
                                    $isToday = ($currDate == date('Y-m-d'));
                                    $todayClass = $isToday ? 'border-primary shadow-sm' : 'border-light';
                                    $dayRecords = $dayRecordsMap[$currDate] ?? [];
                                    $recordsJson = json_encode($dayRecords, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
                                @endphp
                                <div class="col" style="width: 14.28%; min-height: 120px;">
                                    <div class="card h-100 {{ $todayClass }} p-1 position-relative" 
                                         style="{{ !empty($dayRecords) ? 'cursor: pointer;' : '' }}"
                                         @if(!empty($dayRecords)) onclick='showMortalityDetails("{{ $currDate }}", `{!! $recordsJson !!}`)' @endif>
                                        <span class="small fw-bold opacity-50">{{ $day }}</span>
                                        @if(!empty($dayRecords))
                                            <div class="mt-1 overflow-auto" style="max-height: 90px; font-size: 0.65rem;">
                                                @foreach($dayRecords as $rec)
                                                    <div class="bg-danger text-white rounded px-1 mb-1 shadow-sm" style="line-height: 1.1;">
                                                        <strong>{{ $rec['batch_code'] }}</strong>: {{ $rec['quantity_change'] }}<br>
                                                        <span style="font-size: 0.6rem; opacity: 0.9;">{{ $rec['reason'] }}</span>
                                                    </div>
                                                @endforeach
                                            </div>
                                        @endif
                                    </div>
                                </div>

                                @if(($day + $firstDay) % 7 == 0)
                                    </div><div class="row g-2 mt-2">
                                @endif
                            @endfor
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Mortality Log History Table -->
        <div class="card shadow-sm border rounded-4 mt-4">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                    <h5 class="fw-bold mb-0 text-dark" style="font-size: 1.15rem;">
                        <i class="fas fa-clipboard-list text-danger me-2"></i>Recent Mortality Log Records
                    </h5>
                    <span class="badge bg-danger-subtle text-danger border border-danger-subtle px-3 py-2 rounded-pill shadow-sm">
                        <i class="fas fa-shield-alt me-1"></i>Flock Deductions Ledger
                    </span>
                </div>
                <div class="table-responsive custom-scrollbar" style="max-height: 400px; overflow-y: auto;">
                    <table class="table table-hover align-middle w-100 m-0">
                        <thead class="table-light sticky-top">
                            <tr>
                                <th style="width: 18%;">Date</th>
                                <th style="width: 22%;">Batch</th>
                                <th style="width: 16%;" class="text-center">Loss (Heads)</th>
                                <th style="width: 22%;">Reason / Cause</th>
                                <th style="width: 12%;" class="text-end">Loss (₱)</th>
                                <th style="width: 10%;">Worker</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($recentRecords as $rm)
                                @php
                                    $recName = !empty($rm->recorder->full_name) ? $rm->recorder->full_name : (!empty($rm->recorder->username) ? $rm->recorder->username : 'Admin');
                                    $pen = !empty($rm->batch->house_pen) ? ' <span class="badge bg-light text-muted border py-0 px-2 small">' . $rm->batch->house_pen . '</span>' : '';
                                @endphp
                                <tr>
                                    <td>
                                        <div class="fw-bold text-dark">{{ date('M d, Y', strtotime($rm->date)) }}</div>
                                        <small class="text-muted">{{ date('h:i A', strtotime($rm->created_at)) }}</small>
                                    </td>
                                    <td>
                                        <span class="fw-bold text-primary">{{ $rm->batch->batch_code ?? 'Batch #' . $rm->batch_id }}</span>
                                        {!! $pen !!}
                                    </td>
                                    <td class="text-center">
                                        <span class="badge bg-danger-subtle text-danger border border-danger-subtle px-3 py-1 fs-6 fw-bold">
                                            {{ (int)$rm->quantity_change }} heads
                                        </span>
                                    </td>
                                    <td>
                                        <div class="fw-semibold text-dark">{{ $rm->reason }}</div>
                                    </td>
                                    <td class="text-end">
                                        <span class="fw-bold text-dark">₱{{ number_format((float)$rm->financial_value, 2) }}</span>
                                    </td>
                                    <td>
                                        <small class="text-secondary fw-medium">{{ $recName }}</small>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="text-center py-4 text-muted small">No mortality entries recorded yet.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal 1: Professional Confirmation Warning Modal -->
<div class="modal fade" id="confirmMortalityModal" tabindex="-1" aria-labelledby="confirmMortalityModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" style="max-width: 490px;">
        <div class="modal-content border-0 rounded-4 shadow-lg overflow-hidden">
            <div class="modal-body p-4 text-center pb-2">
                <div class="mx-auto mb-3 modal-loss-icon-wrap shadow-sm">
                    <i class="fas fa-triangle-exclamation"></i>
                </div>
                <h5 class="fw-bold mb-1 text-dark" id="confirmMortalityModalLabel" style="font-size: 19px;">
                    Confirm Mortality Record
                </h5>
                <p class="text-muted small mb-3">
                    Please verify the flock headcount deduction and financial assessment before applying this record.
                </p>

                <!-- Detailed Audit Summary Box -->
                <div class="text-start p-3 rounded-3 mb-3 bg-light border">
                    <div class="d-flex justify-content-between align-items-center pb-2 mb-2 border-bottom">
                        <span class="text-muted small fw-medium">Flock Batch</span>
                        <span class="fw-bold text-dark text-end" id="confModalBatch">--</span>
                    </div>
                    <div class="d-flex justify-content-between align-items-center pb-2 mb-2 border-bottom">
                        <span class="text-muted small fw-medium">Mortality Count</span>
                        <span class="badge bg-danger-subtle text-danger border border-danger-subtle fw-bold fs-6 px-3 py-1" id="confModalQty">-0 Heads</span>
                    </div>
                    <div class="d-flex justify-content-between align-items-center pb-2 mb-2 border-bottom">
                        <span class="text-muted small fw-medium">Live Flock Impact</span>
                        <span class="fw-semibold text-dark text-end" id="confModalFlock">--</span>
                    </div>
                    <div class="d-flex justify-content-between align-items-center pb-2 mb-2 border-bottom">
                        <span class="text-muted small fw-medium">Cause / Observation</span>
                        <span class="fw-semibold text-dark text-end text-truncate" style="max-width: 240px;" id="confModalReason">--</span>
                    </div>
                    <div class="d-flex justify-content-between align-items-center pb-2 mb-2 border-bottom">
                        <span class="text-muted small fw-medium">Estimated Financial Loss</span>
                        <span class="fw-bold text-danger text-end fs-6" id="confModalLoss">₱0.00</span>
                    </div>
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="text-muted small fw-medium">Record Date</span>
                        <span class="text-secondary text-end fw-medium" id="confModalDate">--</span>
                    </div>
                </div>

                <div class="alert alert-warning-subtle border border-warning-subtle rounded-3 py-2 px-3 small d-flex align-items-start gap-2 text-warning-emphasis text-start mb-0">
                    <i class="fas fa-circle-info mt-1 flex-shrink-0 text-warning"></i>
                    <div style="font-size: 12.5px; line-height: 1.45;">
                        <strong>System Notice:</strong> Confirming will immediately decrement the live batch headcount, record the loss in the financial ledger, and log an audit entry.
                    </div>
                </div>
            </div>

            <div class="modal-footer border-0 p-3 pt-0 d-flex gap-2">
                <button type="button" class="btn btn-outline-secondary flex-fill py-2 rounded-3 fw-medium" data-bs-dismiss="modal">
                    <i class="fas fa-arrow-left me-1"></i> Edit Details
                </button>
                <button type="button" class="btn btn-danger flex-fill py-2 rounded-3 fw-semibold shadow-sm" id="btnConfirmMortalityFinal" onclick="executeMortalitySubmission()">
                    <i class="fas fa-check-circle me-1"></i> Confirm & Record
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Modal 2: Professional Validation Warning Modal -->
<div class="modal fade" id="mortalityValidationModal" tabindex="-1" aria-labelledby="mortalityValidationModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" style="max-width: 420px;">
        <div class="modal-content border-0 rounded-4 shadow-lg overflow-hidden">
            <div class="modal-body p-4 text-center">
                <div class="mx-auto mb-3 modal-warn-icon-wrap shadow-sm">
                    <i class="fas fa-circle-exclamation"></i>
                </div>
                <h5 class="fw-bold mb-2 text-dark" id="mortalityValidationModalLabel" style="font-size: 18px;">Validation Notice</h5>
                <p class="text-muted small mb-4" id="mortalityValidationMessage" style="line-height: 1.6; font-size: 13px;"></p>
                <button type="button" class="btn btn-primary px-4 py-2 rounded-3 w-100 fw-semibold" data-bs-dismiss="modal">
                    Acknowledge & Adjust
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Modal 3: Daily Mortality Detail Modal -->
<div class="modal fade" id="mortalityModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content rounded-4 border-0 shadow">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold text-primary"><i class="fas fa-clipboard-list me-2"></i>Daily Mortality Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="modalDate" class="text-muted mb-3 small fw-bold text-uppercase"></div>
                <div id="modalContent"></div>
            </div>
        </div>
    </div>
</div>

<style>
.calendar-container .col {
    flex: 0 0 14.28%;
    max-width: 14.28%;
}
.calendar-container .card {
    min-height: 120px;
    border: 1px solid #eee;
    transition: all 0.2s ease;
}
.calendar-container .card:hover {
    background-color: #f8f9fa;
}
.calendar-container .overflow-auto::-webkit-scrollbar {
    width: 3px;
}
.calendar-container .overflow-auto::-webkit-scrollbar-track {
    background: transparent;
}
.calendar-container .overflow-auto::-webkit-scrollbar-thumb {
    background: rgba(0,0,0,0.1);
    border-radius: 10px;
}
.modal-loss-icon-wrap {
    width: 62px;
    height: 62px;
    background-color: #FEE2E2;
    color: #DC2626;
    border: 1px solid #FECACA;
    border-radius: 50%;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 24px;
}
.modal-warn-icon-wrap {
    width: 62px;
    height: 62px;
    background-color: #FEF3C7;
    color: #D97706;
    border: 1px solid #FDE68A;
    border-radius: 50%;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 24px;
}
</style>
@endsection

@push('scripts')
<script>
function showMortalityDetails(date, recordsJson) {
    const records = typeof recordsJson === 'string' ? JSON.parse(recordsJson) : recordsJson;
    const modalDate = document.getElementById('modalDate');
    const modalContent = document.getElementById('modalContent');
    
    const dateObj = new Date(date);
    modalDate.innerText = dateObj.toLocaleDateString('en-US', { 
        weekday: 'long', 
        year: 'numeric', 
        month: 'long', 
        day: 'numeric' 
    });
    
    modalContent.innerHTML = '';
    records.forEach(rec => {
        const row = document.createElement('div');
        row.className = 'd-flex align-items-center p-3 mb-2 bg-light rounded-3 border-start border-4 border-danger';

        const qtyCol = document.createElement('div');
        qtyCol.className = 'me-3 text-center';
        qtyCol.style.minWidth = '50px';
        const qtyBadge = document.createElement('span');
        qtyBadge.className = 'badge bg-danger rounded-pill px-3 py-2';
        qtyBadge.textContent = String(rec.quantity_change);
        qtyCol.appendChild(qtyBadge);

        const infoCol = document.createElement('div');
        infoCol.className = 'flex-grow-1';
        const title = document.createElement('div');
        title.className = 'fw-bold text-dark mb-0';
        title.textContent = 'Batch ' + rec.batch_code;
        const sub = document.createElement('div');
        sub.className = 'small text-muted';
        let subText = rec.reason;
        if (parseFloat(rec.financial_value) > 0) {
            subText += ' (Loss: ₱' + parseFloat(rec.financial_value).toFixed(2) + ')';
        }
        sub.textContent = subText;
        infoCol.appendChild(title);
        infoCol.appendChild(sub);

        row.appendChild(qtyCol);
        row.appendChild(infoCol);
        modalContent.appendChild(row);
    });
    
    const modal = new bootstrap.Modal(document.getElementById('mortalityModal'));
    modal.show();
}

function updateSummaryPeriod(period) {
    const url = new URL(window.location);
    url.searchParams.set('summary_period', period);
    window.location.href = url.href;
}

function calculateAutoLoss() {
    const batchSelect = document.getElementById('batchSelect');
    if (!batchSelect) return;
    const selectedOption = batchSelect.options[batchSelect.selectedIndex];
    const qtyInput = document.getElementById('mortQty');
    const dateInput = document.getElementById('mortDate');
    const lossInput = document.getElementById('financialValueInput');
    const feedback = document.getElementById('qtyHeadcountFeedback');
    const breakdown = document.getElementById('lossBreakdownText');

    if (!selectedOption || !selectedOption.value) {
        if (feedback) feedback.innerHTML = '';
        return;
    }

    const currentQty = parseInt(selectedOption.getAttribute('data-qty') || 0);
    const arrivalDateStr = selectedOption.getAttribute('data-arrival');
    const chickCost = parseFloat(selectedOption.getAttribute('data-cost') || 38.00);
    const deadQty = parseInt(qtyInput.value || 0);

    if (deadQty > currentQty) {
        feedback.innerHTML = `<span class="text-danger fw-semibold"><i class="fas fa-circle-exclamation me-1"></i>Headcount limit exceeded: Batch currently has ${currentQty.toLocaleString()} live heads.</span>`;
        qtyInput.classList.add('is-invalid');
    } else if (deadQty >= 50 || (currentQty > 0 && deadQty >= currentQty * 0.1)) {
        feedback.innerHTML = `<span class="text-warning-emphasis fw-semibold"><i class="fas fa-triangle-exclamation me-1"></i>Elevated Mortality Rate: ${deadQty} heads flagged. Please verify cause and farm protocol.</span>`;
        qtyInput.classList.remove('is-invalid');
    } else if (deadQty > 0) {
        feedback.innerHTML = `<span class="text-success fw-medium"><i class="fas fa-circle-check me-1"></i>Projected post-deduction population: ${Math.max(0, currentQty - deadQty).toLocaleString()} heads remaining.</span>`;
        qtyInput.classList.remove('is-invalid');
    } else {
        feedback.innerHTML = '';
        qtyInput.classList.remove('is-invalid');
    }

    if (arrivalDateStr && deadQty > 0) {
        const arrDate = new Date(arrivalDateStr);
        const recDate = new Date(dateInput.value || new Date());
        const ageDays = Math.max(1, Math.floor((recDate - arrDate) / (1000 * 60 * 60 * 24)));
        const feedMeds = Math.min(35, ageDays) * 2.85;
        const costPerBird = chickCost + feedMeds;
        const totalLoss = (deadQty * costPerBird).toFixed(2);

        lossInput.value = totalLoss;
        breakdown.innerHTML = `<span class="text-primary fw-bold">₱${parseFloat(totalLoss).toLocaleString('en-PH', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</span> (${deadQty} birds @ ~₱${costPerBird.toFixed(2)}/bird at Day ${ageDays}: ₱${chickCost.toFixed(2)} DOC + ₱${feedMeds.toFixed(2)} feed/meds)`;
    }
}

function showValidationNotice(title, message, focusElementId) {
    document.getElementById('mortalityValidationModalLabel').textContent = title;
    document.getElementById('mortalityValidationMessage').textContent = message;
    const modalEl = document.getElementById('mortalityValidationModal');
    const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
    
    if (focusElementId) {
        modalEl.addEventListener('hidden.bs.modal', function onHidden() {
            modalEl.removeEventListener('hidden.bs.modal', onHidden);
            const el = document.getElementById(focusElementId);
            if (el) el.focus();
        });
    }
    modal.show();
}

function initiateMortalityConfirmation() {
    const batchSelect = document.getElementById('batchSelect');
    const selectedOption = batchSelect ? batchSelect.options[batchSelect.selectedIndex] : null;
    const qtyInput = document.getElementById('mortQty');
    const reasonInput = document.getElementById('mortReason');
    const dateInput = document.getElementById('mortDate');
    const lossInput = document.getElementById('financialValueInput');

    if (!selectedOption || !selectedOption.value) {
        showValidationNotice(
            'Batch Selection Required',
            'Please select an active broiler flock batch before recording mortality.',
            'batchSelect'
        );
        return;
    }

    const currentQty = parseInt(selectedOption.getAttribute('data-qty') || 0);
    const deadQty = parseInt(qtyInput ? qtyInput.value : 0);

    if (isNaN(deadQty) || deadQty <= 0) {
        showValidationNotice(
            'Invalid Headcount',
            'Please enter a valid mortality count (minimum 1 head).',
            'mortQty'
        );
        return;
    }

    if (deadQty > currentQty) {
        showValidationNotice(
            'Flock Population Limit Exceeded',
            `The requested mortality count of ${deadQty} heads exceeds the currently available live population (${currentQty} heads) for this batch.`,
            'mortQty'
        );
        return;
    }

    const reason = reasonInput ? reasonInput.value.trim() : '';
    if (!reason) {
        showValidationNotice(
            'Observed Cause Required',
            'Please specify the cause or observed symptoms for this mortality entry (e.g., Heat stress, Smothering).',
            'mortReason'
        );
        return;
    }

    document.getElementById('confModalBatch').textContent = selectedOption.text.split('(')[0].trim();
    document.getElementById('confModalQty').textContent = `-${deadQty} Heads`;
    
    const remainingHeads = Math.max(0, currentQty - deadQty);
    document.getElementById('confModalFlock').innerHTML = `${currentQty.toLocaleString()} &rarr; <span class="text-success fw-bold">${remainingHeads.toLocaleString()} live heads left</span>`;
    
    document.getElementById('confModalReason').textContent = reason;
    
    const lossVal = parseFloat(lossInput ? lossInput.value : 0) || 0;
    document.getElementById('confModalLoss').textContent = '₱' + lossVal.toLocaleString('en-PH', {minimumFractionDigits: 2, maximumFractionDigits: 2});
    
    let formattedDate = dateInput && dateInput.value ? dateInput.value : 'Today';
    try {
        const d = new Date(dateInput.value);
        if (!isNaN(d.getTime())) {
            formattedDate = d.toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' });
        }
    } catch(e) {}
    document.getElementById('confModalDate').textContent = formattedDate;

    const confirmModal = bootstrap.Modal.getOrCreateInstance(document.getElementById('confirmMortalityModal'));
    confirmModal.show();
}

function executeMortalitySubmission() {
    const btn = document.getElementById('btnConfirmMortalityFinal');
    if (btn) {
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status"></span>Saving Record...';
    }
    document.getElementById('mortalityForm').submit();
}

document.addEventListener('DOMContentLoaded', function() {
    calculateAutoLoss();
});
</script>
@endpush
