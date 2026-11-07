<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>JE RELAIS — Swagger de l’API mobile v1</title>
    <link rel="stylesheet" href="https://unpkg.com/swagger-ui-dist@5.17.14/swagger-ui.css">
    <style>
        body { margin: 0; background: #FAF7F1; font-family: -apple-system, "Helvetica Neue", Arial, sans-serif; }
        .jr-bar { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 12px; padding: 14px 24px; background: #15171C; color: #FAF7F1; }
        .jr-brand { font-weight: 800; letter-spacing: -.01em; font-size: 17px; }
        .jr-brand span { color: #F28C28; }
        .jr-note { font-size: 13px; opacity: .8; }
        .swagger-ui .topbar { display: none; }
        .swagger-ui .info { margin: 32px 0 16px; }
        .swagger-ui .btn.authorize { border-color: #C8610C; color: #C8610C; }
        .swagger-ui .btn.authorize svg { fill: #C8610C; }
        .swagger-ui .opblock-tag { font-size: 20px; }
    </style>
</head>
<body>
    <header class="jr-bar">
        <div class="jr-brand"><span>JE</span> RELAIS · API mobile v1</div>
        <div class="jr-note">Généré le {{ now()->timezone('Africa/Abidjan')->format('d/m/Y à H:i') }} · fichier autonome, à ouvrir dans un navigateur</div>
    </header>
    <div id="swagger-ui"></div>

    <script src="https://unpkg.com/swagger-ui-dist@5.17.14/swagger-ui-bundle.js" crossorigin></script>
    <script>
        window.ui = SwaggerUIBundle({
            spec: {!! $spec !!},
            dom_id: '#swagger-ui',
            deepLinking: true,
            persistAuthorization: true,
            displayRequestDuration: true,
            docExpansion: 'list',
            defaultModelsExpandDepth: 1,
            defaultModelExpandDepth: 3,
            filter: true,
            requestInterceptor: (request) => {
                request.headers['Accept'] = 'application/json';
                return request;
            },
        });
    </script>
</body>
</html>
