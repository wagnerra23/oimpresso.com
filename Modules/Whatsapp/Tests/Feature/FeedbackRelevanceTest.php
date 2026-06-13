<?php

declare(strict_types=1);

use App\Contact;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Whatsapp\Entities\ClientFeedback;
use Modules\Whatsapp\Http\Controllers\Admin\ClientFeedbackController;
use Modules\Whatsapp\Services\FeedbackRelevanceService;

uses(Tests\TestCase::class);

/**
 * Guard tests ADR 0195 — feedback relevance scoring + signature dedup + decay.
 *
 * Cobre:
 *   001. signature determinística (mesma input → mesmo hash)
 *   002. normalizeLiteral filtra stopwords + < 3 chars
 *   003. computeScore segue fórmula: sev=4 + recor=5 + pagante + primary → ~95
 *   004. dedup: 2ª captura mesma signature em 90d → recorrente_count++, NÃO cria
 *   005. pattern_emergente true quando recorrente >= 3
 *   006. classify HOT/WARM/COLD por score boundary
 *   007. Observer creating computa signature automaticamente
 *   008. cross-tenant: signature inclui business_id (mesma frase em biz=1 e biz=2 → hashes diferentes)
 */
beforeEach(function () {
    if (DB::connection()->getDriverName() !== 'sqlite') {
        test()->markTestSkipped('era-sqlite: schema sintético manual incompatível com MySQL persistente — quarentena Onda 2 SDD floor; burn-down converte depois.');
    }

    foreach (['clients_feedbacks', 'contacts', 'activity_log'] as $t) {
        Schema::dropIfExists($t);
    }

    Schema::create('activity_log', function ($table) {
        $table->bigIncrements('id');
        $table->string('log_name')->nullable();
        $table->text('description')->nullable();
        $table->unsignedBigInteger('subject_id')->nullable();
        $table->string('subject_type')->nullable();
        $table->unsignedBigInteger('causer_id')->nullable();
        $table->string('causer_type')->nullable();
        $table->text('properties')->nullable();
        $table->uuid('batch_uuid')->nullable();
        $table->string('event')->nullable();
        $table->timestamps();
    });

    Schema::create('contacts', function ($table) {
        $table->increments('id');
        $table->unsignedInteger('business_id');
        $table->string('name', 191);
        $table->string('mobile', 191)->nullable();
        $table->string('email', 191)->nullable();
        $table->string('type', 191)->default('customer');
        $table->string('contact_type', 191)->nullable();
        $table->timestamp('deleted_at')->nullable();
        $table->timestamps();
    });

    Schema::create('clients_feedbacks', function ($table) {
        $table->id();
        $table->unsignedInteger('business_id')->index();
        $table->unsignedBigInteger('contact_id')->nullable()->index();
        $table->unsignedBigInteger('source_message_id')->nullable()->index();
        $table->unsignedBigInteger('conversation_id')->nullable()->index();
        $table->string('persona_slug', 80)->nullable()->index();
        $table->string('cliente_slug', 80)->nullable();
        $table->string('signature', 40)->nullable();
        $table->decimal('relevance_score', 5, 2)->default(0);
        $table->timestamp('relevance_score_at')->nullable();
        $table->timestamp('last_seen_at')->nullable();
        $table->string('canal', 32)->default('whatsapp');
        $table->text('literal');
        $table->text('contexto')->nullable();
        $table->string('modulo_afetado', 80)->nullable();
        $table->string('tela_afetada', 160)->nullable();
        $table->string('acao_afetada', 80)->nullable();
        $table->string('job', 255)->nullable();
        $table->string('motivacao_tipo', 24)->nullable();
        $table->string('workaround_o_que_faz', 255)->nullable();
        $table->string('workaround_custo', 255)->nullable();
        $table->unsignedTinyInteger('severity_nng')->default(2);
        $table->boolean('primeira_vez')->default(true);
        $table->unsignedSmallInteger('recorrente_count')->default(1);
        $table->boolean('pattern_emergente')->default(false);
        $table->string('status', 20)->default('novo')->index();
        $table->text('responder_cliente')->nullable();
        $table->string('mcp_task_id', 80)->nullable();
        $table->boolean('dev_task_requested')->default(false);
        $table->timestamp('data_resolvido')->nullable();
        $table->string('pr_link', 255)->nullable();
        $table->boolean('cliente_confirmou')->nullable();
        $table->boolean('re_reclamacao')->default(false);
        $table->unsignedBigInteger('created_by')->nullable();
        $table->timestamps();
        $table->softDeletes();
    });
});

