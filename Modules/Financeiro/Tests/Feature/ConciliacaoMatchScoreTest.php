<?php

declare(strict_types=1);

use App\Business;
use App\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Financeiro\Http\Controllers\ConciliacaoController;
use Modules\Financeiro\Models\Titulo;

uses(Tests\TestCase::class);

/**
 * Bug B1 — match_score REAL na Conciliação OFX.
 *
 * Antes, ConciliacaoController::sugerirMatches() gravava o constante 0.85 em
 * `match_score` pra QUALQUER candidato — a UI mostrava "match 85%" como se fosse
 * calculado. Agora o score é REAL em [0,1] (peso 0.7 valor + 0.3 proximidade-data,
 * arredondado a 2 casas).
 *
 * Cobre:
 *  (a) valor exato + mesmo dia  → score ≈ 1.0 (e NÃO 0.85)
 *  (b) candidato com data afastada → score estritamente menor que o exato
 *  (c) score nunca é o constante 0.85 pra candidatos distintos
 *
 * Roda contra DB dev real (sem RefreshDatabase) em business_id=1 (dogfooding,
 * NUNCA biz de cliente). Skip gracioso quando schema/seed ausente.
 * Modelado conforme Modules/Financeiro/Tests/Feature/OnCobrancaPagaCreateFinanceiroTituloTest.php.
 */

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('Requer schema MySQL UltimatePOS + Financeiro.');
    }
    if (! Schema::hasTable('fin_titulos') || ! Schema::hasTable('fin_bank_statement_lines')) {
        $this->markTestSkipped('Schema Financeiro (fin_titulos / fin_bank_statement_lines) ausente.');
    }
});

/** Usuário real do business=1 (created_by é FK NOT NULL em fin_titulos). */
function concilScore_userBiz1(): ?User
{
    $business = Business::find(1);
    if (! $business) {
        test()->markTestSkipped('Sem business_id=1 no banco (dogfooding biz).');
    }

    $user = User::withoutGlobalScopes()->where('business_id', 1)->first();
    if (! $user) {
        test()->markTestSkipped('Sem user no business_id=1.');
    }

    return $user;
}

/**
 * Cria um Titulo a receber aberto em biz=1.
 * `origem_id` único garante que a unique uk_titulo_origem não colida entre os casos.
 */
function concilScore_makeTitulo(int $userId, float $valor, string $vencimento, int $origemId): Titulo
{
    return Titulo::create([
        'business_id'     => 1,
        'numero'          => 'B1-' . $origemId,
        'tipo'            => 'receber',
        'status'          => 'aberto',
        'valor_total'     => $valor,
        'valor_aberto'    => $valor,
        'moeda'           => 'BRL',
        'emissao'         => $vencimento,
        'vencimento'      => $vencimento,
        'competencia_mes' => substr($vencimento, 0, 7),
        'origem'          => 'manual',
        'origem_id'       => $origemId,
        'created_by'      => $userId,
    ]);
}

/** Insere uma linha de extrato pendente em biz=1 e devolve o id criado. */
function concilScore_makeLinha(float $valor, string $dataMovimento, string $fitid): int
{
    return (int) DB::table('fin_bank_statement_lines')->insertGetId([
        'business_id'    => 1,
        'fitid'          => $fitid,
        'data_movimento' => $dataMovimento,
        'descricao'      => 'Linha teste B1',
        'valor'          => $valor,
        'tipo'           => 'credit',
        'status'         => 'pendente',
        'created_at'     => now(),
        'updated_at'     => now(),
    ]);
}

/** Invoca o método privado sugerirMatches() (mesma técnica de reflection já usada na suíte). */
function concilScore_runSugerir(): void
{
    $controller = new ConciliacaoController();
    $ref = new ReflectionMethod($controller, 'sugerirMatches');
    $ref->setAccessible(true);
    $ref->invoke($controller, 1);
}

function concilScore_cleanup(array $lineIds, array $origemIds): void
{
    DB::table('fin_bank_statement_lines')->whereIn('id', $lineIds)->delete();
    Titulo::withoutGlobalScopes()
        ->where('business_id', 1)
        ->where('origem', 'manual')
        ->whereIn('origem_id', $origemIds)
        ->forceDelete();
}

