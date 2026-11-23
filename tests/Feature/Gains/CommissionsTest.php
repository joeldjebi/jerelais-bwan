<?php

use App\Domain\Access\Enums\Role;
use App\Domain\Audit\Models\AuditLog;
use App\Domain\Contribution\Events\RelaisStatusChanged;
use App\Domain\Contribution\Models\RelaisItem;
use App\Domain\Gains\Actions\ManageWithdrawals;
use App\Domain\Gains\Enums\CommissionStatus;
use App\Domain\Gains\Enums\PayoutStatus;
use App\Domain\Gains\Models\Commission;
use App\Domain\Gains\Models\PayoutAccount;
use App\Domain\Identity\Enums\IdentityStatus;
use App\Domain\Identity\Models\IdentityVerification;
use App\Domain\Settings\Settings;
use App\Domain\Territoire\Models\Commune;
use App\Livewire\Console\Gains\Index as GainsPage;
use App\Models\User;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Livewire\Livewire;

/*
 * Portefeuille des Relais (MOB-26, ADM-27) : gain crédité à la diffusion, retrait demandé depuis l'application,
 * payé par Mobile Money (ou refusé) depuis la console.
 */

beforeEach(function () {
    $this->sms = fakeSms();
    $this->admin = User::factory()->withTwoFactor()->withRole(Role::Admin)->create();
    app(Settings::class)->set('gains.min_withdrawal', 1000);
    $this->relais = RelaisItem::factory()->create(['commune_id' => Commune::factory()->create()->id, 'title' => 'Inondation à Niangon']);
    $this->user = $this->relais->user;
});

function verifyIdentityOf(User $user): void
{
    IdentityVerification::query()->forceCreate(['user_id' => $user->id, 'status' => IdentityStatus::Verified]);
}

function readyForWithdrawal(User $user): void
{
    verifyIdentityOf($user);
    PayoutAccount::query()->create(['user_id' => $user->id, 'method' => 'orange_money', 'phone' => '+2250707070707', 'holder_name' => 'Awa Traoré']);
}

it('ne crédite jamais le portefeuille automatiquement, ni à la validation ni à la diffusion [ADM-27]', function () {
    RelaisStatusChanged::dispatch($this->relais, 'in_review', 'validated');
    RelaisStatusChanged::dispatch($this->relais, 'selected', 'broadcast');

    expect(Commission::query()->count())->toBe(0);
});

it('montre le portefeuille et explique pourquoi un retrait est encore impossible [MOB-26]', function () {
    Commission::factory()->create(['relais_item_id' => $this->relais->id, 'kind' => 'broadcast', 'amount' => 500]);
    Sanctum::actingAs($this->user);

    $this->getJson(route('api.v1.me.wallet'))
        ->assertOk()
        ->assertJsonPath('data.earned', 500)
        ->assertJsonPath('data.available', 500)
        ->assertJsonPath('data.min_withdrawal', 1000)
        ->assertJsonPath('data.can_withdraw', false)
        ->assertJsonPath('data.blockers.*.code', ['identity', 'payout_account', 'balance']);

    $this->postJson(route('api.v1.me.wallet.withdraw'), ['amount' => 500])->assertUnprocessable()->assertJsonValidationErrors('amount');
});

