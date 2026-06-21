<?php

use Tests\TestCase;

uses(TestCase::class)->in('Feature');

// Browser (Pest 4) — boota o app Laravel pros testes em tests/Browser/. Sem isto,
// `config`/factory/visit rodam sem container → BindingResolutionException [config]
// (US-GOV-013 Fase A). DB trait NÃO entra aqui: browser test usa server real em
// subprocesso, então dado precisa estar COMMITADO (transação de RefreshDatabase não
// cruza). Telas autenticadas (Fase B) trarão seed commitado + actingAs cross-process.
uses(TestCase::class)->in('Browser');

// Pest 3.x descobre `Pest.php` somente em `tests/` (Bootstrappers\BootFiles),
// então o `uses(...)->in()` de módulos com test suites próprios precisa ficar
// AQUI mesmo. `realpath` resolve worktrees / junctions.
$kbFeatureDir = realpath(__DIR__ . '/../Modules/KB/Tests/Feature');
$kbUnitDir    = realpath(__DIR__ . '/../Modules/KB/Tests/Unit');
if ($kbFeatureDir !== false) { uses(TestCase::class)->in($kbFeatureDir); }
if ($kbUnitDir    !== false) { uses(TestCase::class)->in($kbUnitDir); }

// RecurringBilling — Spatie LogsActivity está ATIVO em Plan/Subscription/Invoice, mas
// vários testes do módulo montam schema sqlite manual sem `activity_log`, quebrando com
// "no such table: activity_log" no 1º create de model logável. Garante a tabela
// (idempotente, guarded) num beforeEach do módulo. Nenhum teste RB dropa/assume ausência
// de activity_log, então a criação central é segura (roda antes do beforeEach do arquivo).
$rbFeatureDir = realpath(__DIR__ . '/../Modules/RecurringBilling/Tests/Feature');
if ($rbFeatureDir !== false) {
    uses()->beforeEach(function () {
        if (! \Illuminate\Support\Facades\Schema::hasTable('activity_log')) {
            \Illuminate\Support\Facades\Schema::create('activity_log', function ($t) {
                $t->id();
                $t->string('log_name')->nullable();
                $t->text('description')->nullable();
                $t->unsignedBigInteger('subject_id')->nullable();
                $t->string('subject_type')->nullable();
                $t->unsignedBigInteger('causer_id')->nullable();
                $t->string('causer_type')->nullable();
                $t->json('properties')->nullable();
                $t->string('event')->nullable();
                $t->uuid('batch_uuid')->nullable();
                $t->timestamps();
            });
        }

        // contacts — Subscription/Invoice referenciam contact_id; o model Contact usa
        // SoftDeletes (query `deleted_at is null`). Garante a tabela (com deleted_at) pros
        // testes que não a montam. Quem dropa+recria contacts próprio sobrescreve.
        if (! \Illuminate\Support\Facades\Schema::hasTable('contacts')) {
            \Illuminate\Support\Facades\Schema::create('contacts', function ($t) {
                $t->increments('id');
                $t->unsignedInteger('business_id')->nullable()->index();
                $t->string('type')->nullable();
                $t->string('supplier_business_name')->nullable();
                $t->string('name')->nullable();
                $t->softDeletes();
                $t->timestamps();
            });
        }
    })->in($rbFeatureDir);
}

// Whatsapp — mesma dor do RB: muitos models têm Spatie LogsActivity, mas os testes
// montam schema sintético sem `activity_log` e quebram no 1º create logável
// ("no such table: activity_log" — 341 das falhas na auditoria de cobertura-CI
// 2026-06-19). Garante a tabela (idempotente) num beforeEach scoped ao dir.
//
// O dir Whatsapp é MISTO: tem testes de reflexão pura que NÃO bootam app completo
// (ex MessageHasArquivosTraitTest, sem `uses(TestCase)`). Neles `DB::connection()`
// estoura ("facade root not set" / config não carregado). Por isso envolvemos em
// try/catch: provisionar a tabela é best-effort — se não há DB booted, no-op. Não
// mascara nada: se um teste REAL precisar de activity_log e a criação falhar, ele
// quebra sozinho no 1º create logável. Só toca sqlite (no MySQL real a migration
// canônica já cria a tabela, então `hasTable` curto-circuita).
$waFeatureDir = realpath(__DIR__ . '/../Modules/Whatsapp/Tests/Feature');
if ($waFeatureDir !== false) {
    uses()->beforeEach(function () {
        try {
            if (\Illuminate\Support\Facades\DB::connection()->getDriverName() !== 'sqlite') {
                return;
            }
            if (! \Illuminate\Support\Facades\Schema::hasTable('activity_log')) {
                \Illuminate\Support\Facades\Schema::create('activity_log', function ($t) {
                    $t->id();
                    $t->string('log_name')->nullable();
                    $t->text('description')->nullable();
                    $t->unsignedBigInteger('subject_id')->nullable();
                    $t->string('subject_type')->nullable();
                    $t->unsignedBigInteger('causer_id')->nullable();
                    $t->string('causer_type')->nullable();
                    $t->json('properties')->nullable();
                    $t->string('event')->nullable();
                    $t->uuid('batch_uuid')->nullable();
                    $t->timestamps();
                });
            }
        } catch (\Throwable $e) {
            // teste sem app/DB booted (reflexão pura) — nada a provisionar
        }
    })->in($waFeatureDir);
}
