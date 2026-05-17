<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Crm\Entities\Deal;
use Modules\Crm\Services\DealPipelineService;

uses(Tests\TestCase::class);

/**
 * W27 Crm Deal Pipeline Kanban (Pipedrive/HubSpot-like).
 *
 * Cobre:
 *   - Deal entity: stages enum + probabilidades + valorPonderado() + scopes
 *   - DealPipelineService: moverStage + pipelineSummary + forecastFechamento
 *   - Multi-tenant Tier 0 (ADR 0093): cross-tenant isolation biz=1 vs biz=99
 *   - LogsActivity audit trail (sem PII em description — ADR 0093 §LGPD)
 *
 * Estratégia DB:
 *   - SQLite ok pra maioria dos testes (schema crm_deals criado on-the-fly sem FK pra business)
 *   - Cross-tenant biz=1 vs biz=99 roda em SQLite isolado (sem dependência FK)
 *
 * Multi-tenant: testes usam biz=99 fictício; biz=1 só onde necessário pra simular owner real.
 *
 * @see Modules/Crm/Entities/Deal.php
 * @see Modules/Crm/Services/DealPipelineService.php
 * @see memory/decisions/0093-multi-tenant-isolation-tier-0.md
 * @see memory/decisions/0101-tests-business-id-1-nunca-cliente.md
 */

const W27_BIZ_FICTICIO = 99;
const W27_BIZ_FICTICIO_B = 88;
const W27_OWNER_ID = 1;

