<?php

use App\Domain\Access\Enums\Role;
use App\Domain\Auth\Data\PhoneNumber;
use App\Models\User;

it('liste dans la console Relais ceux inscrits depuis l’application, sans commune', function () {
    config(['jerelais.otp.fixed_code' => '1234']);
    fakeSms();

    $this->postJson(route('api.v1.auth.register.request'), ['phone' => '0707070707'])->assertAccepted();
    $this->postJson(route('api.v1.auth.register.verify'), ['phone' => '0707070707', 'code' => '1234'])->assertOk();
    $this->postJson(route('api.v1.auth.register.complete'), ['phone' => '0707070707', 'password' => 'MotDePasse2026', 'password_confirmation' => 'MotDePasse2026'])->assertCreated();

    $number = User::findByPhone(PhoneNumber::parse('0707070707'))->relaisProfile()->withoutGlobalScopes()->value('number');
    $admin = User::factory()->withTwoFactor()->withRole(Role::Admin)->create();

    $this->actingAs($admin)->get(route('console.relais'))->assertOk()->assertSee($number);
});
