<?php

declare(strict_types=1);

use App\Business;
use App\User;
use Illuminate\Support\Facades\DB;
use Modules\ComunicacaoVisual\Entities\Material;
use Spatie\Permission\Models\Permission;

uses(Tests\TestCase::class);

/**
 * Contrato da TELA de orçamento por m² (`/comunicacao-visual`).
 *
 * Nasce com o trio da tela (chip da Onda 4 do passo 5 · ADR 0351) — os UC derivam do
 * SDD §6, NUNCA do `Index.tsx` (teste derivado do código é tautológico e trava o desvio
 * em vez de pegá-lo — proibicoes §5 2026-06-05).
 *
 * Cobre:
 *   UC-CV-07  ·  CU-CV-09  ·  US-COMVIS-002 — a calculadora recebe o catálogo do business
 *   UC-CV-09  ·  CU-CV-10  ·  US-COMVIS-006 — substrato nasce com os campos fiscais do CNAE 1813
 *   UC-CV-10  ·  CU-CV-01                  — o hub abre com permissao e renderiza a calculadora
 *
 * ⚠️ FORÇA DO VEREDITO (medido 2026-07-28, três portas distintas):
 *   · roda em algum lugar? SIM — `phpunit.xml` inclui `./Modules/ComunicacaoVisual/Tests/Feature`
 *     e `scripts/tests/shards-plan.mjs` enumera `Modules` (full-suite noturna, MySQL real).
 *   · roda no PR? o 1º teste NÃO — `modules-pest.yml` roda `DB_CONNECTION=sqlite :memory:`
 *     SEM migrate, então ele cai no `markTestSkipped` igual aos outros 6 arquivos DB do módulo.
 *     O 2º teste é source-scoped e roda em qualquer lane.
 *   · bloqueia merge? NÃO — `Pest ComunicacaoVisual` não está em
 *     `governance/required-checks-baseline.json` (Pest required = Financeiro, NfeBrasil, Unit).
 *
 * PREVISÃO DECLARADA (predição, não veredito — nenhum teste foi executado neste PR, CT 100/CI
 * é quem roda · ADR 0062): o 1º teste é **vermelho esperado** — a rota só passa `bizName`
 * (varredura contada: `git grep "ComunicacaoVisual/Index" -- '*.php'` = 1 render real + 1
 * comentário; `git grep "'materiais'" -- Modules/ComunicacaoVisual` = 1 hit, e é tradução).
 *
 * Tests biz=1 (Wagner WR2) conforme ADR 0101 — nunca biz=4 (cliente ROTA LIVRE).
 *
 * @covers-us US-COMVIS-002
 * @covers-us US-COMVIS-006
 *
 * @see memory/requisitos/ComunicacaoVisual/SDD-tela-orcamento-m2-v1.0.md §5.4.1 · §6 CU-CV-09/CU-CV-10
 * @see resources/js/Pages/ComunicacaoVisual/Index.casos.md UC-CV-07 · UC-CV-09
 * @see memory/decisions/0093-multi-tenant-isolation-tier-0.md
 * @see memory/decisions/0101-tests-business-id-1-nunca-cliente.md
 */

// ------------------------------------------------------------------
// Helpers (nomes prefixados: Pest carrega todos os arquivos no MESMO
// processo — `bootstrapComvisUser`/`bootstrapControllerApt` já existem
// nos irmãos e redeclarar quebraria a suíte inteira).
// ------------------------------------------------------------------

/** Raiz do repo a partir de Modules/ComunicacaoVisual/Tests/Feature/. */
function contratoTelaCvPath(string $rel): string
{
    return dirname(__DIR__, 4) . '/' . ltrim($rel, '/');
}

/** Usuário do biz=1 com permissão de ver o hub. Skip explícito quando o ambiente não tem schema. */
function contratoTelaCvUser(): array
{
    if (DB::connection()->getDriverName() === 'sqlite') {
        test()->markTestSkipped(
            'SQLite-incompatível: requer schema MySQL UltimatePOS. Este caso roda na full-suite ' .
            'noturna (CT 100/MySQL), não na lane sqlite do PR — ver SDD §6 tabela das 3 portas.'
        );
    }

    try {
        $business = Business::find(1) ?? Business::first();
    } catch (\Throwable $e) {
        test()->markTestSkipped('Tabela business indisponível: ' . $e->getMessage());
    }

    if (! $business) {
        test()->markTestSkipped('Sem business no banco — rode o seeder UltimatePOS antes.');
    }

    $user = User::where('business_id', $business->id)->first();
    if (! $user) {
        test()->markTestSkipped('Sem user no business_id=1.');
    }

    // Spatie v6: givePermissionTo com string exige a permission pré-existente.
    Permission::findOrCreate('comvis.orcamento.view', 'web');
    $user->givePermissionTo('comvis.orcamento.view');

    session([
        'user.business_id'         => $business->id,
        'user.id'                  => $user->id,
        'business.id'              => $business->id,
        'business.name'            => $business->name,
        'business.currency_symbol' => 'R$',
        'is_admin'                 => true,
    ]);

    return [$business, $user];
}

// ------------------------------------------------------------------
// UC-CV-07 — a calculadora recebe o catálogo do business (CU-CV-09)
// ------------------------------------------------------------------

