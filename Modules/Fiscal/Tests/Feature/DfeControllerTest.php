<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\NfeBrasil\Models\NfeDfeRecebido;

uses(Tests\TestCase::class);

/**
 * PR #3 Wave DF-e Fiscal — isolation Tier 0 (ADR 0093) + doutrina de tenant (ADR 0358).
 *
 * 2026-09-06 — a lane NfeBrasil passou a EXECUTAR este arquivo (#6902) e o UC-FDFE-01 caiu
 * vermelho. Não era vazamento de produto: o ScopeByBusiness faz early-return em
 * `! auth()->check()` (app/Scopes/ScopeByBusiness.php) e o teste não autenticava ninguém —
 * media o banco cru, e havia linha de outro business deixada por teste vizinho da lane.
 * Mesma falha de TESTE já catalogada em CockpitMultiTenantTest. Agora: autentica um usuário
 * do tenant canônico, planta UMA linha de cada lado com tag própria, e prova as duas pernas
 * (scope esconde · linha existe sem o scope) — senão o verde seria tautológico.
 */

const DFE_TAG = 'UC-FDFE-01-ISO';

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: NfeDfeRecebido requer schema MySQL (ADR 0101)');
    }
    if (! Schema::hasTable('nfe_dfe_recebidos')) {
        $this->markTestSkipped('nfe_dfe_recebidos table missing');
    }
});

afterEach(function () {
    if (! Schema::hasTable('nfe_dfe_recebidos')) {
        return;
    }
    // SUPERADMIN: limpeza do fixture dos DOIS tenants — é o próprio teste de isolamento
    NfeDfeRecebido::withoutGlobalScopes()
        ->where('chave_44', 'like', '%' . DFE_TAG . '%')
        ->delete();
    // UC-FDFE-14/15/16: fixture do tenant fictício 98 (prefixo de chave próprio).
    // Fica neste único afterEach de propósito — um segundo afterEach() no arquivo
    // substituiria este no registro do Pest.
    // SUPERADMIN: limpeza do fixture do tenant fictício, independente de quem está logado
    NfeDfeRecebido::withoutGlobalScopes()
        ->where('business_id', 98)
        ->where('chave_44', 'like', '4299' . str_pad('', 32, '0') . '%')
        ->delete();
});

it('UC-FDFE-01 · NfeDfeRecebido HasBusinessScope esconde cross-tenant da listagem DF-e', function () {
    $tenant  = $this->seededTenant();
    $bizMeu  = (int) $tenant->id;
    // `Trait::CONST` direto e fatal no PHP ("Cannot access trait constant directly" —
    // run 34031885570); le-se pela classe que USA o trait (Tests\TestCase). Lapide
    // §5 2026-07-28 ja avisava (copia em EstoqueFixture pelo mesmo motivo).
    $bizOutro = \Tests\TestCase::SUPPORT_CLIENT_TENANT_ID; // adversário cross-tenant (99)

    $this->actingAs(\App\User::where('business_id', $bizMeu)->firstOrFail());
    session(['business.id' => $bizMeu, 'user.business_id' => $bizMeu]);

    $base = [
        'nsu'           => 1,
        'cnpj_emitente' => '12345678000199',
        'nome_emitente' => 'Fixture ' . DFE_TAG,
        'valor_total'   => 10,
        'data_emissao'  => now(),
    ];
    // SUPERADMIN: plantar o fixture fora do scope é o setup do próprio teste de isolamento
    NfeDfeRecebido::withoutGlobalScopes()->create($base + [
        'business_id' => $bizMeu,
        'chave_44'    => str_pad('1' . DFE_TAG, 44, '0', STR_PAD_RIGHT),
    ]);
    NfeDfeRecebido::withoutGlobalScopes()->create($base + [
        'business_id' => $bizOutro,
        'chave_44'    => str_pad('2' . DFE_TAG, 44, '0', STR_PAD_RIGHT),
    ]);

    // Controle positivo: a linha do outro tenant EXISTE — só o scope pode escondê-la.
    $semScope = NfeDfeRecebido::withoutGlobalScopes() // SUPERADMIN: controle positivo do teste
        ->where('business_id', $bizOutro)
        ->where('chave_44', 'like', '%' . DFE_TAG . '%')
        ->count();
    expect($semScope)->toBe(1, 'fixture cross-tenant não foi plantado — o verde abaixo seria tautológico');

    $crossTenantCount = NfeDfeRecebido::query()
        ->where('business_id', '!=', $bizMeu)
        ->count();
    expect($crossTenantCount)->toBe(0, 'Global scope deve esconder cross-tenant');

    $meus = NfeDfeRecebido::query()
        ->where('chave_44', 'like', '%' . DFE_TAG . '%')
        ->count();
    expect($meus)->toBe(1, 'a linha do próprio tenant tem que continuar visível');
});

