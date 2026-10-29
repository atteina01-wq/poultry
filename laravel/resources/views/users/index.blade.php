@extends('layouts.app')

@section('title', 'User Administration')

@section('content')
<div class="row mb-4">
    <div class="col-12">
        <div class="card shadow-sm border-0 rounded-4">
            <div class="card-body p-4">
                <div class="d-flex flex-column flex-sm-row justify-content-between align-items-start align-items-sm-center gap-2 mb-4">
                    <div>
                        <h4 class="fw-bold mb-1"><i class="fas fa-user-gear me-2 text-success"></i>User Administration & Role Management</h4>
                        <p class="text-muted small mb-0">Manage system users including Farm Owner, Workers, Staff, and Integrator Partner Representatives.</p>
                    </div>
                    <button type="button" class="btn btn-primary rounded-pill px-4 fw-semibold" data-bs-toggle="modal" data-bs-target="#addUserModal">
                        <i class="fas fa-user-plus me-1"></i>Add New User
                    </button>
                </div>

                @if(session('success'))
                    <div class="alert alert-success alert-dismissible fade show rounded-3" role="alert">
                        <i class="fas fa-check-circle me-2"></i>{{ session('success') }}
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                @endif

                @if(session('error'))
                    <div class="alert alert-danger alert-dismissible fade show rounded-3" role="alert">
                        <i class="fas fa-exclamation-circle me-2"></i>{{ session('error') }}
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                @endif

                <div class="table-responsive custom-scrollbar">
                    <table class="table table-hover align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>User</th>
                                <th>Username</th>
                                <th>Email / Contact</th>
                                <th>Assigned Role</th>
                                <th class="text-center">Account Status</th>
                                <th>Last Login</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($users as $u)
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="user-avatar-badge" style="width: 32px; height: 32px; font-size: 0.85rem;">
                                            {{ strtoupper(substr($u->full_name ?? $u->username, 0, 1)) }}
                                        </div>
                                        <strong>{{ $u->full_name ?? 'User' }}</strong>
                                    </div>
                                </td>
                                <td><code>{{ $u->username }}</code></td>
                                <td>{{ $u->email ?? ($u->phone ?? 'N/A') }}</td>
                                <td>
                                    @if($u->role === 'admin')
                                        <span class="badge bg-primary text-uppercase px-3 py-2"><i class="fas fa-crown me-1"></i>Admin (Owner)</span>
                                    @elseif($u->role === 'integrator')
                                        <span class="badge bg-success text-uppercase px-3 py-2"><i class="fas fa-building me-1"></i>Integrator Partner</span>
                                    @elseif($u->role === 'staff')
                                        <span class="badge bg-info-subtle text-info border text-uppercase px-3 py-2"><i class="fas fa-user-tie me-1"></i>Staff</span>
                                    @else
                                        <span class="badge bg-secondary-subtle text-secondary border text-uppercase px-3 py-2"><i class="fas fa-hammer me-1"></i>Worker</span>
                                    @endif
                                </td>
                                <td class="text-center">
                                    @if((int)$u->is_active === 1)
                                        <span class="badge bg-success rounded-pill px-3 py-1">Active</span>
                                    @else
                                        <span class="badge bg-danger rounded-pill px-3 py-1">Deactivated</span>
                                    @endif
                                </td>
                                <td class="small text-muted">{{ $u->last_login_at ? date('M d, Y h:i A', strtotime($u->last_login_at)) : 'Never' }}</td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="6" class="text-center text-muted py-4">No users found.</td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Add New User -->
<div class="modal fade" id="addUserModal" tabindex="-1" aria-labelledby="addUserModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content rounded-4 border-0 shadow">
            <form method="POST" action="{{ route('users.store') }}">
                @csrf
                <div class="modal-header bg-light border-0 py-3">
                    <h5 class="modal-title fw-bold" id="addUserModalLabel"><i class="fas fa-user-plus me-2 text-primary"></i>Create User Account</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label fw-bold">Full Name</label>
                        <input type="text" name="full_name" class="form-control" placeholder="e.g. Juan Dela Cruz" required>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label fw-bold">Username</label>
                            <input type="text" name="username" class="form-control" placeholder="e.g. juan2026" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label fw-bold">Password</label>
                            <input type="password" name="password" class="form-control" placeholder="Minimum 6 characters" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">User Role</label>
                        <select name="role" class="form-select" required>
                            <option value="worker">Worker (Daily feeding, activities, mortality logging)</option>
                            <option value="staff">Staff (Operational management, view reports)</option>
                            <option value="integrator">Integrator / Partner Rep (Reservation portal, flock monitoring)</option>
                            <option value="admin">Admin (Full farm owner & financial controls)</option>
                        </select>
                        <small class="text-muted">Integrators can view house pen availability and book harvest reservations.</small>
                    </div>
                    <div class="row g-2">
                        <div class="col-6">
                            <label class="form-label fw-bold">Email (Optional)</label>
                            <input type="email" name="email" class="form-control" placeholder="name@example.com">
                        </div>
                        <div class="col-6">
                            <label class="form-label fw-bold">Phone (Optional)</label>
                            <input type="text" name="phone" class="form-control" placeholder="09XX-XXX-XXXX">
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0">
                    <button type="button" class="btn btn-secondary rounded-pill px-4" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary rounded-pill px-4 fw-bold">
                        <i class="fas fa-save me-1"></i>Create User
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
