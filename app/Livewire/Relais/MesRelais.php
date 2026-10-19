<?php

namespace App\Livewire\Relais;

use App\Domain\Contribution\Actions\RespondToComplement;
use App\Domain\Contribution\Exceptions\RelaisIncomplete;
use App\Domain\Contribution\Exceptions\RelaisNotEditable;
use App\Domain\Contribution\Models\RelaisItem;
use App\Livewire\Relais\Concerns\InteractsWithRelaisSpace;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * « Mes relais » (MOB-20, WEB-03) : statut de chaque envoi, motif de rejet, réponse aux demandes de complément.
 *
 * @property-read LengthAwarePaginator<int, RelaisItem> $items
 */
#[Layout('layouts::relais')]
#[Title('Mes relais')]
class MesRelais extends Component
{
    use InteractsWithRelaisSpace, WithPagination;

    public string $answering = '';

    public string $answer = '';

    public function mount(): void
    {
        $this->profile();
    }

    public function respond(string $uuid, RespondToComplement $respond): void
    {
        $relais = RelaisItem::withoutCommuneRestriction()->where('uuid', $uuid)->where('user_id', $this->user()->id)->firstOrFail();

        try {
            $respond->handle($relais, $this->answer);
        } catch (RelaisIncomplete $exception) {
            $this->addError('answer', (string) collect($exception->missing)->first());

            return;
        } catch (RelaisNotEditable $exception) {
            $this->addError('answer', $exception->getMessage());

            return;
        }

        $this->reset('answering', 'answer');
        unset($this->items);
    }

    /**
     * @return LengthAwarePaginator<int, RelaisItem>
     */
    #[Computed]
    public function items(): LengthAwarePaginator
    {
        return RelaisItem::withoutCommuneRestriction()
            ->where('user_id', $this->user()->id)
            ->whereNotNull('submitted_at')
            ->with(['category:id,name', 'commune:id,name', 'rejectionReason:id,label', 'medias'])
            ->latest('submitted_at')
            ->paginate(20);
    }

    public function render(): View
    {
        return view('livewire.relais.mes-relais');
    }
}
