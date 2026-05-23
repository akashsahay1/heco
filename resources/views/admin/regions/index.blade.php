@extends('admin.layout')
@section('title', 'Regions - HCT')
@section('content')

<div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="mb-0"><i class="bi bi-globe-americas"></i> Regions</h5>
    <div class="d-flex gap-2">
        <button type="button" class="btn btn-danger btn-sm d-none" id="btnBulkDelete"><i class="bi bi-trash"></i> Delete Selected</button>
        <button class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#regionModal" id="btnAddRegion">
            <i class="bi bi-plus-lg"></i> Add Region
        </button>
    </div>
</div>

<div class="card mb-3">
    <div class="card-body py-2">
        <div class="row g-2 align-items-end">
            <div class="col-md-2">
                <label class="form-label small mb-1">Continent</label>
                <select class="form-select form-select-sm custom-select" id="filterContinent">
                    <option value="">All Continents</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small mb-1">Country</label>
                <select class="form-select form-select-sm custom-select" id="filterCountry">
                    <option value="">All Countries</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small mb-1">Status</label>
                <select class="form-select form-select-sm custom-select" id="filterStatus">
                    <option value="">All</option>
                    <option value="1">Active</option>
                    <option value="0">Inactive</option>
                </select>
            </div>
            <div class="col-md-4">
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
                        <th>Name</th>
                        <th>Continent</th>
                        <th>Country</th>
                        <th>Lat / Lng</th>
                        <th>Experiences</th>
                        <th>Status</th>
                        <th class="w-actions-lg">Actions</th>
                    </tr>
                </thead>
                <tbody id="regionsTable">
                    <tr><td colspan="8" class="text-center text-muted">Loading...</td></tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div id="regionsPagination" class="mt-3"></div>

<!-- Region Modal -->
<div class="modal fade" id="regionModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="regionModalTitle">Add Region</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="regionId">
                <div class="mb-3">
                    <label class="form-label small">Name <span class="text-danger">*</span></label>
                    <input type="text" class="form-control form-control-sm" id="regionName" required>
                </div>
                <div class="row g-2 mb-3">
                    <div class="col-md-6">
                        <label class="form-label small">Continent <span class="text-danger">*</span></label>
                        <select class="form-select form-select-sm custom-select" id="regionContinent" required>
                            <option value="">Select...</option>
                            <option value="Africa">Africa</option>
                            <option value="Asia">Asia</option>
                            <option value="Europe">Europe</option>
                            <option value="North America">North America</option>
                            <option value="Oceania">Oceania</option>
                            <option value="South America">South America</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small">Country <span class="text-danger">*</span></label>
                        <input type="text" class="form-control form-control-sm" id="regionCountry" required>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label small">Description</label>
                    <textarea class="form-control form-control-sm" id="regionDescription" rows="3"></textarea>
                </div>
                <div class="row g-2 mb-3">
                    <div class="col-md-6">
                        <label class="form-label small">Latitude</label>
                        <input type="number" step="0.00000001" class="form-control form-control-sm" id="regionLatitude">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small">Longitude</label>
                        <input type="number" step="0.00000001" class="form-control form-control-sm" id="regionLongitude">
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label small">External URL</label>
                    <input type="url" class="form-control form-control-sm" id="regionUrl">
                </div>
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" id="regionActive" checked>
                    <label class="form-check-label small" for="regionActive">Active</label>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-success btn-sm" id="btnSaveRegion">
                    <i class="bi bi-check-lg"></i> Save Region
                </button>
            </div>
        </div>
    </div>
</div>

@endsection

@section('js')
<script>
var allRegions = [];
var continentCountryMap = {};   // continent -> [countries]
var allCountries = [];

function rebuildDropdown(id) {
    if (window.buildCustomDropdown) buildCustomDropdown(jQuery('#' + id)[0]);
}

function loadRegions(page) {
    var params = { get_regions_list: 1, paginate: 1, page: page || 1 };
    var continent = jQuery('#filterContinent').val();
    var country = jQuery('#filterCountry').val();
    var status = jQuery('#filterStatus').val();
    var search = jQuery('#filterSearch').val();

    if (continent) params.continent = continent;
    if (country) params.country = country;
    if (status !== '') params.status = status;
    if (search) params.search = search;

    ajaxPost(params, function(resp) {
        allRegions = resp.data || [];
        renderTable(allRegions);
        renderPagination('#regionsPagination', resp, loadRegions);
    });
}

