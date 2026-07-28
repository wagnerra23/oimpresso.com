<?php

declare(strict_types=1);

// @covers-us US-NFE-010 / US-NFE-TPL-001 — tela de tributação (regras NCM Níveis 2/3, templates L1
// e o gate per-business de emissão automática).
// Contrato da tela: resources/js/Pages/NfeBrasil/Tributacao/Index.casos.md — UC-NFTR-01..05.
// (UC-NFTR-06, ordenação, vive em TributacaoControllerTest — ver §UC-NFTR-06 do casos.md.)
// Os casos derivam do CONTRATO (ARQ-0006 + US-NFE-010 + charter `live`), não da implementação —
// teste derivado do código é tautológico (proibicoes.md §5 2026-06-05).

use App\User;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\NfeBrasil\Jobs\EmitirNFSeJob;
use Modules\NfeBrasil\Jobs\EmitirNfceJob;
use Spatie\Permission\Models\Permission;

uses(Tests\TestCase::class);

/**
 * US-NFE-010 fase 2 · /nfe-brasil/tributacao.
 *
 * POR QUE É HTTP (e o irmão TributacaoControllerTest não precisa ser)
 * ------------------------------------------------------------------
 * O `TributacaoControllerTest` instancia o controller na mão — ótimo pra forma do payload
 * (ordenação, shape), mas SEM usuário autenticado o global scope `ScopeByBusiness` faz
 * early-return em `! auth()->check()` e NO-OPA. Ou seja: lá o "guard de isolamento" prova
 * só o `where` manual do controller. É exatamente a causa que derrubou
 * NfeBrasilMultiTenantIsolationTest e Wave25NfeSaturationTest em 2026-06-24 (documentada na
 * allowlist de nfebrasil-pest.yml). Aqui usamos `actingAs`, então as DUAS camadas valem —
 * e todo caso de isolamento carrega controle positivo, pra o verde não vir de lista vazia
 * por ausência de dado (proibicoes.md §5 2026-07-24: verde por não-execução).
 *
 * POR QUE MYSQL-ONLY
 * ------------------
 * Isolamento multi-tenant só vale contra o schema real; no lane sqlite (:memory:) o schema é
 * recriado à mão e o verde MENTE — razão declarada da lane nfebrasil-pest.yml.
 *
 * biz=1 (Wagner) e biz=2 (cross-tenant) são os semeados por `pest-mysql-setup`.
 * NUNCA biz=4 — é ROTA LIVRE / Larissa, cliente real em produção (ADR 0101).
 *
 * @see memory/requisitos/NfeBrasil/adr/arq/0006-cascade-defaults-ncm-produto.md
 * @see memory/decisions/0093-multi-tenant-isolation-tier-0.md
 * @see resources/js/Pages/NfeBrasil/Tributacao/Index.charter.md (status: live, aprovado [W] 2026-05-10)
 */

const NFTR_BIZ = 1;
const NFTR_BIZ_OUTRO = 2;
const NFTR_TOGGLE_URL = '/nfe-brasil/tributacao/auto-emission/toggle';

/** Template L1 de `Modules/NfeBrasil/Resources/templates/` — regime `simples`. */
const NFTR_TEMPLATE = 'comercio-varejo-simples-sp';

function nftrRegra(int $businessId, string $ncm, ?string $ufDestino = null, string $cfop = '5102'): int
{
    return (int) DB::table('nfe_fiscal_rules')->insertGetId([
        'business_id'     => $businessId,
        'ncm'             => $ncm,
        'uf_origem'       => 'SP',
        'uf_destino'      => $ufDestino,
        'cfop'            => $cfop,
        'csosn'           => '102',
        'aliquota_icms'   => 0.18,
        'aliquota_pis'    => 0.0065,
        'aliquota_cofins' => 0.03,
        'aliquota_ipi'    => 0,
        'created_at'      => now(),
        'updated_at'      => now(),
    ]);
}

