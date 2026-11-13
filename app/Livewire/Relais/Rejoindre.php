<?php

namespace App\Livewire\Relais;

use App\Domain\Audit\AuditLogger;
use App\Domain\Auth\Actions\CompleteRegistration;
use App\Domain\Auth\Actions\ConfirmPhoneOwnership;
use App\Domain\Auth\Actions\LoginWithPassword;
use App\Domain\Auth\Actions\RequestOtp;
use App\Domain\Auth\Actions\VerifyRegistrationCode;
use App\Domain\Auth\Data\PhoneNumber;
use App\Domain\Auth\Exceptions\AuthenticationRefused;
use App\Domain\Auth\Exceptions\SmsNotSent;
use App\Domain\Auth\Support\OtpPolicy;
use App\Domain\Auth\Support\PasswordPolicy;
use App\Domain\Relais\Actions\EnrolAppRelais;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Validator;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * « Devenir Relais » sur le site (WEB-02), même fonctionnement que l'application (MOB-01) :
 * - inscription : numéro, code SMS, mot de passe → Relais tout de suite, puis connexion ;
 * - connexion : numéro et mot de passe ;
 * - mot de passe oublié : code SMS, puis nouveau mot de passe.
 * Mêmes règles de sécurité que l'API (codes hachés, quotas par numéro et par IP, blocage après 5 mots de passe erronés).
 * Les comptes de l'équipe sont refusés ici : ils passent par la console et sa double authentification.
 */
#[Layout('layouts::relais')]
#[Title('Devenir Relais')]
class Rejoindre extends Component
{
    public const array MODES = ['inscription', 'connexion', 'oubli'];

    #[Url(except: 'inscription')]
    public string $mode = 'inscription';

    /** Étape du parcours d'inscription ou d'oubli : « phone », « code » ou « password ». */
    #[Locked]
    public string $step = 'phone';

    public string $phone = '';

    public string $code = '';

    public string $password = '';

    public string $passwordConfirmation = '';

    #[Locked]
    public ?string $maskedPhone = null;

    /** Longueur du code attendu (celle du code fixe s'il s'applique à ce numéro). */
    #[Locked]
    public int $codeLength = 4;

    /** Compte dont le mot de passe va être redéfini (mot de passe oublié), une fois le code vérifié. */
    #[Locked]
    public ?int $resetUserId = null;

    #[Locked]
    public ?string $notice = null;

    public function mount(): mixed
    {
        $user = Auth::user();

        if ($user instanceof User && ! $user->isStaff()) {
            return $this->redirectRoute('relais.send', navigate: true);
        }

        if (! in_array($this->mode, self::MODES, true)) {
            $this->mode = 'inscription';
        }

        return null;
    }

    public function switchMode(string $mode): void
    {
        $this->mode = in_array($mode, self::MODES, true) ? $mode : 'inscription';
        $this->reset('step', 'code', 'password', 'passwordConfirmation', 'maskedPhone', 'resetUserId', 'notice');
        $this->resetErrorBag();
    }

    /**
     * Inscription ou mot de passe oublié : envoi du code par SMS.
     */
    public function sendCode(RequestOtp $requestOtp): void
    {
        $this->resetErrorBag();
        $phone = PhoneNumber::tryParse($this->phone);

        if ($phone === null) {
            $this->addError('phone', __('validation.phone'));

            return;
        }

        if ($this->mode === 'inscription') {
            try {
                VerifyRegistrationCode::ensureAvailable($phone);
            } catch (AuthenticationRefused $exception) {
                $this->addError('phone', $exception->getMessage());

                return;
            }
        }

        if (! $this->hitLimits()) {
            $this->addError('phone', __('auth.otp_throttled'));

            return;
        }

        try {
            $requestOtp->handle($phone, request()->ip());
            $this->codeLength = OtpPolicy::lengthFor($phone);
        } catch (ThrottleRequestsException) {
            $this->addError('phone', __('auth.otp_throttled'));

            return;
        } catch (SmsNotSent $exception) {
            $this->addError('phone', $exception->userMessage());

            return;
        }

        $this->phone = $phone->e164;
        $this->maskedPhone = $phone->masked();
        $this->code = '';
        $this->step = 'code';
        $this->dispatch('otp-sent');
    }