it('retire immédiatement vers le numéro choisi, en simulation, et prévient le Relais [MOB-26]', function () {
    readyForWithdrawal($this->user);
    $wave = PayoutAccount::query()->create(['user_id' => $this->user->id, 'method' => 'wave', 'phone' => '+2250505050505', 'holder_name' => 'Awa Traoré']);
    Commission::factory()->create(['relais_item_id' => $this->relais->id, 'kind' => 'broadcast', 'amount' => 3000]);
    Sanctum::actingAs($this->user);

    $this->postJson(route('api.v1.me.wallet.withdraw'), ['amount' => 500])->assertUnprocessable()->assertJsonPath('errors.amount.0', 'Le retrait minimum est de 1 000 FCFA.');
    $this->postJson(route('api.v1.me.wallet.withdraw'), ['amount' => 5000])->assertUnprocessable()->assertJsonValidationErrors('amount');
    $this->postJson(route('api.v1.me.wallet.withdraw'), ['amount' => 1000, 'payout_account' => (string) Str::uuid()])->assertUnprocessable()->assertJsonValidationErrors('amount');

    $this->postJson(route('api.v1.me.wallet.withdraw'), ['amount' => 2000, 'payout_account' => $wave->uuid])
        ->assertCreated()
        ->assertJsonPath('data.status', 'paid')
        ->assertJsonPath('data.provider', 'simulation')
        ->assertJsonPath('data.simulated', true)
        ->assertJsonPath('data.method', 'wave')
        ->assertJsonPath('data.phone', '+2250505050505');

    $this->postJson(route('api.v1.me.wallet.withdraw'), ['amount' => 1000])
        ->assertCreated()
        ->assertJsonPath('data.status', 'paid')
        ->assertJsonPath('data.phone', '+2250707070707');

    expect(collect($this->sms->sent)->last()['message'])->toContain('1 000 FCFA')->toContain('Orange Money')->toContain('+2250707070707')->toContain('SIM-');

    $this->getJson(route('api.v1.me.wallet'))->assertJsonPath('data.available', 0)->assertJsonPath('data.withdrawn', 3000)->assertJsonPath('data.pending', 0);
    $this->getJson(route('api.v1.me.payouts'))
        ->assertJsonPath('data.0.phone', '+2250707070707')
        ->assertJsonPath('data.1.phone', '+2250505050505')
        ->assertJsonPath('data.1.method_label', 'Wave');
});

it('garde le retrait en attente quand la passerelle ne paie pas, une seule demande à la fois [MOB-26]', function () {
    config(['jerelais.payouts.driver' => 'manual']);
    readyForWithdrawal($this->user);
    Commission::factory()->create(['relais_item_id' => $this->relais->id, 'kind' => 'broadcast', 'amount' => 3000]);
    Sanctum::actingAs($this->user);

    $this->postJson(route('api.v1.me.wallet.withdraw'), ['amount' => 2000])->assertCreated()->assertJsonPath('data.status', 'requested');
    $this->postJson(route('api.v1.me.wallet.withdraw'), ['amount' => 1000])->assertUnprocessable()->assertJsonPath('errors.amount.0', 'Un retrait est déjà en cours : attendez qu’il soit traité.');

    $this->getJson(route('api.v1.me.wallet'))->assertJsonPath('data.available', 1000)->assertJsonPath('data.pending', 2000)->assertJsonPath('data.pending_withdrawal.amount', 2000);
});

it('laisse le Relais enregistrer deux numéros de retrait au plus, et ne gérer que les siens [MOB-26]', function () {
    Sanctum::actingAs($this->user);
    $fields = ['method' => 'orange_money', 'phone' => '0707070707', 'holder_name' => 'Awa Traoré'];

    $first = $this->postJson(route('api.v1.me.payout-accounts.store'), $fields)->assertCreated()->json('data.uuid');
    $this->postJson(route('api.v1.me.payout-accounts.store'), ['method' => 'wave', 'phone' => '0505050505', 'holder_name' => 'Awa Traoré'])->assertCreated();
    $this->postJson(route('api.v1.me.payout-accounts.store'), $fields)->assertUnprocessable()->assertJsonValidationErrors('phone');

    $this->getJson(route('api.v1.me.payout-accounts'))
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('meta.max', 2)
        ->assertJsonPath('data.0.phone', '+2250707070707')
        ->assertJsonPath('data.1.method_label', 'Wave');
    $this->getJson(route('api.v1.me.wallet'))->assertJsonCount(2, 'data.payout_accounts')->assertJsonPath('data.payout_account.uuid', $first);

    $this->putJson(route('api.v1.me.payout-accounts.update', $first), ['method' => 'mtn_momo', 'phone' => '0545454545', 'holder_name' => 'Awa T.'])
        ->assertOk()->assertJsonPath('data.method_label', 'MTN MoMo');
    $this->deleteJson(route('api.v1.me.payout-accounts.destroy', $first))->assertNoContent();
    expect($this->user->payoutAccounts()->count())->toBe(1);

    $other = PayoutAccount::query()->create(['user_id' => User::factory()->create()->id, 'method' => 'wave', 'phone' => '+2250101010101', 'holder_name' => 'Autre']);
    $this->putJson(route('api.v1.me.payout-accounts.update', $other->uuid), $fields)->assertNotFound();
    $this->deleteJson(route('api.v1.me.payout-accounts.destroy', $other->uuid))->assertNotFound();
});

