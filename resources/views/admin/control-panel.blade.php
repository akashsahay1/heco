@extends('admin.layout')
@section('title', 'Control Panel - HCT')
@section('content')

<div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="mb-0"><i class="bi bi-sliders"></i> Control Panel</h5>
</div>

{{-- ===== Operational summary ===== --}}
<div class="row g-3 mb-4">
    <div class="col-md-6">
        <div class="card h-100">
            <div class="card-header bg-warning text-dark"><h6 class="mb-0"><i class="bi bi-bell"></i> Lead Reminders</h6></div>
            <div class="card-body cp-scroll-box" id="reminders">
                <p class="text-muted text-center small">Loading...</p>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card h-100">
            <div class="card-header"><h6 class="mb-0"><i class="bi bi-headset"></i> Support Requests</h6></div>
            <div class="card-body cp-scroll-box" id="cpSupport">
                <p class="text-muted text-center small">Loading...</p>
            </div>
        </div>
    </div>
</div>

{{-- ===== Content shortcuts ===== --}}
<div class="card mb-4">
    <div class="card-header"><h6 class="mb-0"><i class="bi bi-link-45deg"></i> Content Management</h6></div>
    <div class="card-body">
        <div class="d-flex flex-wrap gap-2">
            <a href="{{ url('/experiences') }}" class="btn btn-success btn-sm"><i class="bi bi-star me-1"></i> Manage Experiences</a>
            <a href="{{ url('/experiences/create') }}" class="btn btn-outline-dark btn-sm"><i class="bi bi-plus me-1"></i> Create Experience</a>
            <a href="{{ url('/regenerative-projects') }}" class="btn btn-success btn-sm"><i class="bi bi-tree me-1"></i> Manage RP</a>
            <a href="{{ url('/regenerative-projects/create') }}" class="btn btn-outline-dark btn-sm"><i class="bi bi-plus me-1"></i> Create RP</a>
            <a href="{{ url('/regions') }}" class="btn btn-success btn-sm"><i class="bi bi-globe-americas me-1"></i> Manage Regions</a>
            <a href="{{ url('/travel-preferences') }}" class="btn btn-outline-dark btn-sm"><i class="bi bi-sliders2 me-1"></i> Travel Preferences</a>
        </div>
    </div>
</div>

{{-- ===== Admin console ===== --}}
<ul class="nav nav-tabs" id="cpTabs" role="tablist">
    <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#cpSettings" type="button">Settings</button></li>
    <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#cpLists" type="button">System Lists</button></li>
    <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#cpPrompts" type="button">AI Prompts</button></li>
    <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#cpLogs" type="button">Activity Logs</button></li>
    <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#cpPdf" type="button">PDF Templates</button></li>
    <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#cpGlossary" type="button">Glossary</button></li>
</ul>

