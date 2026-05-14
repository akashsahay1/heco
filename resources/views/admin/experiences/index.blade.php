@extends('admin.layout')
@section('title', 'Experiences - HCT')
@section('content')

@php
    $regions = \App\Models\Region::where('is_active', 1)->orderBy('name')->get();
    $expTypes = \App\Models\Experience::query()->whereNotNull('type')->where('type', '!=', '')->distinct()->orderBy('type')->pluck('type');
    $expDifficulties = \App\Models\Experience::query()->whereNotNull('difficulty_level')->where('difficulty_level', '!=', '')->distinct()->orderBy('difficulty_level')->pluck('difficulty_level');
@endphp

<div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="mb-0"><i class="bi bi-compass"></i> Experiences</h5>
    <div class="d-flex gap-2">
        <button type="button" class="btn btn-danger btn-sm d-none" id="btnBulkDelete"><i class="bi bi-slash-circle"></i> Deactivate Selected</button>
        <a href="{{ url('/experiences/create') }}" class="btn btn-success btn-sm">
            <i class="bi bi-plus-lg"></i> Create New
        </a>
    </div>
</div>

<div class="card mb-3">
    <div class="card-body py-2">
        <div class="row g-2 align-items-end">
            <div class="col-md-2">
                <label class="form-label small mb-1">Region</label>
                <select class="form-select form-select-sm custom-select" id="filterRegion">
                    <option value="">All Regions</option>
                    @foreach($regions as $r)
                        <option value="{{ $r->id }}">{{ $r->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small mb-1">Type</label>
                <select class="form-select form-select-sm custom-select" id="filterType">
                    <option value="">All Types</option>
                    @foreach($expTypes as $t)
                        <option value="{{ $t }}">{{ $t }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small mb-1">Difficulty</label>
                <select class="form-select form-select-sm custom-select" id="filterDifficulty">
                    <option value="">All Levels</option>
                    @foreach($expDifficulties as $d)
                        <option value="{{ $d }}">{{ ucfirst($d) }}</option>
                    @endforeach
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
            <div class="col-md-3">
                <label class="form-label small mb-1">Search</label>
                <input type="text" class="form-control form-control-sm" id="filterSearch">
            </div>
            <div class="col-md-1">
                <button class="btn btn-outline-secondary btn-sm w-100" id="btnReset" title="Reset filters">
                    <i class="bi bi-arrow-counterclockwise"></i>
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
                        <th class="w-img-thumb">Image</th>
                        <th>Name</th>
                        <th>Type</th>
                        <th>Region</th>
                        <th>HLH Provider</th>
                        <th>Duration</th>
                        <th>Inclusions</th>
                        <th>Difficulty</th>
                        <th>Cost/Person</th>
                        <th>Status</th>
                        <th class="w-actions">Actions</th>
                    </tr>
                </thead>
                <tbody id="experiencesTable">
                    <tr><td colspan="12" class="text-center text-muted">Loading...</td></tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<nav class="mt-3" id="paginationNav">
    <ul class="pagination pagination-sm justify-content-center" id="paginationList"></ul>
</nav>

@endsection

@section('js')
<script>
var editExpUrl = "{{ url('/experiences/__ID__/edit') }}";
var currentPage = 1;

function formatDuration(item) {
    if (item.duration_type === 'less_than_day') {
        return (item.duration_hours || 0) + ' hrs';
    } else if (item.duration_type === 'multi_day') {
        var d = item.duration_days || 0;
        var n = item.duration_nights || 0;
        return d + 'D/' + n + 'N';
    } else {
        return '1 Day';
    }
}

function difficultyBadge(level) {
    var cls = 'secondary';
    if (level === 'easy') cls = 'success';
    else if (level === 'moderate') cls = 'info';
    else if (level === 'challenging') cls = 'warning text-dark';
    else if (level === 'extreme') cls = 'danger';
    return '<span class="badge bg-' + cls + '">' + (level || '-') + '</span>';
}

function loadExperiences(page) {
    if (page) currentPage = page;
    var params = {
        get_experiences_list: 1,
        page: currentPage
    };
    var regionId = $('#filterRegion').val();
    var type = $('#filterType').val();
    var difficulty = $('#filterDifficulty').val();
    var status = $('#filterStatus').val();
    var search = $('#filterSearch').val();

    if (regionId) params.region_id = regionId;
    if (type) params.type = type;
    if (difficulty) params.difficulty = difficulty;
    if (status !== '') params.status = status;
    if (search) params.search = search;

    ajaxPost(params, function(resp) {
        var html = '';
        var items = resp.experiences || resp.data || [];
        if (!items.length) {
            html = '<tr><td colspan="12" class="text-center text-muted">No experiences found</td></tr>';
        }
        items.forEach(function(e) {
            var imgSrc = e.card_image ? e.card_image : '/images/placeholder.png';
            html += '<tr>';
            html += '<td><i class="bi bi-square row-check" role="button" data-id="' + e.id + '"></i></td>';
            html += '<td><img src="' + imgSrc + '" class="rounded img-thumb-list" alt=""></td>';
            html += '<td><strong>' + (e.name || '') + '</strong><br><small class="text-muted">' + (e.slug || '') + '</small></td>';
            html += '<td><span class="badge bg-light text-dark">' + (e.type || '-') + '</span></td>';
            html += '<td>' + (e.region ? e.region.name : '-') + '</td>';
            html += '<td>' + (e.hlh ? e.hlh.name : '-') + '</td>';
            html += '<td><small>' + formatDuration(e) + '</small></td>';

            // Inclusions column - from day-wise details
            var incHtml = '';
            var incIconMap = { breakfast: 'bi-cup-hot', lunch: 'bi-egg-fried', dinner: 'bi-moon-stars', snacks: 'bi-basket', accommodation: 'bi-house', guide: 'bi-person-badge', transport: 'bi-truck' };
            var allInc = [];
            if (e.days && e.days.length) {
                e.days.forEach(function(d) {
                    if (d.inclusions && d.inclusions.length) {
                        d.inclusions.forEach(function(inc) {
                            if (allInc.indexOf(inc) === -1) allInc.push(inc);
                        });
                    }
                });
            }
            if (allInc.length) {
                allInc.forEach(function(inc) {
                    var icon = incIconMap[inc] || 'bi-check';
                    incHtml += '<span class="exp-inc-chip" title="' + inc.charAt(0).toUpperCase() + inc.slice(1) + '"><i class="bi ' + icon + '"></i></span>';
                });
            }
            html += '<td><div class="exp-inc-row">' + (incHtml || '<small class="text-muted">-</small>') + '</div></td>';
            html += '<td>' + difficultyBadge(e.difficulty_level) + '</td>';
            html += '<td>' + (e.base_cost_per_person ? '<i class="bi bi-currency-rupee"></i>' + Number(e.base_cost_per_person).toLocaleString() : '-') + '</td>';
            html += '<td>';
            html += '<div class="form-check form-switch">';
            html += '<input class="form-check-input toggle-status" type="checkbox" data-id="' + e.id + '"' + (e.is_active ? ' checked' : '') + '>';
            html += '</div>';
            html += '</td>';
            html += '<td>';
            html += '<a href="' + editExpUrl.replace('__ID__', e.id) + '" class="btn btn-sm btn-outline-primary me-1" title="Edit"><i class="bi bi-pencil"></i></a>';
            html += '<button class="btn btn-sm btn-outline-danger btn-disable" data-id="' + e.id + '" data-active="' + (e.is_active ? '1' : '0') + '" title="' + (e.is_active ? 'Disable' : 'Enable') + '">';
            html += '<i class="bi bi-' + (e.is_active ? 'eye-slash' : 'eye') + '"></i>';
            html += '</button>';
            html += '</td>';
            html += '</tr>';
        });
        $('#experiencesTable').html(html);
        refreshBulkBtn();

        renderPagination(resp.pagination || {});
    });
}

function renderPagination(pagination) {
    var html = '';
    var current = pagination.current_page || 1;
    var last = pagination.last_page || 1;
    var total = pagination.total || 0;

    if (last <= 1) {
        $('#paginationNav').hide();
        return;
    }
    $('#paginationNav').show();

    html += '<li class="page-item' + (current <= 1 ? ' disabled' : '') + '">';
    html += '<a class="page-link" href="#" data-page="' + (current - 1) + '">&laquo;</a></li>';

    var startPage = Math.max(1, current - 2);
    var endPage = Math.min(last, current + 2);

    if (startPage > 1) {
        html += '<li class="page-item"><a class="page-link" href="#" data-page="1">1</a></li>';
        if (startPage > 2) html += '<li class="page-item disabled"><span class="page-link">...</span></li>';
    }

    for (var i = startPage; i <= endPage; i++) {
        html += '<li class="page-item' + (i === current ? ' active' : '') + '">';
        html += '<a class="page-link" href="#" data-page="' + i + '">' + i + '</a></li>';
    }

    if (endPage < last) {
        if (endPage < last - 1) html += '<li class="page-item disabled"><span class="page-link">...</span></li>';
        html += '<li class="page-item"><a class="page-link" href="#" data-page="' + last + '">' + last + '</a></li>';
    }

    html += '<li class="page-item' + (current >= last ? ' disabled' : '') + '">';
    html += '<a class="page-link" href="#" data-page="' + (current + 1) + '">&raquo;</a></li>';

    html += '<li class="page-item disabled"><span class="page-link small">Total: ' + total + '</span></li>';

    $('#paginationList').html(html);
}

$(function() { loadExperiences(1); });

$('#filterRegion, #filterType, #filterDifficulty, #filterStatus').on('change', function() {
    loadExperiences(1);
});

var searchTimer;
$('#filterSearch').on('keyup', function() {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(function() { loadExperiences(1); }, 400);
});

$('#btnReset').on('click', function() {
    $('#filterRegion, #filterType, #filterDifficulty, #filterStatus').val('').each(function() {
        if (window.buildCustomDropdown) buildCustomDropdown(this);
    });
    $('#filterSearch').val('');
    loadExperiences(1);
});

$(document).on('click', '#paginationList a.page-link', function(e) {
    e.preventDefault();
    var page = $(this).data('page');
    if (page) loadExperiences(page);
});

$(document).on('change', '.toggle-status', function() {
    var id = $(this).data('id');
    ajaxPost({ disable_experience: 1, id: id }, function(resp) {
        loadExperiences(currentPage);
    });
});

$(document).on('click', '.btn-disable', function() {
    var id = $(this).data('id');
    var isActive = $(this).data('active');
    var action = isActive == 1 ? 'disable' : 'enable';
    confirmAction('Are you sure you want to ' + action + ' this experience?', function() {
        ajaxPost({ disable_experience: 1, id: id }, function(resp) {
            loadExperiences(currentPage);
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
    confirmAction('Deactivate ' + ids.length + ' experience(s)?', function() {
        ajaxPost({ bulk_delete_experiences: 1, ids: ids }, function(resp) {
            showAlert(resp.message || 'Deactivated', 'success');
            loadExperiences(currentPage);
        });
    });
});
</script>
@endsection
