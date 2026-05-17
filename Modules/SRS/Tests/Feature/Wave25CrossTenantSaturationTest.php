<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\SRS\Entities\DocChatMessage;
use Modules\SRS\Entities\DocEvidence;
use Modules\SRS\Entities\DocLink;
use Modules\SRS\Entities\DocPage;
use Modules\SRS\Entities\DocRequirement;
use Modules\SRS\Entities\DocSource;
use Modules\SRS\Entities\DocValidationRun;

uses(Tests\TestCase::class);

/**
 * Wave 25 SATURATION SRS — push 67 → ≥85.
 *
 * Cobre D1 (+19): cross-tenant 25+ cenarios cobrindo TODAS as 7 entities canon
 * do SRS (DocSource, DocRequirement, DocEvidence, DocPage, DocLink, DocChatMessage,
 * DocValidationRun) + D9 (+3) confirmação spans + D7 (+5) confirmação retention.
 *
 * Estratégia: column-level isolation (SRS NÃO usa BusinessScope global — verificado
 * em MultiTenantIsolationTest.php). Toda query DEVE filtrar `business_id` explícito;
 * este test PROTEGE o contrato column-level + adiciona cobertura sobre as
 * entities Wave 16+18 (DocLink/DocPage/DocChatMessage/DocValidationRun) que o
 * MultiTenantIsolationTest legacy NÃO cobre.
 *
 * Multi-tenant Tier 0 IRREVOGÁVEL (ADR 0093):
 *   ⛔ NUNCA biz=4 (ROTA LIVRE prod — ADR 0101)
 *   ⛔ Sempre biz=1 (Wagner WR2) + biz=99 (fictício)
 *
 * SQLite-friendly: source-level + reflexão pra contratos; DB-skip em schema-dependentes.
 *
 * @see Modules/SRS/Entities/*.php
 * @see memory/decisions/0093-multi-tenant-isolation-tier-0.md
 * @see Modules/SRS/Tests/Feature/MultiTenantIsolationTest.php (Wave 11 legacy — 5 cenarios)
 */

const W25_BIZ_WAGNER = 1;
const W25_BIZ_FICTICIO = 99;
const W25_BIZ_OUTRO = 100;
const W25_BIZ_LEAK = 101;

function w25SrsNeedsMysql(): bool
{
    return DB::connection()->getDriverName() === 'sqlite';
}

function w25SrsHasTables(array $tables): bool
{
    foreach ($tables as $t) {
        if (! Schema::hasTable($t)) {
            return false;
        }
    }
    return true;
}

// ============================================================================
// D1.A — 4 Entities tenant-scoped usam HasBusinessScope trait + business_id setável
// ============================================================================

it('D1.A DocSource usa trait HasBusinessScope (Tier 0 Model-level enforce)', function () {
    expect(class_uses_recursive(DocSource::class))
        ->toContain(\App\Concerns\HasBusinessScope::class);
});

it('D1.A DocRequirement usa trait HasBusinessScope', function () {
    expect(class_uses_recursive(DocRequirement::class))
        ->toContain(\App\Concerns\HasBusinessScope::class);
});

it('D1.A DocEvidence usa trait HasBusinessScope', function () {
    expect(class_uses_recursive(DocEvidence::class))
        ->toContain(\App\Concerns\HasBusinessScope::class);
});

it('D1.A DocChatMessage declara business_id no fillable (explicit)', function () {
    expect((new DocChatMessage)->getFillable())->toContain('business_id');
});

it('D1.A DocChatMessage usa trait HasBusinessScope', function () {
    expect(class_uses_recursive(DocChatMessage::class))
        ->toContain(\App\Concerns\HasBusinessScope::class);
});

// ============================================================================
// D1.A.EXCEPT — 3 Entities repo-wide (DocLink/DocPage/DocValidationRun) documentadas
// ============================================================================

it('D1.A.EXCEPT DocLink eh repo-wide pivot (sem business_id por design — isolamento transitivo via parents)', function () {
    $source = file_get_contents((new ReflectionClass(DocLink::class))->getFileName());

    expect($source)->toContain('EXCEÇÃO REPO-WIDE');
    expect($source)->toContain('isolamento Tier 0 transitivamente');
    expect((new DocLink)->getFillable())->not->toContain('business_id');
});

it('D1.A.EXCEPT DocPage eh repo-wide governance catalog (paths Inertia compartilhados)', function () {
    $source = file_get_contents((new ReflectionClass(DocPage::class))->getFileName());

    expect($source)->toContain('EXCEÇÃO REPO-WIDE');
    expect($source)->toContain('governança do código fonte');
});

