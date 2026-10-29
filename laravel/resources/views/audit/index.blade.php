@extends('layouts.app')

@section('title', 'System Audit Logs')

@section('content')
<div class="row mb-4">
    <div class="col-12">
        <div class="card shadow-sm border-0 rounded-4">
            <div class="card-body p-4">
                <div class="d-flex flex-column flex-sm-row justify-content-between align-items-start align-items-sm-center gap-2 mb-4">
                    <div>
                        <h4 class="fw-bold mb-1"><i class="fas fa-shield-halved me-2 text-success"></i>System Audit Trail & Security Logs</h4>
                        <p class="text-muted small mb-0">Immutable record of all operational and administrative actions across the farm.</p>
                    </div>
                    <form method="GET" action="{{ route('audit-logs.index') }}">
                        <select name="table" class="form-select form-select-sm" onchange="this.form.submit()">
                            <option value="all">All Modules</option>
                            <option value="batches" {{ ($tableFilter ?? '') === 'batches' ? 'selected' : '' }}>Batches</option>
                            <option value="reservations" {{ ($tableFilter ?? '') === 'reservations' ? 'selected' : '' }}>Reservations</option>
                            <option value="mortality_records" {{ ($tableFilter ?? '') === 'mortality_records' ? 'selected' : '' }}>Mortality</option>
                            <option value="feed_consumption" {{ ($tableFilter ?? '') === 'feed_consumption' ? 'selected' : '' }}>Feed Intake</option>
                            <option value="inventory" {{ ($tableFilter ?? '') === 'inventory' ? 'selected' : '' }}>Inventory</option>
                            <option value="expenses" {{ ($tableFilter ?? '') === 'expenses' ? 'selected' : '' }}>Expenses</option>
                        </select>
                    </form>
                </div>

                <div class="table-responsive custom-scrollbar">
                    <table class="table table-hover align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Timestamp</th>
                                <th>User / Role</th>
                                <th>Action</th>
                                <th>Target Table</th>
                                <th>Record ID</th>
                                <th>IP Address</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($logs as $l)
                            <tr>
                                <td class="small"><i class="far fa-clock me-1 text-muted"></i>{{ date('M d, Y h:i A', strtotime($l->created_at)) }}</td>
                                <td>
                                    <strong>{{ $l->user->full_name ?? ($l->user->username ?? 'System/Guest') }}</strong>
                                    @if(!empty($l->user->role))
                                        <span class="badge bg-light text-dark border ms-1">{{ ucfirst($l->user->role) }}</span>
                                    @endif
                                </td>
                                <td><span class="badge bg-dark text-uppercase px-2 py-1">{{ $l->action }}</span></td>
                                <td><code>{{ $l->table_name }}</code></td>
                                <td>{{ $l->record_id ? '#' . $l->record_id : '-' }}</td>
                                <td class="small text-muted">{{ $l->ip_address ?: '-' }}</td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="6" class="text-center text-muted py-4">No audit logs recorded yet.</td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @if($logs->hasPages())
                <div class="mt-4">
                    {{ $logs->withQueryString()->links() }}
                </div>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
