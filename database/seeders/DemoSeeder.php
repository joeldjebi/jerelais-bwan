<?php

namespace Database\Seeders;

use App\Domain\Access\Enums\Role;
use App\Domain\AppelRelais\Actions\PublishAppel;
use App\Domain\Auth\Contracts\SmsGateway;
use App\Domain\Auth\Data\PhoneNumber;
use App\Domain\Candidature\Enums\CandidatureStatus;
use App\Domain\Candidature\Enums\Occupation;
use App\Domain\Candidature\Models\Candidature;
use App\Domain\Contribution\Actions\DecideRelaisItem;
use App\Domain\Contribution\Actions\LockRelaisItem;
use App\Domain\Contribution\Enums\CreditMode;
use App\Domain\Contribution\Enums\PeopleConsent;
use App\Domain\Contribution\Enums\RelaisDecision;
use App\Domain\Contribution\Enums\Urgency;
use App\Domain\Contribution\Models\Category;
use App\Domain\Contribution\Models\RelaisItem;
use App\Domain\Contribution\States\Received;
use App\Domain\Editorial\Actions\PublishToShowcase;
use App\Domain\Editorial\Actions\SelectRelaisItem;
use App\Domain\Editorial\Models\RelaisCollection;
use App\Domain\Media\Enums\MediaKind;
use App\Domain\Media\Enums\MediaStatus;
use App\Domain\Media\Jobs\ProcessMedia;
use App\Domain\Media\Models\Media;
use App\Domain\Messagerie\Actions\SendMessage;
use App\Domain\Relais\Enums\RelaisStatus;
use App\Domain\Relais\Models\RelaisProfile;
use App\Domain\Territoire\Enums\LocaliteType;
use App\Domain\Territoire\Models\Commune;
use App\Domain\Territoire\Models\Localite;
use App\Domain\Territoire\Models\Region;
use App\Domain\Validation\Enums\RejectionTarget;
use App\Domain\Validation\Models\RejectionReason;
use App\Models\User;
use Carbon\CarbonImmutable;
use Clickbar\Magellan\Data\Geometries\Point;
use GdImage;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Jeu de démonstration pour la recette locale : un compte par rôle, des Relais, des candidatures et des relais
 * dans tous les états, produits par les vraies actions métier (verrou, décisions, sélection, diffusion, vitrine)
 * et le vrai traitement des médias (stockage, antivirus, vignette). Jamais exécuté hors environnement local.
 *
 * Mots de passe : 12345678 · double authentification : DEMO_TWO_FACTOR_CODE (123456).
 */
class DemoSeeder extends Seeder
{
    public const PASSWORD = '12345678';

    /** @var array<string, User> */
    private array $staff = [];

    /** @var array<string, Commune> */
    private array $communes = [];

    /** @var array<string, RelaisProfile> */
    private array $relais = [];

    public function run(): void
    {
        if (! app()->isLocal()) {
            throw new RuntimeException('Le jeu de démonstration est réservé à l’environnement local.');
        }

        // Aucun SMS ni notification pendant la préparation du jeu de données.
        app()->instance(SmsGateway::class, new class implements SmsGateway
        {
            public function send(PhoneNumber $to, string $message): void {}
        });
        Notification::fake();

        // Tout ou rien : un échec ne laisse pas de jeu de données à moitié créé.
        DB::transaction(function (): void {
            $this->openCommunes();
            $this->seedStaff();
            $this->seedRelais();
            $this->seedCandidatures();
            $items = $this->seedRelaisItems();
            $this->seedAppels($items);
            $this->seedMessages();
            $this->seedCollection($items);
        });
    }

    private function openCommunes(): void
    {
        $quartiers = [
            'yopougon' => ['Niangon', 'Selmer', 'Toits Rouges', 'Siporex', 'Andokoi', 'Maroc', 'Wassakara', 'Sideci'],
            'cocody' => ['Riviera 2', 'Angré', 'Deux-Plateaux', 'Blockhaus', 'Saint-Jean'],
            'abobo' => ['Abobo-Gare', 'PK 18', 'Avocatier', 'Samaké'],
        ];

        foreach ($quartiers as $slug => $names) {
            $commune = Commune::query()->where('slug', $slug)->firstOrFail();
            $commune->forceFill(['is_active' => true])->save();

            foreach ($names as $name) {
                Localite::query()->firstOrCreate(['commune_id' => $commune->id, 'name' => $name], ['type' => LocaliteType::Quartier]);
            }

            $this->communes[$slug] = $commune;
        }
    }

