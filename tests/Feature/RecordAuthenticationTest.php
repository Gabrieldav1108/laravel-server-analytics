<?php

declare(strict_types=1);

use Gabrieldav1108\ServerAnalytics\Actor;
use Gabrieldav1108\ServerAnalytics\ServerAnalytics;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Auth\GenericUser;

it('records the login with the user that just authenticated', function (): void {
    ServerAnalytics::resolveActorUsing(fn (): Actor => new Actor(tenant: 'ACME', userId: null));

    event(new Login('web', new GenericUser(['id' => 42]), false));

    expect($this->queuedEvents()[0])
        ->type->toBe('session.login')
        ->name->toBe('web')
        ->tenant->toBe('ACME')
        ->user_id->toBe('42');
});

it('records the logout of the user', function (): void {
    event(new Logout('web', new GenericUser(['id' => 42])));

    expect($this->queuedEvents()[0])
        ->type->toBe('session.logout')
        ->user_id->toBe('42');
});

it('records a failed login without the typed credentials', function (): void {
    event(new Failed('web', new GenericUser(['id' => 42]), [
        'email' => 'jane@example.com',
        'password' => 'wrong-password',
    ]));

    $event = $this->queuedEvents()[0];

    expect($event)
        ->type->toBe('session.login_failed')
        ->user_id->toBeNull()
        ->and(json_encode($event))
        ->not->toContain('jane@example.com')
        ->not->toContain('wrong-password');
});
