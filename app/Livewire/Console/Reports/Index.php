<?php

namespace App\Livewire\Console\Reports;

use App\Domain\Reporting\Support\ReportBuilder;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Tableaux de bord et rapports exportables (ADM-26, §13).
 *
 * @phpstan-import-type Section from ReportBuilder
 *
 * @property-read array{from: CarbonImmutable, to: CarbonImmutable, sections: list<Section>} $report
 */
#[Title('Rapports')]
class Index extends Component
{
    #[Url(except: '30')]
    public string $period = '30';

    #[Url(except: '')]
    public string $from = '';

    #[Url(except: '')]
    public string $to = '';

    /**
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    public function range(): array
    {
        if ($this->period === 'custom' && $this->from !== '' && $this->to !== '') {
            return [CarbonImmutable::parse($this->from)->startOfDay(), CarbonImmutable::parse($this->to)->endOfDay()];
        }

        $days = max(1, (int) $this->period);

        return [CarbonImmutable::now()->subDays($days - 1)->startOfDay(), CarbonImmutable::now()->endOfDay()];
    }

    /**
     * @return array{from: CarbonImmutable, to: CarbonImmutable, sections: list<Section>}
     */
    #[Computed]
    public function report(): array
    {
        [$from, $to] = $this->range();

        return app(ReportBuilder::class)->build($from, $to);
    }

    public function render(): View
    {
        [$from, $to] = $this->range();

        return view('livewire.console.reports.index', ['rangeFrom' => $from->toDateString(), 'rangeTo' => $to->toDateString()]);
    }
}
