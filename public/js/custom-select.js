/**
 * HECO Custom Searchable Dropdown
 *
 * Wraps a native <select> in a styled dropdown with:
 *   - Chevron-down arrow indicator
 *   - Optional search input (auto-enabled when there are >5 options)
 *   - Click-to-select with the original <select> kept in sync (form submission unchanged)
 *
 * Usage:
 *   <select class="custom-select" name="country">...options...</select>
 *   jQuery(function() { jQuery('.custom-select').each(function() { buildCustomDropdown(this); }); });
 *
 * Per project coding rules: jQuery only, no vanilla JS.
 */

(function ($) {
    'use strict';

    /**
     * Wrap or rebuild a <select> as a custom searchable dropdown.
     * @param {HTMLSelectElement|jQuery} sel
     * @param {{searchable?: boolean, searchThreshold?: number}} [opts]
     */
    window.buildCustomDropdown = function (sel, opts) {
        opts = opts || {};
        var $sel = $(sel);
        if (!$sel.length || $sel[0].tagName !== 'SELECT') return;

        var threshold = opts.searchThreshold != null ? opts.searchThreshold : 5;
        var searchable = opts.searchable != null
            ? !!opts.searchable
            : $sel.find('option').length > threshold;

        // Already wrapped — refresh the option list (e.g. dynamic options changed)
        if ($sel.closest('.custom-select-wrap').length) {
            var $wrap = $sel.closest('.custom-select-wrap');
            $wrap.toggleClass('has-search', searchable);
            renderOptions($sel, $wrap);
            updateTriggerLabel($sel, $wrap);
            return;
        }

        // First-time wrap
        var $newWrap = $('<div class="custom-select-wrap"></div>');
        if (searchable) $newWrap.addClass('has-search');
        $sel.wrap($newWrap);
        var $w = $sel.parent();

        var $trigger = $(
            '<div class="custom-select-trigger">' +
                '<span class="custom-select-label"></span>' +
                '<i class="bi bi-chevron-down caret"></i>' +
            '</div>'
        );

        var $body = $('<div class="custom-select-body"></div>');

        if (searchable) {
            var $search = $(
                '<div class="custom-select-search">' +
                    '<input type="text" class="custom-select-search-input" placeholder="Search..." autocomplete="off">' +
                '</div>'
            );
            $body.append($search);
            $search.find('input').on('input', function () {
                var q = $(this).val().toLowerCase().trim();
                $body.find('.custom-select-option').each(function () {
                    var $o = $(this);
                    var match = $o.text().toLowerCase().indexOf(q) !== -1;
                    $o.toggleClass('hidden', !match);
                });
                // Show "No results" placeholder if all are hidden
                var visible = $body.find('.custom-select-option:not(.hidden)').length;
                $body.find('.custom-select-empty').toggle(visible === 0);
            });
            // Stop propagation so opening the dropdown doesn't close it on input click
            $search.on('click', function (e) { e.stopPropagation(); });
        }

        var $optList = $('<div class="custom-select-options"></div>');
        $body.append($optList);
        $body.append('<div class="custom-select-empty" style="display:none;">No results found</div>');

        $w.append($trigger).append($body);

        renderOptions($sel, $w);
        updateTriggerLabel($sel, $w);

        // Toggle open
        $trigger.on('click', function (e) {
            e.stopPropagation();
            $('.custom-select-wrap').not($w).removeClass('open');
            $w.toggleClass('open');
            if ($w.hasClass('open') && searchable) {
                // Reset and focus search
                var $input = $w.find('.custom-select-search-input');
                $input.val('').trigger('input').focus();
            }
        });

        // Select an option
        $body.on('click', '.custom-select-option', function (e) {
            e.stopPropagation();
            var val = $(this).attr('data-value');
            $sel.val(val).trigger('change');
            $body.find('.custom-select-option').removeClass('selected');
            $(this).addClass('selected');
            updateTriggerLabel($sel, $w);
            $w.removeClass('open');
        });
    };

    function renderOptions($sel, $wrap) {
        var $optList = $wrap.find('.custom-select-options');
        $optList.empty();
        $sel.find('option').each(function () {
            var $o = $(this);
            var cls = 'custom-select-option' + ($sel.val() == $o.val() ? ' selected' : '');
            $optList.append(
                '<div class="' + cls + '" data-value="' + escapeAttr($o.val()) + '">' +
                    escapeHtml($o.text()) +
                '</div>'
            );
        });
    }

    function updateTriggerLabel($sel, $wrap) {
        var $opt = $sel.find('option:selected');
        var text = $opt.length ? $opt.text() : ($sel.find('option:first').text() || '');
        $wrap.find('.custom-select-label').text(text);
    }

    function escapeHtml(s) {
        return $('<div>').text(s == null ? '' : s).html();
    }
    function escapeAttr(s) {
        return String(s == null ? '' : s).replace(/"/g, '&quot;');
    }

    // Close all open dropdowns on outside click
    $(document).on('click', function () {
        $('.custom-select-wrap').removeClass('open');
    });

    // Auto-init any element with .custom-select on DOM ready
    $(function () {
        $('.custom-select').each(function () {
            buildCustomDropdown(this);
        });
    });
})(jQuery);
