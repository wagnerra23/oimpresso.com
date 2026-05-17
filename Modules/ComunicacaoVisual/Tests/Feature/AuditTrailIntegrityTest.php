<?php

declare(strict_types=1);

/**
 * AuditTrailIntegrityTest — valida integridade do audit trail Spatie ActivityLog
 * pras 3 entities core ComunicacaoVisual (D7 LGPD compliance saturação Wave 25).
 *
 * Cobre:
 *  - Whitelist `logOnly` declarado em cada Entity (não loga campo sensível)
 *  - `logOnlyDirty` ativo (não polui DB com toda update)
 *  - `useLogName` namespaced (`comvis.*`) — separação SoC entre módulos
 *  - `dontSubmitEmptyLogs` ativo (anti-noise)
 *  - Whitelist Apontamento NÃO inclui `observacoes` (pode ter PII free-text)
 *  - Whitelist Orcamento NÃO inclui `contato_id` (PII reference)
 *
 * Multi-tenant Tier 0: testes reflection-only (sem DB) — compatível Hostinger + CT 100.
 *
 * Wave 25 — saturação D7 forensic regressão (rubrica scoped vertical_client_facing.yaml).
 *
 * @see Modules/ComunicacaoVisual/Entities/Orcamento.php
 * @see Modules/ComunicacaoVisual/Entities/Os.php
 * @see Modules/ComunicacaoVisual/Entities/Apontamento.php
 */

use Modules\ComunicacaoVisual\Entities\Apontamento;
use Modules\ComunicacaoVisual\Entities\Orcamento;
use Modules\ComunicacaoVisual\Entities\Os;
use Spatie\Activitylog\LogOptions;

it('Orcamento.getActivitylogOptions() retorna LogOptions namespaced comvis.orcamento', function () {
    $opts = (new Orcamento())->getActivitylogOptions();

    expect($opts)->toBeInstanceOf(LogOptions::class);
    expect($opts->logName)->toBe('comvis.orcamento',
        'logName deve isolar módulo pra grep facilitada (SoC brutal — ADR 0094)');
});

it('Os.getActivitylogOptions() retorna LogOptions namespaced comvis.os', function () {
    $opts = (new Os())->getActivitylogOptions();

    expect($opts->logName)->toBe('comvis.os');
});

it('Apontamento.getActivitylogOptions() retorna LogOptions namespaced comvis.apontamento', function () {
    $opts = (new Apontamento())->getActivitylogOptions();

    expect($opts->logName)->toBe('comvis.apontamento');
});

it('Orcamento whitelist logOnly NÃO inclui contato_id (PII reference) nem observacoes (free-text)', function () {
    $opts = (new Orcamento())->getActivitylogOptions();

    expect($opts->logAttributes)->not->toContain('contato_id',
        'contato_id é PII reference — não logar em activity_log');
    expect($opts->logAttributes)->not->toContain('observacoes',
        'observacoes pode conter PII free-text — não logar');
});

it('Apontamento whitelist logOnly NÃO inclui observacoes nem operador_id', function () {
    $opts = (new Apontamento())->getActivitylogOptions();

    expect($opts->logAttributes)->not->toContain('observacoes',
        'observacoes pode conter PII free-text — não logar');
    expect($opts->logAttributes)->not->toContain('operador_id',
        'operador_id é PII reference — log captura via causer Spatie automaticamente');
});

it('whitelist logOnly cobre campos críticos de negócio (status/totais/datas)', function () {
    $orcOpts = (new Orcamento())->getActivitylogOptions();
    expect($orcOpts->logAttributes)->toContain('status');
    expect($orcOpts->logAttributes)->toContain('total');

    $osOpts = (new Os())->getActivitylogOptions();
    expect($osOpts->logAttributes)->toContain('status_etapa',
        'status_etapa é coluna crítica FSM-trackável (rastreabilidade produtiva)');
    expect($osOpts->logAttributes)->toContain('valor_total');

    $apOpts = (new Apontamento())->getActivitylogOptions();
    expect($apOpts->logAttributes)->toContain('m2_produzido',
        'm² produzido é coluna crítica drift produtivo (audit fiscal)');
    expect($apOpts->logAttributes)->toContain('drift_percent');
});

it('logOnlyDirty ativo previne polution log (só mudanças reais)', function () {
    foreach ([Orcamento::class, Os::class, Apontamento::class] as $class) {
        $opts = (new $class())->getActivitylogOptions();
        expect($opts->logOnlyDirty)->toBeTrue(
            "{$class} deve ter logOnlyDirty=true (anti-noise — só mudanças reais)"
        );
    }
});

it('dontSubmitEmptyLogs ativo previne log entry sem mudança real', function () {
    foreach ([Orcamento::class, Os::class, Apontamento::class] as $class) {
        $opts = (new $class())->getActivitylogOptions();
        expect($opts->submitEmptyLogs)->toBeFalse(
            "{$class} deve ter dontSubmitEmptyLogs (anti-noise — não criar entry vazia)"
        );
    }
});