function nftrConfig(int $businessId, string $regime = 'simples', bool $auto = false): void
{
    DB::table('nfe_business_configs')->insert([
        'business_id'           => $businessId,
        'regime'                => $regime,
        'auto_emission_enabled' => $auto,
        'tributacao_default'    => json_encode(['cfop' => '5102', 'csosn' => '102', 'aliquota_icms' => 0.18]),
        'created_at'            => now(),
        'updated_at'            => now(),
    ]);
}

function nftrLimpar(): void
{
    $bizs = [NFTR_BIZ, NFTR_BIZ_OUTRO];
    DB::statement('SET FOREIGN_KEY_CHECKS=0');
    if (Schema::hasTable('nfe_fiscal_rule_tax_rate_links')) {
        DB::table('nfe_fiscal_rule_tax_rate_links')->whereIn('business_id', $bizs)->delete();
    }
    DB::table('nfe_fiscal_rules')->whereIn('business_id', $bizs)->delete();
    DB::statement('SET FOREIGN_KEY_CHECKS=1');
    DB::table('nfe_business_configs')->whereIn('business_id', $bizs)->delete();
}

/** Spatie cacheia o mapa de permissões — sem invalidar, o grant/revoke do teste não vale. */
function nftrEsquecerCache(): void
{
    app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
}

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('MySQL-only: isolamento multi-tenant exige schema real (ADR 0101; ver nfebrasil-pest.yml).');
    }
    if (! Schema::hasTable('nfe_fiscal_rules') || ! Schema::hasTable('nfe_business_configs')) {
        $this->markTestSkipped('Tabelas do NfeBrasil ausentes — rode as migrations do módulo.');
    }

    nftrLimpar();

    // `UpsertRegraTributariaRequest::authorize()` exige `nfe.tributacao.manage`. Concedido direto
    // ao usuário semeado do biz=1 (sem criar role: `roles.business_id` é NOT NULL com FK em
    // UltimatePOS — proibicoes.md §FSM). Revogado no afterEach.
    $perm = Permission::firstOrCreate(['name' => 'nfe.tributacao.manage', 'guard_name' => 'web']);
    $user = User::where('business_id', NFTR_BIZ)->firstOrFail();
    $user->givePermissionTo($perm);
    nftrEsquecerCache();

    $this->actingAs($user);
});

afterEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite'
        || ! Schema::hasTable('nfe_fiscal_rules')
        || ! Schema::hasTable('nfe_business_configs')) {
        return;
    }
    nftrLimpar();

    // Re-resolve em vez de guardar em `$this->…`: propriedade dinâmica em TestCase é deprecada
    // no PHP 8.2+.
    $perm = Permission::where('name', 'nfe.tributacao.manage')->where('guard_name', 'web')->first();
    $user = User::where('business_id', NFTR_BIZ)->first();
    if ($perm && $user) {
        $user->revokePermissionTo($perm);
        nftrEsquecerCache();
    }
});

// ---------------------------------------------------------------------------------------
// UC-NFTR-01 · Gate de emissão automática é per-business  [T0] [fiscal]
// ---------------------------------------------------------------------------------------
it('UC-NFTR-01 · toggle de emissão automática não atravessa para outro business', function () {
    nftrConfig(NFTR_BIZ, 'simples', auto: false);
    nftrConfig(NFTR_BIZ_OUTRO, 'simples', auto: false);

    // Ligar no próprio tenant…
    $this->post(NFTR_TOGGLE_URL, ['enabled' => true])->assertRedirect();

    $eu = DB::table('nfe_business_configs')->where('business_id', NFTR_BIZ)->value('auto_emission_enabled');
    $vizinho = DB::table('nfe_business_configs')->where('business_id', NFTR_BIZ_OUTRO)->value('auto_emission_enabled');

    expect((bool) $eu)->toBeTrue();        // controle positivo: a ação de fato aconteceu
    expect((bool) $vizinho)->toBeFalse();  // …e NÃO arrastou o vizinho a emitir NFe sozinho

    // …e desligar também não atravessa (o vizinho ligado permanece ligado).
    DB::table('nfe_business_configs')->where('business_id', NFTR_BIZ_OUTRO)->update(['auto_emission_enabled' => true]);
    $this->post(NFTR_TOGGLE_URL, ['enabled' => false])->assertRedirect();

    expect((bool) DB::table('nfe_business_configs')->where('business_id', NFTR_BIZ)->value('auto_emission_enabled'))->toBeFalse();
    expect((bool) DB::table('nfe_business_configs')->where('business_id', NFTR_BIZ_OUTRO)->value('auto_emission_enabled'))->toBeTrue();
});

