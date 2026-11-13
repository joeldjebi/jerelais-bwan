<?php

namespace App\Domain\Identity\Models;

use App\Domain\Identity\Enums\IdentityFileKind;
use App\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Pièce d'identité acceptée pour la vérification (MOB-01), gérée depuis la console (Référentiels).
 * Le `slug` est la valeur échangée avec l'application (`document_type`) ; il ne change jamais.
 *
 * @property int $id
 * @property string $uuid
 * @property string $slug
 * @property string $name
 * @property bool $two_sided
 * @property int $position
 * @property bool $is_active
 */
#[Fillable(['slug', 'name', 'two_sided', 'position', 'is_active'])]
class IdentityDocumentType extends Model
{
    use HasPublicUuid;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'two_sided' => 'boolean',
            'position' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Faces de la pièce à envoyer : recto et verso, ou une seule page.
     *
     * @return list<IdentityFileKind>
     */
    public function sides(): array
    {
        return $this->two_sided ? [IdentityFileKind::Recto, IdentityFileKind::Verso] : [IdentityFileKind::Page];
    }

    /**
     * Tous les fichiers exigés : faces de la pièce et selfie (qui sert aussi de photo du badge).
     *
     * @return list<IdentityFileKind>
     */
    public function requiredFiles(): array
    {
        return [...$this->sides(), IdentityFileKind::Selfie];
    }

    /**
     * Format exposé à l'application et au site : valeur, libellé et faces.
     *
     * @return array{value: string, label: string, sides: list<string>}
     */
    public function toOption(): array
    {
        return [
            'value' => $this->slug,
            'label' => $this->name,
            'sides' => array_map(fn (IdentityFileKind $kind): string => $kind->value, $this->sides()),
        ];
    }

    /**
     * Pièces proposées aux Relais, dans l'ordre choisi par l'administration.
     *
     * @param  Builder<IdentityDocumentType>  $query
     */
    #[Scope]
    protected function active(Builder $query): void
    {
        $query->where('is_active', true)->orderBy('position');
    }
}