it('R-WA-FR-001 — signature é determinística (mesma input → mesmo hash)', function () {
    $svc = app(FeedbackRelevanceService::class);

    $fb1 = new ClientFeedback([
        'business_id' => 1,
        'persona_slug' => 'kamila-martinho',
        'modulo_afetado' => 'fiscal',
        'acao_afetada' => 'emitir-nfe',
        'literal' => 'tô tentando emitir nota mas dá erro SEFAZ',
    ]);

    $fb2 = new ClientFeedback([
        'business_id' => 1,
        'persona_slug' => 'kamila-martinho',
        'modulo_afetado' => 'fiscal',
        'acao_afetada' => 'emitir-nfe',
        'literal' => 'tô tentando emitir nota mas dá erro SEFAZ',
    ]);

    expect($svc->computeSignature($fb1))->toBe($svc->computeSignature($fb2));
    expect(strlen($svc->computeSignature($fb1)))->toBe(40); // sha1
});

it('R-WA-FR-002 — normalizeLiteral filtra stopwords + < 3 chars + lower', function () {
    $svc = app(FeedbackRelevanceService::class);

    $norm = $svc->normalizeLiteral('Eu TÔ tentando emitir Nota Fiscal mas DÁ erro! socorro');

    // "tô" stopword; "dá" 2 chars filter; "mas" stopword.
    // Significativas: tentando, emitir, nota, fiscal, erro
    expect($norm)->toBe('tentando emitir nota fiscal erro');
});

it('R-WA-FR-003 — computeScore: sev=4 + recor=5 + pagante + primary → ~95', function () {
    $svc = app(FeedbackRelevanceService::class);

    $contact = Contact::create([
        'business_id' => 1, 'name' => 'Kamila', 'mobile' => '+5548999999999', 'type' => 'customer',
    ]);

    $fb = new ClientFeedback([
        'business_id' => 1,
        'contact_id' => $contact->id,
        'persona_slug' => 'kamila-martinho',
        'modulo_afetado' => 'financeiro', // kamila-martinho é primary em financeiro
        'severity_nng' => 4,
        'recorrente_count' => 5,
        'last_seen_at' => now(),
        'literal' => 'erro financeiro',
    ]);

    $score = $svc->computeScore($fb);
    // Esperado: 40*1 + 25*log10(6)/log10(6)=25 + 15*1 + 10*1 + 10*1 = 100
    expect($score)->toBeGreaterThan(95);
    expect($score)->toBeLessThanOrEqual(100);
});

it('R-WA-FR-004 — dedup: 2ª captura mesma signature em 90d → recorrente_count++, NÃO cria duplicata', function () {
    session()->put('user.business_id', 1);
    session()->put('user.id', 7);

    $contact = Contact::create([
        'business_id' => 1, 'name' => 'Kamila', 'mobile' => '+5548888888888', 'type' => 'customer',
    ]);

    $payload = [
        'literal' => 'erro emissão NF-e SEFAZ timeout',
        'severity_nng' => 3,
        'contact_id' => $contact->id,
        'persona_slug' => 'kamila-martinho',
        'modulo_afetado' => 'fiscal',
        'acao_afetada' => 'emitir-nfe',
    ];

    // 1ª captura
    $req1 = Request::create('/atendimento/feedback/capture', 'POST', $payload);
    $req1->setLaravelSession(session());
    $resp1 = app(ClientFeedbackController::class)->capture($req1);
    $data1 = $resp1->getData(true);

    // 2ª captura idêntica
    $req2 = Request::create('/atendimento/feedback/capture', 'POST', $payload);
    $req2->setLaravelSession(session());
    $resp2 = app(ClientFeedbackController::class)->capture($req2);
    $data2 = $resp2->getData(true);

    // Mesmo feedback ID
    expect($data2['feedback']['id'])->toBe($data1['feedback']['id']);
    expect($data2['dedup']['matched_existing'])->toBeTrue();
    expect($data2['dedup']['recorrente_count'])->toBe(2);
    expect(ClientFeedback::count())->toBe(1);  // só 1 registro
});

