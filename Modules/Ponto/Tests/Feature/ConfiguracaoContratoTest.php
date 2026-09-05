<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Modules\Ponto\Tests\Feature\PontoTestCase;

uses(PontoTestCase::class);

/**
 * Contrato das duas telas de configuração do Ponto:
 *   - `/ponto/configuracoes`       → Configuracoes/Index.casos.md (UC-CFGIDX-01)
 *   - `/ponto/configuracoes/reps`  → Configuracoes/Reps.casos.md  (UC-CFGREP-01..05)
 *
 * Cada teste cita o UC no TÍTULO do `it()` — é o que o manifesto G-7 alcança.
 *
 * Os UC derivam da Portaria MTP 671/2021 Anexo I + `CU-PONTO-12` (SDD §6.5) + ADR 0093 +
 * proibicoes.md (segredo fora de superfície que o cliente lê). NÃO do `.tsx`.
 *
 * ── Sobre o UC-CFGIDX-01 ───────────────────────────────────────────────────────────────
 * Ele nasce de um ACHADO medido, não de hipótese: antes do conserto que vem no mesmo PR, o
 * `@index` fazia `Inertia::render(…, ['config' => config('pontowr2')])` e o config carrega
 * `rep.certificado_icp_pass`. Sonda no CT 100 com sentinela: a senha aparecia no corpo da
 * resposta, status 200, pra qualquer usuário com `ponto.access`.
 *
 * `assertStringNotContainsString` e NÃO `expect()->not->toContain($x, $msg)`: o `toContain` do
 * Pest recebe MÚLTIPLOS needles, então a mensagem viraria um 2º needle e o `not` passaria
 * sempre (§5 proibicoes 2026-07-28 — e a classe reapareceu no arquivo irmão desta leva).
 *
 * Tier 0: adversário é o biz fictício 99 via `garantirBizAlheio()`, NUNCA biz=4 (ADR 0358).
 * Sem `RefreshDatabase` — a lane ponto-pest proíbe.
 *
 * @see \Modules\Ponto\Http\Controllers\ConfiguracaoController
 */

const CFG_MARCA = 'SDD-CFG-CONTRATO';

/** Sentinelas de segredo — valores que NÃO podem sair no payload da tela. */
const CFG_SENHA_SENTINELA   = 'SENHA-ICP-SENTINELA-NAO-PODE-VAZAR';
const CFG_CAMINHO_SENTINELA = '/caminho/sentinela/certificado.pfx';

function cfgPrecisaDe(array $tabelas): void
{
    foreach ($tabelas as $t) {
        if (! Schema::hasTable($t)) {
            test()->markTestSkipped("Tabela {$t} ausente — schema do Ponto não migrado nesta lane.");
        }
    }
}

/**
 * Identificador no formato do Anexo I: CNPJ (14) + sequencial (3) = 17 caracteres.
 *
 * Gerado por sorteio pra não colidir com REP real da base — a unicidade é GLOBAL nesta
 * tabela (ver o [BACKLOG] do Reps.casos.md), então um valor fixo quebraria na 2ª execução
 * contra uma base que persiste, como a do CT 100.
 */
function cfgIdentificador(): string
{
    return str_pad((string) random_int(0, 99999999999999), 14, '0', STR_PAD_LEFT)
        . str_pad((string) random_int(0, 999), 3, '0', STR_PAD_LEFT);
}

afterEach(function () {
    try {
        DB::table('ponto_reps')->where('descricao', 'like', CFG_MARCA . '%')->delete();
    } catch (\Throwable $e) {
        // schema ausente — cleanup best-effort, igual aos irmãos do módulo.
    }
});

// =====================================================================
// Configuracoes/Index — o painel de parâmetros
// =====================================================================