it('montre dans la console les numéros enregistrés et le numéro crédité de chaque retrait [ADM-27]', function () {
    readyForWithdrawal($this->user);
    PayoutAccount::query()->create(['user_id' => $this->user->id, 'method' => 'wave', 'phone' => '+2250505050505', 'holder_name' => 'Awa Traoré']);
    Commission::factory()->create(['relais_item_id' => $this->relais->id, 'kind' => 'broadcast', 'amount' => 3000]);
    app(ManageWithdrawals::class)->request($this->user, 2000);

    Livewire::actingAs($this->admin)->test(GainsPage::class)
        ->set('tab', 'historique')
        ->assertSee('+2250707070707')
        ->assertSee('Simulation')
        ->set('tab', 'soldes')
        ->assertSee('Orange Money +2250707070707')
        ->assertSee('Wave +2250505050505');
});

it('paie une demande de retrait depuis la console, trace le paiement et prévient le Relais [ADM-27]', function () {
    config(['jerelais.payouts.driver' => 'manual']);
    readyForWithdrawal($this->user);
    Commission::factory()->create(['relais_item_id' => $this->relais->id, 'kind' => 'broadcast', 'amount' => 3000]);
    $payout = app(ManageWithdrawals::class)->request($this->user, 3000);

    Livewire::actingAs($this->admin)->test(GainsPage::class)
        ->assertSee('3 000 FCFA')
        ->call('openPay', $payout->uuid)
        ->assertSet('method', 'orange_money')
        ->assertSet('phone', '+2250707070707')
        ->call('pay')
        ->assertHasErrors('reference')
        ->set('reference', 'OM-2026-0042')
        ->call('pay')
        ->assertHasNoErrors()
        ->assertSet('paying', false);

    $payout->refresh();
    expect($payout->status)->toBe(PayoutStatus::Paid)
        ->and($payout->reference)->toBe('OM-2026-0042')
        ->and($payout->paid_by)->toBe($this->admin->id)
        ->and(AuditLog::query()->where('action', 'withdrawal.paid')->sole()->payload)->toMatchArray(['amount' => 3000, 'method' => 'orange_money'])
        ->and(collect($this->sms->sent)->last()['message'])->toContain('3 000 FCFA')->toContain('Orange Money');

    Sanctum::actingAs($this->user);
    $this->getJson(route('api.v1.me.wallet'))->assertJsonPath('data.available', 0)->assertJsonPath('data.withdrawn', 3000);
    $this->getJson(route('api.v1.me.payouts'))->assertJsonPath('data.0.status', 'paid')->assertJsonPath('data.0.reference', 'OM-2026-0042')->assertJsonPath('meta.total_received', 3000);
});

it('refuse une demande de retrait avec un motif : la somme revient dans le portefeuille [ADM-27]', function () {
    config(['jerelais.payouts.driver' => 'manual']);
    readyForWithdrawal($this->user);
    Commission::factory()->create(['relais_item_id' => $this->relais->id, 'kind' => 'broadcast', 'amount' => 3000]);
    $payout = app(ManageWithdrawals::class)->request($this->user, 2000);

    Livewire::actingAs($this->admin)->test(GainsPage::class)
        ->call('openReject', $payout->uuid)
        ->call('reject')
        ->assertHasErrors('rejectReason')
        ->set('rejectReason', 'Numéro injoignable.')
        ->call('reject')
        ->assertHasNoErrors();

    expect($payout->refresh()->status)->toBe(PayoutStatus::Rejected)->and($payout->rejection_reason)->toBe('Numéro injoignable.');

    Sanctum::actingAs($this->user);
    $this->getJson(route('api.v1.me.wallet'))->assertJsonPath('data.available', 3000)->assertJsonPath('data.pending', 0);
});

