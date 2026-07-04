<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Welcome to Kyqo</title>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #0f0f0f;
            color: #e5e7eb;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .container {
            text-align: center;
            padding: 2rem;
            max-width: 600px;
        }
        .logo {
            font-size: 4rem;
            font-weight: 800;
            letter-spacing: -2px;
            background: linear-gradient(135deg, #6366f1, #a855f7, #ec4899);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 0.5rem;
        }
        .tagline {
            font-size: 1.1rem;
            color: #9ca3af;
            margin-bottom: 2.5rem;
        }
        .version {
            display: inline-block;
            background: #1f1f1f;
            border: 1px solid #2d2d2d;
            border-radius: 9999px;
            padding: 0.3rem 1rem;
            font-size: 0.8rem;
            color: #6b7280;
            margin-bottom: 2rem;
        }
        .links {
            display: flex;
            gap: 1rem;
            justify-content: center;
            flex-wrap: wrap;
        }
        .link {
            padding: 0.6rem 1.4rem;
            border-radius: 8px;
            text-decoration: none;
            font-size: 0.9rem;
            font-weight: 500;
            transition: opacity 0.15s;
        }
        .link:hover { opacity: 0.8; }
        .link-primary {
            background: linear-gradient(135deg, #6366f1, #a855f7);
            color: #fff;
        }
        .link-secondary {
            background: #1f1f1f;
            border: 1px solid #2d2d2d;
            color: #d1d5db;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="logo">Kyqo</div>
        <p class="tagline">The only framework you'll ever need.</p>
        <span class="version">v<?= $engine->e(\Kyqo\Core\Application::VERSION) ?></span>
        <div class="links">
            <a class="link link-primary" href="https://github.com/CantinP/kyqo">GitHub</a>
            <a class="link link-secondary" href="https://github.com/CantinP/kyqo/blob/main/README.md">Documentation</a>
        </div>
    </div>
</body>
</html>