it('valor exato + mesmo dia gera score ~1.0 (NÃO o constante 0.85)', function () {
    $user = concilScore_userBiz1();

    // Âncora no passado pra estabilidade (sem interferir com schedulers/aging).
    $dia = CarbonImmutable::parse('2026-01-15')->toDateString();
    $origemId = 910001;
    $fitid = 'B1-EXATO-' . uniqid();

    $titulo = concilScore_makeTitulo($user->id, 123.45, $dia, $origemId);
    $lineId = concilScore_makeLinha(123.45, $dia, $fitid);

    concilScore_runSugerir();

    $linha = DB::table('fin_bank_statement_lines')->where('id', $lineId)->first();

    expect($linha->status)->toBe('sugerido');
    expect((int) $linha->titulo_id)->toBe($titulo->id);
    expect((float) $linha->match_score)->toBe(1.0);
    expect((float) $linha->match_score)->not->toBe(0.85);

    concilScore_cleanup([$lineId], [$origemId]);
});

it('candidato com data afastada tem score estritamente menor que o exato', function () {
    $user = concilScore_userBiz1();

    $diaLinha = CarbonImmutable::parse('2026-01-15');

    // Caso 1: valor exato + mesmo dia → score máximo (1.0).
    $origemExato = 910010;
    $fitidExato = 'B1-A-' . uniqid();
    $tituloExato = concilScore_makeTitulo($user->id, 200.00, $diaLinha->toDateString(), $origemExato);
    $lineExato = concilScore_makeLinha(200.00, $diaLinha->toDateString(), $fitidExato);

    // Caso 2: valor exato MAS vencimento 1 dia antes → data_score 0.75 → score 0.7+0.225.
    // (valor distinto pra cada linha casar com exatamente 1 título dentro da janela.)
    // NÃO assertamos o decimal exato (0.925 cai na borda de arredondamento) — só a
    // propriedade que importa: estritamente menor que o match exato, >0 e ≠ 0.85.
    $origemLonge = 910011;
    $fitidLonge = 'B1-B-' . uniqid();
    $tituloLonge = concilScore_makeTitulo($user->id, 350.00, $diaLinha->subDays(1)->toDateString(), $origemLonge);
    $lineLonge = concilScore_makeLinha(350.00, $diaLinha->toDateString(), $fitidLonge);

    concilScore_runSugerir();

    $linhaExato = DB::table('fin_bank_statement_lines')->where('id', $lineExato)->first();
    $linhaLonge = DB::table('fin_bank_statement_lines')->where('id', $lineLonge)->first();

    // Sanidade: cada linha casou com o título correto.
    expect((int) $linhaExato->titulo_id)->toBe($tituloExato->id);
    expect((int) $linhaLonge->titulo_id)->toBe($tituloLonge->id);

    $scoreExato = (float) $linhaExato->match_score;
    $scoreLonge = (float) $linhaLonge->match_score;

    expect($scoreExato)->toBe(1.0);
    expect($scoreLonge)->toBeLessThan($scoreExato);
    expect($scoreLonge)->toBeGreaterThan(0.0);
    expect($scoreLonge)->not->toBe(0.85);

    concilScore_cleanup([$lineExato, $lineLonge], [$origemExato, $origemLonge]);
});

it('score nunca é o constante 0.85 pra candidatos distintos', function () {
    $user = concilScore_userBiz1();

    $diaLinha = CarbonImmutable::parse('2026-01-15');

    $origemA = 910020;
    $fitidA = 'B1-C-' . uniqid();
    $tituloA = concilScore_makeTitulo($user->id, 99.90, $diaLinha->toDateString(), $origemA);
    $lineA = concilScore_makeLinha(99.90, $diaLinha->toDateString(), $fitidA);

    $origemB = 910021;
    $fitidB = 'B1-D-' . uniqid();
    $tituloB = concilScore_makeTitulo($user->id, 510.00, $diaLinha->subDays(1)->toDateString(), $origemB);
    $lineB = concilScore_makeLinha(510.00, $diaLinha->toDateString(), $fitidB);

    concilScore_runSugerir();

    $linhaA = DB::table('fin_bank_statement_lines')->where('id', $lineA)->first();
    $linhaB = DB::table('fin_bank_statement_lines')->where('id', $lineB)->first();

    // Sanidade do casamento.
    expect((int) $linhaA->titulo_id)->toBe($tituloA->id);
    expect((int) $linhaB->titulo_id)->toBe($tituloB->id);

    // Nenhum dos scores é o velho 0.85 hardcoded, e eles diferem entre si.
    expect((float) $linhaA->match_score)->not->toBe(0.85);
    expect((float) $linhaB->match_score)->not->toBe(0.85);
    expect((float) $linhaA->match_score)->not->toBe((float) $linhaB->match_score);

    concilScore_cleanup([$lineA, $lineB], [$origemA, $origemB]);
});
