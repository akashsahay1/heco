@extends('admin.layout')
@section('title', 'Currencies - HCT')
@section('content')

<div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="mb-0"><i class="bi bi-currency-exchange"></i> Currencies</h5>
    <div class="d-flex gap-2">
        <button type="button" class="btn btn-danger btn-sm d-none" id="btnBulkDelete"><i class="bi bi-trash"></i> Delete Selected</button>
        <button class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#currencyModal" id="btnAddCurrency">
            <i class="bi bi-plus-lg"></i> Add Currency
        </button>
    </div>
</div>

<div class="card mb-3">
    <div class="card-body py-2">
        <div class="row g-2 align-items-end">
            <div class="col-md-3">
                <label class="form-label small mb-1">Status</label>
                <select class="form-select form-select-sm custom-select" id="filterStatus">
                    <option value="">All</option>
                    <option value="1">Active</option>
                    <option value="0">Inactive</option>
                </select>
            </div>
            <div class="col-md-5">
                <label class="form-label small mb-1">Search</label>
                <input type="text" class="form-control form-control-sm" id="filterSearch">
            </div>
            <div class="col-md-2">
                <button class="btn btn-outline-secondary btn-sm w-100" id="btnReset" title="Reset filters">
                    <i class="bi bi-arrow-counterclockwise"></i> Reset
                </button>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover table-sm mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="w-check"><i class="bi bi-check2-square selall-check" role="button" title="Select all"></i></th>
                        <th>Code</th>
                        <th>Symbol</th>
                        <th>Name</th>
                        <th>Locale</th>
                        <th>Rate to USD</th>
                        <th>Status</th>
                        <th class="w-actions-lg">Actions</th>
                    </tr>
                </thead>
                <tbody id="currenciesTable">
                    <tr><td colspan="8" class="text-center text-muted">Loading...</td></tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div id="currenciesPagination" class="mt-3"></div>

<!-- Currency Modal -->
<div class="modal fade" id="currencyModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="currencyModalTitle">Add Currency</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="currencyId">
                <div class="row g-2 mb-3">
                    <div class="col-md-4">
                        <label class="form-label small">Code <span class="text-danger">*</span></label>
                        <input type="text" class="form-control form-control-sm text-uppercase" id="currencyCode" maxlength="3">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small">Symbol <span class="text-danger">*</span></label>
                        <input type="text" class="form-control form-control-sm" id="currencySymbol" maxlength="10">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small">Locale</label>
                        <input type="text" class="form-control form-control-sm" id="currencyLocale">
                    </div>
                </div>
                <div class="row g-2 mb-3">
                    <div class="col-md-8">
                        <label class="form-label small">Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control form-control-sm" id="currencyName">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small">Flag Code</label>
                        <div class="input-group input-group-sm">
                            <input type="text" class="form-control form-control-sm" id="currencyFlag" maxlength="5">
                            <span class="input-group-text p-0 px-1" id="flagPreview"></span>
                        </div>
                        <small class="text-muted">2-letter country code (e.g. us, gb, in)</small>
                    </div>
                </div>
                <div class="row g-2 mb-3">
                    <div class="col-md-6">
                        <label class="form-label small">Rate to USD <span class="text-danger">*</span></label>
                        <input type="number" step="0.000001" class="form-control form-control-sm" id="currencyRate">
                        <small class="text-muted">How many of this currency per 1 USD</small>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small">Sort Order</label>
                        <input type="number" class="form-control form-control-sm" id="currencySortOrder" value="0">
                    </div>
                </div>
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" id="currencyActive" checked>
                    <label class="form-check-label small" for="currencyActive">Active</label>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-success btn-sm" id="btnSaveCurrency">
                    <i class="bi bi-check-lg"></i> Save Currency
                </button>
            </div>
        </div>
    </div>
</div>

@endsection

@section('js')
<script>
var allCurrencies = [];

