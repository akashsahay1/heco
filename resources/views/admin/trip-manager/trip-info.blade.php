{{-- Trip Info tab content - included in trip-manager/layout.blade.php --}}
<div class="p-3">
    <form id="tripInfoForm">
        <div class="row">
            {{-- LEFT COLUMN --}}
            <div class="col-md-7">

                {{-- 1. Trip Identity --}}
                <div class="card mb-3">
                    <div class="card-header py-2">
                        <h6 class="mb-0"><i class="bi bi-tag"></i> Trip Identity</h6>
                    </div>
                    <div class="card-body">
                        <div class="row g-2">
                            <div class="col-md-4">
                                <label class="form-label small fw-bold">Trip ID</label>
                                <input type="text" class="form-control form-control-sm bg-light" value="{{ $trip->trip_id }}" readonly>
                            </div>
                            <div class="col-md-8">
                                <label class="form-label small fw-bold">Trip Name</label>
                                <input type="text" class="form-control form-control-sm" name="trip_name" value="{{ $trip->trip_name }}">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-bold">Status</label>
                                <select class="form-select form-select-sm" name="status">
                                    <option value="not_confirmed" {{ $trip->status === 'not_confirmed' ? 'selected' : '' }}>Not Confirmed</option>
                                    <option value="confirmed" {{ $trip->status === 'confirmed' ? 'selected' : '' }}>Confirmed</option>
                                    <option value="running" {{ $trip->status === 'running' ? 'selected' : '' }}>Running</option>
                                    <option value="completed" {{ $trip->status === 'completed' ? 'selected' : '' }}>Completed</option>
                                    <option value="cancelled" {{ $trip->status === 'cancelled' ? 'selected' : '' }}>Cancelled</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-bold">Stage</label>
                                <select class="form-select form-select-sm" name="stage">
                                    <option value="open" {{ ($trip->stage ?? 'open') === 'open' ? 'selected' : '' }}>Open</option>
                                    <option value="closed" {{ ($trip->stage ?? '') === 'closed' ? 'selected' : '' }}>Closed</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- 2. Group & Logistics --}}
                <div class="card mb-3">
                    <div class="card-header py-2">
                        <h6 class="mb-0"><i class="bi bi-people"></i> Group & Logistics</h6>
                    </div>
                    <div class="card-body">
                        <div class="row g-2">
                            <div class="col-md-4">
                                <label class="form-label small fw-bold">Traveller Origin</label>
                                <select class="form-select form-select-sm" name="traveller_origin">
                                    <option value="">-- Select --</option>
                                    <option value="indian" {{ $trip->traveller_origin === 'indian' ? 'selected' : '' }}>Indian</option>
                                    <option value="foreigner" {{ $trip->traveller_origin === 'foreigner' ? 'selected' : '' }}>Foreigner</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label small fw-bold">Adults</label>
                                <input type="number" class="form-control form-control-sm" name="adults" value="{{ $trip->adults ?? 0 }}" min="0">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small fw-bold">Children</label>
                                <input type="number" class="form-control form-control-sm" name="children" value="{{ $trip->children ?? 0 }}" min="0">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small fw-bold">Infants</label>
                                <input type="number" class="form-control form-control-sm" name="infants" value="{{ $trip->infants ?? 0 }}" min="0">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-bold">Start Date</label>
                                <input type="date" class="form-control form-control-sm" name="start_date" value="{{ $trip->start_date ? $trip->start_date->format('Y-m-d') : '' }}">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-bold">End Date</label>
                                <input type="date" class="form-control form-control-sm" name="end_date" value="{{ $trip->end_date ? $trip->end_date->format('Y-m-d') : '' }}">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-bold">Start Location</label>
                                <input type="text" class="form-control form-control-sm" name="start_location" value="{{ $trip->start_location }}">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-bold">End Location</label>
                                <input type="text" class="form-control form-control-sm" name="end_location" value="{{ $trip->end_location }}">
                            </div>
                        </div>
                    </div>
                </div>

                {{-- 3. Pickup / Drop --}}
                <div class="card mb-3">
                    <div class="card-header py-2">
                        <h6 class="mb-0"><i class="bi bi-geo-alt"></i> Pickup / Drop Details</h6>
                    </div>
                    <div class="card-body">
                        <div class="row g-2">
                            <div class="col-md-6">
                                <label class="form-label small fw-bold">Pickup Location</label>
                                <input type="text" class="form-control form-control-sm" name="pickup_location" value="{{ $trip->pickup_location }}">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-bold">Pickup Time</label>
                                <input type="text" class="form-control form-control-sm" name="pickup_time" value="{{ $trip->pickup_time }}" placeholder="e.g. 9:00 AM">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-bold">Drop Location</label>
                                <input type="text" class="form-control form-control-sm" name="drop_location" value="{{ $trip->drop_location }}">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-bold">Drop Time</label>
                                <input type="text" class="form-control form-control-sm" name="drop_time" value="{{ $trip->drop_time }}" placeholder="e.g. 5:00 PM">
                            </div>
                            <div class="col-12">
                                <label class="form-label small fw-bold">Operations Notes</label>
                                <textarea class="form-control form-control-sm" name="operations_notes" rows="2">{{ $trip->operations_notes }}</textarea>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- 4. Travel Preferences --}}
                <div class="card mb-3">
                    <div class="card-header py-2">
                        <h6 class="mb-0"><i class="bi bi-sliders"></i> Travel Preferences</h6>
                    </div>
                    <div class="card-body">
                        <div class="row g-2">
                            <div class="col-md-6">
                                <label class="form-label small fw-bold">Accommodation Comfort</label>
                                <select class="form-select form-select-sm" name="accommodation_comfort">
                                    <option value="">-- Select --</option>
                                    @foreach(['basic', 'standard', 'comfortable', 'luxury'] as $opt)
                                        <option value="{{ $opt }}" {{ $trip->accommodation_comfort === $opt ? 'selected' : '' }}>{{ ucfirst($opt) }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-bold">Vehicle Comfort</label>
                                <select class="form-select form-select-sm" name="vehicle_comfort">
                                    <option value="">-- Select --</option>
                                    @foreach(['basic', 'standard', 'comfortable', 'luxury'] as $opt)
                                        <option value="{{ $opt }}" {{ $trip->vehicle_comfort === $opt ? 'selected' : '' }}>{{ ucfirst($opt) }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-bold">Guide Preference</label>
                                <select class="form-select form-select-sm" name="guide_preference">
                                    <option value="">-- Select --</option>
                                    @foreach(['no_guide', 'local_guide', 'professional_guide', 'expert_guide'] as $opt)
                                        <option value="{{ $opt }}" {{ $trip->guide_preference === $opt ? 'selected' : '' }}>{{ ucfirst(str_replace('_', ' ', $opt)) }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-bold">Travel Pace</label>
                                <select class="form-select form-select-sm" name="travel_pace">
                                    <option value="">-- Select --</option>
                                    @foreach(['relaxed', 'moderate', 'active', 'intensive'] as $opt)
                                        <option value="{{ $opt }}" {{ $trip->travel_pace === $opt ? 'selected' : '' }}>{{ ucfirst($opt) }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-bold">Budget Sensitivity</label>
                                <select class="form-select form-select-sm" name="budget_sensitivity">
                                    <option value="">-- Select --</option>
                                    @foreach(['budget', 'value', 'moderate', 'premium', 'no_constraint'] as $opt)
                                        <option value="{{ $opt }}" {{ $trip->budget_sensitivity === $opt ? 'selected' : '' }}>{{ ucfirst(str_replace('_', ' ', $opt)) }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-bold">Other Preferences</label>
                                <input type="text" class="form-control form-control-sm" name="other_preferences" value="{{ is_array($trip->other_preferences) ? implode(', ', $trip->other_preferences) : $trip->other_preferences }}" placeholder="Comma separated">
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Save Changes Button --}}
                <div class="mb-3">
                    <button type="submit" class="btn btn-success"><i class="bi bi-check-circle"></i> Save Changes</button>
                </div>

            </div>

            {{-- RIGHT COLUMN --}}
            <div class="col-md-5">

                {{-- 5. Financial Snapshot --}}
                <div class="card mb-3">
                    <div class="card-header py-2 d-flex justify-content-between align-items-center">
                        <h6 class="mb-0"><i class="bi bi-currency-rupee"></i> Financial Snapshot</h6>
                        <button type="button" class="btn btn-sm btn-outline-primary" id="btnRecalc"><i class="bi bi-calculator"></i> Recalculate</button>
                    </div>
                    <div class="card-body p-0">
                        <table class="table table-sm table-borderless mb-0">
                            <tbody>
                                <tr><td class="ps-3 small text-muted">Transport Cost</td><td class="text-end pe-3 small">&#8377;{{ number_format($trip->transport_cost ?? 0, 2) }}</td></tr>
                                <tr><td class="ps-3 small text-muted">Accommodation Cost</td><td class="text-end pe-3 small">&#8377;{{ number_format($trip->accommodation_cost ?? 0, 2) }}</td></tr>
                                <tr><td class="ps-3 small text-muted">Guide Cost</td><td class="text-end pe-3 small">&#8377;{{ number_format($trip->guide_cost ?? 0, 2) }}</td></tr>
                                <tr><td class="ps-3 small text-muted">Activity Cost</td><td class="text-end pe-3 small">&#8377;{{ number_format($trip->activity_cost ?? 0, 2) }}</td></tr>
                                <tr><td class="ps-3 small text-muted">Other Cost</td><td class="text-end pe-3 small">&#8377;{{ number_format($trip->other_cost ?? 0, 2) }}</td></tr>
                                <tr class="border-top"><td class="ps-3 small fw-bold">Total Cost</td><td class="text-end pe-3 small fw-bold">&#8377;{{ number_format($trip->total_cost ?? 0, 2) }}</td></tr>
                            </tbody>
                        </table>
                        <hr class="my-1">
                        <table class="table table-sm table-borderless mb-0">
                            <tbody>
                                <tr>
                                    <td class="ps-3 small text-muted">RP Margin</td>
                                    <td class="text-end pe-3 small">
                                        <input type="number" class="form-control form-control-sm d-inline-block" style="width: 70px;" name="margin_rp_percent" value="{{ $trip->margin_rp_percent ?? 0 }}" step="0.01"> %
                                    </td>
                                    <td class="text-end pe-3 small">&#8377;{{ number_format($trip->margin_rp_amount ?? 0, 2) }}</td>
                                </tr>
                                <tr>
                                    <td class="ps-3 small text-muted">HRP Margin</td>
                                    <td class="text-end pe-3 small">
                                        <input type="number" class="form-control form-control-sm d-inline-block" style="width: 70px;" name="margin_hrp_percent" value="{{ $trip->margin_hrp_percent ?? 0 }}" step="0.01"> %
                                    </td>
                                    <td class="text-end pe-3 small">&#8377;{{ number_format($trip->margin_hrp_amount ?? 0, 2) }}</td>
                                </tr>
                                <tr>
                                    <td class="ps-3 small text-muted">HCT Commission</td>
                                    <td class="text-end pe-3 small">
                                        <input type="number" class="form-control form-control-sm d-inline-block" style="width: 70px;" name="commission_hct_percent" value="{{ $trip->commission_hct_percent ?? 0 }}" step="0.01"> %
                                    </td>
                                    <td class="text-end pe-3 small">&#8377;{{ number_format($trip->commission_hct_amount ?? 0, 2) }}</td>
                                </tr>
                            </tbody>
                        </table>
                        <hr class="my-1">
                        <table class="table table-sm table-borderless mb-0">
                            <tbody>
                                <tr><td class="ps-3 small fw-bold">Subtotal</td><td class="text-end pe-3 small fw-bold">&#8377;{{ number_format($trip->subtotal ?? 0, 2) }}</td></tr>
                                <tr><td class="ps-3 small text-muted">GST</td><td class="text-end pe-3 small">&#8377;{{ number_format($trip->gst_amount ?? 0, 2) }}</td></tr>
                                <tr class="border-top bg-success bg-opacity-10">
                                    <td class="ps-3 fw-bold">Final Price</td>
                                    <td class="text-end pe-3 fw-bold text-success fs-6">&#8377;{{ number_format($trip->final_price ?? 0, 2) }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                {{-- 6. Payments from Traveller --}}
                <div class="card mb-3">
                    <div class="card-header py-2 d-flex justify-content-between align-items-center">
                        <h6 class="mb-0"><i class="bi bi-wallet2"></i> Payments from Traveller</h6>
                        <button type="button" class="btn btn-sm btn-outline-success" data-bs-toggle="collapse" data-bs-target="#addPaymentCollapse"><i class="bi bi-plus"></i> Add Payment</button>
                    </div>
                    <div class="card-body p-0">
                        {{-- Add Payment Inline Form --}}
                        <div class="collapse" id="addPaymentCollapse">
                            <div class="p-3 bg-light border-bottom">
                                <form id="addPaymentForm">
                                    <div class="row g-2">
                                        <div class="col-md-4">
                                            <input type="number" class="form-control form-control-sm" name="amount" placeholder="Amount" step="0.01" required>
                                        </div>
                                        <div class="col-md-4">
                                            <input type="date" class="form-control form-control-sm" name="payment_date" value="{{ date('Y-m-d') }}" required>
                                        </div>
                                        <div class="col-md-4">
                                            <select class="form-select form-select-sm" name="mode" required>
                                                <option value="">-- Mode --</option>
                                                <option value="bank_transfer">Bank Transfer</option>
                                                <option value="upi">UPI</option>
                                                <option value="cash">Cash</option>
                                                <option value="card">Card</option>
                                                <option value="other">Other</option>
                                            </select>
                                        </div>
                                        <div class="col-md-8">
                                            <input type="text" class="form-control form-control-sm" name="notes" placeholder="Notes (optional)">
                                        </div>
                                        <div class="col-md-4">
                                            <button type="submit" class="btn btn-sm btn-success w-100"><i class="bi bi-check"></i> Record</button>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>

                        {{-- Payment History Table --}}
                        <div class="table-responsive">
                            <table class="table table-sm table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th class="small">Date</th>
                                        <th class="small text-end">Amount</th>
                                        <th class="small">Mode</th>
                                        <th class="small">Notes</th>
                                        <th class="small">Recorded By</th>
                                    </tr>
                                </thead>
                                <tbody id="paymentHistory">
                                    <tr><td colspan="5" class="text-muted text-center">Loading...</td></tr>
                                </tbody>
                            </table>
                        </div>
                        <div class="px-3 py-2 bg-light border-top d-flex justify-content-between">
                            <div>
                                <span class="small text-muted">Total Paid:</span>
                                <strong class="ms-1" id="totalPaidDisplay">--</strong>
                            </div>
                            <div>
                                <span class="small text-muted">Final Price:</span>
                                <strong class="ms-1">&#8377;{{ number_format($trip->final_price ?? 0, 2) }}</strong>
                            </div>
                            <div>
                                <span class="small text-muted">Balance Due:</span>
                                <strong class="ms-1" id="balanceDueDisplay">--</strong>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- 7. Payments to SPs --}}
                <div class="card mb-3">
                    <div class="card-header py-2">
                        <h6 class="mb-0"><i class="bi bi-building"></i> Payments to Service Providers</h6>
                    </div>
                    <div class="card-body p-0">
                        @if($trip->spPayments && $trip->spPayments->count())
                            <div class="list-group list-group-flush">
                                @foreach($trip->spPayments as $spPayment)
                                    <div class="list-group-item p-2">
                                        <div class="d-flex justify-content-between align-items-center" data-bs-toggle="collapse" data-bs-target="#spPaymentDetail{{ $spPayment->id }}" role="button">
                                            <div>
                                                <strong class="small">{{ $spPayment->serviceProvider->name ?? 'Unknown SP' }}</strong>
                                                <span class="badge bg-secondary ms-1">{{ $spPayment->service_type ?? 'service' }}</span>
                                            </div>
                                            <div class="text-end small">
                                                <span class="text-muted">Due: &#8377;{{ number_format($spPayment->amount_due ?? 0, 2) }}</span>
                                                <span class="mx-1">|</span>
                                                <span class="text-success">Paid: &#8377;{{ number_format($spPayment->amount_paid ?? 0, 2) }}</span>
                                                <span class="mx-1">|</span>
                                                <span class="{{ ($spPayment->balance ?? 0) > 0 ? 'text-danger' : 'text-success' }}">Bal: &#8377;{{ number_format($spPayment->balance ?? 0, 2) }}</span>
                                                <i class="bi bi-chevron-down ms-2"></i>
                                            </div>
                                        </div>
                                        <div class="collapse mt-2" id="spPaymentDetail{{ $spPayment->id }}">
                                            @if($spPayment->entries && $spPayment->entries->count())
                                                <table class="table table-sm table-bordered mb-0 small">
                                                    <thead class="table-light">
                                                        <tr><th>Date</th><th>Amount</th><th>Mode</th><th>Notes</th></tr>
                                                    </thead>
                                                    <tbody>
                                                        @foreach($spPayment->entries as $entry)
                                                            <tr>
                                                                <td>{{ $entry->payment_date ? $entry->payment_date->format('d M Y') : '-' }}</td>
                                                                <td class="text-success">&#8377;{{ number_format($entry->amount ?? 0, 2) }}</td>
                                                                <td>{{ $entry->mode ?? '-' }}</td>
                                                                <td>{{ $entry->notes ?? '' }}</td>
                                                            </tr>
                                                        @endforeach
                                                    </tbody>
                                                </table>
                                            @else
                                                <p class="text-muted small mb-0 ps-2">No payment entries recorded.</p>
                                            @endif
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @else
                            <div class="p-3 text-muted text-center small">No service provider payments recorded.</div>
                        @endif
                    </div>
                </div>

                {{-- 8. General Notes --}}
                <div class="card mb-3">
                    <div class="card-header py-2 d-flex justify-content-between align-items-center">
                        <h6 class="mb-0"><i class="bi bi-journal-text"></i> General Notes</h6>
                        <button type="button" class="btn btn-sm btn-outline-success" id="btnSaveNotes"><i class="bi bi-check"></i> Save Notes</button>
                    </div>
                    <div class="card-body">
                        <textarea class="form-control form-control-sm" id="generalNotes" rows="5" placeholder="Free-text notes about this trip...">{{ $trip->general_notes }}</textarea>
                    </div>
                </div>

            </div>
        </div>
    </form>
</div>
