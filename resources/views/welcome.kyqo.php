<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Welcome to Kyqo</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            background: #0f0f0f;
            color: #f8f8f2;
            font-family: 'Segoe UI', system-ui, sans-serif;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
        }
        .hero {
            text-align: center;
            padding: 4rem 2rem;
        }
        .logo {
            font-size: 5rem;
            font-weight: 900;
            background: linear-gradient(135deg, #bd93f9, #ff79c6, #50fa7b);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            letter-spacing: -0.05em;
            margin-bottom: 1.5rem;
        }
        .tagline {
            font-size: 1.25rem;
            color: #6272a4;
            margin-bottom: 3rem;
            max-width: 600px;
        }
        .badges {
            display: flex;
            gap: 0.75rem;
            justify-content: center;
            flex-wrap: wrap;
            margin-bottom: 3rem;
        }
        .badge {
            padding: 0.5rem 1rem;
            border-radius: 9999px;
            font-size: 0.85rem;
            font-weight: 600;
            border: 1px solid;
        }
        .badge-php   { color: #bd93f9; border-color: #bd93f9; }
        .badge-ts    { color: #8be9fd; border-color: #8be9fd; }
        .badge-ui    { color: #ff79c6; border-color: #ff79c6; }
        .badge-db    { color: #50fa7b; border-color: #50fa7b; }
        .badge-queue { color: #ffb86c; border-color: #ffb86c; }
        .version {
            font-size: 0.8rem;
            color: #44475a;
        }
    </style>
</head>
<body>
    <div class="hero">
        <div class="logo">Kyqo</div>
        <p class="tagline">The only framework you'll ever need — full-stack, powerful, cross-language.</p>
        <div class="badges">
            <span class="badge badge-php">PHP 8.3+</span>
            <span class="badge badge-ts">TypeScript</span>
            <span class="badge badge-ui">UI Components</span>
            <span class="badge badge-db">ORM + Migrations</span>
            <span class="badge badge-queue">Queues + Events</span>
        </div>
        <p class="version">Kyqo v0.1.0</p>
    </div>
</body>
</html>