function loadCurrencies(page) {
    var params = { get_currencies_list: 1, page: page || 1 };
    var status = jQuery('#filterStatus').val();
    var search = jQuery('#filterSearch').val();

    if (status !== '') params.status = status;
    if (search) params.search = search;

    ajaxPost(params, function(resp) {
        allCurrencies = resp.data || [];
        renderTable(allCurrencies);
        renderPagination('#currenciesPagination', resp, loadCurrencies);
    });
}

function renderTable(data) {
    var html = '';
    if (!data.length) {
        html = '<tr><td colspan="8" class="text-center text-muted">No currencies found</td></tr>';
        jQuery('#currenciesTable').html(html);
        refreshBulkBtn();
        return;
    }

    data.forEach(function(c) {
        var flagImg = c.flag ? '<img src="/images/flags/' + c.flag + '.png" alt="" class="img-flag"> ' : '';
        html += '<tr>';
        html += '<td><i class="bi bi-square row-check" role="button" data-id="' + c.id + '"></i></td>';
        html += '<td>' + flagImg + '<strong>' + c.code + '</strong></td>';
        html += '<td><span class="fs-5">' + c.symbol + '</span></td>';
        html += '<td>' + c.name + '</td>';
        html += '<td><small class="text-muted">' + (c.locale || '-') + '</small></td>';
        html += '<td>';
        if (c.code === 'USD') {
            html += '<span class="badge bg-primary">1.0 (base)</span>';
        } else {
            html += '<span class="font-monospace">' + parseFloat(c.rate_to_usd).toFixed(4) + '</span>';
        }
        html += '</td>';
        html += '<td>';
        if (c.is_active) {
            html += '<span class="badge bg-success">Active</span>';
        } else {
            html += '<span class="badge bg-secondary">Inactive</span>';
        }
        html += '</td>';
        html += '<td>';
        html += '<button class="btn btn-sm btn-outline-primary me-1 btn-edit" data-id="' + c.id + '" title="Edit"><i class="bi bi-pencil"></i></button>';
        html += '<button class="btn btn-sm btn-outline-' + (c.is_active ? 'warning' : 'success') + ' me-1 btn-toggle" data-id="' + c.id + '" title="' + (c.is_active ? 'Deactivate' : 'Activate') + '"><i class="bi bi-' + (c.is_active ? 'eye-slash' : 'eye') + '"></i></button>';
        html += '<button class="btn btn-sm btn-outline-danger btn-delete" data-id="' + c.id + '" data-code="' + c.code + '" title="Delete"><i class="bi bi-trash"></i></button>';
        html += '</td>';
        html += '</tr>';
    });

    jQuery('#currenciesTable').html(html);
    refreshBulkBtn();
}

function resetForm() {
    jQuery('#currencyId').val('');
    jQuery('#currencyCode').val('').prop('disabled', false);
    jQuery('#currencySymbol').val('');
    jQuery('#currencyName').val('');
    jQuery('#currencyLocale').val('');
    jQuery('#currencyFlag').val('');
    jQuery('#flagPreview').html('');
    jQuery('#currencyRate').val('');
    jQuery('#currencySortOrder').val('0');
    jQuery('#currencyActive').prop('checked', true);
    jQuery('#currencyModalTitle').text('Add Currency');
}

jQuery(function() {
    loadCurrencies();
});

jQuery('#btnAddCurrency').on('click', function() {
    resetForm();
});

jQuery('#filterStatus').on('change', function() {
    loadCurrencies();
});

var searchTimer;
jQuery('#filterSearch').on('keyup', function() {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(function() { loadCurrencies(); }, 400);
});

jQuery('#btnReset').on('click', function() {
    jQuery('#filterStatus').val('');
    if (window.buildCustomDropdown) buildCustomDropdown(jQuery('#filterStatus')[0]);
    jQuery('#filterSearch').val('');
    loadCurrencies();
});

