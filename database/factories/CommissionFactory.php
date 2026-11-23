<?php

namespace Database\Factories;

use App\Domain\Contribution\Models\RelaisItem;
use App\Domain\Gains\Enums\CommissionKind;
use App\Domain\Gains\Enums\CommissionStatus;
use App\Domain\Gains\Models\Commission;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Commission due pour un relais validé.
 *
 * @extends Factory<Commission>
 */
class CommissionFactory extends Factory
{
    protected $model = Commission::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'relais_item_id' => RelaisItem::factory(),
            'user_id' => fn (array $attributes): int => RelaisItem::query()->whereKey($attributes['relais_item_id'])->firstOrFail()->user_id,
            'kind' => CommissionKind::Validated,
            'amount' => 2000,
            'status' => CommissionStatus::Due,
        ];
    }
}
