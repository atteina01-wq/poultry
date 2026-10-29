@extends('layouts.app')

@section('title', 'Broiler Reservation Management')

@push('styles')
<style>
    /* Custom Reservation Voucher Styles */
    .voucher-sheet {
        background-color: #ffffff !important;
        color: #1e293b !important;
        border: 1px solid #e2e8f0;
        border-top: 5px solid #2E5B43;
        border-radius: 16px;
        box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.06), 0 8px 10px -6px rgba(0, 0, 0, 0.04);
        padding: 2.25rem 2.5rem;
        position: relative;
    }
    .voucher-brand-icon {
        width: 44px;
        height: 44px;
        background: linear-gradient(135deg, #2E5B43 0%, #163023 100%) !important;
        color: #ffffff;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border-radius: 12px;
        box-shadow: 0 4px 10px rgba(46, 91, 67, 0.25);
        flex-shrink: 0;
    }
    .voucher-badge-number {
        font-family: SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", monospace;
        font-size: 1.2rem;
        font-weight: 700;
        color: #163023;
        letter-spacing: 0.5px;
    }
    .voucher-info-box {
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        padding: 1.1rem 1.25rem;
        height: 100%;
    }
    .voucher-table {
        width: 100%;
        margin-bottom: 0;
        border-collapse: separate;
        border-spacing: 0;
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        overflow: hidden;
    }
    .voucher-table thead th {
        background-color: #244A37 !important;
        color: #ffffff !important;
        font-size: 0.72rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.75px;
        padding: 12px 16px;
        border: none;
    }
    .voucher-table tbody td {
        padding: 14px 16px;
        border-bottom: 1px solid #f1f5f9;
        color: #334155;
        background-color: #ffffff !important;
        font-size: 0.88rem;
        vertical-align: middle;
    }
    .voucher-table tbody tr:last-child td {
        border-bottom: none;
    }
    .voucher-summary-card {
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        padding: 1.25rem 1.5rem;
    }
    .voucher-sig-line {
        border-bottom: 1.5px solid #334155;
        width: 220px;
        margin: 35px auto 8px auto;
    }
    @media print {
        body * { visibility: hidden; }
        .voucher-sheet, .voucher-sheet * { visibility: visible; }
        .voucher-sheet { position: absolute; left: 0; top: 0; width: 100%; border: none; box-shadow: none; }
        .no-print { display: none !important; }
    }
</style>
@endpush

@section('content')
<div class="container-fluid px-0">
    <!-- Header -->
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
        <div>
            <h4 class="fw-bold mb-1"><i class="fas fa-calendar-check me-2 text-success"></i>Broiler Chicken Reservation Management</h4>
            <p class="text-muted small mb-0">Manage commercial customer bookings, track advance payments & balances, and record pickup fulfillment.</p>
        </div>
        <div class="d-flex gap-2">
            <button type="button" class="btn btn-success rounded-pill px-4 fw-semibold" data-bs-toggle="modal" data-bs-target="#newBroilerReservationModal">
                <i class="fas fa-plus-circle me-1"></i>New Broiler Reservation
            </button>
        </div>
    </div>

    <!-- House Pen Occupancy Cards Grid -->
    <div class="row g-3 mb-4">
        @foreach($houses as $h)
            @php
                $statusColor = match($h->status) {
                    'available' => 'success',
                    'occupied' => 'danger',
                    'reserved' => 'info',
                    default => 'warning'
                };
                $statusLabel = match($h->status) {
                    'available' => '🟢 Available',
                    'occupied' => '🔴 Occupied',
                    'reserved' => '🔵 Reserved',
                    default => '🟡 Cleaning'
                };
                $batch = $h->currentBatch;
                $ageDays = $batch && $batch->arrival_date ? max(1, (int)now()->diffInDays(\Carbon\Carbon::parse($batch->arrival_date))) : 0;
                $curW = $batch ? (float)($batch->current_weight_kg ?? 0) : 0;
                $tarW = $batch ? (float)($batch->target_weight_kg ?? 1.80) : 1.80;
            @endphp
            <div class="col-md-4 col-xl-2">
                <div class="card h-100 border-0 shadow-sm rounded-4 p-3 bg-white">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <span class="fw-bold text-dark fs-6">{{ $h->house_name }}</span>
                        <span class="badge bg-{{ $statusColor }} bg-opacity-10 text-{{ $statusColor }} px-2 py-1 rounded-pill small">
                            {{ $statusLabel }}
                        </span>
                    </div>
                    <div class="small text-muted mb-2">Cap: {{ number_format($h->capacity) }} birds</div>

                    @if($batch)
                        <div class="p-2 rounded bg-light border border-light-subtle mb-3 small">
                            <div class="fw-bold text-primary mb-1">{{ $batch->batch_code }}</div>
                            <div class="text-muted">Day {{ $ageDays }} &bull; {{ number_format($batch->current_quantity) }} heads</div>
                            <div class="mt-1 fw-semibold {{ $curW >= $tarW ? 'text-success' : 'text-secondary' }}">
                                {{ $curW > 0 ? $curW . ' kg' : 'Not weighed' }} / {{ $tarW }} kg
                            </div>
                        </div>
                    @else
                        <div class="p-2 rounded bg-light text-center text-muted mb-3 small py-3">
                            <em>Ready for intake</em>
                        </div>
                    @endif

                    <div class="mt-auto d-flex flex-column gap-1">
                        @if($batch)
                            <button type="button" class="btn btn-xs btn-outline-primary rounded-pill py-1 small" onclick="openQuickWeighModal({{ $batch->batch_id }}, '{{ $batch->batch_code }}', '{{ $h->house_name }}', {{ $curW }}, {{ $tarW }}, {{ $ageDays }})">
                                <i class="fas fa-weight-scale me-1"></i>Weight Check
                            </button>
                            <button type="button" class="btn btn-xs btn-outline-success rounded-pill py-1 small" onclick="prefillReserveBatch({{ $batch->batch_id }}, '{{ $batch->batch_code }}', {{ $batch->current_quantity }}, {{ $h->house_id }}, {{ $tarW }})">
                                <i class="fas fa-calendar-check me-1"></i>Book Birds
                            </button>
                        @else
                            <button type="button" class="btn btn-xs btn-outline-success rounded-pill py-1 small" onclick="prefillReserveHouse({{ $h->house_id }}, '{{ $h->house_name }}', {{ $h->capacity }})">
                                <i class="fas fa-plus me-1"></i>Reserve Pen
                            </button>
                        @endif
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <!-- KPI Summary Row -->
    <div class="row g-3 mb-4">
        <div class="col-sm-6 col-lg-6">
            <div class="p-3 bg-white rounded-4 border shadow-sm">
                <div class="text-muted small fw-semibold text-uppercase">Active Bookings</div>
                <div class="fs-4 fw-bold text-success mt-1">{{ number_format($kpiActiveCount) }}</div>
                <small class="text-muted">Awaiting pickup/fulfillment</small>
            </div>
        </div>
        <div class="col-sm-6 col-lg-6">
            <div class="p-3 bg-white rounded-4 border shadow-sm">
                <div class="text-muted small fw-semibold text-uppercase">Reserved Birds</div>
                <div class="fs-4 fw-bold text-primary mt-1">{{ number_format($kpiReservedBirds) }} birds</div>
                <small class="text-muted">Live broilers held</small>
            </div>
        </div>
    </div>

    <!-- Reservations Table and Filter Bar -->
    <div class="card shadow-sm border-0 rounded-4 bg-white p-4">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
            <!-- Filter Tabs -->
            <ul class="nav nav-pills gap-1">
                <li class="nav-item">
                    <a class="nav-link rounded-pill px-3 py-1 small fw-semibold {{ $statusFilter === 'all' ? 'active bg-success' : 'text-dark bg-light' }}" href="{{ route('reservations.index', ['status' => 'all', 'batch_id' => $batchFilter, 'search' => $search]) }}">
                        All
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link rounded-pill px-3 py-1 small fw-semibold {{ $statusFilter === 'active' ? 'active bg-success' : 'text-dark bg-light' }}" href="{{ route('reservations.index', ['status' => 'active', 'batch_id' => $batchFilter, 'search' => $search]) }}">
                        Active
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link rounded-pill px-3 py-1 small fw-semibold {{ $statusFilter === 'fulfilled' ? 'active bg-primary' : 'text-dark bg-light' }}" href="{{ route('reservations.index', ['status' => 'fulfilled', 'batch_id' => $batchFilter, 'search' => $search]) }}">
                        Fulfilled
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link rounded-pill px-3 py-1 small fw-semibold {{ $statusFilter === 'cancelled' ? 'active bg-danger' : 'text-dark bg-light' }}" href="{{ route('reservations.index', ['status' => 'cancelled', 'batch_id' => $batchFilter, 'search' => $search]) }}">
                        Cancelled
                    </a>
                </li>
            </ul>

            <!-- Search and Batch Filter -->
            <form method="GET" action="{{ route('reservations.index') }}" class="d-flex gap-2">
                <input type="hidden" name="status" value="{{ $statusFilter }}">
                <select name="batch_id" class="form-select form-select-sm" style="width: auto;" onchange="this.form.submit()">
                    <option value="0">All Batches</option>
                    @foreach($batches as $b)
                        <option value="{{ $b->batch_id }}" {{ $batchFilter == $b->batch_id ? 'selected' : '' }}>
                            {{ $b->batch_code }}
                        </option>
                    @endforeach
                </select>
                <div class="input-group input-group-sm" style="width: 220px;">
                    <input type="text" name="search" class="form-control" placeholder="Search booking..." value="{{ $search }}">
                    <button class="btn btn-outline-secondary" type="submit"><i class="fas fa-search"></i></button>
                </div>
            </form>
        </div>

        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="text-secondary small fw-bold">Ref / No.</th>
                        <th class="text-secondary small fw-bold">Retailer / Buyer</th>
                        <th class="text-secondary small fw-bold">Broiler Batch</th>
                        <th class="text-secondary small fw-bold text-center">Agreed Target</th>
                        <th class="text-secondary small fw-bold text-center">Birds & Advance</th>
                        <th class="text-secondary small fw-bold">Pickup Date</th>
                        <th class="text-secondary small fw-bold text-center">Status</th>
                        <th class="text-secondary small fw-bold text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($reservations as $r)
                        @php
                            $bal = max(0, $r->total_amount - $r->advance_payment);
                            $badgeColor = match($r->status) {
                                'active' => 'success',
                                'fulfilled' => 'primary',
                                'cancelled' => 'danger',
                                default => 'secondary'
                            };
                        @endphp
                        <tr>
                            <td>
                                <span class="fw-bold text-dark font-monospace">{{ $r->reservation_number }}</span>
                                <div class="text-muted" style="font-size: 11px;">{{ date('M d, Y', strtotime($r->created_at)) }}</div>
                            </td>
                            <td>
                                <div class="fw-bold text-dark">{{ $r->customer->name ?? 'Retailer #' . $r->customer_id }}</div>
                                <div class="text-muted small">{{ $r->customer->phone ?? '' }}</div>
                            </td>
                            <td>
                                @if($r->batch)
                                    <span class="badge bg-light text-primary border border-primary-subtle px-2 py-1">
                                        {{ $r->batch->batch_code }}
                                    </span>
                                    @if($r->house)
                                        <span class="badge bg-light text-secondary border px-1 py-1 small">{{ $r->house->house_name }}</span>
                                    @endif
                                @else
                                    <span class="text-muted small">No Batch</span>
                                @endif
                            </td>
                            <td class="text-center">
                                <span class="badge bg-success bg-opacity-10 text-success fw-bold">
                                    {{ number_format($r->target_weight_per_bird, 2) }} kg/bird
                                </span>
                            </td>
                            <td class="text-center">
                                <div class="fw-bold text-dark">{{ number_format($r->quantity_birds) }} heads</div>
                                <div class="small text-muted">Adv: ₱{{ number_format($r->advance_payment, 2) }} &bull; Bal: <span class="text-danger fw-semibold">₱{{ number_format($bal, 2) }}</span></div>
                            </td>
                            <td>
                                <span class="small fw-semibold text-dark">{{ date('M d, Y', strtotime($r->pickup_date)) }}</span>
                            </td>
                            <td class="text-center">
                                <span class="badge bg-{{ $badgeColor }} bg-opacity-10 text-{{ $badgeColor }} px-2 py-1 text-uppercase fw-semibold">
                                    {{ $r->status }}
                                </span>
                            </td>
                            <td class="text-end">
                                <div class="btn-group btn-group-sm">
                                    <button type="button" class="btn btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#voucherModal{{ $r->reservation_id }}" title="Print Official Voucher">
                                        <i class="fas fa-print"></i>
                                    </button>
                                    @if($r->status === 'active')
                                        <button type="button" class="btn btn-outline-success" data-bs-toggle="modal" data-bs-target="#fulfillModal{{ $r->reservation_id }}" title="Fulfill / Dispatch">
                                            <i class="fas fa-check-double"></i>
                                        </button>
                                        <button type="button" class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#editResModal{{ $r->reservation_id }}" title="Edit Booking">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <form method="POST" action="{{ route('reservations.cancel', $r->reservation_id) }}" class="d-inline" onsubmit="return confirm('Cancel this reservation?')">
                                            @csrf
                                            <button type="submit" class="btn btn-outline-danger" title="Cancel Booking">
                                                <i class="fas fa-ban"></i>
                                            </button>
                                        </form>
                                    @elseif($r->status === 'cancelled')
                                        <form method="POST" action="{{ route('reservations.restore', $r->reservation_id) }}" class="d-inline">
                                            @csrf
                                            <button type="submit" class="btn btn-outline-warning" title="Restore to Active">
                                                <i class="fas fa-rotate-left"></i>
                                            </button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>

                        <!-- MODAL: Printable Voucher -->
                        <div class="modal fade" id="voucherModal{{ $r->reservation_id }}" tabindex="-1">
                            <div class="modal-dialog modal-dialog-centered modal-lg">
                                <div class="modal-content border-0 rounded-4 shadow">
                                    <div class="modal-header border-0 pb-0 no-print">
                                        <h5 class="modal-title fw-bold text-dark"><i class="fas fa-file-invoice text-success me-2"></i>Official Reservation Voucher</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                    </div>
                                    <div class="modal-body p-4">
                                        <div class="voucher-sheet">
                                            <div class="d-flex justify-content-between align-items-start mb-4 pb-3 border-bottom">
                                                <div class="d-flex align-items-center gap-3">
                                                    <div class="voucher-brand-icon">
                                                        <i class="fas fa-drumstick-bite fs-4"></i>
                                                    </div>
                                                    <div>
                                                        <h5 class="fw-bold mb-0 text-dark">Alquizar Poultry Farm</h5>
                                                        <span class="small text-muted">Tampakan, South Cotabato &bull; Contract Growing Facility</span>
                                                    </div>
                                                </div>
                                                <div class="text-end">
                                                    <span class="badge bg-light text-secondary border px-2 py-1 small">OFFICIAL BOOKING</span>
                                                    <div class="voucher-badge-number mt-1">{{ $r->reservation_number }}</div>
                                                </div>
                                            </div>

                                            <div class="row g-3 mb-4">
                                                <div class="col-6">
                                                    <div class="voucher-info-box">
                                                        <span class="small fw-bold text-muted text-uppercase">Buyer / Retailer</span>
                                                        <h6 class="fw-bold mt-1 mb-1">{{ $r->customer->name ?? 'Retailer' }}</h6>
                                                        <div class="small text-muted">{{ $r->customer->phone ?? '' }}</div>
                                                        <div class="small text-muted">{{ $r->customer->address ?? '' }}</div>
                                                    </div>
                                                </div>
                                                <div class="col-6">
                                                    <div class="voucher-info-box">
                                                        <span class="small fw-bold text-muted text-uppercase">Flock & Logistics</span>
                                                        <h6 class="fw-bold mt-1 mb-1">{{ $r->batch->batch_code ?? 'Batch' }}</h6>
                                                        <div class="small text-muted">Pen / Coop: {{ $r->house->house_name ?? 'Designated House' }}</div>
                                                        <div class="small text-muted">Pickup Date: <strong>{{ date('F d, Y', strtotime($r->pickup_date)) }}</strong></div>
                                                    </div>
                                                </div>
                                            </div>

                                            <table class="voucher-table mb-4">
                                                <thead>
                                                    <tr>
                                                        <th>Particulars</th>
                                                        <th class="text-center">Reserved Count</th>
                                                        <th class="text-center">Target Weight</th>
                                                        <th class="text-end">Total Price</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <tr>
                                                        <td>
                                                            <strong>Live Broiler Chickens</strong><br>
                                                            <small class="text-muted">Standard release specifications</small>
                                                        </td>
                                                        <td class="text-center fw-bold">{{ number_format($r->quantity_birds) }} heads</td>
                                                        <td class="text-center fw-bold">{{ number_format($r->target_weight_per_bird, 2) }} kg/bird</td>
                                                        <td class="text-end fw-bold">₱{{ number_format($r->total_amount, 2) }}</td>
                                                    </tr>
                                                </tbody>
                                            </table>

                                            <div class="row align-items-center mb-4">
                                                <div class="col-6">
                                                    <div class="small text-muted">
                                                        <strong>Notes:</strong> {{ $r->notes ?: 'Subject to final weight upon loading.' }}
                                                    </div>
                                                </div>
                                                <div class="col-6">
                                                    <div class="voucher-summary-card">
                                                        <div class="d-flex justify-content-between mb-1 small">
                                                            <span>Total Amount:</span>
                                                            <strong>₱{{ number_format($r->total_amount, 2) }}</strong>
                                                        </div>
                                                        <div class="d-flex justify-content-between mb-1 small text-success">
                                                            <span>Advance Deposit:</span>
                                                            <strong>-₱{{ number_format($r->advance_payment, 2) }}</strong>
                                                        </div>
                                                        <div class="d-flex justify-content-between pt-2 border-top fs-6 fw-bold text-danger">
                                                            <span>Remaining Balance:</span>
                                                            <span>₱{{ number_format($bal, 2) }}</span>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="row text-center mt-5">
                                                <div class="col-6">
                                                    <div class="voucher-sig-line"></div>
                                                    <span class="small text-muted">Prepared By / Farm Management</span>
                                                </div>
                                                <div class="col-6">
                                                    <div class="voucher-sig-line"></div>
                                                    <span class="small text-muted">Authorized Buyer / Receiver</span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="modal-footer border-0 no-print">
                                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Close</button>
                                        <button type="button" class="btn btn-primary" onclick="window.print()">
                                            <i class="fas fa-print me-1"></i> Print Voucher
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- MODAL: Fulfill / Harvest -->
                        <div class="modal fade" id="fulfillModal{{ $r->reservation_id }}" tabindex="-1">
                            <div class="modal-dialog modal-dialog-centered">
                                <div class="modal-content border-0 rounded-4 shadow">
                                    <div class="modal-header border-0 pb-0">
                                        <h5 class="modal-title fw-bold text-dark"><i class="fas fa-check-double text-success me-2"></i>Fulfill / Dispatch Reservation</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                    </div>
                                    <form method="POST" action="{{ route('reservations.fulfill', $r->reservation_id) }}">
                                        @csrf
                                        <div class="modal-body p-4">
                                            <div class="mb-3">
                                                <label class="form-label small fw-bold">Actual Pickup Date <span class="text-danger">*</span></label>
                                                <input type="date" name="actual_pickup_date" class="form-control" value="{{ date('Y-m-d') }}" required>
                                            </div>
                                            <div class="row g-3 mb-3">
                                                <div class="col-6">
                                                    <label class="form-label small fw-bold">Accepted Birds (Heads) <span class="text-danger">*</span></label>
                                                    <input type="number" min="0" name="accepted_quantity" class="form-control" value="{{ $r->quantity_birds }}" required>
                                                </div>
                                                <div class="col-6">
                                                    <label class="form-label small fw-bold">Rejected Birds</label>
                                                    <input type="number" min="0" name="rejected_quantity" class="form-control" value="0">
                                                </div>
                                            </div>
                                            <div class="row g-3 mb-3">
                                                <div class="col-6">
                                                    <label class="form-label small fw-bold">Total Weight (kg) <span class="text-danger">*</span></label>
                                                    <input type="number" step="0.01" min="0" name="actual_weight_kg" class="form-control" value="{{ $r->weight_kg }}" required>
                                                </div>
                                                <div class="col-6">
                                                    <label class="form-label small fw-bold">Actual Total Price (₱) <span class="text-danger">*</span></label>
                                                    <input type="number" step="0.01" min="0" name="actual_price" class="form-control" value="{{ $r->total_amount }}" required>
                                                </div>
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label small fw-bold">Official Receipt / OR No. <span class="text-danger">*</span></label>
                                                <input type="text" name="receipt_number" class="form-control" value="OR-{{ date('Ymd') }}-{{ str_pad($r->reservation_id, 4, '0', STR_PAD_LEFT) }}" required>
                                            </div>
                                        </div>
                                        <div class="modal-footer border-0 pt-0">
                                            <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                                            <button type="submit" class="btn btn-success fw-bold px-4">Complete Fulfillment</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>

                        <!-- MODAL: Edit Reservation -->
                        <div class="modal fade" id="editResModal{{ $r->reservation_id }}" tabindex="-1">
                            <div class="modal-dialog modal-dialog-centered modal-lg">
                                <div class="modal-content border-0 rounded-4 shadow">
                                    <div class="modal-header border-0 pb-0">
                                        <h5 class="modal-title fw-bold text-dark"><i class="fas fa-edit text-primary me-2"></i>Edit Reservation #{{ $r->reservation_number }}</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                    </div>
                                    <form method="POST" action="{{ route('reservations.update', $r->reservation_id) }}">
                                        @csrf
                                        @method('PUT')
                                        <div class="modal-body p-4">
                                            <div class="row g-3 mb-3">
                                                <div class="col-6">
                                                    <label class="form-label small fw-bold">Retailer <span class="text-danger">*</span></label>
                                                    <select name="customer_id" class="form-select" required>
                                                        @foreach($customers as $c)
                                                            <option value="{{ $c->customer_id }}" {{ $c->customer_id == $r->customer_id ? 'selected' : '' }}>
                                                                {{ $c->name }} ({{ $c->customer_code }})
                                                            </option>
                                                        @endforeach
                                                    </select>
                                                </div>
                                                <div class="col-6">
                                                    <label class="form-label small fw-bold">Broiler Batch <span class="text-danger">*</span></label>
                                                    <select name="batch_id" class="form-select" required>
                                                        @foreach($batches as $b)
                                                            <option value="{{ $b->batch_id }}" {{ $b->batch_id == $r->batch_id ? 'selected' : '' }}>
                                                                {{ $b->batch_code }}
                                                            </option>
                                                        @endforeach
                                                    </select>
                                                </div>
                                            </div>
                                            <div class="row g-3 mb-3">
                                                <div class="col-4">
                                                    <label class="form-label small fw-bold">Reserved Birds</label>
                                                    <input type="number" min="1" name="quantity_birds" class="form-control" value="{{ $r->quantity_birds }}" required>
                                                </div>
                                                <div class="col-4">
                                                    <label class="form-label small fw-bold">Total Amount (₱)</label>
                                                    <input type="number" step="0.01" min="0" name="total_amount" class="form-control" value="{{ $r->total_amount }}" required>
                                                </div>
                                                <div class="col-4">
                                                    <label class="form-label small fw-bold">Advance Payment (₱)</label>
                                                    <input type="number" step="0.01" min="0" name="advance_payment" class="form-control" value="{{ $r->advance_payment }}">
                                                </div>
                                            </div>
                                            <div class="row g-3 mb-3">
                                                <div class="col-6">
                                                    <label class="form-label small fw-bold">Pickup Date</label>
                                                    <input type="date" name="pickup_date" class="form-control" value="{{ $r->pickup_date }}" required>
                                                </div>
                                                <div class="col-6">
                                                    <label class="form-label small fw-bold">Status</label>
                                                    <select name="status" class="form-select">
                                                        <option value="active" {{ $r->status == 'active' ? 'selected' : '' }}>Active</option>
                                                        <option value="fulfilled" {{ $r->status == 'fulfilled' ? 'selected' : '' }}>Fulfilled</option>
                                                        <option value="cancelled" {{ $r->status == 'cancelled' ? 'selected' : '' }}>Cancelled</option>
                                                    </select>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="modal-footer border-0 pt-0">
                                            <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                                            <button type="submit" class="btn btn-primary fw-bold px-4">Save Changes</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    @empty
                        <tr>
                            <td colspan="8" class="text-center py-4 text-muted">
                                <i class="fas fa-calendar-xmark text-muted fs-3 mb-2 d-block"></i>
                                No broiler reservations found for this filter.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-3">
            {{ $reservations->withQueryString()->links() }}
        </div>
    </div>
</div>

<!-- MODAL: New Broiler Reservation -->
<div class="modal fade" id="newBroilerReservationModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 rounded-4 shadow">
            <form method="POST" action="{{ route('reservations.store') }}" id="newBroilerReservationForm">
                @csrf
                <div class="modal-header bg-success text-white border-0 rounded-top-4">
                    <h5 class="modal-title fw-bold">
                        <i class="fas fa-calendar-plus me-2"></i>New Broiler Chicken / Pen Reservation
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="alert alert-info py-2 px-3 rounded-3 small mb-3">
                        <i class="fas fa-info-circle me-1"></i><strong>Contract Growing & Reservation Rules:</strong>
                        Every reservation must link directly to an <strong>Active Broiler Batch</strong>.
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-4">
                            <label class="form-label small fw-bold">Retailer / Partner Company <span class="text-danger">*</span></label>
                            <select name="customer_id" id="newResCustomerId" class="form-select" required>
                                <option value="">-- Choose Retailer --</option>
                                @foreach($customers as $c)
                                    <option value="{{ $c->customer_id }}">{{ $c->name }} ({{ $c->customer_code }})</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-bold">Reserve Pen / House</label>
                            <select name="house_id" id="newResHouseId" class="form-select" onchange="onHouseSelectChanged(this)">
                                <option value="">-- Select Pen (Optional) --</option>
                                @foreach($houses as $h)
                                    <option value="{{ $h->house_id }}" data-capacity="{{ $h->capacity }}" data-name="{{ $h->house_name }}">
                                        {{ $h->house_name }} (Cap: {{ number_format($h->capacity) }})
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-bold">Active Flock / Batch <span class="text-danger">*</span></label>
                            <select name="batch_id" id="newResBatchId" class="form-select border-primary fw-semibold" onchange="onBatchSelectChanged(this)" required>
                                <option value="" disabled selected>-- Select Broiler Batch * --</option>
                                @foreach($batches as $b)
                                    <option value="{{ $b->batch_id }}" data-quantity="{{ $b->current_quantity }}" data-target="{{ $b->target_weight_kg ?? 1.80 }}" data-house="{{ $b->house_pen }}">
                                        {{ $b->batch_code }} ({{ number_format($b->current_quantity) }} birds)
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-3">
                            <label class="form-label small fw-bold">Reserved Birds <span class="text-danger">*</span></label>
                            <input type="number" min="1" name="quantity_birds" id="newResBirds" class="form-control fw-bold" placeholder="5000" required oninput="calcNewResBalance()">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small fw-bold text-success">Agreed Target Weight (kg) <span class="text-danger">*</span></label>
                            <input type="number" step="0.01" min="0.5" max="5.0" name="target_weight_per_bird" id="newResTargetWeight" class="form-control fw-bold text-success" value="1.80" required oninput="calcNewResBalance()">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small fw-bold">Estimated Live Weight (kg)</label>
                            <input type="number" step="0.01" min="0" name="weight_kg" id="newResWeight" class="form-control font-monospace" placeholder="9000.00" oninput="calcNewResBalance()">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small fw-bold">Agreed Unit Price (₱)</label>
                            <input type="number" step="0.01" min="0" name="unit_price" id="newResUnitPrice" class="form-control font-monospace" value="180.00" oninput="calcNewResBalance()">
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-4">
                            <label class="form-label small fw-bold">Total Contract Amount (₱) <span class="text-danger">*</span></label>
                            <input type="number" step="0.01" min="0" name="total_amount" id="newResTotal" class="form-control font-monospace fw-bold" placeholder="0.00" required oninput="calcNewResBalance(true)">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-bold">Advance Payment (₱) <span class="text-danger">*</span></label>
                            <input type="number" step="0.01" min="0" name="advance_payment" id="newResAdvance" class="form-control font-monospace" value="0.00" required oninput="calcNewResBalance(true)">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-bold text-danger">Remaining Balance (₱)</label>
                            <div class="p-2 bg-light rounded border text-danger fw-bold fs-5 text-center font-monospace" id="newResBalanceDisplay">₱0.00</div>
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Expected Harvest / Pickup Date <span class="text-danger">*</span></label>
                            <input type="date" name="pickup_date" class="form-control" value="{{ date('Y-m-d', strtotime('+3 days')) }}" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Contract Notes / Logistics</label>
                            <textarea name="notes" class="form-control" rows="1" placeholder="e.g. 1.80kg average target, logistics details"></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success rounded-pill px-4 fw-bold">Save Reservation</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- MODAL: Quick Weight Sampling -->
<div class="modal fade" id="quickWeighModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 rounded-4 shadow">
            <form method="POST" id="quickWeighForm">
                @csrf
                <div class="modal-header bg-primary text-white border-0 rounded-top-4">
                    <h5 class="modal-title fw-bold"><i class="fas fa-weight-scale me-2"></i>Record Flock Weight Sampling</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="p-3 bg-light rounded-3 mb-3 border">
                        <div class="d-flex justify-content-between mb-1">
                            <span class="text-muted small">House Location:</span>
                            <span class="fw-bold" id="qwHouseName">-</span>
                        </div>
                        <div class="d-flex justify-content-between mb-1">
                            <span class="text-muted small">Active Flock:</span>
                            <span class="fw-bold text-primary" id="qwBatchCode">-</span>
                        </div>
                        <div class="d-flex justify-content-between">
                            <span class="text-muted small">Target Weight:</span>
                            <span class="fw-bold text-success" id="qwTargetWeight">1.80 kg</span>
                        </div>
                    </div>

                    <div class="mb-3">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <label class="form-label small fw-bold mb-0">Average Weight (kg) <span class="text-danger">*</span></label>
                            <button type="button" class="btn btn-xs btn-outline-primary rounded-pill px-2 py-0" style="font-size:0.75rem;" onclick="autoFillQuickWeighModal()">
                                <i class="fas fa-wand-magic-sparkles text-warning me-1"></i>Auto-Fill Cobb 500
                            </button>
                        </div>
                        <input type="number" step="0.01" min="0.05" max="5.0" name="weight_kg" id="qwWeight" class="form-control fs-5 fw-bold text-primary" required>
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-light rounded-pill px-3" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary rounded-pill px-4 fw-bold">Save Weight Sample</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
let totalEditedManually = false;

function calcNewResBalance(isManual = false) {
    if (isManual) totalEditedManually = true;

    const birds = parseFloat(document.getElementById('newResBirds')?.value) || 0;
    const targetW = parseFloat(document.getElementById('newResTargetWeight')?.value) || 1.80;
    const weightField = document.getElementById('newResWeight');

    if (birds > 0 && weightField && (!weightField.value || weightField.value === '0' || !totalEditedManually)) {
        weightField.value = (birds * targetW).toFixed(2);
    }

    const unitPrice = parseFloat(document.getElementById('newResUnitPrice')?.value) || 180;
    const totalField = document.getElementById('newResTotal');

    if (!totalEditedManually && totalField && unitPrice > 0 && birds > 0) {
        totalField.value = (birds * unitPrice).toFixed(2);
    }

    const total = parseFloat(totalField?.value) || 0;
    const advance = parseFloat(document.getElementById('newResAdvance')?.value) || 0;
    const bal = Math.max(0, total - advance);

    const balDisp = document.getElementById('newResBalanceDisplay');
    if (balDisp) balDisp.innerText = '₱' + bal.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function prefillReserveHouse(houseId, houseName, capacity) {
    const modalEl = document.getElementById('newBroilerReservationModal');
    const modal = bootstrap.Modal.getOrCreateInstance(modalEl);

    const houseSelect = document.getElementById('newResHouseId');
    if (houseSelect) houseSelect.value = houseId;

    const birdsInput = document.getElementById('newResBirds');
    if (birdsInput) birdsInput.value = capacity;

    totalEditedManually = false;
    calcNewResBalance();
    modal.show();
}

function prefillReserveBatch(batchId, batchCode, headcount, houseId, targetWeight) {
    const modalEl = document.getElementById('newBroilerReservationModal');
    const modal = bootstrap.Modal.getOrCreateInstance(modalEl);

    const batchSelect = document.getElementById('newResBatchId');
    if (batchSelect) batchSelect.value = batchId;

    const houseSelect = document.getElementById('newResHouseId');
    if (houseSelect && houseId) houseSelect.value = houseId;

    const birdsInput = document.getElementById('newResBirds');
    if (birdsInput) birdsInput.value = headcount;

    const targetW = document.getElementById('newResTargetWeight');
    if (targetW) targetW.value = Number(targetWeight || 1.80).toFixed(2);

    totalEditedManually = false;
    calcNewResBalance();
    modal.show();
}

let qwAgeGlobal = 28;

function estimateWeightByAge(days) {
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

function autoFillQuickWeighModal() {
    const est = estimateWeightByAge(qwAgeGlobal);
    const input = document.getElementById('qwWeight');
    if (input) input.value = est.toFixed(2);
}

function openQuickWeighModal(batchId, batchCode, houseName, currentWeight, targetWeight, ageDays = 0) {
    const modalEl = document.getElementById('quickWeighModal');
    const modal = bootstrap.Modal.getOrCreateInstance(modalEl);

    qwAgeGlobal = parseInt(ageDays) || 28;
    const est = estimateWeightByAge(qwAgeGlobal);

    const form = document.getElementById('quickWeighForm');
    if (form) form.action = '{{ url("/batches") }}/' + batchId + '/weigh';

    document.getElementById('qwHouseName').innerText = houseName;
    document.getElementById('qwBatchCode').innerText = batchCode + (qwAgeGlobal > 0 ? ` (Day ${qwAgeGlobal})` : '');
    document.getElementById('qwTargetWeight').innerText = Number(targetWeight || 1.80).toFixed(2) + ' kg/bird';
    document.getElementById('qwWeight').value = currentWeight > 0 ? Number(currentWeight).toFixed(2) : est.toFixed(2);

    modal.show();
}

function onHouseSelectChanged(select) {
    const opt = select.options[select.selectedIndex];
    if (opt && opt.value) {
        const cap = opt.getAttribute('data-capacity');
        const birds = document.getElementById('newResBirds');
        if (cap && (!birds.value || birds.value === '0')) {
            birds.value = cap;
            calcNewResBalance();
        }
    }
}

function onBatchSelectChanged(select) {
    const opt = select.options[select.selectedIndex];
    if (opt && opt.value) {
        const qty = opt.getAttribute('data-quantity');
        const tw = opt.getAttribute('data-target');
        const hp = opt.getAttribute('data-house');
        if (qty) document.getElementById('newResBirds').value = qty;
        if (tw) document.getElementById('newResTargetWeight').value = tw;
        if (hp) {
            const houseSelect = document.getElementById('newResHouseId');
            if (houseSelect) {
                for (let i = 0; i < houseSelect.options.length; i++) {
                    if (houseSelect.options[i].getAttribute('data-name')?.toLowerCase() === hp.toLowerCase()) {
                        houseSelect.selectedIndex = i;
                        break;
                    }
                }
            }
        }
        calcNewResBalance();
    }
}
</script>
@endpush
