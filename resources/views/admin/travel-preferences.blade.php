@extends('admin.layout')
@section('title', 'Travel Preferences - HCT')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="mb-0"><i class="bi bi-sliders2"></i> Travel Preferences</h5>
</div>

@php
    $sections = [
        ['type' => 'accommodation_comfort', 'icon' => 'bi-house-door',   'label' => 'Accommodation Comfort'],
        ['type' => 'vehicle_comfort',       'icon' => 'bi-car-front',    'label' => 'Vehicle Comfort'],
        ['type' => 'guide_preference',      'icon' => 'bi-person-badge', 'label' => 'Guide Preference'],
        ['type' => 'travel_pace',           'icon' => 'bi-speedometer',  'label' => 'Travel Pace'],
        ['type' => 'budget_sensitivity',    'icon' => 'bi-cash-coin',    'label' => 'Budget Sensitivity'],
    ];
@endphp

<div class="accordion tp-accordion" id="prefAccordion">
    @foreach($sections as $i => $s)
    <div class="accordion-item pref-list" data-type="{{ $s['type'] }}">
        <h2 class="accordion-header">
            <button class="accordion-button {{ $i === 0 ? '' : 'collapsed' }}" type="button"
                    data-bs-toggle="collapse" data-bs-target="#sec_{{ $s['type'] }}">
                <i class="bi {{ $s['icon'] }} me-2"></i>
                {{ $s['label'] }}
            </button>
        </h2>
        <div id="sec_{{ $s['type'] }}" class="accordion-collapse collapse {{ $i === 0 ? 'show' : '' }}" data-bs-parent="#prefAccordion">
            <div class="accordion-body">
                <div class="table-responsive">
                    <table class="table table-hover table-sm mb-0">
                        <thead class="table-light">
                            <tr>
                                <th style="width: 40px;">#</th>
                                <th>Option name</th>
                                <th style="width: 90px;">Sort</th>
                                <th style="width: 90px;">Status</th>
                                <th style="width: 130px;">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="pref-items">
                            <tr><td colspan="5" class="text-center text-muted small">Loading...</td></tr>
                        </tbody>
                    </table>
                </div>
                <div class="pref-add-row">
                    <form class="pref-add-form d-flex flex-wrap gap-2 align-items-end mb-0" data-type="{{ $s['type'] }}">
                        <div class="flex-grow-1" style="min-width: 220px;">
                            <input type="text" class="form-control form-control-sm pref-new-name" placeholder="Add new option..." required>
                        </div>
                        <div style="width: 90px;">
                            <input type="number" class="form-control form-control-sm pref-new-sort" value="0" min="0" placeholder="Sort">
                        </div>
                        <button type="submit" class="btn btn-sm btn-success">
                            <i class="bi bi-plus-lg me-1"></i> Add
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    @endforeach
</div>
@endsection

@section('js')
<script>
(function() {
    function escapeHtml(s) {
        return String(s == null ? '' : s).replace(/[&<>"']/g, function(c) {
            return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c];
        });
    }

    function loadList(type) {
        var $tbody = jQuery('.pref-list[data-type="' + type + '"] .pref-items');
        $tbody.html('<tr><td colspan="5" class="text-center text-muted small">Loading...</td></tr>');
        ajaxPost({ get_system_lists: 1, list_type: type }, function(resp) {
            var items = resp.items || [];
            if (!items.length) {
                $tbody.html('<tr><td colspan="5" class="text-center text-muted small">No options yet. Add the first one below.</td></tr>');
                return;
            }
            var html = '';
            items.forEach(function(item, idx) {
                var nameClass = item.is_active ? '' : ' text-muted text-decoration-line-through';
                html += '<tr data-id="' + item.id + '" data-name="' + escapeHtml(item.name) + '" data-sort="' + (item.sort_order || 0) + '">';
                html += '<td class="text-muted">' + (idx + 1) + '</td>';
                html += '<td><strong class="' + nameClass + '">' + escapeHtml(item.name) + '</strong></td>';
                html += '<td><small>' + (item.sort_order || 0) + '</small></td>';
                html += '<td>';
                if (item.is_active) {
                    html += '<span class="badge bg-success">Active</span>';
                } else {
                    html += '<span class="badge bg-secondary">Inactive</span>';
                }
                html += '</td>';
                html += '<td>';
                html += '<a href="/travel-preferences/' + item.id + '/edit" class="btn btn-sm btn-outline-primary me-1" title="Edit"><i class="bi bi-pencil"></i></a>';
                if (item.is_active) {
                    html += '<button class="btn btn-sm btn-outline-warning btn-toggle-pref" title="Deactivate"><i class="bi bi-eye-slash"></i></button>';
                } else {
                    html += '<button class="btn btn-sm btn-outline-success btn-toggle-pref" title="Reactivate"><i class="bi bi-eye"></i></button>';
                }
                html += '</td>';
                html += '</tr>';
            });
            $tbody.html(html);
        });
    }

    // Initial load — all sections
    jQuery('.pref-list').each(function() { loadList(jQuery(this).data('type')); });

    // Add new
    jQuery(document).on('submit', '.pref-add-form', function(e) {
        e.preventDefault();
        var $form = jQuery(this);
        var type = $form.data('type');
        var $name = $form.find('.pref-new-name');
        var $sort = $form.find('.pref-new-sort');
        var nameVal = ($name.val() || '').trim();
        if (!nameVal) return;
        ajaxPost({
            save_system_list_item: 1,
            list_type: type,
            name: nameVal,
            sort_order: parseInt($sort.val(), 10) || 0
        }, function() {
            $name.val('');
            $sort.val(0);
            loadList(type);
            showAlert('Option added.', 'success');
        });
    });

    // Toggle active/inactive
    jQuery(document).on('click', '.btn-toggle-pref', function() {
        var $row = jQuery(this).closest('tr');
        var id = $row.data('id');
        var type = $row.closest('.pref-list').data('type');
        var name = $row.data('name');
        var sort = $row.data('sort');
        var $btn = jQuery(this);
        var goingActive = $btn.hasClass('btn-outline-success');

        if (!goingActive && !confirm('Deactivate this option? It will be hidden from new dropdowns but kept on existing trips.')) return;

        if (goingActive) {
            ajaxPost({
                save_system_list_item: 1,
                id: id,
                list_type: type,
                name: name,
                sort_order: parseInt(sort, 10) || 0,
                is_active: 1
            }, function() {
                loadList(type);
                showAlert('Option reactivated.', 'success');
            });
        } else {
            ajaxPost({ deactivate_system_list_item: 1, id: id }, function() {
                loadList(type);
                showAlert('Option deactivated.', 'success');
            });
        }
    });
})();
</script>
@endsection
