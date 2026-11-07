<?php

namespace App\Domain\Contribution\Actions;

use App\Domain\Audit\AuditLogger;
use App\Domain\Contribution\Enums\PeopleConsent;
use App\Domain\Contribution\Enums\RelaisDecision;
use App\Domain\Contribution\Exceptions\RelaisLocked;
use App\Domain\Contribution\Models\Category;
use App\Domain\Contribution\Models\RelaisItem;
use App\Domain\Contribution\States\InReview;
use App\Domain\Validation\Enums\RejectionTarget;
use App\Domain\Validation\Models\RejectionReason;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Décision du coordonnateur sur un relais en cours de vérification (COO-14 à COO-16) :
 * valider (avec corrections éventuelles et indication de floutage obligatoire si des personnes sont identifiables),
 * demander un complément, rejeter avec un motif de la liste, ou transmettre à la rédaction comme sujet sensible.
 */
class DecideRelaisItem
{
    public function __construct(
        private readonly TransitionRelaisItem $transition,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * @param  array{title?: string|null, description?: string|null, category?: string|null, declared_place?: string|null}  $corrections
     */
    public function handle(
        RelaisItem $relais,
        RelaisDecision $decision,
        User $decider,
        ?string $comment = null,
        ?RejectionReason $reason = null,
        ?bool $blurRequired = null,
        array $corrections = [],
    ): RelaisItem {
        if (! $relais->state instanceof InReview) {
            throw new InvalidArgumentException(__('relais_items.errors.not_in_review'));
        }

        if ($relais->isLockedFor($decider)) {
            throw RelaisLocked::by($relais);
        }

        $comment = $comment === null ? null : (trim($comment) ?: null);

        match ($decision) {
            RelaisDecision::Reject => $reason === null || $reason->applies_to !== RejectionTarget::Relais || ! $reason->is_active
                ? throw new InvalidArgumentException(__('relais_items.errors.reason_required')) : null,
            RelaisDecision::RequestComplement, RelaisDecision::Sensitive => $comment === null
                ? throw new InvalidArgumentException(__('relais_items.errors.comment_required')) : null,
            RelaisDecision::Validate => $this->requiresBlurAnswer($relais) && $blurRequired === null
                ? throw new InvalidArgumentException(__('relais_items.errors.blur_required')) : null,
        };

        DB::transaction(function () use ($relais, $decision, $decider, $comment, $reason, $blurRequired, $corrections): void {
            $changes = $decision === RelaisDecision::Validate ? $this->applyCorrections($relais, $corrections) : [];

            $relais->forceFill([
                'rejection_reason_id' => $decision === RelaisDecision::Reject ? $reason?->id : null,
                'decision_comment' => $comment,
                'blur_required' => $decision === RelaisDecision::Validate ? ($blurRequired ?? false) : $relais->blur_required,
                'locked_by' => null,
                'locked_until' => null,
            ])->save();

            $this->transition->handle($relais, $decision->state(), $decider, $reason->label ?? $comment);

            $this->audit->record($decision->auditAction(), $relais, array_filter([
                'reason' => $reason?->label,
                'comment' => $comment,
                'blur_required' => $decision === RelaisDecision::Validate ? $relais->blur_required : null,
                'corrections' => $changes === [] ? null : $changes,
            ], fn (mixed $value): bool => $value !== null), actor: $decider, communeId: $relais->commune_id);
        });

        return $relais;
    }

    /**
     * COO-16 : mineurs ou personnes identifiables → le coordonnateur doit dire si un floutage est nécessaire.
     */
    public function requiresBlurAnswer(RelaisItem $relais): bool
    {
        return $relais->has_minors === true || in_array($relais->people_consent, [PeopleConsent::Yes, PeopleConsent::No], true);
    }

    /**
     * Validation avec corrections (titre, description, catégorie, lieu) : l'avant/après est conservé dans l'audit.
     *
     * @param  array{title?: string|null, description?: string|null, category?: string|null, declared_place?: string|null}  $corrections
     * @return array<string, array{from: mixed, to: mixed}>
     */
    private function applyCorrections(RelaisItem $relais, array $corrections): array
    {
        $changes = [];

        foreach (Arr::only($corrections, ['title', 'description', 'declared_place']) as $field => $value) {
            $value = $value === null ? null : (trim($value) ?: null);

            if ($value !== $relais->getAttribute($field)) {
                $changes[$field] = ['from' => $relais->getAttribute($field), 'to' => $value];
                $relais->setAttribute($field, $value);
            }
        }

        if (array_key_exists('category', $corrections) && $corrections['category'] !== null) {
            $category = Category::query()->where('uuid', $corrections['category'])->first();

            if ($category !== null && $category->id !== $relais->category_id) {
                $changes['category'] = ['from' => $relais->category?->name, 'to' => $category->name];
                $relais->category()->associate($category);
            }
        }

        return $changes;
    }
}