// One-time fetch of the full region list to build the continent/country map for the cascading filters.
function buildRegionMap() {
    ajaxPost({ get_regions_list: 1 }, function(resp) {
        var data = resp.data || [];
        continentCountryMap = {};
        allCountries = [];
        var continents = [];
        data.forEach(function(r) {
            if (r.continent && continents.indexOf(r.continent) === -1) continents.push(r.continent);
            if (r.country && allCountries.indexOf(r.country) === -1) allCountries.push(r.country);
            if (r.continent) {
                if (!continentCountryMap[r.continent]) continentCountryMap[r.continent] = [];
                if (r.country && continentCountryMap[r.continent].indexOf(r.country) === -1) {
                    continentCountryMap[r.continent].push(r.country);
                }
            }
        });
        continents.sort();
        allCountries.sort();

        var contHtml = '<option value="">All Continents</option>';
        continents.forEach(function(c) { contHtml += '<option value="' + c + '">' + c + '</option>'; });
        jQuery('#filterContinent').html(contHtml);
        rebuildDropdown('filterContinent');
        repopulateCountryFilter();
    });
}

function repopulateCountryFilter() {
    var continent = jQuery('#filterContinent').val();
    var list = continent ? (continentCountryMap[continent] || []).slice() : allCountries.slice();
    list.sort();
    var current = jQuery('#filterCountry').val();
    var html = '<option value="">All Countries</option>';
    list.forEach(function(c) { html += '<option value="' + c + '">' + c + '</option>'; });
    jQuery('#filterCountry').html(html);
    // Keep current selection only if still valid for the chosen continent.
    if (current && list.indexOf(current) !== -1) {
        jQuery('#filterCountry').val(current);
    } else {
        jQuery('#filterCountry').val('');
    }
    rebuildDropdown('filterCountry');
}

function renderTable(data) {
    var html = '';
    if (!data.length) {
        html = '<tr><td colspan="8" class="text-center text-muted">No regions found</td></tr>';
        jQuery('#regionsTable').html(html);
        refreshBulkBtn();
        return;
    }

    var currentContinent = '';
    data.forEach(function(r) {
        if (r.continent !== currentContinent) {
            currentContinent = r.continent;
            html += '<tr class="table-secondary"><td colspan="8" class="fw-bold small">';
            html += '<i class="bi bi-globe me-1"></i> ' + (currentContinent || 'Unknown');
            html += '</td></tr>';
        }

        var expCount = r.experiences_count || 0;
        html += '<tr>';
        html += '<td><i class="bi bi-square row-check" role="button" data-id="' + r.id + '"></i></td>';
        html += '<td><strong>' + r.name + '</strong><br><small class="text-muted">' + (r.slug || '') + '</small></td>';
        html += '<td><small>' + (r.continent || '-') + '</small></td>';
        html += '<td><small>' + (r.country || '-') + '</small></td>';
        html += '<td><small>' + (r.latitude ? parseFloat(r.latitude).toFixed(4) + ', ' + parseFloat(r.longitude).toFixed(4) : '-') + '</small></td>';
        html += '<td><span class="badge bg-light text-dark">' + expCount + '</span></td>';
        html += '<td>';
        if (r.is_active) {
            html += '<span class="badge bg-success">Active</span>';
        } else {
            html += '<span class="badge bg-secondary">Inactive</span>';
        }
        html += '</td>';
        html += '<td>';
        html += '<button class="btn btn-sm btn-outline-primary me-1 btn-edit" data-id="' + r.id + '" title="Edit"><i class="bi bi-pencil"></i></button>';
        html += '<button class="btn btn-sm btn-outline-' + (r.is_active ? 'warning' : 'success') + ' me-1 btn-toggle" data-id="' + r.id + '" title="' + (r.is_active ? 'Deactivate' : 'Activate') + '"><i class="bi bi-' + (r.is_active ? 'eye-slash' : 'eye') + '"></i></button>';
        html += '<button class="btn btn-sm btn-outline-danger btn-delete" data-id="' + r.id + '" data-name="' + r.name + '" title="Delete"><i class="bi bi-trash"></i></button>';
        html += '</td>';
        html += '</tr>';
    });

    jQuery('#regionsTable').html(html);
    refreshBulkBtn();
}

