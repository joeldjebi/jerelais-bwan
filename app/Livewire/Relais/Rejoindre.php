<?php

namespace App\Livewire\Relais;

use App\Domain\Audit\AuditLogger;
use App\Domain\Auth\Actions\ConfirmPhoneOwnership;
use App\Domain\Auth\Actions\RequestOtp;
use App\Domain\Auth\Data\PhoneNumber;
use App\Domain\Auth\Exceptions\AuthenticationRefused;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * « Devenir Relais » sur le site (WEB-02) : numéro de téléphone, code SMS, puis dossier de candidature.
 * Mêmes règles que l'application : code haché, 5 minutes, 5 essais, 3 envois par numéro et 10 par IP sur 15 minutes.
 * Les comptes de l'équipe sont refusés ici : ils passent par la console et sa double authentification.
 */
#[Layout('layouts::relais')]
#[Title('Devenir Relais')]
class Rejoindre extends Component
{
    public string $phone = '';

    public string $code = '';

    #[Locked]
    public ?string $maskedPhone = null;

    public function mount(): mixed
    {
        $user = Auth::user();

        if ($user instanceof User && ! $user->isStaff()) {
            return $this->redirectRoute('relais.candidature', navigate: true);
        }

        return null;
    }

    public function sendCode(RequestOtp $requestOtp): void
    {
        $this->resetErrorBag();
        $phone = PhoneNumber::tryParse($this->phone);

        if ($phone === null) {
            $this->addError('phone', __('validation.phone'));

            return;
        }

        if (! $this->hitLimits($phone)) {
            $this->addError('phone', __('auth.otp_throttled'));

            return;
        }

        $requestOtp->handle($phone, request()->ip());

        $this->phone = $phone->e164;
        $this->maskedPhone = $phone->masked();
        $this->code = '';
        $this->dispatch('otp-sent');
    }

    public function verify(ConfirmPhoneOwnership $confirm, AuditLogger $audit): mixed
    {
        $this->resetErrorBag();
        $phone = PhoneNumber::tryParse($this->phone);
        $verifyKey = 'web-otp-verify:'.request()->ip();

        if ($phone === null || $this->maskedPhone === null) {
            $this->maskedPhone = null;

            return null;
        }

        if (RateLimiter::tooManyAttempts($verifyKey, 10)) {
            $this->addError('code', __('auth.otp_throttled'));

            return null;
        }

        RateLimiter::hit($verifyKey, 60);

        if (preg_match('/^\d{6}$/', $this->code) !== 1) {
            $this->addError('code', __('auth.otp_format'));

            return null;
        }

        try {
            $user = $confirm->handle($phone, $this->code);
        } catch (AuthenticationRefused $exception) {
            $this->addError('code', $exception->getMessage());

            return null;
        }

        if ($user->isStaff()) {
            $this->addError('code', __('auth.staff_use_console'));

            return null;
        }

        Auth::login($user);
        session()->regenerate();
        $user->forceFill(['last_login_at' => now()])->save();
        $audit->record('auth.login', $user, ['method' => 'otp', 'channel' => 'web'], actor: $user);

        return $this->redirectRoute('relais.candidature', navigate: true);
    }

    public function changeNumber(): void
    {
        $this->maskedPhone = null;
        $this->code = '';
        $this->resetErrorBag();
    }

    public function render(): View
    {
        return view('livewire.relais.rejoindre');
    }

    /**
     * Applique les mêmes quotas d'envoi que l'API (par numéro et par adresse IP).
     */
    private function hitLimits(PhoneNumber $phone): bool
    {
        $window = (int) config('jerelais.otp.requests_window_minutes') * 60;
        $limits = [
            'web-otp-phone:'.$phone->hash() => (int) config('jerelais.otp.requests_per_phone'),
            'web-otp-ip:'.request()->ip() => (int) config('jerelais.otp.requests_per_ip'),
        ];

        foreach ($limits as $key => $max) {
            if (RateLimiter::tooManyAttempts($key, $max)) {
                return false;
            }
        }

        foreach (array_keys($limits) as $key) {
            RateLimiter::hit($key, $window);
        }

        return true;
    }
}
