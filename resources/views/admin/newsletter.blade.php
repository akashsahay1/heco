@extends('admin.layout')
@section('title', 'Newsletter - HCT')
@section('content')

<div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="mb-0">
        Newsletter Subscribers
        <span class="badge bg-secondary ms-2" title="Subscribed in current view">{{ number_format($subscribers->total()) }}</span>
    </h5>
    <div class="d-flex gap-2">
        <button type="button" class="btn btn-primary btn-sm" id="btnComposeNewsletter">
            <i class="bi bi-envelope-paper"></i> Compose & Send
        </button>
    </div>
</div>

{{-- Stat strip --}}
<div class="row g-2 mb-3">
    <div class="col-md-3 col-6">
        <div class="card border-0 bg-light">
            <div class="card-body py-2 px-3">
                <div class="small text-muted">Subscribed</div>
                <div class="fs-5 fw-bold">{{ number_format($stats['total']) }}</div>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-6">
        <div class="card border-0 bg-light">
            <div class="card-body py-2 px-3">
                <div class="small text-muted">Customers</div>
                <div class="fs-5 fw-bold text-success">{{ number_format($stats['customers']) }}</div>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-6">
        <div class="card border-0 bg-light">
            <div class="card-body py-2 px-3">
                <div class="small text-muted">Non-customers</div>
                <div class="fs-5 fw-bold text-secondary">{{ number_format($stats['non_customers']) }}</div>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-6">
        <div class="card border-0 bg-light">
            <div class="card-body py-2 px-3">
                <div class="small text-muted">Unsubscribed</div>
                <div class="fs-5 fw-bold text-muted">{{ number_format($stats['unsubscribed']) }}</div>
            </div>
        </div>
    </div>
</div>

{{-- Filter card --}}
<form method="GET" action="{{ url('/newsletter') }}" class="card mb-3" id="newsletterFilterForm">
    <div class="card-body py-2">
        <div class="row g-2 align-items-end">
            <div class="col-md-3">
                <label class="form-label small text-muted mb-1">Status</label>
                <select class="form-select form-select-sm custom-select" name="segment">
                    <option value="subscribed"   {{ $segment === 'subscribed'   ? 'selected' : '' }}>Subscribed</option>
                    <option value="unsubscribed" {{ $segment === 'unsubscribed' ? 'selected' : '' }}>Unsubscribed</option>
                    <option value="all"          {{ $segment === 'all'          ? 'selected' : '' }}>All</option>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label small text-muted mb-1">Customer?</label>
                <select class="form-select form-select-sm custom-select" name="customer">
                    <option value="any" {{ $customer === 'any' ? 'selected' : '' }}>Any</option>
                    <option value="yes" {{ $customer === 'yes' ? 'selected' : '' }}>Yes (registered user)</option>
                    <option value="no"  {{ $customer === 'no'  ? 'selected' : '' }}>No (email only)</option>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label small text-muted mb-1">Search email</label>
                <input type="text" name="search" value="{{ $search }}" class="form-control form-control-sm" placeholder="Type to search...">
            </div>
            <div class="col-md-3 d-flex gap-2">
                <button type="submit" class="btn btn-primary btn-sm">
                    <i class="bi bi-funnel"></i> Apply
                </button>
                @if($segment !== 'subscribed' || $customer !== 'any' || $search !== '')
                    <a href="{{ url('/newsletter') }}" class="btn btn-outline-secondary btn-sm">
                        <i class="bi bi-x-circle"></i> Clear
                    </a>
                @endif
            </div>
        </div>
    </div>
</form>

<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover table-sm mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Email</th>
                        <th>Customer</th>
                        <th>Source</th>
                        <th>Subscribed</th>
                        <th>Last Emailed</th>
                        <th>Status</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($subscribers as $s)
                        <tr data-subscriber-id="{{ $s->id }}">
                            <td>
                                <span class="fw-semibold">{{ $s->email }}</span>
                                @if($s->user)
                                    <div class="small text-muted">{{ $s->user->full_name }}</div>
                                @endif
                            </td>
                            <td>
                                @if($s->is_customer)
                                    <span class="badge bg-success">Yes</span>
                                @else
                                    <span class="badge bg-light text-dark">No</span>
                                @endif
                            </td>
                            <td><small class="text-muted">{{ $s->source ?: '—' }}</small></td>
                            <td><small>{{ optional($s->subscribed_at)->format('Y-m-d H:i') ?: '—' }}</small></td>
                            <td><small class="text-muted">{{ optional($s->last_emailed_at)->format('Y-m-d H:i') ?: '—' }}</small></td>
                            <td>
                                @if($s->unsubscribed_at)
                                    <span class="badge bg-secondary">Unsubscribed</span>
                                @else
                                    <span class="badge bg-success-subtle text-success-emphasis">Active</span>
                                @endif
                            </td>
                            <td class="text-end">
                                @if(!$s->unsubscribed_at)
                                    <button type="button" class="btn btn-outline-secondary btn-xs btn-unsubscribe" data-id="{{ $s->id }}" data-email="{{ $s->email }}" title="Mark as unsubscribed">
                                        <i class="bi bi-x-circle"></i>
                                    </button>
                                @else
                                    <button type="button" class="btn btn-outline-success btn-xs btn-resubscribe" data-id="{{ $s->id }}" data-email="{{ $s->email }}" title="Re-subscribe">
                                        <i class="bi bi-arrow-counterclockwise"></i>
                                    </button>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center text-muted py-4">No subscribers match the current filter.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @if($subscribers->lastPage() > 1)
        <div class="card-footer bg-white py-2">
            {{ $subscribers->links() }}
        </div>
    @endif
</div>

