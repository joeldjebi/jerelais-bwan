<?php

use App\Domain\Access\Enums\Permission;
use App\Domain\Access\Enums\Role;
use App\Domain\Audit\Models\AuditLog;
use App\Domain\Auth\Models\Device;
use App\Domain\Contribution\Models\RelaisItem;
use App\Domain\Contribution\States\InReview;
use App\Domain\Contribution\States\Validated;
use App\Domain\Gains\Actions\DecideRelaisGain;
use App\Domain\Gains\Actions\ManageWithdrawals;
use App\Domain\Gains\Enums\CommissionStatus;
use App\Domain\Gains\Exceptions\CommissionRefused;
use App\Domain\Gains\Models\Commission;
use App\Domain\Gains\Models\GainDecision;
use App\Domain\Gains\Models\PayoutAccount;
use App\Domain\Gains\Support\Wallet;
use App\Domain\Identity\Enums\IdentityStatus;
use App\Domain\Identity\Models\IdentityVerification;
use App\Domain\Territoire\Models\Commune;
use App\Livewire\Console\Rewards\Index as RewardsPage;
use App\Livewire\Console\Validation\Show;
use App\Models\User;
use Livewire\Livewire;

/*
 * Rémunération décidée relais par relais (ADM-27) : l'administration ou le superviseur décide de payer ou non un
 * relais validé, avec un montant libre ; chaque décision est historisée.
 */

beforeEach(function () {
    fakeSms();
    $this->push = fakePush();
    fakeMultipart();
    $this->commune = Commune::factory()->create();
    $this->admin = User::factory()->withTwoFactor()->withRole(Role::Admin)->create();
    $this->relais = RelaisItem::factory()->create(['commune_id' => $this->commune->id, 'state' => Validated::class, 'title' => 'Marché inondé']);
    attachReadyMedia($this->relais);
    $this->user = $this->relais->user;
});

it('rémunère un relais validé d’un montant libre, crédité au portefeuille, historisé et notifié', function () {
    Livewire::actingAs($this->admin)->test(Show::class, ['relais' => $this->relais])
        ->assertSee('À décider')
        ->call('openGain', 'credit')
        ->call('saveGain')
        ->assertHasErrors('gainAmount')
        ->set('gainAmount', '2750')
        ->set('gainReason', 'Sujet repris au 20 h')
        ->call('saveGain')
        ->assertHasNoErrors()
        ->assertSee('2 750 FCFA');

    $commission = Commission::query()->sole();
    expect($commission->amount)->toBe(2750)
        ->and($commission->kind->value)->toBe('reward')
        ->and($commission->status)->toBe(CommissionStatus::Due)
        ->and(app(Wallet::class)->summary($this->user)['available'])->toBe(2750)
        ->and(GainDecision::query()->sole()->only(['decision', 'amount', 'reason', 'decided_by']))->toBe(['decision' => 'credited', 'amount' => 2750, 'reason' => 'Sujet repris au 20 h', 'decided_by' => $this->admin->id])
        ->and(AuditLog::query()->where('action', 'gains.credited')->exists())->toBeTrue()
        ->and(collect($this->push->sent)->pluck('message.data.type')->all())->toBe([])
        ->and(AuditLog::query()->where('action', 'gains.credited')->sole()->payload['amount'])->toBe(2750);
});

it('laisse des montants différents d’un relais à l’autre, et refuse de rémunérer deux fois le même relais', function () {
    $other = RelaisItem::factory()->create(['user_id' => $this->user->id, 'commune_id' => $this->commune->id, 'state' => Validated::class]);
    $gains = app(DecideRelaisGain::class);

    $gains->credit($this->relais, $this->admin, 1000);
    $gains->credit($other, $this->admin, 3500);

    expect(fn () => $gains->credit($this->relais, $this->admin, 500))->toThrow(CommissionRefused::class)
        ->and(app(Wallet::class)->summary($this->user)['available'])->toBe(4500);
});

it('enregistre la décision de ne pas rémunérer avec un motif obligatoire, puis permet de revenir dessus', function () {
    Livewire::actingAs($this->admin)->test(Show::class, ['relais' => $this->relais])
        ->call('openGain', 'decline')
        ->call('saveGain')
        ->assertHasErrors('gainReason')
        ->set('gainReason', 'Images inexploitables à l’antenne')
        ->call('saveGain')
        ->assertHasNoErrors()
        ->assertSee('Pas de rémunération')
        ->call('openGain', 'credit')
        ->set('gainAmount', '500')
        ->call('saveGain')
        ->assertHasNoErrors();

    expect(GainDecision::query()->orderBy('id')->pluck('decision')->all())->toBe(['declined', 'credited'])
        ->and(Commission::query()->sole()->amount)->toBe(500);
});

