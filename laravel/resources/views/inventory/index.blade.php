@extends('layouts.app')

@section('title', 'Inventory & Supplies')

@php
function getCategoryDisplayName(string $cat): string {
    $cat = strtolower(trim($cat));
    if ($cat === 'supply') return 'Other Farm Supplies';
    if ($cat === 'vitamins') return 'Vitamins';
    if ($cat === 'medicine') return 'Medicine';
    if ($cat === 'feed') return 'Feed';
    if ($cat === 'limestone') return 'Limestone';
    return ucfirst($cat);
}
@endphp

@section('content')
<!-- KPI Highlights -->
<div class="row g-3 mb-4">
    <div class="col-md-4 col-sm-6">
        <div class="card p-3 shadow-sm border-0 rounded-4 bg-white h-100">
            <div class="d-flex align-items-center">
                <div class="p-3 bg-primary-subtle text-primary rounded-circle me-3">
                    <i class="fas fa-coins fa-2x"></i>
                </div>
                <div>
                    <small class="text-muted text-uppercase fw-bold">Overall Valuation</small>
                    <h3 class="mb-0 fw-bold text-dark">₱{{ number_format($totalValuation, 2) }}</h3>
                    <small class="text-success fw-semibold"><i class="fas fa-check-circle me-1"></i>Active Farm Inventory Assets</small>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-4 col-sm-6">
        <div class="card p-3 shadow-sm border-0 rounded-4 bg-white h-100">
            <div class="d-flex align-items-center">
                <div class="p-3 bg-warning-subtle text-warning rounded-circle me-3">
                    <i class="fas fa-triangle-exclamation fa-2x"></i>
                </div>
                <div>
                    <small class="text-muted text-uppercase fw-bold">Low Stock Count</small>
                    <h3 class="mb-0 fw-bold {{ $lowStockCount > 0 ? 'text-warning' : 'text-dark' }}">
                        {{ $lowStockCount }} Item{{ $lowStockCount === 1 ? '' : 's' }}
                    </h3>
                    <small class="text-muted">Stock remaining (&le; 10 units threshold)</small>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-4 col-sm-6">
        <div class="card p-3 shadow-sm border-0 rounded-4 bg-white h-100">
            <div class="d-flex align-items-center">
                <div class="p-3 bg-danger-subtle text-danger rounded-circle me-3">
                    <i class="fas fa-circle-xmark fa-2x"></i>
                </div>
                <div>
                    <small class="text-muted text-uppercase fw-bold">Out of Stock Count</small>
                    <h3 class="mb-0 fw-bold {{ $outOfStockCount > 0 ? 'text-danger' : 'text-dark' }}">
                        {{ $outOfStockCount }} Item{{ $outOfStockCount === 1 ? '' : 's' }}
                    </h3>
                    <small class="text-muted">Depleted items requiring immediate restock</small>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="page-grid">
    <!-- Add Inventory Form -->
    <div>
        <div class="card form-container h-100">
            <div class="card-body d-flex flex-column">
                <h3 class="section-title"><i class="fas fa-plus-circle text-primary"></i>Add Inventory & Lots</h3>
                <p class="text-muted small mb-4">Add new feed, vitamins, medicines, limestone, or other farm supplies with lot tracking.</p>

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

                <form method="POST" action="{{ route('inventory.store') }}">
                    @csrf

                    <div class="mb-3">
                        <label class="form-label fw-bold">Item Name <span class="text-danger">*</span></label>
                        <input type="text" name="item_name" class="form-control" placeholder="e.g., Broiler Starter Feed" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">Category <span class="text-danger">*</span></label>
                        <select name="category" class="form-select" required>
                            <option value="feed">Feed</option>
                            <option value="medicine">Medicine</option>
                            <option value="vitamins">Vitamins</option>
                            <option value="supply">Other Farm Supplies</option>
                            <option value="limestone">Limestone</option>
                        </select>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label fw-bold">Quantity <span class="text-danger">*</span></label>
                            <input type="number" step="0.01" min="0.01" name="quantity" class="form-control" placeholder="0.00" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label fw-bold">Unit <span class="text-danger">*</span></label>
                            <input type="text" name="unit" class="form-control" placeholder="e.g. sacks, bottles" required>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">Unit Cost (₱ per unit) <span class="text-danger">*</span></label>
                        <input type="number" step="0.01" min="0" name="cost" class="form-control" placeholder="e.g., 150.00" required>
                        <small class="text-muted">Enter cost per single unit (sack, vial, bottle, etc.)</small>
                    </div>

                    <!-- Lot & Expiry Details -->
                    <div class="border rounded-3 p-3 mb-4 bg-light">
                        <h6 class="fw-bold small text-secondary mb-2">
                            <i class="fas fa-barcode me-1 text-primary"></i>Lot & Expiry Tracking (Recommended)
                        </h6>
                        <div class="row g-2">
                            <div class="col-6">
                                <label class="form-label small fw-semibold mb-1">Lot / Batch #</label>
                                <input type="text" name="lot_number" class="form-control form-control-sm" placeholder="e.g., LOT-2026-01">
                            </div>
                            <div class="col-6">
                                <label class="form-label small fw-semibold mb-1">Supplier</label>
                                <input type="text" name="supplier" class="form-control form-control-sm" placeholder="e.g., B-Meg Feeds">
                            </div>
                            <div class="col-6">
                                <label class="form-label small fw-semibold mb-1">Received Date</label>
                                <input type="date" name="received_date" class="form-control form-control-sm" value="{{ date('Y-m-d') }}">
                            </div>
                            <div class="col-6">
                                <label class="form-label small fw-semibold mb-1">Expiry Date</label>
                                <input type="date" name="expiry_date" class="form-control form-control-sm">
                            </div>
                        </div>
                    </div>

                    <div class="form-actions-sticky mt-auto d-grid">
                        <button type="submit" name="save" class="btn btn-primary w-100">
                            <i class="fas fa-save me-2"></i>Save Item & Lot
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Right Column: Expiring Lots, Current Stock, and Usage History -->
    <div style="min-width: 0;">
        <!-- Expiring Soon Lots Monitoring -->
        <div class="card mb-4">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
                    <div>
                        <h4 class="section-title mb-1 text-danger">
                            <i class="fas fa-clock-rotate-left"></i>Expiring Lots Monitoring
                        </h4>
                        <p class="text-muted small mb-0">Lots expiring within the next 60 days.</p>
                    </div>
                    <span class="badge bg-danger-subtle text-danger border border-danger-subtle px-3 py-2 rounded-pill">
                        {{ count($expiringLots) }} Expiring Lots
                    </span>
                </div>

                <div class="table-responsive custom-scrollbar" style="max-height: 220px; overflow-y: auto;">
                    <table class="table table-hover align-middle">
                        <thead class="table-light sticky-top">
                            <tr>
                                <th>Item</th>
                                <th>Category</th>
                                <th>Lot #</th>
                                <th class="text-end">Remaining</th>
                                <th>Expiry Date</th>
                                <th class="text-center">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($expiringLots as $lot)
                                @php
                                    $days = (int)$lot->days_until_expiry;
                                    if ($days < 0) {
                                        $expBadge = 'bg-danger text-white';
                                        $expText = 'Expired (' . abs($days) . 'd ago)';
                                    } elseif ($days <= 14) {
                                        $expBadge = 'bg-danger-subtle text-danger border-danger';
                                        $expText = $days . ' days left';
                                    } elseif ($days <= 30) {
                                        $expBadge = 'bg-warning-subtle text-warning border-warning';
                                        $expText = $days . ' days left';
                                    } else {
                                        $expBadge = 'bg-info-subtle text-info border-info';
                                        $expText = $days . ' days left';
                                    }
                                @endphp
                                <tr>
                                    <td class="fw-bold">{{ $lot->item_name }}</td>
                                    <td>
                                        <span class="badge bg-light text-dark border">
                                            {{ getCategoryDisplayName($lot->category) }}
                                        </span>
                                    </td>
                                    <td><code>{{ !empty($lot->lot_number) ? $lot->lot_number : 'N/A' }}</code></td>
                                    <td class="text-end fw-bold">{{ number_format($lot->quantity_remaining, 1) }} {{ $lot->unit ?? '' }}</td>
                                    <td>{{ date('M d, Y', strtotime($lot->expiry_date)) }}</td>
                                    <td class="text-center">
                                        <span class="badge rounded-pill {{ $expBadge }} border px-3 py-1">
                                            {{ $expText }}
                                        </span>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="text-center text-muted py-4">
                                        <i class="fas fa-check-circle text-success me-2"></i>No lots expiring soon! All inventory lots are in good condition.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Current Stock -->
        <div class="card p-4 shadow-sm border-0 rounded-4 mb-4">
            <h4 class="card-title mb-4 text-primary fw-bold">
                <i class="fas fa-warehouse me-2"></i>Current Stock
            </h4>
            <div class="table-responsive custom-scrollbar" style="max-height: 350px; overflow-y: auto;">
                <table class="table table-hover align-middle">
                    <thead class="table-light sticky-top">
                        <tr>
                            <th>Item Name</th>
                            <th>Category</th>
                            <th>Prev. Stock</th>
                            <th>Current Stock</th>
                            <th class="text-end">Total Value</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($currentStock as $row)
                            @php
                                $totalValue = max(0, (float)$row->quantity) * (float)$row->unit_cost;
                                $qty = (float)$row->quantity;
                                
                                if ($qty <= 0) {
                                    $stockStatusClass = 'text-danger fw-bold';
                                    $statusBadge = '<span class="badge bg-danger">Out of Stock</span>';
                                } elseif ($qty <= 10) {
                                    $stockStatusClass = 'text-warning fw-bold';
                                    $statusBadge = '<span class="badge bg-warning text-dark">Low Stock</span>';
                                } else {
                                    $stockStatusClass = 'text-success fw-semibold';
                                    $statusBadge = '<span class="badge bg-success">In Stock</span>';
                                }
                            @endphp
                            <tr>
                                <td class="fw-bold">{{ $row->item_name }}</td>
                                <td><span class="badge bg-info-subtle text-info border rounded-pill">{{ getCategoryDisplayName($row->category) }}</span></td>
                                <td class="text-muted small">{{ $row->previous_quantity }} {{ $row->unit }}</td>
                                <td class="{{ $stockStatusClass }}">{{ number_format($qty, 2) }} {{ $row->unit }}</td>
                                <td class="text-end fw-bold text-primary">₱{{ number_format($totalValue, 2) }}</td>
                                <td>{!! $statusBadge !!}</td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="text-muted text-center py-3">No inventory items registered.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Usage History with Remaining Stock -->
        <div class="card p-4 shadow-sm border-0 rounded-4">
            <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
                <h4 class="card-title mb-0 text-primary fw-bold">
                    <i class="fas fa-history me-2"></i>Usage History & Stock Balance
                </h4>
                
                <!-- Item Filter -->
                <div class="d-flex align-items-center gap-2">
                    <label class="small fw-bold text-muted text-nowrap">Filter by Item:</label>
                    <select id="itemHistoryFilter" class="form-select form-select-sm rounded-pill" style="width: auto;" onchange="filterUsage(this.value)">
                        <option value="all" {{ $itemFilter === 'all' ? 'selected' : '' }}>-- All Items --</option>
                        @foreach($allItems as $ai)
                            <option value="{{ $ai->inventory_id }}" {{ $itemFilter == $ai->inventory_id ? 'selected' : '' }}>
                                {{ $ai->item_name }} ({{ getCategoryDisplayName($ai->category) }})
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="table-responsive custom-scrollbar" style="max-height: 350px; overflow-y: auto;">
                <table class="table table-hover align-middle">
                    <thead class="table-light sticky-top">
                        <tr>
                            <th>Date</th>
                            <th>Broiler Batch</th>
                            <th>Item Name</th>
                            <th>Type</th>
                            <th class="text-end">Qty Used</th>
                            <th class="text-end">Remaining Stock</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($usageHistory as $u)
                            @php
                                $badgeColor = 'bg-secondary';
                                if ($u->type === 'Feeding') $badgeColor = 'bg-warning text-dark';
                                elseif ($u->type === 'Health') $badgeColor = 'bg-danger text-white';
                                elseif ($u->type === 'Supply') $badgeColor = 'bg-success text-white';
                            @endphp
                            <tr>
                                <td>{{ date('M d, Y', strtotime($u->date)) }}</td>
                                <td>
                                    <span class="badge bg-light text-primary border">
                                        <i class="fas fa-drumstick-bite me-1 text-success"></i>{{ $u->batch_code }}
                                    </span>
                                </td>
                                <td class="fw-bold">{{ $u->item_name }}</td>
                                <td><span class="badge {{ $badgeColor }} rounded-pill">{{ ucfirst($u->type) }}</span></td>
                                <td class="text-end fw-bold text-danger">-{{ number_format($u->quantity, 2) }} <small class="text-muted fw-normal">{{ $u->unit }}</small></td>
                                <td class="text-end fw-bold text-success">{{ number_format((float)$u->remaining_stock, 2) }} <small class="text-muted fw-normal">{{ $u->unit }}</small></td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="text-center py-5 text-muted small"><i class="fas fa-info-circle me-2 opacity-50"></i>No usage records found.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            
            @if($usageHistory->hasPages())
                <div class="mt-4">
                    {{ $usageHistory->withQueryString()->links() }}
                </div>
            @endif
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
function filterUsage(inventoryId) {
    const url = new URL(window.location);
    url.searchParams.set('item_filter', inventoryId);
    window.location.href = url.href;
}
</script>
@endpush
