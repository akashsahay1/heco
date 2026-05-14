// HECO Portal - Main JS
jQuery(function ($) {
    // Apply data-bg as a background-image so blade files can keep image URLs
    // on the element (e.g. `<div data-bg="/path/to/img.jpg">`) without writing
    // an inline style= attribute (Rule 2: no inline CSS).
    $('[data-bg]').each(function () {
        var url = $(this).data('bg');
        if (url) $(this).css('background-image', "url('" + url + "')");
    });
});
