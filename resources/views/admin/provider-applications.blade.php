@extends('admin.layout')
@section('title', 'Provider Applications - HCT')
@section('content')

<div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="mb-0">
        <i class="bi bi-envelope-paper"></i> Provider Applications
        <span class="badge bg-secondary ms-2" title="Total in current view">{{ number_format($applications->total()) }}</span>
    </h5>
</div>

{{-- Server-side filter card — explicit Apply / Clear (Travelers-style). --}}
<form method="GET" action="{{ url('/provider-applications') }}" class="card mb-3">
    <div class="card-body py-2">
        <div class="row g-2 align-items-end">
            <div class="col-md-3">
                <label class="form-label small text-muted mb-1">Status</label>
                <select class="form-select form-select-sm custom-select" name="status">
                    <option value="pending"  {{ $status === 'pending'  ? 'selected' : '' }}>Pending (default)</option>
                    <option value="approved" {{ $status === 'approved' ? 'selected' : '' }}>Approved</option>
                    <option value="rejected" {{ $status === 'rejected' ? 'selected' : '' }}>Rejected</option>
                    <option value="removed"  {{ $status === 'removed'  ? 'selected' : '' }}>Removed</option>
                    <option value="all"      {{ $status === 'all'      ? 'selected' : '' }}>All statuses</option>
                </select>
            </div>
            <div class="col-md-6">
                <label class="form-label small text-muted mb-1">Search (name / email / phone)</label>
                <input type="text" name="search" value="{{ $search }}" class="form-control form-control-sm" placeholder="Type to search...">
            </div>
            <div class="col-md-3 d-flex gap-2">
                <button type="submit" class="btn btn-primary btn-sm">
                    <i class="bi bi-funnel"></i> Apply
                </button>
                @if($status !== 'pending' || $search !== '')
                    <a href="{{ url('/provider-applications') }}" class="btn btn-outline-secondary btn-sm">
                        <i class="bi bi-x-circle"></i> Clear
                    </a>
                @endif
            </div>
        </div>
    </div>
</form>

<div class="row g-3">
    @forelse($applications as $app)
        @php
            $typeBadge = match($app->provider_type) {
                'hrp' => '<span class="badge bg-info">HRP</span>',
                'hlh' => '<span class="badge bg-success">HLH</span>',
                'osp' => '<span class="badge bg-warning text-dark">OSP</span>',
                default => '<span class="badge bg-secondary">'.e($app->provider_type ?: '-').'</span>',
            };
            $services = is_array($app->services_offered) ? $app->services_offered
                       : (json_decode($app->services_offered ?? '[]', true) ?: []);
        @endphp
        <div class="col-md-6 col-lg-4">
            <div class="card h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <h6 class="card-title mb-0">{{ $app->name ?: 'Unnamed' }}</h6>
                        {!! $typeBadge !!}
                    </div>

                    <div class="small mb-2">
                        @if($app->email)<div><i class="bi bi-envelope text-muted"></i> {{ $app->email }}</div>@endif
                        @if($app->phone_1)<div><i class="bi bi-telephone text-muted"></i> {{ $app->phone_1 }}</div>@endif
                    </div>

                    <div class="small mb-2">
                        @if($app->region)<span class="me-3"><i class="bi bi-geo-alt text-muted"></i> {{ $app->region->name }}</span>@endif
                        <span><i class="bi bi-calendar text-muted"></i> {{ optional($app->created_at)->format('Y-m-d') ?: '-' }}</span>
                    </div>

                    @if(count($services))
                        <div class="mb-3">
                            <small class="text-muted d-block mb-1">Services Offered:</small>
                            @foreach($services as $s)
                                <span class="badge bg-light text-dark border me-1 mb-1">{{ $s }}</span>
                            @endforeach
                        </div>
                    @endif

                    @if($app->status === 'pending')
                        <div class="d-flex gap-2">
                            <button class="btn btn-sm btn-success flex-fill approve-app" data-id="{{ $app->id }}"><i class="bi bi-check-lg"></i> Approve</button>
                            <button class="btn btn-sm btn-danger flex-fill reject-app" data-id="{{ $app->id }}"><i class="bi bi-x-lg"></i> Reject</button>
                        </div>
                    @elseif($app->status === 'approved')
                        <div class="d-flex justify-content-between align-items-center">
                            <span class="badge bg-success"><i class="bi bi-check-circle"></i> Approved</span>
                            <small class="text-muted">{{ optional($app->approved_at)->format('Y-m-d') }}</small>
                        </div>
                    @elseif($app->status === 'rejected')
                        <div class="d-flex justify-content-between align-items-center">
                            <span class="badge bg-danger"><i class="bi bi-x-circle"></i> Rejected</span>
                            <small class="text-muted">{{ optional($app->approved_at)->format('Y-m-d') }}</small>
                        </div>
                    @elseif($app->status === 'removed')
                        <div class="d-flex justify-content-between align-items-center">
                            <span class="badge bg-dark"><i class="bi bi-archive"></i> Removed</span>
                            <small class="text-muted">archived</small>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    @empty
        <div class="col-12 text-center text-muted py-4">No applications found.</div>
    @endforelse
</div>

@if($applications->hasPages())
    <div class="mt-3 d-flex justify-content-center">
        {{ $applications->links() }}
    </div>
@endif

@endsection

@section('js')
<script>
// Approve / Reject still go through AJAX (writes) — page reloads after to
// pick up the server-side filter + pagination state.
$(document).on('click', '.approve-app', function() {
    var id = $(this).data('id');
    var $btn = $(this);
    Swal.fire({
        title: 'Approve this provider application?',
        text: 'A user account will be created for the provider and a set-password email will be sent.',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Yes, approve',
        confirmButtonColor: '#79a09f',
    }).then(function(res) {
        if (!res.isConfirmed) return;
        $btn.prop('disabled', true).html('<i class="bi bi-hourglass-split"></i> Processing...');
        ajaxPost({ approve_provider: 1, provider_id: id }, function() {
            showAlert('Application approved.', 'success');
            location.reload();
        });
    });
});

$(document).on('click', '.reject-app', function() {
    var id = $(this).data('id');
    var $btn = $(this);
    Swal.fire({
        title: 'Reject this provider application?',
        text: 'You can reverse this later by re-approving the application.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Yes, reject',
        confirmButtonColor: '#b54a4a',
    }).then(function(res) {
        if (!res.isConfirmed) return;
        $btn.prop('disabled', true).html('<i class="bi bi-hourglass-split"></i> Processing...');
        ajaxPost({ reject_provider: 1, provider_id: id }, function() {
            showAlert('Application rejected.', 'info');
            location.reload();
        });
    });
});
</script>
@endsection