it('R-WA-FR-005 — pattern_emergente vira true quando recorrente >= 3', function () {
    session()->put('user.business_id', 1);
    session()->put('user.id', 7);

    $contact = Contact::create([
        'business_id' => 1, 'name' => 'Kamila', 'mobile' => '+5548777777777', 'type' => 'customer',
    ]);

    $payload = [
        'literal' => 'travou no fechamento mensal',
        'severity_nng' => 3,
        'contact_id' => $contact->id,
        'persona_slug' => 'kamila-martinho',
        'modulo_afetado' => 'financeiro',
        'acao_afetada' => 'fechar-mes',
    ];

    foreach (range(1, 3) as $i) {
        $req = Request::create('/atendimento/feedback/capture', 'POST', $payload);
        $req->setLaravelSession(session());
        app(ClientFeedbackController::class)->capture($req);
    }

    $fb = ClientFeedback::first();
    expect($fb->recorrente_count)->toBe(3);
    expect($fb->pattern_emergente)->toBeTrue();
});

it('R-WA-FR-006 — classify HOT/WARM/COLD baseado em score boundary', function () {
    $svc = app(FeedbackRelevanceService::class);

    $contact = Contact::create([
        'business_id' => 1, 'name' => 'C', 'mobile' => '+1', 'type' => 'customer',
    ]);

    // HOT: score >= 70 (sev=4 + pagante + primary)
    $fb_hot = ClientFeedback::create([
        'business_id' => 1, 'contact_id' => $contact->id,
        'literal' => 'crítico bloqueia',
        'severity_nng' => 4, 'recorrente_count' => 3,
        'persona_slug' => 'kamila-martinho', 'modulo_afetado' => 'financeiro',
        'status' => 'novo',
    ]);
    expect($svc->classify($fb_hot))->toBe('HOT');

    // COLD: score baixo (lead + sev=1 + sem persona)
    $fb_cold = ClientFeedback::create([
        'business_id' => 1, 'contact_id' => null,
        'literal' => 'cosmético sem dor',
        'severity_nng' => 1, 'recorrente_count' => 1,
        'status' => 'novo',
    ]);
    expect($svc->classify($fb_cold))->toBe('COLD');
});

it('R-WA-FR-007 — Observer creating computa signature + score automaticamente', function () {
    $contact = Contact::create([
        'business_id' => 1, 'name' => 'C', 'mobile' => '+1', 'type' => 'customer',
    ]);

    $fb = ClientFeedback::create([
        'business_id' => 1, 'contact_id' => $contact->id,
        'literal' => 'auto-compute signature funciona',
        'severity_nng' => 3,
        'persona_slug' => 'larissa-rota-livre', 'modulo_afetado' => 'sells',
    ]);

    expect($fb->signature)->not->toBeNull();
    expect(strlen($fb->signature))->toBe(40);
    expect((float) $fb->relevance_score)->toBeGreaterThan(0);
    expect($fb->relevance_score_at)->not->toBeNull();
    expect($fb->last_seen_at)->not->toBeNull();
});

it('R-WA-FR-008 — signature inclui business_id (mesma frase em biz=1 e biz=2 → hashes diferentes)', function () {
    $svc = app(FeedbackRelevanceService::class);

    $fb1 = new ClientFeedback([
        'business_id' => 1, 'persona_slug' => 'p', 'modulo_afetado' => 'm',
        'acao_afetada' => 'a', 'literal' => 'mesma reclamação literal',
    ]);
    $fb2 = new ClientFeedback([
        'business_id' => 2, 'persona_slug' => 'p', 'modulo_afetado' => 'm',
        'acao_afetada' => 'a', 'literal' => 'mesma reclamação literal',
    ]);

    expect($svc->computeSignature($fb1))->not->toBe($svc->computeSignature($fb2));
});
