@extends('layouts.app')

@section('title', 'Flock Health Records')

@section('content')
<div class="page-grid">
    <!-- Form: Add Health Record -->
    <div>
        <div class="card form-container h-100">
            <div class="card-body d-flex flex-column">
                <h4 class="section-title"><i class="fas fa-heartbeat text-primary"></i>New Health Record</h4>
                <p class="text-muted small mb-4">Record disease observation, consultation, medicine dosage and schedule for broiler flocks.</p>
            
                @if(session('success'))
                    <div class="alert alert-success shadow-sm mb-4">
                        <i class="fas fa-check-circle me-1"></i>{{ session('success') }}
                    </div>
                @endif
                @if(session('error'))
                    <div class="alert alert-danger shadow-sm mb-4">
                        <i class="fas fa-exclamation-circle me-1"></i>{{ session('error') }}
                    </div>
                @endif

                <form method="POST" action="{{ route('health.store') }}">
                    @csrf
                    <div class="mb-3">
                        <label class="form-label fw-bold">Broiler Batch <span class="text-danger">*</span></label>
                        <select name="batch_id" class="form-select" required>
                            <option value="">-- Select Broiler Batch --</option>
                            @foreach($batches as $b)
                                @php
                                    $selected = ($filterBatch === (int)$b->batch_id) ? 'selected' : '';
                                    $hp = !empty($b->house_pen) ? ' - ' . $b->house_pen : '';
                                @endphp
                                <option value="{{ $b->batch_id }}" {{ $selected }}>
                                    {{ $b->batch_code }}{{ $hp }} (Broiler - {{ $b->breed ?: 'Cobb 500' }})
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="row">
                        <div class="col-sm-6 mb-3">
                            <label class="form-label">Record Type <span class="text-danger">*</span></label>
                            <select name="type" class="form-select" required readonly>
                                <option value="Treatment" selected>Treatment / Medication</option>
                            </select>
                        </div>
                        <div class="col-sm-6 mb-3">
                            <label class="form-label">Treatment Status <span class="text-danger">*</span></label>
                            <select name="status" class="form-select" required>
                                <option value="completed" selected>Completed</option>
                                <option value="scheduled">Scheduled</option>
                                <option value="in_progress">In Progress</option>
                                <option value="cancelled">Cancelled</option>
                            </select>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">Medicine / Treatment / Vitamin <span class="text-danger">*</span></label>
                        <select name="medicine" class="form-select" required>
                            <option value="">-- Select from Inventory --</option>
                            @foreach($inventoryItems as $inv)
                                @php
                                    $disabled = ($inv->quantity <= 0) ? 'disabled' : '';
                                    $stockMsg = ($inv->quantity <= 0) ? ' [OUT OF STOCK]' : ' (Available: ' . number_format($inv->quantity, 2) . ' ' . $inv->unit . ')';
                                @endphp
                                <option value="{{ $inv->item_name }}" {{ $disabled }}>
                                    {{ $inv->item_name }}{{ $stockMsg }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="row">
                        <div class="col-sm-6 mb-3">
                            <label class="form-label">Illness Observation</label>
                            <input type="text" name="illness_observation" class="form-control" placeholder="e.g. Sneezing, lethargy">
                        </div>
                        <div class="col-sm-6 mb-3">
                            <label class="form-label">Diagnosis</label>
                            <input type="text" name="diagnosis" class="form-control" placeholder="e.g. CRD / Mild Enteritis">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">Company-Doctor Consultation</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-user-md text-primary"></i></span>
                            <input type="text" name="doctor_consultation" class="form-control" placeholder="e.g. Dr. A. Santos (Farm Vet)">
                        </div>
                        <small class="text-muted">Attending veterinary doctor or company medical consultant.</small>
                    </div>

                    <div class="row">
                        <div class="col-4 mb-3">
                            <label class="form-label fw-bold">Quantity Used <span class="text-danger">*</span></label>
                            <input type="number" step="0.01" name="qty" class="form-control" placeholder="0.00" required>
                        </div>
                        <div class="col-4 mb-3">
                            <label class="form-label fw-bold">Chickens Treated</label>
                            <input type="number" name="number_of_chickens_treated" class="form-control" placeholder="e.g. 100">
                        </div>
                        <div class="col-4 mb-3">
                            <label class="form-label fw-bold">Treatment Date <span class="text-danger">*</span></label>
                            <input type="date" name="date" class="form-control" value="{{ date('Y-m-d') }}" required>
                        </div>
                    </div>

                    <div class="mb-4">
                        <label class="form-label fw-bold">Remarks / Notes</label>
                        <textarea name="remarks" class="form-control" rows="2" placeholder="Administration route, dosage remarks, flock response..."></textarea>
                    </div>

                    <div class="form-actions-sticky mt-auto">
                        <button type="submit" class="btn btn-primary w-100">Add Health Record</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Table & Filters: Health Records -->
    <div style="min-width: 0;">
        <div class="card h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h4 class="section-title mb-0"><i class="fas fa-notes-medical"></i>Flock Health & Treatment History</h4>
                    <span class="badge bg-primary px-3 py-2">
                        {{ number_format($totalRecordsCount) }} Records
                    </span>
                </div>

                <!-- Filter Toolbar -->
                <form method="GET" action="{{ route('health.index') }}" class="p-3 bg-light rounded-4 mb-4 border">
                    <div class="row g-2 align-items-end">
                        <div class="col-md-3 col-sm-6">
                            <label class="form-label small fw-bold text-muted mb-1">Batch</label>
                            <select name="batch_id" class="form-select form-select-sm">
                                <option value="">All Batches</option>
                                @foreach($filterBatches as $fb)
                                    @php
                                        $sel = ($filterBatch === (int)$fb->batch_id) ? 'selected' : '';
                                        $hp = !empty($fb->house_pen) ? ' - ' . $fb->house_pen : '';
                                    @endphp
                                    <option value="{{ $fb->batch_id }}" {{ $sel }}>{{ $fb->batch_code }}{{ $hp }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-2 col-sm-6">
                            <label class="form-label small fw-bold text-muted mb-1">Status</label>
                            <select name="status" class="form-select form-select-sm">
                                <option value="">All Statuses</option>
                                <option value="scheduled" {{ $filterStatus === 'scheduled' ? 'selected' : '' }}>Scheduled</option>
                                <option value="in_progress" {{ $filterStatus === 'in_progress' ? 'selected' : '' }}>In Progress</option>
                                <option value="completed" {{ $filterStatus === 'completed' ? 'selected' : '' }}>Completed</option>
                                <option value="cancelled" {{ $filterStatus === 'cancelled' ? 'selected' : '' }}>Cancelled</option>
                            </select>
                        </div>
                        <div class="col-md-2 col-sm-6">
                            <label class="form-label small fw-bold text-muted mb-1">Type</label>
                            <select name="type" class="form-select form-select-sm">
                                <option value="">All Types</option>
                                <option value="treatment" {{ $filterType === 'treatment' ? 'selected' : '' }}>Treatment</option>
                            </select>
                        </div>
                        <div class="col-md-2 col-sm-6">
                            <label class="form-label small fw-bold text-muted mb-1">From Date</label>
                            <input type="date" name="date_from" class="form-control form-control-sm" value="{{ $filterDateFrom }}">
                        </div>
                        <div class="col-md-2 col-sm-6">
                            <label class="form-label small fw-bold text-muted mb-1">To Date</label>
                            <input type="date" name="date_to" class="form-control form-control-sm" value="{{ $filterDateTo }}">
                        </div>
                        <div class="col-md-1 col-sm-12 d-flex gap-1">
                            <button type="submit" class="btn btn-sm btn-primary w-100" title="Apply Filters">
                                <i class="fas fa-filter"></i>
                            </button>
                            <a href="{{ route('health.index') }}" class="btn btn-sm btn-outline-secondary" title="Reset Filters">
                                <i class="fas fa-undo"></i>
                            </a>
                        </div>
                    </div>
                </form>

                <div class="table-responsive custom-scrollbar" style="max-height: 520px; overflow-y: auto;">
                    <table class="table table-hover align-middle">
                        <thead class="table-light sticky-top">
                            <tr>
                                <th>Date / Batch</th>
                                <th>Type</th>
                                <th>Medicine / Qty</th>
                                <th>Chickens Treated</th>
                                <th>Illness & Diagnosis</th>
                                <th>Doctor Consultation</th>
                                <th>Status</th>
                                <th class="text-center">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($records as $row)
                                @php
                                    $typeBadge = 'bg-danger-subtle text-danger border-danger-subtle';
                                    $typeName = 'Treatment';

                                    $rawStatus = strtolower($row->status ?? 'completed');
                                    $statusBadge = 'bg-secondary text-white';
                                    if ($rawStatus === 'completed') $statusBadge = 'bg-success text-white';
                                    elseif ($rawStatus === 'in_progress') $statusBadge = 'bg-info text-dark';
                                    elseif ($rawStatus === 'scheduled') $statusBadge = 'bg-warning text-dark';
                                    elseif ($rawStatus === 'cancelled') $statusBadge = 'bg-danger text-white';

                                    $rowJson = json_encode([
                                        'health_id' => $row->health_id,
                                        'medicine_name' => $row->medicine_name,
                                        'status' => $row->status
                                    ], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
                                @endphp
                                <tr>
                                    <td>
                                        <div class="fw-bold">{{ date('M d, Y', strtotime($row->date)) }}</div>
                                        <span class="badge bg-info-subtle text-info border border-info-subtle px-2 py-0 small">
                                            <i class="fas fa-drumstick-bite me-1"></i>{{ $row->batch->batch_code ?? 'Batch #' . $row->batch_id }}
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge rounded-pill {{ $typeBadge }} border px-2 py-1">{{ $typeName }}</span>
                                    </td>
                                    <td>
                                        <div class="fw-bold text-dark">{{ $row->medicine_name }}</div>
                                        <small class="text-muted"><i class="fas fa-prescription-bottle me-1 text-primary"></i>{{ (float)$row->quantity_used }} unit(s)</small>
                                    </td>
                                    <td>
                                        <div class="fw-bold text-dark">{{ (int)$row->number_of_chickens_treated ?: '—' }}</div>
                                    </td>
                                    <td>
                                        @if(!empty($row->diagnosis))
                                            <div class="fw-semibold text-danger small"><i class="fas fa-stethoscope me-1"></i>{{ $row->diagnosis }}</div>
                                        @endif
                                        @if(!empty($row->illness_observation))
                                            <div class="small text-muted"><i class="fas fa-eye me-1"></i>{{ $row->illness_observation }}</div>
                                        @endif
                                        @if(empty($row->diagnosis) && empty($row->illness_observation))
                                            <span class="text-muted small">—</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if(!empty($row->doctor_consultation))
                                            <div class="small fw-semibold text-primary"><i class="fas fa-user-md me-1"></i>{{ $row->doctor_consultation }}</div>
                                        @else
                                            <span class="text-muted small">Not specified</span>
                                        @endif
                                    </td>
                                    <td>
                                        <span class="badge rounded-pill {{ $statusBadge }} px-2 py-1 text-uppercase small">
                                            {{ ucfirst(str_replace('_', ' ', $rawStatus)) }}
                                        </span>
                                    </td>
                                    <td class="text-center">
                                        @if(strtolower(auth()->user()->role ?? '') === 'admin')
                                            <button type="button" class="btn btn-sm btn-outline-primary" onclick='openStatusModal({!! $rowJson !!})' title="Change Treatment Status">
                                                <i class="fas fa-sync-alt"></i>
                                            </button>
                                        @else
                                            <span class="text-muted small"><i class="fas fa-lock" title="Admin only"></i></span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="8" class="text-center text-muted py-4">No health records match your criteria.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @if($records->hasPages())
                    <div class="mt-4">
                        {{ $records->withQueryString()->links() }}
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>

<!-- Modal: Update Treatment Status -->
<div class="modal fade" id="statusModal" tabindex="-1" aria-labelledby="statusModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content rounded-4 border-0 shadow">
            <form method="POST" action="{{ route('health.update-status') }}">
                @csrf
                <input type="hidden" name="health_id" id="m_health_id">

                <div class="modal-header bg-light border-0 py-3">
                    <h6 class="modal-title fw-bold mb-0" id="statusModalLabel">
                        <i class="fas fa-sync-alt me-1 text-primary"></i>Update Status
                    </h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-3">
                    <p class="small text-muted mb-2">Item: <strong id="m_medicine_name" class="text-dark"></strong></p>
                    <label class="form-label fw-bold small">Treatment Status</label>
                    <select name="status" id="m_status_select" class="form-select" required>
                        <option value="scheduled">Scheduled</option>
                        <option value="in_progress">In Progress</option>
                        <option value="completed">Completed</option>
                        <option value="cancelled">Cancelled</option>
                    </select>
                </div>
                <div class="modal-footer border-0 p-3 pt-0">
                    <button type="button" class="btn btn-sm btn-secondary rounded-pill px-3" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-sm btn-primary rounded-pill px-3 fw-bold">Update</button>
                </div>
            </form>
        </div>
    </div>
</div>

@endsection

@push('scripts')
<script>
function openStatusModal(record) {
    const r = typeof record === 'string' ? JSON.parse(record) : record;
    document.getElementById('m_health_id').value = r.health_id || '';
    document.getElementById('m_medicine_name').innerText = r.medicine_name || '';
    document.getElementById('m_status_select').value = (r.status || 'completed').toLowerCase();

    const modal = new bootstrap.Modal(document.getElementById('statusModal'));
    modal.show();
}
</script>
@endpush
