<?php

namespace App\Domain\Reporting\Exports;

use App\Domain\Media\Support\TemporaryPath;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\CarbonImmutable;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Writer;

/**
 * Exports des rapports de pilotage (ADM-26) : classeur Excel (un onglet par famille d'indicateurs) et PDF.
 *
 * @phpstan-import-type Section from \App\Domain\Reporting\Support\ReportBuilder
 */
class ReportExporter
{
    /**
     * @param  array{from: CarbonImmutable, to: CarbonImmutable, sections: list<Section>}  $report
     * @return string chemin du fichier temporaire .xlsx
     */
    public function xlsx(array $report): string
    {
        $path = TemporaryPath::make('xlsx');
        $writer = new Writer;
        $writer->openToFile($path);

        $title = (new Style)->withFontBold(true)->withFontSize(14);
        $header = (new Style)->withFontBold(true)->withFontColor('6E6E73');

        foreach ($report['sections'] as $index => $section) {
            if ($index > 0) {
                $writer->addNewSheetAndMakeItCurrent();
            }

            $sheet = $writer->getCurrentSheet();
            $sheet->setName(mb_substr($section['title'], 0, 31));
            $sheet->setColumnWidth(48, 1);
            $sheet->setColumnWidth(18, 2, 3);

            $writer->addRow(Row::fromValuesWithStyle([$section['title'], $this->period($report)], $title));
            $writer->addRow(Row::fromValues([]));

            foreach ($section['figures'] as $figure) {
                $writer->addRow(Row::fromValues([$figure['label'], $figure['value']]));
            }

            foreach ($section['tables'] as $table) {
                $writer->addRow(Row::fromValues([]));
                $writer->addRow(Row::fromValuesWithStyle([$table['title']], (new Style)->withFontBold(true)));
                $writer->addRow(Row::fromValuesWithStyle($table['columns'], $header));

                foreach ($table['rows'] as $row) {
                    $writer->addRow(Row::fromValues($row));
                }
            }
        }

        $writer->close();

        return $path;
    }

    /**
     * @param  array{from: CarbonImmutable, to: CarbonImmutable, sections: list<Section>}  $report
     */
    public function pdf(array $report, string $scope): string
    {
        return Pdf::loadView('pdf.report', ['report' => $report, 'period' => $this->period($report), 'scope' => $scope])->setPaper('a4')->output();
    }

    /**
     * @param  array{from: CarbonImmutable, to: CarbonImmutable, sections: list<Section>}  $report
     */
    public function period(array $report): string
    {
        return __('Du :from au :to', [
            'from' => $report['from']->translatedFormat('j F Y'),
            'to' => $report['to']->translatedFormat('j F Y'),
        ]);
    }
}
