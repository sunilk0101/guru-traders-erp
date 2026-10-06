<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Server Error - {{ config('app.name', 'Guru Traders ERP') }}</title>
    <style>
        body { font-family: system-ui, sans-serif; background: #f4f6fb; color: #1f2430; margin: 0; min-height: 100vh; display: flex; align-items: center; justify-content: center; }
        .card { background: #fff; border-radius: 12px; padding: 2rem 2.25rem; max-width: 28rem; box-shadow: 0 8px 24px rgba(15, 23, 42, .08); }
        h1 { font-size: 1.25rem; margin: 0 0 .5rem; }
        p { color: #5b6472; margin: 0 0 1.25rem; line-height: 1.45; }
        a { display: inline-block; background: #1d4ed8; color: #fff; text-decoration: none; border-radius: 8px; padding: .55rem 1rem; font-weight: 600; }
    </style>
</head>
<body>
    <div class="card">
        <h1>Something went wrong</h1>
        <p>This page could not be loaded. Go back to the dashboard and try again, or tell an admin if it keeps happening.</p>
        <a href="{{ url('/dashboard') }}">Back to dashboard</a>
    </div>
</body>
</html>
