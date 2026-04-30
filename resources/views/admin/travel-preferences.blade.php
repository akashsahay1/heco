@extends('admin.layout')
@section('title', 'Travel Preferences')

@section('css')
<style>
.tp-page .accordion-button {
    background: #fff;
    color: #15803d;
    font-weight: 600;
    box-shadow: none;
}
.tp-page .accordion-button:not(.collapsed) {
    background: #f0fdf4;
    color: #14532d;
}
.tp-page .accordion-button:focus {
    border-color: #22c55e;
    box-shadow: 0 0 0 0.2rem rgba(34, 197, 94, 0.15);
}
.tp-page .accordion-item {
    border: 1px solid #e5e7eb;
    margin-bottom: 8px;
    border-radius: 8px !important;
    overflow: hidden;
}
.tp-page .accordion-item .accordion-button .tp-section-desc {
    color: #6b7280;
    font-weight: 400;
    font-size: 0.82rem;
    margin-left: 8px;
}
.tp-page .pref-add-form {
    background: #f9fafb;
    border: 1px solid #e5e7eb;
    border-radius: 8px;
    padding: 12px;
}
.tp-page .pref-table th {
    font-size: 0.78rem;
    color: #6b7280;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.04em;
}
.tp-page .pref-table td {
    vertical-align: middle;
}
.tp-page .badge-inactive {
    background: #fef3c7;
    color: #92400e;
}
.tp-page .empty-state {
    color: #9ca3af;
    text-align: center;
    padding: 20px 0;
    font-size: 0.9rem;
}
</style>
@endsection

