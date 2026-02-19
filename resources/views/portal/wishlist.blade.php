@extends('portal.layout')
@section('title', 'My Wishlist - HECO')

@section('css')
<style>
    .wishlist-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
        gap: 1.5rem;
    }
    .wishlist-price-row {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 0.75rem 1rem;
        border-top: 1px solid #f0f0f0;
    }
    .wishlist-price-left {
        display: flex;
        align-items: baseline;
        gap: 0.25rem;
    }
    .wishlist-price-actions {
        display: flex;
        align-items: center;
        gap: 0.75rem;
    }
    .wishlist-action-icon {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 34px;
        height: 34px;
        border-radius: 50%;
        border: 1px solid #e0e0e0;
        background: #fff;
        color: #555;
        font-size: 1rem;
        cursor: pointer;
        transition: all 0.2s;
        text-decoration: none;
    }
    .wishlist-action-icon:hover {
        background: var(--heco-green, #2d6a4f);
        color: #fff;
        border-color: var(--heco-green, #2d6a4f);
    }
    .wishlist-action-remove:hover {
        background: #dc3545;
        color: #fff;
        border-color: #dc3545;
    }
    .empty-state { padding: 80px 20px; }
    .empty-state i { font-size: 5rem; color: #dee2e6; }
</style>
@endsection

@section('content')
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h3 class="mb-1"><i class="bi bi-heart-fill text-danger"></i> My Wishlist</h3>
            <p class="text-muted mb-0">Experiences you've saved for later.</p>
        </div>
        <a href="/home" class="btn btn-success">
            <i class="bi bi-compass"></i> Explore More
        </a>
    </div>

    <div id="wishlistContainer">
        <div class="text-center py-5">
            <div class="spinner-border text-success" role="status"></div>
            <p class="text-muted mt-2">Loading your wishlist...</p>
        </div>
    </div>
</div>
@endsection

@section('js')
<script>
jQuery(function() {
    loadWishlist();

    function loadWishlist() {
        ajaxPost({ get_wishlist: 1 }, function(resp) {
            var items = resp.data || [];
            if (items.length === 0) {
                renderEmpty();
                return;
            }
            renderWishlist(items);
        });
    }

    function renderEmpty() {
        var html = '<div class="text-center empty-state">';
        html += '<i class="bi bi-heart"></i>';
        html += '<h4 class="text-muted mt-3">Your wishlist is empty</h4>';
        html += '<p class="text-muted">Browse experiences and tap the heart icon to save them here.</p>';
        html += '<a href="/home" class="btn btn-success btn-lg"><i class="bi bi-compass"></i> Explore Experiences</a>';
        html += '</div>';
        jQuery('#wishlistContainer').html(html);
    }

    function renderWishlist(items) {
        var html = '<div class="wishlist-grid">';
        items.forEach(function(exp) {
            var imgHtml = exp.card_image
                ? '<img src="/storage/' + exp.card_image + '" alt="' + exp.name + '">'
                : '<div class="exp-placeholder"><i class="bi bi-image"></i></div>';

            var durationText = '';
            if (exp.duration_type === 'less_than_day') {
                durationText = exp.duration_hours + 'h';
            } else if (exp.duration_type === 'single_day') {
                durationText = '1 Day';
            } else {
                durationText = (exp.duration_days || '?') + ' Days';
            }

            var regionName = exp.region ? exp.region.name : '';
            var expType = exp.type ? exp.type.charAt(0).toUpperCase() + exp.type.slice(1) : '';

            html += '<div class="exp-card" data-exp-id="' + exp.id + '">';
            html += '<div class="exp-card-image">';
            html += imgHtml;
            if (expType) html += '<span class="exp-card-badge">' + expType + '</span>';
            html += '</div>';
            html += '<div class="exp-card-body">';
            html += '<h3 class="exp-card-title"><a href="/experience/' + exp.slug + '" target="_blank">' + exp.name + '</a></h3>';
            html += '<p class="exp-card-desc">' + (exp.short_description ? exp.short_description.substring(0, 120) + (exp.short_description.length > 120 ? '...' : '') : '') + '</p>';
            html += '<div class="exp-card-meta">';
            if (regionName) html += '<span class="exp-meta-item"><i class="bi bi-geo-alt"></i> ' + regionName + '</span>';
            html += '<span class="exp-meta-item"><i class="bi bi-clock"></i> ' + durationText + '</span>';
            html += '</div>';
            html += '<div class="wishlist-price-row">';
            html += '<div class="wishlist-price-left">';
            if (exp.base_cost_per_person > 0) {
                html += '<span class="exp-price-amount">' + fmtCurrency(exp.base_cost_per_person, exp.price_currency || 'INR') + '</span>';
                html += '<span class="exp-price-label">/ person</span>';
            }
            html += '</div>';
            html += '<div class="wishlist-price-actions">';
            html += '<a href="/experience/' + exp.slug + '" target="_blank" class="wishlist-action-icon" title="View Details"><i class="bi bi-eye"></i></a>';
            html += '<button class="wishlist-action-icon wishlist-action-remove btn-remove-wishlist" data-exp-id="' + exp.id + '" title="Remove from Wishlist"><i class="bi bi-trash3"></i></button>';
            html += '</div>';
            html += '</div>';
            html += '</div>';
            html += '</div>';
        });
        html += '</div>';
        jQuery('#wishlistContainer').html(html);
    }

    // Remove from wishlist
    jQuery(document).on('click', '.btn-remove-wishlist', function(e) {
        e.stopPropagation();
        var btn = jQuery(this);
        var expId = btn.data('exp-id');
        var card = btn.closest('.exp-card');

        ajaxPost({ prefer_experience: 1, experience_id: expId }, function(resp) {
            card.fadeOut(300, function() {
                jQuery(this).remove();
                if (jQuery('#wishlistContainer .exp-card').length === 0) {
                    renderEmpty();
                }
            });
            showAlert('Removed from wishlist.', 'success');
        });
    });

    // Add to journey (kept for backward compat)
    jQuery(document).on('click', '.btn-add-exp', function(e) {
        e.stopPropagation();
        var btn = jQuery(this);
        var expId = btn.data('exp-id');
        var expName = btn.data('exp-name');
        btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm"></span>');

        ajaxPost({ add_experience_to_trip: 1, experience_id: expId }, function(resp) {
            btn.prop('disabled', false).html('<i class="bi bi-check-lg"></i> Added!');
            showAlert(expName + ' added to your journey!', 'success');
            setTimeout(function() {
                btn.html('<i class="bi bi-plus-lg"></i> Add');
            }, 2000);
        }, function() {
            btn.prop('disabled', false).html('<i class="bi bi-plus-lg"></i> Add');
        });
    });
});
</script>
@endsection