    private function seedStaff(): void
    {
        $accounts = [
            'admin' => ['admin@jerelais.com', 'Admin Démo', Role::Admin],
            'redaction' => ['redaction@jerelais.com', 'Aïcha Koné', Role::RedacteurEnChef],
            'journaliste' => ['journaliste@jerelais.com', 'Yao Kouassi', Role::Journaliste],
            'lecture' => ['lecture@jerelais.com', 'Lecture Seule', Role::LectureSeule],
            'superviseur' => ['superviseur@jerelais.com', 'Bernard Gnagne', Role::Superviseur],
            'coordinateur' => ['coordinateur@jerelais.com', 'Coordinateur Yopougon', Role::Coordonnateur],
            'suppleant' => ['suppleant@jerelais.com', 'Estelle Brou', Role::Coordonnateur],
            'cocody' => ['coordinateur.cocody@jerelais.com', 'Didier Kacou', Role::Coordonnateur],
        ];

        foreach ($accounts as $key => [$email, $name, $role]) {
            $user = User::query()->firstOrNew(['email' => $email]);
            $user->fill(['name' => $name, 'password' => self::PASSWORD]);
            $user->forceFill([
                'email_verified_at' => now(),
                'two_factor_secret' => encrypt('JBSWY3DPEHPK3PXP'),
                'two_factor_recovery_codes' => encrypt((string) json_encode(['demo-recovery-1', 'demo-recovery-2'])),
                'two_factor_confirmed_at' => now(),
            ])->save();
            $user->syncRoles([$role->value]);

            $this->staff[$key] = $user;
        }

        $this->staff['coordinateur']->communes()->syncWithPivotValues([$this->communes['yopougon']->id], ['assignment' => 'titulaire']);
        $this->staff['suppleant']->communes()->syncWithPivotValues([$this->communes['yopougon']->id], ['assignment' => 'suppleant']);
        $this->staff['cocody']->communes()->syncWithPivotValues([$this->communes['cocody']->id, $this->communes['abobo']->id], ['assignment' => 'titulaire']);
        $this->staff['superviseur']->regions()->sync([Region::query()->where('slug', 'district-autonome-d-abidjan')->value('id')]);
    }

    private function seedRelais(): void
    {
        $people = [
            'awa' => ['0700000001', 'Awa', 'Traoré', 'yopougon', 'Niangon', Occupation::Etudiant, RelaisStatus::Active],
            'koffi' => ['0700000002', 'Koffi', 'N’Guessan', 'yopougon', 'Selmer', Occupation::Commercant, RelaisStatus::Active],
            'mariam' => ['0700000003', 'Mariam', 'Bamba', 'yopougon', 'Siporex', Occupation::Salarie, RelaisStatus::Active],
            'fatou' => ['0700000004', 'Fatou', 'Diallo', 'cocody', 'Angré', Occupation::Etudiant, RelaisStatus::Active],
            'jeanmarc' => ['0700000005', 'Jean-Marc', 'Yao', 'abobo', 'Abobo-Gare', Occupation::Independant, RelaisStatus::Active],
            'ibrahim' => ['0700000006', 'Ibrahim', 'Ouattara', 'abobo', 'PK 18', Occupation::Artisan, RelaisStatus::Suspended],
        ];

        foreach ($people as $key => [$phone, $firstName, $lastName, $commune, $quartier, $occupation, $status]) {
            $profile = RelaisProfile::factory()->create([
                'commune_id' => $this->communes[$commune]->id,
                'display_name' => $firstName.' '.Str::substr($lastName, 0, 1).'.',
                'status' => $status,
                'suspended_at' => $status === RelaisStatus::Active ? null : now(),
                'localite_id' => Localite::query()->where('commune_id', $this->communes[$commune]->id)->where('name', $quartier)->value('id'),
            ]);

            $user = $profile->user;
            $user->fill(['name' => "{$firstName} {$lastName}", 'password' => self::PASSWORD])->setPhoneNumber(PhoneNumber::parse($phone))->save();

            $candidature = Candidature::withoutCommuneRestriction()->whereKey($profile->candidature_id)->firstOrFail();
            $candidature->forceFill(['first_names' => $firstName, 'last_name' => $lastName, 'occupation' => $occupation])->save();
            $this->storeIdentityImages($candidature, "{$firstName} {$lastName}");

            $this->relais[$key] = $profile;
        }
    }

