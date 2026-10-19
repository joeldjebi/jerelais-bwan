<?php

namespace App\Livewire\Console\Messagerie;

use App\Domain\Access\Enums\Permission;
use App\Domain\Access\Support\CommuneAccess;
use App\Domain\Messagerie\Actions\SendMessage;
use App\Domain\Messagerie\Models\Conversation;
use App\Domain\Territoire\Models\Commune;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use InvalidArgumentException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Messagerie de l'équipe avec les Relais de son périmètre (COO-20, MOB-22) et message groupé à une commune.
 *
 * @property-read Collection<int, Conversation> $conversations
 * @property-read Conversation|null $current
 * @property-read Collection<int, Commune> $communes
 */
#[Title('Messagerie')]
class Index extends Component
{
    #[Url(as: 'c')]
    public string $selected = '';

    public string $body = '';

    public bool $broadcasting = false;

    public string $broadcastCommune = '';

    public string $broadcastBody = '';

    public function select(string $uuid, SendMessage $send): void
    {
        $this->authorizeAccess();

        $this->selected = $uuid;
        $this->body = '';

        if ($this->current !== null) {
            $send->markRead($this->current, $this->user());
        }
    }

    public function reply(SendMessage $send): void
    {
        $this->authorizeAccess();

        $conversation = $this->current;
        abort_if($conversation === null, 404);
        $this->validate(['body' => ['required', 'string', 'max:2000']], attributes: ['body' => __('message')]);

        $send->send($conversation, $this->user(), $this->body);
        $this->body = '';
        unset($this->current, $this->conversations);
    }

    public function sendBroadcast(SendMessage $send): void
    {
        $this->authorizeAccess();

        $this->validate(['broadcastCommune' => ['required', 'uuid'], 'broadcastBody' => ['required', 'string', 'max:2000']], attributes: ['broadcastCommune' => __('commune'), 'broadcastBody' => __('message')]);
        $commune = $this->communes->firstWhere('uuid', $this->broadcastCommune);
        abort_if($commune === null, 403);

        try {
            $count = $send->broadcast($commune, $this->user(), $this->broadcastBody);
        } catch (InvalidArgumentException $exception) {
            $this->addError('broadcastBody', $exception->getMessage());

            return;
        }

        $this->reset('broadcasting', 'broadcastBody');
        unset($this->conversations);
        $this->dispatch('toast', message: trans_choice('Message envoyé à :count Relais.|Message envoyé à :count Relais.', $count));
    }

    /**
     * @return Collection<int, Conversation>
     */
    #[Computed]
    public function conversations(): Collection
    {
        return Conversation::query()
            ->with(['relaisProfile:id,display_name,number', 'relaisItem:id,uuid,title', 'commune:id,name', 'messages' => fn ($query) => $query->reorder()->latest('id')->limit(1)])
            ->whereNotNull('last_message_at')
            ->orderByDesc('last_message_at')
            ->limit(100)
            ->get();
    }

    #[Computed]
    public function current(): ?Conversation
    {
        return $this->selected === ''
            ? null
            : Conversation::query()->with(['relaisProfile', 'relaisItem', 'commune', 'messages.sender:id,name'])->where('uuid', $this->selected)->first();
    }

    /**
     * @return Collection<int, Commune>
     */
    #[Computed]
    public function communes(): Collection
    {
        $ids = app(CommuneAccess::class)->communeIdsFor($this->user());

        return Commune::query()->active()->when($ids !== null, fn ($query) => $query->whereIn('id', $ids))->orderBy('name')->get(['id', 'uuid', 'name']);
    }

    public function render(): View
    {
        return view('livewire.console.messagerie.index');
    }

    private function user(): User
    {
        $user = Auth::user();
        abort_unless($user instanceof User, 403);

        return $user;
    }

    /**
     * Revérifié à chaque action : un droit retiré prend effet même sur un onglet resté ouvert.
     */
    private function authorizeAccess(): void
    {
        abort_unless(Auth::user()?->can(Permission::RelaisValidate->value) === true, 403);
    }
}