<div class="tab-content border border-top-0 p-3 cp-tabwrap">

    {{-- ---- Settings ---- --}}
    <div class="tab-pane fade show active" id="cpSettings">
        <div class="mb-3 cp-select-narrow">
            <label class="form-label small text-muted">Settings group</label>
            <select class="form-select form-select-sm custom-select" id="cpSettingsGroup">
                @foreach($settingGroups as $g)
                    <option value="{{ $g }}">{{ ucwords(str_replace('_', ' ', $g)) }}</option>
                @endforeach
            </select>
        </div>
        <form id="cpSettingsForm">
            <div class="table-responsive">
                <table class="table table-sm align-middle">
                    <thead class="table-light"><tr><th class="w-key-col">Key</th><th>Value</th></tr></thead>
                    <tbody id="cpSettingsBody"><tr><td colspan="2" class="text-center text-muted small">Loading...</td></tr></tbody>
                </table>
            </div>
            <button type="submit" class="btn btn-sm btn-success"><i class="bi bi-check-lg me-1"></i> Save Settings</button>
        </form>
    </div>

    {{-- ---- System Lists ---- --}}
    <div class="tab-pane fade" id="cpLists">
        <div class="mb-3 cp-select-narrow">
            <label class="form-label small text-muted">List type</label>
            <select class="form-select form-select-sm custom-select" id="cpListType">
                @foreach($systemListTypes as $val => $label)
                    <option value="{{ $val }}">{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div class="table-responsive">
            <table class="table table-sm table-hover align-middle">
                <thead class="table-light">
                    <tr>
                        <th class="w-check"><i class="bi bi-check2-square cp-list-selall" role="button" title="Select all"></i></th>
                        <th class="w-id">#</th>
                        <th>Name</th>
                        <th>Description</th>
                        <th class="w-sort">Sort</th>
                        <th class="w-status">Status</th>
                        <th class="w-actions-md">Actions</th>
                    </tr>
                </thead>
                <tbody id="cpListBody"><tr><td colspan="7" class="text-center text-muted small">Loading...</td></tr></tbody>
            </table>
        </div>
        <div class="d-flex gap-2">
            <button type="button" class="btn btn-sm btn-danger d-none" id="cpListBulkDelete"><i class="bi bi-trash me-1"></i> Delete Selected</button>
            <button type="button" class="btn btn-sm btn-success" id="cpListAdd"><i class="bi bi-plus-lg me-1"></i> Add New</button>
        </div>
    </div>

    {{-- ---- AI Prompts ---- --}}
    <div class="tab-pane fade" id="cpPrompts">
        <div class="table-responsive">
            <table class="table table-sm table-hover align-middle">
                <thead class="table-light">
                    <tr>
                        <th class="w-check"><i class="bi bi-check2-square cp-prompt-selall" role="button" title="Select all"></i></th>
                        <th>Key</th>
                        <th>Name</th>
                        <th class="w-paper">Model</th>
                        <th class="w-active">Active</th>
                        <th class="w-actions">Actions</th>
                    </tr>
                </thead>
                <tbody id="cpPromptBody"><tr><td colspan="6" class="text-center text-muted small">Loading...</td></tr></tbody>
            </table>
        </div>
        <div class="d-flex gap-2">
            <button type="button" class="btn btn-sm btn-danger d-none" id="cpPromptBulkDelete"><i class="bi bi-trash me-1"></i> Delete Selected</button>
            <button type="button" class="btn btn-sm btn-success" id="cpPromptAdd"><i class="bi bi-plus-lg me-1"></i> Add New</button>
        </div>
    </div>

    {{-- ---- Activity Logs ---- --}}
    <div class="tab-pane fade" id="cpLogs">
        <div class="table-responsive">
            <table class="table table-sm table-hover align-middle">
                <thead class="table-light"><tr><th class="w-when">When</th><th class="w-user">User</th><th>Action</th><th>Target</th><th>Details</th></tr></thead>
                <tbody id="cpLogBody"><tr><td colspan="5" class="text-center text-muted small">Loading...</td></tr></tbody>
            </table>
        </div>
        <div id="cpLogPagination" class="mt-2"></div>
    </div>

    {{-- ---- Glossary ---- --}}
    <div class="tab-pane fade" id="cpGlossary">
        <p class="text-muted small mb-3">
            <i class="bi bi-info-circle me-1"></i>
            Reference of every option used across the platform — Accommodation categories, vehicle types,
            travel preferences, meal plans, occupancy units, etc. Edit any item via the
            <strong>System Lists</strong> tab or the <a href="{{ url('/travel-preferences') }}">Travel Preferences</a> page.
        </p>
        <div id="cpGlossaryBody"><p class="text-muted text-center small">Loading…</p></div>
    </div>

    {{-- ---- PDF Templates ---- --}}
    <div class="tab-pane fade" id="cpPdf">
        <div class="table-responsive">
            <table class="table table-sm table-hover align-middle">
                <thead class="table-light"><tr><th>Key</th><th>Name</th><th class="w-paper">Paper</th><th class="w-orient">Orientation</th><th class="w-active">Actions</th></tr></thead>
                <tbody id="cpPdfBody"><tr><td colspan="5" class="text-center text-muted small">Loading...</td></tr></tbody>
            </table>
        </div>
        <button type="button" class="btn btn-sm btn-success" id="cpPdfAdd"><i class="bi bi-plus-lg me-1"></i> Add Template</button>
    </div>
</div>

{{-- ===== Modals ===== --}}
<div class="modal fade" id="cpListModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
    <div class="modal-header"><h6 class="modal-title">List Item</h6><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body"><form id="cpListForm">
        <input type="hidden" name="id">
        <input type="hidden" name="list_type">
        <div class="mb-2"><label class="form-label small">Name</label><input type="text" class="form-control form-control-sm" name="name" required></div>
        <div class="mb-2"><label class="form-label small">Description <span class="text-muted">(what this option means — shown as help-text)</span></label><textarea class="form-control form-control-sm" name="description" rows="3" maxlength="500" placeholder="1-2 lines explaining what this option includes — amenities, price band, who it suits."></textarea></div>
        <div class="mb-2"><label class="form-label small">Sort order</label><input type="number" class="form-control form-control-sm" name="sort_order" value="0" min="0"></div>
        <div class="form-check form-switch mb-3"><input class="form-check-input" type="checkbox" name="is_active" value="1" checked id="cpListActive"><label class="form-check-label small" for="cpListActive">Active</label></div>
        <button type="submit" class="btn btn-sm btn-success w-100">Save</button>
    </form></div>
</div></div></div>

<div class="modal fade" id="cpPromptModal" tabindex="-1"><div class="modal-dialog modal-lg"><div class="modal-content">
    <div class="modal-header"><h6 class="modal-title">AI Prompt</h6><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body"><form id="cpPromptForm">
        <input type="hidden" name="id">
        <div class="row g-2 mb-2">
            <div class="col-md-6"><label class="form-label small">Name</label><input type="text" class="form-control form-control-sm" name="name" required></div>
            <div class="col-md-6"><label class="form-label small">Key</label><input type="text" class="form-control form-control-sm" name="key" required></div>
        </div>
        <div class="mb-2"><label class="form-label small">System prompt</label><textarea class="form-control form-control-sm cp-mono" name="system_prompt" rows="5"></textarea></div>
        <div class="mb-2"><label class="form-label small">User prompt template <span class="text-muted">(supports @{{placeholder}} vars)</span></label><textarea class="form-control form-control-sm cp-mono" name="user_prompt_template" rows="6"></textarea></div>
        <div class="row g-2 mb-2">
            <div class="col-md-4"><label class="form-label small">Model</label><input type="text" class="form-control form-control-sm" name="model"></div>
            <div class="col-md-3"><label class="form-label small">Temperature</label><input type="number" step="0.01" min="0" max="2" class="form-control form-control-sm" name="temperature"></div>
            <div class="col-md-3"><label class="form-label small">Max tokens</label><input type="number" min="1" class="form-control form-control-sm" name="max_tokens"></div>
            <div class="col-md-2"><label class="form-label small">Format</label><input type="text" class="form-control form-control-sm" name="response_format"></div>
        </div>
        <div class="mb-2"><label class="form-label small">Notes</label><textarea class="form-control form-control-sm" name="notes" rows="2"></textarea></div>
        <div class="form-check form-switch mb-3"><input class="form-check-input" type="checkbox" name="is_active" value="1" checked id="cpPromptActive"><label class="form-check-label small" for="cpPromptActive">Active</label></div>
        <button type="submit" class="btn btn-sm btn-success w-100">Save Prompt</button>
    </form></div>
</div></div></div>

<div class="modal fade" id="cpPdfModal" tabindex="-1"><div class="modal-dialog modal-lg"><div class="modal-content">
    <div class="modal-header"><h6 class="modal-title">PDF Template</h6><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body"><form id="cpPdfForm">
        <input type="hidden" name="id">
        <div class="row g-2 mb-2">
            <div class="col-md-6"><label class="form-label small">Name</label><input type="text" class="form-control form-control-sm" name="name" required></div>
            <div class="col-md-6"><label class="form-label small">Key</label><input type="text" class="form-control form-control-sm" name="key" required></div>
        </div>
        <div class="mb-2"><label class="form-label small">Header HTML</label><textarea class="form-control form-control-sm cp-mono" name="header_html" rows="4"></textarea></div>
        <div class="mb-2"><label class="form-label small">Footer HTML</label><textarea class="form-control form-control-sm cp-mono" name="footer_html" rows="4"></textarea></div>
        <div class="mb-2"><label class="form-label small">CSS</label><textarea class="form-control form-control-sm cp-mono" name="css" rows="5"></textarea></div>
        <div class="row g-2 mb-3">
            <div class="col-md-6"><label class="form-label small">Paper size</label><input type="text" class="form-control form-control-sm" name="paper_size" value="A4"></div>
            <div class="col-md-6"><label class="form-label small">Orientation</label>
                <select class="form-select form-select-sm custom-select" name="orientation"><option value="portrait">Portrait</option><option value="landscape">Landscape</option></select>
            </div>
        </div>
        <button type="submit" class="btn btn-sm btn-success w-100">Save Template</button>
    </form></div>
</div></div></div>

@endsection

@section('js')
<script>
jQuery(function() {
    function escapeHtml(s) {
        return String(s == null ? '' : s).replace(/[&<>"']/g, function(c) {
            return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c];
        });
    }

    // ===== Operational summary =====
    ajaxPost({ get_lead_reminders: 1 }, function(resp) {
        var html = '';
        var rems = resp.reminders || [];
        if (!rems.length) { html = '<p class="text-muted text-center small mb-0">No reminders due</p>'; }
        rems.forEach(function(r) {
            html += '<div class="border-bottom pb-2 mb-2">';
            html += '<strong class="small">' + escapeHtml(r.user ? (r.user.full_name || r.user.email) : '') + '</strong>';
            html += '<br><small class="text-muted">Trip: ' + escapeHtml(r.trip ? r.trip.trip_id : '') + '</small>';
            html += '<br><a href="/leads" class="btn btn-sm btn-outline-warning mt-1">Follow Up</a>';
            html += '</div>';
        });
        jQuery('#reminders').html(html);
    });

    ajaxPost({ get_support_requests: 1, unresolved_only: 1 }, function(resp) {
        var html = '';
        var items = resp.data || [];
        if (!items.length) { html = '<p class="text-muted text-center small mb-0">No unresolved requests</p>'; }
        items.forEach(function(r) {
            html += '<div class="border-bottom pb-2 mb-2">';
            html += '<strong class="small">' + escapeHtml(r.user ? (r.user.full_name || r.user.email) : '') + '</strong>';
            html += ' <span class="badge bg-' + (r.traveller_status === 'client' ? 'success' : 'warning text-dark') + '">' + escapeHtml(r.traveller_status) + '</span>';
            html += '<p class="small mb-1">' + escapeHtml((r.message || '').substring(0, 140)) + '</p>';
            html += '<button class="btn btn-sm btn-outline-success cp-resolve-sr" data-id="' + r.id + '">Resolve</button>';
            html += '</div>';
        });
        jQuery('#cpSupport').html(html);
    });

    jQuery(document).on('click', '.cp-resolve-sr', function() {
        ajaxPost({ resolve_support_request: 1, id: jQuery(this).data('id') }, function() { location.reload(); });
    });

    // ===== Settings =====
    function loadSettings() {
        var group = jQuery('#cpSettingsGroup').val();
        jQuery('#cpSettingsBody').html('<tr><td colspan="2" class="text-center text-muted small">Loading...</td></tr>');
        ajaxPost({ get_settings: 1, group: group }, function(resp) {
            var rows = resp.settings || [];
            if (!rows.length) { jQuery('#cpSettingsBody').html('<tr><td colspan="2" class="text-center text-muted small">No settings in this group.</td></tr>'); return; }
            var html = '';
            rows.forEach(function(s) {
                html += '<tr><td class="small text-muted">' + escapeHtml(s.key) + '</td>';
                html += '<td><input type="text" class="form-control form-control-sm cp-setting-input" data-key="' + escapeHtml(s.key) + '" value="' + escapeHtml(s.value) + '"></td></tr>';
            });
            jQuery('#cpSettingsBody').html(html);
        });
    }
    jQuery('#cpSettingsGroup').on('change', loadSettings);
    jQuery('#cpSettingsForm').on('submit', function(e) {
        e.preventDefault();
        var group = jQuery('#cpSettingsGroup').val();
        var settings = {};
        jQuery('.cp-setting-input').each(function() { settings[jQuery(this).data('key')] = jQuery(this).val(); });
        ajaxPost({ save_settings: 1, group: group, settings: settings }, function() { showAlert('Settings saved.', 'success'); });
    });
    loadSettings();

    // ===== System Lists =====
    function refreshListBulkBtn() {
        jQuery('#cpListBulkDelete').toggleClass('d-none', jQuery('.cp-list-check.cp-checked').length === 0);
    }
    function loadList() {
        var type = jQuery('#cpListType').val();
        jQuery('#cpListBody').html('<tr><td colspan="7" class="text-center text-muted small">Loading...</td></tr>');
        ajaxPost({ get_system_lists: 1, list_type: type }, function(resp) {
            var items = resp.items || [];
            if (!items.length) { jQuery('#cpListBody').html('<tr><td colspan="7" class="text-center text-muted small">No items. Add the first one.</td></tr>'); refreshListBulkBtn(); return; }
            var html = '';
            items.forEach(function(item, idx) {
                var desc = item.description || '';
                html += '<tr data-id="' + item.id + '" data-name="' + escapeHtml(item.name) + '" data-desc="' + escapeHtml(desc) + '" data-sort="' + (item.sort_order || 0) + '" data-active="' + (item.is_active ? 1 : 0) + '">';
                html += '<td><i class="bi bi-square cp-list-check" role="button" data-id="' + item.id + '"></i></td>';
                html += '<td class="text-muted small">' + (idx + 1) + '</td>';
                html += '<td>' + escapeHtml(item.name) + '</td>';
                html += '<td class="small text-muted">' + escapeHtml(desc || '—') + '</td>';
                html += '<td class="small">' + (item.sort_order || 0) + '</td>';
                html += '<td>' + (item.is_active ? '<span class="badge bg-success">Active</span>' : '<span class="badge bg-secondary">Inactive</span>') + '</td>';
                html += '<td>';
                html += '<button class="btn btn-sm btn-outline-primary cp-list-edit me-1" title="Edit"><i class="bi bi-pencil"></i></button>';
                html += '<button class="btn btn-sm btn-outline-warning cp-list-toggle" title="Toggle active"><i class="bi bi-power"></i></button>';
                html += '</td></tr>';
            });
            jQuery('#cpListBody').html(html);
            refreshListBulkBtn();
        });
    }
    jQuery('#cpListType').on('change', loadList);
    jQuery(document).on('click', '.cp-list-check', function() {
        jQuery(this).toggleClass('cp-checked').toggleClass('bi-square').toggleClass('bi-check-square');
        refreshListBulkBtn();
    });
    jQuery(document).on('click', '.cp-list-selall', function() {
        var anyUnchecked = jQuery('.cp-list-check:not(.cp-checked)').length > 0;
        jQuery('.cp-list-check').each(function() {
            jQuery(this).toggleClass('cp-checked', anyUnchecked).toggleClass('bi-square', !anyUnchecked).toggleClass('bi-check-square', anyUnchecked);
        });
        refreshListBulkBtn();
    });
    jQuery('#cpListBulkDelete').on('click', function() {
        var ids = jQuery('.cp-list-check.cp-checked').map(function() { return jQuery(this).data('id'); }).get();
        if (!ids.length) return;
        confirmAction('Delete ' + ids.length + ' item(s)? This is permanent.', function() {
            ajaxPost({ delete_system_list_item: 1, ids: ids }, function() { loadList(); showAlert('Deleted.', 'success'); });
        });
    });
    jQuery('#cpListAdd').on('click', function() {
        var $f = jQuery('#cpListForm')[0]; $f.reset();
        jQuery('#cpListForm [name=id]').val('');
        jQuery('#cpListForm [name=list_type]').val(jQuery('#cpListType').val());
        jQuery('#cpListForm [name=is_active]').prop('checked', true);
        new bootstrap.Modal('#cpListModal').show();
    });
    jQuery(document).on('click', '.cp-list-edit', function() {
        var $tr = jQuery(this).closest('tr');
        jQuery('#cpListForm [name=id]').val($tr.data('id'));
        jQuery('#cpListForm [name=list_type]').val(jQuery('#cpListType').val());
        jQuery('#cpListForm [name=name]').val($tr.data('name'));
        jQuery('#cpListForm [name=description]').val($tr.data('desc') || '');
        jQuery('#cpListForm [name=sort_order]').val($tr.data('sort'));
        jQuery('#cpListForm [name=is_active]').prop('checked', String($tr.data('active')) === '1');
        new bootstrap.Modal('#cpListModal').show();
    });
    jQuery(document).on('click', '.cp-list-toggle', function() {
        ajaxPost({ deactivate_system_list_item: 1, id: jQuery(this).closest('tr').data('id') }, function() { loadList(); });
    });
    jQuery('#cpListForm').on('submit', function(e) {
        e.preventDefault();
        var data = { save_system_list_item: 1 };
        jQuery(this).find('[name]').each(function() {
            if (this.type === 'checkbox') data[this.name] = this.checked ? 1 : 0;
            else data[this.name] = jQuery(this).val();
        });
        ajaxPost(data, function() { bootstrap.Modal.getInstance('#cpListModal').hide(); loadList(); showAlert('Saved.', 'success'); });
    });
    loadList();

    // ===== AI Prompts =====
    function refreshPromptBulkBtn() {
        jQuery('#cpPromptBulkDelete').toggleClass('d-none', jQuery('.cp-prompt-check.cp-checked').length === 0);
    }
    var promptCache = {};
    function loadPrompts() {
        jQuery('#cpPromptBody').html('<tr><td colspan="6" class="text-center text-muted small">Loading...</td></tr>');
        ajaxPost({ get_ai_prompts: 1 }, function(resp) {
            var rows = resp.prompts || [];
            promptCache = {};
            if (!rows.length) { jQuery('#cpPromptBody').html('<tr><td colspan="6" class="text-center text-muted small">No prompts yet.</td></tr>'); refreshPromptBulkBtn(); return; }
            var html = '';
            rows.forEach(function(p) {
                promptCache[p.id] = p;
                html += '<tr data-id="' + p.id + '">';
                html += '<td><i class="bi bi-square cp-prompt-check" role="button" data-id="' + p.id + '"></i></td>';
                html += '<td class="small"><code>' + escapeHtml(p.key) + '</code></td>';
                html += '<td>' + escapeHtml(p.name) + '</td>';
                html += '<td class="small">' + escapeHtml(p.model) + '</td>';
                html += '<td>' + (p.is_active ? '<span class="badge bg-success">Yes</span>' : '<span class="badge bg-secondary">No</span>') + '</td>';
                html += '<td><button class="btn btn-sm btn-outline-primary cp-prompt-edit" title="Edit"><i class="bi bi-pencil"></i></button></td>';
                html += '</tr>';
            });
            jQuery('#cpPromptBody').html(html);
            refreshPromptBulkBtn();
        });
    }
    jQuery(document).on('click', '.cp-prompt-check', function() {
        jQuery(this).toggleClass('cp-checked').toggleClass('bi-square').toggleClass('bi-check-square');
        refreshPromptBulkBtn();
    });
    jQuery(document).on('click', '.cp-prompt-selall', function() {
        var anyUnchecked = jQuery('.cp-prompt-check:not(.cp-checked)').length > 0;
        jQuery('.cp-prompt-check').each(function() {
            jQuery(this).toggleClass('cp-checked', anyUnchecked).toggleClass('bi-square', !anyUnchecked).toggleClass('bi-check-square', anyUnchecked);
        });
        refreshPromptBulkBtn();
    });
    jQuery('#cpPromptBulkDelete').on('click', function() {
        var ids = jQuery('.cp-prompt-check.cp-checked').map(function() { return jQuery(this).data('id'); }).get();
        if (!ids.length) return;
        confirmAction('Delete ' + ids.length + ' prompt(s)? This is permanent.', function() {
            ajaxPost({ delete_ai_prompt: 1, ids: ids }, function() { loadPrompts(); showAlert('Deleted.', 'success'); });
        });
    });
    function fillPromptForm(p) {
        var $f = jQuery('#cpPromptForm');
        $f[0].reset();
        $f.find('[name=id]').val(p ? p.id : '');
        $f.find('[name=name]').val(p ? p.name : '');
        $f.find('[name=key]').val(p ? p.key : '');
        $f.find('[name=system_prompt]').val(p ? p.system_prompt : '');
        $f.find('[name=user_prompt_template]').val(p ? p.user_prompt_template : '');
        $f.find('[name=model]').val(p ? p.model : 'mistral');
        $f.find('[name=temperature]').val(p ? p.temperature : '0.7');
        $f.find('[name=max_tokens]').val(p ? p.max_tokens : '4096');
        $f.find('[name=response_format]').val(p ? p.response_format : 'json');
        $f.find('[name=notes]').val(p ? (p.notes || '') : '');
        $f.find('[name=is_active]').prop('checked', p ? !!p.is_active : true);
        $f.find('[name=key]').prop('readonly', !!p);
    }
    jQuery('#cpPromptAdd').on('click', function() { fillPromptForm(null); new bootstrap.Modal('#cpPromptModal').show(); });
    jQuery(document).on('click', '.cp-prompt-edit', function() {
        var id = jQuery(this).closest('tr').data('id');
        fillPromptForm(promptCache[id]);
        new bootstrap.Modal('#cpPromptModal').show();
    });
    jQuery('#cpPromptForm').on('submit', function(e) {
        e.preventDefault();
        var data = { save_ai_prompt: 1 };
        jQuery(this).find('[name]').each(function() {
            if (this.type === 'checkbox') data[this.name] = this.checked ? 1 : 0;
            else data[this.name] = jQuery(this).val();
        });
        ajaxPost(data, function() { bootstrap.Modal.getInstance('#cpPromptModal').hide(); loadPrompts(); showAlert('Prompt saved.', 'success'); },
            function(xhr) { showAlert(xhr.responseJSON ? (xhr.responseJSON.error || 'Save failed') : 'Save failed', 'danger'); });
    });
    loadPrompts();

    // ===== Activity Logs =====
    var logPage = 1, logLastPage = 1;
    function loadLogs() {
        jQuery('#cpLogBody').html('<tr><td colspan="5" class="text-center text-muted small">Loading...</td></tr>');
        ajaxPost({ get_activity_logs: 1, page: logPage }, function(resp) {
            var rows = resp.data || [];
            logLastPage = resp.last_page || 1;
            renderPagination('#cpLogPagination', resp, function(p) { logPage = p; loadLogs(); });
            if (!rows.length) { jQuery('#cpLogBody').html('<tr><td colspan="5" class="text-center text-muted small">No activity logged.</td></tr>'); return; }
            var html = '';
            rows.forEach(function(l) {
                var det = l.details;
                if (det && typeof det === 'object') det = JSON.stringify(det);
                if (!det) det = l.details_text || '';
                var who = l.user ? (l.user.full_name || l.user.email) : 'System';
                var target = (l.model_type ? (String(l.model_type).split('\\').pop() + (l.model_id ? ' #' + l.model_id : '')) : '');
                html += '<tr>';
                html += '<td class="small text-muted">' + escapeHtml((l.created_at || '').replace('T', ' ').substring(0, 16)) + '</td>';
                html += '<td class="small">' + escapeHtml(who) + '</td>';
                html += '<td class="small">' + escapeHtml(l.action) + '</td>';
                html += '<td class="small">' + escapeHtml(target) + '</td>';
                html += '<td class="small text-muted">' + escapeHtml(String(det).substring(0, 200)) + '</td>';
                html += '</tr>';
            });
            jQuery('#cpLogBody').html(html);
        });
    }
    var logsLoaded = false;
    jQuery('button[data-bs-target="#cpLogs"]').on('shown.bs.tab', function() { if (!logsLoaded) { logsLoaded = true; loadLogs(); } });

    // ===== PDF Templates =====
    var pdfCache = {};
    function loadPdf() {
        jQuery('#cpPdfBody').html('<tr><td colspan="5" class="text-center text-muted small">Loading...</td></tr>');
        ajaxPost({ get_pdf_templates: 1 }, function(resp) {
            var rows = resp.templates || [];
            pdfCache = {};
            if (!rows.length) { jQuery('#cpPdfBody').html('<tr><td colspan="5" class="text-center text-muted small">No templates yet. Create one.</td></tr>'); return; }
            var html = '';
            rows.forEach(function(t) {
                pdfCache[t.id] = t;
                html += '<tr data-id="' + t.id + '">';
                html += '<td class="small"><code>' + escapeHtml(t.key) + '</code></td>';
                html += '<td>' + escapeHtml(t.name) + '</td>';
                html += '<td class="small">' + escapeHtml(t.paper_size) + '</td>';
                html += '<td class="small">' + escapeHtml(t.orientation) + '</td>';
                html += '<td><button class="btn btn-sm btn-outline-primary cp-pdf-edit" title="Edit"><i class="bi bi-pencil"></i></button></td>';
                html += '</tr>';
            });
            jQuery('#cpPdfBody').html(html);
        });
    }
    function fillPdfForm(t) {
        var $f = jQuery('#cpPdfForm');
        $f[0].reset();
        $f.find('[name=id]').val(t ? t.id : '');
        $f.find('[name=name]').val(t ? t.name : '');
        $f.find('[name=key]').val(t ? t.key : '');
        $f.find('[name=header_html]').val(t ? (t.header_html || '') : '');
        $f.find('[name=footer_html]').val(t ? (t.footer_html || '') : '');
        $f.find('[name=css]').val(t ? (t.css || '') : '');
        $f.find('[name=paper_size]').val(t ? t.paper_size : 'A4');
        $f.find('[name=orientation]').val(t ? t.orientation : 'portrait');
        $f.find('[name=key]').prop('readonly', !!t);
    }
    jQuery('#cpPdfAdd').on('click', function() { fillPdfForm(null); new bootstrap.Modal('#cpPdfModal').show(); });
    jQuery(document).on('click', '.cp-pdf-edit', function() { fillPdfForm(pdfCache[jQuery(this).closest('tr').data('id')]); new bootstrap.Modal('#cpPdfModal').show(); });
    jQuery('#cpPdfForm').on('submit', function(e) {
        e.preventDefault();
        var data = { save_pdf_template: 1 };
        jQuery(this).find('[name]').each(function() { data[this.name] = jQuery(this).val(); });
        ajaxPost(data, function() { bootstrap.Modal.getInstance('#cpPdfModal').hide(); loadPdf(); showAlert('Template saved.', 'success'); },
            function(xhr) { showAlert(xhr.responseJSON ? (xhr.responseJSON.error || 'Save failed') : 'Save failed', 'danger'); });
    });
    var pdfLoaded = false;
    jQuery('button[data-bs-target="#cpPdf"]').on('shown.bs.tab', function() { if (!pdfLoaded) { pdfLoaded = true; loadPdf(); } });

    // ===== Glossary tab (read-only reference of every system_list) =====
    var glossaryLoaded = false;
    function loadGlossary() {
        var $box = jQuery('#cpGlossaryBody');
        $box.html('<p class="text-muted text-center small">Loading…</p>');

        // List types to display + friendly section labels.
        var types = [
            ['service_type',          'Service Types'],
            ['accommodation_category','Accommodation Categories (SP-side)'],
            ['accommodation_comfort', 'Accommodation Comfort (Traveller preference)'],
            ['vehicle_type',          'Vehicle Types (SP-side)'],
            ['vehicle_comfort',       'Vehicle Comfort (Traveller preference)'],
            ['guide_preference',      'Guide Preferences'],
            ['activity_type',         'Activity Types'],
            ['experience_type',       'Experience Types'],
            ['occupancy_unit',        'Occupancy Units (used in SP pricing)'],
            ['meal_plan',             'Meal Plans (used in SP pricing)'],
            ['travel_pace',           'Travel Pace'],
            ['budget_sensitivity',    'Budget Sensitivity'],
            ['payment_mode',          'Payment Modes'],
        ];

        // Fire one AJAX per type, render as a section. Sequential to keep
        // the markup ordered.
        var html = '';
        var remaining = types.length;
        types.forEach(function(tuple, idx) {
            (function(listType, label, position) {
                ajaxPost({ get_system_lists: 1, list_type: listType }, function(resp) {
                    var items = resp.items || [];
                    var section = '<div class="cp-glossary-section mb-4" data-pos="' + position + '">';
                    section += '<h6 class="fw-bold text-primary mb-2">' + escapeHtml(label) + ' <span class="text-muted small fw-normal">(' + items.length + ')</span></h6>';
                    if (!items.length) {
                        section += '<p class="text-muted small mb-0 ms-2">No options.</p>';
                    } else {
                        section += '<table class="table table-sm table-bordered mb-0">';
                        section += '<thead class="table-light"><tr><th class="w-status">Option</th><th>Description</th><th class="w-status">Status</th></tr></thead>';
                        section += '<tbody>';
                        items.forEach(function(item) {
                            section += '<tr>';
                            section += '<td class="fw-semibold">' + escapeHtml(item.name) + '</td>';
                            section += '<td><small class="text-muted">' + escapeHtml(item.description || '— (no description set)') + '</small></td>';
                            section += '<td>' + (item.is_active ? '<span class="badge bg-success">Active</span>' : '<span class="badge bg-secondary">Inactive</span>') + '</td>';
                            section += '</tr>';
                        });
                        section += '</tbody></table>';
                    }
                    section += '</div>';
                    // Stash on the box keyed by position so we can re-order.
                    $box.data('section-' + position, section);
                    remaining--;
                    if (remaining === 0) {
                        var ordered = '';
                        for (var i = 0; i < types.length; i++) {
                            ordered += $box.data('section-' + i) || '';
                        }
                        $box.html(ordered);
                    }
                });
            })(tuple[0], tuple[1], idx);
        });
    }
    jQuery('button[data-bs-target="#cpGlossary"]').on('shown.bs.tab', function() {
        if (!glossaryLoaded) { glossaryLoaded = true; loadGlossary(); }
    });
});
</script>
@endsection
