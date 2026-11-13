<?php

namespace App\Providers;

use App\Domain\Access\Enums\Role;
use App\Domain\Identity\Models\IdentityDocumentType;
use App\Models\User;
use Dedoc\Scramble\Http\Middleware\RestrictedDocsAccess;
use Dedoc\Scramble\Scramble;
use Dedoc\Scramble\Support\Generator\Combined\AnyOf;
use Dedoc\Scramble\Support\Generator\Header;
use Dedoc\Scramble\Support\Generator\MediaType;
use Dedoc\Scramble\Support\Generator\MissingValue;
use Dedoc\Scramble\Support\Generator\OpenApi;
use Dedoc\Scramble\Support\Generator\Operation;
use Dedoc\Scramble\Support\Generator\Parameter;
use Dedoc\Scramble\Support\Generator\RequestBodyObject;
use Dedoc\Scramble\Support\Generator\Response;
use Dedoc\Scramble\Support\Generator\Schema;
use Dedoc\Scramble\Support\Generator\SecurityScheme;
use Dedoc\Scramble\Support\Generator\Types\ArrayType;
use Dedoc\Scramble\Support\Generator\Types\IntegerType;
use Dedoc\Scramble\Support\Generator\Types\ObjectType;
use Dedoc\Scramble\Support\Generator\Types\StringType;
use Dedoc\Scramble\Support\Generator\Types\Type;
use Dedoc\Scramble\Support\RouteInfo;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

/**
 * Documentation OpenAPI (Swagger) de l'API mobile, générée depuis le code par Scramble puis complétée :
 * schéma d'authentification Bearer, guide d'intégration, sécurité route par route, limites (429),
 * dictionnaire des champs (descriptions et exemples) et page Swagger UI.
 */
class ApiDocumentationServiceProvider extends ServiceProvider
{
    /**
     * Limites d'appels propres à certaines routes, en plus de la limite générale de l'API.
     *
     * @var array<string, string>
     */
    private const LIMITS = [
        'throttle:otp-request' => '3 demandes de code par numéro et par 15 minutes (quota commun au site et à l’application), 10 par adresse IP.',
        'throttle:otp-verify' => '10 vérifications par minute et par adresse IP. Chaque code accepte 5 essais.',
        'throttle:password-login' => '5 tentatives par minute pour un même numéro depuis une même adresse IP.',
        'throttle:quiz' => '10 tentatives de quiz par heure.',
        'throttle:60,1' => '60 appels par minute.',
        'throttle:120,1' => '120 appels par minute.',
        'throttle:30,1' => '30 appels par minute.',
        'throttle:5,60' => '5 exports par heure.',
    ];

    /** @var array{fields: array<string, array{0: string, 1?: mixed}>, enums: array<string, string>, parameters: array<string, string>, responses: array<string, string>}|null */
    private ?array $dictionary = null;

    public function boot(): void
    {
        // Documentation ouverte en local ; ailleurs, aux administrateurs ou si API_DOCS_PUBLIC=true (préproduction, équipe mobile).
        Gate::define('viewApiDocs', fn (?User $user = null): bool => $this->app->isLocal()
            || (bool) config('jerelais.api_docs_public')
            || $user?->hasRole(Role::Admin->value) === true);

        Scramble::configure()
            ->withOperationTransformers(function (Operation $operation, RouteInfo $routeInfo): void {
                $middleware = array_values(array_filter($routeInfo->route->gatherMiddleware(), 'is_string'));

                // La sécurité suit les routes réelles : seules celles protégées par Sanctum exigent un jeton.
                if (! in_array('auth:sanctum', $middleware, true)) {
                    $operation->security = [];
                }

                if ($routeInfo->route->getName() === 'api.v1.candidature.steps.update') {
                    $operation->addRequestBodyObject($this->candidatureStepBody());
                }

                if (in_array($routeInfo->route->getName(), ['api.v1.identity.store', 'api.v1.identity.update'], true)) {
                    $operation->addRequestBodyObject($this->identityBody($routeInfo->route->getName() === 'api.v1.identity.store'));
                }

                $operation->addResponse($this->tooManyRequests($middleware));
            })
            ->withDocumentTransformers(function (OpenApi $openApi): void {
                $openApi->secure(
                    SecurityScheme::http('bearer', 'Sanctum')
                        ->as('sanctum')
                        ->setDescription('Jeton obtenu par `POST /auth/verify` (code SMS) ou `POST /auth/login` (mot de passe), à envoyer dans l’en-tête `Authorization: Bearer <jeton>`. Un jeton par appareil, valable 60 jours, révocable à distance.')
                );

                $guide = resource_path('docs/api-guide.md');
                $openApi->info->description = File::exists($guide) ? File::get($guide) : $openApi->info->description;

                $this->fixQuizAnswers($openApi);
                $this->describe($openApi);
            });

        Route::middleware(['web', RestrictedDocsAccess::class])->get('docs/swagger', fn () => view('docs.swagger', [
            'specUrl' => url('docs/api.json'),
        ]))->name('docs.swagger');
    }

