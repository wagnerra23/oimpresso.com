<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Ponto\Entities\Importacao;
use Modules\Ponto\Tests\Feature\PontoTestCase;

uses(PontoTestCase::class);

/**
 * Contrato do detalhe da importação (`/ponto/importacoes/{id}`) — o caso do ERRO.
 *
 * Cada teste cita o UC no TÍTULO do `it()` (G-2 do casos-gate, ADR 0264):
 *   Importacoes/Show.casos.md → UC-IMPSH-05
 *
 * ── O UC deriva do CONTRATO, nunca do `.tsx` ─────────────────────────────────────
 * Teste derivado do código é tautológico (proibicoes §5 2026-06-05). As fontes:
 *   · SDD §6.4 `CU-PONTO-11` — "A importação mostra estado, contagens fiéis ao que foi
 *     processado, **erro quando houver**, e permite baixar o arquivo original".
 *   · SDD §5.3 F7 — "`Importacoes/Show` acompanha `estado`, …, `erro_mensagem`".
 *   · US-PONTO-002 (aceitação: "registra arquivo + checksum + linhas processadas + **erros**").
 *   · `Show.charter.md` §Goals — "Alerta de erro com `erro_mensagem` quando o processamento
 *     falha".
 *   · Portaria MTP 671/2021 Anexo I — a importação é a origem rastreável da marcação; uma
 *     que falhou em silêncio quebra a cadeia no pior momento possível.
 *
 * ── Por que arquivo NOVO, e não dentro do `BancoHorasImportacaoContratoTest` ─────
 * Aquele é classe PHPUnit e cita UC-IMPSH-01..04 em DOCBLOCK. Método PHP não aceita
 * hífen, então o `name` do `<testcase>` sai `uc_impshow_04_…` e o UC nunca chega ao
 * manifesto do G-7 — medido: UC-IMPSH-01..04 estão na lista `⛓` do `casos:report`
 * (10 do Ponto, todos por formato de nome). Escrever o 05 lá dentro herdaria o defeito.
 * Converter aquele arquivo é dívida alheia e acorda gate diff-aware (proibicoes §5
 * 2026-07-12); o próprio `ponto-pest.yml` registra que a conversão dele é oportunística.
 *
 * ── Por que o assert NÃO é sobre a chave `erro_mensagem` ────────────────────────
 * O contrato é "a importação que falhou diz por quê" — não "existe a chave X". Há mais de
 * uma correção legítima (renomear a leitura, accessor, `$appends`), e assert por chave
 * literal reprovaria as outras; é a mesma nota que o UC-IMPSH-04 já carrega. Então o caso
 * procura o MOTIVO GRAVADO em qualquer chave do payload e exige que ele chegue como
 * STRING NÃO-VAZIA — a única forma que o `{i.erro_mensagem && <Alert>…}` consegue
 * renderizar como texto.
 *
 * Tier 0: biz=1 (WR2 interno) — NUNCA biz=4 (ROTA LIVRE, ADR 0101).
 * Sem RefreshDatabase: a lane ponto-pest proíbe (dropa schema + limpa o seed).
 *
 * Contrato: resources/js/Pages/Ponto/Importacoes/Show.casos.md
 *
 * Este arquivo cobre a 4ª e última instância de atributo fantasma da US-PONTO-012
 * (`erro_mensagem`). As outras três já têm teste próprio, listados no `Implementado em:`
 * da US: `EspelhoContratoTest` (UC-ESPSH-01), `EscalaFormContratoTest` (UC-ESCF-01),
 * `ImportacaoIndexContratoTest` + `BancoHorasImportacaoContratoTest` (UC-IMPIDX-03/UC-IMPSH-04).
 *
 * @covers-us US-PONTO-012
 *
 * @see \Modules\Ponto\Http\Controllers\ImportacaoController::show
 */

const IMPSH_MARCADOR = 'SDD-IMPSH-CONTRATO';

function impShPrecisaDe(array $tabelas): void
{
    foreach ($tabelas as $t) {
        if (! Schema::hasTable($t)) {
            test()->markTestSkipped("Tabela {$t} ausente — schema do Ponto não migrado nesta lane.");
        }
    }
}

/**
 * `usuario_id` é NOT NULL na migration — omitir derruba o INSERT antes de o caso exercer
 * qualquer coisa. `hash_arquivo` é único por `(business_id, hash_arquivo)`, daí o uniqid.
 */
function impShCriar(int $businessId, int $usuarioId, array $extra = []): Importacao
{
    $imp = new Importacao();
    $imp->forceFill(array_merge([
        'business_id'        => $businessId,
        'usuario_id'         => $usuarioId,
        'tipo'               => 'AFD',
        'nome_arquivo'       => IMPSH_MARCADOR . '-' . uniqid() . '.txt',
        'arquivo_path'       => "ponto/importacoes/{$businessId}/fixture.txt",
        'hash_arquivo'       => hash('sha256', uniqid('', true)),
        'tamanho_bytes'      => 2048,
        'estado'             => Importacao::ESTADO_CONCLUIDA,
        'linhas_total'       => 0,
        'linhas_processadas' => 0,
        'linhas_sucesso'     => 0,
        'linhas_erro'        => 0,
    ], $extra))->save();

    return $imp;
}

afterEach(function () {
    try {
        DB::table('ponto_importacoes')
            ->where('nome_arquivo', 'like', IMPSH_MARCADOR . '%')
            ->delete();
    } catch (\Throwable $e) {
        // schema ausente — cleanup best-effort
    }
});

// =====================================================================
// Importacoes/Show
// =====================================================================

