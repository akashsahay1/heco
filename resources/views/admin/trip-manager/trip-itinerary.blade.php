{{-- Trip Itinerary tab content - included in trip-manager/layout.blade.php --}}
<div class="container-fluid py-2">
    <div class="row">
        {{-- Left column: Experience Search --}}
        <div class="col-md-3 trip-manager-col tm-sidebar">
            <h6 class="mb-2"><i class="bi bi-search"></i> Search Experiences</h6>
            <div class="mb-2">
                <input type="text" class="form-control form-control-sm" id="searchExpInput" placeholder="Search by name...">
            </div>
            <div class="mb-2">
                <select class="form-select form-select-sm" id="searchExpRegion">
                    <option value="">All Regions</option>
                    @if(isset($regions))
                        @foreach($regions as $r)
                            <option value="{{ $r->id }}">{{ $r->name }}</option>
                        @endforeach
                    @elseif($trip->regions)
                        @foreach($trip->regions as $r)
                            <option value="{{ $r->id }}">{{ $r->name }}</option>
                        @endforeach
                    @endif
                </select>
            </div>
            <div class="mb-2">
                <select class="form-select form-select-sm" id="searchExpType">
                    <option value="">All Types</option>
                    <option value="trek">Trek</option>
                    <option value="cultural">Cultural</option>
                    <option value="adventure">Adventure</option>
                    <option value="wildlife">Wildlife</option>
                    <option value="wellness">Wellness</option>
                    <option value="culinary">Culinary</option>
                    <option value="village">Village</option>
                    <option value="other">Other</option>
                </select>
            </div>
            <button class="btn btn-sm btn-success w-100 mb-2" id="searchExpBtn"><i class="bi bi-search"></i> Search</button>

            <hr class="my-2">

            <div class="mb-2">
                <label class="form-label small fw-bold">Add to Day:</label>
                <select class="form-select form-select-sm" id="targetDaySelect">
                    <option value="">-- Select Day --</option>
                    @foreach($trip->tripDays as $day)
                        <option value="{{ $day->id }}">Day {{ $day->day_number }}{{ $day->title ? ' - ' . $day->title : '' }}</option>
                    @endforeach
                </select>
            </div>

            <div id="expSearchResults" style="max-height: 400px; overflow-y: auto;">
                <p class="text-muted small text-center">Search for experiences above</p>
            </div>

            <hr class="my-2">
            <button class="btn btn-sm btn-success w-100" id="btnAddTripDay"><i class="bi bi-plus"></i> Add Day</button>
        </div>

        {{-- Center column: Timeline --}}
        <div class="col-md-6 trip-manager-col">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <h6 class="mb-0"><i class="bi bi-calendar3"></i> Itinerary Timeline</h6>
                <div class="d-flex gap-1">
                    <button class="btn btn-sm btn-outline-primary" id="btnAiRecalc"><i class="bi bi-robot"></i> Ask AI to Recalculate</button>
                </div>
            </div>
            <div id="itineraryTimeline">
                <p class="text-muted text-center">Loading itinerary...</p>
            </div>
            <div id="aiResponse" class="mt-2"></div>
        </div>

        {{-- Right column: Day Services --}}
        <div class="col-md-3 trip-manager-col tm-sidebar">
            <div id="dayServicesPanel" style="display: none;">
                <h6 class="mb-2"><i class="bi bi-gear"></i> <span id="selectedDayLabel">Day Services</span></h6>
                <input type="hidden" id="serviceDayId" value="">

                <div id="dayServicesList" class="mb-3">
                    <p class="text-muted small text-center">Select a day to view services</p>
                </div>

                <hr class="my-2">

                {{-- Add Service collapsible form --}}
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <h6 class="small mb-0">Add Service</h6>
                    <button class="btn btn-sm btn-outline-success" data-bs-toggle="collapse" data-bs-target="#addServiceCollapse"><i class="bi bi-plus"></i></button>
                </div>
                <div class="collapse" id="addServiceCollapse">
                    <form id="addServiceForm">
                        <div class="mb-1">
                            <select class="form-select form-select-sm" name="service_type" required>
                                <option value="">-- Type --</option>
                                <option value="accommodation">Accommodation</option>
                                <option value="transport">Transport</option>
                                <option value="guide">Guide</option>
                                <option value="activity">Activity</option>
                                <option value="meal">Meal</option>
                                <option value="permit">Permit</option>
                                <option value="equipment">Equipment</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                        <div class="mb-1">
                            <input type="text" class="form-control form-control-sm" name="description" placeholder="Description">
                        </div>
                        <div class="row g-1 mb-1">
                            <div class="col-6">
                                <input type="text" class="form-control form-control-sm" name="from_location" placeholder="From">
                            </div>
                            <div class="col-6">
                                <input type="text" class="form-control form-control-sm" name="to_location" placeholder="To">
                            </div>
                        </div>
                        <div class="row g-1 mb-1">
                            <div class="col-6">
                                <input type="number" class="form-control form-control-sm" name="cost" placeholder="Cost" step="0.01" min="0">
                            </div>
                            <div class="col-6">
                                <select class="form-select form-select-sm" name="service_provider_id">
                                    <option value="">No Provider</option>
                                    @if(isset($providers))
                                        @foreach($providers as $p)
                                            <option value="{{ $p->id }}">{{ $p->name }} ({{ $p->provider_type }})</option>
                                        @endforeach
                                    @endif
                                </select>
                            </div>
                        </div>
                        <div class="mb-1">
                            <input type="text" class="form-control form-control-sm" name="notes" placeholder="Notes (optional)">
                        </div>
                        <button type="submit" class="btn btn-sm btn-success w-100"><i class="bi bi-plus"></i> Add Service</button>
                    </form>
                </div>
            </div>

            {{-- Placeholder when no day is selected --}}
            <div id="dayServicesPlaceholder">
                <div class="text-center py-5 text-muted">
                    <i class="bi bi-hand-index fs-1 d-block mb-2"></i>
                    <p class="small">Click on a day in the timeline to view and manage its services.</p>
                </div>
            </div>
        </div>
    </div>
</div>
