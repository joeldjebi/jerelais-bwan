<?php

use App\Domain\Access\Enums\Role;
use App\Domain\Contribution\Actions\TransitionRelaisItem;
use App\Domain\Contribution\Models\RelaisItem;
use App\Domain\Contribution\States\Broadcast;
use App\Domain\Contribution\States\InReview;
use App\Domain\Contribution\States\Validated;
use App\Domain\Reporting\Mail\PeriodicReport;
use App\Domain\Reporting\Support\ReportBuilder;
use App\Domain\Territoire\Models\Commune;
use App\Livewire\Console\Reports\Index;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\ListsSeeder;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(ListsSeeder::class);
    fakeSms();
    $this->commune = Commune::factory()->create(['name' => 'Yopougon']);
    $this->redacteur = User::factory()->withTwoFactor()->withRole(Role::RedacteurEnChef)->create(['email' => 'chef@reflet.tv']);
    $coordo = User::factory()->withTwoFactor()->coordonnateurOf($this->commune)->create();

    $relais = RelaisItem::factory()->create(['commune_id' => $this->commune->id, 'received_at' => now()->subMinutes(30)]);
    app(TransitionRelaisItem::class)->handle($relais, InReview::class, $coordo);
    $this->travel(10)->minutes();
    app(TransitionRelaisItem::class)->handle($relais, Validated::class, $coordo);
    RelaisItem::factory()->create(['commune_id' => $this->commune->id, 'state' => Broadcast::class, 'broadcast_at' => now(), 'broadcast_programme' => 'Journal de 20 h']);
});

function figure(array $report, string $section, string $label): mixed
{
    $found = collect($report['sections'])->firstWhere('key', $section);

    return collect($found['figures'])->firstWhere('label', $label)['value'] ?? null;
}

it('calcule les indicateurs des six familles [§13]', function () {
    $report = app(ReportBuilder::class)->build(CarbonImmutable::now()->subDays(7), CarbonImmutable::now());

    expect(collect($report['sections'])->pluck('key')->all())->toBe(['reseau', 'activite', 'qualite', 'reactivite', 'editorial', 'technique'])
        ->and(figure($report, 'activite', 'Relais reçus'))->toBe(2)
        ->and(figure($report, 'qualite', 'Taux de validation'))->toBe('100 %')
        ->and(figure($report, 'reactivite', 'Délai moyen avant décision'))->toBe('40 min')
        ->and(figure($report, 'editorial', 'Relais diffusés'))->toBe(1);
});

it('affiche les rapports et les exporte en Excel et en PDF [ADM-26]', function () {
    Livewire::actingAs($this->redacteur)->test(Index::class)->assertSee('Valeur éditoriale')->assertSee('Journal de 20 h');

    $range = ['from' => now()->subDays(7)->toDateString(), 'to' => now()->toDateString()];

    $xlsx = $this->actingAs($this->redacteur)->get(route('console.reports.export', ['format' => 'xlsx', ...$range]))->assertOk();
    expect(file_get_contents($xlsx->baseResponse->getFile()->getPathname()))->toStartWith('PK');

    $pdf = $this->actingAs($this->redacteur)->get(route('console.reports.export', ['format' => 'pdf', ...$range]))->assertOk()->assertHeader('Content-Type', 'application/pdf');
    expect($pdf->getContent())->toStartWith('%PDF');
});

it('réserve les rapports aux rôles habilités [Annexe A]', function () {
    $this->actingAs(User::factory()->withTwoFactor()->coordonnateurOf($this->commune)->create())->get(route('console.reports'))->assertForbidden();
});

it('envoie les rapports périodiques à l’administration et à la rédaction en chef [§13]', function () {
    Mail::fake();

    $this->artisan('reports:send daily')->assertSuccessful();

    Mail::assertSent(PeriodicReport::class, fn (PeriodicReport $mail) => $mail->hasTo('chef@reflet.tv') && $mail->label === 'quotidien');
});