    /**
     * Remplace récursivement les références internes (« #/components/... ») par leur contenu, pour le Swagger autonome :
     * sans serveur, Swagger UI ne peut pas relire le document pour suivre les « $ref ». Une référence déjà en cours
     * de résolution (schéma récursif) est laissée telle quelle.
     *
     * @param  array<array-key, mixed>  $root
     * @param  list<string>  $resolving
     */
    public static function inlineReferences(mixed $node, array $root, array $resolving = []): mixed
    {
        if (! is_array($node)) {
            return $node;
        }

        $reference = $node['$ref'] ?? null;

        if (is_string($reference) && str_starts_with($reference, '#/') && ! in_array($reference, $resolving, true)) {
            $target = $root;

            foreach (explode('/', substr($reference, 2)) as $segment) {
                $segment = str_replace(['~1', '~0'], ['/', '~'], $segment);

                if (! is_array($target) || ! array_key_exists($segment, $target)) {
                    return $node;
                }

                $target = $target[$segment];
            }

            $siblings = $node;
            unset($siblings['$ref']);
            $resolved = self::inlineReferences($target, $root, [...$resolving, $reference]);

            return is_array($resolved) ? [...$resolved, ...$siblings] : $resolved;
        }

        foreach ($node as $key => $value) {
            $node[$key] = self::inlineReferences($value, $root, $resolving);
        }

        return $node;
    }

    /**
     * Applique le dictionnaire des champs (resources/docs/api-fields.php) aux schémas nommés, aux réponses et aux
     * corps de requête en ligne, aux paramètres, et traduit les descriptions par défaut de Scramble.
     */
    private function describe(OpenApi $openApi): void
    {
        $dictionary = $this->dictionary();

        foreach ($openApi->components->schemas as $name => $schema) {
            if (! $schema instanceof Schema) {
                continue;
            }

            $short = class_basename((string) $name);

            if (isset($dictionary['enums'][$short]) && $schema->type->description === '') {
                $schema->type->setDescription($dictionary['enums'][$short]);
            }

            $this->describeType($schema->type, $short, '');
        }

        foreach ($openApi->components->responses as $response) {
            if ($response instanceof Response) {
                $response->setDescription($dictionary['responses'][$response->description] ?? $response->description);
            }
        }

        foreach ($openApi->paths as $path) {
            foreach ($path->operations as $operation) {
                foreach ($operation->parameters as $parameter) {
                    if ($parameter instanceof Parameter && isset($dictionary['parameters'][$parameter->name])) {
                        $parameter->description = $dictionary['parameters'][$parameter->name];
                    }
                }

                foreach ($operation->responses ?? [] as $response) {
                    if (! $response instanceof Response) {
                        continue;
                    }

                    $response->setDescription($this->responseDescription($response));

                    foreach ($response->content as $content) {
                        $schema = $content instanceof MediaType ? $content->schema : $content;

                        if ($schema instanceof Schema) {
                            $this->describeType($schema->type, '', '');
                        }
                    }
                }
            }
        }
    }

