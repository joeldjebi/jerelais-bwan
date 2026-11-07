<?php

namespace App\Http\Controllers\Public;

use App\Domain\Legal\Enums\LegalDocumentType;
use App\Domain\Legal\Models\LegalDocument;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;

/**
 * Pages publiques de la charte, des conditions d'utilisation et de la politique de confidentialité (WEB-01, WEB-07).
 */
class LegalPageController extends Controller
{
    public function __invoke(LegalDocumentType $type): View
    {
        $document = LegalDocument::current($type);
        abort_if($document === null, 404);

        return view('public.legal', ['document' => $document]);
    }
}
