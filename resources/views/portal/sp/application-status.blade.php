@extends('portal.layout')
@section('title', 'Application Status - HECO Portal')

@php
    $status = $provider->status; // pending | approved | rejected
    $typeLabel = [
        'hrp' => 'Himalayan Regenerative Partner (HRP)',
        'hlh' => 'Homestay Local Host (HLH)',
        'osp' => 'Other Service Provider (OSP)',
    ][$provider->provider_type] ?? 'Partner';
    $firstName = $provider->contact_person ?: $provider->name;
@endphp

@section('content')
<div class="container py-4">
    <div class="row justify-content-center">
        <div class="col-md-7">

            @if($status === 'approved')
                {{-- ── You're approved! ──────────────────────────────────── --}}
                <div class="text-center py-3">
                    <div class="app-status-approved-badge mx-auto mb-4"><i class="bi bi-check-lg"></i></div>
                    <h2 class="mb-2">You're approved!</h2>
                    <p class="text-muted mb-4">
                        Welcome to HECO{{ $firstName ? ', '.$firstName : '' }}! Your provider account is
                        live — start taking bookings today.
                    </p>
                    <a href="{{ route('sp.dashboard') }}" class="btn sp-btn-primary btn-lg w-100">
                        <i class="bi bi-speedometer2"></i> Go to dashboard
                    </a>
                </div>
            @else
                @php $isRejected = $status === 'rejected'; @endphp

                <div class="d-flex justify-content-between align-items-center mb-4">
                    <span class="app-status-badge {{ $isRejected ? 'is-rejected' : '' }}">
                        <span class="dot"></span>
                        {{ $isRejected ? 'Application not approved' : 'Pending approval' }}
                    </span>
                    <form method="POST" action="{{ route('logout') }}" class="m-0">
                        @csrf
                        <button type="submit" class="btn btn-link text-muted p-0 text-decoration-none">
                            <i class="bi bi-box-arrow-right"></i> Sign out
                        </button>
                    </form>
                </div>

                @if($isRejected)
                    <h2 class="mb-2">Your application was not approved</h2>
                    <p class="text-muted mb-4">
                        Unfortunately your application to join HECO as a {{ $typeLabel }} was not approved.
                        Please contact the HECO team for details or to reapply.
                    </p>
                @else
                    <h2 class="mb-2">Your application is under review</h2>
                    <p class="text-muted mb-4">
                        Thanks{{ $firstName ? ', '.$firstName : '' }}! Our team is reviewing your details.
                        We'll email you as soon as you're approved — usually within 1–2 business days.
                    </p>
                @endif

                {{-- Status card --}}
                <div class="card p-3 mb-4 {{ $isRejected ? 'app-status-card-rejected' : 'app-status-card-pending' }}">
                    <div class="d-flex align-items-center">
                        <div class="app-status-icon me-3">
                            <i class="bi {{ $isRejected ? 'bi-x-circle' : 'bi-hourglass-split' }}"></i>
                        </div>
                        <div>
                            <div class="fw-semibold">{{ $isRejected ? 'Not approved' : 'Under review' }}</div>
                            <div class="text-muted small">Application #{{ $provider->id }} · {{ $typeLabel }}</div>
                        </div>
                    </div>
                </div>

                {{-- Lifecycle timeline --}}
                <div class="text-uppercase text-muted small fw-semibold mb-3" style="letter-spacing:.06em;">
                    What happens next
                </div>
                <ul class="app-timeline list-unstyled">
                    <li class="done">
                        <span class="node"><i class="bi bi-check"></i></span>
                        <div>
                            <div class="fw-semibold">Application submitted</div>
                            <div class="text-muted small">{{ $provider->created_at->format('d M Y, H:i') }}</div>
                        </div>
                    </li>
                    <li class="{{ $isRejected ? 'done' : 'active' }}">
                        <span class="node">{!! $isRejected ? '<i class="bi bi-check"></i>' : '' !!}</span>
                        <div>
                            <div class="fw-semibold">Under review</div>
                            <div class="text-muted small">{{ $isRejected ? 'Completed' : 'In progress' }}</div>
                        </div>
                    </li>
                    <li class="{{ $isRejected ? 'rejected' : '' }}">
                        <span class="node">{!! $isRejected ? '<i class="bi bi-x"></i>' : '' !!}</span>
                        <div>
                            <div class="fw-semibold">{{ $isRejected ? 'Not approved' : 'Approved' }}</div>
                            <div class="text-muted small">{{ $isRejected ? '—' : 'Pending' }}</div>
                        </div>
                    </li>
                </ul>

                <div class="mt-4">
                    @unless($isRejected)
                        <a href="{{ route('sp.status') }}" class="btn sp-btn-primary btn-lg w-100">
                            <i class="bi bi-arrow-clockwise"></i> Refresh status
                        </a>
                    @else
                        <a href="{{ route('contact') }}" class="btn sp-btn-primary btn-lg w-100">
                            <i class="bi bi-envelope"></i> Contact HECO
                        </a>
                    @endunless
                </div>
            @endif

        </div>
    </div>
</div>
@endsection

@section('css')
<style>
    .app-status-badge {
        display: inline-flex; align-items: center; gap: 8px;
        font-weight: 600; font-size: .95rem; color: #575753;
    }
    .app-status-badge .dot {
        width: 10px; height: 10px; border-radius: 50%;
        background: #A2A941; animation: appPulse 2s ease-in-out infinite;
    }
    .app-status-badge.is-rejected .dot { background: #B36959; animation: none; }
    @keyframes appPulse { 0%,100% { opacity: .4; } 50% { opacity: 1; } }

    .app-status-approved-badge {
        width: 96px; height: 96px; border-radius: 50%;
        background: #eef4f3; color: #79A09F;
        display: flex; align-items: center; justify-content: center; font-size: 3rem;
    }

    .app-status-card-pending { background: #fbfaef; border-color: #e6e5c9; }
    .app-status-card-rejected { background: #fbf2ef; border-color: #ecd6cf; }
    .app-status-icon {
        width: 46px; height: 46px; border-radius: 50%; background: #fff;
        display: flex; align-items: center; justify-content: center;
        font-size: 1.3rem; color: #A2A941;
    }
    .app-status-card-rejected .app-status-icon { color: #B36959; }

    .app-timeline { position: relative; margin: 0; padding-left: 4px; }
    .app-timeline li { position: relative; display: flex; gap: 14px; padding-bottom: 22px; }
    .app-timeline li:not(:last-child)::before {
        content: ''; position: absolute; left: 13px; top: 26px; bottom: 0;
        width: 2px; background: #e3e3df;
    }
    .app-timeline .node {
        flex: 0 0 auto; width: 28px; height: 28px; border-radius: 50%;
        border: 2px solid #e3e3df; background: #fff;
        display: flex; align-items: center; justify-content: center;
        font-size: .85rem; color: #fff; z-index: 1;
    }
    .app-timeline li.done .node { background: #79A09F; border-color: #79A09F; }
    .app-timeline li.active .node { border-color: #A2A941; background: #A2A941; }
    .app-timeline li.rejected .node { background: #B36959; border-color: #B36959; }
</style>
@endsection
