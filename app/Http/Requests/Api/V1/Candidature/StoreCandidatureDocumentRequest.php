<?php

namespace App\Http\Requests\Api\V1\Candidature;

use App\Domain\Access\Enums\Permission;
use App\Domain\Candidature\Enums\DocumentType;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Envoi d'une pièce du dossier (MOB-03) : type vérifié sur le contenu du fichier.
 */
class StoreCandidatureDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user instanceof User && $user->hasPermissionTo(Permission::CandidatureApply->value);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $type = DocumentType::tryFrom($this->string('type')->toString());

        return [
            'type' => ['required', Rule::enum(DocumentType::class)],
            'file' => [
                'required',
                'file',
                'max:'.(int) config('jerelais.candidature.document_max_kb'),
                'mimetypes:'.implode(',', ($type ?? DocumentType::IdFront)->mimeTypes()),
            ],
        ];
    }

    public function documentType(): DocumentType
    {
        return DocumentType::from($this->string('type')->toString());
    }
}
