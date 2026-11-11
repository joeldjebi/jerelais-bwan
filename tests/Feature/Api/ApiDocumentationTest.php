<?php

use App\Domain\Access\Enums\Role;
use App\Models\User;
use App\Providers\ApiDocumentationServiceProvider;
use Dedoc\Scramble\Generator;
use Dedoc\Scramble\Scramble;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route as Router;

function openApiDocument(): array
{
    return app(Generator::class)(Scramble::getGeneratorConfig(Scramble::DEFAULT_API));
}

/**
 * @return list<array{method: string, path: string, operation: array<string, mixed>}>
 */
function openApiOperations(array $document): array
{
    $operations = [];

    foreach ($document['paths'] as $path => $methods) {
        foreach ($methods as $method => $operation) {
            if (is_array($operation) && isset($operation['responses'])) {
                $operations[] = ['method' => strtoupper($method), 'path' => $path, 'operation' => $operation];
            }
        }
    }

    return $operations;
}

it('documente toutes les routes de l’API mobile', function () {
    $routes = collect(Router::getRoutes()->getRoutes())
        ->filter(fn (Route $route) => str_starts_with($route->uri(), 'api/v1/') && $route->getName() !== 'api.v1.sms.dlr')
        ->flatMap(fn (Route $route) => collect($route->methods())->reject(fn (string $m) => $m === 'HEAD')->map(fn (string $m) => $m.' /'.substr($route->uri(), strlen('api/v1/'))))
        ->sort()->values()->all();

    $documented = collect(openApiOperations(openApiDocument()))->map(fn (array $op) => $op['method'].' '.$op['path'])->sort()->values()->all();

    expect($documented)->toBe($routes);
});

it('donne à chaque opération un titre, une description, un groupe et une réponse 429', function () {
    $document = openApiDocument();
    $groups = collect($document['tags'])->pluck('name')->all();

    expect($groups)->toBe(['Authentification', 'Mon compte', 'Vérification d’identité', 'Référentiels', 'Candidature', 'Textes juridiques', 'Formation et quiz', 'Relais', 'Médias', 'Appels à relais', 'Messagerie', 'Diffusions']);

    foreach (openApiOperations($document) as ['method' => $method, 'path' => $path, 'operation' => $operation]) {
        $where = "$method $path";

        expect($operation['summary'] ?? '')->not->toBe('', "$where : titre manquant")
            ->and($operation['description'] ?? '')->not->toBe('', "$where : description manquante")
            ->and($operation['tags'] ?? [])->toHaveCount(1)
            ->and(in_array($operation['tags'][0], $groups, true))->toBeTrue("$where : groupe inconnu")
            ->and($operation['responses'])->toHaveKey('429');
    }
});

it('déclare le jeton Bearer et ne l’exige que sur les routes protégées', function () {
    $document = openApiDocument();

    expect($document['components']['securitySchemes']['sanctum'] ?? null)->toMatchArray(['type' => 'http', 'scheme' => 'bearer']);

    $protected = collect(Router::getRoutes()->getRoutes())
        ->filter(fn (Route $route) => str_starts_with($route->uri(), 'api/v1/') && in_array('auth:sanctum', $route->gatherMiddleware(), true))
        ->map(fn (Route $route) => '/'.substr($route->uri(), strlen('api/v1/')))
        ->unique()->values()->all();

    foreach (openApiOperations($document) as ['path' => $path, 'operation' => $operation]) {
        $isPublic = ($operation['security'] ?? null) === [];

        expect($isPublic)->toBe(! in_array($path, $protected, true), "$path : sécurité incohérente");
    }
});

it('documente le corps de chaque étape de candidature et les réponses au quiz comme un objet', function () {
    $document = openApiDocument();

    $step = $document['paths']['/candidature/steps/{step}']['put']['requestBody']['content']['application/json']['schema'];
    $fields = collect($step['anyOf'])->flatMap(fn (array $s) => array_keys($s['properties']))->all();

    expect($fields)->toContain('last_name', 'birth_date', 'commune', 'localite', 'occupation', 'motivation')
        ->and($document['components']['schemas']['StoreQuizAttemptRequest']['properties']['answers']['type'])->toBe('object');
});

it('ouvre le Swagger à un administrateur, avec une politique de sécurité propre aux pages de documentation', function () {
    $this->get(route('docs.swagger'))->assertForbidden();

    $response = $this->actingAs(User::factory()->withRole(Role::Admin)->create())
        ->get(route('docs.swagger'))->assertOk()->assertSee('swagger-ui', false);

    expect($response->headers->get('Content-Security-Policy'))->toContain('https://unpkg.com');
    expect($this->get('/')->headers->get('Content-Security-Policy'))->not->toContain('unpkg.com');
});

it('réserve la documentation aux administrateurs hors local, sauf ouverture explicite', function () {
    app()->detectEnvironment(fn () => 'production');

    expect(Gate::forUser(User::factory()->withRole(Role::Admin)->create())->allows('viewApiDocs'))->toBeTrue()
        ->and(Gate::forUser(User::factory()->withRole(Role::Relais)->create())->allows('viewApiDocs'))->toBeFalse();

    config(['jerelais.api_docs_public' => true]);
    expect(Gate::allows('viewApiDocs'))->toBeTrue();
});

it('intègre le contenu des références dans le Swagger autonome, sans boucler sur un schéma récursif', function () {
    $document = [
        'paths' => ['/quiz/attempts' => ['post' => ['requestBody' => ['content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/Quiz']]]]]]],
        'components' => ['schemas' => [
            'Quiz' => ['type' => 'object', 'properties' => ['answers' => ['type' => 'object']]],
            'Node' => ['type' => 'object', 'properties' => ['child' => ['$ref' => '#/components/schemas/Node']]],
        ]],
    ];

    $inline = ApiDocumentationServiceProvider::inlineReferences($document, $document);

    expect($inline['paths']['/quiz/attempts']['post']['requestBody']['content']['application/json']['schema'])
        ->toBe(['type' => 'object', 'properties' => ['answers' => ['type' => 'object']]])
        ->and($inline['components']['schemas']['Node']['properties']['child']['properties']['child'])->toBe(['$ref' => '#/components/schemas/Node']);
});
