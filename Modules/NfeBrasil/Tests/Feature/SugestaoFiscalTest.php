<?php

declare(strict_types=1);

// @covers-us US-NFE-010 — sugestões da Jana na tributação (D-IA, playbook Fiscal thread 10).
// Contrato da tela: resources/js/Pages/NfeBrasil/Tributacao/Index.casos.md — UC-NFTR-10 · 12 · 13

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Modules\Jana\Ai\Agents\SugestaoFiscalAgent;
use Modules\NfeBrasil\Events\FiscalRuleCreated;
use Modules\NfeBrasil\Events\FiscalRuleDeleted;
use Modules\NfeBrasil\Events\FiscalRuleUpdated;
use Modules\NfeBrasil\Models\NfeFiscalRule;
use Modules\NfeBrasil\Services\MotorTributarioService;
use Modules\NfeBrasil\Services\Tributacao\ProdutoFiscalContext;

uses(Tests\TestCase::class);

/**
 * Os casos derivam do aceite da thread 10 (D-IA: "a IA sugere e nunca aplica") — não do service.
 * A Jana é o fake do laravel/ai: nenhuma chamada ao provedor. MySQL-only · tenant 98 e vizinho 99
 * (ADR 0358). NCMs 7709xxxx e SKUs SUG10-* reservados a este arquivo.
 */

function sugBiz(): int
{
    return test()->seededTenant()->id;
}

function sugBizOutro(): int
{
    return test()->seededSupportClientTenant()->id;
}

function sugLimpar(): void
{
    $bizs = [sugBiz(), sugBizOutro()];
    DB::statement('SET FOREIGN_KEY_CHECKS=0');
    $ids = DB::table('nfe_sugestoes_fiscais')->whereIn('business_id', $bizs)->pluck('id');
    DB::table('activity_log')->where('subject_type', \Modules\NfeBrasil\Models\NfeSugestaoFiscal::class)
        ->whereIn('subject_id', $ids)->delete();
    DB::table('nfe_sugestoes_fiscais')->whereIn('business_id', $bizs)->delete();
    DB::table('nfe_fiscal_rules')->whereIn('business_id', $bizs)->where('ncm', 'like', '7709%')->delete();
    DB::table('products')->whereIn('business_id', $bizs)->where('sku', 'like', 'SUG10-%')->delete();
    DB::statement('SET FOREIGN_KEY_CHECKS=1');
}

function sugProduto(int $biz, string $sku, string $ncm, int $userId): int
{
    $unit = (int) (DB::table('units')->where('business_id', $biz)->value('id')
        ?? DB::table('units')->insertGetId([
            'business_id' => $biz, 'actual_name' => 'Unidade sugestões', 'short_name' => 'un',
            'allow_decimal' => 0, 'created_by' => $userId, 'created_at' => Carbon::now(), 'updated_at' => Carbon::now(),
        ]));

    return (int) DB::table('products')->insertGetId([
        'business_id' => $biz, 'name' => 'Lona ' . $sku, 'type' => 'single', 'unit_id' => $unit, 'sku' => $sku,
        'ncm' => $ncm, 'enable_stock' => 0, 'alert_quantity' => 0, 'tax_type' => 'exclusive',
        'barcode_type' => 'C128', 'created_by' => $userId, 'created_at' => Carbon::now(), 'updated_at' => Carbon::now(),
    ]);
}

function sugRegra(int $biz): int
{
    return (int) DB::table('nfe_fiscal_rules')->insertGetId([
        'business_id' => $biz, 'ncm' => '77090001', 'uf_origem' => 'SP', 'uf_destino' => null,
        'cfop' => '5102', 'csosn' => '102', 'aliquota_icms' => 0.12, 'aliquota_pis' => 0,
        'aliquota_cofins' => 0, 'aliquota_ipi' => 0, 'created_at' => Carbon::now(), 'updated_at' => Carbon::now(),
    ]);
}

function sugLogar(array $permissoes, ?int $biz = null): \App\User
{
    $biz ??= sugBiz();
    $u = test()->usuarioComPermissoes($permissoes);
    test()->actingAs($u)->withSession(['business.id' => $biz, 'user.business_id' => $biz]);

    return $u;
}

function sugFoto(): array
{
    return [
        'produtos' => DB::table('products')->where('sku', 'like', 'SUG10-%')->orderBy('id')->get(['id', 'ncm'])->toArray(),
        'regras'   => DB::table('nfe_fiscal_rules')->where('ncm', 'like', '7709%')->orderBy('id')->get()->toArray(),
    ];
}

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('MySQL-only: lê products/nfe_fiscal_rules reais.');
    }
    if (! Schema::hasTable('nfe_sugestoes_fiscais')) {
        $this->markTestSkipped('Migração 2026_10_07_000004 não rodou — rode as migrations do módulo.');
    }
    Event::fake([FiscalRuleCreated::class, FiscalRuleUpdated::class, FiscalRuleDeleted::class]);
    NfeFiscalRule::esquecerVersionamento();
    sugLimpar();
});

