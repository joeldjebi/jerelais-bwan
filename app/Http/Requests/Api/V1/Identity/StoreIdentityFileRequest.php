<?php

namespace App\Http\Requests\Api\V1\Identity;

use App\Domain\Identity\Enums\IdentityFileKind;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreIdentityFileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        $kind = IdentityFileKind::tryFrom($this->string('kind')->toString());

        return [
            'kind' => ['required', Rule::enum(IdentityFileKind::class)],
            'file' => [
                'required',
                'file',
                'max:'.(int) config('jerelais.candidature.document_max_kb'),
                'mimetypes:'.implode(',', ($kind ?? IdentityFileKind::Recto)->mimeTypes()),
            ],
        ];
    }

    public function kind(): IdentityFileKind
    {
        return IdentityFileKind::from($this->string('kind')->toString());
    }
}
