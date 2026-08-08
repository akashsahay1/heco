<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Upload test</title>
</head>
<body>
    {{-- Deliberately unstyled and standalone: this page exists to find out
         whether a file reaches PHP, and the fewer things it loads, the fewer
         things can be blamed for the answer. --}}
    <h1>Upload test</h1>

    <p>Pick an image and send it. The reply below is whatever reached PHP.</p>

    <form id="uploadTestForm">
        <input type="file" name="document" id="documentInput" accept="image/*" required>
        <button type="submit">Send</button>
    </form>

    <pre id="result"></pre>

    <script src="{{ url('js/jquery-3.7.1.min.js') }}"></script>
    <script>
    jQuery(function ($) {
        // Posted to the API route rather than back to this page, so the request
        // takes the same shape the Become a Partner form's upload takes.
        var endpoint = '{{ url('/api/upload-test') }}';

        $('#uploadTestForm').on('submit', function (e) {
            e.preventDefault();

            var file = $('#documentInput')[0].files[0];
            if (!file) return;

            var data = new FormData();
            data.append('document', file);

            $('#result').text('Sending ' + file.name + ' (' + file.size + ' bytes)…');

            $.ajax({
                url: endpoint,
                type: 'POST',
                data: data,
                processData: false,
                contentType: false,
                // The failure being chased is an HTML error page from the
                // server, so the reply is read as text and shown as it came —
                // parsing it as JSON would hide exactly what we came to see.
                dataType: 'text',
                skipGlobalError: true
            }).done(function (response) {
                $('#result').text(response);
            }).fail(function (xhr) {
                $('#result').text(
                    'HTTP ' + xhr.status + ' ' + xhr.statusText + '\n\n' + xhr.responseText
                );
            });
        });
    });
    </script>
</body>
</html>