afterEach(function () {
    if (DB::connection()->getDriverName() !== 'sqlite' && Schema::hasTable('nfe_sugestoes_fiscais')) {
        sugLimpar();
    }
});

// ---------------------------------------------------------------------------------------
// UC-NFTR-10 · Sugestão da Jana nunca altera regra nem produto sozinha  [T0] [fiscal]
// ---------------------------------------------------------------------------------------
it('UC-NFTR-10 · sugestão nunca aplica sozinha', function () {
    $semPermissao = test()->usuarioComPermissoes([]);
    $autor = sugLogar(['nfe.tributacao.manage']);
    $produto = sugProduto(sugBiz(), 'SUG10-A', '77090001', $autor->id);
    $alheio  = sugProduto(sugBizOutro(), 'SUG10-X', '77090001', $autor->id);
    $regra   = sugRegra(sugBiz());

    SugestaoFiscalAgent::fake([[
        'sugestoes' => [
            ['tipo' => 'ncm', 'alvo_id' => $produto, 'valor_sugerido' => '7709.00.02', 'confianca' => 0.9, 'risco' => 'baixo', 'motivo' => 'Descrição indica lona.'],
            ['tipo' => 'regra', 'alvo_id' => $regra, 'valor_sugerido' => '{"c_class_trib":"000001","cst_ibs":"000","cst_cbs":"000","aliquota_icms":0.99}', 'confianca' => 0.8, 'risco' => 'alto', 'motivo' => 'LC 214/2025: regra sem cClassTrib.'],
            // Lixo que a Jana pode devolver e NÃO pode virar sugestão:
            ['tipo' => 'ncm', 'alvo_id' => $alheio, 'valor_sugerido' => '77090009', 'confianca' => 0.9, 'risco' => 'baixo', 'motivo' => 'produto de outra empresa'],
            ['tipo' => 'ncm', 'alvo_id' => $produto, 'valor_sugerido' => '123', 'confianca' => 0.9, 'risco' => 'baixo', 'motivo' => 'NCM inválido'],
            ['tipo' => 'alterar_preco', 'alvo_id' => $produto, 'valor_sugerido' => '10', 'confianca' => 1, 'risco' => 'baixo', 'motivo' => 'tipo desconhecido'],
        ],
    ]]);

    $antes = sugFoto();
    $this->postJson('/nfe-brasil/tributacao/sugestoes/gerar')->assertOk()->assertJsonPath('criadas', 2);

    // Gerar não muda produto nem regra — só cria 2 pendentes (o lixo ficou de fora).
    expect(sugFoto())->toEqual($antes)
        ->and(DB::table('nfe_sugestoes_fiscais')->where('business_id', sugBiz())->where('status', 'pendente')->count())->toBe(2);

    $sNcm   = (int) DB::table('nfe_sugestoes_fiscais')->where('business_id', sugBiz())->where('tipo', 'ncm')->value('id');
    $sRegra = (int) DB::table('nfe_sugestoes_fiscais')->where('business_id', sugBiz())->where('tipo', 'regra')->value('id');
    // A alíquota que a Jana mandou junto foi descartada — a IA nunca sugere alíquota.
    expect(json_decode((string) DB::table('nfe_sugestoes_fiscais')->where('id', $sRegra)->value('valor_sugerido'), true))
        ->not->toHaveKey('aliquota_icms');

    // Sem nfe.tributacao.manage → 403, e nada muda.
    $this->actingAs($semPermissao)->postJson("/nfe-brasil/tributacao/sugestoes/{$sNcm}/aceitar")->assertForbidden();
    expect(sugFoto())->toEqual($antes);

    // Risco alto sem confirmar a leitura → 422, e nada muda.
    $this->actingAs($autor)->postJson("/nfe-brasil/tributacao/sugestoes/{$sRegra}/aceitar")->assertStatus(422);
    expect(sugFoto())->toEqual($antes);

    // Aceitar certo: a regra ganha versão nova com o cClassTrib; a antiga só ganha valida_ate.
    $this->postJson("/nfe-brasil/tributacao/sugestoes/{$sRegra}/aceitar", ['confirmou_leitura' => true])->assertOk();
    $vigente = DB::table('nfe_fiscal_rules')->where('business_id', sugBiz())->where('ncm', '77090001')
        ->whereNull('valida_ate')->orderByDesc('id')->first();
    $antiga = DB::table('nfe_fiscal_rules')->where('id', $regra)->first();
    expect($vigente->id)->not->toBe($regra)
        ->and($vigente->c_class_trib)->toBe('000001')
        ->and((float) $vigente->aliquota_icms)->toBe(0.12)   // alíquota intacta
        ->and($antiga->c_class_trib)->toBeNull()
        ->and($antiga->valida_ate)->not->toBeNull();

    // O motor segue calculando o mesmo ICMS (1.000 × 0,12 = 120,00): a sugestão mudou código, não valor.
    $t = (new MotorTributarioService())->calcular(new ProdutoFiscalContext(ncm: '77090001', valor: 1000.0), sugBiz(), 'SP', 'SP');
    expect($t->valor_icms)->toBe(120.0)->and($t->c_class_trib)->toBe('000001');

    expect(DB::table('activity_log')->where('subject_id', $sRegra)->where('description', 'sugestao.aceita')
        ->where('causer_id', $autor->id)->count())->toBe(1);

    // CONTROLE POSITIVO — descartar não muda o produto, mas também fica registrado.
    $this->postJson("/nfe-brasil/tributacao/sugestoes/{$sNcm}/descartar")->assertOk()->assertJsonPath('status', 'descartada');
    expect(DB::table('products')->where('id', $produto)->value('ncm'))->toBe('77090001')
        ->and(DB::table('activity_log')->where('subject_id', $sNcm)->where('description', 'sugestao.descartada')->count())->toBe(1);
});

