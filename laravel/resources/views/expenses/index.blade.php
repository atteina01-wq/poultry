@extends('layouts.app')

@section('title', 'Expenses Ledger')

@section('content')
<div class="row g-4 mb-4">
    <!-- Category Cards -->
    @foreach($categorySummary as $cat)
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="card shadow-sm border-0 rounded-4 h-100">
            <div class="card-body p-3">
                <div class="text-muted small text-uppercase fw-bold mb-1">{{ $cat->category }}</div>
                <h4 class="fw-bold text-dark mb-0">₱{{ number_format($cat->total_amount, 2) }}</h4>
                <small class="text-muted">{{ $cat->count }} transactions</small>
            </div>
        </div>
    </div>
    @endforeach
</div>

<div class="row mb-4">
    <div class="col-12">
        <div class="card shadow-sm border-0 rounded-4">
            <div class="card-body p-4">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <h4 class="fw-bold mb-1"><i class="fas fa-wallet me-2 text-success"></i>Operational Expenses Ledger</h4>
                        <p class="text-muted small mb-0">Track all farm expenses (Feed, Limestone, Medicine, Supplies, Labor, Utilities, Waste).</p>
                    </div>
                </div>

                @if(session('success'))
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="fas fa-check-circle me-2"></i>{{ session('success') }}
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                @endif

                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Date</th>
                                <th>Category</th>
                                <th>Description</th>
                                <th>Related Flock / Item</th>
                                <th class="text-end">Amount</th>
                                <th>Recorded By</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($expenses as $e)
                            <tr>
                                <td>{{ date('M d, Y', strtotime($e->date)) }}</td>
                                <td><span class="badge bg-secondary text-uppercase px-2 py-1">{{ $e->category }}</span></td>
                                <td>{{ $e->description ?? '-' }}</td>
                                <td>
                                    @if($e->batch)
                                        <span class="badge bg-light text-dark border"><i class="fas fa-feather-pointed me-1"></i>{{ $e->batch->batch_code }}</span>
                                    @elseif($e->inventory)
                                        <span class="badge bg-light text-dark border"><i class="fas fa-box me-1"></i>{{ $e->inventory->item_name }}</span>
                                    @else
                                        <span class="text-muted">-</span>
                                    @endif
                                </td>
                                <td class="text-end fw-bold text-dark">₱{{ number_format($e->amount, 2) }}</td>
                                <td class="small text-muted">{{ $e->recorder->full_name ?? 'System' }}</td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="6" class="text-center text-muted py-4">No expense records logged yet.</td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                
                @if($expenses->hasPages())
                <div class="mt-4">
                    {{ $expenses->links() }}
                </div>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