@section('content')
<div class="container-fluid p-4 tp-page">
    <div class="d-flex align-items-center mb-2">
        <i class="bi bi-sliders2 me-2 fs-4 text-success"></i>
        <h4 class="mb-0">Travel Preferences</h4>
    </div>
    <p class="text-muted mb-4">
        Manage the dropdown options shown to travellers in the Travel Preferences sidebar
        on the homepage and in Trip Manager. Changes here take effect immediately.
        Deactivated items are hidden from new selections but kept on existing trips for historical accuracy.
    </p>

    @php
        $sections = [
            ['type' => 'accommodation_comfort', 'icon' => 'bi-house-door', 'label' => 'Accommodation Comfort', 'desc' => 'Stay categories — Cat A, Cat B, etc.'],
            ['type' => 'vehicle_comfort',       'icon' => 'bi-car-front',  'label' => 'Vehicle Comfort',       'desc' => 'Vehicle classes for ground transport'],
            ['type' => 'guide_preference',      'icon' => 'bi-person-badge','label' => 'Guide Preference',     'desc' => 'Types of guides offered (no guide / local / specialist…)'],
            ['type' => 'travel_pace',           'icon' => 'bi-speedometer','label' => 'Travel Pace',           'desc' => 'Slow / balanced / packed signals for the AI'],
            ['type' => 'budget_sensitivity',    'icon' => 'bi-cash-coin',  'label' => 'Budget Sensitivity',    'desc' => 'Lean / standard / premium / no-limit'],
        ];
    @endphp

    <div class="accordion" id="prefAccordion">
        @foreach($sections as $i => $s)
        <div class="accordion-item">
            <h2 class="accordion-header">
                <button class="accordion-button {{ $i === 0 ? '' : 'collapsed' }}" type="button"
                        data-bs-toggle="collapse" data-bs-target="#sec_{{ $s['type'] }}">
                    <i class="bi {{ $s['icon'] }} me-2"></i>
                    {{ $s['label'] }}
                    <span class="tp-section-desc">— {{ $s['desc'] }}</span>
                </button>
            </h2>
            <div id="sec_{{ $s['type'] }}" class="accordion-collapse collapse {{ $i === 0 ? 'show' : '' }}" data-bs-parent="#prefAccordion">
                <div class="accordion-body">
                    <div class="pref-list" data-type="{{ $s['type'] }}">
                        <div class="pref-items"><p class="empty-state">Loading...</p></div>

                        <form class="pref-add-form mt-3 d-flex flex-wrap gap-2 align-items-end" data-type="{{ $s['type'] }}">
                            <div class="flex-grow-1" style="min-width: 220px;">
                                <label class="form-label small text-muted mb-1">Option name</label>
                                <input type="text" class="form-control form-control-sm pref-new-name" placeholder="e.g. Cat A - Premium/Luxury" required>
                            </div>
                            <div style="width: 110px;">
                                <label class="form-label small text-muted mb-1">Sort order</label>
                                <input type="number" class="form-control form-control-sm pref-new-sort" value="0" min="0">
                            </div>
                            <button type="submit" class="btn btn-sm btn-success">
                                <i class="bi bi-plus-lg me-1"></i> Add Option
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
        @endforeach
    </div>
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
        var $section = jQuery('.pref-list[data-type="' + type + '"] .pref-items');
        $section.html('<p class="empty-state">Loading...</p>');
        ajaxPost({ get_system_lists: 1, list_type: type }, function(resp) {
            var items = resp.items || [];
            if (!items.length) {
                $section.html('<p class="empty-state">No options yet. Add the first one below.</p>');
                return;
            }
            var html = '<table class="table table-sm pref-table align-middle mb-0">';
            html += '<thead><tr>';
            html += '<th style="width:40px;">#</th>';
            html += '<th>Option name</th>';
            html += '<th style="width:100px;">Sort</th>';
            html += '<th style="width:90px;">Status</th>';
            html += '<th style="width:170px;text-align:right;">Actions</th>';
            html += '</tr></thead><tbody>';
            items.forEach(function(item, idx) {
                var nameClass = item.is_active ? '' : ' text-muted text-decoration-line-through';
                html += '<tr data-id="' + item.id + '" data-name="' + escapeHtml(item.name) + '" data-sort="' + (item.sort_order || 0) + '">';
                html += '<td class="text-muted">' + (idx + 1) + '</td>';
                html += '<td><span class="pref-name fw-medium' + nameClass + '">' + escapeHtml(item.name) + '</span></td>';
                html += '<td><span class="pref-sort">' + (item.sort_order || 0) + '</span></td>';
                html += '<td>';
                if (item.is_active) {
                    html += '<span class="badge bg-success bg-opacity-25 text-success">Active</span>';
                } else {
                    html += '<span class="badge badge-inactive">Inactive</span>';
                }
                html += '</td>';
                html += '<td class="text-end">';
                html += '<button class="btn btn-sm btn-outline-secondary me-1 btn-edit" title="Rename / reorder"><i class="bi bi-pencil"></i></button>';
                if (item.is_active) {
                    html += '<button class="btn btn-sm btn-outline-danger btn-deactivate" title="Deactivate"><i class="bi bi-x-circle"></i></button>';
                }
                html += '</td></tr>';
            });
            html += '</tbody></table>';
            $section.html(html);
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

    // Edit (rename + reorder)
    jQuery(document).on('click', '.btn-edit', function() {
        var $row = jQuery(this).closest('tr');
        var id = $row.data('id');
        var type = $row.closest('.pref-list').data('type');
        var current = $row.data('name');
        var currentSort = $row.data('sort');

        var newName = prompt('Rename option:', current);
        if (newName === null) return;
        newName = newName.trim();
        if (!newName) {
            showAlert('Name cannot be empty.', 'warning');
            return;
        }
        var newSort = prompt('Sort order:', currentSort);
        if (newSort === null) newSort = currentSort;

        ajaxPost({
            save_system_list_item: 1,
            id: id,
            list_type: type,
            name: newName,
            sort_order: parseInt(newSort, 10) || 0
        }, function() {
            loadList(type);
            showAlert('Option updated.', 'success');
        });
    });

    // Deactivate
    jQuery(document).on('click', '.btn-deactivate', function() {
        var $row = jQuery(this).closest('tr');
        var id = $row.data('id');
        var type = $row.closest('.pref-list').data('type');
        if (!confirm('Deactivate this option? It will be hidden from new dropdowns but kept on existing trips.')) return;
        ajaxPost({ deactivate_system_list_item: 1, id: id }, function() {
            loadList(type);
            showAlert('Option deactivated.', 'success');
        });
    });
})();
</script>
@endsection