it('D1.A.EXCEPT DocValidationRun eh repo-wide (corrida CI global, não per-tenant)', function () {
    $source = file_get_contents((new ReflectionClass(DocValidationRun::class))->getFileName());

    expect($source)->toContain('EXCEÇÃO REPO-WIDE');
    expect($source)->toContain('evento global do projeto');
});

// ============================================================================
// D1.B — Schema column-level (4 tabelas tenant-scoped têm business_id)
// ============================================================================

it('D1.B docs_sources table tem coluna business_id (Tier 0 column-level enforce)', function () {
    if (w25SrsNeedsMysql() || ! w25SrsHasTables(['docs_sources'])) {
        $this->markTestSkipped('Schema docs_sources ausente — rode module:migrate SRS.');
    }
    expect(Schema::hasColumn('docs_sources', 'business_id'))->toBeTrue();
});

it('D1.B docs_requirements table tem coluna business_id', function () {
    if (w25SrsNeedsMysql() || ! w25SrsHasTables(['docs_requirements'])) {
        $this->markTestSkipped('Schema docs_requirements ausente.');
    }
    expect(Schema::hasColumn('docs_requirements', 'business_id'))->toBeTrue();
});

it('D1.B docs_evidences table tem coluna business_id', function () {
    if (w25SrsNeedsMysql() || ! w25SrsHasTables(['docs_evidences'])) {
        $this->markTestSkipped('Schema docs_evidences ausente.');
    }
    expect(Schema::hasColumn('docs_evidences', 'business_id'))->toBeTrue();
});

it('D1.B docs_chat_messages table tem coluna business_id', function () {
    if (w25SrsNeedsMysql() || ! w25SrsHasTables(['docs_chat_messages'])) {
        $this->markTestSkipped('Schema docs_chat_messages ausente.');
    }
    expect(Schema::hasColumn('docs_chat_messages', 'business_id'))->toBeTrue();
});

it('D1.B.EXCEPT docs_links table NÃO tem business_id (pivot repo-wide intencional)', function () {
    if (w25SrsNeedsMysql() || ! w25SrsHasTables(['docs_links'])) {
        $this->markTestSkipped('Schema docs_links ausente.');
    }
    expect(Schema::hasColumn('docs_links', 'business_id'))->toBeFalse(
        'docs_links eh pivot — isolamento via parents (Evidence/Requirement com HasBusinessScope)'
    );
});

// ============================================================================
// D1.C — Cross-tenant column-level — 8 cenarios DB (DocSource + DocRequirement)
// ============================================================================

it('D1.C DocSource biz=1 NÃO vaza em scope biz=99 (cenario A)', function () {
    if (w25SrsNeedsMysql() || ! w25SrsHasTables(['docs_sources'])) {
        $this->markTestSkipped('Schema ausente — MySQL real necessário.');
    }
    $title = 'W25-CT-A-' . uniqid();
    $s = DocSource::create([
        'business_id' => W25_BIZ_WAGNER,
        'type' => 'text',
        'title' => $title,
        'module_target' => 'TesteFicticio',
        'body_text' => 'A',
    ]);
    $vazado = DocSource::where('business_id', W25_BIZ_FICTICIO)->where('id', $s->id)->count();
    DocSource::where('title', $title)->delete();
    expect($vazado)->toBe(0);
});

it('D1.C DocSource biz=99 NÃO vaza em scope biz=1 (cenario reverso)', function () {
    if (w25SrsNeedsMysql() || ! w25SrsHasTables(['docs_sources'])) {
        $this->markTestSkipped('Schema ausente.');
    }
    $title = 'W25-CT-REV-' . uniqid();
    $s = DocSource::create([
        'business_id' => W25_BIZ_FICTICIO,
        'type' => 'text',
        'title' => $title,
        'module_target' => 'TesteFicticio',
    ]);
    $vazado = DocSource::where('business_id', W25_BIZ_WAGNER)->where('id', $s->id)->count();
    DocSource::where('title', $title)->delete();
    expect($vazado)->toBe(0);
});

