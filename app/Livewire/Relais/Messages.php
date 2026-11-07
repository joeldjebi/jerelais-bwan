<?php

namespace App\Livewire\Relais;

use App\Domain\Messagerie\Actions\SendMessage;
use App\Domain\Messagerie\Models\Conversation;
use App\Livewire\Relais\Concerns\InteractsWithRelaisSpace;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use InvalidArgumentException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Messagerie du Relais avec son coordonnateur (MOB-22, WEB-03).
 *
 * @property-read Collection<int, Conversation> $conversations
 * @property-read Conversation|null $current
 */
#[Layout('layouts::relais')]
#[Title('Messages')]
class Messages extends Component
{
    use InteractsWithRelaisSpace;

    #[Url(as: 'c', except: '')]
    public string $selected = '';

    public string $body = '';

    public function mount(SendMessage $send): void
    {
        if ($this->selected === '') {
            $this->selected = $send->conversationFor($this->profile())->uuid;
        }
    }

    public function select(string $uuid): void
    {
        $this->selected = $uuid;
    }

    public function sendMessage(SendMessage $send): void
    {
        $conversation = $this->current;
        abort_if($conversation === null, 404);
        $this->validate(['body' => ['required', 'string', 'max:2000']], attributes: ['body' => __('message')]);

        try {
            $send->send($conversation, $this->user(), $this->body);
        } catch (InvalidArgumentException $exception) {
            $this->addError('body', $exception->getMessage());

            return;
        }

        $this->body = '';
        unset($this->current, $this->conversations);
    }

    /**
     * @return Collection<int, Conversation>
     */
    #[Computed]
    public function conversations(): Collection
    {
        return Conversation::withoutCommuneRestriction()
            ->where('relais_profile_id', $this->profile()->id)
            ->with('relaisItem:id,uuid,title')
            ->orderByRaw('last_message_at desc nulls last')
            ->get();
    }

    #[Computed]
    public function current(): ?Conversation
    {
        $conversation = Conversation::withoutCommuneRestriction()
            ->where('relais_profile_id', $this->profile()->id)
            ->where('uuid', $this->selected)
            ->with(['messages', 'relaisItem'])
            ->first();

        if ($conversation !== null) {
            app(SendMessage::class)->markRead($conversation, $this->user());
        }

        return $conversation;
    }

    public function render(): View
    {
        return view('livewire.relais.messages');
    }
}
