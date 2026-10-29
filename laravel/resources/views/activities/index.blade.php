@extends('layouts.app')

@section('title', 'Farm Operations & Worker Audit Trail')

@php
    $user_role = strtolower(auth()->user()->role ?? 'staff');
@endphp

@section('content')
<div class="container-fluid px-0">
    <div class="row g-4 align-items-start">
        @if($user_role === 'admin')
        <div class="col-md-4">
            <div class="card shadow-sm border rounded-4 form-container h-100">
                <div class="card-body d-flex flex-column">
                    <h3 class="section-title mb-4" style="font-size: 1.25rem;">
                        <i class="fas fa-edit text-primary me-2"></i>Record Activity
                    </h3>
                
                    @if(session('success'))
                        <div class="alert alert-success shadow-sm">{{ session('success') }}</div>
                    @endif
                    @if(session('error'))
                        <div class="alert alert-danger shadow-sm">{{ session('error') }}</div>
                    @endif

                    <form method="POST" action="{{ route('activities.store') }}">
                        @csrf
                        <div class="mb-3">
                            <label class="form-label fw-bold">Select Batch</label>
                            <select name="batch_id" class="form-select" required>
                                <option value="">-- Choose Batch --</option>
                                @foreach($batches as $b)
                                    <option value="{{ $b->batch_id }}">{{ $b->batch_code }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold">Predefined Task / Activity Type</label>
                            <select name="type" id="activityType" class="form-select" onchange="toggleFields()" required>
                                <option value="">-- Select Task --</option>
                                <option value="Feeding">Feeding</option>
                                <option value="Cleaning">Cleaning</option>
                                <option value="Health Inspection">Health Inspection</option>
                                <option value="Weight Sampling">Weight Sampling</option>
                                <option value="Supply">Supply</option>
                                <option value="General">General</option>
                            </select>
                        </div>

                        <div id="itemField" class="mb-3">
                            <label class="form-label fw-bold" id="itemLabel">Item Name</label>
                            <div id="inventorySelectContainer">
                                <input type="text" name="item_name" id="itemNameInput" class="form-control" placeholder="Enter name">
                            </div>
                        </div>

                        <div id="qtyField" class="mb-3">
                            <label class="form-label fw-bold" id="qtyLabel">Quantity</label>
                            <input type="number" step="0.01" name="quantity" id="qtyInput" class="form-control" placeholder="e.g. 5">
                            <small id="qtyHelp" class="text-muted d-none">Enter the number of broilers lost. No need for negative sign.</small>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold">Date</label>
                            <input type="date" name="date" class="form-control" value="{{ date('Y-m-d') }}" required>
                        </div>

                        <div id="remarksField" class="mb-3">
                            <label class="form-label fw-bold">Remarks</label>
                            <textarea name="remarks" class="form-control" rows="2"></textarea>
                        </div>

                        <div class="form-actions-sticky mt-auto d-grid">
                            <button type="submit" name="save_activity" class="btn btn-primary w-100">
                                <i class="fas fa-save me-2"></i>Save Activity
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        @endif

        <div class="{{ $user_role === 'admin' ? 'col-md-8' : 'col-md-12' }}">
            <div class="card shadow-sm border rounded-4">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                        <h5 class="fw-bold mb-0 text-dark" style="font-size: 1.15rem;">
                            <i class="fas fa-list-ul text-secondary me-2"></i>Recent Activities
                        </h5>
                        <span class="badge bg-success-subtle text-success border border-success-subtle px-3 py-2 rounded-pill shadow-sm">
                            <i class="fas fa-shield-alt me-1"></i>Secure Audit Log
                        </span>
                    </div>
                    <div class="table-responsive custom-scrollbar" style="min-height: 400px;">
                        <table class="table table-hover align-middle w-100 m-0" style="table-layout: fixed;">
                            <thead class="table-light sticky-top" style="border-bottom: 2px solid var(--border, #DDD6C4);">
                                <tr>
                                    <th style="width: 16%;" class="text-secondary fw-semibold text-uppercase small">Date / Time</th>
                                    <th style="width: 15%;" class="text-secondary fw-semibold text-uppercase small">Worker / Source</th>
                                    <th style="width: 14%;" class="text-secondary fw-semibold text-uppercase small">Batch</th>
                                    <th style="width: 16%;" class="text-secondary fw-semibold text-uppercase small">Activity Type</th>
                                    <th style="width: 24%;" class="text-secondary fw-semibold text-uppercase small">Activity / Item</th>
                                    <th style="width: 15%;" class="text-secondary fw-semibold text-uppercase small text-center">Status / Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($activities as $row)
                                    @php
                                        $timestampFormatted = !empty($row->created_at) 
                                            ? date('M d, Y h:i A', strtotime($row->created_at)) 
                                            : date('M d, Y', strtotime($row->date));
                                        $operationalDate = date('M d, Y', strtotime($row->date));

                                        $typeColor = 'bg-secondary text-white';
                                        $icon = 'fa-tasks';
                                        if ($row->type === 'Feeding') {
                                            $typeColor = 'bg-success-subtle text-success border border-success-subtle';
                                            $icon = 'fa-utensils';
                                        } elseif ($row->type === 'Cleaning') {
                                            $typeColor = 'bg-warning-subtle text-warning border border-warning-subtle';
                                            $icon = 'fa-broom';
                                        } elseif ($row->type === 'Health Inspection' || $row->type === 'Health') {
                                            $typeColor = 'bg-danger-subtle text-danger border border-danger-subtle';
                                            $icon = 'fa-stethoscope';
                                        } elseif ($row->type === 'Supply') {
                                            $typeColor = 'bg-info-subtle text-info border border-info-subtle';
                                            $icon = 'fa-box';
                                        } elseif ($row->type === 'Weight Sampling') {
                                            $typeColor = 'bg-primary-subtle text-primary border border-primary-subtle';
                                            $icon = 'fa-weight-scale';
                                        } elseif ($row->type === 'General') {
                                            $typeColor = 'bg-light text-secondary border border-secondary-subtle';
                                            $icon = 'fa-clipboard-check';
                                        }

                                        $workerName = $row->user->full_name ?? ($row->user->username ?? 'System / Unassigned');
                                        $workerRole = $row->user->role ?? null;
                                    @endphp
                                    <tr style="border-bottom: 1px solid rgba(0,0,0,0.05);">
                                        <td class="align-top pt-3">
                                            <div class="fw-bold text-dark small mb-1">{{ $timestampFormatted }}</div>
                                            <div class="text-muted" style="font-size: 0.75rem;">Op: {{ $operationalDate }}</div>
                                        </td>
                                        <td class="align-top pt-3">
                                            <div class="fw-bold text-dark text-truncate" style="max-width: 120px;">{{ $workerName }}</div>
                                            @if(!empty($workerRole))
                                                @php $roleBadgeClass = ($workerRole === 'admin') ? 'bg-dark text-white' : 'bg-light text-dark border'; @endphp
                                                <span class="badge {{ $roleBadgeClass }} mt-1 d-block w-75 text-truncate" style="font-size: 0.65rem;">
                                                    {{ ucfirst($workerRole) }}
                                                </span>
                                            @endif
                                        </td>
                                        <td class="align-top pt-3 fw-bold" style="color: var(--primary, #2E5B43);">
                                            {{ $row->batch->batch_code ?? 'General' }}
                                        </td>
                                        <td class="align-top pt-3">
                                            <span class="badge {{ $typeColor }} px-2 py-1 rounded-pill fw-medium">
                                                <i class="fas {{ $icon }} me-1"></i>{{ $row->type }}
                                            </span>
                                        </td>
                                        <td class="align-top pt-3 text-wrap">
                                            @if(!empty($row->item_name))
                                                <div class="fw-bold text-dark mb-1">{{ $row->item_name }}</div>
                                                @if($row->quantity !== null && (float)$row->quantity > 0)
                                                    <div class="small text-muted"><i class="fas fa-balance-scale me-1"></i>Qty: {{ number_format((float)$row->quantity, 2) }}</div>
                                                @endif
                                            @endif
                                            @if(!empty($row->remarks))
                                                <div class="small text-muted mt-1 fst-italic" style="white-space: normal;">"{{ $row->remarks }}"</div>
                                            @endif
                                            @if(empty($row->item_name) && empty($row->remarks))
                                                <span class="text-muted fst-italic">General Activity</span>
                                            @endif
                                        </td>
                                        <td class="align-middle">
                                            @if($row->status === 'pending')
                                                @if($user_role !== 'admin')
                                                    <form method="POST" action="{{ route('activities.complete', $row->activity_id) }}" class="m-0 d-flex justify-content-center">
                                                        @csrf
                                                        <button type="submit" class="btn btn-sm btn-outline-warning rounded-pill fw-bold">
                                                            Complete Action
                                                        </button>
                                                    </form>
                                                @else
                                                    <div class="text-center">
                                                        <span class="badge bg-white text-warning border border-warning px-3 py-2 rounded-pill"><i class="far fa-circle me-1"></i>Pending</span>
                                                    </div>
                                                @endif
                                            @else
                                                <div class="text-center">
                                                    <span class="badge bg-success-subtle text-success border border-success px-3 py-2 rounded-pill"><i class="fas fa-check-circle me-1"></i>Completed</span>
                                                </div>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="6" class="text-center text-muted py-4">No activity records found.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
function toggleFields() {
    var type = document.getElementById('activityType').value;
    var itemField = document.getElementById('itemField');
    var qtyField = document.getElementById('qtyField');
    var itemLabel = document.getElementById('itemLabel');
    var qtyLabel = document.getElementById('qtyLabel');
    var qtyHelp = document.getElementById('qtyHelp');
    var remarksField = document.getElementById('remarksField');
    var inventorySelectContainer = document.getElementById('inventorySelectContainer');
    var qtyInput = document.getElementById('qtyInput');

    if (type === 'Cleaning') {
        itemField.style.display = 'none';
        qtyField.style.display = 'none';
        remarksField.style.display = 'block';
        inventorySelectContainer.innerHTML = '<input type="hidden" name="item_name" value="Facility Cleaning">'; 
        qtyInput.removeAttribute('required');
        qtyInput.value = '0';
    } else if (type === 'General') {
        itemField.style.display = 'block';
        qtyField.style.display = 'block';
        remarksField.style.display = 'block';
        if (qtyHelp) qtyHelp.classList.add('d-none');
        if (qtyLabel) qtyLabel.innerHTML = 'Quantity / Value (Optional)';
        if (itemLabel) itemLabel.innerHTML = 'Task / Operation Name';
        inventorySelectContainer.innerHTML = '<input type="text" name="item_name" id="itemNameInput" class="form-control" placeholder="e.g., Ventilation Check, Coop Maintenance, Weighing" required>';
        qtyInput.removeAttribute('required');
        qtyInput.value = '';
    } else if (type === 'Health Inspection') {
        itemField.style.display = 'block';
        qtyField.style.display = 'block';
        remarksField.style.display = 'block';
        if (qtyHelp) qtyHelp.classList.add('d-none');
        if (qtyLabel) qtyLabel.innerHTML = 'Quantity / Dosage (Optional)';
        if (itemLabel) itemLabel.innerHTML = 'Medicine / Supplement (Optional)';
        qtyInput.removeAttribute('required');
        
        fetch('{{ route("activities.inventory-items") }}?type=' + encodeURIComponent(type))
            .then(response => response.json())
            .then(data => {
                let html = '<select name="inventory_id" id="itemNameInput" class="form-select">';
                html += '<option value="">-- None / Visual Inspection Only --</option>';
                data.forEach(item => {
                    html += `<option value="${item.inventory_id}">${item.item_name} (${item.quantity} ${item.unit} available)</option>`;
                });
                html += '</select>';
                inventorySelectContainer.innerHTML = html;
            })
            .catch(error => {
                inventorySelectContainer.innerHTML = '<input type="text" name="item_name" id="itemNameInput" class="form-control" placeholder="Enter inspection details">';
            });
    } else if (type === 'Feeding' || type === 'Supply') {
        itemField.style.display = 'block';
        qtyField.style.display = 'block';
        remarksField.style.display = 'block';
        if (qtyHelp) qtyHelp.classList.add('d-none');
        qtyInput.setAttribute('required', '');
        
        if (type === 'Feeding') {
            if (itemLabel) itemLabel.innerHTML = 'Feed Item (Inventory)';
            if (qtyLabel) qtyLabel.innerHTML = 'Quantity Used (kg / bags)';
        } else {
            if (itemLabel) itemLabel.innerHTML = 'Supply Item (Inventory)';
            if (qtyLabel) qtyLabel.innerHTML = 'Quantity Used';
        }

        fetch('{{ route("activities.inventory-items") }}?type=' + encodeURIComponent(type))
            .then(response => response.json())
            .then(data => {
                let html = '<select name="inventory_id" id="itemNameInput" class="form-select" required>';
                html += '<option value="">-- Select Item --</option>';
                data.forEach(item => {
                    html += `<option value="${item.inventory_id}">${item.item_name} (${item.quantity} ${item.unit} available)</option>`;
                });
                html += '</select>';
                inventorySelectContainer.innerHTML = html;
            })
            .catch(error => {
                inventorySelectContainer.innerHTML = '<input type="text" name="item_name" id="itemNameInput" class="form-control" placeholder="Enter name" required>';
            });
    } else {
        itemField.style.display = 'block';
        qtyField.style.display = 'block';
        remarksField.style.display = 'block';
        if (qtyHelp) qtyHelp.classList.add('d-none');
        if (itemLabel) itemLabel.innerHTML = 'Item Name';
        if (qtyLabel) qtyLabel.innerHTML = 'Quantity';
        inventorySelectContainer.innerHTML = '<input type="text" name="item_name" id="itemNameInput" class="form-control" placeholder="Enter name">';
        qtyInput.removeAttribute('required');
    }
}
</script>
@endpush
