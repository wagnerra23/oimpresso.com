<?php

declare(strict_types=1);

// @covers-us US-NFSE-006 US-NFSE-008 US-NFSE-009
// Contrato das 3 telas: resources/js/Pages/Nfse/{Index,Emitir,Show}.casos.md
//   UC-NFSL-01..03 (lista) · UC-NFSEM-01..03 (emitir) · UC-NFSD-01..04 (detalhe).
// Os casos derivam dos charters + SPEC (US-NFSE-006/008/009), NUNCA dos .tsx — teste derivado
// do código é tautológico (proibicoes.md §5 2026-06-05).

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Validator;
use Modules\NFSe\Http\Requests\CancelarNfseRequest;
use Modules\NFSe\Http\Requests\IndexNfseRequest;
use Modules\NFSe\Http\Requests\StoreNfseRequest;
use Modules\NFSe\Models\NfseEmissao;

uses(Tests\TestCase::class);

/**
 * POR QUE DB-LESS
 * ---------------
 * Este arquivo roda na lane sqlite per-PR (`.github/ci-sqlite-pest.list`), que NÃO migra o
 * schema. Por isso nenhum caso aqui toca tabela: o isolamento Tier 0 é provado no SQL que o
 * Eloquent MONTA (`toSql()` + bindings), as regras fiscais no `Validator` com as rules reais do
 * FormRequest, e a autorização pelo `authorize()` do próprio FormRequest com um usuário-dublê.
 * Nenhum desses caminhos executa query — logo nenhum deles pode virar skip-as-pass.
 *
 * Tenant fictício 98 (ADR 0358). NUNCA biz=4 (ROTA LIVRE, cliente real).
 *
 * Todo caso negativo vem com CONTROLE POSITIVO: "rejeitou" sem "aceitou o válido" pode ser só
 * uma regra que rejeita tudo (verde por construção).
 */

function nfstelasBiz(): int
{
    return 98;
}

/** Usuário-dublê: `can()` responde SÓ à habilidade concedida — prova QUAL gate o request pede. */
function nfstelasUser(?string $concedida): object
{
    return new class($concedida) {
        public function __construct(private readonly ?string $concedida) {}

        public function can($ability, $arguments = []): bool
        {
            return $ability === $this->concedida;
        }
    };
}

/** SQL sem aspas de identificador — o mesmo assert vale em sqlite (") e MySQL (`). */
function nfstelasSql($query): string
{
    return str_replace(['"', '`'], '', $query->toSql());
}

function nfstelasEmissaoValida(array $over = []): array
{
    return array_merge([
        'competencia'    => '2026-09',
        'tomador_nome'   => 'Tomador Ficticio Ltda',
        'tomador_email'  => 'tomador@example.test',
        'descricao'      => 'Servico de impressao',
        'lc116_codigo'   => '13.05',
        'valor_servicos' => 100.00,
        'aliquota_iss'   => 0.05,
        'iss_retido'     => false,
    ], $over);
}

beforeEach(function () {
    session(['user.business_id' => nfstelasBiz()]);
});

// ---------------------------------------------------------------------------------------
// Lista — resources/js/Pages/Nfse/Index.casos.md
// ---------------------------------------------------------------------------------------

it('UC-NFSL-01 · a consulta da listagem sai filtrada pelo business da sessao [T0]', function () {
    $query = NfseEmissao::query();

    // Controle positivo: o SQL montado carrega a coluna de tenant qualificada pela tabela…
    expect(nfstelasSql($query))->toContain('nfse_emissoes.business_id = ?');
    // …e o valor ligado é o tenant DA SESSÃO, não um literal.
    expect($query->getBindings())->toContain(nfstelasBiz());

    // Troca de tenant na sessão troca o binding — o scope lê a sessão a cada consulta.
    session(['user.business_id' => 97]);
    $outra = NfseEmissao::query()->getBindings();
    expect($outra)->toContain(97);
    expect($outra)->not->toContain(nfstelasBiz());
});

it('UC-NFSL-02 · filtros da lista aceitam so status conhecido, data Y-m-d e busca curta', function () {
    $rules = (new IndexNfseRequest())->rules();

    // Controle positivo: combinação válida passa.
    expect(Validator::make(
        ['status' => 'erro', 'de' => '2026-01-01', 'ate' => '2026-09-30', 'q' => 'Tomador'],
        $rules
    )->passes())->toBeTrue();
    expect(Validator::make([], $rules)->passes())->toBeTrue();

    // Negativos — cada um isolado, pra o vermelho apontar a regra certa.
    expect(Validator::make(['status' => 'inventado'], $rules)->errors()->has('status'))->toBeTrue();
    expect(Validator::make(['de' => '01/09/2026'], $rules)->errors()->has('de'))->toBeTrue();
    expect(Validator::make(['de' => '2026-09-30', 'ate' => '2026-09-01'], $rules)->errors()->has('ate'))->toBeTrue();
    expect(Validator::make(['q' => str_repeat('a', 121)], $rules)->errors()->has('q'))->toBeTrue();
    expect(Validator::make(['q' => str_repeat('a', 120)], $rules)->passes())->toBeTrue();
});

it('UC-NFSL-03 · a listagem exige sessao autenticada e e somente leitura', function () {
    $rota = Route::getRoutes()->getByName('nfse.index');
    expect($rota)->not->toBeNull();

    expect($rota->gatherMiddleware())->toContain('auth');
    // Só leitura: GET (e o HEAD implícito). Nenhum verbo que grave.
    expect($rota->methods())->toEqualCanonicalizing(['GET', 'HEAD']);
});

// ---------------------------------------------------------------------------------------
// Emitir — resources/js/Pages/Nfse/Emitir.casos.md
// ---------------------------------------------------------------------------------------

