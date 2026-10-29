@extends('layouts.app')

@section('title', 'Retailer Directory')

@section('content')
<div class="page-grid">
    <!-- Left Column: Add / Edit Form -->
    <div>
        <div class="card form-container h-100">
            <div class="card-body d-flex flex-column">
                <h3 class="section-title"><i class="fas fa-plus-circle text-primary"></i><span id="formTitleText">Add New Retailer</span></h3>

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

                <form method="POST" action="{{ route('customers.store') }}" id="customerForm">
                    @csrf
                    <input type="hidden" name="customer_id" id="customer_id" value="">

                    <div class="mb-3">
                        <label class="form-label fw-bold">Retailer Code</label>
                        <input type="text" name="customer_code" class="form-control" placeholder="e.g. RET-001" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">Retailer Name</label>
                        <input type="text" name="name" class="form-control" placeholder="Enter retailer name" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">Manager Name</label>
                        <input type="text" name="contact_person" class="form-control" placeholder="Optional manager name">
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">Phone</label>
                        <input type="text" name="phone" class="form-control" placeholder="Contact number">
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">Email</label>
                        <input type="email" name="email" class="form-control" placeholder="email@example.com">
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">Address</label>
                        <textarea name="address" class="form-control" rows="2" placeholder="Optional address"></textarea>
                    </div>

                    <div class="mb-4">
                        <label class="form-label">Retailer Type</label>
                        <select name="customer_type" class="form-select" required>
                            <option value="retail">Retail</option>
                            <option value="wholesale">Wholesale</option>
                            <option value="institutional">Institutional</option>
                        </select>
                    </div>

                    <div class="form-actions-sticky mt-auto d-grid gap-2">
                        <button type="submit" name="save_customer" class="btn btn-primary w-100">
                            <i class="fas fa-save me-2"></i>Save Retailer Record
                        </button>
                        <button type="button" class="btn btn-secondary w-100" onclick="clearForm()">
                            <i class="fas fa-redo me-2"></i>Clear
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Retailers List Table (Right Column) -->
    <div style="min-width: 0;">
        <div class="card h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
                    <h3 class="section-title mb-0"><i class="fas fa-store me-2 text-primary"></i>Registered Retailers</h3>
                    <span class="badge bg-light text-dark border rounded-pill px-3 py-2 fw-medium">
                        <i class="fas fa-users me-1 text-primary"></i>{{ $customers->count() }} Registered
                    </span>
                </div>
                <div class="table-responsive custom-scrollbar">
                    <table class="table table-hover align-middle">
                        <thead class="table-light sticky-top">
                            <tr>
                                <th>Code</th>
                                <th>Name</th>
                                <th>Contact</th>
                                <th>Phone</th>
                                <th>Type</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($customers as $cust)
                                @php
                                    $isActive = (int)$cust->is_active === 1;
                                    $typeBadge = 'bg-secondary-subtle text-secondary';
                                    if ($cust->customer_type === 'retail') {
                                        $typeBadge = 'bg-info-subtle text-info';
                                    } elseif ($cust->customer_type === 'wholesale') {
                                        $typeBadge = 'bg-warning-subtle text-warning';
                                    } elseif ($cust->customer_type === 'institutional') {
                                        $typeBadge = 'bg-primary-subtle text-primary';
                                    }
                                @endphp
                                <tr class="{{ $isActive ? '' : 'table-secondary' }}">
                                    <td class="fw-bold text-primary">{{ $cust->customer_code }}</td>
                                    <td class="fw-bold">{{ $cust->name }}</td>
                                    <td>{!! $cust->contact_person ? e($cust->contact_person) : '<span class="text-muted small">—</span>' !!}</td>
                                    <td>{!! $cust->phone ? e($cust->phone) : '<span class="text-muted small">—</span>' !!}</td>
                                    <td>
                                        <span class="badge rounded-pill {{ $typeBadge }} border px-3">
                                            {{ ucfirst($cust->customer_type) }}
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge rounded-pill {{ $isActive ? 'bg-success-subtle text-success' : 'bg-secondary-subtle text-secondary' }} border px-3">
                                            {{ $isActive ? 'Active' : 'Inactive' }}
                                        </span>
                                    </td>
                                    <td>
                                        <button class="btn btn-icon"
                                                onclick='editCustomer({{ json_encode($cust->customer_id) }}, {{ json_encode($cust->customer_code) }}, {{ json_encode($cust->name) }}, {{ json_encode($cust->contact_person ?? '') }}, {{ json_encode($cust->phone ?? '') }}, {{ json_encode($cust->email ?? '') }}, {{ json_encode($cust->address ?? '') }}, {{ json_encode($cust->customer_type) }})'>
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        @if($isActive)
                                            <form method="POST" action="{{ route('customers.deactivate', $cust->customer_id) }}" class="d-inline" onsubmit="return confirm('Are you sure you want to deactivate this retailer? Existing reservations will retain the retailer reference.');">
                                                @csrf
                                                <button type="submit" class="btn btn-icon" style="color: var(--danger); border-color: var(--danger-bg); background-color: var(--danger-bg);">
                                                    <i class="fas fa-user-slash"></i>
                                                </button>
                                            </form>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="text-center text-muted py-4">
                                        <i class="fas fa-inbox me-2"></i>No retailers registered yet
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
function clearForm() {
    document.getElementById('customerForm').reset();
    document.getElementById('customer_id').value = '';
    document.getElementById('formTitleText').textContent = 'Add New Retailer';
    document.querySelector('[name="customer_code"]').focus();
}

function editCustomer(id, code, name, contactPerson, phone, email, address, customerType) {
    document.getElementById('customer_id').value = id;
    document.getElementById('formTitleText').textContent = 'Edit Retailer #' + code;
    document.querySelector('[name="customer_code"]').value = code;
    document.querySelector('[name="name"]').value = name;
    document.querySelector('[name="contact_person"]').value = contactPerson;
    document.querySelector('[name="phone"]').value = phone;
    document.querySelector('[name="email"]').value = email;
    document.querySelector('[name="address"]').value = address;
    document.querySelector('[name="customer_type"]').value = customerType;
    window.scrollTo({ top: 0, behavior: 'smooth' });
    document.querySelector('[name="customer_code"]').focus();
}
</script>
@endpush