{{-- Compose & Send Modal --}}
<div class="modal fade" id="composeNewsletterModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="bi bi-envelope-paper me-2"></i> Send newsletter campaign
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-info py-2 small mb-3">
                    <i class="bi bi-info-circle me-1"></i>
                    This sends to <strong id="composeRecipientCount">…</strong> active subscribers matching the current filter
                    (<span id="composeFilterSummary"></span>).
                    Unsubscribed rows are always excluded.
                </div>
                <div class="mb-3">
                    <label class="form-label">Subject</label>
                    <input type="text" class="form-control" id="composeSubject" maxlength="180" placeholder="e.g. New region: Spiti Valley experiences are live">
                </div>
                <div class="mb-3">
                    <label class="form-label">HTML body</label>
                    <textarea class="form-control font-monospace" id="composeBody" rows="14" placeholder="Paste or write HTML here. The HECO branded header/footer is added automatically."></textarea>
                    <div class="form-text small">Tip: simple tags like &lt;p&gt;, &lt;h2&gt;, &lt;a&gt;, &lt;ul&gt;/&lt;li&gt;, &lt;img&gt; are all fine. Inline styles work best in email clients.</div>
                </div>
                <details class="mb-2">
                    <summary class="small text-muted">Preview body HTML</summary>
                    <div class="border rounded p-3 mt-2 bg-white" id="composePreview" style="min-height: 80px;"></div>
                </details>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary btn-sm" id="btnSendCampaign">
                    <i class="bi bi-send"></i> Send to <span id="composeSendCount">…</span>
                </button>
            </div>
        </div>
    </div>
</div>

@endsection

@section('js')
<script>
jQuery(function () {
    // Snapshot the current filter so we can re-send the same criteria to the
    // bulk-send endpoint (so admin doesn't have to re-pick filters).
    var currentFilter = {
        segment:  @json($segment),
        customer: @json($customer),
        search:   @json($search),
    };

    function refreshCount() {
        ajaxPost({
            get_newsletter_send_count: 1,
            segment:  currentFilter.segment,
            customer: currentFilter.customer,
            search:   currentFilter.search,
        }, function (resp) {
            var n = resp.count || 0;
            jQuery('#composeRecipientCount').text(n.toLocaleString());
            jQuery('#composeSendCount').text(n.toLocaleString());
        }, function () {
            jQuery('#composeRecipientCount, #composeSendCount').text('?');
        });
    }

    jQuery('#btnComposeNewsletter').on('click', function () {
        var summary = [];
        if (currentFilter.customer === 'yes') summary.push('customers only');
        if (currentFilter.customer === 'no')  summary.push('non-customers only');
        if (currentFilter.search)             summary.push('matching "' + currentFilter.search + '"');
        if (!summary.length) summary.push('all active subscribers');
        jQuery('#composeFilterSummary').text(summary.join(', '));
        refreshCount();
        new bootstrap.Modal(jQuery('#composeNewsletterModal')[0]).show();
    });

    // Live preview on each textarea change
    jQuery('#composeBody').on('input', function () {
        jQuery('#composePreview').html(jQuery(this).val());
    });

    jQuery('#btnSendCampaign').on('click', function () {
        var subj = jQuery('#composeSubject').val().trim();
        var body = jQuery('#composeBody').val().trim();
        if (!subj) {
            Swal.fire({ title: 'Subject is required', icon: 'warning', confirmButtonColor: '#79a09f' });
            return;
        }
        if (!body) {
            Swal.fire({ title: 'Body is required', icon: 'warning', confirmButtonColor: '#79a09f' });
            return;
        }
        var btn = jQuery(this);
        var origHtml = btn.html();
        Swal.fire({
            title: 'Send campaign?',
            text: 'This will email every active subscriber matching the current filter.',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Yes, send now',
            confirmButtonColor: '#79a09f'
        }).then(function (res) {
            if (!res.isConfirmed) return;
            btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm"></span> Sending...');
            ajaxPost({
                send_newsletter_campaign: 1,
                subject:  subj,
                body:     body,
                segment:  currentFilter.segment,
                customer: currentFilter.customer,
                search:   currentFilter.search,
            }, function (resp) {
                btn.prop('disabled', false).html(origHtml);
                bootstrap.Modal.getInstance(jQuery('#composeNewsletterModal')[0]).hide();
                Swal.fire({
                    title: 'Sent',
                    text: 'Delivered to ' + (resp.sent || 0) + ' subscriber(s)' + (resp.failed ? ' (' + resp.failed + ' failed — check logs).' : '.'),
                    icon: 'success',
                    confirmButtonColor: '#79a09f'
                }).then(function () { window.location.reload(); });
            }, function (xhr) {
                btn.prop('disabled', false).html(origHtml);
                var msg = xhr.responseJSON ? (xhr.responseJSON.error || 'Send failed') : 'Send failed';
                Swal.fire({ title: 'Send failed', text: msg, icon: 'error', confirmButtonColor: '#79a09f' });
            });
        });
    });

    // Unsubscribe / Resubscribe row buttons
    jQuery('.btn-unsubscribe, .btn-resubscribe').on('click', function () {
        var btn = jQuery(this);
        var id = btn.data('id');
        var unsubscribe = btn.hasClass('btn-unsubscribe');
        Swal.fire({
            title: unsubscribe ? 'Unsubscribe ' + btn.data('email') + '?' : 'Re-subscribe ' + btn.data('email') + '?',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: unsubscribe ? 'Yes, unsubscribe' : 'Yes, re-subscribe',
            confirmButtonColor: '#79a09f'
        }).then(function (res) {
            if (!res.isConfirmed) return;
            ajaxPost({
                set_subscriber_status: 1,
                id: id,
                unsubscribed: unsubscribe ? 1 : 0,
            }, function () {
                window.location.reload();
            });
        });
    });
});
</script>
@endsection
