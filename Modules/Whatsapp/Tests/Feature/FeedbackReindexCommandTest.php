<?php

declare(strict_types=1);

use App\Contact;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Whatsapp\Entities\ClientFeedback;
use Modules\Whatsapp\Services\FeedbackIndexGenerator;

uses(Tests\TestCase::class);

/**
 * Guard tests ADR 0195 Fase B — feedback:reindex command + INDEX.md + archive.
 *
 * Cobre:
 *   001. Command roda sem erro + retorna exit 0
 *   002. reindexScores() recomputa quando score muda >= 0.5
 *   003. generateIndex() cria arquivo memory/feedback/INDEX.md
 *   004. INDEX inclui só HOT (score >= 70)
 *   005. INDEX é multi-tenant (separado por business)
 *   006. generateArchive() agrupa COLD por persona+modulo (sem literal PII)
 *   007. --skip-archive funciona
 *   008. --business=X filtra apenas aquele tenant
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

afterEach(function () {
    // Limpa arquivos gerados em base_path durante test (não polui git)
    $indexPath = base_path('memory/feedback/INDEX.md');
    if (file_exists($indexPath)) {
        @unlink($indexPath);
    }
    $archiveDir = base_path('memory/feedback/archive');
    if (is_dir($archiveDir)) {
        foreach (glob($archiveDir . '/*.md') as $f) {
            @unlink($f);
        }
    }
});

it('R-WA-FBR-001 — command feedback:reindex retorna exit 0', function () {
    $this->artisan('feedback:reindex --skip-archive --skip-index')
        ->assertSuccessful();
});

it('R-WA-FBR-002 — reindexScores() recompute quando score muda >= 0.5', function () {
    $contact = Contact::create([
        'business_id' => 1, 'name' => 'C', 'mobile' => '+1', 'type' => 'customer',
    ]);

    $fb = ClientFeedback::create([
        'business_id' => 1, 'contact_id' => $contact->id,
        'literal' => 'erro test',
        'severity_nng' => 3,
        'persona_slug' => 'kamila-martinho', 'modulo_afetado' => 'financeiro',
    ]);

    // Force score baixo manualmente
    $fb->relevance_score = 10;
    $fb->saveQuietly();

    $gen = app(FeedbackIndexGenerator::class);
    $stats = $gen->reindexScores();

    expect($stats['processed'])->toBeGreaterThanOrEqual(1);
    expect($stats['rescored'])->toBeGreaterThanOrEqual(1);

    $fb->refresh();
    expect((float) $fb->relevance_score)->toBeGreaterThan(50);
});

it('R-WA-FBR-003 — generateIndex() cria arquivo memory/feedback/INDEX.md', function () {
    $gen = app(FeedbackIndexGenerator::class);
    $path = $gen->generateIndex();

    expect(file_exists($path))->toBeTrue();
    expect($path)->toContain('memory');
    expect($path)->toContain('INDEX.md');

    $content = file_get_contents($path);
    expect($content)->toContain('Feedback HOT');
    expect($content)->toContain('ADR 0195');
});

it('R-WA-FBR-004 — INDEX inclui só HOT (score >= 70)', function () {
    $contact = Contact::create([
        'business_id' => 1, 'name' => 'C', 'mobile' => '+1', 'type' => 'customer',
    ]);

    // HOT (sev=4 + primary + cliente pagante)
    $hot = ClientFeedback::create([
        'business_id' => 1, 'contact_id' => $contact->id,
        'literal' => 'critico hot bloqueia operacao',
        'severity_nng' => 4, 'recorrente_count' => 4,
        'persona_slug' => 'kamila-martinho', 'modulo_afetado' => 'financeiro',
    ]);

    // WARM (sev=2 + sem cliente)
    $warm = ClientFeedback::create([
        'business_id' => 1, 'contact_id' => null,
        'literal' => 'pequeno problema sem dor',
        'severity_nng' => 2, 'recorrente_count' => 1,
        'persona_slug' => 'outro', 'modulo_afetado' => 'sells',
    ]);

    $gen = app(FeedbackIndexGenerator::class);
    $path = $gen->generateIndex();
    $content = file_get_contents($path);

    expect($content)->toContain(substr($hot->signature, 0, 8));
    expect($content)->not->toContain(substr($warm->signature, 0, 8));
});

it('R-WA-FBR-005 — INDEX é multi-tenant (separado por business)', function () {
    $c1 = Contact::create(['business_id' => 1, 'name' => 'C1', 'mobile' => '+1', 'type' => 'customer']);
    $c2 = Contact::create(['business_id' => 99, 'name' => 'C99', 'mobile' => '+99', 'type' => 'customer']);

    ClientFeedback::create([
        'business_id' => 1, 'contact_id' => $c1->id,
        'literal' => 'feedback business 1',
        'severity_nng' => 4, 'recorrente_count' => 5,
        'persona_slug' => 'kamila-martinho', 'modulo_afetado' => 'financeiro',
    ]);
    ClientFeedback::create([
        'business_id' => 99, 'contact_id' => $c2->id,
        'literal' => 'feedback business 99',
        'severity_nng' => 4, 'recorrente_count' => 5,
        'persona_slug' => 'tenant-adversario', 'modulo_afetado' => 'sells',
    ]);

    $gen = app(FeedbackIndexGenerator::class);
    $path = $gen->generateIndex();
    $content = file_get_contents($path);

    expect($content)->toContain('biz=1');
    expect($content)->toContain('biz=99');
});

it('R-WA-FBR-006 — generateArchive() agrupa COLD sem incluir literal PII', function () {
    $contact = Contact::create([
        'business_id' => 1, 'name' => 'C', 'mobile' => '+1', 'type' => 'customer',
    ]);

    // COLD: score baixo (sev=1)
    ClientFeedback::create([
        'business_id' => 1, 'contact_id' => $contact->id,
        'literal' => 'PII literal NÃO deve aparecer no archive',
        'severity_nng' => 1, 'recorrente_count' => 1,
        'persona_slug' => 'kamila-martinho', 'modulo_afetado' => 'fiscal',
    ]);

    $gen = app(FeedbackIndexGenerator::class);
    $path = $gen->generateArchive();
    $content = file_get_contents($path);

    expect(file_exists($path))->toBeTrue();
    expect($content)->toContain('COLD archive');
    // Sem literal PII (LGPD) — só persona + módulo agregados
    expect($content)->not->toContain('PII literal NÃO');
});

it('R-WA-FBR-007 — --skip-archive pula geração de archive', function () {
    $this->artisan('feedback:reindex --skip-archive')
        ->assertSuccessful()
        ->expectsOutputToContain('Archive skipped');
});

it('R-WA-FBR-008 — --business=X filtra apenas aquele tenant no rescore', function () {
    $c1 = Contact::create(['business_id' => 1, 'name' => 'C1', 'mobile' => '+1', 'type' => 'customer']);
    $c2 = Contact::create(['business_id' => 99, 'name' => 'Alien', 'mobile' => '+99', 'type' => 'customer']);

    $fb1 = ClientFeedback::create([
        'business_id' => 1, 'contact_id' => $c1->id,
        'literal' => 'biz 1', 'severity_nng' => 3,
    ]);
    $fb99 = ClientFeedback::create([
        'business_id' => 99, 'contact_id' => $c2->id,
        'literal' => 'biz 99', 'severity_nng' => 3,
    ]);

    $fb1->relevance_score = 5; $fb1->saveQuietly();
    $fb99->relevance_score = 5; $fb99->saveQuietly();

    $gen = app(FeedbackIndexGenerator::class);
    $stats = $gen->reindexScores(1);

    expect($stats['processed'])->toBe(1);

    $fb1->refresh();
    $fb99->refresh();

    expect((float) $fb1->relevance_score)->toBeGreaterThan(5);     // re-scored
    expect((float) $fb99->relevance_score)->toBe(5.0);             // intocado
});
