<?php

namespace App\Domain\Settings\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['key', 'value', 'updated_by'])]
class Setting extends Model
{
    protected $primaryKey = 'key';

    protected $keyType = 'string';

    public $incrementing = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'value' => 'json',
        ];
    }
}