it('corrige le montant ou annule tant que la somme n’est pas retirée, avec un historique complet', function () {
    $gains = app(DecideRelaisGain::class);
    $gains->credit($this->relais, $this->admin, 2000);

    Livewire::actingAs($this->admin)->test(Show::class, ['relais' => $this->relais])
        ->call('openGain', 'amend')
        ->assertSet('gainAmount', '2000')
        ->set('gainAmount', '3000')
        ->set('gainReason', 'Relais exclusif')
        ->call('saveGain')
        ->assertHasNoErrors();

    expect(Commission::query()->sole()->amount)->toBe(3000)
        ->and(GainDecision::query()->latest('id')->first()->only(['decision', 'amount', 'previous_amount']))->toBe(['decision' => 'amended', 'amount' => 3000, 'previous_amount' => 2000]);

    IdentityVerification::query()->forceCreate(['user_id' => $this->user->id, 'status' => IdentityStatus::Verified]);
    PayoutAccount::query()->create(['user_id' => $this->user->id, 'method' => 'wave', 'phone' => '+2250707070707', 'holder_name' => 'Awa']);
    app(ManageWithdrawals::class)->request($this->user, 2500);

    expect(fn () => $gains->amend($this->relais, $this->admin, 100, 'Baisse'))->toThrow(CommissionRefused::class)
        ->and(fn () => $gains->cancel($this->relais, $this->admin, 'Annulation'))->toThrow(CommissionRefused::class);

    $gains->amend($this->relais, $this->admin, 2600, 'Petite baisse possible');
    expect(Commission::query()->sole()->amount)->toBe(2600);
});

it('réserve la décision à l’administration et au superviseur, sur un relais validé seulement', function () {
    $coordo = User::factory()->withTwoFactor()->coordonnateurOf($this->commune)->create();
    Livewire::actingAs($coordo)->test(Show::class, ['relais' => $this->relais])->assertDontSee('Rémunérer')->call('openGain', 'credit')->assertForbidden();

    $pending = RelaisItem::factory()->create(['commune_id' => $this->commune->id, 'state' => InReview::class]);
    attachReadyMedia($pending);
    Livewire::actingAs($this->admin)->test(Show::class, ['relais' => $pending])->assertDontSee('Rémunérer');

    $superviseur = User::factory()->withTwoFactor()->withRole(Role::Superviseur)->create();
    expect($superviseur->can(Permission::GainsDecide->value))->toBeTrue();
    $this->actingAs($superviseur)->get(route('console.rewards'))->assertOk();
    $this->actingAs($coordo)->get(route('console.rewards'))->assertForbidden();
});

it('liste les relais validés à décider puis les relais décidés', function () {
    $decided = RelaisItem::factory()->create(['commune_id' => $this->commune->id, 'state' => Validated::class, 'title' => 'Déjà rémunéré']);
    app(DecideRelaisGain::class)->credit($decided, $this->admin, 1500);

    Livewire::actingAs($this->admin)->test(RewardsPage::class)
        ->assertSee('Marché inondé')->assertDontSee('Déjà rémunéré')
        ->set('tab', 'decides')
        ->assertSee('Déjà rémunéré')->assertSee('1 500 FCFA')->assertDontSee('Marché inondé');
});

it('prévient le Relais de sa rémunération par push, ou par SMS s’il n’a pas de téléphone joignable', function () {
    $sms = fakeSms();
    $gains = app(DecideRelaisGain::class);

    $gains->credit($this->relais, $this->admin, 5000);
    expect($this->push->sent)->toBeEmpty()
        ->and($sms->sent)->toHaveCount(1)
        ->and($sms->sent[0]['message'])->toContain('5 000 FCFA')->toContain('portefeuille')->toContain('Marché inondé');

    Device::query()->create(['user_id' => $this->user->id, 'name' => 'Tecno', 'platform' => 'android', 'fcm_token' => 'jeton']);
    $gains->amend($this->relais, $this->admin, 6000, 'Relais exclusif');

    expect($this->push->sent)->toHaveCount(1)
        ->and($this->push->sent[0]['message']->data['type'])->toBe('gains.updated')
        ->and($this->push->sent[0]['message']->body)->toContain('6 000 FCFA')
        ->and($sms->sent)->toHaveCount(1);
});