// ---------------------------------------------------------------------------------------
// UC-NFTR-07 · Ligar o toggle NÃO emite nada — é configuração, não gatilho  [T0] [fiscal]
// ---------------------------------------------------------------------------------------
//
// POR QUE ESTE CASO EXISTE (regra de domínio, [W] 2026-07-28)
// -----------------------------------------------------------
// "As notas não podem sair automáticas em todos os clientes. Não é assim que funciona.
//  O cliente escolhe se quer emitir ou não. E TEM CONFIGURAÇÃO POR EMPRESA se isso é
//  automático."
//
// A emissão automática é OPT-IN POR EMPRESA (`nfe_business_configs.auto_emission_enabled`).
// O toggle apenas GRAVA a escolha da empresa; quem emite é o listener de venda finalizada
// (`EmitirNfceAoFinalizarVenda` → `EmitirNfceJob`), e só para quem ligou.
//
// O charter da tela JÁ declarava isso — §Automation Anti-hooks, literal: "Não dispara Job
// de emissão quando toggleAutoEmission=true (Job é disparado por listener de venda
// finalizada)". E o mesmo charter promete "cada item vira Pest GUARD test". Este item não
// tinha guard: a regra estava ESCRITA e INDEFESA. Um agente futuro lendo só o controller
// pode "otimizar" emitindo no toggle — e nada quebraria. Agora quebra.
//
// Anti-vácuo: o caso afirma uma AUSÊNCIA (nada foi despachado), então precisa provar antes
// que a operação de fato aconteceu — senão mede não-execução e chama de contrato satisfeito
// (proibicoes.md §5 2026-07-24).
it('UC-NFTR-07 · ligar a emissão automática grava a escolha da empresa e NÃO despacha emissão', function () {
    Bus::fake();

    nftrConfig(NFTR_BIZ, 'simples', auto: false);

    $this->post(NFTR_TOGGLE_URL, ['enabled' => true])->assertRedirect();

    // PRÉ-CONDIÇÃO ANTI-VÁCUO: a escolha PRECISA ter sido gravada. Sem isto, um "não
    // despachou" poderia significar apenas que a request morreu antes de fazer qualquer coisa.
    expect((bool) DB::table('nfe_business_configs')->where('business_id', NFTR_BIZ)->value('auto_emission_enabled'))
        ->toBeTrue();

    // O CONTRATO: configurar ≠ emitir. Nenhum documento fiscal sai daqui.
    Bus::assertNotDispatched(EmitirNfceJob::class);
    Bus::assertNotDispatched(EmitirNFSeJob::class);
});

