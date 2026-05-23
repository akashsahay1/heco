@extends('admin.layout')
@section('title', 'Regenerative Projects - HCT')
@section('content')

@php
    $regions = \App\Models\Region::where('is_active', 1)->orderBy('name')->get();
@endphp

<div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="mb-0"><i class="bi bi-tree"></i> Regenerative Projects</h5>
    <div class="d-flex gap-2">
        <button type="button" class="btn btn-danger btn-sm d-none" id="btnBulkDelete"><i class="bi bi-slash-circle"></i> Deactivate Selected</button>
        <a href="{{ url('/regenerative-projects/create') }}" class="btn btn-success btn-sm">
            <i class="bi bi-plus-lg"></i> Create New
        </a>
    </div>
</div>

<div class="card mb-3">
    <div class="card-body py-2">
        <div class="row g-2 align-items-end">
            <div class="col-md-3">
                <label class="form-label small mb-1">Region</label>
                <select class="form-select form-select-sm custom-select" id="filterRegion">
                    <option value="">All Regions</option>
                    @foreach($regions as $r)
                        <option value="{{ $r->id }}">{{ $r->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
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
                        <th>Region</th>
                        <th>Action Type</th>
                        <th>Impact Unit</th>
                        <th>Cost Per Unit</th>
                        <th>Status</th>
                        <th class="w-actions">Actions</th>
                    </tr>
                </thead>
                <tbody id="projectsTable">
                    <tr><td colspan="8" class="text-center text-muted">Loading...</td></tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div id="projectsPagination" class="mt-3"></div>

@endsection

@section('js')
<script>
var editRpUrl = "{{ url('/regenerative-projects/__ID__/edit') }}";
function loadProjects(page) {
    var params = { get_regenerative_projects: 1, page: page || 1 };
    var regionId = $('#filterRegion').val();
    var status = $('#filterStatus').val();
    var search = $('#filterSearch').val();

    if (regionId) params.region_id = regionId;
    if (status !== '') params.status = status;
    if (search) params.search = search;

    ajaxPost(params, function(resp) {
        var html = '';
        var items = resp.data || [];
        if (!items.length) {
            html = '<tr><td colspan="8" class="text-center text-muted">No regenerative projects found</td></tr>';
        }
        items.forEach(function(p) {
            html += '<tr>';
            html += '<td><i class="bi bi-square row-check" role="button" data-id="' + p.id + '"></i></td>';
            html += '<td><strong>' + (p.name || '') + '</strong></td>';
            html += '<td>' + (p.region ? p.region.name : '-') + '</td>';
            html += '<td><span class="badge bg-info text-dark">' + (p.action_type || '-') + '</span></td>';
            html += '<td>' + (p.impact_unit || '-') + '</td>';
            html += '<td>' + (p.cost_per_impact_unit ? '<i class="bi bi-currency-rupee"></i>' + Number(p.cost_per_impact_unit).toLocaleString() : '-') + '</td>';
            html += '<td>';
            html += '<div class="form-check form-switch">';
            html += '<input class="form-check-input toggle-status" type="checkbox" data-id="' + p.id + '"' + (p.is_active ? ' checked' : '') + '>';
            html += '</div>';
            html += '</td>';
            html += '<td>';
            html += '<a href="' + editRpUrl.replace('__ID__', p.id) + '" class="btn btn-sm btn-outline-primary me-1" title="Edit"><i class="bi bi-pencil"></i></a>';
            html += '<button class="btn btn-sm btn-outline-danger btn-disable" data-id="' + p.id + '" data-active="' + (p.is_active ? '1' : '0') + '" title="' + (p.is_active ? 'Disable' : 'Enable') + '">';
            html += '<i class="bi bi-' + (p.is_active ? 'eye-slash' : 'eye') + '"></i>';
            html += '</button>';
            html += '</td>';
            html += '</tr>';
        });
        $('#projectsTable').html(html);
        refreshBulkBtn();
        renderPagination('#projectsPagination', resp, loadProjects);
    });
}

$(function() { loadProjects(); });

$('#filterRegion, #filterStatus').on('change', function() { loadProjects(); });

var searchTimer;
$('#filterSearch').on('keyup', function() {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(function() { loadProjects(); }, 400);
});

$('#btnReset').on('click', function() {
    $('#filterRegion, #filterStatus').val('').each(function() {
        if (window.buildCustomDropdown) buildCustomDropdown(this);
    });
    $('#filterSearch').val('');
    loadProjects();
});

$(document).on('change', '.toggle-status', function() {
    var id = $(this).data('id');
    ajaxPost({ disable_regenerative_project: 1, project_id: id }, function(resp) {
        loadProjects();
    });
});

$(document).on('click', '.btn-disable', function() {
    var id = $(this).data('id');
    var isActive = $(this).data('active');
    var action = isActive == 1 ? 'disable' : 'enable';
    confirmAction('Are you sure you want to ' + action + ' this project?', function() {
        ajaxPost({ disable_regenerative_project: 1, project_id: id }, function(resp) {
            loadProjects();
        });
    });
});

// ---- Bulk delete ----
function refreshBulkBtn() {
    $('#btnBulkDelete').toggleClass('d-none', $('.row-check.row-checked').length === 0);
}
$(document).on('click', '.row-check', function() {
    $(this).toggleClass('row-checked').toggleClass('bi-square').toggleClass('bi-check-square');
    refreshBulkBtn();
});
$(document).on('click', '.selall-check', function() {
    var anyUnchecked = $('.row-check:not(.row-checked)').length > 0;
    $('.row-check').each(function() {
        $(this).toggleClass('row-checked', anyUnchecked).toggleClass('bi-square', !anyUnchecked).toggleClass('bi-check-square', anyUnchecked);
    });
    refreshBulkBtn();
});
$('#btnBulkDelete').on('click', function() {
    var ids = $('.row-check.row-checked').map(function() { return $(this).data('id'); }).get();
    if (!ids.length) return;
    confirmAction('Deactivate ' + ids.length + ' project(s)?', function() {
        ajaxPost({ bulk_delete_regenerative_projects: 1, ids: ids }, function(resp) {
            showAlert(resp.message || 'Deactivated', 'success');
            loadProjects();
        });
    });
});
</script>
@endsection
