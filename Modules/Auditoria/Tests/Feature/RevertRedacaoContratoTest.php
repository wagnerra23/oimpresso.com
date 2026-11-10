<?php

declare(strict_types=1);

use App\Providers\OtelServiceProvider;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Auditoria\Services\RevertService;
use OpenTelemetry\API\Globals;
use OpenTelemetry\API\Instrumentation\Configurator;
use OpenTelemetry\SDK\Trace\SpanExporter\InMemoryExporter;
use Spatie\Activitylog\Models\Activity;

/**
 * O que RevertService::revert() grava de verdade: subject restaurado, justificativa
 * sem PII e span sem o texto livre. Substitui os testes que liam o arquivo do serviço
 * procurando as strings `->redact(` e `$log->save()` (presença não prova comportamento).
 *
 * O revert roda de fato: o HTTP ainda responde 501, mas o serviço existe e é ele que
 * vai gravar quando for ligado.
 *
 * Tenant fictício via seededTenant() (ADR 0358), nunca biz=4. Transação revertida.
 *
 * @see Modules/Auditoria/Services/RevertService.php
 * @see memory/decisions/0127-modulo-auditoria-ui-undo.md
 */

uses(Tests\TestCase::class, DatabaseTransactions::class);

const AUD_REVERT_CPF = '123.456.789-09'; # pii-allowlist

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompativel: activity_log com colunas de reversão requer schema MySQL');
    }
    if (! Schema::hasColumn('activity_log', 'revert_reason')) {
        $this->markTestSkipped('coluna revert_reason ausente em activity_log — rode a migration US-AUDIT-005');
    }

    $business = $this->seededTenant();
    $this->by = $this->usuarioComPermissoes(['auditoria.revert.unlimited'], $business);

    $this->contactId = DB::table('contacts')->insertGetId([
        'business_id' => $business->id,
        'type'        => 'customer',
        'name'        => 'Nome Atual',
        'contact_id'  => 'AUD'.uniqid(),
        'created_by'  => $this->by->id,
        'is_default'  => 0,
        'created_at'  => now(),
        'updated_at'  => now(),
    ]);

    $this->log = Activity::create([
        'log_name'     => 'auditoria-revert-contrato',
        'description'  => 'updated',
        'event'        => 'updated',
        'business_id'  => $business->id,
        'subject_type' => \App\Contact::class,
        'subject_id'   => $this->contactId,
        'causer_type'  => \App\User::class,
        'causer_id'    => $this->by->id,
        'properties'   => ['old' => ['name' => 'Nome Antigo'], 'attributes' => ['name' => 'Nome Atual']],
    ]);

    $this->service = new RevertService();
});

function auditRevertSpans(callable $acao): array
{
    config(['otel.enabled' => true, 'otel.sdk_disabled' => false, 'otel.sample_rate' => 1.0, 'otel.service_name' => 'oimpresso-test']);
    Globals::reset();
    $exporter = new InMemoryExporter();
    $provider = (new OtelServiceProvider(app()))->buildTracerProvider($exporter);
    Globals::registerInitializer(fn (Configurator $c) => $c->withTracerProvider($provider));

    try {
        $acao();
        $provider->forceFlush();

        return $exporter->getSpans();
    } finally {
        $provider->shutdown();
        Globals::reset();
        config(['otel.enabled' => false]);
    }
}

it('revert restaura o subject e marca a linha original', function () {
    $entry = $this->service->revert($this->log, $this->by, 'Correção do cadastro a pedido do cliente');

    expect(DB::table('contacts')->where('id', $this->contactId)->value('name'))->toBe('Nome Antigo');

    $original = $this->log->fresh();
    expect($original->reverted_at)->not->toBeNull();
    expect((int) $original->reverted_by_user_id)->toBe((int) $this->by->id);
    expect($entry->event)->toBe('reverted');
    expect((int) $entry->business_id)->toBe((int) $this->log->business_id);
});

it('CPF da justificativa não chega a nenhuma linha gravada', function () {
    $reason = 'Cliente CPF '.AUD_REVERT_CPF.' pediu a correção';

    $entry = $this->service->revert($this->log, $this->by, $reason);

    $original = DB::table('activity_log')->where('id', $this->log->id)->first();
    $nova = DB::table('activity_log')->where('id', $entry->id)->first();

    expect($original->revert_reason)->toContain('[REDACTED:CPF]');
    expect($original->revert_reason)->not->toContain(AUD_REVERT_CPF);
    expect($nova->properties)->toContain('[REDACTED:CPF]');
    expect($nova->properties)->not->toContain(AUD_REVERT_CPF);
});

it('justificativa curta é recusada sem alterar nada', function () {
    expect(fn () => $this->service->revert($this->log, $this->by, 'curta'))
        ->toThrow(\InvalidArgumentException::class);

    expect(DB::table('contacts')->where('id', $this->contactId)->value('name'))->toBe('Nome Atual');
    expect($this->log->fresh()->reverted_at)->toBeNull();
});

it('span auditoria.revert.execute leva IDs e flag, nunca o texto da justificativa', function () {
    $reason = 'Cliente CPF '.AUD_REVERT_CPF.' pediu a correção';

    $spans = auditRevertSpans(fn () => $this->service->revert($this->log, $this->by, $reason));
    $span = collect($spans)->first(fn ($s) => $s->getName() === 'auditoria.revert.execute');

    expect($span)->not->toBeNull();
    $attrs = $span->getAttributes()->toArray();
    expect($attrs['activity_id'])->toBe($this->log->id);
    expect($attrs['has_reason'])->toBeTrue();
    expect($attrs['restored_attrs_count'])->toBe(1);
    expect($attrs)->not->toHaveKey('reason');
    expect(json_encode($attrs))->not->toContain('pediu a correção');
});
