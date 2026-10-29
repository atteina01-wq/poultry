@extends('layouts.app')

@php
    $cat = request('cat', $targetCategory ?? 'feed');
    $is_limestone = ($cat === 'limestone');
    $target_category = $is_limestone ? 'limestone' : 'feed';
    $section_icon = $is_limestone ? 'fa-mountain text-success' : 'fa-wheat-awn text-warning';
    $form_title = $is_limestone ? 'Record Limestone Usage' : 'Record Feed Consumption';
    $item_label = $is_limestone ? 'Limestone Item' : 'Feed Item';
    $item_placeholder = $is_limestone ? '-- Choose Limestone Item --' : '-- Choose Feed Item --';
    $button_text = $is_limestone ? 'Record Limestone Usage' : 'Submit Feeding Record';
    $history_title = $is_limestone ? 'Limestone Usage History' : 'Feeding History';
    $history_col_header = $is_limestone ? 'Limestone Item' : 'Feed Item';
    $no_records_msg = $is_limestone ? 'No limestone usage records found.' : 'No feeding records found.';
@endphp

@section('title', $form_title)

@section('content')
<div class="page-grid">
    <!-- Form Card -->
    <div>
        <div class="card form-container h-100">
            <div class="card-body d-flex flex-column">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <h3 class="section-title mb-0"><i class="fas {{ $section_icon }} text-primary"></i>{{ $form_title }}</h3>
                    <div class="btn-group btn-group-sm">
                        <a href="{{ route('feed.index', ['cat' => 'feed']) }}" class="btn btn-outline-primary {{ !$is_limestone ? 'active' : '' }}">Feed</a>
                        <a href="{{ route('feed.index', ['cat' => 'limestone']) }}" class="btn btn-outline-success {{ $is_limestone ? 'active' : '' }}">Limestone</a>
                    </div>
                </div>
                <p class="text-muted small mb-4">
                    @if($is_limestone)
                        Track mineral and nutritional supplementation for flock growth and health.
                    @else
                        Track daily Pre-Starter, Starter, Grower, and Finisher feeding for active broiler flocks.
                    @endif
                </p>

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
                
                <form method="POST" action="{{ route('feed.store') }}">
                    @csrf
                    <input type="hidden" name="cat" value="{{ $target_category }}">
                    <div class="mb-3">
                        <label class="form-label fw-bold">Broiler Batch <span class="text-danger">*</span></label>
                        <select name="batch_id" class="form-select" required>
                            <option value="">-- Choose Broiler Batch --</option>
                            @foreach($batches as $b)
                                @php $hp = !empty($b->house_pen) ? ' - ' . $b->house_pen : ''; @endphp
                                <option value="{{ $b->batch_id }}">
                                    {{ $b->batch_code }}{{ $hp }} (Broiler{{ !empty($b->breed) ? ' - ' . $b->breed : '' }})
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">Consumption Date <span class="text-danger">*</span></label>
                        <input type="date" name="date" class="form-control" value="{{ date('Y-m-d') }}" required>
                    </div>

                    @if(!$is_limestone)
                    <div class="mb-3">
                        <label class="form-label fw-bold">Feed Stage <span class="text-danger">*</span></label>
                        <select name="feed_stage" class="form-select" required>
                            <option value="">-- Select Feed Stage --</option>
                            <option value="Pre-Starter">Pre-Starter</option>
                            <option value="Starter">Starter</option>
                            <option value="Grower">Grower</option>
                            <option value="Finisher">Finisher</option>
                        </select>
                        <small class="text-muted">Pre-Starter (1-10d), Starter (11-20d), Grower (21-30d), Finisher (31d+)</small>
                    </div>
                    @endif

                    <div class="mb-3">
                        <label class="form-label fw-bold">{{ $item_label }} <span class="text-danger">*</span></label>
                        <select name="inventory_id" id="feedItemSelect" class="form-select" required>
                            <option value="">{{ $item_placeholder }}</option>
                            @if($inventoryItems->isEmpty())
                                <option value="" disabled>No active {{ $target_category }} items in inventory</option>
                            @else
                                @foreach($inventoryItems as $f)
                                    @php $isOutOfStock = ((float)$f->quantity <= 0); @endphp
                                    <option value="{{ $f->inventory_id }}" 
                                            data-unit="{{ $f->unit }}"
                                            data-stock="{{ (float)$f->quantity }}"
                                            {{ $isOutOfStock ? 'disabled' : '' }}>
                                        {{ $f->item_name }} (Stock: {{ number_format($f->quantity, 2) }} {{ $f->unit }})
                                        {{ $isOutOfStock ? ' [OUT OF STOCK]' : '' }}
                                    </option>
                                @endforeach
                            @endif
                        </select>
                        @if($inventoryItems->isEmpty())
                            <small class="text-warning mt-1 d-block">
                                <i class="fas fa-exclamation-triangle me-1"></i>Add {{ $target_category }} in <a href="{{ route('inventory.index') }}">Central Inventory</a> first.
                            </small>
                        @endif
                    </div>

                    <div class="mb-4">
                        <label class="form-label fw-bold">Quantity Consumed <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <input type="number" step="0.01" min="0.01" name="qty" class="form-control" placeholder="0.00" required>
                            <span class="input-group-text" id="unitDisplay">Units</span>
                        </div>
                    </div>

                    <div class="form-actions-sticky mt-auto d-grid">
                        <button type="submit" name="consume" class="btn btn-primary w-100">
                            <i class="fas fa-check me-2"></i>{{ $button_text }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- History Card -->
    <div style="min-width: 0;">
        <div class="card h-100">
            <div class="card-body">
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
                    <h3 class="section-title mb-0"><i class="fas fa-history"></i>{{ $history_title }}</h3>
                    <span class="badge bg-primary px-3 py-2 rounded-pill fw-bold">
                        {{ $records->count() }} Consumption Entries
                    </span>
                </div>

                <div class="table-responsive custom-scrollbar">
                    <table class="table table-hover align-middle">
                        <thead class="table-light sticky-top">
                            <tr>
                                <th>Date</th>
                                <th>Broiler Batch</th>
                                @if(!$is_limestone)
                                    <th>Stage</th>
                                @endif
                                <th>{{ $history_col_header }}</th>
                                <th class="text-end">Qty Used</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($records as $row)
                                @php
                                    $stage = $row->feed_stage ?? '';
                                    $stageBadge = 'bg-secondary text-white';
                                    if ($stage === 'Pre-Starter') $stageBadge = 'bg-primary text-white';
                                    elseif ($stage === 'Starter') $stageBadge = 'bg-success text-white';
                                    elseif ($stage === 'Grower') $stageBadge = 'bg-warning text-dark';
                                    elseif ($stage === 'Finisher') $stageBadge = 'bg-danger text-white';
                                @endphp
                                <tr>
                                    <td>{{ date('M d, Y', strtotime($row->date)) }}</td>
                                    <td>
                                        <span class="badge bg-light text-primary border">
                                            <i class="fas fa-drumstick-bite me-1 text-success"></i>{{ $row->batch->batch_code ?? 'Batch #' . $row->batch_id }}
                                        </span>
                                    </td>
                                    @if(!$is_limestone)
                                        <td>
                                            @if(!empty($stage))
                                                <span class="badge rounded-pill {{ $stageBadge }} px-2 py-1">
                                                    {{ $stage }}
                                                </span>
                                            @else
                                                <span class="text-muted small">—</span>
                                            @endif
                                        </td>
                                    @endif
                                    <td class="fw-semibold">{{ $row->inventory->item_name ?? 'Item' }}</td>
                                    <td class="text-end fw-bold text-danger">-{{ number_format($row->quantity_used, 2) }} {{ $row->inventory->unit ?? 'kg' }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="{{ $is_limestone ? 4 : 5 }}" class="text-center text-muted py-4">
                                        <i class="fas fa-inbox me-2"></i>{{ $no_records_msg }}
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
document.getElementById('feedItemSelect')?.addEventListener('change', function() {
    const selected = this.options[this.selectedIndex];
    const unit = selected.getAttribute('data-unit');
    if (unit) {
        document.getElementById('unitDisplay').textContent = unit;
    }
});
</script>
@endpush
