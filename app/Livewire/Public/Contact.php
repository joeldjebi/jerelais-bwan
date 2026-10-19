<?php

namespace App\Livewire\Public;

use App\Domain\Public\Mail\ContactMessage;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Formulaire de contact (WEB-01) : limité par adresse IP, champ piège contre les robots.
 */
#[Layout('components.layouts.public')]
#[Title('Contact')]
class Contact extends Component
{
    public string $name = '';

    public string $email = '';

    public string $subject = 'question';

    public string $message = '';

    /** Champ invisible : un robot le remplit, un humain non. */
    public string $website = '';

    public bool $sent = false;

    public function send(): void
    {
        $this->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email:rfc', 'max:255'],
            'subject' => ['required', 'in:question,presse,partenariat,donnees,autre'],
            'message' => ['required', 'string', 'min:10', 'max:3000'],
        ], attributes: ['name' => __('nom'), 'email' => __('adresse e-mail'), 'message' => __('message')]);

        $key = 'contact:'.request()->ip();

        if (RateLimiter::tooManyAttempts($key, 3)) {
            $this->addError('message', __('Trop de messages envoyés. Réessayez dans une heure.'));

            return;
        }

        RateLimiter::hit($key, 3600);

        if ($this->website === '') {
            Mail::to((string) config('jerelais.contact_email'))->send(new ContactMessage($this->name, $this->email, $this->subject, $this->message));
        }

        $this->reset('name', 'email', 'message');
        $this->sent = true;
    }

    public function render(): View
    {
        return view('livewire.public.contact');
    }
}
