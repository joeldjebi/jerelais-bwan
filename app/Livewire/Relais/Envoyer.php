<?php

namespace App\Livewire\Relais;

use App\Domain\AppelRelais\Models\AppelRelais;
use App\Domain\Contribution\Actions\SaveRelaisItem;
use App\Domain\Contribution\Actions\SubmitRelaisItem;
use App\Domain\Contribution\Enums\CreditMode;
use App\Domain\Contribution\Enums\PeopleConsent;
use App\Domain\Contribution\Enums\Urgency;
use App\Domain\Contribution\Exceptions\RelaisIncomplete;
use App\Domain\Contribution\Models\Category;
use App\Domain\Contribution\Models\RelaisItem;
use App\Domain\Contribution\States\Uploading;
use App\Domain\Contribution\Support\RelaisSender;
use App\Domain\Media\Exceptions\MediaRefused;
use App\Domain\Territoire\Models\Commune;
use App\Livewire\Relais\Concerns\InteractsWithRelaisSpace;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Envoyer un relais depuis un ordinateur (WEB-03, WEB-04) : médias en morceaux, formulaire d'accompagnement,
 * questions de protection, lieu ; même règles que l'application.
 *
 * @property-read Collection<int, Category> $categories
 * @property-read Collection<int, Commune> $communes
 * @property-read AppelRelais|null $appelModel
 */
#[Layout('layouts::relais')]
#[Title('Envoyer un relais')]
class Envoyer extends Component
{
    use InteractsWithRelaisSpace;

    #[Locked]
    public string $relaisUuid = '';

    #[Url(as: 'appel', except: '')]
    public string $appel = '';

    public string $category = '';

    public string $urgency = 'normal';

    public string $title = '';

    public string $description = '';

    public string $declaredCommune = '';

    public string $declaredPlace = '';

    public string $lat = '';

    public string $lng = '';

    public string $accuracy = '';

    public string $hasMinors = '';

    public string $peopleConsent = '';

    public string $creditMode = '';

    public string $creditName = '';

    /** @var array<string, string> */
    public array $missing = [];

    public function mount(SaveRelaisItem $save): void
    {
        $user = $this->user();
        abort_unless(RelaisSender::canSend($user), 403);

        $draft = RelaisItem::withoutCommuneRestriction()->where('user_id', $user->id)->where('state', Uploading::getMorphClass())->whereNull('submitted_at')->latest('id')->first()
            ?? $save->create($user, (string) Str::uuid(), []);

        $this->relaisUuid = $draft->uuid;
        $this->declaredCommune = (string) ($draft->declaredCommune->uuid ?? $this->profile()->commune?->uuid);
        $this->category = (string) $draft->category?->uuid;
        $this->title = (string) $draft->title;
    }

    public function send(SaveRelaisItem $save, SubmitRelaisItem $submit): mixed
    {
        $this->missing = [];
        $this->validate([
            'category' => ['required', Rule::exists('categories', 'uuid')->where('is_active', true)],
            'urgency' => ['required', Rule::enum(Urgency::class)],
            'title' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:2000'],
            'declaredCommune' => ['nullable', Rule::exists('communes', 'uuid')->where('is_active', true)],
            'declaredPlace' => ['nullable', 'string', 'max:200'],
            'lat' => ['nullable', 'numeric', 'between:-90,90'],
            'lng' => ['nullable', 'numeric', 'between:-180,180'],
            'hasMinors' => ['required', 'in:0,1'],
            'peopleConsent' => ['required', Rule::enum(PeopleConsent::class)],
            'creditMode' => ['required', Rule::enum(CreditMode::class)],
            'creditName' => ['required_if:creditMode,pseudonym', 'nullable', 'string', 'max:80'],
        ], attributes: __('relais_items.attributes') + ['declaredCommune' => __('commune'), 'hasMinors' => __('présence de mineurs'), 'peopleConsent' => __('accord des personnes filmées'), 'creditMode' => __('crédit à l’antenne'), 'creditName' => __('pseudonyme')]);

        $relais = $this->relais();

        try {
            $save->update($relais, array_filter([
                'appel' => $this->appel === '' ? null : $this->appel,
                'category' => $this->category,
                'urgency' => $this->urgency,
                'title' => $this->title,
                'description' => $this->description ?: null,
                'declared_commune' => $this->declaredCommune ?: null,
                'declared_place' => $this->declaredPlace ?: null,
                'location' => $this->lat !== '' && $this->lng !== '' ? ['lat' => (float) $this->lat, 'lng' => (float) $this->lng] : null,
                'location_accuracy_m' => $this->accuracy === '' ? null : (int) round((float) $this->accuracy),
                'has_minors' => $this->hasMinors === '1',
                'people_consent' => $this->peopleConsent,
                'credit_mode' => $this->creditMode,
                'credit_name' => $this->creditName ?: null,
                'device_model' => __('Site web'),
            ], fn (mixed $value): bool => $value !== null));

            $submit->handle($relais);
        } catch (MediaRefused $exception) {
            $this->addError('appel', $exception->getMessage());

            return null;
        } catch (RelaisIncomplete $exception) {
            $this->missing = $exception->missing;

            return null;
        }

        session()->flash('toast', ['message' => __('Relais envoyé : il est dans la file de votre coordonnateur.'), 'tone' => 'success']);

        return $this->redirectRoute('relais.items', navigate: true);
    }

    /**
     * @return Collection<int, Category>
     */
    #[Computed(persist: true)]
    public function categories(): Collection
    {
        return Category::query()->active()->get(['id', 'uuid', 'name', 'examples']);
    }

    /**
     * @return Collection<int, Commune>
     */
    #[Computed(persist: true)]
    public function communes(): Collection
    {
        return Commune::query()->active()->orderBy('name')->get(['id', 'uuid', 'name']);
    }

    #[Computed]
    public function appelModel(): ?AppelRelais
    {
        return $this->appel === '' ? null : AppelRelais::query()->where('uuid', $this->appel)->first();
    }

    public function render(): View
    {
        return view('livewire.relais.envoyer');
    }

    private function relais(): RelaisItem
    {
        return RelaisItem::withoutCommuneRestriction()->where('uuid', $this->relaisUuid)->where('user_id', $this->user()->id)->firstOrFail();
    }
}