function resetForm() {
    jQuery('#regionId').val('');
    jQuery('#regionName').val('');
    jQuery('#regionContinent').val('');
    rebuildDropdown('regionContinent');
    jQuery('#regionCountry').val('');
    jQuery('#regionDescription').val('');
    jQuery('#regionLatitude').val('');
    jQuery('#regionLongitude').val('');
    jQuery('#regionUrl').val('');
    jQuery('#regionActive').prop('checked', true);
    jQuery('#regionModalTitle').text('Add Region');
}

jQuery(function() {
    buildRegionMap();
    loadRegions();
});

jQuery('#btnAddRegion').on('click', function() {
    resetForm();
});

jQuery(document).on('change', '#filterContinent', function() {
    repopulateCountryFilter();
    loadRegions();
});

jQuery(document).on('change', '#filterCountry, #filterStatus', function() {
    loadRegions();
});

var searchTimer;
jQuery('#filterSearch').on('keyup', function() {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(function() { loadRegions(); }, 400);
});

jQuery('#btnReset').on('click', function() {
    jQuery('#filterContinent').val('');
    rebuildDropdown('filterContinent');
    jQuery('#filterStatus').val('');
    rebuildDropdown('filterStatus');
    jQuery('#filterSearch').val('');
    repopulateCountryFilter();
    loadRegions();
});

jQuery('#btnSaveRegion').on('click', function() {
    var data = {
        save_region: 1,
        name: jQuery('#regionName').val(),
        continent: jQuery('#regionContinent').val(),
        country: jQuery('#regionCountry').val(),
        description: jQuery('#regionDescription').val(),
        latitude: jQuery('#regionLatitude').val(),
        longitude: jQuery('#regionLongitude').val(),
        external_url: jQuery('#regionUrl').val(),
        is_active: jQuery('#regionActive').is(':checked') ? 1 : 0,
    };

    var regionId = jQuery('#regionId').val();
    if (regionId) data.region_id = regionId;

    ajaxPost(data, function(resp) {
        showAlert(resp.message || resp.success || 'Saved', 'success');
        bootstrap.Modal.getInstance(jQuery('#regionModal')[0]).hide();
        buildRegionMap();
        loadRegions();
    });
});

jQuery(document).on('click', '.btn-edit', function() {
    var id = jQuery(this).data('id');
    var region = allRegions.find(function(r) { return r.id === id; });
    if (!region) return;

    jQuery('#regionId').val(region.id);
    jQuery('#regionName').val(region.name);
    jQuery('#regionContinent').val(region.continent);
    rebuildDropdown('regionContinent');
    jQuery('#regionCountry').val(region.country);
    jQuery('#regionDescription').val(region.description || '');
    jQuery('#regionLatitude').val(region.latitude || '');
    jQuery('#regionLongitude').val(region.longitude || '');
    jQuery('#regionUrl').val(region.external_url || '');
    jQuery('#regionActive').prop('checked', region.is_active);
    jQuery('#regionModalTitle').text('Edit Region');

    new bootstrap.Modal(jQuery('#regionModal')[0]).show();
});

jQuery(document).on('click', '.btn-toggle', function() {
    var id = jQuery(this).data('id');
    ajaxPost({ toggle_region: 1, region_id: id }, function(resp) {
        showAlert(resp.message || resp.success || 'Saved', 'success');
        loadRegions();
    });
});

jQuery(document).on('click', '.btn-delete', function() {
    var id = jQuery(this).data('id');
    var name = jQuery(this).data('name');
    confirmAction('Delete region "' + name + '"? This cannot be undone.', function() {
        ajaxPost({ delete_region: 1, region_id: id }, function(resp) {
            showAlert(resp.message || resp.success || 'Saved', 'success');
            buildRegionMap();
            loadRegions();
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
    confirmAction('Delete ' + ids.length + ' region(s)? This cannot be undone.', function() {
        ajaxPost({ bulk_delete_regions: 1, ids: ids }, function(resp) {
            showAlert(resp.message || 'Deleted', 'success');
            buildRegionMap();
            loadRegions();
        });
    });
});
</script>
@endsection