it('UC-FDFE-02 · STATUS constants estão definidas — Controller depende delas pra filtros', function () {
    expect(NfeDfeRecebido::STATUS_PENDENTE)->toBe('pendente')
        ->and(NfeDfeRecebido::STATUS_CIENCIA)->toBe('ciencia')
        ->and(NfeDfeRecebido::STATUS_CONFIRMADA)->toBe('confirmada')
        ->and(NfeDfeRecebido::STATUS_DESCONHECIDA)->toBe('desconhecida')
        ->and(NfeDfeRecebido::STATUS_NAO_REALIZADA)->toBe('nao_realizada');
});

it('UC-FDFE-02 · isPendenteManifestacao retorna true pra status PENDENTE e CIENCIA', function () {
    $pendente = new NfeDfeRecebido(['status_manifestacao' => NfeDfeRecebido::STATUS_PENDENTE]);
    $ciencia  = new NfeDfeRecebido(['status_manifestacao' => NfeDfeRecebido::STATUS_CIENCIA]);
    $conf     = new NfeDfeRecebido(['status_manifestacao' => NfeDfeRecebido::STATUS_CONFIRMADA]);

    expect($pendente->isPendenteManifestacao())->toBeTrue()
        ->and($ciencia->isPendenteManifestacao())->toBeTrue()
        ->and($conf->isPendenteManifestacao())->toBeFalse();
});

/*
 * =====================================================================================
 * UC-FDFE-14/15/16 — prazo, chips e busca da listagem (2026-11-04)
 * =====================================================================================
 * Os três eram [BACKLOG · ⬜ sem teste] no Dfe.casos.md: o comportamento existia no
 * Controller e nenhum teste lia o resultado. Aqui o objeto sob teste é o payload que a tela
 * recebe (`buildRowsPayload`), chamado com um usuário COMUM autenticado no tenant fictício 98
 * (ADR 0358) — usuário comum, não superadmin, para o ScopeByBusiness filtrar só por
 * business_id e não somar as linhas de business NULL.
 *
 * O banco do CT 100 e o de cada lane podem ter linhas de outros testes. Por isso nenhum assert
 * conta o total: todos perguntam "esta linha MINHA está / não está no resultado", e as minhas
 * nascem com `data_emissao` à frente de qualquer fixture vizinha para caber na 1ª página.
 */
const DFE_LISTA_TAG = 'UCFDFE11A13';
const DFE_LISTA_BIZ = 98;

function dfeListaUsuario(): void
{
    \Illuminate\Support\Facades\Auth::setUser(new class extends \App\User
    {
        public function can($abilities, $arguments = []): bool
        {
            return false; // usuário comum: o escopo filtra só por business_id
        }
    });
    session(['user.business_id' => DFE_LISTA_BIZ, 'business.id' => DFE_LISTA_BIZ]);
}

/** Planta uma DF-e do tenant 98. `$sufixo` são 8 dígitos que identificam a linha na chave. */
function dfeListaSemear(string $sufixo, array $attrs = []): NfeDfeRecebido
{
    // SUPERADMIN: plantar o fixture sem depender do escopo — o escopo é o que a leitura testa
    return NfeDfeRecebido::withoutGlobalScopes()->create(array_merge([
        'business_id'          => DFE_LISTA_BIZ,
        'chave_44'             => '4299' . str_pad('', 32, '0') . $sufixo,
        'nsu'                  => (int) $sufixo,
        'cnpj_emitente'        => '12345678000199',
        'nome_emitente'        => 'FORNECEDOR ' . DFE_LISTA_TAG,
        'valor_total'          => 100,
        'data_emissao'         => now()->addYears(5),
        'status_manifestacao'  => NfeDfeRecebido::STATUS_PENDENTE,
        'prazo_confirmacao_em' => now()->addDays(60)->toDateString(),
    ], $attrs));
}

