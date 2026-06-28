@props([
    'code',
    'title',
    'message',
    'primaryLabel' => 'Zur Startseite',
    'primaryUrl' => url('/'),
    'secondaryLabel' => null,
    'secondaryUrl' => null,
])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">

    <title>{{ $code }} – {{ $title }} | {{ config('app.name', 'NEAREON') }}</title>

    <style>
        :root {
            color-scheme: dark;
            --background: #030318;
            --card: rgba(10, 12, 34, 0.88);
            --card-border: rgba(148, 163, 184, 0.18);
            --text: #ffffff;
            --muted: rgba(226, 232, 240, 0.72);
            --purple: #5a45c8;
            --purple-hover: #6d58e8;
            --green: #38f6a3;
            --green-soft: rgba(56, 246, 163, 0.16);
            --shadow: rgba(0, 0, 0, 0.42);
        }

        * {
            box-sizing: border-box;
        }

        body {
            min-height: 100vh;
            margin: 0;
            background:
                radial-gradient(circle at 18% 18%, rgba(90, 69, 200, 0.24), transparent 32rem),
                radial-gradient(circle at 82% 12%, rgba(56, 246, 163, 0.12), transparent 28rem),
                linear-gradient(135deg, #030318 0%, #07091f 52%, #02020f 100%);
            color: var(--text);
            font-family:
                Instrument Sans,
                ui-sans-serif,
                system-ui,
                -apple-system,
                BlinkMacSystemFont,
                "Segoe UI",
                sans-serif;
        }

        .page {
            display: flex;
            min-height: 100vh;
            width: 100%;
            align-items: center;
            justify-content: center;
            overflow-x: hidden;
            padding: 1.5rem;
        }

        .shell {
            position: relative;
            width: min(100%, 44rem);
            overflow: hidden;
            border: 1px solid var(--card-border);
            border-radius: 1.5rem;
            background: var(--card);
            box-shadow: 0 1.5rem 4rem var(--shadow);
            backdrop-filter: blur(18px);
        }

        .shell::before {
            position: absolute;
            inset: 0;
            pointer-events: none;
            content: "";
            background:
                linear-gradient(90deg, rgba(56, 246, 163, 0.24), transparent 26%),
                radial-gradient(circle at top right, rgba(90, 69, 200, 0.32), transparent 20rem);
            opacity: 0.9;
        }

        .content {
            position: relative;
            display: grid;
            gap: 1.5rem;
            padding: clamp(1.5rem, 5vw, 3rem);
        }

        .brand {
            display: inline-flex;
            width: fit-content;
            max-width: 100%;
            align-items: center;
            gap: 0.6rem;
            border: 1px solid rgba(56, 246, 163, 0.28);
            border-radius: 999px;
            background: var(--green-soft);
            padding: 0.45rem 0.8rem;
            color: var(--green);
            font-size: 0.75rem;
            font-weight: 700;
            letter-spacing: 0.12em;
            text-transform: uppercase;
        }

        .dot {
            width: 0.55rem;
            height: 0.55rem;
            flex: none;
            border-radius: 999px;
            background: var(--green);
            box-shadow: 0 0 1rem rgba(56, 246, 163, 0.72);
        }

        .code {
            font-size: clamp(4.5rem, 18vw, 8.5rem);
            line-height: 0.9;
            font-weight: 800;
            letter-spacing: -0.08em;
            color: transparent;
            background: linear-gradient(135deg, #ffffff 0%, #c9c1ff 45%, var(--green) 100%);
            -webkit-background-clip: text;
            background-clip: text;
        }

        h1 {
            max-width: 34rem;
            margin: 0;
            font-size: clamp(1.75rem, 5vw, 3rem);
            line-height: 1.05;
            letter-spacing: -0.04em;
        }

        p {
            max-width: 36rem;
            margin: 0;
            color: var(--muted);
            font-size: clamp(1rem, 2.6vw, 1.125rem);
            line-height: 1.7;
        }

        .actions {
            display: flex;
            flex-wrap: wrap;
            gap: 0.75rem;
            padding-top: 0.25rem;
        }

        .button {
            display: inline-flex;
            min-height: 2.75rem;
            max-width: 100%;
            align-items: center;
            justify-content: center;
            border-radius: 0.8rem;
            padding: 0.75rem 1rem;
            font-size: 0.95rem;
            font-weight: 700;
            text-decoration: none;
            transition: transform 160ms ease, border-color 160ms ease, background-color 160ms ease;
        }

        .button:focus-visible {
            outline: 3px solid rgba(56, 246, 163, 0.48);
            outline-offset: 3px;
        }

        .button:hover {
            transform: translateY(-1px);
        }

        .button-primary {
            border: 1px solid rgba(109, 88, 232, 0.72);
            background: var(--purple);
            color: #ffffff;
        }

        .button-primary:hover {
            background: var(--purple-hover);
        }

        .button-secondary {
            border: 1px solid rgba(148, 163, 184, 0.26);
            background: rgba(255, 255, 255, 0.06);
            color: #ffffff;
        }

        .button-secondary:hover {
            border-color: rgba(56, 246, 163, 0.36);
            background: rgba(255, 255, 255, 0.1);
        }

        @media (max-width: 520px) {
            .page {
                align-items: stretch;
                padding: 1rem;
            }

            .shell {
                align-self: center;
                border-radius: 1.25rem;
            }

            .actions {
                flex-direction: column;
            }

            .button {
                width: 100%;
            }
        }

        @media (prefers-reduced-motion: reduce) {
            .button {
                transition: none;
            }

            .button:hover {
                transform: none;
            }
        }
    </style>
</head>
<body>
    <main class="page">
        <section class="shell" aria-labelledby="error-title">
            <div class="content">
                <div class="brand">
                    <span class="dot" aria-hidden="true"></span>
                    NEAREON
                </div>

                <div class="code" aria-label="Statuscode {{ $code }}">{{ $code }}</div>

                <div>
                    <h1 id="error-title">{{ $title }}</h1>
                    <p>{{ $message }}</p>
                </div>

                <div class="actions" aria-label="Navigation">
                    <a class="button button-primary" href="{{ $primaryUrl }}">
                        {{ $primaryLabel }}
                    </a>

                    @if ($secondaryLabel !== null && $secondaryUrl !== null)
                        <a class="button button-secondary" href="{{ $secondaryUrl }}">
                            {{ $secondaryLabel }}
                        </a>
                    @endif
                </div>
            </div>
        </section>
    </main>
</body>
</html>
