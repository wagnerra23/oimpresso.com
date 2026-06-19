<?php

declare(strict_types=1);

use Modules\Whatsapp\Console\Commands\WhatsmeowHealthProbeCommand as Probe;
use Modules\Whatsapp\Services\Drivers\WhatsmeowState;

uses(Tests\TestCase::class);

/**
 * Camada 2 (incidente 2026-06-18 inverso) — decisão PURA do health-probe.
 *
 * O flag `loggedIn` do WuzAPI mentiu: marcou como "fora do ar" um canal que
 * recebia ~48 msg/h (verificado ao vivo no daemon CT 100). `decideAction()`
 * corrobora o estado do daemon com "inbound recente" SÓ no ramo caído — suprime o
 * falso `disconnected` e auto-cura pra `healthy`. Determinístico, sem DB/daemon:
 * é a catraca da regra (a query de inbound real fica fina no handle()).
 */

it('caído + inbound recente + já disconnected → AUTO-CURA (paired)', function () {
    expect(Probe::decideAction(WhatsmeowState::LOGGED_OUT, 'disconnected', true))->toBe(Probe::ACTION_PAIRED);
    expect(Probe::decideAction(WhatsmeowState::NOT_EXISTS, 'disconnected', true))->toBe(Probe::ACTION_PAIRED);
});

it('caído + inbound recente + já healthy → NÃO mexe (idempotente, sem churn)', function () {
    expect(Probe::decideAction(WhatsmeowState::LOGGED_OUT, 'healthy', true))->toBe(Probe::ACTION_NONE);
});

it('caído SEM inbound recente → marca disconnected (queda real preservada)', function () {
    expect(Probe::decideAction(WhatsmeowState::LOGGED_OUT, 'healthy', false))->toBe(Probe::ACTION_DISCONNECTED);
    expect(Probe::decideAction(WhatsmeowState::NOT_EXISTS, 'healthy', false))->toBe(Probe::ACTION_DISCONNECTED);
});

it('caído SEM inbound + já disconnected → NÃO repete (idempotente)', function () {
    expect(Probe::decideAction(WhatsmeowState::LOGGED_OUT, 'disconnected', false))->toBe(Probe::ACTION_NONE);
});

it('BANNED nunca é suprimido por mensagem (msg pré-ban não invalida o ban)', function () {
    expect(Probe::decideAction(WhatsmeowState::BANNED, 'healthy', false))->toBe(Probe::ACTION_BANNED);
    expect(Probe::decideAction(WhatsmeowState::BANNED, 'banned', false))->toBe(Probe::ACTION_NONE);
});

it('PAIRED converge healthy só se estava unhealthy', function () {
    expect(Probe::decideAction(WhatsmeowState::PAIRED, 'disconnected', false))->toBe(Probe::ACTION_PAIRED);
    expect(Probe::decideAction(WhatsmeowState::PAIRED, 'healthy', false))->toBe(Probe::ACTION_NONE);
});

it('estados transitórios (daemon down / pareando) não mutam health', function () {
    foreach ([
        WhatsmeowState::DAEMON_UNREACHABLE,
        WhatsmeowState::QR_PENDING,
        WhatsmeowState::ERROR,
    ] as $s) {
        expect(Probe::decideAction($s, 'healthy', false))->toBe(Probe::ACTION_NONE);
        expect(Probe::decideAction($s, 'disconnected', false))->toBe(Probe::ACTION_NONE);
    }
});

it('PROVISION_PENDING num canal que estava healthy = QUEDA real (ADR 0287 — fim da queda invisível)', function () {
    // connected=false num canal que estava no ar: a Jana sumiu sem o app marcar.
    expect(Probe::decideAction(WhatsmeowState::PROVISION_PENDING, 'healthy', false))->toBe(Probe::ACTION_DISCONNECTED);
    // inbound recente prova "no ar" → suprime o falso disconnected (corroboração ADR 0286).
    expect(Probe::decideAction(WhatsmeowState::PROVISION_PENDING, 'healthy', true))->toBe(Probe::ACTION_NONE);
});

it('PROVISION_PENDING em canal nunca-pareado / já caído segue transitório (não marca)', function () {
    expect(Probe::decideAction(WhatsmeowState::PROVISION_PENDING, 'never_checked', false))->toBe(Probe::ACTION_NONE);
    expect(Probe::decideAction(WhatsmeowState::PROVISION_PENDING, 'disconnected', false))->toBe(Probe::ACTION_NONE);
});
