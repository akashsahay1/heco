@php
    // 419 = CSRF token expired ("Page Expired"). This can render on either domain,
    // so resolve the right login target from the request host (same rule as
    // bootstrap/app.php's redirectGuestsTo).
    $isAdmin  = request()->getHost() === config('app.admin_domain');
    $loginUrl = $isAdmin ? route('admin.login') : route('login');
    $homeUrl  = $isAdmin ? $loginUrl : url('/');
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Session expired — HECO</title>
    <style>
        :root { --teal: #79a09f; --teal-dark: #496767; --ink: #2f3b3a; --muted: #6b7c7b; }
        * { box-sizing: border-box; }
        body {
            margin: 0; min-height: 100vh; display: flex; align-items: center; justify-content: center;
            background: #f5f8f8; color: var(--ink); padding: 24px;
            font-family: system-ui, -apple-system, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
        }
        .card {
            background: #fff; border-radius: 16px; box-shadow: 0 8px 30px rgba(73,103,103,.12);
            max-width: 460px; width: 100%; padding: 40px 32px; text-align: center;
        }
        .icon {
            width: 64px; height: 64px; margin: 0 auto 20px; border-radius: 50%;
            background: rgba(121,160,159,.15); display: flex; align-items: center; justify-content: center;
        }
        .icon svg { width: 32px; height: 32px; stroke: var(--teal); fill: none; stroke-width: 2; stroke-linecap: round; stroke-linejoin: round; }
        h1 { font-size: 1.4rem; margin: 0 0 10px; color: var(--teal-dark); }
        p { margin: 0 0 24px; color: var(--muted); line-height: 1.55; font-size: .98rem; }
        .actions { display: flex; flex-direction: column; gap: 10px; }
        .btn {
            display: inline-block; padding: 12px 18px; border-radius: 10px; text-decoration: none;
            font-weight: 600; font-size: .95rem; cursor: pointer; border: 1px solid transparent;
        }
        .btn-primary { background: var(--teal); color: #fff; }
        .btn-primary:hover { background: var(--teal-dark); }
        .btn-link { background: transparent; color: var(--teal-dark); border-color: rgba(73,103,103,.25); }
        .btn-link:hover { background: rgba(121,160,159,.08); }
        .code { margin-top: 22px; font-size: .8rem; color: #aab6b5; letter-spacing: .04em; }
    </style>
</head>
<body>
    <div class="card">
        <div class="icon" aria-hidden="true">
            <svg viewBox="0 0 24 24"><path d="M23 4v6h-6"/><path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"/></svg>
        </div>
        <h1>Session expired</h1>
        <p>
            For your security, this page expired after a period of inactivity.
            Nothing is broken — just refresh and try again, or log in again to get
            a fresh session, then retry your last action.
        </p>
        <div class="actions">
            <a href="{{ $homeUrl }}" class="btn btn-primary"
               onclick="if (history.length > 1) { history.back(); return false; }">
                Refresh &amp; retry
            </a>
            <a href="{{ $loginUrl }}" class="btn btn-link">Log in again</a>
        </div>
        <div class="code">419 · PAGE EXPIRED</div>
    </div>
</body>
</html>
