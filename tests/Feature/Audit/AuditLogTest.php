<?php

use App\Domain\Audit\AuditLogger;
use App\Domain\Audit\Models\AuditLog;
use App\Domain\Audit\Support\AuditChainVerifier;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

it('inscrit une action avec son auteur et son sujet [ADM-25]', function () {
    $actor = User::factory()->create();
    $subject = User::factory()->create();

    $entry = app(AuditLogger::class)->record('users.suspended', $subject, ['motif' => 'Fausse information'], $actor);

    expect($entry->actor_id)->toBe($actor->id)
        ->and($entry->subject_type)->toBe('user')
        ->and($entry->subject_id)->toBe($subject->uuid)
        ->and($entry->payload)->toBe(['motif' => 'Fausse information'])
        ->and($entry->hash)->toHaveLength(64);
});

it('refuse toute modification au niveau de la base (trigger) [ADM-25]', function () {
    app(AuditLogger::class)->record('test.action');

    expect(fn () => DB::table('audit_logs')->update(['action' => 'falsifie']))->toThrow(QueryException::class, 'ajout seul');
});

it('refuse toute suppression au niveau de la base (trigger) [ADM-25]', function () {
    app(AuditLogger::class)->record('test.action');

    expect(fn () => DB::table('audit_logs')->delete())->toThrow(QueryException::class, 'ajout seul');
});

it('refuse toute modification au niveau du modèle', function () {
    $entry = app(AuditLogger::class)->record('test.action');

    expect(fn () => $entry->update(['action' => 'falsifie']))->toThrow(LogicException::class);
    expect(fn () => $entry->delete())->toThrow(LogicException::class);
});

it('chaîne chaque ligne à la précédente et vérifie l’intégrité [§10 Intégrité]', function () {
    $logger = app(AuditLogger::class);
    $first = $logger->record('a');
    $second = $logger->record('b');
    $third = $logger->record('c');

    expect($first->prev_hash)->toBeNull()
        ->and($second->prev_hash)->toBe($first->hash)
        ->and($third->prev_hash)->toBe($second->hash)
        ->and(app(AuditChainVerifier::class)->verify())->toBe(['checked' => 3, 'broken_at' => null]);

    $this->artisan('audit:verify')->expectsOutputToContain('3 lignes vérifiées')->assertSuccessful();
});

it('détecte une ligne altérée hors application', function () {
    $logger = app(AuditLogger::class);
    $logger->record('a');
    $tampered = $logger->record('b');
    $logger->record('c');

    DB::unprepared('ALTER TABLE audit_logs DISABLE TRIGGER audit_logs_no_update_delete');
    DB::table('audit_logs')->where('id', $tampered->id)->update(['action' => 'falsifie']);
    DB::unprepared('ALTER TABLE audit_logs ENABLE TRIGGER audit_logs_no_update_delete');

    expect(app(AuditChainVerifier::class)->verify()['broken_at'])->toBe($tampered->id);
    $this->artisan('audit:verify')->assertFailed();
});

it('n’écrit jamais de secret ni de donnée personnelle sensible', function () {
    $entry = app(AuditLogger::class)->record('test.sensible', payload: [
        'password' => 'secret',
        'code' => '123456',
        'phone' => '+2250707070707',
        'details' => ['latitude' => 5.3, 'token' => 'abc', 'commune' => 'Yopougon'],
    ]);

    expect($entry->payload)->toBe([
        'password' => '[retiré]',
        'code' => '[retiré]',
        'phone' => '[retiré]',
        'details' => ['latitude' => '[retiré]', 'token' => '[retiré]', 'commune' => 'Yopougon'],
    ]);
    expect(AuditLog::query()->sole()->toJson())->not->toContain('123456');
});