    private function responseDescription(Response $response): string
    {
        $description = $this->dictionary()['responses'][$response->description] ?? $response->description;

        // Scramble décrit les succès par le nom technique de la ressource (« `RelaisItemResource` ») : on le remplace.
        if ($description === '' || preg_match('/^(Array of |Paginated set of )?`\w+`$/', $description) === 1) {
            return match ((int) $response->code) {
                201 => 'Créé.',
                202 => 'Demande acceptée.',
                204 => 'Succès, sans contenu.',
                default => str_starts_with($description, 'Paginated') ? 'Succès : une page de 20 éléments, avec `links` et `meta` pour la pagination.' : 'Succès.',
            };
        }

        return $description;
    }

    private function describeType(Type $type, string $schemaName, string $path): void
    {
        if ($type instanceof ObjectType) {
            foreach ($type->properties as $name => $property) {
                if (! $property instanceof Type) {
                    continue;
                }

                $propertyPath = $path === '' ? (string) $name : $path.'.'.$name;
                $this->applyEntry($property, $schemaName, $propertyPath, (string) $name);
                $this->describeType($property, $schemaName, $propertyPath);
            }
        }

        if ($type instanceof ArrayType) {
            $this->describeType($type->items, $schemaName, $path.'[]');
        }

        if ($type instanceof AnyOf) {
            foreach ($type->items as $item) {
                $this->describeType($item, $schemaName, $path);
            }
        }
    }

    private function applyEntry(Type $type, string $schemaName, string $path, string $name): void
    {
        $fields = $this->dictionary()['fields'];
        // Une requête de modification reprend les champs de la requête de création (UpdateRelaisItemRequest → StoreRelaisItemRequest).
        $entry = ($schemaName !== '' ? $fields[$schemaName.'.'.$path] ?? $fields[preg_replace('/^Update/', 'Store', $schemaName).'.'.$path] ?? null : null)
            ?? $fields[$path] ?? $fields[preg_replace('/^[^.]+\./', '', $path) ?? ''] ?? $fields['*.'.$name] ?? null;

        if ($entry === null) {
            return;
        }

        if ($type->description === '') {
            $type->setDescription($entry[0]);
        }

        if (array_key_exists(1, $entry) && $type->example instanceof MissingValue) {
            $type->example($entry[1]);
        }
    }

    /**
     * Les réponses au quiz sont un objet { uuid de la question : uuid du choix }, pas une liste.
     */
    private function fixQuizAnswers(OpenApi $openApi): void
    {
        $schema = collect($openApi->components->schemas)->first(fn (mixed $schema, string $name): bool => class_basename($name) === 'StoreQuizAttemptRequest');

        if (! $schema instanceof Schema || ! $schema->type instanceof ObjectType) {
            return;
        }

        $answers = (new ObjectType)
            ->additionalProperties((new StringType)->format('uuid'))
            ->setDescription('Une réponse par question publiée : clé = `uuid` de la question (`GET /quiz`), valeur = `uuid` du choix retenu. Toutes les questions doivent recevoir une réponse.')
            ->example([
                '01a15e8f-1111-7000-8000-000000000001' => '01a15e8f-aaaa-7000-8000-00000000000a',
                '01a15e8f-2222-7000-8000-000000000002' => '01a15e8f-bbbb-7000-8000-00000000000b',
            ]);

        $schema->type->addProperty('answers', $answers);
    }