// ---------------------------------------------------------------------------------------
// UC-NFTR-02 · Toggle sem config é recusado e não cria config  [fiscal]
// ---------------------------------------------------------------------------------------
it('UC-NFTR-02 · toggle sem config existente é recusado e não cria config', function () {
    expect(DB::table('nfe_business_configs')->where('business_id', NFTR_BIZ)->count())->toBe(0);

    $this->post(NFTR_TOGGLE_URL, ['enabled' => true])
        ->assertRedirect()
        ->assertSessionHas('error');

    // O ponto do caso: a guarda não pode "criar config no caminho" (um `firstOrCreate` bem
    // intencionado ligaria a emissão automática em cima de uma config vazia).
    expect(DB::table('nfe_business_configs')->where('business_id', NFTR_BIZ)->count())->toBe(0);

    // Controle positivo: com config, o mesmo POST passa — prova que o vermelho acima veio da
    // guarda, e não de a rota nunca alcançar o handler.
    nftrConfig(NFTR_BIZ);
    $this->post(NFTR_TOGGLE_URL, ['enabled' => true])
        ->assertRedirect()
        ->assertSessionHas('success');
    expect((bool) DB::table('nfe_business_configs')->where('business_id', NFTR_BIZ)->value('auto_emission_enabled'))->toBeTrue();
});

// ---------------------------------------------------------------------------------------
// UC-NFTR-03 · Aplicar template substitui a config e preserva as regras NCM  [fiscal]
// ---------------------------------------------------------------------------------------
it('UC-NFTR-03 · aplicar template substitui a config e preserva as regras NCM', function () {
    nftrConfig(NFTR_BIZ, 'lucro_real');              // regime distinto do template (`simples`)
    $r1 = nftrRegra(NFTR_BIZ, '49019900');           // refinos que a operadora fez à mão
    $r2 = nftrRegra(NFTR_BIZ, '22021000', 'RJ', '6102');
    $antesRegras = DB::table('nfe_fiscal_rules')->whereIn('id', [$r1, $r2])->orderBy('id')->get()->toArray();

    $this->post("/nfe-brasil/tributacao/templates/".NFTR_TEMPLATE."/aplicar")->assertRedirect();

    // (a) a config FOI substituída — controle positivo da ação destrutiva-por-design.
    expect(DB::table('nfe_business_configs')->where('business_id', NFTR_BIZ)->value('regime'))->toBe('simples');

    // (b) …e o estrago PAROU na config: as regras NCM (Níveis 2/3) seguem intactas.
    $depoisRegras = DB::table('nfe_fiscal_rules')->whereIn('id', [$r1, $r2])->orderBy('id')->get()->toArray();
    expect($depoisRegras)->toEqual($antesRegras);
    expect(DB::table('nfe_fiscal_rules')->where('business_id', NFTR_BIZ)->count())->toBe(2);

    // (c) idempotência: re-aplicar o mesmo template é no-op SEMÂNTICO (docblock do
    //     TributacaoTemplateService::aplicar). Comparo regime + o JSON DECODIFICADO com `==`
    //     (insensível à ordem das chaves) em vez da row inteira: a coluna é `json` e o MySQL
    //     NORMALIZA a ordem das chaves de objeto, então comparar a string crua — ou o
    //     `updated_at` — mediria o formato de armazenamento, não o contrato.
    $regimeAntes = DB::table('nfe_business_configs')->where('business_id', NFTR_BIZ)->value('regime');
    $tdAntes = json_decode(DB::table('nfe_business_configs')->where('business_id', NFTR_BIZ)->value('tributacao_default'), true);

    $this->post("/nfe-brasil/tributacao/templates/".NFTR_TEMPLATE."/aplicar")->assertRedirect();

    $tdDepois = json_decode(DB::table('nfe_business_configs')->where('business_id', NFTR_BIZ)->value('tributacao_default'), true);
    expect(DB::table('nfe_business_configs')->where('business_id', NFTR_BIZ)->value('regime'))->toBe($regimeAntes);
    expect($tdDepois == $tdAntes)->toBeTrue();
    expect(DB::table('nfe_business_configs')->where('business_id', NFTR_BIZ)->count())->toBe(1);  // não duplicou row
});