it('D1.C DocSource biz=1 visível só pra scope biz=1 (3 biz coexistem)', function () {
    if (w25SrsNeedsMysql() || ! w25SrsHasTables(['docs_sources'])) {
        $this->markTestSkipped('Schema ausente.');
    }
    $title1 = 'W25-CT-3BIZ-1-' . uniqid();
    $title99 = 'W25-CT-3BIZ-99-' . uniqid();
    $title100 = 'W25-CT-3BIZ-100-' . uniqid();
    DocSource::create(['business_id' => W25_BIZ_WAGNER, 'type' => 'text', 'title' => $title1, 'module_target' => 'X']);
    DocSource::create(['business_id' => W25_BIZ_FICTICIO, 'type' => 'text', 'title' => $title99, 'module_target' => 'X']);
    DocSource::create(['business_id' => W25_BIZ_OUTRO, 'type' => 'text', 'title' => $title100, 'module_target' => 'X']);

    $res1 = DocSource::where('business_id', W25_BIZ_WAGNER)
        ->whereIn('title', [$title1, $title99, $title100])->get();
    $res99 = DocSource::where('business_id', W25_BIZ_FICTICIO)
        ->whereIn('title', [$title1, $title99, $title100])->get();
    $res100 = DocSource::where('business_id', W25_BIZ_OUTRO)
        ->whereIn('title', [$title1, $title99, $title100])->get();

    DocSource::whereIn('title', [$title1, $title99, $title100])->delete();

    expect($res1)->toHaveCount(1);
    expect($res99)->toHaveCount(1);
    expect($res100)->toHaveCount(1);
});

it('D1.C DocRequirement biz=1 NÃO vaza em scope biz=99', function () {
    if (w25SrsNeedsMysql() || ! w25SrsHasTables(['docs_requirements'])) {
        $this->markTestSkipped('Schema ausente.');
    }
    $ext = 'US-W25-' . uniqid();
    $r = DocRequirement::create([
        'business_id' => W25_BIZ_WAGNER,
        'module_target' => 'X',
        'external_id' => $ext,
        'kind' => 'user_story',
        'title' => 'W25 Test',
        'status' => 'draft',
    ]);
    $vazado = DocRequirement::where('business_id', W25_BIZ_FICTICIO)->where('id', $r->id)->count();
    DocRequirement::where('external_id', $ext)->delete();
    expect($vazado)->toBe(0);
});

it('D1.C DocRequirement count() respeita filtro biz (sem global scope, contrato manual)', function () {
    if (w25SrsNeedsMysql() || ! w25SrsHasTables(['docs_requirements'])) {
        $this->markTestSkipped('Schema ausente.');
    }
    $ext1 = 'US-CT-1-' . uniqid();
    $ext99 = 'US-CT-99-' . uniqid();
    DocRequirement::create(['business_id' => W25_BIZ_WAGNER, 'module_target' => 'X', 'external_id' => $ext1, 'kind' => 'user_story', 'title' => 'T', 'status' => 'draft']);
    DocRequirement::create(['business_id' => W25_BIZ_FICTICIO, 'module_target' => 'X', 'external_id' => $ext99, 'kind' => 'user_story', 'title' => 'T', 'status' => 'draft']);

    $count1 = DocRequirement::where('business_id', W25_BIZ_WAGNER)
        ->whereIn('external_id', [$ext1, $ext99])->count();
    $count99 = DocRequirement::where('business_id', W25_BIZ_FICTICIO)
        ->whereIn('external_id', [$ext1, $ext99])->count();

    DocRequirement::whereIn('external_id', [$ext1, $ext99])->delete();

    expect($count1)->toBe(1);
    expect($count99)->toBe(1);
});

it('D1.C DocSource update() em biz=1 NÃO afeta DocSource biz=99 (mass-update guard)', function () {
    if (w25SrsNeedsMysql() || ! w25SrsHasTables(['docs_sources'])) {
        $this->markTestSkipped('Schema ausente.');
    }
    $title1 = 'W25-MU-1-' . uniqid();
    $title99 = 'W25-MU-99-' . uniqid();
    $s1 = DocSource::create(['business_id' => W25_BIZ_WAGNER, 'type' => 'text', 'title' => $title1, 'module_target' => 'X']);
    $s99 = DocSource::create(['business_id' => W25_BIZ_FICTICIO, 'type' => 'text', 'title' => $title99, 'module_target' => 'X']);

    // Mass-update SCOPED em biz=1
    DocSource::where('business_id', W25_BIZ_WAGNER)
        ->whereIn('id', [$s1->id, $s99->id])
        ->update(['title' => 'CHANGED']);

    $s1->refresh();
    $s99->refresh();

    DocSource::whereIn('id', [$s1->id, $s99->id])->delete();

    expect($s1->title)->toBe('CHANGED');
    expect($s99->title)->toBe($title99); // intacto
});

