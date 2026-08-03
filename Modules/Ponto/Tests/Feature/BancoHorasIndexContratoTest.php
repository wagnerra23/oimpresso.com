<?php

declare(strict_types=1);

use App\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Ponto\Entities\BancoHorasSaldo;
use Modules\Ponto\Entities\Colaborador;
use Modules\Ponto\Tests\Feature\PontoTestCase;

uses(PontoTestCase::class);

/**
 * Contrato da tela de SALDOS de banco de horas (`/ponto/banco-horas`) — trio derivado
 * do SDD (agent `sdd-from-source`, ADR 0351).
 *
 * Cada teste cita o UC no TÍTULO do `it()` (G-2 do casos-gate, ADR 0264):
 *   BancoHoras/Index.casos.md → UC-BHIDX-01..04
 *
 * Os UC derivam do SDD §6.3 (CU-PONTO-08) e §6.5 (CU-PONTO-12), ancorados em
 * US-PONTO-004/007 e CLT Art. 59 §5º. NÃO derivam do `.tsx` (teste tautológico —
 * proibicoes.md §5 2026-06-05).
 *
 * ── Por que Pest `it()` e não classe PHPUnit ────────────────────────────────
 * O `casos-results-collect.mjs` (manifesto G-7) lê o UC-id do atributo `name` do
 * `<testcase>` no JUnit. Método PHP não aceita hífen, então método vira nome
 * humanizado sem hífen e o regex canônico (`scripts/lib/uc-regex.mjs`, que exige
 * `UC-BHIDX-NN`) nunca casa. Medido na sessão de 2026-08-02: dos 82 UCs do
 * manifesto, 82 vêm de título `it()` e 0 de método. Um teste em classe nasceria
 * verde e valendo 0 — ⚠️ é exatamente o estado dos 3 `*ContratoTest` irmãos deste
 * módulo (UC em docblock, método `uc_bhshow_01_…`): a conversão deles é
 * oportunística, quando o arquivo for tocado por trabalho real. NÃO varrer em lote
 * (proibicoes.md §5 2026-07-12).
 *
 * ── Tier 0 ─────────────────────────────────────────────────────────────────
 * biz=1 (WR2 interno) — NUNCA biz=4 (ROTA LIVRE, ADR 0101). Sem `RefreshDatabase`:
 * a lane `ponto-pest` proíbe (dropa o schema e limpa o seed biz=1).
 *
 * ── [V0] ───────────────────────────────────────────────────────────────────
 * Minuto de jornada é valor (SDD §3.2). Os UC aqui provam ISOLAMENTO do agregado
 * (o total não muda quando nasce saldo alheio), NUNCA o valor apurado — assert
 * sobre o número exigiria o protocolo da REGRA MESTRE (2 caminhos + antes→depois).
 *
 * Contrato: resources/js/Pages/Ponto/BancoHoras/Index.casos.md
 *
 * @see \Modules\Ponto\Http\Controllers\BancoHorasController::index
 */

const BHIDX_MARCADOR = 'SDD-BHIDX-CONTRATO';
const BHIDX_BIZ_ALHEIO = 99;
const BHIDX_BIZ_NOME = 'BHIDX Test Biz Adversario#99';

/** Guard de ambiente — schema do Ponto presente. Skip gracioso e VISÍVEL. */
function bhIdxPrecisaDe(array $tabelas): void
{
    foreach ($tabelas as $t) {
        if (! Schema::hasTable($t)) {
            test()->markTestSkipped("Tabela {$t} ausente — schema do Ponto não migrado nesta lane.");
        }
    }
}

/**
 * Stub do business fictício 99.
 *
 * MEDIDO na lane (run 30778424885, PR #5191): sem isto o INSERT do colaborador
 * alheio morre com `SQLSTATE[23000] 1452 ... ponto_colaborador_config_business_id_foreign`
 * — nem o clone-de-prod do CT100 nem o seed biz=1/biz=2 do `pest-mysql-setup` têm
 * biz=99, e a FK é real. O teste não reprova: ele MORRE NO SETUP, sem exercer
 * isolamento nenhum (o mesmo modo de falha que a fixture do UC-FCC-06 tinha).
 *
 * Padrão copiado de `Wave27CrossTenantEscalaTest` — o único teste do módulo que já
 * rodava verde nesta lane, e que documenta a mesma armadilha. Convenção biz=99:
 * ADR 0101 (nunca biz=4, que é cliente real).
 */