/** O que a tela recebe em `rows`, para um filtro de status e uma busca. */
function dfeListaPayload(string $status, string $search = ''): array
{
    $controller = new \Modules\Fiscal\Http\Controllers\DfeController();

    return (fn () => $this->buildRowsPayload(['status' => $status, 'search' => $search]))
        ->call($controller);
}

/** @return int[] */
function dfeListaIds(array $payload): array
{
    return array_map(fn (array $r) => (int) $r['id'], $payload['data']);
}

it('UC-FDFE-14 · prazoDias sai do prazo_confirmacao_em gravado pela SEFAZ, não de 90 dias fixos', function () {
    dfeListaUsuario();
    // Mesma data de emissão, prazos diferentes: se a tela contasse 90 dias a partir da
    // emissão, as duas linhas teriam o mesmo número.
    $curto = dfeListaSemear('11000001', ['prazo_confirmacao_em' => now()->addDays(5)->toDateString()]);
    $longo = dfeListaSemear('11000002', ['prazo_confirmacao_em' => now()->addDays(45)->toDateString()]);
    $vencido = dfeListaSemear('11000003', ['prazo_confirmacao_em' => now()->subDays(3)->toDateString()]);
    $semPrazo = dfeListaSemear('11000004', ['prazo_confirmacao_em' => null]);

    $porId = collect(dfeListaPayload('todas')['data'])->keyBy('id');

    expect($porId->has($curto->id))->toBeTrue('a linha do fixture não voltou — o resto seria verde por ausência');
    expect($porId[$curto->id]['prazoDias'])->toBe(5)
        ->and($porId[$longo->id]['prazoDias'])->toBe(45)
        ->and($porId[$vencido->id]['prazoDias'])->toBe(-3)
        ->and($porId[$semPrazo->id]['prazoDias'])->toBeNull();
});

it('UC-FDFE-15 · cada chip devolve só o seu estado; "pendentes" inclui a nota com ciência dada', function () {
    dfeListaUsuario();
    $pendente   = dfeListaSemear('12000001');
    $ciencia    = dfeListaSemear('12000002', ['status_manifestacao' => NfeDfeRecebido::STATUS_CIENCIA]);
    $confirmada = dfeListaSemear('12000003', ['status_manifestacao' => NfeDfeRecebido::STATUS_CONFIRMADA]);
    $desconhec  = dfeListaSemear('12000004', ['status_manifestacao' => NfeDfeRecebido::STATUS_DESCONHECIDA]);
    $naoReal    = dfeListaSemear('12000005', ['status_manifestacao' => NfeDfeRecebido::STATUS_NAO_REALIZADA]);
    $minhas = [$pendente->id, $ciencia->id, $confirmada->id, $desconhec->id, $naoReal->id];

    $esperado = [
        'pendentes'      => [$pendente->id, $ciencia->id],
        'confirmadas'    => [$confirmada->id],
        'desconhecidas'  => [$desconhec->id],
        'nao_realizadas' => [$naoReal->id],
        'todas'          => $minhas,
    ];

    foreach ($esperado as $chip => $ids) {
        $vistas = array_values(array_intersect(dfeListaIds(dfeListaPayload($chip)), $minhas));
        sort($vistas);
        sort($ids);
        expect($vistas)->toBe($ids, "o chip '{$chip}' devolveu as linhas erradas");
    }
});

it('UC-FDFE-16 · a busca acha pela chave, pelo CNPJ digitado com pontuação e pelo nome do emitente', function () {
    dfeListaUsuario();
    $lona = dfeListaSemear('13000001', [
        'cnpj_emitente' => '11222333000144',
        'nome_emitente' => 'LONAFICT DISTRIBUIDORA ' . DFE_LISTA_TAG,
    ]);
    $tinta = dfeListaSemear('13000002', [
        'cnpj_emitente' => '55666777000188',
        'nome_emitente' => 'TINTAFICT INDUSTRIA ' . DFE_LISTA_TAG,
    ]);
    $minhas = [$lona->id, $tinta->id];
    $achadas = fn (string $busca) => array_values(array_intersect(dfeListaIds(dfeListaPayload('todas', $busca)), $minhas));

    expect($achadas('13000002'))->toBe([$tinta->id], 'busca por trecho da chave');
    expect($achadas('11.222.333/0001-44'))->toBe([$lona->id], 'busca por CNPJ com pontuação');
    expect($achadas('LONAFICT'))->toBe([$lona->id], 'busca pelo nome do emitente trouxe nota de outro emitente');
});
