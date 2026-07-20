<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>404 - Page Not Found</title>
    <style>
        :root { color-scheme: light dark; }
        * { box-sizing: border-box; }
        body.error-page {
            margin: 0;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: system-ui, -apple-system, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            background: #0f1115;
            color: #e6e6e6;
        }
        .error-page .container { text-align: center; padding: 2rem; max-width: 32rem; }
        .error-page h1 { font-size: 5rem; margin: 0; line-height: 1; font-weight: 700; }
        .error-page h2 { font-size: 1.5rem; margin: .5rem 0 1rem; font-weight: 600; }
        .error-page p { margin: .5rem 0; color: #a8a8a8; }
        .error-page code { background: rgba(255,255,255,.08); padding: .15rem .4rem; border-radius: .25rem; }
        .error-page a { color: #6ea8fe; text-decoration: none; }
        .error-page a:hover { text-decoration: underline; }
    </style>
</head>
<body class="error-page">
    <div class="container">
        <h1>404</h1>
        <h2>Page Not Found</h2>
        <p>The requested path <code>{{ $path ?? request()->path() }}</code> was not found.</p>
        <p><a href="/">Go to homepage</a></p>
    </div>
</body>
</html>

