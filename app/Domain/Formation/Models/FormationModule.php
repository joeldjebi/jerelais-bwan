<?php

namespace App\Domain\Formation\Models;

use App\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

/**
 * Fiche de formation (bien filmer, sécurité, droit à l'image, fausses informations).
 *
 * @property int $id
 * @property string $uuid
 * @property string $slug
 * @property string $title
 * @property string $summary
 * @property string $body
 * @property string $icon
 * @property string|null $video_url
 * @property int $position
 * @property bool $is_active
 */
#[Fillable(['slug', 'title', 'summary', 'body', 'icon', 'video_url', 'position', 'is_active'])]
class FormationModule extends Model
{
    use HasPublicUuid;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function html(): HtmlString
    {
        return new HtmlString(Str::markdown($this->body, ['html_input' => 'strip', 'allow_unsafe_links' => false]));
    }

    /**
     * @param  Builder<FormationModule>  $query
     */
    #[Scope]
    protected function published(Builder $query): void
    {
        $query->where('is_active', true)->orderBy('position');
    }
}
