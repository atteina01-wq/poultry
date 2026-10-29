@extends('layouts.app')

@section('title', 'Farm Profile')

@section('content')
<div class="container-fluid px-0">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm rounded-4 bg-white p-4">
                <div class="d-flex align-items-center mb-4 pb-3 border-bottom">
                    <div class="rounded-circle p-3 bg-primary bg-opacity-10 text-primary me-3">
                        <i class="fas fa-tractor fs-3"></i>
                    </div>
                    <div>
                        <h4 class="fw-bold mb-1 text-dark">Farm Identity & Company Profile</h4>
                        <p class="text-muted small mb-0">Customize your poultry facility details printed on official delivery receipts and vouchers.</p>
                    </div>
                </div>

                <form method="POST" action="{{ route('company.update') }}" enctype="multipart/form-data">
                    @csrf
                    @method('PUT')

                    <div class="mb-3">
                        <label class="form-label small fw-bold">Farm / Company Legal Name <span class="text-danger">*</span></label>
                        <input type="text" name="company_name" class="form-control" value="{{ old('company_name', $company->company_name) }}" required>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Contact Phone Number</label>
                            <input type="text" name="contact_number" class="form-control" value="{{ old('contact_number', $company->contact_number) }}" placeholder="e.g. 09123456789">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Official Email Address</label>
                            <input type="email" name="email" class="form-control" value="{{ old('email', $company->email) }}" placeholder="farm@example.com">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold">Farm Physical Address</label>
                        <textarea name="address" class="form-control" rows="2">{{ old('address', $company->address) }}</textarea>
                    </div>

                    <div class="mb-4">
                        <label class="form-label small fw-bold">Additional Bio / Permit Notes</label>
                        <textarea name="additional_info" class="form-control" rows="2">{{ old('additional_info', $company->additional_info) }}</textarea>
                    </div>

                    <div class="d-flex justify-content-end">
                        <button type="submit" class="btn btn-primary fw-semibold px-4 py-2 rounded-3 shadow-sm">
                            <i class="fas fa-save me-1"></i> Save Farm Profile
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