    /**
     * Corps de PUT /candidature/steps/{step} : les champs dépendent de l'étape, tous facultatifs (sauvegarde automatique).
     */
    private function candidatureStepBody(): RequestBodyObject
    {
        $string = fn (string $description, mixed $example): Type => (new StringType)->setDescription($description)->example($example);
        $uuid = fn (string $description): Type => (new StringType)->format('uuid')->setDescription($description);
        $list = fn (string $description, array $example): Type => (new ArrayType)->setItems(new StringType)->setDescription($description)->example($example);

        $identite = (new ObjectType)->setDescription('Étape `identite`.')
            ->addProperty('last_name', $string('Nom (100 caractères maximum).', 'Traoré'))
            ->addProperty('first_names', $string('Prénoms (150 caractères maximum).', 'Awa'))
            ->addProperty('birth_date', (new StringType)->format('date')->setDescription('Date de naissance, AAAA-MM-JJ. L’âge minimum est vérifié (`GET /settings`).')->example('2001-04-12'))
            ->addProperty('gender', $string('Genre : `femme`, `homme` ou `non_precise`.', 'femme'))
            ->addProperty('email', (new StringType)->format('email')->setDescription('Adresse e-mail, facultative.')->example('awa@example.com'));

        $territoire = (new ObjectType)->setDescription('Étape `territoire`.')
            ->addProperty('commune', $uuid('`uuid` d’une commune ouverte (`GET /communes`).'))
            ->addProperty('localite', $uuid('`uuid` d’une localité de cette commune (`GET /communes/{commune}/localites`).'))
            ->addProperty('lieu_type', $uuid('`uuid` du type de lieu fréquenté : école, marché… (`GET /candidature/options`).'));

        $profil = (new ObjectType)->setDescription('Étape `profil`.')
            ->addProperty('occupation', $string('Profession ou statut (valeurs de `GET /candidature/options`).', 'etudiant'))
            ->addProperty('languages', $list('Langues parlées, 8 au maximum (codes de `GET /candidature/options`).', ['fr', 'dioula']))
            ->addProperty('interests', $list('Centres d’intérêt : slugs des catégories, 12 au maximum.', ['vie-de-quartier', 'education']))
            ->addProperty('motivation', $string('Pourquoi devenir Relais (1 500 caractères maximum).', 'Je veux montrer ce qui se passe dans mon quartier.'));

        $body = new AnyOf;
        $body->items = [$identite, $territoire, $profil];
        $body->setDescription('Champs de l’étape indiquée dans l’adresse. Tous sont facultatifs : seuls ceux envoyés sont enregistrés.');

        return RequestBodyObject::make()
            ->setContent('application/json', Schema::fromType($body))
            ->description('Champs de l’étape `identite`, `territoire` ou `profil`.')
            ->required();
    }

