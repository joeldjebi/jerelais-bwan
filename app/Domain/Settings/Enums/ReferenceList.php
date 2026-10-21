<?php

namespace App\Domain\Settings\Enums;

use App\Domain\Access\Enums\Permission;
use App\Domain\Contribution\Models\Category;
use App\Domain\Territoire\Models\LieuType;
use App\Domain\Territoire\Models\Localite;
use App\Domain\Territoire\Models\Region;
use App\Domain\Validation\Models\RejectionReason;
use Illuminate\Database\Eloquent\Model;

/**
 * Listes de référence gérées depuis la console (ADM-20, ADM-23, COO-15). Les communes ont leur propre page.
 */
enum ReferenceList: string
{
    case Regions = 'regions';
    case Localites = 'localites';
    case LieuTypes = 'lieux';
    case Categories = 'categories';
    case RejectionReasons = 'motifs';

    public function label(): string
    {
        return __("settings.reference_lists.{$this->value}");
    }

    public function permission(): Permission
    {
        return match ($this) {
            self::Regions, self::Localites, self::LieuTypes => Permission::TerritoireManage,
            self::Categories, self::RejectionReasons => Permission::SettingsManage,
        };
    }

    /**
     * @return class-string<Model>
     */
    public function model(): string
    {
        return match ($this) {
            self::Regions => Region::class,
            self::Localites => Localite::class,
            self::LieuTypes => LieuType::class,
            self::Categories => Category::class,
            self::RejectionReasons => RejectionReason::class,
        };
    }

    /**
     * Colonne qui porte le libellé affiché.
     */
    public function labelColumn(): string
    {
        return $this === self::RejectionReasons ? 'label' : 'name';
    }

    /**
     * Les catégories et les motifs se désactivent (l'historique les référence) ; les autres se renomment seulement.
     */
    public function canBeDisabled(): bool
    {
        return in_array($this, [self::Categories, self::RejectionReasons], true);
    }
}
