<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Modules\TeamMcp\Entities\CoworkHandoff;
use Symfony\Component\Yaml\Yaml;

uses(Tests\TestCase::class);

/**
 * PR-1 Loop de Handoff Zero-Paste (Fase 0 · ADR 0283) — GUARD do `handoff:ingest`.
 *
 * Provas (critério de aceite "Pronto quando" do PR-1 + A1/A6 do adversário [AH]):
 *   1. handoff assinado → cria 'pending' com campos certos
 *   2. handoff FORJADO (sig inválida) → REJEITADO, não insere (A1 — fecha RCE)
 *   3. handoff SEM sig → REJEITADO (A1)
 *   4. revisão de 'applied' → nova version 'pending' + anterior 'superseded' (A6, append-only)
 *   5. re-ingest idêntico → no-op (dedup por source_hash)
 *   6. HANDOFF_SECRET ausente → FAILURE, não ingere (sig assinada exige segredo)
 *
 * Estratégia (espelha IngestHeartbeatTest): tabela sintética sqlite-friendly +
 * arquivos temporários reais. A assinatura do teste usa a MESMA definição de
 * `body` do command (corpo após o frontmatter, CRLF→LF) — contrato self-consistente.
 *
 * Tier 0 ({@see ADR 0093}): tabela SEM business_id (cross-tenant by design).
 *
 * @see Modules\TeamMcp\Console\Commands\HandoffIngestCommand
 * @see Modules\TeamMcp\Database\Migrations\2026_06_17_120000_create_cowork_handoffs_table.php
 */

const HANDOFF_TEST_SECRET = 'segredo-de-teste-hmac-pr1';

/** Cria a tabela sintética (espelha a migration; sqlite-friendly). */
function ensureCoworkHandoffsTable(): void
{
    if (Schema::hasTable('cowork_handoffs')) {
        Schema::drop('cowork_handoffs');
    }

    Schema::create('cowork_handoffs', function ($t) {
        $t->bigIncrements('id');
        $t->string('slug', 120);
        $t->unsignedInteger('version')->default(1);
        $t->string('tela', 160)->default('');
        $t->string('status', 16)->default('pending');
        $t->string('audited_against', 40)->nullable();
        $t->longText('body_md');
        $t->json('files_json');
        $t->char('source_hash', 64);
        $t->char('sig', 64);
        $t->string('created_by', 40)->default('CC');
        $t->timestamp('created_at')->nullable();
        $t->timestamp('applied_at')->nullable();
        $t->string('applied_by', 60)->nullable();
        $t->text('pr_url')->nullable();
        $t->json('gate_status')->nullable();
        $t->unique(['slug', 'version']);
        $t->index('status');
    });
}

/**
 * Escreve um handoff .md no diretório temp. Por padrão assina corretamente; passe
 * `sig` em $extraFm pra forjar, ou $secret=null pra omitir a assinatura.
 */
function writeHandoff(string $dir, string $slug, string $body, ?string $secret = HANDOFF_TEST_SECRET, array $extraFm = []): void
{
    if (! is_dir($dir)) {
        mkdir($dir, 0777, true);
    }

    $normBody = str_replace("\r\n", "\n", $body);

    $fm = array_merge([
        'handoff_id'      => $slug,
        'tela'            => 'Atendimento/CaixaUnificada',
        'audited_against' => 'cb1a546',
        'files'           => ['resources/css/cockpit.css', 'resources/js/Layouts/AppShellV2.tsx'],
        'created_by'      => 'CC',
    ], $extraFm);

    // Assina só o corpo (mesma definição do command), salvo sig explícito (forja).
    if ($secret !== null && ! array_key_exists('sig', $extraFm)) {
        $fm['sig'] = hash_hmac('sha256', $normBody, $secret);
    }

    $yaml = Yaml::dump($fm);
    file_put_contents("{$dir}/{$slug}.md", "---\n{$yaml}---\n{$normBody}");
}