it('annule un gain encore disponible avec un motif, jamais un gain déjà retiré [ADM-27]', function () {
    readyForWithdrawal($this->user);
    $first = Commission::factory()->create(['relais_item_id' => $this->relais->id, 'kind' => 'broadcast', 'amount' => 2000]);

    Livewire::actingAs($this->admin)->test(GainsPage::class)
        ->set('tab', 'soldes')
        ->call('openHolder', $this->user->uuid)
        ->call('askCancel', $first->uuid)
        ->call('cancel')
        ->assertHasErrors('cancelReason')
        ->set('cancelReason', 'Doublon d’un autre relais.')
        ->call('cancel')
        ->assertHasNoErrors();

    expect($first->refresh()->status)->toBe(CommissionStatus::Cancelled);

    $second = Commission::factory()->create(['relais_item_id' => RelaisItem::factory()->create(['user_id' => $this->user->id])->id, 'kind' => 'broadcast', 'amount' => 2000]);
    app(ManageWithdrawals::class)->request($this->user, 2000);

    Livewire::actingAs($this->admin)->test(GainsPage::class)
        ->call('askCancel', $second->uuid)
        ->set('cancelReason', 'Essai')
        ->call('cancel')
        ->assertHasErrors('cancelReason');

    expect($second->refresh()->status)->toBe(CommissionStatus::Due);
});

it('réserve la page Paiements à l’administration [Annexe A]', function () {
    $this->actingAs($this->admin)->get(route('console.gains'))->assertOk()->assertSee('Paiements');

    foreach ([Role::RedacteurEnChef, Role::Journaliste] as $role) {
        $this->actingAs(User::factory()->withTwoFactor()->withRole($role)->create())->get(route('console.gains'))->assertForbidden();
    }
});

it('laisse le Relais enregistrer ses coordonnées Mobile Money [MOB-26]', function () {
    Sanctum::actingAs($this->user);

    $this->getJson(route('api.v1.me.payout-account'))->assertOk()->assertJsonPath('data', null)->assertJsonPath('meta.methods.0.value', 'wave');

    $this->putJson(route('api.v1.me.payout-account'), ['method' => 'cash', 'phone' => '12', 'holder_name' => ''])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['method', 'phone', 'holder_name']);

    $this->putJson(route('api.v1.me.payout-account'), ['method' => 'orange_money', 'phone' => '0707070707', 'holder_name' => 'Awa Traoré'])
        ->assertOk()
        ->assertJsonPath('data.method_label', 'Orange Money')
        ->assertJsonPath('data.phone', '+2250707070707');

    expect(PayoutAccount::query()->where('user_id', $this->user->id)->sole()->holder_name)->toBe('Awa Traoré');
});

it('liste les gains crédités du Relais, sans les gains annulés [MOB-26]', function () {
    Commission::factory()->create(['relais_item_id' => $this->relais->id, 'kind' => 'broadcast', 'amount' => 2000]);
    Commission::factory()->create(['user_id' => $this->user->id, 'relais_item_id' => RelaisItem::factory()->create(['user_id' => $this->user->id])->id, 'kind' => 'broadcast', 'status' => CommissionStatus::Cancelled]);
    Sanctum::actingAs($this->user);

    $this->getJson(route('api.v1.me.gains'))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.amount', 2000)
        ->assertJsonPath('data.0.relais.title', 'Inondation à Niangon')
        ->assertJsonPath('meta.totals.earned', 2000);
});