    private function seedCandidatures(): void
    {
        $applicants = [
            ['0700000011', 'Adjoua', 'Konan', CandidatureStatus::Submitted, 'Je suis étudiante à Yopougon et je veux raconter la vie de mon quartier.'],
            ['0700000012', 'Moussa', 'Coulibaly', CandidatureStatus::UnderReview, 'Commerçant au marché de Selmer, je vois tout ce qui s’y passe.'],
        ];

        foreach ($applicants as [$phone, $firstName, $lastName, $status, $motivation]) {
            $user = User::factory()->withRole(Role::Candidat)->create(['name' => "{$firstName} {$lastName}"]);
            $user->setPhoneNumber(PhoneNumber::parse($phone))->save();

            $candidature = Candidature::factory()->submitted()->createOne([
                'user_id' => $user->id,
                'commune_id' => $this->communes['yopougon']->id,
                'first_names' => $firstName,
                'last_name' => $lastName,
                'motivation' => $motivation,
            ]);
            $candidature->forceFill(['status' => $status])->save();
            $this->storeIdentityImages($candidature, "{$firstName} {$lastName}");
        }

        // Dossier commencé, à reprendre depuis le site (étape « territoire » non remplie).
        $draft = User::factory()->withRole(Role::Candidat)->create(['name' => 'Grâce Aka']);
        $draft->setPhoneNumber(PhoneNumber::parse('0700000013'))->save();
        Candidature::factory()->createOne(['user_id' => $draft->id, 'first_names' => 'Grâce', 'last_name' => 'Aka']);
    }

