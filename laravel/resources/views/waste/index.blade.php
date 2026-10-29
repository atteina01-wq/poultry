@extends('layouts.app')

@section('title', 'Record Waste / Damage')

@section('content')
<div class="page-grid">
    <!-- Record Waste Form -->
    <div>
        <div class="card form-container h-100">
            <div class="card-body d-flex flex-column">
                <h3 class="section-title"><i class="fas fa-trash-can text-primary"></i>Record Waste / Damage</h3>
                <p class="text-muted small mb-4">Log damaged, broken, or expired farm inventory and biological losses.</p>

                @if(session('success'))
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="fas fa-check-circle me-2"></i>{{ session('success') }}
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                @endif

                @if(session('error'))
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="fas fa-exclamation-circle me-2"></i>{{ session('error') }}
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                @endif

                <form method="POST" action="{{ route('waste.store') }}">
                    @csrf

                    <div class="mb-3">
                        <label class="form-label fw-bold">Waste Type</label>
                        <select name="waste_type" id="wasteTypeSelect" class="form-select" required onchange="toggleWasteTypeFields()">
                            <option value="supply" selected>Feed Spoilage or Supply Damage</option>
                            <option value="medicine">Expired Medicine or Biologics</option>
                            <option value="other">Other Farm Waste</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">Date of Loss</label>
                        <input type="date" name="waste_date" class="form-control" value="{{ date('Y-m-d') }}" required>
                    </div>

                    <!-- Inventory Item Selector -->
                    <div class="mb-3" id="inventoryItemGroup">
                        <label class="form-label fw-bold">Inventory Item <span class="text-danger">*</span></label>
                        <select name="inventory_id" id="inventorySelect" class="form-select">
                            <option value="">-- Select Inventory Item --</option>
                            @foreach($inventoryItems as $inv)
                                <option value="{{ $inv->inventory_id }}" data-unit="{{ $inv->unit }}" data-category="{{ $inv->category }}">
                                    {{ $inv->item_name }} ({{ ucfirst($inv->category) }})
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-7">
                            <label class="form-label fw-bold">Quantity</label>
                            <input type="number" step="0.01" min="0.01" name="quantity" class="form-control" placeholder="0" required>
                        </div>
                        <div class="col-5">
                            <label class="form-label fw-bold">Unit</label>
                            <input type="text" name="unit" id="wasteUnitInput" class="form-control" value="Bags" required>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">Reason for Waste</label>
                        <select name="reason" class="form-select" required>
                            <option value="spoiled" selected>Spoiled / Contaminated</option>
                            <option value="expired">Expired</option>
                            <option value="damaged">Damaged / Bag Tear</option>
                            <option value="cull">Cull / Defect</option>
                            <option value="other">Other</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">Batch (Optional)</label>
                        <select name="batch_id" class="form-select">
                            <option value="">-- None / General Farm --</option>
                            @foreach($batches as $b)
                                <option value="{{ $b->batch_id }}">{{ $b->batch_code }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">Estimated Cost Loss (₱)</label>
                        <input type="number" step="0.01" min="0" name="estimated_cost" class="form-control" placeholder="0.00 (Optional)">
                        <small class="text-muted">Optionally records an expense entry for accounting.</small>
                    </div>

                    <div class="mb-4">
                        <label class="form-label fw-bold">Remarks</label>
                        <textarea name="remarks" class="form-control" rows="2" placeholder="Additional notes..."></textarea>
                    </div>

                    <div class="form-actions-sticky mt-auto d-grid">
                        <button type="submit" name="save_waste" class="btn btn-primary w-100">
                            <i class="fas fa-save me-2"></i>Record Waste
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Waste & Damage Records List -->
    <div style="min-width: 0;">
        <div class="card h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
                    <div>
                        <h4 class="section-title mb-1 text-danger"><i class="fas fa-trash-can"></i>Waste & Damage Records</h4>
                        <p class="text-muted small mb-0">Audit history of farm write-offs, feed spoilage, and discarded supplies.</p>
                    </div>
                    <span class="badge bg-danger px-3 py-2 rounded-pill">
                        {{ $wasteRecords->count() }} Logged Records
                    </span>
                </div>

                <div class="table-responsive custom-scrollbar">
                    <table class="table table-hover align-middle">
                        <thead class="table-light sticky-top">
                            <tr>
                                <th>Date</th>
                                <th>Waste Type</th>
                                <th>Item / Grade</th>
                                <th>Reason</th>
                                <th class="text-end">Quantity</th>
                                <th class="text-end">Est. Loss Cost</th>
                                <th>Recorded By</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($wasteRecords as $w)
                            <tr>
                                <td>{{ date('M d, Y', strtotime($w->waste_date)) }}</td>
                                <td>
                                    <span class="badge bg-danger-subtle text-danger border border-danger-subtle text-uppercase px-2 py-1">
                                        {{ $w->waste_type }}
                                    </span>
                                </td>
                                <td>
                                    @if($w->inventory)
                                        <strong>{{ $w->inventory->item_name }}</strong>
                                    @else
                                        <span class="text-muted">General Loss</span>
                                    @endif
                                </td>
                                <td>
                                    <span class="badge bg-light text-dark border">
                                        {{ ucfirst($w->reason) }}
                                    </span>
                                    @if(!empty($w->remarks))
                                        <small class="text-muted d-block">{{ $w->remarks }}</small>
                                    @endif
                                </td>
                                <td class="text-end fw-bold">{{ number_format($w->quantity) }} {{ $w->unit }}</td>
                                <td class="text-end text-danger fw-semibold">
                                    {{ !empty($w->estimated_cost) ? '₱' . number_format($w->estimated_cost, 2) : '-' }}
                                </td>
                                <td class="small text-muted">{{ $w->recorder->full_name ?? 'System' }}</td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="7" class="text-center text-muted py-4">
                                    <i class="fas fa-inbox me-2"></i>No waste records logged yet.
                                </td>
                            </tr>
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
function toggleWasteTypeFields() {
    const type = document.getElementById('wasteTypeSelect').value;
    const invGroup = document.getElementById('inventoryItemGroup');
    const unitInput = document.getElementById('wasteUnitInput');

    if (type === 'medicine' || type === 'supply') {
        invGroup.classList.remove('d-none');
        unitInput.value = (type === 'supply') ? 'Bags' : 'Bottles';
    } else {
        invGroup.classList.add('d-none');
        unitInput.value = 'Units';
    }
}

document.getElementById('inventorySelect')?.addEventListener('change', function() {
    const selected = this.options[this.selectedIndex];
    const unit = selected.getAttribute('data-unit');
    if (unit) {
        document.getElementById('wasteUnitInput').value = unit;
    }
});
</script>
@endpush
