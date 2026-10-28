/**
 * A stay's rate form (resources/views/partials/rate-rooms.blade.php), shared by
 * the partner's rate page and HCT's provider page so the two cannot drift
 * apart, and kept to the app's form box for box.
 *
 * The property's name, place, photos and extras are said once; each room
 * carries its own tier, category, rooms, price, occupancy, meal plan and
 * guests. A new stay with several rooms is saved in one call
 * (save_sp_pricing_rows), all of them or none; an existing rate is one room
 * and saved on its own (save_sp_pricing).
 */
window.RateRooms = (function ($) {
    'use strict';

    var MAX_PHOTOS = 8;
    var kept = [];        // photo paths already stored that are being kept
    var editing = false;  // an existing rate: one room, no adding

    function titleFor(tier, room) {
        var parts = [(tier || '').split(' - ')[0], room || ''].filter(Boolean);
        return parts.length ? parts.join(' · ') : 'Room';
    }

    function dropdowns($el) {
        if (!window.buildCustomDropdown) return;
        $el.find('select.custom-select').each(function () { window.buildCustomDropdown(this); });
    }

    function refresh() {
        var $rooms = $('#rrRooms .rr-room');
        $rooms.each(function () {
            var $r = $(this);
            $r.find('.rr-title').text(titleFor($r.find('[data-field=comfort_tier]').val(), $r.find('[data-field=room_category]').val()));
        });
        $rooms.find('.rr-remove').toggleClass('d-none', $rooms.length <= 1);
        $('#rrAddRoom').toggleClass('d-none', editing);
    }

    function addRoom(values) {
        var $room = $($('#rrRoomTpl')[0].content.cloneNode(true)).children().first();
        $('#rrRooms').append($room);
        if (values) {
            $.each(values, function (field, value) {
                $room.find('[data-field=' + field + ']').val(value === null || value === undefined ? '' : value);
            });
            // A figure already stored is the member's own, not one to recompute.
            $room.find('.guests-field').data('touched', !!values.guest_capacity);
        }
        dropdowns($room);
        refresh();
        return $room;
    }

    function addAddon(values) {
        var $row = $($('#rrAddonTpl')[0].content.cloneNode(true)).children().first();
        $('#rrAddons').append($row);
        $.each(values || {}, function (key, value) {
            $row.find('[data-addon=' + key + ']').val(value === null || value === undefined ? '' : value);
        });
    }

    function renderKept() {
        var $box = $('#rrPhotosKept').empty();
        kept.forEach(function (path, i) {
            $box.append(
                $('<div class="position-relative"></div>')
                    .append($('<img class="rounded border" style="width:72px;height:54px;object-fit:cover">').attr('src', path))
                    .append($('<button type="button" class="btn btn-sm btn-danger position-absolute top-0 end-0 py-0 px-1 rr-photo-remove" title="Remove">&times;</button>').attr('data-i', i))
            );
        });
    }

    $(document).on('change', '#rrRooms [data-field=comfort_tier], #rrRooms [data-field=room_category]', refresh);
    $(document).on('click', '#rrAddRoom', function () { addRoom(); });
    $(document).on('click', '.rr-remove', function () { $(this).closest('.rr-room').remove(); refresh(); });
    $(document).on('click', '#rrAddAddon', function () { addAddon(); });
    $(document).on('click', '.rr-addon-remove', function () { $(this).closest('.rr-addon').remove(); });
    $(document).on('click', '.rr-photo-remove', function () { kept.splice(+$(this).data('i'), 1); renderKept(); });

    /** Put a rate on the form, or empty it for a new stay. */
    function fill(r) {
        editing = !!r;
        $('#rrPropertyName').val(r ? (r.category || '') : '');
        $('#rrRooms').empty();
        addRoom(r ? {
            comfort_tier: r.comfort_tier,
            room_category: r.room_category,
            total_rooms: r.total_rooms,
            price: r.price,
            default_occupancy: r.default_occupancy,
            meal_plan: r.meal_plan,
            guest_capacity: r.guest_capacity,
        } : null);
        $('#rrLatitude').val(r ? (r.latitude || '') : '');
        $('#rrLongitude').val(r ? (r.longitude || '') : '');
        $('#rrSeasonality').val(r ? (r.seasonality_notes || '') : '');
        kept = (r && r.photos) ? r.photos.slice() : [];
        renderKept();
        $('#rrPhotos').val('');
        $('#rrAddons').empty();
        ((r && r.addons) || []).forEach(function (a) {
            addAddon({ name: a.name, price: a.price, price_unit: a.price_unit, description: a.description });
        });
        refresh();
    }

    /** The rooms as rows, and what is missing from them. */
    function collect() {
        var rows = [];
        var missing = [];
        $('#rrRooms .rr-room').each(function () {
            var $r = $(this);
            var row = {};
            $r.find('[data-field]').each(function () {
                row[$(this).data('field')] = $(this).val() || '';
            });
            var title = titleFor(row.comfort_tier, row.room_category);
            if (!row.comfort_tier) missing.push(title + ': pick a comfort tier.');
            else if (!row.room_category) missing.push(title + ': pick a room category.');
            else if (!row.total_rooms) missing.push(title + ': say how many rooms.');
            else if (!row.price) missing.push(title + ': give the rate per night.');
            rows.push(row);
        });
        if (!$('#rrPropertyName').val().trim()) missing.unshift('Give the property a name.');
        return { rows: rows, missing: missing };
    }

    /**
     * Save the stay. opts: providerId, id (when editing), asDraft, isActive,
     * description, done(resp), fail(message).
     */
    function save(opts) {
        var c = collect();
        if (c.missing.length && !opts.asDraft) {
            opts.fail(c.missing[0]);
            return;
        }

        var fd = new FormData();
        var put = function (key, value) {
            if (value !== undefined && value !== null) fd.append(key, value);
        };
        put(opts.id ? 'save_sp_pricing' : 'save_sp_pricing_rows', 1);
        put('provider_id', opts.providerId);
        put('service_type', 'accommodation');
        put('category', $('#rrPropertyName').val().trim());
        put('unit', 'per night');
        put('description', opts.description || '');
        put('is_active', opts.isActive ? 1 : 0);
        if (opts.asDraft) put('as_draft', 1);
        put('latitude', $('#rrLatitude').val());
        put('longitude', $('#rrLongitude').val());
        put('seasonality_notes', $('#rrSeasonality').val());

        kept.forEach(function (path) { put('photos_keep[]', path); });
        // Every stored photo removed still has to be said: a post cannot carry
        // an empty list, and with nothing sent the server kept them all. A
        // blank key reads as "keep none".
        if (opts.id && !kept.length) put('photos_keep', '');
        var files = $('#rrPhotos')[0].files;
        for (var i = 0; i < files.length && kept.length + i < MAX_PHOTOS; i++) {
            put('photos[]', files[i]);
        }

        // Always sent, so emptying the list removes the stored extras; one
        // blank entry keeps it a list, and the server drops the blank.
        var addons = 0;
        $('#rrAddons .rr-addon').each(function () {
            var $a = $(this);
            var name = $a.find('[data-addon=name]').val().trim();
            if (!name) return;
            ['name', 'price', 'price_unit', 'description'].forEach(function (key) {
                put('addons[' + addons + '][' + key + ']', $a.find('[data-addon=' + key + ']').val());
            });
            addons++;
        });
        if (!addons) put('addons[0]', '');

        if (opts.id) {
            put('id', opts.id);
            $.each(c.rows[0], function (field, value) { put(field, value); });
        } else {
            c.rows.forEach(function (row, n) {
                $.each(row, function (field, value) { put('rows[' + n + '][' + field + ']', value); });
            });
        }

        $.ajax({
            url: '/ajax',
            type: 'POST',
            data: fd,
            processData: false,
            contentType: false,
            skipGlobalError: true,
        }).done(function (resp) {
            opts.done(resp, c.rows.length);
        }).fail(function (xhr) {
            opts.fail((xhr.responseJSON && xhr.responseJSON.error) || 'Nothing was saved. Try again.');
        });
    }

    return { fill: fill, save: save, titleFor: titleFor };
})(jQuery);