    /**
     * @return array<string, RelaisItem>
     */
    private function seedRelaisItems(): array
    {
        $lock = app(LockRelaisItem::class);
        $decide = app(DecideRelaisItem::class);
        $select = app(SelectRelaisItem::class);
        $showcase = app(PublishToShowcase::class);
        $coordo = $this->staff['coordinateur'];
        $cocody = $this->staff['cocody'];
        $redaction = $this->staff['redaction'];
        $reason = fn (string $slug) => RejectionReason::query()->where('applies_to', RejectionTarget::Relais->value)->where('slug', $slug)->firstOrFail();

        $items = [];

        $items['incendie'] = $this->relaisItem('awa', 'faits-divers-et-accidents', Urgency::Urgent, 'Incendie au marché de Selmer',
            'Le feu a pris dans la partie des pagnes. Les pompiers ne sont pas encore arrivés.', [5.3365, -4.0785], 'Marché de Selmer', 8, ['#7c2d12', '#ea580c']);

        $items['eclairage'] = $this->relaisItem('koffi', 'vie-de-quartier', Urgency::Normal, 'Lampadaires éteints depuis une semaine',
            'Toute la rue principale de Selmer est dans le noir le soir.', [5.3398, -4.0812], 'Rue principale, Selmer', 300, ['#1e293b', '#475569'], hoursAgo: 6);

        $items['route'] = $this->relaisItem('mariam', 'vie-de-quartier', Urgency::Important, 'Route coupée à Niangon',
            'Un camion est en panne en travers de la voie depuis ce matin.', [5.3302, -4.1005], 'Niangon Lokoa', 20, ['#3f3f46', '#a16207']);
        $lock->handle($items['route'], $coordo);
        $decide->handle($items['route'], RelaisDecision::RequestComplement, $coordo, 'Pouvez-vous envoyer une photo plus large, avec la plaque du camion masquée ?');

        $items['inondation'] = $this->relaisItem('awa', 'meteo-et-environnement', Urgency::Urgent, 'Inondation au carrefour Siporex',
            'L’eau arrive aux genoux, les gbakas ne passent plus.', [5.3445, -4.0702], 'Carrefour Siporex', 12, ['#0c4a6e', '#38bdf8'], hoursAgo: 30);
        $lock->handle($items['inondation'], $coordo);
        $decide->handle($items['inondation'], RelaisDecision::Validate, $coordo, blurRequired: false);

        $items['horssujet'] = $this->relaisItem('koffi', 'autre', Urgency::Normal, 'Mon nouveau téléphone',
            'Regardez la qualité de la photo.', [5.3380, -4.0790], null, 15, ['#4c1d95', '#a78bfa'], hoursAgo: 20);
        $lock->handle($items['horssujet'], $coordo);
        $decide->handle($items['horssujet'], RelaisDecision::Reject, $coordo, 'Ce relais ne présente pas d’intérêt d’information.', $reason('hors-sujet'));

        $items['accident'] = $this->relaisItem('mariam', 'faits-divers-et-accidents', Urgency::Urgent, 'Accident sur l’autoroute du Nord',
            'Collision entre un car et un taxi. Des enfants étaient dans le car.', [5.3520, -4.0650], 'Autoroute du Nord, sortie Yopougon', 25, ['#450a0a', '#dc2626'], hoursAgo: 3, minors: true);
        $lock->handle($items['accident'], $coordo);
        $decide->handle($items['accident'], RelaisDecision::Sensitive, $coordo, 'Des enfants blessés sont visibles : arbitrage de la rédaction nécessaire.');

        $items['nettoyage'] = $this->relaisItem('awa', 'societe-et-solidarite', Urgency::Normal, 'Les habitants nettoient le marché de Wassakara',
            'Une centaine de jeunes se sont mobilisés ce samedi matin.', [5.3412, -4.0855], 'Marché de Wassakara', 10, ['#14532d', '#22c55e'], hoursAgo: 72, credit: CreditMode::Named);
        $lock->handle($items['nettoyage'], $coordo);
        $decide->handle($items['nettoyage'], RelaisDecision::Validate, $coordo, blurRequired: false);
        $select->select($items['nettoyage'], $redaction, $this->staff['journaliste'], 'Journal de 20 h');
        $select->markBroadcast($items['nettoyage'], $redaction, CarbonImmutable::now()->subDay(), 'Journal de 20 h', 'https://www.reflet.tv');
        $showcase->publish($items['nettoyage'], $redaction, 'À Wassakara, la jeunesse redonne vie au marché', 'Une centaine de jeunes ont nettoyé le marché ce samedi : une initiative relayée par Awa, Relais à Yopougon.', true);

        $items['embouteillage'] = $this->relaisItem('fatou', 'economie-et-consommation', Urgency::Normal, 'Embouteillage géant à la Riviera 2',
            'Plus d’une heure pour traverser le carrefour.', [5.3610, -3.9720], 'Carrefour Riviera 2', 30, ['#78350f', '#f59e0b'], hoursAgo: 1);

        $items['electricite'] = $this->relaisItem('fatou', 'vie-de-quartier', Urgency::Important, 'Coupure d’électricité à Angré',
            'Tout le quartier est privé de courant depuis hier soir.', [5.3950, -3.9880], 'Angré 8e tranche', 18, ['#111827', '#fbbf24'], hoursAgo: 26, credit: CreditMode::Pseudonym, creditName: 'Fatou d’Angré');
        $lock->handle($items['electricite'], $cocody);
        $decide->handle($items['electricite'], RelaisDecision::Validate, $cocody, blurRequired: false);
        $select->select($items['electricite'], $redaction, $this->staff['journaliste'], 'Édition de 13 h');

        $items['pluies'] = $this->relaisItem('jeanmarc', 'meteo-et-environnement', Urgency::Urgent, 'Pluies diluviennes à Abobo-Gare',
            'Les commerçants sauvent leurs marchandises sous la pluie.', [5.4180, -4.0200], 'Gare d’Abobo', 14, ['#1e3a8a', '#60a5fa'], hoursAgo: 50, credit: CreditMode::Anonymous);
        $lock->handle($items['pluies'], $cocody);
        $decide->handle($items['pluies'], RelaisDecision::Validate, $cocody, blurRequired: false);
        $select->select($items['pluies'], $redaction, $this->staff['journaliste'], 'Journal de 20 h');
        $select->markBroadcast($items['pluies'], $redaction, CarbonImmutable::now()->subHours(20), 'Journal de 20 h', null);
        $showcase->publish($items['pluies'], $redaction, 'Abobo sous les eaux', 'Les images d’un Relais montrent l’ampleur des pluies sur la gare d’Abobo.', true);

        return $items;
    }

