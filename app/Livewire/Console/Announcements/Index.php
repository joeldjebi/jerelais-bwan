<?php

namespace App\Livewire\Console\Announcements;

use App\Domain\Access\Enums\Permission;
use App\Domain\Auth\Data\PhoneNumber;
use App\Domain\Notification\Actions\PublishAnnouncement;
use App\Domain\Notification\Enums\AnnouncementAudience;
use App\Domain\Notification\Enums\AnnouncementScreen;
use App\Domain\Notification\Models\Announcement;
use App\Domain\Notification\Push\FcmPushGateway;
use App\Domain\Notification\Support\AnnouncementTargets;
use App\Domain\Relais\Enums\RelaisStatus;
use App\Domain\Relais\Models\RelaisProfile;
use App\Domain\Territoire\Models\Commune;
use App\Domain\Territoire\Models\Region;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use InvalidArgumentException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Notifications aux Relais (MOB-21) : l'administration rédige une annonce, choisit les destinataires (tout le réseau,
 * régions, communes, un Relais, identités à vérifier, coordonnées de paiement manquantes), l'écran à ouvrir, le SMS
 * de secours, l'envoi immédiat ou programmé, puis suit la diffusion (push, SMS, non joints, lus).
 *
 * @property-read array{total: int, push: int} $estimate
 * @property-read Collection<int, Region> $regions
 * @property-read Collection<int, Commune> $communes
 * @property-read Collection<int, RelaisProfile> $relaisMatches
 * @property-read RelaisProfile|null $selectedRelais
 * @property-read LengthAwarePaginator<int, Announcement> $history
 * @property-read bool $pushReady
 */
#[Title('Notifications push')]
class Index extends Component
{
    use WithPagination;

    public string $title = '';

    public string $body = '';

    public string $audience = 'all';

    /** @var list<string> */
    public array $regionIds = [];

    /** @var list<string> */
    public array $communeIds = [];

    public string $communeSearch = '';

    public string $relaisSearch = '';

    public string $relaisUuid = '';

    public string $screen = 'announcements';

    public bool $withSms = false;

    public string $when = 'now';

    public string $scheduledAt = '';

    public bool $confirming = false;

    public function mount(): void
    {
        $this->authorizeAccess();
        $this->scheduledAt = now()->timezone('Africa/Abidjan')->addHour()->startOfHour()->format('Y-m-d\TH:i');
    }

    public function updatedAudience(): void
    {
        $this->reset('regionIds', 'communeIds', 'relaisUuid', 'relaisSearch', 'communeSearch');
        $this->resetErrorBag();
    }

    public function chooseRelais(string $uuid): void
    {
        abort_unless(Str::isUuid($uuid), 404);
        $this->relaisUuid = $uuid;
        $this->relaisSearch = '';
    }

    /**
     * Contrôle du formulaire, puis demande de confirmation avec le nombre de destinataires.
     */
    public function review(): void
    {
        $this->authorizeAccess();
        $this->validate($this->rules(), attributes: $this->attributeNames());

        if ($this->estimate['total'] === 0) {
            $this->addError('audience', __('announcements.errors.no_recipient'));

            return;
        }

        $this->confirming = true;
    }

    public function send(PublishAnnouncement $publish): void
    {
        $this->authorizeAccess();
        $this->validate($this->rules(), attributes: $this->attributeNames());

        /** @var User $author */
        $author = Auth::user();

        try {
            $announcement = $publish->handle(
                $author,
                $this->title,
                $this->body,
                AnnouncementAudience::from($this->audience),
                $this->targetIds(),
                AnnouncementScreen::from($this->screen),
                $this->withSms,
                $this->when === 'later' ? CarbonImmutable::parse($this->scheduledAt, 'Africa/Abidjan') : null,
            );
        } catch (InvalidArgumentException $exception) {
            $this->confirming = false;
            $this->addError($this->when === 'later' && str_contains($exception->getMessage(), 'date') ? 'scheduledAt' : 'audience', $exception->getMessage());

            return;
        }

        $this->confirming = false;
        $this->reset('title', 'body', 'regionIds', 'communeIds', 'relaisUuid', 'relaisSearch', 'communeSearch', 'withSms', 'when');
        $this->audience = 'all';
        $this->resetPage();
        unset($this->history);

        $this->dispatch('toast', message: $announcement->scheduled_at === null
            ? __('Annonce en cours d’envoi à :count Relais.', ['count' => $announcement->recipients_count ?: $this->estimate['total']])
            : __('Annonce programmée le :date.', ['date' => $announcement->scheduled_at->timezone('Africa/Abidjan')->translatedFormat('j M à H:i')]));
    }

    public function cancel(string $uuid, PublishAnnouncement $publish): void
    {
        $this->authorizeAccess();
        abort_unless(Str::isUuid($uuid), 404);
        $announcement = Announcement::query()->where('uuid', $uuid)->firstOrFail();

        /** @var User $author */
        $author = Auth::user();

        try {
            $publish->cancel($announcement, $author);
        } catch (InvalidArgumentException $exception) {
            $this->dispatch('toast', message: $exception->getMessage(), tone: 'error');

            return;
        }

        unset($this->history);
        $this->dispatch('toast', message: __('Annonce annulée : elle ne partira pas.'));
    }

