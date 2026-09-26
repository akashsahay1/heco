{{--
    The partner's own work, under one heading, the way the app puts it.

    The app has a single Services screen whose tabs appear according to what
    the partner is: Rate card for an OSP, Experiences for an HLH, My region for
    an HRP. Somebody who is two of those sees two tabs. The website had the
    same three things as three unrelated pages reached from different places,
    so the same partner met a different shape depending which one they opened.

    A tab that does not apply is not drawn at all rather than drawn and
    refused, which is what the app does and the reason its version reads
    clearly. Where only one applies, no tab bar is drawn either: a row of one
    tab is furniture, not navigation.

    $current is 'pricing', 'experiences' or 'region'.
--}}
@php
    $sp = $provider ?? null;
    $tabs = [];
    if ($sp && $sp->suppliesServices()) {
        $tabs[] = ['pricing', 'Rate card', route('sp.pricing'), 'bi-list-check'];
    }
    if ($sp && $sp->isHost()) {
        $tabs[] = ['experiences', 'Experiences', route('sp.experiences'), 'bi-star'];
    }
    if ($sp && $sp->hasType('hrp')) {
        $tabs[] = ['region', 'My region', route('sp.region'), 'bi-map'];
    }
    // Named after what is on it. A partner who holds only the region is not
    // looking at services, which is the app's own reasoning.
    $heading = (count($tabs) === 1 && $tabs[0][0] === 'region') ? 'My region' : 'Services';
@endphp

<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0">{{ $heading }}</h4>
    <a href="{{ route('sp.dashboard') }}" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-arrow-left"></i> Dashboard
    </a>
</div>

@if(count($tabs) > 1)
    <ul class="nav nav-tabs mb-3">
        @foreach($tabs as [$key, $label, $url, $icon])
            <li class="nav-item">
                <a class="nav-link {{ ($current ?? '') === $key ? 'active' : '' }}" href="{{ $url }}">
                    <i class="bi {{ $icon }} me-1"></i>{{ $label }}
                </a>
            </li>
        @endforeach
    </ul>
@endif
