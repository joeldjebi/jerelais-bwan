<?php

namespace App\Http\Controllers\Api\V1\Sms;

use App\Domain\Auth\Data\PhoneNumber;
use App\Http\Controllers\Controller;
use Dedoc\Scramble\Attributes\ExcludeRouteFromDocs;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

/**
 * Accusés de réception MTarget (DLR) : confirmation de livraison d'un SMS accepté.
 * Protégé par un jeton (MTARGET_DLR_TOKEN) ; désactivé tant que le jeton n'est pas configuré.
 * Le statut est journalisé avec le ticket MTarget et le numéro masqué. Réservé à MTarget, absent du Swagger.
 */
class MtargetDeliveryReportController extends Controller
{
    #[ExcludeRouteFromDocs]
    public function __invoke(Request $request): Response
    {
        $expected = (string) config('services.mtarget.dlr_token');

        abort_if($expected === '', 404);
        abort_unless(hash_equals($expected, $request->string('token')->toString()), 403);

        $msisdn = $request->string('msisdn')->toString();

        Log::info('[SMS MTarget] accusé de réception', [
            'ticket' => $request->string('ticket')->limit(100)->toString() ?: null,
            'remoteid' => $request->string('remoteid')->limit(100)->toString() ?: null,
            'status' => $request->string('status')->limit(50)->toString() ?: null,
            'reason' => $request->string('reason')->limit(100)->toString() ?: null,
            'to' => PhoneNumber::tryParse($msisdn)?->masked(),
        ]);

        return response()->noContent();
    }
}
