<?php

use App\Domain\Access\Enums\Role;
use App\Domain\Audit\Models\AuditLog;
use App\Domain\Contribution\Events\RelaisStatusChanged;
use App\Domain\Contribution\Models\RelaisItem;
use App\Domain\Gains\Actions\PayCommissions;
use App\Domain\Gains\Enums\CommissionStatus;
use App\Domain\Gains\Enums\PaymentMethod;
use App\Domain\Gains\Models\Commission;
use App\Domain\Gains\Models\Payout;
use App\Domain\Gains\Models\PayoutAccount;
use App\Domain\Identity\Enums\IdentityStatus;
use App\Domain\Identity\Models\IdentityVerification;
use App\Domain\Settings\Settings;
use App\Domain\Territoire\Models\Commune;
use App\Livewire\Console\Gains\Index as GainsPage;
use App\Models\User;
use Laravel\Sanctum\Sanctum;
use Livewire\Livewire;

beforeEach(function () {
    $this->sms = fakeSms();
    $this->admin = User::factory()->withTwoFactor()->withRole(Role::Admin)->create();
    app(Settings::class)->set('gains.amount_per_validated', 2000);
    app(Settings::class)->set('gains.amount_per_broadcast', 3000);
    $this->relais = RelaisItem::factory()->create(['commune_id' => Commune::factory()->create()->id, 'title' => 'Inondation à Niangon']);
    $this->user = $this->relais->user;
});

function verifyIdentityOf(User $user): void
{
    IdentityVerification::query()->forceCreate(['user_id' => $user->id, 'status' => IdentityStatus::Verified]);
}

it('crée une commission à la validation puis une autre à la diffusion, une seule de chaque [MOB-26]', function () {
    RelaisStatusChanged::dispatch($this->relais, 'in_review', 'validated');
    RelaisStatusChanged::dispatch($this->relais, 'in_review', 'validated');
    RelaisStatusChanged::dispatch($this->relais, 'selected', 'broadcast');

    $commissions = Commission::query()->where('relais_item_id', $this->relais->id)->orderBy('id')->get();

    expect($commissions)->toHaveCount(2)
        ->and($commissions->pluck('kind.value')->all())->toBe(['validated', 'broadcast'])
        ->and($commissions->pluck('amount')->all())->toBe([2000, 3000])
        ->and($commissions->every(fn ($commission) => $commission->user_id === $this->user->id && $commission->status === CommissionStatus::Due))->toBeTrue();
});

it('ne crée aucune commission tant que les montants ne sont pas fixés [ADM-27]', function () {
    app(Settings::class)->set('gains.amount_per_validated', null);

    RelaisStatusChanged::dispatch($this->relais, 'in_review', 'validated');

    expect(Commission::query()->count())->toBe(0);
});

it('annule la commission encore due d’un relais finalement rejeté, jamais une commission payée [ADM-27]', function () {
    $due = Commission::factory()->create(['relais_item_id' => $this->relais->id]);
    $paid = Commission::factory()->create(['relais_item_id' => $this->relais->id, 'kind' => 'broadcast', 'status' => CommissionStatus::Paid]);

    RelaisStatusChanged::dispatch($this->relais, 'validated', 'rejected');

    expect($due->refresh()->status)->toBe(CommissionStatus::Cancelled)
        ->and($due->cancel_reason)->not->toBeNull()
        ->and($paid->refresh()->status)->toBe(CommissionStatus::Paid);
});

it('paie toutes les commissions dues d’un Relais vérifié, trace le paiement et prévient le Relais [ADM-27]', function () {
    verifyIdentityOf($this->user);
    $first = Commission::factory()->create(['relais_item_id' => $this->relais->id, 'amount' => 2000]);
    $second = Commission::factory()->create(['relais_item_id' => $this->relais->id, 'kind' => 'broadcast', 'amount' => 3000]);

    Livewire::actingAs($this->admin)->test(GainsPage::class)
        ->assertSee('5 000 FCFA')
        ->call('openPay', $this->user->uuid)
        ->assertSet('paying', true)
        ->call('pay')
        ->assertHasErrors('reference')
        ->set('method', PaymentMethod::OrangeMoney->value)
        ->set('reference', 'OM-2026-0042')
        ->call('pay')
        ->assertHasNoErrors()
        ->assertSet('paying', false);

    foreach ([$first, $second] as $commission) {
        $commission->refresh();
        expect($commission->status)->toBe(CommissionStatus::Paid)
            ->and($commission->payment_method)->toBe(PaymentMethod::OrangeMoney)
            ->and($commission->payment_reference)->toBe('OM-2026-0042')
            ->and($commission->paid_by)->toBe($this->admin->id);
    }

    $payout = Payout::query()->sole();
    expect($payout->amount)->toBe(5000)
        ->and($payout->reference)->toBe('OM-2026-0042')
        ->and($first->payout_id)->toBe($payout->id)
        ->and(AuditLog::query()->where('action', 'commissions.paid')->sole()->payload)->toMatchArray(['amount' => 5000, 'method' => 'orange_money'])
        ->and(collect($this->sms->sent)->last()['message'])->toContain('5 000 FCFA')->toContain('Orange Money');
});

it('bloque le paiement d’un Relais dont l’identité n’est pas vérifiée [MOB-01, ADM-27]', function () {
    $commission = Commission::factory()->create(['relais_item_id' => $this->relais->id]);

    Livewire::actingAs($this->admin)->test(GainsPage::class)
        ->assertSee('Identité non vérifiée')
        ->call('openPay', $this->user->uuid)
        ->set('reference', 'W-1')
        ->call('pay')
        ->assertHasErrors('reference');

    expect($commission->refresh()->status)->toBe(CommissionStatus::Due);
});