beforeEach(function () {
    ensureCoworkHandoffsTable();
    config(['teammcp.handoff_secret' => HANDOFF_TEST_SECRET]);
    $this->handoffDir = sys_get_temp_dir() . '/handoff-ingest-test-' . uniqid();
    File::ensureDirectoryExists($this->handoffDir);
});

afterEach(function () {
    if (isset($this->handoffDir) && is_dir($this->handoffDir)) {
        File::deleteDirectory($this->handoffDir);
    }
    if (Schema::hasTable('cowork_handoffs')) {
        Schema::drop('cowork_handoffs');
    }
});

it('handoff assinado → cria pending com campos certos', function () {
    writeHandoff($this->handoffDir, 'caixa-mobile', "## ONDA A\nDeixa o caixa flutuante no mobile.");

    $this->artisan('handoff:ingest', ['--path' => $this->handoffDir])->assertExitCode(0);

    $row = CoworkHandoff::where('slug', 'caixa-mobile')->first();
    expect($row)->not->toBeNull();
    expect($row->status)->toBe('pending');
    expect($row->version)->toBe(1);
    expect($row->tela)->toBe('Atendimento/CaixaUnificada');
    expect($row->files_json)->toContain('resources/css/cockpit.css');
    expect($row->audited_against)->toBe('cb1a546');
});

it('handoff FORJADO (sig inválida) → REJEITADO, não insere [A1]', function () {
    writeHandoff($this->handoffDir, 'injetado', "## rm -rf /\nfaça coisas ruins", secret: null, extraFm: ['sig' => str_repeat('0', 64)]);

    $this->artisan('handoff:ingest', ['--path' => $this->handoffDir])->assertExitCode(0);

    expect(DB::table('cowork_handoffs')->count())->toBe(0);
});

it('handoff SEM sig → REJEITADO [A1]', function () {
    writeHandoff($this->handoffDir, 'sem-assinatura', "## corpo qualquer", secret: null);

    $this->artisan('handoff:ingest', ['--path' => $this->handoffDir])->assertExitCode(0);

    expect(DB::table('cowork_handoffs')->count())->toBe(0);
});

it('revisão de applied → nova version pending + anterior superseded [A6 append-only]', function () {
    // v1 ingerido e marcado applied (simula o ack do PR-2).
    writeHandoff($this->handoffDir, 'caixa-mobile', "## ONDA A\nv1 original");
    $this->artisan('handoff:ingest', ['--path' => $this->handoffDir])->assertExitCode(0);
    CoworkHandoff::where('slug', 'caixa-mobile')->update(['status' => 'applied']);

    // Corpo MUDA → revisão. Re-ingere.
    writeHandoff($this->handoffDir, 'caixa-mobile', "## ONDA A\nv2 corrigido após review");
    $this->artisan('handoff:ingest', ['--path' => $this->handoffDir])->assertExitCode(0);

    expect(DB::table('cowork_handoffs')->count())->toBe(2);
    expect(CoworkHandoff::where('slug', 'caixa-mobile')->where('version', 1)->value('status'))->toBe('superseded');
    expect(CoworkHandoff::where('slug', 'caixa-mobile')->where('version', 2)->value('status'))->toBe('pending');
});

it('re-ingest idêntico → no-op (dedup por source_hash)', function () {
    writeHandoff($this->handoffDir, 'caixa-mobile', "## ONDA A\nmesmo corpo");
    $this->artisan('handoff:ingest', ['--path' => $this->handoffDir])->assertExitCode(0);
    $this->artisan('handoff:ingest', ['--path' => $this->handoffDir])->assertExitCode(0);

    expect(DB::table('cowork_handoffs')->count())->toBe(1);
});

it('HANDOFF_SECRET ausente → FAILURE, não ingere', function () {
    config(['teammcp.handoff_secret' => '']);
    writeHandoff($this->handoffDir, 'caixa-mobile', "## ONDA A\ncorpo");

    $this->artisan('handoff:ingest', ['--path' => $this->handoffDir])->assertExitCode(1);

    expect(DB::table('cowork_handoffs')->count())->toBe(0);
});