it('UC-CFGIDX-01 · o painel de parâmetros não entrega ao browser a senha do certificado ICP', function () {
    $this->actAsAdmin();

    // O certificado CONFIGURADO é a pré-condição do caso: sem valor definido, "não vazou"
    // seria verdade porque não há o que vazar (LC-13 — verde por não-execução).
    config([
        'pontowr2.rep.certificado_icp_pass' => CFG_SENHA_SENTINELA,
        'pontowr2.rep.certificado_icp_path' => CFG_CAMINHO_SENTINELA,
    ]);

    $resp = $this->inertiaGet('/ponto/configuracoes');
    $resp->assertStatus(200);
    $corpo = $resp->getContent();

    $this->assertStringNotContainsString(
        CFG_SENHA_SENTINELA,
        $corpo,
        'A senha do certificado ICP-Brasil NÃO pode viajar no payload da tela. Prop do Inertia vai '
        . 'inteira no HTML servido ao browser, e quem tem essa senha assina marcação de ponto em '
        . 'nome do empregador (PKCS#7 A1). Filtrar no TypeScript não resolve: o dado já viajou.'
    );

    $this->assertStringNotContainsString(
        CFG_CAMINHO_SENTINELA,
        $corpo,
        'O caminho do certificado também não sai — ele diz onde o arquivo mora no servidor.'
    );

    $this->assertStringNotContainsString(
        'certificado_icp_pass',
        $corpo,
        'Nem o NOME da chave deve aparecer: ele anuncia que existe uma senha de certificado e '
        . 'em que bloco de configuração procurá-la.'
    );

    // A outra metade: a tela tem de continuar recebendo o que ela existe pra mostrar. Sem isto,
    // "não vazou" passaria também num controller que parou de mandar configuração nenhuma.
    $clt = $resp->json('props.config.clt');
    expect($clt)->not->toBeEmpty(
        'O bloco de parâmetros CLT tem de continuar chegando — o painel existe pra mostrar as '
        . 'tolerâncias dos Art. 58/59/66/71/73.'
    );

    // ⚠️ `array_key_exists` + `toBeTrue($msg)`, e NÃO `toHaveKey($chave, $msg)`: o 2º argumento
    // de `toHaveKey` é o VALOR esperado, não mensagem — a armadilha que o próprio
    // `ponto-pest.yml` documenta no ratchet de 2026-08-24, e na qual esta linha caiu antes.
    //
    // A chave escolhida é `intrajornada_minima_minutos` (Art. 71) porque ela é uma das únicas
    // DUAS, de 15 que a tela lê, que de fato existem em `Modules/Ponto/Config/config.php` —
    // as outras 13 são fantasma e estão registradas como [BACKLOG] no casos.md desta tela.
    expect(array_key_exists('intrajornada_minima_minutos', (array) $clt))->toBeTrue(
        'A intrajornada mínima (Art. 71) é um dos parâmetros que o painel promete exibir, e é '
        . 'uma das duas chaves que a tela lê e que realmente existem na configuração.'
    );
});

// =====================================================================
// Configuracoes/Reps — o cadastro de dispositivos
// =====================================================================

it('UC-CFGREP-01 · a lista de REPs não mostra dispositivo de outro empregador', function () {
    $this->actAsAdmin();
    cfgPrecisaDe(['ponto_reps']);

    $alheio        = $this->garantirBizAlheio();
    $identAlheio   = cfgIdentificador();
    $descricaoAlheia = CFG_MARCA . '-REP-DE-OUTRO-EMPREGADOR';

    DB::table('ponto_reps')->insert([
        'id'            => (string) Str::uuid(),
        'business_id'   => $alheio,
        'tipo'          => 'REP_P',
        'identificador' => $identAlheio,
        'descricao'     => $descricaoAlheia,
        'created_at'    => now(),
        'updated_at'    => now(),
    ]);

    // Pré-condição anti-vácuo.
    expect(DB::table('ponto_reps')->where('identificador', $identAlheio)->exists())
        ->toBeTrue('O REP do empregador adversário tem de existir — senão o caso não exerce isolamento.');

    $resp = $this->inertiaGet('/ponto/configuracoes/reps');
    $resp->assertStatus(200);
    $corpo = $resp->getContent();

    $this->assertStringNotContainsString(
        $identAlheio,
        $corpo,
        'O identificador de REP de OUTRO empregador não pode aparecer na lista — ele carrega o '
        . 'CNPJ de quem registrou o dispositivo (CU-PONTO-12 · ADR 0093).'
    );

    $this->assertStringNotContainsString(
        $descricaoAlheia,
        $corpo,
        'Nem a descrição do REP alheio.'
    );

    $this->removerBizAlheio();
});

