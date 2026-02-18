@extends('admin.layout')
@section('title', 'Calendar - HCT')
@section('content')

<div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="mb-0"><i class="bi bi-calendar3"></i> Trip Calendar</h5>
    <div class="d-flex gap-2 align-items-center">
        <button class="btn btn-sm btn-outline-secondary" id="prevMonth"><i class="bi bi-chevron-left"></i></button>
        <span class="fw-semibold" id="currentMonthLabel" style="min-width: 140px; text-align: center;"></span>
        <button class="btn btn-sm btn-outline-secondary" id="nextMonth"><i class="bi bi-chevron-right"></i></button>
        <button class="btn btn-sm btn-outline-primary ms-2" id="todayBtn">Today</button>
    </div>
</div>

<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-bordered mb-0" id="calendarGrid">
                <thead class="table-light">
                    <tr>
                        <th class="text-center" style="width:14.28%">Mon</th>
                        <th class="text-center" style="width:14.28%">Tue</th>
                        <th class="text-center" style="width:14.28%">Wed</th>
                        <th class="text-center" style="width:14.28%">Thu</th>
                        <th class="text-center" style="width:14.28%">Fri</th>
                        <th class="text-center" style="width:14.28%">Sat</th>
                        <th class="text-center" style="width:14.28%">Sun</th>
                    </tr>
                </thead>
                <tbody id="calendarBody">
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="mt-3">
    <small class="text-muted me-3"><span class="badge bg-warning">&nbsp;</span> Not Confirmed</small>
    <small class="text-muted me-3"><span class="badge bg-success">&nbsp;</span> Confirmed</small>
    <small class="text-muted me-3"><span class="badge bg-primary">&nbsp;</span> Running</small>
    <small class="text-muted me-3"><span class="badge bg-secondary">&nbsp;</span> Completed</small>
    <small class="text-muted"><span class="badge bg-danger">&nbsp;</span> Cancelled</small>
</div>

@endsection

@section('js')
<script>
var calMonth, calYear;

function statusColor(status) {
    switch (status) {
        case 'not_confirmed': return 'warning';
        case 'confirmed': return 'success';
        case 'running': return 'primary';
        case 'completed': return 'secondary';
        case 'cancelled': return 'danger';
        default: return 'light';
    }
}

var monthNames = ['January', 'February', 'March', 'April', 'May', 'June',
    'July', 'August', 'September', 'October', 'November', 'December'];

function renderCalendar(month, year) {
    var firstDay = new Date(year, month - 1, 1);
    var lastDay = new Date(year, month, 0);
    var startDow = firstDay.getDay();
    startDow = startDow === 0 ? 6 : startDow - 1;
    var totalDays = lastDay.getDate();
    var today = new Date();
    var todayStr = today.getFullYear() + '-' + String(today.getMonth() + 1).padStart(2, '0') + '-' + String(today.getDate()).padStart(2, '0');

    $('#currentMonthLabel').text(monthNames[month - 1] + ' ' + year);

    var html = '';
    var day = 1;
    var started = false;

    for (var row = 0; row < 6; row++) {
        if (day > totalDays) break;
        html += '<tr>';
        for (var col = 0; col < 7; col++) {
            if (!started && col < startDow) {
                html += '<td class="text-muted bg-light" style="height: 100px; vertical-align: top; padding: 4px;"></td>';
            } else if (day > totalDays) {
                html += '<td class="text-muted bg-light" style="height: 100px; vertical-align: top; padding: 4px;"></td>';
            } else {
                started = true;
                var dateStr = year + '-' + String(month).padStart(2, '0') + '-' + String(day).padStart(2, '0');
                var isToday = dateStr === todayStr;
                html += '<td data-date="' + dateStr + '" style="height: 100px; vertical-align: top; padding: 4px;"' + (isToday ? ' class="bg-light"' : '') + '>';
                html += '<div class="fw-bold small' + (isToday ? ' text-primary' : '') + '">' + day + '</div>';
                html += '<div class="cal-trips" data-date="' + dateStr + '"></div>';
                html += '</td>';
                day++;
            }
        }
        html += '</tr>';
    }
    $('#calendarBody').html(html);
}

function loadCalendarTrips(month, year) {
    renderCalendar(month, year);
    ajaxPost({ get_calendar_trips: 1, month: month, year: year }, function(resp) {
        var trips = resp.trips || [];
        trips.forEach(function(trip) {
            var start = new Date(trip.start_date);
            var end = new Date(trip.end_date);
            var calStart = new Date(year, month - 1, 1);
            var calEnd = new Date(year, month, 0);

            if (start < calStart) start = calStart;
            if (end > calEnd) end = calEnd;

            var current = new Date(start);
            while (current <= end) {
                var dateStr = current.getFullYear() + '-' + String(current.getMonth() + 1).padStart(2, '0') + '-' + String(current.getDate()).padStart(2, '0');
                var cell = $('.cal-trips[data-date="' + dateStr + '"]');
                if (cell.length) {
                    var color = statusColor(trip.status);
                    var label = trip.trip_id || trip.id;
                    var name = trip.user ? trip.user.full_name : '';
                    cell.append(
                        '<a href="/trip-manager/' + trip.id + '" target="_blank" class="badge bg-' + color + ' d-block mb-1 text-decoration-none" ' +
                        'style="font-size: 0.65rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" ' +
                        'title="' + label + ' - ' + name + '">' +
                        label + '</a>'
                    );
                }
                current.setDate(current.getDate() + 1);
            }
        });
    });
}

$(function() {
    var now = new Date();
    calMonth = now.getMonth() + 1;
    calYear = now.getFullYear();
    loadCalendarTrips(calMonth, calYear);
});

$('#prevMonth').on('click', function() {
    calMonth--;
    if (calMonth < 1) { calMonth = 12; calYear--; }
    loadCalendarTrips(calMonth, calYear);
});

$('#nextMonth').on('click', function() {
    calMonth++;
    if (calMonth > 12) { calMonth = 1; calYear++; }
    loadCalendarTrips(calMonth, calYear);
});

$('#todayBtn').on('click', function() {
    var now = new Date();
    calMonth = now.getMonth() + 1;
    calYear = now.getFullYear();
    loadCalendarTrips(calMonth, calYear);
});
</script>
@endsection
