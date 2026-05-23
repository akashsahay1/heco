<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'HECO Portal')</title>
</head>
<body style="margin:0; padding:0; background:#f5f5f0; font-family:'Helvetica Neue', Arial, sans-serif; color:#2d3a2e;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f5f5f0; padding:30px 0;">
        <tr>
            <td align="center">
                <table role="presentation" width="600" cellpadding="0" cellspacing="0" style="background:#ffffff; border-radius:8px; overflow:hidden; box-shadow:0 2px 8px rgba(0,0,0,0.06);">
                    <tr>
                        <td style="background:#F0EEE5; padding:24px 32px; text-align:center;">
                            <img src="{{ isset($message) ? $message->embed(public_path('images/logo/heco-logo-dark.png')) : url('/images/logo/heco-logo-dark.png') }}" alt="HECO Portal" width="160" height="44" style="display:inline-block; width:160px; height:auto; border:0; outline:none; -ms-interpolation-mode:bicubic;">
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:32px;">
                            @yield('content')
                        </td>
                    </tr>
                    <tr>
                        <td style="background:#f0eee5; padding:20px 32px; text-align:center; font-size:12px; color:#7a7a6e;">
                            <p style="margin:0 0 6px;">Himalayan Ecotourism Collective</p>
                            <p style="margin:0;">
                                <a href="mailto:info@himalayanecotourism.com" style="color:#5f8484; text-decoration:none;">info@himalayanecotourism.com</a>
                                &nbsp;&middot;&nbsp;
                                Shimla, Himachal Pradesh, India
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
