{{-- A stay's rate form, the same on the partner's page and on HCT's.

     It follows the app's form box for box: the property's name once, then
     each room with its own tier, category, price, occupancy and meal plan,
     then where the place is, when it is open, its photos and its extras.
     A new stay can carry several rooms, saved together as one rate each; an
     existing rate is one room and is edited on its own. Driven by
     public/js/rate-rooms.js. --}}
<div id="rrBlock">
    <div class="mb-2">
        <label class="form-label small">Property name <span class="text-danger">*</span></label>
        <input type="text" class="form-control form-control-sm" id="rrPropertyName" maxlength="100"
               placeholder="e.g. Tirthan Riverside Homestay">
    </div>

    <div id="rrRooms"></div>
    <button type="button" class="btn btn-sm btn-outline-success mb-3" id="rrAddRoom">
        <i class="bi bi-plus-lg me-1"></i> Add another room
    </button>

    <div class="row g-2 mb-2">
        <div class="col-md-6">
            <label class="form-label small">Latitude</label>
            <input type="number" step="0.0000001" min="-90" max="90" class="form-control form-control-sm"
                   id="rrLatitude" placeholder="e.g. 31.6234567">
        </div>
        <div class="col-md-6">
            <label class="form-label small">Longitude</label>
            <input type="number" step="0.0000001" min="-180" max="180" class="form-control form-control-sm"
                   id="rrLongitude" placeholder="e.g. 77.3456789">
        </div>
    </div>
    <div class="mb-2">
        <label class="form-label small">Seasonality</label>
        <input type="text" class="form-control form-control-sm" id="rrSeasonality" maxlength="1000"
               placeholder="e.g. Closed in January; best April to June">
    </div>

    <div class="mb-2">
        <label class="form-label small">Photos</label>
        <div id="rrPhotosKept" class="d-flex flex-wrap gap-2 mb-1"></div>
        <input type="file" class="form-control form-control-sm" id="rrPhotos" accept="image/*" multiple>
        <small class="text-muted">Up to 8 photos of the property.</small>
    </div>

    <div class="mb-2">
        <label class="form-label small">Add-ons</label>
        <div class="small text-muted mb-1">Extras a guest adds to the stay, such as a mattress or a pickup from the bus stand.</div>
        <div id="rrAddons"></div>
        <button type="button" class="btn btn-sm btn-outline-secondary" id="rrAddAddon">
            <i class="bi bi-plus-lg me-1"></i> Add an extra
        </button>
    </div>
</div>

<template id="rrRoomTpl">
    <div class="bulk-row rr-room card border p-2 mb-2">
        <div class="d-flex align-items-center mb-1">
            <strong class="small rr-title flex-grow-1">Room</strong>
            <button type="button" class="btn btn-sm btn-outline-danger rr-remove" title="Remove this room"><i class="bi bi-x-lg"></i></button>
        </div>
        <div class="row g-2">
            <div class="col-md-6">
                <label class="form-label small mb-1">Comfort tier <span class="text-danger">*</span></label>
                <select class="form-select form-select-sm custom-select" data-field="comfort_tier" data-list-type="accommodation_category">
                    <option value="">Pick...</option>
                    @foreach($accommodationCategories as $c)
                        <option value="{{ $c->name }}" data-desc="{{ $c->description }}">{{ $c->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-6">
                <label class="form-label small mb-1">Room category <span class="text-danger">*</span></label>
                <select class="form-select form-select-sm custom-select" data-field="room_category" data-list-type="room_category">
                    <option value="">Pick...</option>
                    @foreach($roomCategories as $r)
                        <option value="{{ $r->name }}" data-desc="{{ $r->description }}">{{ $r->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label small mb-1">Total rooms <span class="text-danger">*</span></label>
                <input type="number" min="1" max="500" class="form-control form-control-sm" data-field="total_rooms" placeholder="e.g. 4">
            </div>
            <div class="col-md-4">
                <label class="form-label small mb-1">Rate per night (₹) <span class="text-danger">*</span></label>
                <input type="number" step="0.01" min="0" class="form-control form-control-sm" data-field="price" placeholder="e.g. 2500">
            </div>
            <div class="col-md-4">
                <label class="form-label small mb-1">Occupancy</label>
                <select class="form-select form-select-sm custom-select" data-field="default_occupancy" data-list-type="room_occupancy">
                    <option value="">Pick...</option>
                    @foreach($roomOccupancies as $o)
                        <option value="{{ $o->name }}" data-desc="{{ $o->description }}">{{ $o->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-6">
                <label class="form-label small mb-1">Meal plan</label>
                <select class="form-select form-select-sm custom-select" data-field="meal_plan" data-list-type="meal_plan">
                    <option value="">— no meals (room only) —</option>
                    @foreach($mealPlans as $m)
                        <option value="{{ $m->name }}" data-desc="{{ $m->description }}">{{ $m->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-6">
                <label class="form-label small mb-1">Total guests</label>
                <input type="number" min="1" max="2000" class="form-control form-control-sm guests-field" data-field="guest_capacity" placeholder="rooms × occupancy">
            </div>
        </div>
        <small class="text-muted">Total guests = rooms &times; occupancy, e.g. 2 double rooms = 4. Fills itself; change it if a room takes more.</small>
    </div>
</template>

<template id="rrAddonTpl">
    <div class="rr-addon row g-2 mb-2 align-items-end">
        <div class="col-md-4">
            <label class="form-label small mb-1">Extra</label>
            <input type="text" class="form-control form-control-sm" data-addon="name" maxlength="150" placeholder="e.g. Extra mattress">
        </div>
        <div class="col-md-2">
            <label class="form-label small mb-1">Price (₹)</label>
            <input type="number" step="0.01" min="0" class="form-control form-control-sm" data-addon="price" placeholder="300">
        </div>
        <div class="col-md-2">
            <label class="form-label small mb-1">Per</label>
            <input type="text" class="form-control form-control-sm" data-addon="price_unit" maxlength="50" placeholder="per night">
        </div>
        <div class="col-md-3">
            <label class="form-label small mb-1">What it involves</label>
            <input type="text" class="form-control form-control-sm" data-addon="description" maxlength="1000">
        </div>
        <div class="col-md-1 text-end">
            <button type="button" class="btn btn-sm btn-outline-danger rr-addon-remove" title="Remove this extra"><i class="bi bi-x-lg"></i></button>
        </div>
    </div>
</template>