it('UC-IMPSH-05 · a importação que falhou mostra o motivo da falha', function () {
    $this->actAsAdmin();
    impShPrecisaDe(['ponto_importacoes']);

    // Estado do mundo: é EXATAMENTE o que o produtor grava quando o processamento morre —
    // `ProcessarImportacaoAfdJob` (estado FALHOU + `log` = "Falha no job: …") e
    // `AfdParserService` ("Não foi possível abrir o arquivo: …"). A fixture reproduz o
    // REGISTRO, não o caminho: o UC é da tela, não do parser.
    $motivo = 'Falha no job: AFD rejeitado — NSR fora de sequencia na linha 42 [' . IMPSH_MARCADOR . ']';

    $falhou = impShCriar($this->business->id, $this->admin->id, [
        'estado'             => Importacao::ESTADO_FALHOU,
        'linhas_total'       => 120,
        'linhas_processadas' => 41,
        'log'                => $motivo,
    ]);

    // Pré-condição anti-vácuo (LC-13): sem o motivo GRAVADO, "a tela não mostra o motivo"
    // seria verdade por não haver motivo nenhum — o caso passaria a medir a fixture.
    //
    // ⚠️ Assert do PHPUnit, e não `expect()->toContain($motivo, 'mensagem')`: os matchers
    // variádicos do Pest tratam TODO argumento como needle, então a "mensagem" vira um
    // segundo termo a procurar e o caso reprova por si mesmo. É a mesma armadilha que o
    // `ponto-pest.yml` já registra pro `toHaveKey(chave, msg)` (causa 4 do ratchet de
    // 2026-08-24) e que a proibicoes §5 2026-07-28 cataloga pro `toContain`. Nos asserts do
    // PHPUnit a mensagem tem posição própria — ambiguidade zero.
    $gravado = DB::table('ponto_importacoes')->where('id', $falhou->id)->first();
    $this->assertStringContainsString(
        $motivo,
        (string) ($gravado->log ?? ''),
        'A fixture precisa gravar o motivo da falha na importação — senão o caso não exerce nada.'
    );

    $resp = $this->inertiaGet("/ponto/importacoes/{$falhou->id}");
    $this->assertInertiaComponent($resp, 'Ponto/Importacoes/Show');

    $payload = $resp->json('props.importacao');
    $this->assertIsArray($payload, 'O detalhe da importação precisa chegar como payload Inertia.');

    // O motivo é procurado em QUALQUER chave — o contrato é "diz por quê", não "expõe a
    // chave `erro_mensagem`". Os dois modos de falha ficam separados pra mensagem ser
    // diagnóstica em vez de só vermelha.
    $chavesRenderizaveis = [];
    $chavesNaoRenderizaveis = [];
    foreach ($payload as $chave => $valor) {
        if (is_string($valor)) {
            if ($valor !== '' && str_contains($valor, $motivo)) {
                $chavesRenderizaveis[] = $chave;
            }
            continue;
        }
        if ($valor !== null && str_contains((string) json_encode($valor), $motivo)) {
            $chavesNaoRenderizaveis[] = $chave;
        }
    }

    $this->assertNotEmpty(
        $chavesRenderizaveis,
        'A importação FALHOU com motivo gravado, e o detalhe não entrega esse motivo como texto '
        . 'renderizável — o operador vê uma importação quebrada sem saber por quê (CU-PONTO-11: '
        . '"erro quando houver" · charter §Goals: "alerta de erro quando o processamento falha"). '
        . 'Chaves do payload: [' . implode(', ', array_keys($payload)) . ']. '
        . (empty($chavesNaoRenderizaveis)
            ? 'O motivo não chegou em NENHUMA chave: é o sintoma de leitura de atributo fantasma '
              . '(a coluna real é `log`) — SDD §9 D-8, a mesma classe do UC-IMPSH-04.'
            : 'O motivo chegou em [' . implode(', ', $chavesNaoRenderizaveis) . '] mas NÃO como '
              . 'string. O alerta da tela é `{erro_mensagem && <Alert>{erro_mensagem}</Alert>}`: '
              . 'array vazio é truthy em JS (o alerta abriria sempre) e objeto não renderiza como '
              . 'texto. Se a correção passar a expor lista de erros, mude a tela e este assert JUNTOS.')
    );

    // Controle negativo — a outra metade do "QUANDO o processamento falha": importação sem
    // falha não pode acionar o alerta. Defende o inverso exato do bug original, e ele é real:
    // a coluna irmã `erros_amostra` tem cast `array` e devolveria `[]` (truthy em JS) para
    // TODA importação. A chave vem da DESCOBERTA acima, nunca hardcoded.
    $chaveDoMotivo = $chavesRenderizaveis[0];

    $concluida = impShCriar($this->business->id, $this->admin->id, [
        'estado'             => Importacao::ESTADO_CONCLUIDA,
        'linhas_total'       => 120,
        'linhas_processadas' => 120,
        'linhas_sucesso'     => 120,
    ]);

    $respOk = $this->inertiaGet("/ponto/importacoes/{$concluida->id}");
    $this->assertInertiaComponent($respOk, 'Ponto/Importacoes/Show');

    $valorNoSucesso = $respOk->json("props.importacao.{$chaveDoMotivo}");

    $this->assertTrue(
        $valorNoSucesso === null || $valorNoSucesso === '',
        "Importação concluída SEM erro entregou `{$chaveDoMotivo}` = "
        . var_export($valorNoSucesso, true) . '. Qualquer valor truthy em JS abre o alerta '
        . '"Erro no processamento" numa importação que deu certo — o oposto do contrato '
        . '(charter §Goals: o alerta é "quando o processamento falha").'
    );
});