it('UC-CFGREP-02 · identificador fora do formato da Portaria é recusado', function () {
    $this->actAsAdmin();
    cfgPrecisaDe(['ponto_reps']);

    $antes = DB::table('ponto_reps')->where('descricao', 'like', CFG_MARCA . '%')->count();

    $resp = $this->post('/ponto/configuracoes/reps', [
        'tipo'          => 'REP_P',
        'identificador' => 'CURTO-DEMAIS',            // 12 caracteres, não 17
        'descricao'     => CFG_MARCA . '-fora-do-formato',
    ]);

    // ⚠️ NÃO usar `expect($resp->isSuccessful())->toBeFalse(...)` aqui, e a razão é medida:
    // `isSuccessful()` é 200..299 (Symfony `Response`), e o caminho de SUCESSO deste endpoint
    // é `return back()` — 302. Ou seja o predicado dá `false` na recusa E no cadastro que
    // GRAVOU: passa sempre, sem separar os dois caminhos. Sonda no CT 100:
    //   storeRep sucesso → status=302 isSuccessful=false gravou=true
    //   storeRep recusa  → status=302 isSuccessful=false
    // A versão anterior deste caso abria com esse assert e o comentário o defendia como
    // escolha deliberada ("não cravar status") — ele parecia o guarda-costas do caso e não
    // era. Quem de fato prova a recusa são os três asserts abaixo: o erro chega ao operador,
    // no campo certo, e a tabela não ganha linha.
    // Família: §5 2026-09-05 (bite-test) — contagem/veredito idêntico ao do caso honesto.
    $erros = session('errors');
    expect($erros)->not->toBeNull('A recusa tem de chegar ao operador como erro de formulário.');
    expect($erros->has('identificador'))->toBeTrue(
        'O erro tem de apontar o campo do identificador — sem isso o operador não sabe o que corrigir.'
    );

    // A terceira metade: só "não foi sucesso" passaria também num 500 que já tivesse gravado.
    $depois = DB::table('ponto_reps')->where('descricao', 'like', CFG_MARCA . '%')->count();
    expect($depois)->toBe($antes,
        'A tentativa recusada não pode ter criado REP nenhum.'
    );
});

it('UC-CFGREP-03 · a lista traz o REP do meu empregador com a identificação dele', function () {
    $this->actAsAdmin();
    cfgPrecisaDe(['ponto_reps']);

    $ident = cfgIdentificador();
    $descricao = CFG_MARCA . '-REP-PROPRIO';

    DB::table('ponto_reps')->insert([
        'id'            => (string) Str::uuid(),
        'business_id'   => (int) $this->business->id,
        'tipo'          => 'REP_C',
        'identificador' => $ident,
        'descricao'     => $descricao,
        'created_at'    => now(),
        'updated_at'    => now(),
    ]);

    $resp = $this->inertiaGet('/ponto/configuracoes/reps');
    $resp->assertStatus(200);

    $linhas = collect($resp->json('props.reps.data') ?? []);

    // Pré-condição anti-vácuo: lista vazia faria os asserts abaixo passarem por
    // não-execução, não por preservação (LC-13).
    expect($linhas)->not->toBeEmpty('A lista de REPs veio vazia — o caso não exerceu nada.');

    $minha = $linhas->firstWhere('identificador', $ident);

    expect($minha)->not->toBeNull(
        'O REP do meu business tem de aparecer na lista (charter §Mission).'
    );
    // Identidade, não só a linha: o `@reps` monta cada linha por `transform()` campo a
    // campo. Um campo removido de lá não quebra a query nem o 200 — a linha chega SEM o
    // dado, e o identificador é justamente o que amarra o equipamento ao registro perante
    // a fiscalização (Portaria 671/2021 Anexo I). O irmão UC-CFGREP-01 prova que o
    // ALHEIO não entra; este prova que o PRÓPRIO chega inteiro — sem ele, "a lista está
    // correta" seria verdade também com a lista sempre vazia.
    expect($minha['tipo'] ?? null)->toBe('REP_C',
        'A linha tem de trazer o tipo do REP — é a classificação legal do equipamento.'
    );
    expect($minha['descricao'] ?? null)->toBe($descricao,
        'A linha tem de trazer a descrição cadastrada, não um placeholder.'
    );
});