jQuery('#btnSaveCurrency').on('click', function() {
    var data = {
        save_currency: 1,
        code: jQuery('#currencyCode').val(),
        name: jQuery('#currencyName').val(),
        symbol: jQuery('#currencySymbol').val(),
        locale: jQuery('#currencyLocale').val() || 'en-US',
        flag: jQuery('#currencyFlag').val() || '',
        rate_to_usd: jQuery('#currencyRate').val(),
        sort_order: jQuery('#currencySortOrder').val() || 0,
        is_active: jQuery('#currencyActive').is(':checked') ? 1 : 0,
    };

    var currencyId = jQuery('#currencyId').val();
    if (currencyId) data.currency_id = currencyId;

    ajaxPost(data, function(resp) {
        showAlert(resp.message || resp.success || 'Saved', 'success');
        bootstrap.Modal.getInstance(jQuery('#currencyModal')[0]).hide();
        loadCurrencies();
    });
});

jQuery(document).on('click', '.btn-edit', function() {
    var id = jQuery(this).data('id');
    var currency = allCurrencies.find(function(c) { return c.id === id; });
    if (!currency) return;

    jQuery('#currencyId').val(currency.id);
    jQuery('#currencyCode').val(currency.code).prop('disabled', true);
    jQuery('#currencySymbol').val(currency.symbol);
    jQuery('#currencyName').val(currency.name);
    jQuery('#currencyLocale').val(currency.locale || '');
    jQuery('#currencyFlag').val(currency.flag || '');
    jQuery('#flagPreview').html(currency.flag ? '<img src="/images/flags/' + currency.flag + '.png" class="img-flag-plain">' : '');
    jQuery('#currencyRate').val(currency.rate_to_usd);
    jQuery('#currencySortOrder').val(currency.sort_order || 0);
    jQuery('#currencyActive').prop('checked', currency.is_active);
    jQuery('#currencyModalTitle').text('Edit Currency');

    new bootstrap.Modal(jQuery('#currencyModal')[0]).show();
});

jQuery(document).on('click', '.btn-toggle', function() {
    var id = jQuery(this).data('id');
    ajaxPost({ toggle_currency: 1, currency_id: id }, function(resp) {
        showAlert(resp.message || resp.success || 'Saved', 'success');
        loadCurrencies();
    });
});

jQuery(document).on('click', '.btn-delete', function() {
    var id = jQuery(this).data('id');
    var code = jQuery(this).data('code');
    confirmAction('Delete currency "' + code + '"? This cannot be undone.', function() {
        ajaxPost({ delete_currency: 1, currency_id: id }, function(resp) {
            showAlert(resp.message || resp.success || 'Saved', 'success');
            loadCurrencies();
        });
    });
});

// ---- Bulk delete ----
function refreshBulkBtn() {
    jQuery('#btnBulkDelete').toggleClass('d-none', jQuery('.row-check.row-checked').length === 0);
}
jQuery(document).on('click', '.row-check', function() {
    jQuery(this).toggleClass('row-checked').toggleClass('bi-square').toggleClass('bi-check-square');
    refreshBulkBtn();
});
jQuery(document).on('click', '.selall-check', function() {
    var anyUnchecked = jQuery('.row-check:not(.row-checked)').length > 0;
    jQuery('.row-check').each(function() {
        jQuery(this).toggleClass('row-checked', anyUnchecked).toggleClass('bi-square', !anyUnchecked).toggleClass('bi-check-square', anyUnchecked);
    });
    refreshBulkBtn();
});
jQuery('#btnBulkDelete').on('click', function() {
    var ids = jQuery('.row-check.row-checked').map(function() { return jQuery(this).data('id'); }).get();
    if (!ids.length) return;
    confirmAction('Delete ' + ids.length + ' currency(ies)? This cannot be undone.', function() {
        ajaxPost({ bulk_delete_currencies: 1, ids: ids }, function(resp) {
            showAlert(resp.message || 'Deleted', 'success');
            loadCurrencies();
        });
    });
});

// Live flag preview
jQuery('#currencyFlag').on('input', function() {
    var code = jQuery(this).val().toLowerCase().trim();
    if (code.length >= 2) {
        jQuery('#flagPreview').html('<img src="/images/flags/' + code + '.png" class="img-flag-plain" onerror="jQuery(this).hide()">');
    } else {
        jQuery('#flagPreview').html('');
    }
});
</script>
@endsection