    /**
     * Relais envoyé (état « reçu ») avec une photo passée par le vrai traitement des médias.
     *
     * @param  array{0: float, 1: float}  $location
     * @param  array{0: string, 1: string}  $colors
     */
    private function relaisItem(
        string $relaisKey,
        string $categorySlug,
        Urgency $urgency,
        string $title,
        string $description,
        array $location,
        ?string $place,
        int $accuracy,
        array $colors,
        int $hoursAgo = 0,
        bool $minors = false,
        CreditMode $credit = CreditMode::Anonymous,
        ?string $creditName = null,
    ): RelaisItem {
        $profile = $this->relais[$relaisKey];
        $receivedAt = now()->subHours($hoursAgo)->subMinutes(random_int(2, 50));
        $slaMinutes = $urgency === Urgency::Urgent ? 30 : 240;

        $relais = RelaisItem::factory()->create([
            'user_id' => $profile->user_id,
            'relais_profile_id' => $profile->id,
            'commune_id' => $profile->commune_id,
            'declared_commune_id' => $profile->commune_id,
            'routed_by' => 'declared',
            'category_id' => Category::query()->where('slug', $categorySlug)->value('id'),
            'state' => Received::class,
            'urgency' => $urgency,
            'title' => $title,
            'description' => $description,
            'location' => Point::makeGeodetic($location[0], $location[1]),
            'location_accuracy_m' => $accuracy,
            'declared_place' => $place,
            'device_captured_at' => $receivedAt->copy()->subMinutes(3),
            'device_model' => 'Tecno Spark 10',
            'has_minors' => $minors,
            'people_consent' => $minors ? PeopleConsent::No : PeopleConsent::NotApplicable,
            'credit_mode' => $credit,
            'credit_name' => $creditName,
            'submitted_at' => $receivedAt,
            'received_at' => $receivedAt,
            'sla_due_at' => $receivedAt->copy()->addMinutes($slaMinutes),
        ]);
        $relais->statusHistory()->create(['from' => 'uploading', 'to' => 'received', 'created_at' => $receivedAt]);

        $this->attachPhoto($relais, $title, $colors);

        return $relais->refresh();
    }

    /**
     * @param  array{0: string, 1: string}  $colors
     */
    private function attachPhoto(RelaisItem $relais, string $caption, array $colors): void
    {
        $jpeg = $this->demoImage(1280, 720, $colors, $caption, 'JE RELAIS · photo de démonstration');
        $path = 'relais/'.$relais->uuid.'/'.Str::uuid7().'.jpg';
        Storage::disk('medias')->put($path, $jpeg);

        $media = new Media([
            'client_uuid' => (string) Str::uuid(),
            'kind' => MediaKind::Photo,
            'status' => MediaStatus::Uploaded,
            'original_name' => 'IMG_'.random_int(1000, 9999).'.jpg',
            'declared_mime' => 'image/jpeg',
            'size' => strlen($jpeg),
            'declared_sha256' => hash('sha256', $jpeg),
            'disk' => 'medias',
            'path' => $path,
            'part_size' => (int) config('jerelais.media.part_size_mb') * 1024 * 1024,
            'is_imported' => false,
        ]);
        $relais->medias()->save($media);

        ProcessMedia::dispatchSync($media->id);
    }

    private function storeIdentityImages(Candidature $candidature, string $name): void
    {
        foreach ($candidature->documents()->get() as $document) {
            $label = Str::upper(str_replace('_', ' ', $document->type->value));
            $jpeg = $this->demoImage(900, 600, ['#e2e8f0', '#94a3b8'], $label, $name.' · document fictif');
            Storage::disk($document->disk)->put($document->path, $jpeg);
            $document->forceFill(['size' => strlen($jpeg), 'sha256' => hash('sha256', $jpeg)])->save();
        }
    }