function bhIdxGarantirBizAlheio(): void
{
    if (\App\Business::find(BHIDX_BIZ_ALHEIO)) {
        return;
    }

    \App\Business::forceCreate([
        'id'                              => BHIDX_BIZ_ALHEIO,
        'name'                            => BHIDX_BIZ_NOME,
        'currency_id'                     => 1,
        'start_date'                      => now()->toDateString(),
        'default_profit_percent'          => 0,
        'owner_id'                        => 1,
        'stop_selling_before'             => 0,
        'weighing_scale_setting'          => '',
        'certificado'                     => '',
        'officeimpresso_numerodemaquinas' => 0,
    ]);
}

/**
 * Cria colaborador + saldo marcados pro cleanup.
 *
 * `forceFill` (e não `create`) porque o saldo alheio precisa nascer com
 * `business_id` de OUTRO business — o global scope impediria pelo caminho normal,
 * e é justamente isso que o UC-BHIDX-02/03 querem ver barrado na LEITURA.
 */
function bhIdxCriarColaboradorComSaldo(int $businessId, int $saldoMinutos, int $userBusinessId): Colaborador
{
    $user = User::factory()->create([
        'business_id' => $userBusinessId,
        'user_type'   => 'user',
    ]);

    $colab = new Colaborador();
    $colab->forceFill([
        'business_id'     => $businessId,
        'user_id'         => $user->id,
        'matricula'       => BHIDX_MARCADOR . '-' . uniqid(),
        'controla_ponto'  => true,
        'usa_banco_horas' => true,
    ])->save();

    $saldo = new BancoHorasSaldo();
    $saldo->forceFill([
        'business_id'           => $businessId,
        'colaborador_config_id' => $colab->id,
        'saldo_minutos'         => $saldoMinutos,
    ])->save();

    return $colab;
}

/** Pede as props diferidas (Inertia::defer) numa segunda passada. */
function bhIdxInertiaPartial(array $props)
{
    $manifestPath = public_path('build-inertia/manifest.json');
    $version = file_exists($manifestPath) ? md5_file($manifestPath) : '1';

    return test()->withHeaders([
        'X-Inertia'                   => 'true',
        'X-Inertia-Version'           => $version,
        'X-Inertia-Partial-Data'      => implode(',', $props),
        'X-Inertia-Partial-Component' => 'Ponto/BancoHoras/Index',
        'Accept'                      => 'text/html',
    ])->get('/ponto/banco-horas');
}

afterEach(function () {
    try {
        $ids = Colaborador::withoutGlobalScopes()
            ->where('matricula', 'like', BHIDX_MARCADOR . '%')
            ->pluck('id');

        if ($ids->isNotEmpty()) {
            // Ledger é append-only via Eloquent — no cleanup de teste usamos
            // DB::table de propósito (jamais em código de produção).
            DB::table('ponto_banco_horas_movimentos')->whereIn('colaborador_config_id', $ids)->delete();
            DB::table('ponto_banco_horas_saldo')->whereIn('colaborador_config_id', $ids)->delete();
            Colaborador::withoutGlobalScopes()->whereIn('id', $ids)->delete();
        }

        // Remove SÓ o stub que este arquivo criou (filtro por nome próprio): o
        // Wave27 usa o mesmo id 99 com nome dele, e não pode ser derrubado aqui.
        \App\Business::where('id', BHIDX_BIZ_ALHEIO)
            ->where('name', BHIDX_BIZ_NOME)
            ->delete();
    } catch (\Throwable $e) {
        // schema ausente — cleanup best-effort
    }
});

// =====================================================================
// BancoHoras/Index
// =====================================================================

it('UC-BHIDX-01 · a lista traz os colaboradores do meu empregador com o saldo deles', function () {
    $this->actAsAdmin();
    bhIdxPrecisaDe(['ponto_colaborador_config', 'ponto_banco_horas_saldo']);

    $colab = bhIdxCriarColaboradorComSaldo($this->business->id, 180, $this->business->id);

    $resp = bhIdxInertiaPartial(['saldos']);
    $resp->assertStatus(200);

    $linhas = collect($resp->json('props.saldos.data') ?? []);

    // Pré-condição anti-vácuo: se a lista vier vazia, o teste NÃO passou por
    // preservação — passou por não-execução (proibicoes.md §5 2026-07-24 LC-13).
    expect($linhas)->not->toBeEmpty(
        'A lista de saldos veio vazia — o caso não exerceu nada.'
    );

    $minha = $linhas->firstWhere('colaborador_id', $colab->id);

    expect($minha)->not->toBeNull(
        'O colaborador do meu business com saldo tem de aparecer na lista (CU-PONTO-08).'
    );
    expect($minha['saldo_minutos'])->toBe(180,
        'O saldo exibido tem de ser o que está registrado no ledger.'
    );
    // Identidade, não só a linha: `optional()` encadeado devolve '—' em silêncio se
    // o eager-load da relação sumir, e a query segue verde.
    expect($minha['matricula'])->toStartWith(BHIDX_MARCADOR,
        'A linha tem de trazer a matrícula do colaborador, não um placeholder.'
    );
});