    /**
     * Corps multipart de POST /identity (tout obligatoire) et PUT /identity (tout facultatif) : liste déroulante des pièces
     * actives et vrais champs « fichier » pour les photos (Scramble les déduit mal des règles conditionnelles).
     */
    private function identityBody(bool $submit): RequestBodyObject
    {
        $text = fn (string $description, string $example): Type => (new StringType)->setDescription($description)->example($example);
        $uuid = fn (string $description): Type => (new StringType)->format('uuid')->setDescription($description);
        $file = fn (string $description): Type => (new StringType)->format('binary')->contentMediaType('application/octet-stream')->setDescription($description);

        try {
            $types = IdentityDocumentType::query()->active()->get();
        } catch (\Throwable) {
            $types = collect();
        }

        $choices = $types->map(fn (IdentityDocumentType $type): string => '`'.$type->slug.'` '.$type->name.' → '.($type->two_sided ? '`recto` + `verso`' : '`page`'))->implode(' ; ');
        $documentType = (new StringType)
            ->setDescription('Type de pièce (liste gérée par l’administration, `GET /auth/register/options`). Il décide des photos de la pièce : '.$choices.'.')
            ->example($types->first()->slug ?? 'cni');

        if ($types->isNotEmpty()) {
            $documentType->enum($types->pluck('slug')->all());
        }

        $twoSided = $types->where('two_sided', true)->pluck('name')->implode(', ') ?: 'CNI, permis, carte consulaire';
        $onePage = $types->where('two_sided', false)->pluck('name')->implode(', ') ?: 'passeport, attestation';
        $when = $submit ? 'Obligatoire pour ces pièces' : 'Facultatif';

        // Corps à plat : l'outil d'essai de la documentation n'envoie rien quand le corps est découpé en variantes (anyOf).
        $body = (new ObjectType)
            ->addProperty('last_name', $text('Nom.', 'Traoré'))
            ->addProperty('first_name', $text('Prénoms (`first_names` est aussi accepté).', 'Abou'))
            ->addProperty('gender', (new StringType)->enum(['femme', 'homme'])->setDescription('Genre.')->example('homme'))
            ->addProperty('birth_date', $text('Date de naissance, JJ/MM/AAAA (ou AAAA-MM-JJ). Âge minimum fixé par l’administration.', '26/04/2000'))
            ->addProperty('commune', $uuid('Commune : `uuid` renvoyé par `GET /communes`.'.($submit ? ' Obligatoire sauf si `localite` est envoyée.' : '')))
            ->addProperty('localite', $uuid('Facultatif. Localité de cette commune : `uuid` renvoyé par `GET /communes/{commune}/localites`.'))
            ->addProperty('document_type', $documentType)
            ->addProperty('recto', $file('**Seulement pour : '.$twoSided.'.** Photo du recto de la pièce, image ou PDF, 10 Mo maximum. '.$when.'.'))
            ->addProperty('verso', $file('**Seulement pour : '.$twoSided.'.** Photo du verso de la pièce, image ou PDF, 10 Mo maximum. '.$when.'.'))
            ->addProperty('page', $file('**Seulement pour : '.$onePage.'.** Photo de la page de la pièce, image ou PDF, 10 Mo maximum. '.$when.'.'))
            ->addProperty('selfie', $file('La personne tenant sa pièce, visage bien visible ; sert aussi de photo du badge. Image JPEG, PNG ou HEIC, 10 Mo maximum.'.($submit ? ' Obligatoire.' : ' Facultatif.')));

        if ($submit) {
            $body->setRequired(['last_name', 'first_name', 'gender', 'birth_date', 'document_type', 'selfie']);
        }

        return RequestBodyObject::make()
            ->setContent('multipart/form-data', Schema::fromType($body))
            ->description($submit
                ? 'Tout en une requête. Choisir d’abord `document_type`, puis envoyer les photos de cette pièce : `recto` + `verso` ('.$twoSided.') ou `page` ('.$onePage.'), et le `selfie`. Laisser vides les photos qui ne concernent pas la pièce choisie.'
                : 'Tout est facultatif : seuls les éléments envoyés changent. Les photos de la pièce doivent correspondre au type de pièce (envoyé ou déjà enregistré).')
            ->required($submit);
    }

    /**
     * Réponse 429 documentée sur chaque opération : limite générale de l'API, plus la limite propre à la route.
     *
     * @param  list<string>  $middleware
     */
    private function tooManyRequests(array $middleware): Response
    {
        $specific = collect($middleware)->map(fn (string $name): ?string => self::LIMITS[$name] ?? null)->filter()->implode(' ');
        $general = 'Limite générale : 120 appels par minute et par compte (1 200 par adresse IP sans connexion).';

        $body = (new ObjectType)
            ->addProperty('message', (new StringType)->example('Trop de demandes. Patientez quelques minutes avant de réessayer.'))
            ->setRequired(['message']);

        $response = Response::make(429)
            ->setDescription(implode(' ', array_filter(['Trop de requêtes.', $specific, $general, 'Attendre la durée indiquée par l’en-tête `Retry-After` avant de réessayer.'])))
            ->setContent('application/json', Schema::fromType($body));

        $response->addHeader('Retry-After', new Header(
            description: 'Nombre de secondes à attendre avant de réessayer.',
            schema: Schema::fromType((new IntegerType)->example(60)),
        ));

        return $response;
    }

    /**
     * @return array{fields: array<string, array{0: string, 1?: mixed}>, enums: array<string, string>, parameters: array<string, string>, responses: array<string, string>}
     */
    private function dictionary(): array
    {
        /** @var array{fields: array<string, array{0: string, 1?: mixed}>, enums: array<string, string>, parameters: array<string, string>, responses: array<string, string>} $dictionary */
        $dictionary = $this->dictionary ??= require resource_path('docs/api-fields.php');

        return $dictionary;
    }
}
