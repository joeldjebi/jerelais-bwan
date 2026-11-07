<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>JE RELAIS — Swagger de l’API mobile</title>
    <link rel="stylesheet" href="https://unpkg.com/swagger-ui-dist@5.17.14/swagger-ui.css">
    <style>
        body { margin: 0; background: #FAF7F1; font-family: -apple-system, "Helvetica Neue", Arial, sans-serif; }
        .jr-bar { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 12px; padding: 14px 24px; background: #15171C; color: #FAF7F1; }
        .jr-brand { display: flex; align-items: center; gap: 10px; font-weight: 800; letter-spacing: -.01em; font-size: 17px; }
        .jr-brand span:first-child { color: #F28C28; }
        .jr-links { display: flex; gap: 18px; font-size: 14px; }
        .jr-links a { color: #FAF7F1; opacity: .85; text-decoration: none; }
        .jr-links a:hover { opacity: 1; text-decoration: underline; }
        .swagger-ui .topbar { display: none; }
        .swagger-ui .info { margin: 32px 0 16px; }
        .swagger-ui .info .title { color: #15171C; }
        .swagger-ui .btn.authorize { border-color: #C8610C; color: #C8610C; }
        .swagger-ui .btn.authorize svg { fill: #C8610C; }
        .swagger-ui .opblock-tag { font-size: 20px; }
    </style>
</head>
<body>
    <header class="jr-bar">
        <div class="jr-brand"><span>JE</span><span>RELAIS · API mobile v1</span></div>
        <nav class="jr-links">
            <a href="{{ url('docs/api') }}">Vue détaillée</a>
            <a href="{{ $specUrl }}" download="jerelais-openapi.json">Télécharger le fichier OpenAPI</a>
        </nav>
    </header>
    <div id="swagger-ui"></div>

    <script src="https://unpkg.com/swagger-ui-dist@5.17.14/swagger-ui-bundle.js" crossorigin></script>
    <script>
        window.ui = SwaggerUIBundle({
            url: @json($specUrl),
            dom_id: '#swagger-ui',
            deepLinking: true,
            persistAuthorization: true,
            displayRequestDuration: true,
            docExpansion: 'list',
            defaultModelsExpandDepth: 1,
            defaultModelExpandDepth: 3,
            filter: true,
            tryItOutEnabled: false,
            requestInterceptor: (request) => {
                request.headers['Accept'] = 'application/json';
                return request;
            },
        });
    </script>
</body>
</html>
