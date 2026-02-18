@extends('admin.layout')
@section('title', 'Admin - HCT Dashboard')
@section('content')

<div class="row">
    <div class="col-md-7">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">HCT Team Members</h5>
                <button class="btn btn-sm btn-success" data-bs-toggle="modal" data-bs-target="#addUserModal"><i class="bi bi-plus"></i> Add User</button>
            </div>
            <div class="card-body">
                <table class="table table-sm">
                    <thead><tr><th>Name</th><th>Email</th><th>Role</th><th>Status</th><th>Actions</th></tr></thead>
                    <tbody>
                        @foreach($hctUsers as $u)
                        <tr>
                            <td>{{ $u->full_name }}</td>
                            <td>{{ $u->email }}</td>
                            <td><span class="badge bg-{{ $u->user_role === 'hct_admin' ? 'danger' : 'primary' }}">{{ $u->user_role }}</span></td>
                            <td><span class="badge bg-{{ $u->status === 'active' ? 'success' : 'secondary' }}">{{ $u->status }}</span></td>
                            <td>
                                @if($u->id !== auth()->id())
                                    <button class="btn btn-sm btn-outline-danger deactivate-user" data-id="{{ $u->id }}">Deactivate</button>
                                @endif
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-md-5">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">System Reference Lists</h5>
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <select class="form-select form-select-sm" id="listTypeSelect">
                        <option value="service_type">Service Types</option>
                        <option value="accommodation_category">Accommodation Categories</option>
                        <option value="vehicle_type">Vehicle Types</option>
                        <option value="activity_type">Activity Types</option>
                        <option value="experience_type">Experience Types</option>
                        <option value="payment_mode">Payment Modes</option>
                    </select>
                </div>
                <div id="listItems"></div>
                <div class="input-group mt-2">
                    <input type="text" class="form-control form-control-sm" id="newListItem" placeholder="Add new item...">
                    <button class="btn btn-sm btn-success" id="addListItem"><i class="bi bi-plus"></i></button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Add User Modal -->
<div class="modal fade" id="addUserModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header"><h5 class="modal-title">Add HCT User</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <div id="adduser-alert"></div>
                <form id="addUserForm">
                    <div class="mb-3"><label class="form-label">Full Name</label><input type="text" class="form-control" name="full_name" required></div>
                    <div class="mb-3"><label class="form-label">Email</label><input type="email" class="form-control" name="email" required></div>
                    <div class="mb-3"><label class="form-label">Password</label><input type="password" class="form-control" name="password" required minlength="8"></div>
                    <div class="mb-3"><label class="form-label">Role</label>
                        <select class="form-select" name="user_role"><option value="hct_collaborator">HCT Collaborator</option><option value="hct_admin">HCT Admin</option></select>
                    </div>
                    <button type="submit" class="btn btn-success w-100">Create User</button>
                </form>
            </div>
        </div>
    </div>
</div>

@endsection

@section('js')
<script>
function loadList() {
    ajaxPost({ get_system_lists: 1, list_type: $('#listTypeSelect').val() }, function(resp) {
        var html = '';
        resp.items.forEach(function(item) {
            html += '<div class="d-flex justify-content-between align-items-center py-1 border-bottom">';
            html += '<span class="small">' + item.name + '</span>';
            html += '<button class="btn btn-sm btn-outline-danger deactivate-list-item" data-id="' + item.id + '"><i class="bi bi-x"></i></button>';
            html += '</div>';
        });
        if (!resp.items.length) html = '<p class="text-muted small text-center">No items</p>';
        $('#listItems').html(html);
    });
}

$(function() { loadList(); });
$('#listTypeSelect').on('change', loadList);

$('#addListItem').on('click', function() {
    var name = $('#newListItem').val().trim();
    if (!name) return;
    ajaxPost({ save_system_list_item: 1, list_type: $('#listTypeSelect').val(), name: name }, function() {
        $('#newListItem').val('');
        loadList();
    });
});

$(document).on('click', '.deactivate-list-item', function() {
    ajaxPost({ deactivate_system_list_item: 1, id: $(this).data('id') }, function() { loadList(); });
});

$('#addUserForm').on('submit', function(e) {
    e.preventDefault();
    var data = { create_hct_user: 1 };
    $(this).find('[name]').each(function() { data[$(this).attr('name')] = $(this).val(); });
    ajaxPost(data, function() { location.reload(); }, function(xhr) {
        $('#adduser-alert').html('<div class="alert alert-danger">' + (xhr.responseJSON ? xhr.responseJSON.error : 'Error') + '</div>');
    });
});

$('.deactivate-user').on('click', function() {
    if (!confirm('Deactivate this user?')) return;
    ajaxPost({ deactivate_hct_user: 1, user_id: $(this).data('id') }, function() { location.reload(); });
});
</script>
@endsection