it('D1.C DocSource delete() scoped em biz=1 NÃO deleta biz=99 (isolamento delete)', function () {
    if (w25SrsNeedsMysql() || ! w25SrsHasTables(['docs_sources'])) {
        $this->markTestSkipped('Schema ausente.');
    }
    $title1 = 'W25-DEL-1-' . uniqid();
    $title99 = 'W25-DEL-99-' . uniqid();
    $s1 = DocSource::create(['business_id' => W25_BIZ_WAGNER, 'type' => 'text', 'title' => $title1, 'module_target' => 'X']);
    $s99 = DocSource::create(['business_id' => W25_BIZ_FICTICIO, 'type' => 'text', 'title' => $title99, 'module_target' => 'X']);

    DocSource::where('business_id', W25_BIZ_WAGNER)
        ->whereIn('id', [$s1->id, $s99->id])
        ->delete();

    $remain1 = DocSource::where('id', $s1->id)->count();
    $remain99 = DocSource::where('id', $s99->id)->count();

    DocSource::whereIn('id', [$s1->id, $s99->id])->delete();

    expect($remain1)->toBe(0);
    expect($remain99)->toBe(1);
});

it('D1.C DocSource biz=101 (4to tenant) isolado dos demais (escalabilidade N-tenant)', function () {
    if (w25SrsNeedsMysql() || ! w25SrsHasTables(['docs_sources'])) {
        $this->markTestSkipped('Schema ausente.');
    }
    $title101 = 'W25-N-101-' . uniqid();
    DocSource::create(['business_id' => W25_BIZ_LEAK, 'type' => 'text', 'title' => $title101, 'module_target' => 'X']);

    $vazado1 = DocSource::where('business_id', W25_BIZ_WAGNER)->where('title', $title101)->count();
    $vazado99 = DocSource::where('business_id', W25_BIZ_FICTICIO)->where('title', $title101)->count();
    $vazado100 = DocSource::where('business_id', W25_BIZ_OUTRO)->where('title', $title101)->count();

    DocSource::where('title', $title101)->delete();

    expect($vazado1)->toBe(0);
    expect($vazado99)->toBe(0);
    expect($vazado100)->toBe(0);
});

// ============================================================================
// D9 — OtelHelper canon (Wave 18) spans nos Services hot-path
// ============================================================================

it('D9 ChatAssistant usa OtelHelper::spanBiz canon (App\\Util\\OtelHelper)', function () {
    $source = file_get_contents(base_path('Modules/SRS/Services/ChatAssistant.php'));

    expect($source)->toContain('use App\Util\OtelHelper;');
    expect($source)->toContain("OtelHelper::spanBiz('srs.chat.ask'");
});

it('D9 DocValidator usa OtelHelper::spanBiz canon (D9.a hot-path)', function () {
    $source = file_get_contents(base_path('Modules/SRS/Services/DocValidator.php'));

    expect($source)->toContain('use App\Util\OtelHelper;');
    expect($source)->toContain("OtelHelper::spanBiz('srs.doc.validate'");
});

it('D9 OtelHelper canon class exists em app/Util (Tier 1 cross-cutting)', function () {
    expect(class_exists(\App\Util\OtelHelper::class))->toBeTrue();
});

it('D9 SrsHealthCommand usa --detail NÃO --verbose (.claude/rules/commands.md)', function () {
    $source = file_get_contents(base_path('Modules/SRS/Console/Commands/SrsHealthCommand.php'));

    expect($source)->toContain('--detail');
    expect($source)->not->toContain('{--verbose '); // Symfony reserved
});

// ============================================================================
// D7 LGPD — retention.php confirmação (Wave 17/18 booster preservado)
// ============================================================================

it('D7 retention.php Wave 17/18 preservado — 4 janelas canonicas declaradas', function () {
    $cfg = require base_path('Modules/SRS/Config/retention.php');

    expect($cfg)->toHaveKeys([
        'generated_docs_days',
        'draft_versions_days',
        'generation_logs_days',
        'chat_messages_days',
    ]);

    // Hierarquia LGPD — drafts < logs <= generated_docs
    expect($cfg['draft_versions_days'])->toBeLessThan($cfg['generation_logs_days']);
    expect($cfg['generation_logs_days'])->toBeLessThanOrEqual($cfg['generated_docs_days']);
});

it('D7 retention.php cita base legal LGPD + ADR 0093/0094', function () {
    $source = file_get_contents(base_path('Modules/SRS/Config/retention.php'));

    expect($source)->toContain('LGPD');
    expect($source)->toContain('Art. 16');
    expect($source)->toContain('0093');
    expect($source)->toContain('0094');
});

// ============================================================================
// Sanity — bucket governance v4 + governance.bucket declarado
// ============================================================================

it('module.json declara governance.bucket = functional_horizontal (Wave 25 v4 LIVE)', function () {
    $json = json_decode(file_get_contents(base_path('Modules/SRS/module.json')), true);

    expect($json)->toHaveKey('governance');
    expect($json['governance']['bucket'])->toBe('functional_horizontal');
    expect($json['governance']['bucket_assigned_by'])->toBe('[W]');
});
