<?php

namespace App\Domain\Legal\Models;

use App\Domain\Legal\Enums\LegalDocumentType;
use App\Models\Concerns\HasPublicUuid;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

/**
 * Version d'un texte juridique. Une version publiée n'est jamais modifiée : on en publie une nouvelle.
 *
 * @property int $id
 * @property string $uuid
 * @property LegalDocumentType $type
 * @property int $version
 * @property string $title
 * @property string $body
 * @property CarbonImmutable $published_at
 * @property int|null $published_by
 */
#[Fillable(['type', 'version', 'title', 'body', 'published_at', 'published_by'])]
class LegalDocument extends Model
{
    use HasPublicUuid;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => LegalDocumentType::class,
            'version' => 'integer',
            'published_at' => 'immutable_datetime',
        ];
    }

    public static function current(LegalDocumentType $type): ?self
    {
        return static::query()->where('type', $type->value)->orderByDesc('version')->first();
    }

    /**
     * Version en vigueur de chaque texte, dans l'ordre d'affichage.
     *
     * @return array<string, self>
     */
    public static function currentSet(): array
    {
        $set = [];

        foreach (LegalDocumentType::cases() as $type) {
            $document = static::current($type);

            if ($document !== null) {
                $set[$type->value] = $document;
            }
        }

        return $set;
    }

    /**
     * Rendu du texte (Markdown) sans HTML saisi : aucun script ne peut y être injecté.
     */
    public function html(): HtmlString
    {
        return new HtmlString(Str::markdown($this->body, ['html_input' => 'strip', 'allow_unsafe_links' => false]));
    }

    /**
     * @param  Builder<LegalDocument>  $query
     */
    #[Scope]
    protected function ofType(Builder $query, LegalDocumentType $type): void
    {
        $query->where('type', $type->value);
    }
}
