<?php

namespace App\Domain\Privacy\Actions;

use App\Domain\Candidature\Models\Candidature;
use App\Domain\Candidature\Models\CandidatureDocument;
use App\Domain\Contribution\Models\RelaisItem;
use App\Domain\Formation\Models\QuizAttempt;
use App\Domain\Legal\Models\Consent;
use App\Domain\Messagerie\Models\Message;
use App\Domain\Relais\Models\RelaisProfile;
use App\Models\User;

/**
 * Droit d'accès (§9.4) : toutes les données personnelles d'un compte, dans un format lisible et réutilisable.
 * Les notes internes de l'équipe et les données des autres personnes n'y figurent pas.
 */
class ExportPersonalData
{
    /**
     * @return array<string, mixed>
     */
    public function handle(User $user): array
    {
        $candidature = Candidature::withoutCommuneRestriction()->with(['commune:id,name', 'localite:id,name', 'documents'])->where('user_id', $user->id)->first();
        $profile = RelaisProfile::withoutCommuneRestriction()->with('commune:id,name')->where('user_id', $user->id)->first();

        return [
            'generated_at' => now()->toIso8601String(),
            'account' => [
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'status' => $user->status->value,
                'created_at' => $user->created_at?->toIso8601String(),
                'last_login_at' => $user->last_login_at?->toIso8601String(),
            ],
            'candidature' => $candidature === null ? null : [
                'status' => $candidature->status->value,
                'last_name' => $candidature->last_name,
                'first_names' => $candidature->first_names,
                'birth_date' => $candidature->birth_date?->toDateString(),
                'gender' => $candidature->gender?->value,
                'email' => $candidature->email,
                'commune' => $candidature->commune?->name,
                'localite' => $candidature->localite?->name,
                'occupation' => $candidature->occupation?->value,
                'languages' => $candidature->languages,
                'interests' => $candidature->interests,
                'motivation' => $candidature->motivation,
                'submitted_at' => $candidature->submitted_at?->toIso8601String(),
                'documents' => $candidature->documents->map(fn (CandidatureDocument $document): array => [
                    'type' => $document->type->value, 'sha256' => $document->sha256, 'uploaded_at' => $document->created_at?->toIso8601String(),
                ])->values(),
            ],
            'consents' => Consent::query()->with('legalDocument')->where('user_id', $user->id)->get()->map(fn (Consent $consent): array => [
                'document' => $consent->legalDocument->type->value, 'version' => $consent->legalDocument->version, 'accepted_at' => $consent->accepted_at->toIso8601String(), 'channel' => $consent->channel,
            ])->values(),
            'quiz_attempts' => QuizAttempt::query()->where('user_id', $user->id)->get()->map(fn (QuizAttempt $attempt): array => [
                'score' => $attempt->score, 'passed' => $attempt->passed, 'at' => $attempt->created_at?->toIso8601String(),
            ])->values(),
            'relais_profile' => $profile === null ? null : [
                'number' => $profile->number, 'display_name' => $profile->display_name, 'commune' => $profile->commune?->name,
                'status' => $profile->status->value, 'activated_at' => $profile->activated_at->toIso8601String(),
            ],
            'relais' => RelaisItem::withoutCommuneRestriction()->with(['commune:id,name', 'category:id,name', 'medias'])->where('user_id', $user->id)->get()->map(fn (RelaisItem $relais): array => [
                'title' => $relais->title,
                'description' => $relais->description,
                'category' => $relais->category?->name,
                'commune' => $relais->commune?->name,
                'status' => $relais->state->publicName(),
                'location' => $relais->location === null ? null : ['lat' => $relais->location->getLatitude(), 'lng' => $relais->location->getLongitude()],
                'credit_mode' => $relais->credit_mode?->value,
                'credit_name' => $relais->credit_name,
                'submitted_at' => $relais->submitted_at?->toIso8601String(),
                'medias' => $relais->medias->map(fn ($media): array => ['kind' => $media->kind->value, 'sha256' => $media->sha256, 'size' => $media->size])->values(),
            ])->values(),
            'messages_sent' => Message::query()->where('sender_id', $user->id)->get()->map(fn (Message $message): array => [
                'body' => $message->body, 'sent_at' => $message->created_at->toIso8601String(),
            ])->values(),
        ];
    }
}