// ---------------------------------------------------------------------------------------
// UC-NFTR-12 · Aceitar ou descartar sugestão de outra empresa é 404  [T0]
// ---------------------------------------------------------------------------------------
it('UC-NFTR-12 · sugestão isolada por tenant', function () {
    $autor = sugLogar(['nfe.tributacao.manage']);
    $produto = sugProduto(sugBiz(), 'SUG10-B', '77090001', $autor->id);
    $s = (int) DB::table('nfe_sugestoes_fiscais')->insertGetId([
        'business_id' => sugBiz(), 'tipo' => 'ncm', 'alvo_tipo' => 'produto', 'alvo_id' => $produto,
        'valor_sugerido' => json_encode(['ncm' => '77090002']), 'confianca' => 0.9, 'risco' => 'baixo',
        'motivo' => 'teste', 'status' => 'pendente', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now(),
    ]);

    // Outra empresa: 404 nas duas ações, e nada muda.
    sugLogar(['nfe.tributacao.manage'], sugBizOutro());
    $this->postJson("/nfe-brasil/tributacao/sugestoes/{$s}/aceitar")->assertNotFound();
    $this->postJson("/nfe-brasil/tributacao/sugestoes/{$s}/descartar")->assertNotFound();
    expect(DB::table('nfe_sugestoes_fiscais')->where('id', $s)->value('status'))->toBe('pendente')
        ->and(DB::table('products')->where('id', $produto)->value('ncm'))->toBe('77090001');

    // CONTROLE POSITIVO — a própria empresa aceita, e o NCM do produto muda.
    sugLogar(['nfe.tributacao.manage']);
    $this->postJson("/nfe-brasil/tributacao/sugestoes/{$s}/aceitar")->assertOk()->assertJsonPath('status', 'aceita');
    expect(DB::table('products')->where('id', $produto)->value('ncm'))->toBe('77090002');
});

// ---------------------------------------------------------------------------------------
// UC-NFTR-13 · Jana fora do ar não afeta emissão nem cadastro  [fiscal]
// ---------------------------------------------------------------------------------------
it('UC-NFTR-13 · IA indisponível não bloqueia fiscal', function () {
    $autor = sugLogar(['nfe.tributacao.manage']);
    $produto = sugProduto(sugBiz(), 'SUG10-C', '77090001', $autor->id);

    SugestaoFiscalAgent::fake(fn () => throw new RuntimeException('timeout do provedor'));

    $this->postJson('/nfe-brasil/tributacao/sugestoes/gerar')->assertOk()
        ->assertJsonPath('indisponivel', true)
        ->assertJsonPath('mensagem', 'Sugestões indisponíveis agora.');
    expect(DB::table('nfe_sugestoes_fiscais')->where('business_id', sugBiz())->count())->toBe(0);

    // Com a Jana fora: salvar regra e calcular imposto funcionam igual.
    $this->post('/nfe-brasil/tributacao/regras', [
        'ncm' => '77090001', 'uf_origem' => 'SP', 'uf_destino' => null, 'cfop' => '5102', 'csosn' => '102',
        'aliquota_icms' => 0.12, 'aliquota_pis' => 0, 'aliquota_cofins' => 0, 'aliquota_ipi' => 0,
    ])->assertSessionHasNoErrors()->assertRedirect();
    $t = (new MotorTributarioService())->calcular(new ProdutoFiscalContext(ncm: '77090001', valor: 1000.0), sugBiz(), 'SP', 'SP');
    expect($t->valor_icms)->toBe(120.0); // 1.000 × 0,12

    // CONTROLE POSITIVO — com a Jana de volta, as sugestões reaparecem.
    SugestaoFiscalAgent::fake([[
        'sugestoes' => [['tipo' => 'ncm', 'alvo_id' => $produto, 'valor_sugerido' => '77090003', 'confianca' => 0.7, 'risco' => 'baixo', 'motivo' => 'ok']],
    ]]);
    $this->postJson('/nfe-brasil/tributacao/sugestoes/gerar')->assertOk()
        ->assertJsonPath('indisponivel', false)->assertJsonPath('criadas', 1);
});