it('UC-CFGREP-04 · REP cadastrado nasce no meu empregador, não no que veio na requisição', function () {
    $this->actAsAdmin();
    cfgPrecisaDe(['ponto_reps']);

    $alheio = $this->garantirBizAlheio();
    $ident  = cfgIdentificador();

    // Controle negativo: manda `business_id` ALHEIO no corpo do POST, de propósito.
    $resp = $this->post('/ponto/configuracoes/reps', [
        'tipo'          => 'REP_A',
        'identificador' => $ident,
        'descricao'     => CFG_MARCA . '-escrita-cross-tenant',
        'business_id'   => $alheio,
    ]);

    $resp->assertSessionHasNoErrors();

    $criado = DB::table('ponto_reps')->where('identificador', $ident)->first();

    expect($criado)->not->toBeNull(
        'O cadastro válido tem de gravar — senão o caso não exerceu a atribuição de tenant.'
    );
    expect((int) $criado->business_id)->toBe((int) $this->business->id,
        'O REP tem de nascer no MEU business, nunca no que veio no corpo da requisição. '
        . 'Isolamento de LEITURA (UC-CFGREP-01) e de ESCRITA são propriedades diferentes: uma '
        . 'lista escopada certo convive com um `create` que grava no tenant errado, e o registro '
        . 'errado só aparece quando o OUTRO empregador abrir a tela dele (ADR 0093 · CU-PONTO-12).'
    );

    $this->removerBizAlheio();
});

it('UC-CFGREP-05 · tipo fora de REP-P/C/A é recusado', function () {
    $this->actAsAdmin();
    cfgPrecisaDe(['ponto_reps']);

    $antes = DB::table('ponto_reps')->where('descricao', 'like', CFG_MARCA . '%')->count();
    $ident = cfgIdentificador();

    $resp = $this->post('/ponto/configuracoes/reps', [
        'tipo'          => 'REP_X',                   // fora do enum taxativo da Portaria
        'identificador' => $ident,
        'descricao'     => CFG_MARCA . '-tipo-invalido',
    ]);

    // ⚠️ NADA de `expect($resp->isSuccessful())->toBeFalse(...)` aqui, e o motivo é medido:
    // `isSuccessful()` é `200..299` (Symfony `Response::isSuccessful`), e o caminho de SUCESSO
    // deste endpoint é `return back()`, que é **302**. Ou seja, o assert daria `false` tanto na
    // recusa quanto no cadastro bem-sucedido — passaria sempre, sem discriminar nada. Quem
    // separa os dois caminhos aqui é a sessão de erros e a contagem de registros, abaixo.
    // (Confirmado na fonte do vendor, não por memória. O mesmo vale pro irmão UC-CFGREP-02.)
    $erros = session('errors');
    expect($erros)->not->toBeNull('A recusa tem de chegar ao operador como erro de formulário.');
    expect($erros->has('tipo'))->toBeTrue(
        'O erro tem de apontar o campo do tipo.'
    );

    // A regra `in:` do controller é DEFESA ÚNICA aqui, e isso foi medido: o `sql_mode` da
    // lane não tem STRICT, então a coluna `enum('REP_P','REP_C','REP_A')` converteria um
    // valor fora da lista em STRING VAZIA em vez de recusar. O REP nasceria aceito, listado
    // e sem classificação legal. Por isso o caso asserta também a ausência do registro.
    $depois = DB::table('ponto_reps')->where('descricao', 'like', CFG_MARCA . '%')->count();
    expect($depois)->toBe($antes,
        'A tentativa recusada não pode ter criado REP nenhum.'
    );
});