    /**
     * @return array{total: int, push: int}
     */
    #[Computed]
    public function estimate(): array
    {
        $audience = AnnouncementAudience::tryFrom($this->audience);

        if ($audience === null || (in_array($audience, [AnnouncementAudience::Regions, AnnouncementAudience::Communes, AnnouncementAudience::Relais], true) && $this->targetIds() === [])) {
            return ['total' => 0, 'push' => 0];
        }

        $estimate = AnnouncementTargets::estimate($audience, $this->targetIds());

        return $this->pushReady ? $estimate : ['total' => $estimate['total'], 'push' => 0];
    }

    /**
     * Les notifications push peuvent-elles partir (passerelle active et clé Firebase présente) ?
     */
    #[Computed]
    public function pushReady(): bool
    {
        return match (config('jerelais.push.driver')) {
            'none' => false,
            'fcm' => FcmPushGateway::fromConfig()->isConfigured(),
            default => true,
        };
    }

    /**
     * @return Collection<int, Region>
     */
    #[Computed]
    public function regions(): Collection
    {
        return Region::query()->orderBy('name')->get(['id', 'uuid', 'name']);
    }

    /**
     * @return Collection<int, Commune>
     */
    #[Computed]
    public function communes(): Collection
    {
        return Commune::query()->where('is_active', true)
            ->when($this->communeSearch !== '', fn ($query) => $query->whereLike('name', '%'.$this->communeSearch.'%'))
            ->orderBy('name')->limit(60)->get(['id', 'uuid', 'name']);
    }

    /**
     * Recherche d'un Relais actif par nom affiché, numéro de Relais ou téléphone.
     *
     * @return Collection<int, RelaisProfile>
     */
    #[Computed]
    public function relaisMatches(): Collection
    {
        $term = trim($this->relaisSearch);

        if (mb_strlen($term) < 2) {
            return new Collection;
        }

        $phone = PhoneNumber::tryParse($term);

        return RelaisProfile::withoutCommuneRestriction()
            ->where('status', RelaisStatus::Active->value)
            ->where(fn ($query) => $query->whereLike('display_name', '%'.$term.'%')
                ->orWhereLike('number', '%'.$term.'%')
                ->when($phone !== null, fn ($inner) => $inner->orWhereIn('user_id', User::query()->where('phone_hash', $phone?->hash())->select('id'))))
            ->with(['user:id,uuid,name', 'commune:id,name'])
            ->limit(8)->get();
    }

    #[Computed]
    public function selectedRelais(): ?RelaisProfile
    {
        return $this->relaisUuid === '' ? null : RelaisProfile::withoutCommuneRestriction()
            ->whereIn('user_id', User::query()->where('uuid', $this->relaisUuid)->select('id'))
            ->with(['user:id,uuid,name', 'commune:id,name'])
            ->first();
    }

    /**
     * @return LengthAwarePaginator<int, Announcement>
     */
    #[Computed]
    public function history(): LengthAwarePaginator
    {
        return Announcement::query()
            ->with('author:id,name')
            ->withCount(['recipients as read_count' => fn ($query) => $query->whereNotNull('read_at')])
            ->latest('id')
            ->paginate(15);
    }

    public function render(): View
    {
        return view('livewire.console.announcements.index');
    }

    /**
     * @return list<string>
     */
    private function targetIds(): array
    {
        return match ($this->audience) {
            AnnouncementAudience::Regions->value => array_values(array_filter($this->regionIds, fn (string $id): bool => Str::isUuid($id))),
            AnnouncementAudience::Communes->value => array_values(array_filter($this->communeIds, fn (string $id): bool => Str::isUuid($id))),
            AnnouncementAudience::Relais->value => Str::isUuid($this->relaisUuid) ? [$this->relaisUuid] : [],
            default => [],
        };
    }

    /**
     * @return array<string, list<mixed>>
     */
    private function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:80'],
            'body' => ['required', 'string', 'max:500'],
            'audience' => ['required', Rule::enum(AnnouncementAudience::class)],
            'regionIds' => [Rule::requiredIf($this->audience === AnnouncementAudience::Regions->value), 'array'],
            'communeIds' => [Rule::requiredIf($this->audience === AnnouncementAudience::Communes->value), 'array'],
            'relaisUuid' => [Rule::requiredIf($this->audience === AnnouncementAudience::Relais->value), 'string'],
            'screen' => ['required', Rule::enum(AnnouncementScreen::class)],
            'withSms' => ['boolean'],
            'when' => ['required', Rule::in(['now', 'later'])],
            'scheduledAt' => [Rule::requiredIf($this->when === 'later'), 'nullable', 'date', $this->when === 'later' ? 'after:now' : 'nullable'],
        ];
    }

    /**
     * @return array<string, string>
     */
    private function attributeNames(): array
    {
        return [
            'title' => __('titre'), 'body' => __('message'), 'audience' => __('destinataires'),
            'regionIds' => __('régions'), 'communeIds' => __('communes'), 'relaisUuid' => __('Relais'),
            'screen' => __('écran à ouvrir'), 'scheduledAt' => __('date d’envoi'),
        ];
    }

    private function authorizeAccess(): void
    {
        abort_unless(Auth::user()?->can(Permission::NotificationsSend->value) === true, 403);
    }
}
