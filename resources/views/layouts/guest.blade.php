<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fontsource/source-sans-3@5.0.12/index.css" />
    <title>{{ config('app.name', 'Guru Traders ERP') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        /* M-10: this login page is a fixed light design with no dark-mode
           variant of its own — but without an explicit color-scheme, a
           browser running with the OS/browser in dark mode still paints its
           OWN native chrome (autofill panel, and specifically the built-in
           password-reveal control) using dark colors, which showed up as a
           dark patch behind the password field while every other input
           stayed on our own light background. Declaring the page light
           tells the browser to keep native form-control chrome light too.
           Also hides Chrome/Edge's own built-in reveal icon so it doesn't
           sit on top of this page's own eye button. */
        :root { color-scheme: light; }
        input[type="password"]::-ms-reveal,
        input[type="password"]::-ms-clear { display: none; }
        input[type="password"]::-webkit-textfield-decoration-container { color-scheme: light; }
        body.modern-login {
            background-color: #f4f7f6;
            margin: 0;
            padding: 0;
            font-family: 'Source Sans 3', sans-serif;
        }
        .full-height {
            min-height: 100vh;
        }
        .bg-glass {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            box-shadow: 0 15px 35px rgba(0,0,0,0.05);
            border-radius: 20px;
        }
        .auth-container {
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            background-color: #f1f5f9;
            background-image: radial-gradient(#cbd5e1 1px, transparent 1px);
            background-size: 24px 24px;
            padding: 2rem;
        }
        .auth-card {
            width: 100%;
            max-width: 1100px;
            display: flex;
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.15), 0 10px 20px rgba(0,0,0,0.05);
            background: #fff;
            border: 1px solid rgba(255, 255, 255, 0.5);
        }
        .auth-image {
            width: 50%;
            background: linear-gradient(135deg, #0f172a 0%, #1e3a8a 100%);
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            padding: 4rem;
            color: #ffffff;
            position: relative;
            overflow: hidden;
        }
        .auth-image::before {
            content: "";
            position: absolute;
            top: 0; left: 0; right: 0; bottom: 0;
            background: url('https://images.unsplash.com/photo-1611974789855-9c2a0a7236a3?q=80&w=2070&auto=format&fit=crop') center center / cover;
            opacity: 0.15;
            mix-blend-mode: color-dodge;
        }
        .auth-image::after {
            content: "";
            position: absolute;
            top: 0; left: 0; right: 0; bottom: 0;
            background: linear-gradient(to top, rgba(15,23,42,0.9), transparent);
            z-index: 1;
        }
        .auth-image h1 {
            font-size: 2.5rem;
            font-weight: 800;
            margin-bottom: 1.25rem;
            z-index: 2;
            color: #ffffff;
            text-align: center;
            letter-spacing: -0.5px;
            text-shadow: 0 4px 15px rgba(0,0,0,0.3);
        }
        .auth-image p {
            font-size: 1.15rem;
            text-align: center;
            color: #94a3b8;
            line-height: 1.6;
            z-index: 2;
            font-weight: 400;
        }
        .auth-form {
            width: 50%;
            padding: 3.5rem 4rem;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }
        .auth-brand {
            font-size: 2rem;
            font-weight: 800;
            color: #2b3445;
            margin-bottom: 0.5rem;
        }
        .form-control {
            padding: 0.85rem 1.25rem;
            border-radius: 10px;
            border: 1px solid #e2e8f0;
            background-color: #f8fafc;
            color: #334155;
            font-size: 0.95rem;
        }
        .form-control:focus {
            background-color: #fff;
            border-color: #3b82f6;
            box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.1);
            outline: none;
        }
        .input-group-text {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            color: #64748b;
        }
        /* Leading icon addon: rounded on the left, seamless into the field. */
        .input-group > .input-group-text:first-child {
            border-right: none;
            border-radius: 10px 0 0 10px;
        }
        /* Trailing eye button: rounded on the right. */
        .input-group > .input-group-text:last-child {
            border-left: none;
            border-radius: 0 10px 10px 0;
        }
        /* A field flanked by addons owns neither side border. */
        .input-group > .input-group-text:first-child + .form-control {
            border-left: none;
        }
        .input-group > .form-control:has(+ .input-group-text) {
            border-right: none;
        }
        /* Bootstrap's focus ring is drawn per-element, so a focused field would
           highlight only its own middle segment. Lift the ring to the group. */
        .input-group:focus-within > .input-group-text,
        .input-group:focus-within > .form-control {
            border-color: #3b82f6;
            background-color: #fff;
        }
        .input-group:focus-within {
            border-radius: 10px;
            box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.1);
        }
        .input-group > .form-control:focus {
            box-shadow: none;
        }
        .toggle-password {
            cursor: pointer;
            transition: color 0.15s ease;
        }
        .toggle-password:hover {
            color: #2563eb;
        }
        .btn-modern {
            padding: 0.85rem 1.5rem;
            font-weight: 600;
            border-radius: 10px;
            letter-spacing: 0.3px;
            transition: all 0.3s ease;
            background-color: #2563eb;
            color: #fff;
            border: none;
        }
        .btn-modern:hover {
            transform: translateY(-2px);
            background-color: #1d4ed8;
            color: #fff;
            box-shadow: 0 8px 20px rgba(37, 99, 235, 0.25);
        }
        @media (max-width: 768px) {
            .auth-image { display: none; }
            .auth-form { width: 100%; padding: 2.5rem; }
        }
    </style>
</head>
<body class="modern-login">
    <div class="auth-container">
        <div class="auth-card">
            
            <div class="auth-image">
                <div class="auth-logo" style="z-index:2;margin-bottom:1.25rem;">
                    <x-brand-logo :size="56" />
                </div>
                <h1>Guru Traders ERP</h1>
                <p>Enterprise Resource Planning made simple. Manage your sales, products, and customers effortlessly.</p>
            </div>

            <div class="auth-form">
                {{ $slot }}
            </div>

        </div>
    </div>
</body>
</html>