it('annule une commission avec un motif obligatoire [ADM-27]', function () {
    $commission = Commission::factory()->create(['relais_item_id' => $this->relais->id]);

    Livewire::actingAs($this->admin)->test(GainsPage::class)
        ->call('askCancel', $commission->uuid)
        ->call('cancel')
        ->assertHasErrors('cancelReason')
        ->set('cancelReason', 'Doublon d’un autre relais.')
        ->call('cancel')
        ->assertHasNoErrors();

    expect($commission->refresh()->status)->toBe(CommissionStatus::Cancelled)
        ->and(AuditLog::query()->where('action', 'commissions.cancelled')->exists())->toBeTrue();
});

it('réserve la page Paiements à l’administration [Annexe A]', function () {
    $this->actingAs($this->admin)->get(route('console.gains'))->assertOk()->assertSee('Paiements');

    foreach ([Role::RedacteurEnChef, Role::Journaliste] as $role) {
        $this->actingAs(User::factory()->withTwoFactor()->withRole($role)->create())->get(route('console.gains'))->assertForbidden();
    }
});

it('montre au Relais ses gains dans l’application, sans les commissions annulées [MOB-26]', function () {
    Commission::factory()->create(['relais_item_id' => $this->relais->id, 'amount' => 2000]);
    Commission::factory()->create(['relais_item_id' => $this->relais->id, 'kind' => 'broadcast', 'amount' => 3000, 'status' => CommissionStatus::Paid, 'payment_method' => PaymentMethod::Wave, 'payment_reference' => 'W-9']);
    Commission::factory()->create(['user_id' => $this->user->id, 'relais_item_id' => RelaisItem::factory()->create(['user_id' => $this->user->id])->id, 'status' => CommissionStatus::Cancelled]);
    Commission::factory()->create();

    Sanctum::actingAs($this->user);

    $this->getJson(route('api.v1.me.gains'))
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('meta.totals.due', 2000)
        ->assertJsonPath('meta.totals.paid', 3000)
        ->assertJsonPath('meta.gains_eligible', false)
        ->assertJsonPath('data.0.status', 'paid')
        ->assertJsonPath('data.0.payment_method_label', 'Wave')
        ->assertJsonPath('data.0.relais.title', 'Inondation à Niangon')
        ->assertJsonPath('data.1.amount', 2000);
});

it('laisse le Relais enregistrer ses coordonnées Mobile Money, reprises par l’administration au paiement [MOB-26]', function () {
    Sanctum::actingAs($this->user);

    $this->getJson(route('api.v1.me.payout-account'))->assertOk()->assertJsonPath('data', null)->assertJsonPath('meta.methods.0.value', 'wave');

    $this->putJson(route('api.v1.me.payout-account'), ['method' => 'cash', 'phone' => '12', 'holder_name' => ''])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['method', 'phone', 'holder_name']);

    $this->putJson(route('api.v1.me.payout-account'), ['method' => 'orange_money', 'phone' => '0707070707', 'holder_name' => 'Awa Traoré'])
        ->assertOk()
        ->assertJsonPath('data.method', 'orange_money')
        ->assertJsonPath('data.method_label', 'Orange Money')
        ->assertJsonPath('data.phone', '+2250707070707')
        ->assertJsonPath('data.holder_name', 'Awa Traoré');

    $this->putJson(route('api.v1.me.payout-account'), ['method' => 'wave', 'phone' => '+2250102030405', 'holder_name' => 'Awa Traoré'])->assertOk();

    expect(PayoutAccount::query()->where('user_id', $this->user->id)->sole()->method->value)->toBe('wave');

    Commission::factory()->create(['relais_item_id' => $this->relais->id]);

    Livewire::actingAs($this->admin)->test(GainsPage::class)
        ->assertSee('+2250102030405')
        ->call('openPay', $this->user->uuid)
        ->assertSet('method', 'wave')
        ->assertSet('phone', '+2250102030405');
});

it('donne au Relais l’historique de ses paiements reçus et filtre ses commissions [MOB-26]', function () {
    verifyIdentityOf($this->user);
    Commission::factory()->create(['relais_item_id' => $this->relais->id, 'amount' => 2000]);
    app(PayCommissions::class)->pay($this->user, $this->admin, PaymentMethod::Wave, 'W-77', '+2250707070707');
    Commission::factory()->create(['relais_item_id' => $this->relais->id, 'kind' => 'broadcast', 'amount' => 3000]);

    Sanctum::actingAs($this->user);

    $this->getJson(route('api.v1.me.payouts'))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.amount', 2000)
        ->assertJsonPath('data.0.method_label', 'Wave')
        ->assertJsonPath('data.0.reference', 'W-77')
        ->assertJsonPath('data.0.commissions_count', 1)
        ->assertJsonPath('data.0.commissions.0.relais.title', 'Inondation à Niangon')
        ->assertJsonPath('meta.total_received', 2000);

    $this->getJson(route('api.v1.me.gains', ['status' => 'due']))->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.amount', 3000);
    $this->getJson(route('api.v1.me.gains', ['status' => 'paid']))->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.amount', 2000)
        ->assertJsonPath('meta.gains_eligible', true)
        ->assertJsonPath('meta.payout_account_set', false);

    Livewire::actingAs($this->admin)->test(GainsPage::class)->set('tab', 'historique')->assertSee('W-77')->assertSee('2 000 FCFA');
});
