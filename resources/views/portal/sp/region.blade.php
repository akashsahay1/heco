@extends('portal.layout')
@section('title', 'My Region - HECO')

@section('content')
{{--
    A regional partner's own screen, so that the third of the app's three tabs
    has somewhere to go on the website too. It shows the region they hold and
    the trips travelling through it. Everything here was already on the
    dashboard; it has a page of its own now because the app gives it one.
--}}
<div class="container py-4">
    <div class="row justify-content-center">
        <div class="col-lg-9">

            @include('portal.sp._service-tabs', ['current' => 'region'])

            @if(! $provider->region)
                <div class="card">
                    <div class="card-body text-center py-5">
                        <i class="bi bi-map fs-1 text-muted"></i>
                        <h5 class="mt-3">No region yet</h5>
                        <p class="text-muted mb-0">
                            HECO has not yet named the region you coordinate. We will be in touch.
                        </p>
                    </div>
                </div>
            @else
                <div class="card mb-3">
                    <div class="card-header py-2">
                        <h6 class="mb-0"><i class="bi bi-map"></i> {{ $provider->region->name }}</h6>
                    </div>
                    <div class="card-body">
                        @if($provider->region->country)
                            <p class="small text-muted mb-1">{{ $provider->region->country }}</p>
                        @endif
                        @if($provider->region->description)
                            <p class="small mb-0">{{ $provider->region->description }}</p>
                        @endif
                    </div>
                </div>

                <div class="card mb-3">
                    <div class="card-header py-2">
                        <h6 class="mb-0"><i class="bi bi-people"></i> Partners in your region</h6>
                    </div>
                    <div class="card-body p-0">
                        @if($partners->isEmpty())
                            <p class="text-muted small mb-0 p-3">
                                Nobody is working in this region yet.
                            </p>
                        @else
                            <div class="table-responsive">
                                <table class="table table-sm mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th class="small">Name</th>
                                            <th class="small">What they do</th>
                                            <th class="small">Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($partners as $p)
                                            <tr>
                                                <td class="small">{{ $p->name }}</td>
                                                <td class="small">
                                                    {{ collect((array) $p->provider_types)
                                                        ->map(fn ($t) => strtoupper($t))->implode(', ') }}
                                                </td>
                                                <td class="small">{{ ucfirst((string) $p->status) }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif
                    </div>
                </div>

                <div class="card">
                    <div class="card-header py-2">
                        <h6 class="mb-0"><i class="bi bi-signpost-split"></i> Trips travelling here</h6>
                    </div>
                    <div class="card-body p-0">
                        @if($trips->isEmpty())
                            <p class="text-muted small mb-0 p-3">
                                No trips are booked into this region yet.
                            </p>
                        @else
                            <div class="table-responsive">
                                <table class="table table-sm mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th class="small">Trip</th>
                                            <th class="small">Status</th>
                                            <th class="small">Dates</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($trips as $t)
                                            <tr>
                                                <td class="small">{{ $t->trip_id }}</td>
                                                <td class="small">{{ ucfirst(str_replace('_', ' ', (string) $t->status)) }}</td>
                                                <td class="small">
                                                    {{ $t->start_date ? $t->start_date->format('j M Y') : '-' }}
                                                    @if($t->end_date) to {{ $t->end_date->format('j M Y') }} @endif
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif
                    </div>
                </div>
            @endif

        </div>
    </div>
</div>
@endsection