it('UC-CV-07 — a pagina do hub entrega o catalogo de materiais do business a calculadora', function () {
    [$business, $user] = contratoTelaCvUser();

    // Nome único: o assert é sobre o CONTEÚDO chegar, não sobre o nome da prop.
    // (Assert acoplado à chave literal reprovaria arbitrariamente uma das duas
    //  correções válidas — passar a prop na rota OU a tela buscar por fetch.
    //  O contrato é "o catálogo chega", não "a prop se chama materiais".)
    $marcador = 'Lona front-light CONTRATO-' . uniqid();

    // SUPERADMIN: setup de fixture — cria direto no business do teste.
    $material = Material::withoutGlobalScopes()->create([
        'business_id'    => $business->id,
        'nome'           => $marcador,
        'categoria'      => 'lona',
        'unidade'        => 'm2',
        'preco_custo_m2' => 20.00,
        'preco_venda_m2' => 60.00,
        'ativo'          => true,
    ]);

    try {
        $resposta = $this->actingAs($user)->get('/comunicacao-visual');

        // PRÉ-CONDIÇÃO ANTI-VÁCUO: sem 200 o teste não mediu contrato nenhum —
        // ele mediu o gate de permissão. Verde por não-execução é o falso-verde
        // catalogado em proibicoes §5 (2026-07-24).
        $resposta->assertOk();

        $pagina = $resposta->viewData('page');
        expect($pagina)->toBeArray()
            ->and($pagina['component'] ?? null)->toBe('ComunicacaoVisual/Index');

        $payload = json_encode($pagina['props'] ?? [], JSON_UNESCAPED_UNICODE) ?: '';

        // `toContain(...$needles)` é VARIÁDICO no Pest (Mixins/Expectation.php:184 —
        // `foreach ($needles as $needle)`). Passar a explicação como 2º argumento fazia o
        // Pest procurar a FRASE INTEIRA no payload: falha garantida, e o erro dizia
        // "To contain: <a explicação>", despistando pro lado do código. Quem aceita
        // mensagem é `toBeTrue(string $message = '')` (linha 88 do mesmo arquivo).
        expect(str_contains($payload, $marcador))->toBeTrue(
            'O catálogo do business não chega à calculadora: a página renderiza sem nenhum ' .
            'material, então o seletor fica desabilitado ("Sem catálogo") e a operadora precisa ' .
            'digitar o preço/m² à mão em toda peça. Ver SDD §5.4.1 / CU-CV-09.'
        );
    } finally {
        Material::withoutGlobalScopes()->where('id', $material->id)->forceDelete();
    }
});

it('UC-CV-07 controle-negativo — catalogo de OUTRO business nunca chega a pagina', function () {
    [$business, $user] = contratoTelaCvUser();

    $bizAlheio = 99;
    if ((int) $business->id === $bizAlheio) {
        test()->markTestSkipped('Business do teste colide com o business de controle (99).');
    }

    $marcadorAlheio = 'Vinil ALHEIO-' . uniqid();

    $material = Material::withoutGlobalScopes()->create([
        'business_id'    => $bizAlheio,
        'nome'           => $marcadorAlheio,
        'categoria'      => 'vinil_adesivo',
        'unidade'        => 'm2',
        'preco_custo_m2' => 10.00,
        'preco_venda_m2' => 30.00,
        'ativo'          => true,
    ]);

    try {
        $resposta = $this->actingAs($user)->get('/comunicacao-visual');
        $resposta->assertOk(); // pré-condição anti-vácuo

        $payload = json_encode($resposta->viewData('page')['props'] ?? [], JSON_UNESCAPED_UNICODE) ?: '';

        expect($payload)->not->toContain(
            $marcadorAlheio,
            'Vazamento cross-tenant Tier 0 (ADR 0093): material de outro business apareceu no ' .
            'payload da página. Ver SDD §6 CU-CV-09 item 2.'
        );
    } finally {
        Material::withoutGlobalScopes()->where('id', $material->id)->forceDelete();
    }
});

// ------------------------------------------------------------------
// UC-CV-09 — substrato nasce com os campos fiscais do CNAE 1813 (CU-CV-10)
// ------------------------------------------------------------------
//
// Classe deste caso: GUARD ESTRUTURAL (lê o fonte da migration, não exercita runtime).
// Aqui o nome da coluna É o contrato — `ncm`/`cfop_padrao`/`csosn_padrao` são campos
// fiscais definidos por SEFAZ/CONFAZ, não chaves arbitrárias de payload; logo isto NÃO é
// o "assert acoplado à chave literal" que a ADR 0351 bane. Roda em qualquer lane.

it('UC-CV-09 — o schema de substratos carrega ncm, cfop_padrao e csosn_padrao (CNAE 1813)', function () {
    $migration = contratoTelaCvPath(
        'Modules/ComunicacaoVisual/Database/Migrations/2026_05_12_000010_create_cv_substratos_table.php'
    );

    // Pré-condição anti-vácuo: se a migration sumiu/renomeou, o teste falha dizendo isso —
    // não passa calado por não ter o que ler.
    expect(file_exists($migration))->toBeTrue(
        "Migration de substratos não encontrada em {$migration} — a US-COMVIS-006 aponta pra ela."
    );

    $fonte = (string) file_get_contents($migration);

    // Mesmo defeito da linha ~141: a explicação ia como 2º NEEDLE, não como mensagem.
    // Este caso é o que derrubou a lane em 2026-07-28 — e acusava a migration, que está
    // CORRETA (3.260 bytes, os 3 campos presentes). Falso-vermelho: o assert é que estava
    // errado. Ver `vendor/pestphp/pest/src/Mixins/Expectation.php:184`.
    foreach (['ncm', 'cfop_padrao', 'csosn_padrao'] as $campoFiscal) {
        expect(str_contains($fonte, $campoFiscal))->toBeTrue(
            "O substrato precisa carregar `{$campoFiscal}` pra emitir NFC-e/NFe de impresso " .
            'publicitário sem contador configurar item a item (US-COMVIS-006 DoD / CU-CV-10 item 1).'
        );
    }
});