// ---------------------------------------------------------------------------------------
// UC-NFTR-04 · Update/destroy de regra de outro business → 404, e a regra alheia sobrevive  [T0]
// ---------------------------------------------------------------------------------------
it('UC-NFTR-04 · update e destroy de regra de outro business dão 404 e não tocam a regra alheia', function () {
    $alheia = nftrRegra(NFTR_BIZ_OUTRO, '49019900');
    $antes = DB::table('nfe_fiscal_rules')->where('id', $alheia)->first();

    $payload = [
        'ncm' => '11111111', 'uf_origem' => 'SP', 'uf_destino' => null, 'cfop' => '9999',
        'csosn' => '900', 'aliquota_icms' => 0.99, 'aliquota_pis' => 0.99,
        'aliquota_cofins' => 0.99, 'aliquota_ipi' => 0.99,
    ];

    $this->put("/nfe-brasil/tributacao/regras/{$alheia}", $payload)->assertNotFound();
    $this->delete("/nfe-brasil/tributacao/regras/{$alheia}")->assertNotFound();

    // O 404 sozinho não basta — o que importa é que NADA foi escrito antes dele.
    expect(DB::table('nfe_fiscal_rules')->where('id', $alheia)->first())->toEqual($antes);

    // Controle positivo: a MESMA rota funciona na regra do próprio business — prova que os 404
    // acima vieram do escopo, não de rota/permissão quebrada.
    $minha = nftrRegra(NFTR_BIZ, '49019900');
    $this->put("/nfe-brasil/tributacao/regras/{$minha}", $payload)->assertRedirect();
    expect(DB::table('nfe_fiscal_rules')->where('id', $minha)->value('cfop'))->toBe('9999');

    // `NfeFiscalRule` usa SoftDeletes: "removida" = `deleted_at` preenchido, a linha PERMANECE.
    // Afirmar `->exists() === false` seria medir a coisa errada e daria vermelho num
    // comportamento correto (e desejável: histórico de regra fiscal não se apaga).
    $this->delete("/nfe-brasil/tributacao/regras/{$minha}")->assertRedirect();
    expect(DB::table('nfe_fiscal_rules')->where('id', $minha)->value('deleted_at'))->not->toBeNull();
});

// ---------------------------------------------------------------------------------------
// UC-NFTR-05 · Listagem não traz regra de outro tenant (com auth real)  [T0]
// ---------------------------------------------------------------------------------------
it('UC-NFTR-05 · listagem traz a regra do próprio business e nenhuma do vizinho', function () {
    nftrRegra(NFTR_BIZ, '49019900');        // minha
    nftrRegra(NFTR_BIZ_OUTRO, '22021000');  // do vizinho

    // `regras` é `Inertia::defer` — não vem no render inicial, e arrancá-la por partial reload
    // exigiria casar `X-Inertia-Version` (mismatch → 409, fragilidade que não tem a ver com o
    // caso). Chamamos o controller COM o usuário autenticado: é a autenticação — não o HTTP —
    // que ativa o `ScopeByBusiness` (ele faz early-return em `! auth()->check()`), então as duas
    // camadas de isolamento valem aqui exatamente como valeriam na rota.
    $request = Illuminate\Http\Request::create('/nfe-brasil/tributacao', 'GET');
    $request->setLaravelSession(app('session.store'));
    $request->session()->put('business.id', NFTR_BIZ);
    $request->session()->put('user.business_id', NFTR_BIZ);  // chave que o ScopeByBusiness lê

    $response = (new Modules\NfeBrasil\Http\Controllers\TributacaoController())->index($request);

    $props = new ReflectionProperty($response, 'props');
    $props->setAccessible(true);
    $deferred = $props->getValue($response)['regras'];
    $callback = new ReflectionProperty($deferred, 'callback');
    $callback->setAccessible(true);
    $regras = ($callback->getValue($deferred))();

    expect($regras)->toHaveCount(1);                  // controle positivo: a minha veio…
    expect($regras[0]['ncm'])->toBe('49019900');      // …e é a minha mesmo, não a do vizinho
});