it('UC-NFSEM-01 · emissao exige competencia, tomador, descricao, codigo LC 116, valor e aliquota', function () {
    $rules = (new StoreNfseRequest())->rules();

    // Controle positivo.
    expect(Validator::make(nfstelasEmissaoValida(), $rules)->passes())->toBeTrue();

    foreach (['competencia', 'tomador_nome', 'descricao', 'lc116_codigo', 'valor_servicos', 'aliquota_iss'] as $campo) {
        $dados = nfstelasEmissaoValida();
        unset($dados[$campo]);
        expect(Validator::make($dados, $rules)->errors()->has($campo))->toBeTrue();
    }

    // Competência é mês fiscal (Y-m), não data cheia.
    expect(Validator::make(nfstelasEmissaoValida(['competencia' => '2026-09-15']), $rules)->errors()->has('competencia'))->toBeTrue();
});

it('UC-NFSEM-02 · aliquota de ISS e fracao entre 0 e 1 e o valor dos servicos e positivo', function () {
    $rules = (new StoreNfseRequest())->rules();

    // 5% chega como 0.05. "5" (percentual digitado cru) é rejeitado — evita ISS 100× maior.
    expect(Validator::make(nfstelasEmissaoValida(['aliquota_iss' => 0.05]), $rules)->passes())->toBeTrue();
    expect(Validator::make(nfstelasEmissaoValida(['aliquota_iss' => 5]), $rules)->errors()->has('aliquota_iss'))->toBeTrue();
    expect(Validator::make(nfstelasEmissaoValida(['aliquota_iss' => -0.01]), $rules)->errors()->has('aliquota_iss'))->toBeTrue();

    expect(Validator::make(nfstelasEmissaoValida(['valor_servicos' => 0.01]), $rules)->passes())->toBeTrue();
    expect(Validator::make(nfstelasEmissaoValida(['valor_servicos' => 0]), $rules)->errors()->has('valor_servicos'))->toBeTrue();
});

it('UC-NFSEM-03 · so quem tem a permissao nfse.emit envia a emissao', function () {
    $req = new StoreNfseRequest();

    $req->setUserResolver(fn () => null);
    expect($req->authorize())->toBeFalse();

    // A permissão vizinha NÃO serve — prova que o request pede exatamente nfse.emit.
    $req->setUserResolver(fn () => nfstelasUser('nfse.view'));
    expect($req->authorize())->toBeFalse();

    // Controle positivo.
    $req->setUserResolver(fn () => nfstelasUser('nfse.emit'));
    expect($req->authorize())->toBeTrue();

    $rota = Route::getRoutes()->getByName('nfse.store');
    expect($rota)->not->toBeNull();
    expect($rota->gatherMiddleware())->toContain('auth');
});

// ---------------------------------------------------------------------------------------
// Detalhe — resources/js/Pages/Nfse/Show.casos.md
// ---------------------------------------------------------------------------------------

it('UC-NFSD-01 · o id da URL so resolve nota do business da sessao [T0]', function () {
    $model = new NfseEmissao();
    $query = $model->resolveRouteBindingQuery(NfseEmissao::query(), 12345);

    $sql = nfstelasSql($query);
    expect($sql)->toContain('nfse_emissoes.business_id = ?');
    // `id` solto (não o sufixo de business_id) — lookbehind impede o falso casamento.
    expect($sql)->toMatch('/(?<!\w)id = \?/');
    expect($query->getBindings())->toContain(nfstelasBiz());
    expect($query->getBindings())->toContain(12345);
});

it('UC-NFSD-02 · cancelar exige motivo entre 15 e 255 caracteres', function () {
    $rules = (new CancelarNfseRequest())->rules();

    expect(Validator::make([], $rules)->errors()->has('motivo'))->toBeTrue();
    expect(Validator::make(['motivo' => str_repeat('x', 14)], $rules)->errors()->has('motivo'))->toBeTrue();
    expect(Validator::make(['motivo' => str_repeat('x', 256)], $rules)->errors()->has('motivo'))->toBeTrue();

    // Controle positivo nas duas bordas.
    expect(Validator::make(['motivo' => str_repeat('x', 15)], $rules)->passes())->toBeTrue();
    expect(Validator::make(['motivo' => str_repeat('x', 255)], $rules)->passes())->toBeTrue();
});

it('UC-NFSD-03 · so quem tem a permissao nfse.cancel cancela, e so por POST', function () {
    $req = new CancelarNfseRequest();

    $req->setUserResolver(fn () => null);
    expect($req->authorize())->toBeFalse();

    $req->setUserResolver(fn () => nfstelasUser('nfse.view'));
    expect($req->authorize())->toBeFalse();

    $req->setUserResolver(fn () => nfstelasUser('nfse.cancel'));
    expect($req->authorize())->toBeTrue();

    $rota = Route::getRoutes()->getByName('nfse.cancelar');
    expect($rota)->not->toBeNull();
    expect($rota->methods())->toBe(['POST']);
    expect($rota->gatherMiddleware())->toContain('auth');
});

it('UC-NFSD-04 · emitida, cancelada e erro tem rotulo e cor distintos', function () {
    $estados = [];
    foreach (['emitida', 'cancelada', 'erro'] as $status) {
        $n = new NfseEmissao(['status' => $status]);
        $estados[$status] = [$n->statusLabel(), $n->statusColor()];
    }

    expect($estados['emitida'])->toBe(['Emitida', 'success']);
    expect($estados['cancelada'])->toBe(['Cancelada', 'warning']);
    expect($estados['erro'])->toBe(['Erro', 'danger']);

    // Três cores diferentes — o operador distingue o destino da nota sem ler o texto.
    expect(array_unique(array_column($estados, 1)))->toHaveCount(3);
});
