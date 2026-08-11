<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Authorize MCP client</title>
    <style>
        :root { color-scheme: light dark; font-family: system-ui, sans-serif; }
        body { display:grid; min-height:100vh; margin:0; place-items:center; background:#111827; color:#f9fafb; }
        main { width:min(30rem, calc(100% - 2rem)); box-sizing:border-box; padding:2rem; border:1px solid #374151; border-radius:1rem; background:#1f2937; }
        h1 { margin-top:0; font-size:1.4rem; }
        p { line-height:1.5; color:#d1d5db; }
        code { overflow-wrap:anywhere; }
        form { display:flex; gap:.75rem; margin-top:1.5rem; }
        button { flex:1; padding:.75rem 1rem; border:0; border-radius:.5rem; font-weight:700; cursor:pointer; }
        .approve { background:#7c3aed; color:white; }
    </style>
</head>
<body>
<main>
    <h1>Connect {{ $authorization->client->name }}?</h1>
    <p>The client will act as your Polymorph user on this MCP gateway:</p>
    <p><code>{{ $authorization->resource }}</code></p>
    <p>After approval, the browser returns to:</p>
    <p><code>{{ $authorization->redirectUri }}</code></p>
    <p>Your existing Polymorph permissions still limit which tools and resources it can use.</p>
    <form method="post" action="/oauth/authorize">
        @foreach ([
            'response_type' => 'code',
            'client_id' => $authorization->client->id,
            'redirect_uri' => $authorization->redirectUri,
            'resource' => $authorization->resource,
            'scope' => $authorization->scope,
            'code_challenge' => $authorization->codeChallenge,
            'code_challenge_method' => 'S256',
            'state' => $authorization->state,
        ] as $name => $value)
            @if ($value !== null)
                <input type="hidden" name="{{ $name }}" value="{{ $value }}">
            @endif
        @endforeach
        <button type="submit" name="decision" value="deny">Cancel</button>
        <button class="approve" type="submit" name="decision" value="approve">Authorize</button>
    </form>
</main>
</body>
</html>
