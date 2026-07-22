<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="REDL is a GPS ride tracker built for motorbikes. Track every apex.">

    <title>REDL — Track every apex.</title>

    <style>
        @font-face {
            font-family: 'Inter';
            src: url('{{ asset('fonts/Inter-Variable.ttf') }}') format('truetype-variations');
            font-weight: 100 900;
            font-display: swap;
        }

        :root {
            --base: #1A1A1A;
            --base-alt: #F2EFEA;
            --accent: #8E2430;
            --accent-tint: #E6C9CC;
            --text-muted: #5C5750;
            --text-secondary: #B3ABA2;
            --surface-1: #161514;
            --surface-2: #221F1C;
            --surface-3: #2A2825;
            --border: #3A3733;
        }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            background: var(--base);
            color: var(--base-alt);
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        main {
            width: 100%;
            max-width: 640px;
            padding: 96px 24px 48px;
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
            flex: 1;
        }

        .mark { width: 56px; height: 56px; }

        h1 {
            font-weight: 800;
            font-size: 28px;
            letter-spacing: 5px;
            margin: 20px 0 8px;
        }

        .tagline {
            font-weight: 400;
            font-size: 16px;
            color: var(--text-secondary);
            margin: 0 0 40px;
        }

        p.lede {
            font-weight: 400;
            font-size: 15px;
            line-height: 1.6;
            color: var(--base-alt);
            max-width: 480px;
            margin: 0 0 48px;
        }

        .card {
            width: 100%;
            background: var(--surface-2);
            border-radius: 6px;
            padding: 28px 24px;
            margin-bottom: 20px;
        }

        .eyebrow {
            font-weight: 700;
            font-size: 10px;
            letter-spacing: 1.6px;
            text-transform: uppercase;
            color: var(--text-muted);
            margin: 0 0 10px;
        }

        .card h2 {
            font-weight: 700;
            font-size: 18px;
            margin: 0 0 10px;
        }

        .card p {
            font-weight: 400;
            font-size: 14px;
            line-height: 1.6;
            color: var(--text-secondary);
            margin: 0 0 20px;
        }

        .btn {
            display: inline-block;
            background: var(--accent);
            color: var(--base-alt);
            text-decoration: none;
            font-weight: 700;
            font-size: 13px;
            padding: 12px 24px;
            border-radius: 4px;
        }

        .btn:hover { opacity: 0.9; }

        .status-row {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            font-size: 12px;
            color: var(--text-muted);
            margin-top: 8px;
        }

        .dot {
            width: 7px;
            height: 7px;
            border-radius: 50%;
            background: #6FBF73;
            display: inline-block;
        }

        footer {
            width: 100%;
            max-width: 640px;
            padding: 24px;
            text-align: center;
            font-size: 11px;
            color: var(--text-muted);
        }

        footer a { color: var(--text-secondary); text-decoration: none; }
        footer a:hover { text-decoration: underline; }
    </style>
</head>
<body>
    <main>
        <svg class="mark" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100" fill="none">
            <path d="M18 80 C18 40 40 16 78 22" stroke="#8E2430" stroke-width="7" stroke-linecap="round"></path>
            <line x1="26.7" y1="26.7" x2="40.8" y2="40.8" stroke="#8E2430" stroke-width="7" stroke-linecap="round"></line>
        </svg>

        <h1>REDL</h1>
        <p class="tagline">Track every apex.</p>

        <p class="lede">
            REDL is a GPS ride tracker built specifically for motorbikes.
            Record every ride, draw your route on OpenStreetMap, and grow
            your garage — no Google Maps, no paid map service, just the
            open road.
        </p>

        <div class="card">
            <p class="eyebrow">Coming soon</p>
            <h2>REDL for Android</h2>
            <p>The app is currently in active development. Early builds are
                published as they're ready.</p>
            <a class="btn" href="https://github.com/jimbes/Motor-share/releases" target="_blank" rel="noopener">
                See early builds
            </a>
        </div>

        <div class="status-row">
            <span class="dot"></span>
            <span>API online</span>
        </div>
    </main>

    <footer>
        &copy; {{ date('Y') }} REDL — this is the REDL API server.
    </footer>
</body>
</html>