    /**
     * @param  int<1, max>  $width
     * @param  int<1, max>  $height
     * @param  array{0: string, 1: string}  $colors  fond et couleur d'accent (hexadécimal)
     */
    private function demoImage(int $width, int $height, array $colors, string $title, string $subtitle): string
    {
        $image = imagecreatetruecolor($width, $height);
        imagefill($image, 0, 0, $this->color($image, $colors[0]));
        $accent = $this->color($image, $colors[1]);

        // Quelques formes pour donner une texture (et une empreinte perceptuelle propre à chaque image).
        for ($i = 0; $i < 14; $i++) {
            $size = random_int(60, 320);
            imagefilledellipse($image, random_int(0, $width), random_int(0, $height), $size, $size, $accent);
        }

        $white = (int) imagecolorallocate($image, 255, 255, 255);
        $band = (int) imagecolorallocatealpha($image, 0, 0, 0, 50);
        imagefilledrectangle($image, 0, $height - 120, $width, $height, $band);
        imagestring($image, 5, 40, $height - 95, mb_convert_encoding($title, 'ISO-8859-1', 'UTF-8'), $white);
        imagestring($image, 3, 40, $height - 60, mb_convert_encoding($subtitle, 'ISO-8859-1', 'UTF-8'), $white);

        ob_start();
        imagejpeg($image, null, 85);

        return (string) ob_get_clean();
    }

    private function color(GdImage $image, string $hex): int
    {
        [$red, $green, $blue] = array_map(fn (string $pair): int => max(0, min(255, (int) hexdec($pair))), str_split(ltrim($hex, '#'), 2));

        return (int) imagecolorallocate($image, $red, $green, $blue);
    }

    /**
     * @param  array<string, RelaisItem>  $items
     */
    private function seedAppels(array $items): void
    {
        $publish = app(PublishAppel::class);

        $national = $publish->handle($this->staff['redaction'], [
            'title' => 'Saison des pluies : montrez vos quartiers',
            'instructions' => 'Filmez les rues inondées, les caniveaux bouchés et les initiatives des habitants. Ne vous mettez jamais en danger.',
            'target' => 'communes',
            'communes' => array_map(fn (Commune $commune) => $commune->uuid, array_values($this->communes)),
            'deadline_at' => now()->addDays(10)->toIso8601String(),
        ]);
        $items['inondation']->forceFill(['appel_relais_id' => $national->id])->save();
        $items['pluies']->forceFill(['appel_relais_id' => $national->id])->save();

        $publish->handle($this->staff['coordinateur'], [
            'title' => 'Rentrée scolaire à Yopougon',
            'instructions' => 'Montrez l’ambiance devant les écoles et les difficultés des parents (sans filmer le visage des enfants).',
            'target' => 'communes',
            'communes' => [$this->communes['yopougon']->uuid],
            'deadline_at' => now()->addDays(5)->toIso8601String(),
        ]);
    }

    private function seedMessages(): void
    {
        $send = app(SendMessage::class);
        $coordo = $this->staff['coordinateur'];

        $conversation = $send->conversationFor($this->relais['awa']);
        $send->send($conversation, $this->relais['awa']->user, 'Bonjour, j’ai envoyé l’incendie de Selmer, il est urgent.');
        $send->send($conversation, $coordo, 'Merci Awa, je le regarde tout de suite. Restez à distance du feu.');

        $send->broadcast($this->communes['yopougon'], $coordo, 'Rappel : formation des Relais samedi à 10 h à la mairie de Yopougon.');
    }

    /**
     * @param  array<string, RelaisItem>  $items
     */
    private function seedCollection(array $items): void
    {
        $collection = new RelaisCollection(['name' => 'Saison des pluies 2026', 'description' => 'Inondations et intempéries signalées par le réseau.']);
        $collection->creator()->associate($this->staff['redaction']);
        $collection->save();
        $collection->relais()->attach([$items['inondation']->id, $items['pluies']->id], ['added_by' => $this->staff['redaction']->id, 'created_at' => now()]);
    }
}