beforeEach(function () {
    // Cria schema isolado pra Deal sem depender de FK pra `business` (UltimatePOS-only).
    // Mantém compatibilidade SQLite + MySQL local.
    if (! Schema::hasTable('crm_deals')) {
        Schema::create('crm_deals', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('business_id')->index();
            $table->unsignedBigInteger('contact_id')->nullable();
            $table->unsignedBigInteger('proposal_id')->nullable();
            $table->string('titulo', 191);
            $table->string('stage', 32)->default('lead');
            $table->decimal('valor_estimado', 12, 2)->default(0);
            $table->date('data_fechamento_prevista')->nullable();
            $table->unsignedBigInteger('owner_user_id');
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    // Spatie activity_log (LogsActivity trait depende). Cria minimal em SQLite se faltar.
    if (! Schema::hasTable('activity_log')) {
        Schema::create('activity_log', function (Blueprint $table) {
            $table->id();
            $table->string('log_name')->nullable();
            $table->text('description');
            $table->nullableMorphs('subject', 'subject');
            $table->string('event')->nullable();
            $table->nullableMorphs('causer', 'causer');
            $table->longText('properties')->nullable();
            $table->uuid('batch_uuid')->nullable();
            $table->timestamps();
        });
    }

    // Cleanup: garante isolamento entre testes.
    DB::table('crm_deals')->whereIn('business_id', [W27_BIZ_FICTICIO, W27_BIZ_FICTICIO_B])->delete();
});

afterEach(function () {
    DB::table('crm_deals')->whereIn('business_id', [W27_BIZ_FICTICIO, W27_BIZ_FICTICIO_B])->delete();
});

// ----------------------------------------------------------------------
// Deal entity — stages enum, probabilidades, valorPonderado()
// ----------------------------------------------------------------------

describe('Deal entity', function () {
    it('expõe constantes de stages canônicos PT-BR', function () {
        expect(Deal::STAGES)->toBe([
            'lead', 'qualificacao', 'proposta', 'negociacao', 'ganho', 'perdido',
        ]);
        expect(Deal::STAGE_LEAD)->toBe('lead');
        expect(Deal::STAGE_GANHO)->toBe('ganho');
    });

    it('cria Deal biz=99 com defaults coerentes', function () {
        $deal = Deal::create([
            'business_id' => W27_BIZ_FICTICIO,
            'titulo' => 'Deal W27 teste',
            'valor_estimado' => 5000.00,
            'owner_user_id' => W27_OWNER_ID,
        ]);

        expect($deal->stage)->toBe('lead');
        expect((float) $deal->valor_estimado)->toBe(5000.00);
        expect($deal->isTerminal())->toBeFalse();
        expect($deal->probabilidade())->toBe(0.10);
        expect($deal->valorPonderado())->toBe(500.00); // 5000 × 0.10
    });

    it('isTerminal() true em ganho/perdido', function () {
        $ganho = new Deal(['stage' => 'ganho']);
        $perdido = new Deal(['stage' => 'perdido']);
        $aberto = new Deal(['stage' => 'negociacao']);

        expect($ganho->isTerminal())->toBeTrue();
        expect($perdido->isTerminal())->toBeTrue();
        expect($aberto->isTerminal())->toBeFalse();
    });

    it('PROBABILIDADES_DEFAULT cobre todos os stages', function () {
        foreach (Deal::STAGES as $stage) {
            expect(Deal::PROBABILIDADES_DEFAULT)->toHaveKey($stage);
        }
        expect(Deal::PROBABILIDADES_DEFAULT['ganho'])->toBe(1.00);
        expect(Deal::PROBABILIDADES_DEFAULT['perdido'])->toBe(0.00);
    });

    it('scope abertos() exclui ganho e perdido', function () {
        Deal::create(['business_id' => W27_BIZ_FICTICIO, 'titulo' => 'A', 'stage' => 'lead', 'valor_estimado' => 100, 'owner_user_id' => W27_OWNER_ID]);
        Deal::create(['business_id' => W27_BIZ_FICTICIO, 'titulo' => 'B', 'stage' => 'ganho', 'valor_estimado' => 200, 'owner_user_id' => W27_OWNER_ID]);
        Deal::create(['business_id' => W27_BIZ_FICTICIO, 'titulo' => 'C', 'stage' => 'perdido', 'valor_estimado' => 300, 'owner_user_id' => W27_OWNER_ID]);

        $abertos = Deal::where('business_id', W27_BIZ_FICTICIO)->abertos()->get();
        expect($abertos)->toHaveCount(1);
        expect($abertos->first()->titulo)->toBe('A');
    });
});

// ----------------------------------------------------------------------
// DealPipelineService — moverStage
// ----------------------------------------------------------------------

describe('DealPipelineService::moverStage', function () {
    it('move deal de lead pra qualificacao e persiste metadata transition', function () {
        $svc = new DealPipelineService();
        $deal = Deal::create([
            'business_id' => W27_BIZ_FICTICIO,
            'titulo' => 'Move W27',
            'valor_estimado' => 1000.00,
            'owner_user_id' => W27_OWNER_ID,
        ]);

        $atualizado = $svc->moverStage(W27_BIZ_FICTICIO, $deal->id, 'qualificacao', 'cliente respondeu email');

        expect($atualizado->stage)->toBe('qualificacao');
        expect($atualizado->metadata)->toHaveKey('transitions');
        expect($atualizado->metadata['transitions'][0]['from'])->toBe('lead');
        expect($atualizado->metadata['transitions'][0]['to'])->toBe('qualificacao');
        expect($atualizado->metadata['transitions'][0]['razao'])->toBe('cliente respondeu email');
    });

    it('lança InvalidArgumentException pra stage inválido', function () {
        $svc = new DealPipelineService();
        $deal = Deal::create([
            'business_id' => W27_BIZ_FICTICIO,
            'titulo' => 'Invalid',
            'valor_estimado' => 100,
            'owner_user_id' => W27_OWNER_ID,
        ]);

        expect(fn () => $svc->moverStage(W27_BIZ_FICTICIO, $deal->id, 'estagio_inexistente'))
            ->toThrow(InvalidArgumentException::class);
    });

    it('lança RuntimeException quando deal em stage terminal tenta mover', function () {
        $svc = new DealPipelineService();
        $deal = Deal::create([
            'business_id' => W27_BIZ_FICTICIO,
            'titulo' => 'Terminal',
            'stage' => 'ganho',
            'valor_estimado' => 100,
            'owner_user_id' => W27_OWNER_ID,
        ]);

        expect(fn () => $svc->moverStage(W27_BIZ_FICTICIO, $deal->id, 'negociacao'))
            ->toThrow(RuntimeException::class);
    });

    it('cross-tenant — biz=88 NÃO consegue mover deal de biz=99', function () {
        $svc = new DealPipelineService();
        $deal = Deal::create([
            'business_id' => W27_BIZ_FICTICIO,
            'titulo' => 'Cross-tenant',
            'valor_estimado' => 100,
            'owner_user_id' => W27_OWNER_ID,
        ]);

        expect(fn () => $svc->moverStage(W27_BIZ_FICTICIO_B, $deal->id, 'qualificacao'))
            ->toThrow(RuntimeException::class, "não encontrado em business");
    });
});

// ----------------------------------------------------------------------
// DealPipelineService::pipelineSummary
// ----------------------------------------------------------------------

describe('DealPipelineService::pipelineSummary', function () {
    it('agrega count + valor por stage com todos os 6 stages presentes', function () {
        $svc = new DealPipelineService();

        Deal::create(['business_id' => W27_BIZ_FICTICIO, 'titulo' => 'L1', 'stage' => 'lead', 'valor_estimado' => 1000, 'owner_user_id' => W27_OWNER_ID]);
        Deal::create(['business_id' => W27_BIZ_FICTICIO, 'titulo' => 'L2', 'stage' => 'lead', 'valor_estimado' => 2000, 'owner_user_id' => W27_OWNER_ID]);
        Deal::create(['business_id' => W27_BIZ_FICTICIO, 'titulo' => 'P1', 'stage' => 'proposta', 'valor_estimado' => 5000, 'owner_user_id' => W27_OWNER_ID]);
        Deal::create(['business_id' => W27_BIZ_FICTICIO, 'titulo' => 'G1', 'stage' => 'ganho', 'valor_estimado' => 10000, 'owner_user_id' => W27_OWNER_ID]);

        $summary = $svc->pipelineSummary(W27_BIZ_FICTICIO);

        // Todos os 6 stages + totais.
        foreach (Deal::STAGES as $stage) {
            expect($summary)->toHaveKey($stage);
        }
        expect($summary)->toHaveKey('totais');

        expect($summary['lead']['count'])->toBe(2);
        expect($summary['lead']['valor_total'])->toBe(3000.00);
        expect($summary['lead']['valor_ponderado'])->toBe(300.00); // 3000 × 0.10

        expect($summary['proposta']['count'])->toBe(1);
        expect($summary['proposta']['valor_total'])->toBe(5000.00);
        expect($summary['proposta']['valor_ponderado'])->toBe(2500.00); // 5000 × 0.50

        expect($summary['ganho']['count'])->toBe(1);
        expect($summary['ganho']['valor_ponderado'])->toBe(10000.00); // 10000 × 1.00

        // Stage vazio reporta zero, não null/missing.
        expect($summary['negociacao']['count'])->toBe(0);
        expect($summary['negociacao']['valor_total'])->toBe(0.00);

        expect($summary['totais']['count'])->toBe(4);
        expect($summary['totais']['valor_total'])->toBe(18000.00);
        expect($summary['totais']['valor_ponderado'])->toBe(12800.00); // 300+2500+10000
    });

    it('cross-tenant — pipelineSummary biz=99 NÃO vê deals biz=88', function () {
        $svc = new DealPipelineService();

        Deal::create(['business_id' => W27_BIZ_FICTICIO, 'titulo' => 'V99', 'stage' => 'lead', 'valor_estimado' => 100, 'owner_user_id' => W27_OWNER_ID]);
        Deal::create(['business_id' => W27_BIZ_FICTICIO_B, 'titulo' => 'V88', 'stage' => 'lead', 'valor_estimado' => 999999, 'owner_user_id' => W27_OWNER_ID]);

        $summary = $svc->pipelineSummary(W27_BIZ_FICTICIO);

        expect($summary['lead']['count'])->toBe(1);
        expect($summary['lead']['valor_total'])->toBe(100.00); // sem leak biz=88
        expect($summary['totais']['valor_total'])->toBe(100.00);
    });
});

// ----------------------------------------------------------------------
// DealPipelineService::forecastFechamento (weighted forecast)
// ----------------------------------------------------------------------

describe('DealPipelineService::forecastFechamento', function () {
    it('soma valor ponderado de deals abertos dentro do período', function () {
        $svc = new DealPipelineService();

        // Dentro do período (próximos 30 dias) — abertos — entram no forecast
        Deal::create([
            'business_id' => W27_BIZ_FICTICIO, 'titulo' => 'F1', 'stage' => 'proposta',
            'valor_estimado' => 10000, 'data_fechamento_prevista' => now()->addDays(15),
            'owner_user_id' => W27_OWNER_ID,
        ]); // 10000 × 0.50 = 5000

        Deal::create([
            'business_id' => W27_BIZ_FICTICIO, 'titulo' => 'F2', 'stage' => 'negociacao',
            'valor_estimado' => 4000, 'data_fechamento_prevista' => now()->addDays(20),
            'owner_user_id' => W27_OWNER_ID,
        ]); // 4000 × 0.75 = 3000

        // Fora do período — não entra
        Deal::create([
            'business_id' => W27_BIZ_FICTICIO, 'titulo' => 'F3', 'stage' => 'lead',
            'valor_estimado' => 99999, 'data_fechamento_prevista' => now()->addDays(60),
            'owner_user_id' => W27_OWNER_ID,
        ]);

        // Terminal (ganho) — não entra em forecast (já fechado)
        Deal::create([
            'business_id' => W27_BIZ_FICTICIO, 'titulo' => 'F4', 'stage' => 'ganho',
            'valor_estimado' => 5000, 'data_fechamento_prevista' => now()->addDays(10),
            'owner_user_id' => W27_OWNER_ID,
        ]);

        // Sem data_fechamento_prevista — intencionalmente fora
        Deal::create([
            'business_id' => W27_BIZ_FICTICIO, 'titulo' => 'F5', 'stage' => 'proposta',
            'valor_estimado' => 7777, 'owner_user_id' => W27_OWNER_ID,
        ]);

        $forecast = $svc->forecastFechamento(W27_BIZ_FICTICIO, now()->addDays(30));

        expect($forecast)->toBe(8000.00); // 5000 + 3000
    });

    it('forecast zero quando nenhum deal no período', function () {
        $svc = new DealPipelineService();
        $forecast = $svc->forecastFechamento(W27_BIZ_FICTICIO, now()->addDays(30));
        expect($forecast)->toBe(0.0);
    });

    it('cross-tenant — forecast biz=99 ignora deals biz=88', function () {
        $svc = new DealPipelineService();

        Deal::create([
            'business_id' => W27_BIZ_FICTICIO_B, 'titulo' => 'Leak', 'stage' => 'proposta',
            'valor_estimado' => 99999999, 'data_fechamento_prevista' => now()->addDays(5),
            'owner_user_id' => W27_OWNER_ID,
        ]);

        $forecast = $svc->forecastFechamento(W27_BIZ_FICTICIO, now()->addDays(30));
        expect($forecast)->toBe(0.0); // sem leak
    });
});

// ----------------------------------------------------------------------
// Audit LGPD — LogsActivity sem PII em description
// ----------------------------------------------------------------------

describe('LogsActivity audit trail (ADR 0093 LGPD)', function () {
    it('Deal::getActivitylogOptions configura logOnly fields sem PII', function () {
        $deal = new Deal();
        $opts = $deal->getActivitylogOptions();

        // logOnly definido: stage, valor_estimado, owner_user_id, data_fechamento_prevista
        // NÃO loga: titulo (pode ter nome cliente), contact_id (FK PII)
        $reflection = new ReflectionObject($opts);
        $prop = $reflection->getProperty('logAttributes');
        $prop->setAccessible(true);
        $logged = $prop->getValue($opts);

        expect($logged)->toContain('stage', 'valor_estimado', 'owner_user_id', 'data_fechamento_prevista');
        expect($logged)->not->toContain('titulo');
        expect($logged)->not->toContain('contact_id');
    });
});