    /**
     * Vérification du code : le numéro est confirmé (inscription) ou le compte retrouvé (mot de passe oublié).
     */
    public function verify(VerifyRegistrationCode $verifyRegistration, ConfirmPhoneOwnership $confirm): void
    {
        $this->resetErrorBag();
        $phone = PhoneNumber::tryParse($this->phone);

        if ($phone === null || $this->step !== 'code') {
            $this->step = 'phone';

            return;
        }

        $verifyKey = 'web-otp-verify:'.request()->ip();

        if (RateLimiter::tooManyAttempts($verifyKey, 10)) {
            $this->addError('code', __('auth.otp_throttled'));

            return;
        }

        RateLimiter::hit($verifyKey, 60);

        if (preg_match('/^\d{'.$this->codeLength.'}$/', $this->code) !== 1) {
            $this->addError('code', __('auth.otp_format', ['length' => $this->codeLength]));

            return;
        }

        try {
            if ($this->mode === 'inscription') {
                $verifyRegistration->handle($phone, $this->code);
            } else {
                $user = $confirm->handle($phone, $this->code, createAccount: false);

                if ($user->isStaff()) {
                    $this->addError('code', __('auth.staff_use_console'));

                    return;
                }

                $this->resetUserId = $user->id;
            }
        } catch (AuthenticationRefused $exception) {
            $this->addError('code', $exception->getMessage());

            return;
        }

        $this->step = 'password';
    }

    /**
     * Dernière étape : mot de passe de l'inscription, ou nouveau mot de passe après un oubli.
     */
    public function savePassword(CompleteRegistration $complete, EnrolAppRelais $enrol, AuditLogger $audit): mixed
    {
        $this->resetErrorBag();
        $phone = PhoneNumber::tryParse($this->phone);

        if ($phone === null || $this->step !== 'password') {
            $this->step = 'phone';

            return null;
        }

        $validator = Validator::make(
            ['password' => $this->password, 'password_confirmation' => $this->passwordConfirmation],
            ['password' => PasswordPolicy::rules()],
            PasswordPolicy::messages(),
        );

        if ($validator->fails()) {
            $this->addError('password', (string) $validator->errors()->first('password'));

            return null;
        }

        if ($this->mode === 'inscription') {
            try {
                $complete->handle($phone, $this->password);
            } catch (AuthenticationRefused $exception) {
                $this->switchMode('inscription');
                $this->addError('phone', $exception->getMessage());

                return null;
            }

            $registeredPhone = $this->phone;
            $this->switchMode('connexion');
            $this->phone = PhoneNumber::tryParse($registeredPhone)?->national() ?? $registeredPhone;
            $this->notice = __('auth.registration_completed');

            return null;
        }

        $user = User::query()->whereKey($this->resetUserId)->first();

        if (! $user instanceof User || $user->isStaff() || ! $user->status->canSignIn()) {
            $this->switchMode('oubli');

            return null;
        }

        $user->forceFill(['password' => $this->password])->save();
        PasswordPolicy::clearFailures($phone);
        $audit->record('auth.password.reset', $user, ['channel' => 'web'], actor: $user);
        $enrol->ensure($user);

        return $this->signIn($user, $audit);
    }

    /**
     * Connexion : numéro et mot de passe.
     */
    public function login(LoginWithPassword $loginWithPassword, AuditLogger $audit): mixed
    {
        $this->resetErrorBag();
        $phone = PhoneNumber::tryParse($this->phone);

        if ($phone === null) {
            $this->addError('phone', __('validation.phone'));

            return null;
        }

        try {
            $user = $loginWithPassword->verify($phone, $this->password);
        } catch (AuthenticationRefused $exception) {
            $this->addError('phone', $exception->getMessage());

            return null;
        } catch (ThrottleRequestsException $exception) {
            $this->addError('phone', $exception->getMessage());

            return null;
        }

        if ($user->isStaff()) {
            $this->addError('phone', __('auth.staff_use_console'));

            return null;
        }

        return $this->signIn($user, $audit);
    }

    public function changeNumber(): void
    {
        $this->reset('step', 'code', 'maskedPhone', 'resetUserId');
        $this->resetErrorBag();
    }

    public function render(): View
    {
        return view('livewire.relais.rejoindre');
    }

    private function signIn(User $user, AuditLogger $audit): mixed
    {
        Auth::login($user);
        session()->regenerate();
        $user->forceFill(['last_login_at' => now()])->save();
        $audit->record('auth.login', $user, ['method' => 'password', 'channel' => 'web'], actor: $user);

        return $this->redirectRoute('relais.send', navigate: true);
    }

    /**
     * Quota d'envoi par adresse IP (le quota par numéro est commun avec l'API, dans RequestOtp).
     */
    private function hitLimits(): bool
    {
        $key = 'web-otp-ip:'.request()->ip();

        if (RateLimiter::tooManyAttempts($key, (int) config('jerelais.otp.requests_per_ip'))) {
            return false;
        }

        RateLimiter::hit($key, (int) config('jerelais.otp.requests_window_minutes') * 60);

        return true;
    }
}
