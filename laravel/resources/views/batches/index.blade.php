@extends('layouts.app')

@section('title', 'Batch Management')

@section('content')
<div class="page-grid">
    <!-- Register New Batch (Left Column) -->
    <div>
        <div class="card form-container h-100 shadow-sm border-0 rounded-4">
            <div class="card-body d-flex flex-column p-4">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div>
                        <h3 class="section-title mb-0 fs-5 fw-bold text-dark"><i class="fas fa-layer-group text-primary me-2"></i>Register New Flock</h3>
                        <small class="text-muted">Broiler contract intake & housing placement</small>
                    </div>
                    <span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1 rounded-pill">
                        <i class="fas fa-check-circle me-1"></i>Broiler Intake
                    </span>
                </div>

                <form method="POST" action="{{ route('batches.store') }}" id="newBatchForm">
                    @csrf
                    <input type="hidden" name="flock_type" value="broiler">
                    <input type="hidden" name="linked_reservation_id" id="linked_reservation_id" value="">

                    <!-- 1. Identification & Stocking -->
                    <div class="p-3 bg-light rounded-3 border mb-3">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span class="fw-bold small text-dark"><i class="fas fa-id-card text-primary me-1"></i>Flock Identification</span>
                            <button type="button" class="btn btn-sm btn-outline-primary rounded-pill py-0 px-2" style="font-size: 0.72rem;" onclick="generateBatchCode()" title="Auto-generate standard batch code">
                                <i class="fas fa-magic me-1"></i>Auto-Code
                            </button>
                        </div>

                        <div class="mb-2">
                            <label class="form-label small fw-bold text-muted mb-1">Batch Code <span class="text-danger">*</span></label>
                            <input type="text" name="batch_code" id="new_batch_code" class="form-control font-monospace fw-bold text-primary" placeholder="e.g. BR-{{ date('Ym') }}-001" value="BR-{{ date('Ym') }}-{{ sprintf('%03d', $totalRecords + 1) }}" required>
                        </div>

                        <div class="row g-2">
                            <div class="col-6">
                                <label class="form-label small fw-bold text-muted mb-1">Chicks Stocked <span class="text-danger">*</span></label>
                                <div class="input-group input-group-sm">
                                    <input type="number" name="quantity" id="new_quantity" class="form-control fw-bold" min="1" placeholder="5000" required>
                                    <span class="input-group-text bg-white text-muted">birds</span>
                                </div>
                            </div>
                            <div class="col-6">
                                <label class="form-label small fw-bold text-muted mb-1">Breed / Line</label>
                                <input type="text" name="breed" class="form-control form-control-sm" placeholder="e.g. Cobb 500" value="Cobb 500">
                            </div>
                        </div>
                    </div>

                    <!-- 2. Housing & Placement -->
                    <div class="p-3 bg-light rounded-3 border mb-3">
                        <div class="fw-bold small text-dark mb-2"><i class="fas fa-warehouse text-primary me-1"></i>Housing Placement</div>
                        <div class="mb-2">
                            <label class="form-label small fw-bold text-muted mb-1">Pen / House</label>
                            <select name="house_pen" id="new_house_pen" class="form-select form-select-sm" required>
                                <option value="">-- Choose Housing Pen --</option>
                                @foreach($houses as $h)
                                    <option value="{{ $h->house_name }}">
                                        {{ $h->house_name }} (Cap: {{ number_format($h->capacity) }}) - {{ ucfirst($h->status) }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="row g-2">
                            <div class="col-6">
                                <label class="form-label small fw-bold text-muted mb-1">Arrival Date <span class="text-danger">*</span></label>
                                <input type="date" name="arrival_date" id="new_arrival_date" class="form-control form-control-sm" value="{{ date('Y-m-d') }}" required>
                            </div>
                            <div class="col-6">
                                <label class="form-label small fw-bold text-muted mb-1">Expected Harvest Date</label>
                                <input type="date" name="expected_harvest_date" id="new_expected_harvest_date" class="form-control form-control-sm" value="{{ date('Y-m-d', strtotime('+35 days')) }}">
                            </div>
                        </div>
                    </div>

                    <!-- 3. Contract Economics & Standards -->
                    <div class="p-3 bg-light rounded-3 border mb-3">
                        <div class="fw-bold small text-dark mb-2"><i class="fas fa-scale-balanced text-primary me-1"></i>Target Standards & Cost</div>
                        <div class="row g-2">
                            <div class="col-6">
                                <label class="form-label small fw-bold text-muted mb-1">Target Weight (kg) <span class="text-danger">*</span></label>
                                <input type="number" step="0.05" name="target_weight_kg" id="new_target_weight_kg" class="form-control form-control-sm font-monospace fw-bold" value="1.80" required>
                            </div>
                            <div class="col-6">
                                <label class="form-label small fw-bold text-muted mb-1">Chick Cost (₱) <span class="text-danger">*</span></label>
                                <input type="number" step="0.01" name="chick_unit_cost" class="form-control form-control-sm font-monospace" value="38.00" required>
                            </div>
                        </div>
                    </div>

                    <!-- 4. Supplier & Notes -->
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-muted mb-1">Breeder Supplier / Hatchery</label>
                        <input type="text" name="supplier" class="form-control form-control-sm" placeholder="e.g. San Miguel Foods / Integrator">
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold text-muted mb-1">Field / Intake Notes</label>
                        <textarea name="notes" class="form-control form-control-sm" rows="2" placeholder="Flock uniformity, initial health, or intake remarks..."></textarea>
                    </div>

                    <div class="form-actions-sticky mt-auto d-grid">
                        <button type="submit" class="btn btn-primary py-2 fw-bold shadow-sm">
                            <i class="fas fa-check-circle me-1"></i>Save Batch Record
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Registered Batches Table (Right Column) -->
    <div style="min-width: 0;">
        <div class="card h-100 border-0 shadow-sm rounded-4 bg-white p-4">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h3 class="section-title mb-1 fs-5 fw-bold text-dark"><i class="fas fa-layer-group me-2 text-primary"></i>Registered Flocks & Houses</h3>
                    <p class="text-muted small mb-0">Active and historical broiler flocks across all houses.</p>
                </div>
            </div>

            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Batch Code</th>
                            <th>Dates</th>
                            <th>Chicks Stocked</th>
                            <th>Current Headcount</th>
                            <th>House / Breed</th>
                            <th>Target & Live Weight</th>
                            <th>Status</th>
                            <th class="text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($batches as $b)
                            @php
                                $stage = $b->stage_info;
                                $readiness = $b->readiness;
                                $statusBadge = $b->status === 'active' ? 'bg-success text-white' : ($b->status === 'harvested' ? 'bg-info text-white' : 'bg-secondary text-white');
                            @endphp
                            <tr>
                                <td>
                                    <span class="fw-bold text-primary fs-6">{{ $b->batch_code }}</span>
                                    <div class="small text-dark mt-1"><strong>Age:</strong> {{ $stage['age_days'] }} days (Wk {{ $stage['age_weeks'] }})</div>
                                    <span class="badge {{ $stage['badge_class'] }} px-2 py-1 mt-1">{{ $stage['status_label'] }}</span>
                                    @if($stage['is_ready_pickup'] && $b->status === 'active')
                                        <div class="mt-1"><span class="badge bg-success text-white py-1 px-2"><i class="fas fa-truck-loading me-1"></i>Ready for Pickup</span></div>
                                    @endif
                                </td>
                                <td>
                                    <div class="small"><strong>Intake:</strong> {{ date('M d, Y', strtotime($b->arrival_date)) }}</div>
                                    @if($b->expected_harvest_date)
                                        <div class="small text-muted"><strong>Harvest:</strong> {{ date('M d, Y', strtotime($b->expected_harvest_date)) }}</div>
                                    @endif
                                </td>
                                <td>
                                    <span class="fw-bold fs-6">{{ number_format($b->initial_quantity) }}</span>
                                    <small class="text-muted d-block">Chicks Stocked</small>
                                </td>
                                <td>
                                    <span class="fw-bold fs-6 text-success">{{ number_format($b->current_quantity) }} heads</span>
                                    <small class="text-danger d-block">(-{{ number_format($b->total_mortality) }} loss / ₱{{ number_format($b->total_financial_loss, 2) }})</small>
                                    <div class="small text-muted">{{ $b->survival_rate_pct }}% survival</div>
                                </td>
                                <td>
                                    <div><strong>{{ $b->house_pen ?: 'General' }}</strong></div>
                                    <small class="text-muted d-block">{{ $b->breed ?: 'Broiler (Cobb 500)' }}</small>
                                    <small class="text-primary d-block"><i class="fas fa-utensils me-1"></i>Feed: {{ $stage['feed_type'] }}</small>
                                    @if($b->supplier)
                                        <small class="text-secondary d-block"><i class="fas fa-building me-1"></i>{{ $b->supplier }}</small>
                                    @endif
                                </td>
                                <td>
                                    <div>
                                        <strong class="text-primary font-monospace">{{ number_format((float)($b->current_weight_kg ?? 0), 2) }} kg</strong> 
                                        <span class="text-muted small">/ {{ number_format((float)($b->target_weight_kg ?? 1.80), 2) }} kg</span>
                                    </div>
                                    <div class="mt-1">
                                        @if($readiness['ready'])
                                            <span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1"><i class="fas fa-check-circle me-1"></i>Weight Met</span>
                                        @else
                                            <span class="badge bg-warning-subtle text-dark border border-warning-subtle px-2 py-1"><i class="fas fa-hourglass-half me-1"></i>Growing ({{ $readiness['percent'] }}%)</span>
                                        @endif
                                    </div>
                                </td>
                                <td>
                                    <span class="badge rounded-pill {{ $statusBadge }} px-3 py-1 text-uppercase">
                                        {{ $b->status }}
                                    </span>
                                </td>
                                <td class="text-center">
                                    <div class="btn-group btn-group-sm" role="group">
                                        <button type="button" class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#viewBatchModal{{ $b->batch_id }}" title="View Batch Details">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <button type="button" class="btn btn-outline-success" onclick="openBatchWeighModal({{ $b->batch_id }}, '{{ $b->batch_code }}', {{ (float)($b->current_weight_kg ?? 0) }}, {{ (float)($b->target_weight_kg ?? 1.80) }}, {{ $stage['age_days'] }})" title="Record Weight Sample">
                                            <i class="fas fa-weight-scale"></i>
                                        </button>
                                        <button type="button" class="btn btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#editBatchModal{{ $b->batch_id }}" title="Edit Batch">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>

                            <!-- MODAL: View Batch Details -->
                            <div class="modal fade" id="viewBatchModal{{ $b->batch_id }}" tabindex="-1">
                                <div class="modal-dialog modal-dialog-centered">
                                    <div class="modal-content border-0 rounded-4 shadow">
                                        <div class="modal-header border-0 pb-0">
                                            <h5 class="modal-title fw-bold text-dark"><i class="fas fa-info-circle text-primary me-2"></i>Batch Details: {{ $b->batch_code }}</h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                        </div>
                                        <div class="modal-body p-4">
                                            <ul class="list-group list-group-flush small">
                                                <li class="list-group-item d-flex justify-content-between px-0">
                                                    <span class="text-muted">Batch Identifier:</span>
                                                    <strong class="text-primary">{{ $b->batch_code }}</strong>
                                                </li>
                                                <li class="list-group-item d-flex justify-content-between px-0">
                                                    <span class="text-muted">Housing Coop / Pen:</span>
                                                    <strong>{{ $b->house_pen ?: 'Unassigned' }}</strong>
                                                </li>
                                                <li class="list-group-item d-flex justify-content-between px-0">
                                                    <span class="text-muted">Intake Date:</span>
                                                    <strong>{{ date('F d, Y', strtotime($b->arrival_date)) }} (Day {{ $stage['age_days'] }})</strong>
                                                </li>
                                                <li class="list-group-item d-flex justify-content-between px-0">
                                                    <span class="text-muted">Chicks Stocked:</span>
                                                    <strong>{{ number_format($b->initial_quantity) }} birds</strong>
                                                </li>
                                                <li class="list-group-item d-flex justify-content-between px-0">
                                                    <span class="text-muted">Current Live Count:</span>
                                                    <strong class="text-success">{{ number_format($b->current_quantity) }} birds</strong>
                                                </li>
                                                <li class="list-group-item d-flex justify-content-between px-0">
                                                    <span class="text-muted">Mortality Loss:</span>
                                                    <strong class="text-danger">-{{ number_format($b->total_mortality) }} birds (₱{{ number_format($b->total_financial_loss, 2) }})</strong>
                                                </li>
                                                <li class="list-group-item d-flex justify-content-between px-0">
                                                    <span class="text-muted">Total Feed Consumed:</span>
                                                    <strong>{{ number_format($b->total_feed_used, 2) }} kg</strong>
                                                </li>
                                                <li class="list-group-item d-flex justify-content-between px-0">
                                                    <span class="text-muted">Field / Intake Notes:</span>
                                                    <span>{{ $b->notes ?: 'None' }}</span>
                                                </li>
                                            </ul>
                                        </div>
                                        <div class="modal-footer border-0 pt-0">
                                            <button type="button" class="btn btn-light rounded-pill px-3" data-bs-dismiss="modal">Close</button>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- MODAL: Edit Batch -->
                            <div class="modal fade" id="editBatchModal{{ $b->batch_id }}" tabindex="-1">
                                <div class="modal-dialog modal-dialog-centered">
                                    <div class="modal-content border-0 rounded-4 shadow">
                                        <div class="modal-header border-0 pb-0">
                                            <h5 class="modal-title fw-bold text-dark"><i class="fas fa-edit text-primary me-2"></i>Edit Flock {{ $b->batch_code }}</h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                        </div>
                                        <form method="POST" action="{{ route('batches.update', $b->batch_id) }}">
                                            @csrf
                                            @method('PUT')
                                            <div class="modal-body p-4">
                                                <div class="mb-3">
                                                    <label class="form-label small fw-bold">Batch Code <span class="text-danger">*</span></label>
                                                    <input type="text" name="batch_code" class="form-control" value="{{ $b->batch_code }}" required>
                                                </div>
                                                <div class="row g-3 mb-3">
                                                    <div class="col-6">
                                                        <label class="form-label small fw-bold">Initial Headcount</label>
                                                        <input type="number" name="initial_quantity" class="form-control" value="{{ $b->initial_quantity }}" required>
                                                    </div>
                                                    <div class="col-6">
                                                        <label class="form-label small fw-bold">Status</label>
                                                        <select name="status" class="form-select">
                                                            <option value="active" {{ $b->status === 'active' ? 'selected' : '' }}>Active</option>
                                                            <option value="harvested" {{ $b->status === 'harvested' ? 'selected' : '' }}>Harvested</option>
                                                            <option value="closed" {{ $b->status === 'closed' ? 'selected' : '' }}>Closed</option>
                                                        </select>
                                                    </div>
                                                </div>
                                                <div class="row g-3 mb-3">
                                                    <div class="col-6">
                                                        <label class="form-label small fw-bold">Target Weight (kg)</label>
                                                        <input type="number" step="0.01" name="target_weight_kg" class="form-control" value="{{ $b->target_weight_kg }}" required>
                                                    </div>
                                                    <div class="col-6">
                                                        <label class="form-label small fw-bold">Current Weight (kg)</label>
                                                        <input type="number" step="0.01" name="current_weight_kg" class="form-control" value="{{ $b->current_weight_kg }}">
                                                    </div>
                                                </div>
                                                <div class="mb-3">
                                                    <label class="form-label small fw-bold">Housing Pen</label>
                                                    <input type="text" name="house_pen" class="form-control" value="{{ $b->house_pen }}">
                                                </div>
                                                <div class="mb-3">
                                                    <label class="form-label small fw-bold">Notes</label>
                                                    <textarea name="notes" class="form-control" rows="2">{{ $b->notes }}</textarea>
                                                </div>
                                            </div>
                                            <div class="modal-footer border-0 pt-0">
                                                <button type="button" class="btn btn-light rounded-pill px-3" data-bs-dismiss="modal">Cancel</button>
                                                <button type="submit" class="btn btn-primary fw-semibold px-4 rounded-pill">Save Changes</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        @empty
                            <tr>
                                <td colspan="8" class="text-center py-4 text-muted">
                                    <i class="fas fa-layer-group text-muted fs-3 mb-2 d-block"></i>
                                    No batch records registered yet.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-3">
                {{ $batches->links() }}
            </div>
        </div>
    </div>
</div>

<!-- MODAL: Quick Weighing Modal -->
<div class="modal fade" id="batchWeighModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 rounded-4 shadow">
            <form method="POST" id="batchWeighForm">
                @csrf
                <div class="modal-header bg-primary text-white border-0 rounded-top-4">
                    <h5 class="modal-title fw-bold"><i class="fas fa-weight-scale me-2"></i>Record Flock Weight Sample</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="p-3 bg-light rounded-3 mb-3 border">
                        <div class="d-flex justify-content-between mb-1">
                            <span class="text-muted small">Flock Code:</span>
                            <span class="fw-bold text-primary" id="bWeighCode">-</span>
                        </div>
                        <div class="d-flex justify-content-between">
                            <span class="text-muted small">Target Weight:</span>
                            <span class="fw-bold text-success" id="bWeighTarget">1.80 kg</span>
                        </div>
                    </div>

                    <div class="mb-3">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <label class="form-label small fw-bold mb-0">Average Sample Weight (kg) <span class="text-danger">*</span></label>
                            <button type="button" class="btn btn-xs btn-outline-primary rounded-pill px-2 py-0" style="font-size:0.75rem;" onclick="autoFillBatchWeigh()">
                                <i class="fas fa-wand-magic-sparkles text-warning me-1"></i>Auto-Fill Cobb 500
                            </button>
                        </div>
                        <input type="number" step="0.01" min="0.05" max="5.0" name="weight_kg" id="bWeighInput" class="form-control fs-5 fw-bold text-primary" required>
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-light rounded-pill px-3" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary rounded-pill px-4 fw-bold">Save Weight</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
function generateBatchCode() {
    const d = new Date();
    const ym = d.getFullYear().toString() + String(d.getMonth() + 1).padStart(2, '0');
    const seq = Math.floor(Math.random() * 900) + 100;
    document.getElementById('new_batch_code').value = 'BR-' + ym + '-' + seq;
}

let bWeighAgeGlobal = 28;

function estimateWeight(days) {
    days = Math.max(1, parseInt(days) || 1);
    let est = 0.04;
    if (days <= 7) est = 0.04 + (days * (0.18 - 0.04) / 7);
    else if (days <= 14) est = 0.18 + ((days - 7) * (0.45 - 0.18) / 7);
    else if (days <= 21) est = 0.45 + ((days - 14) * (0.92 - 0.45) / 7);
    else if (days <= 28) est = 0.92 + ((days - 21) * (1.55 - 0.92) / 7);
    else if (days <= 32) est = 1.55 + ((days - 28) * (1.85 - 1.55) / 4);
    else est = 1.85 + ((days - 32) * 0.06);
    return Math.round(est * 100) / 100;
}

function autoFillBatchWeigh() {
    const est = estimateWeight(bWeighAgeGlobal);
    const input = document.getElementById('bWeighInput');
    if (input) input.value = est.toFixed(2);
}

function openBatchWeighModal(batchId, batchCode, curW, tarW, ageDays) {
    const modalEl = document.getElementById('batchWeighModal');
    const modal = bootstrap.Modal.getOrCreateInstance(modalEl);

    bWeighAgeGlobal = parseInt(ageDays) || 28;
    const est = estimateWeight(bWeighAgeGlobal);

    const form = document.getElementById('batchWeighForm');
    if (form) form.action = '{{ url("/batches") }}/' + batchId + '/weigh';

    document.getElementById('bWeighCode').innerText = batchCode + ' (Day ' + bWeighAgeGlobal + ')';
    document.getElementById('bWeighTarget').innerText = Number(tarW || 1.80).toFixed(2) + ' kg';
    document.getElementById('bWeighInput').value = curW > 0 ? Number(curW).toFixed(2) : est.toFixed(2);

    modal.show();
}
</script>
@endpush