it('UC-BHIDX-02 · saldo de outro empregador não aparece na lista', function () {
    $this->actAsAdmin();
    bhIdxPrecisaDe(['ponto_colaborador_config', 'ponto_banco_horas_saldo']);
    bhIdxGarantirBizAlheio();

    // O user fica no MEU business (evita FK/business inexistente); o que é alheio
    // é o colaborador e o saldo — que é o dado que a tela lista.
    $meu    = bhIdxCriarColaboradorComSaldo($this->business->id, 60, $this->business->id);
    $alheio = bhIdxCriarColaboradorComSaldo(BHIDX_BIZ_ALHEIO, 999, $this->business->id);

    $resp = bhIdxInertiaPartial(['saldos']);
    $resp->assertStatus(200);

    $ids = collect($resp->json('props.saldos.data') ?? [])->pluck('colaborador_id')->all();

    // Pré-condição anti-vácuo: sem o meu na lista, "o alheio não está" seria
    // verdade por lista vazia, não por isolamento.
    expect($ids)->toContain($meu->id,
        'O colaborador do meu business tem de estar na lista — senão o caso não exerceu isolamento.'
    );
    expect($ids)->not->toContain($alheio->id,
        'Saldo de banco de horas de OUTRO empregador não pode aparecer (ADR 0093 · CU-PONTO-12).'
    );
});

it('UC-BHIDX-03 · saldo de outro empregador não entra nos totais agregados', function () {
    $this->actAsAdmin();
    bhIdxPrecisaDe(['ponto_colaborador_config', 'ponto_banco_horas_saldo']);
    bhIdxGarantirBizAlheio();

    // Antes: fotografa os 4 agregados do MEU business.
    $antes = bhIdxInertiaPartial(['totais']);
    $antes->assertStatus(200);
    $totaisAntes = $antes->json('props.totais');

    expect($totaisAntes)->toBeArray(
        'Os totais precisam chegar na passada partial — sem eles o caso não mede nada.'
    );

    // Nasce um saldo CREDOR gordo em outro empregador.
    bhIdxCriarColaboradorComSaldo(BHIDX_BIZ_ALHEIO, 99999, $this->business->id);

    // Depois: os agregados do meu business não podem ter se mexido.
    $depois = bhIdxInertiaPartial(['totais']);
    $depois->assertStatus(200);
    $totaisDepois = $depois->json('props.totais');

    expect($totaisDepois)->toBe($totaisAntes,
        'Os 4 agregados do meu business não podem mudar porque nasceu saldo em OUTRO '
        . 'empregador. Agregado que vaza não deixa linha para ninguém notar (ADR 0093).'
    );
});

it('UC-BHIDX-04 · lista e totais são carregados sob demanda, não no primeiro response', function () {
    $this->actAsAdmin();
    bhIdxPrecisaDe(['ponto_colaborador_config', 'ponto_banco_horas_saldo']);

    $manifestPath = public_path('build-inertia/manifest.json');
    $version = file_exists($manifestPath) ? md5_file($manifestPath) : '1';

    // Primeiro response (SEM cabeçalho de partial): props deferidas não viajam.
    $primeiro = $this->withHeaders([
        'X-Inertia'         => 'true',
        'X-Inertia-Version' => $version,
        'Accept'            => 'text/html',
    ])->get('/ponto/banco-horas');

    $primeiro->assertStatus(200);
    $props = $primeiro->json('props') ?? [];

    // Aqui a AUSÊNCIA da chave É o contrato (`Inertia::defer`), não proxy de valor.
    expect($props)->not->toHaveKey('saldos');
    expect($props)->not->toHaveKey('totais');

    // Controle positivo: as mesmas props CHEGAM quando pedidas — senão este caso
    // passaria com a rota quebrada devolvendo props vazio.
    $segundo = bhIdxInertiaPartial(['saldos', 'totais']);
    $segundo->assertStatus(200);

    expect($segundo->json('props'))->toHaveKey('saldos');
    expect($segundo->json('props'))->toHaveKey('totais');
});
