<?php

namespace App\Http\Controllers\Console;

use App\Domain\Access\Support\CommuneAccess;
use App\Domain\Audit\AuditLogger;
use App\Domain\Reporting\Exports\ReportExporter;
use App\Domain\Reporting\Support\ReportBuilder;
use App\Http\Controllers\Controller;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Téléchargement d'un rapport en Excel ou PDF (ADM-26), sur le périmètre de l'utilisateur ; chaque export est tracé.
 */
class ReportExportController extends Controller
{
    public function __invoke(Request $request, string $format, ReportBuilder $builder, ReportExporter $exporter, AuditLogger $audit): Response
    {
        abort_unless(in_array($format, ['xlsx', 'pdf'], true), 404);
        $data = $request->validate(['from' => ['required', 'date'], 'to' => ['required', 'date', 'after_or_equal:from']]);

        /** @var User $user */
        $user = $request->user();
        $from = CarbonImmutable::parse($data['from'])->startOfDay();
        $to = CarbonImmutable::parse($data['to'])->endOfDay();
        $report = $builder->build($from, $to);
        $name = 'je-relais-rapport-'.$from->toDateString().'-'.$to->toDateString().'.'.$format;

        $audit->record('reports.exported', payload: ['format' => $format, 'from' => $from->toDateString(), 'to' => $to->toDateString()], actor: $user);

        if ($format === 'pdf') {
            $scope = app(CommuneAccess::class)->isRestricted($user) ? $user->communes()->pluck('name')->join(', ') : __('Réseau national');

            return response($exporter->pdf($report, $scope), 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'attachment; filename="'.$name.'"']);
        }

        return response()->download($exporter->xlsx($report), $name)->deleteFileAfterSend();
    }
}
