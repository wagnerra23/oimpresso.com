<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Contracts\Producao\ReservaDeInsumo;
use App\Contracts\Compras\RequisicaoEstoqueMinimo;
use App\Domain\Fsm\Modelos\AplicarModelo;
use App\Domain\Fsm\Modelos\CatalogoModelos;
use App\Domain\Fsm\Modelos\CatalogoProcessos;
use App\Domain\Fsm\Modelos\RecomendarModelo;
use App\Domain\Fsm\Modelos\RegrasCompra;
use App\Domain\Fsm\Modelos\RegrasDaEmpresa;
use App\Domain\Fsm\Models\ModeloEmpresa;
use App\Utils\ModuleUtil;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Sistema › Fluxos e regras (ADR 0435) — tela dos modelos de fluxo por porte × atividade.
 *
 * Fonte visual: prototipo-ui/cowork/Wagner/fluxos-page.jsx. Os dados vêm do catálogo
 * `CatalogoModelos` (não há cópia aqui): este controller só formata para a tela.
 *
 * O catálogo é estático (código, sem banco), então não há prop cara a deferir. O encaixe
 * de cada modelo para cada porte × atividade vai pronto, calculado por
 * `CatalogoModelos::encaixe()` — a tela não reimplementa a regra.
 */
class FluxosController extends Controller
{
    /** Abas do protótipo, na ordem: Modelos · Processos · Fluxos da empresa · Casos de uso. */
    private const ABAS = ['modelos', 'processos', 'empresa', 'casos'];

    public function index(RegrasDaEmpresa $regras): Response
    {
        abort_unless(auth()->user()->can('fluxos.ver'), 403);

        $modelos = [];
        $encaixe = [];
        foreach (CatalogoModelos::modelos() as $id => $m) {
            $modelos[] = [
                'id' => $id,
                'nome' => $m['nome'],
                'para' => $m['para'],
                'portes' => $m['portes'],
                'atividades' => $m['atividades'],
                'casos' => $m['casos'],
                'insp' => $m['insp'],
                'processos' => array_map(
                    fn (string $dominio, array $p) => [
                        'dominio' => $dominio,
                        'nome' => CatalogoModelos::DOMINIOS[$dominio] ?? $dominio,
                        'etapas' => $p['etapas'],
                        'regras' => array_map(fn (array $r) => ['etapa' => $r[0], 'efeito' => $r[1], 'uc' => $r[2]], $p['regras']),
                    ],
                    array_keys($m['processos']),
                    $m['processos'],
                ),
            ];
            foreach (array_keys(CatalogoModelos::PORTES) as $porte) {
                foreach (array_keys(CatalogoModelos::ATIVIDADES) as $ativ) {
                    $n = CatalogoModelos::encaixe($id, $porte, $ativ);
                    $encaixe[$id][$porte][$ativ] = [
                        'n' => $n,
                        'rotulo' => CatalogoModelos::rotuloEncaixe($n) ?? 'Outro perfil',
                        'motivo' => self::motivo($m, $porte, $ativ, $n),
                    ];
                }
            }
        }

        $aba = (string) request()->query('aba', 'modelos');
        $aba = in_array($aba, self::ABAS, true) ? $aba : 'modelos';
        $processo = (string) request()->query('processo', 'producao');
        $empresa = self::empresa();
        $businessId = (int) request()->session()->get('user.business_id');
        $comModelo = $aba === 'empresa' && $empresa !== null && CatalogoModelos::modelo($empresa['modelo']) !== null;

        return Inertia::render('Fluxos/Index', [
            'aba' => $aba,
            // Aba Processos: só vai na própria aba (18 processos, estados e transições).
            'processos' => $aba === 'processos' ? self::processos() : [],
            'processos_total' => count(CatalogoProcessos::processos()),
            // Aba Casos de uso: lista só na própria aba; fontes e estados vão sempre (cabeçalho,
            // "Inspirado em" dos cards e a gaveta do modelo usam).
            'casos_lista' => $aba === 'casos' ? self::casosLista() : [],
            'fontes' => CatalogoModelos::fontes(),
            'estados_caso' => CatalogoModelos::estadosCaso(),
            'processo' => isset(CatalogoProcessos::processos()[$processo]) ? $processo : 'producao',
            'modelos' => $modelos,
            'encaixe' => $encaixe,
            'portes' => array_map(
                fn (string $v, array $p) => ['value' => $v, 'label' => $p['rotulo'], 'descricao' => $p['descricao']],
                array_keys(CatalogoModelos::portes()),
                CatalogoModelos::portes(),
            ),
            'atividades' => self::opcoes(CatalogoModelos::ATIVIDADES),
            'dominios' => self::opcoes(CatalogoModelos::DOMINIOS),
            'casos_total' => count(CatalogoModelos::CASOS),
            'casos' => array_map(fn (array $c) => ['titulo' => $c[0], 'dominio' => $c[1]], CatalogoModelos::CASOS),
            'empresa' => $empresa,
            // Aba Fluxos da empresa: o que a empresa ligou/desligou e a Compra configurável.
            'regras_ligadas' => $comModelo ? $regras->ligadas($businessId, $empresa['modelo']) : (object) [],
            'regras_compra' => $comModelo ? $regras->compra($businessId) : [],
            'opcoes_compra' => $comModelo ? RegrasCompra::opcoes() : (object) [],
            // UC-FLX-35 · UC-FLXT-19: margem mínima do orçamento (nula = desligada).
            'margem_minima' => $comModelo ? $regras->margemMinima($businessId) : null,
            // UC-FLX-28 · UC-FLXT-23: sinal mínimo para liberar a OS, em % da venda (nulo = desligado).
            'sinal_minimo' => $comModelo ? $regras->sinalMinimo($businessId) : null,
            // US-PCP-031 · UC-FLX-14: reserva do insumo na venda (null = empresa sem o módulo Produção).
            'reserva_insumo' => $comModelo && self::temProducao($businessId)
                ? app(ReservaDeInsumo::class)->ligada($businessId) : null,
            // UC-FLX-19 · UC-FLXT-24: requisição pelo estoque mínimo (nula = sem o módulo Compras).
            'requisicao_estoque_minimo' => $comModelo && app()->bound(RequisicaoEstoqueMinimo::class)
                ? app(RequisicaoEstoqueMinimo::class)->ativo($businessId)
                : null,
            'pode_configurar' => auth()->user()->can('fluxos.configurar'),
        ]);
    }

    /**
     * "Usar modelo" (aba Fluxos da empresa). Monta os processos do modelo no motor FSM da
     * empresa da sessão via AplicarModelo (#9492, ADR 0435). Documentos em andamento ficam na
     * etapa em que estão: AplicarModelo não toca em documento.
     */
    public function aplicar(Request $request, AplicarModelo $aplicar): RedirectResponse
    {
        abort_unless($request->user()->can('fluxos.configurar'), 403);

        $dados = $request->validate([
            'modelo' => ['required', 'string', Rule::in(array_keys(CatalogoModelos::modelos()))],
            'porte' => ['nullable', 'string', Rule::in(array_keys(CatalogoModelos::PORTES))],
            'atividade' => ['nullable', 'string', Rule::in(array_keys(CatalogoModelos::ATIVIDADES))],
        ]);

        // Tier 0 (ADR 0093): a empresa é sempre a da sessão, nunca um id vindo do formulário.
        $businessId = (int) $request->session()->get('user.business_id');
        $aplicar->aplicar($businessId, $dados['modelo'], $dados['porte'] ?? null, $dados['atividade'] ?? null, (int) $request->user()->id);

        $nome = CatalogoModelos::modelo($dados['modelo'])['nome'];

        return redirect('/fluxos?aba=empresa')->with('status', [
            'success' => 1,
            'msg' => "Modelo {$nome} aplicado. Documentos em andamento continuam na etapa em que estavam.",
        ]);
    }

    /**
     * Liga ou desliga uma regra "ao entrar" do modelo em uso (aba Fluxos da empresa). A regra é a
     * de índice `indice` no processo `dominio` do catálogo, como no protótipo.
     */
    public function regra(Request $request, RegrasDaEmpresa $regras): RedirectResponse
    {
        abort_unless($request->user()->can('fluxos.configurar'), 403);

        $dados = $request->validate([
            'dominio' => ['required', 'string', Rule::in(array_keys(CatalogoModelos::DOMINIOS))],
            'indice' => ['required', 'integer', 'min:0'],
            'ligada' => ['required', 'boolean'],
        ]);

        // Tier 0 (ADR 0093): a empresa é sempre a da sessão.
        self::recusarComo('regra', fn () => $regras->ligar(
            (int) $request->session()->get('user.business_id'),
            $dados['dominio'],
            (int) $dados['indice'],
            (bool) $dados['ligada'],
        ));

        return redirect('/fluxos?aba=empresa');
    }

    /** Liga, desliga ou muda os parâmetros de uma regra das etapas da Compra (RegrasCompra). */
    public function compra(Request $request, RegrasDaEmpresa $regras): RedirectResponse
    {
        abort_unless($request->user()->can('fluxos.configurar'), 403);

        $dados = $request->validate([
            'regra' => ['required', 'string', Rule::in(array_column(RegrasCompra::PADRAO, 'id'))],
            'ativo' => ['nullable', 'boolean'],
            'params' => ['nullable', 'array'],
        ]);

        self::recusarComo('regra', fn () => $regras->mudarCompra(
            (int) $request->session()->get('user.business_id'),
            $dados['regra'],
            isset($dados['ativo']) ? (bool) $dados['ativo'] : null,
            $dados['params'] ?? [],
        ));

        return redirect('/fluxos?aba=empresa');
    }

    /**
     * Margem mínima do orçamento (UC-FLXT-19 · UC-FLX-35). Abaixo dela, converter orçamento em
     * venda exige `sell.aprovar_margem_baixa`. Vazio desliga a regra.
     */
    public function margem(Request $request, RegrasDaEmpresa $regras): RedirectResponse
    {
        abort_unless($request->user()->can('fluxos.configurar'), 403);

        $dados = $request->validate([
            'margem_minima' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ]);

        // Tier 0 (ADR 0093): a empresa é sempre a da sessão.
        self::recusarComo('margem_minima', fn () => $regras->mudarMargemMinima(
            (int) $request->session()->get('user.business_id'),
            isset($dados['margem_minima']) ? (float) $dados['margem_minima'] : null,
        ));

        return redirect('/fluxos?aba=empresa');
    }

    /**
     * Sinal mínimo para a produção liberar a OS (UC-FLXT-23 · UC-FLX-28 · US-PCP-025), em % do total
     * da venda. Ligado, a OS só sai de Aguardando liberação com esse percentual pago. Vazio desliga.
     * O valor segue como texto até o banco (DECIMAL), sem passar por float.
     */
    public function sinal(Request $request, RegrasDaEmpresa $regras): RedirectResponse
    {
        abort_unless($request->user()->can('fluxos.configurar'), 403);

        $dados = $request->validate([
            'sinal_minimo' => ['nullable', 'numeric', 'gt:0', 'max:100'],
        ]);

        // Tier 0 (ADR 0093): a empresa é sempre a da sessão.
        self::recusarComo('sinal_minimo', fn () => $regras->mudarSinalMinimo(
            (int) $request->session()->get('user.business_id'),
            isset($dados['sinal_minimo']) ? (string) $dados['sinal_minimo'] : null,
        ));

        return redirect('/fluxos?aba=empresa');
    }

    /**
     * Reserva o insumo na venda (US-PCP-031 · UC-FLX-14). Ligada, a OS criada pela venda aprovada
     * reserva o material da receita; desligada, nada novo é reservado (o que já foi reservado segue).
     */
    public function reserva(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('fluxos.configurar'), 403);

        $dados = $request->validate(['ligada' => ['required', 'boolean']]);
        // Tier 0 (ADR 0093): a empresa é sempre a da sessão.
        $businessId = (int) $request->session()->get('user.business_id');
        abort_unless(self::temProducao($businessId), 404);

        app(ReservaDeInsumo::class)->mudar($businessId, (bool) $dados['ligada']);

        return redirect('/fluxos?aba=empresa');
    }

    /**
     * Requisição de compra pelo estoque mínimo (UC-FLX-19 · UC-FLXT-24). Ligada, o job diário
     * do Compras cria a requisição do item abaixo do mínimo com o fornecedor da última compra.
     */
    public function requisicaoEstoqueMinimo(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('fluxos.configurar'), 403);
        abort_unless(app()->bound(RequisicaoEstoqueMinimo::class), 404);

        $dados = $request->validate(['ativo' => ['required', 'boolean']]);

        // Tier 0 (ADR 0093): a empresa é sempre a da sessão.
        app(RequisicaoEstoqueMinimo::class)->definir((int) $request->session()->get('user.business_id'), (bool) $dados['ativo']);

        return redirect('/fluxos?aba=empresa');
    }

    /** O interruptor só existe para empresa com o módulo Produção no pacote. */
    private static function temProducao(int $businessId): bool
    {
        return app()->bound(ReservaDeInsumo::class)
            && (bool) app(ModuleUtil::class)->hasThePermissionInSubscription($businessId, 'producao_module');
    }

    /** Erro de regra de negócio vira erro de formulário (Inertia mostra no campo). */
    private static function recusarComo(string $campo, callable $fn): void
    {
        try {
            $fn();
        } catch (InvalidArgumentException $e) {
            throw ValidationException::withMessages([$campo => $e->getMessage()]);
        }
    }

    /**
     * Assistente de configuração: descrição livre → modelo sugerido (IA do ERP, com queda para a
     * regra de encaixe). Só sugere, não grava — por isso basta fluxos.ver.
     */
    public function assistente(Request $request, RecomendarModelo $recomendar): JsonResponse
    {
        abort_unless($request->user()->can('fluxos.ver'), 403);

        $dados = $request->validate([
            'texto' => ['nullable', 'string', 'max:2000'],
            'porte' => ['required', 'string', Rule::in(array_keys(CatalogoModelos::PORTES))],
            'atividade' => ['required', 'string', Rule::in(array_keys(CatalogoModelos::ATIVIDADES))],
        ]);

        return response()->json($recomendar->recomendar(
            (string) ($dados['texto'] ?? ''),
            $dados['porte'],
            $dados['atividade'],
            (int) $request->session()->get('user.business_id'),
        ));
    }

    /**
     * Casos de uso do catálogo (#9501) com os modelos que usam cada um, para a aba Casos de uso.
     *
     * @return list<array<string, mixed>>
     */
    private static function casosLista(): array
    {
        $modelos = CatalogoModelos::modelos();

        return array_map(fn (array $c) => $c + [
            'modelos' => array_keys(array_filter($modelos, fn (array $m) => in_array($c['id'], $m['casos'], true))),
        ], CatalogoModelos::casos());
    }

    /**
     * Processos do CatalogoProcessos (#9496) para a aba Processos, na ordem do protótipo, com a
     * cobertura do ERP por transição e o resumo do processo (indicador aprovado no protótipo, #9506).
     *
     * @return list<array<string, mixed>>
     */
    private static function processos(): array
    {
        return array_values(array_map(fn (array $p) => [
            'id' => $p['id'], 'area' => $p['area'], 'nome' => $p['nome'], 'doc' => $p['doc'],
            'porque' => $p['porque'], 'liga' => $p['liga'], 'roteiro' => $p['roteiro'],
            'estados' => $p['estados'],
            'transicoes' => array_map(fn (array $t) => [
                'de' => $t['de'], 'para' => $t['para'], 'rotulo' => $t['rotulo'],
                'guarda' => $t['guarda'], 'efeito' => $t['efeito'], 'quem' => $t['quem'],
                'cobertura' => $t['cobertura'],
            ], $p['transicoes']),
            'cobertura' => self::resumoCobertura($p['transicoes']),
        ], CatalogoProcessos::processos()));
    }

    /**
     * Mesma conta de CatalogoProcessos::resumo(), sobre as transições já montadas (resumo() monta o
     * catálogo inteiro de novo a cada chamada).
     *
     * @param  list<array{cobertura:array{estado:string}}>  $transicoes
     * @return array<string, int>
     */
    private static function resumoCobertura(array $transicoes): array
    {
        $r = array_fill_keys(CatalogoProcessos::ESTADOS_COBERTURA, 0);
        foreach ($transicoes as $t) {
            $r[$t['cobertura']['estado']]++;
        }

        return $r + ['total' => array_sum($r)];
    }

    /**
     * Modelo em uso na empresa da sessão (HasBusinessScope filtra). null = nenhuma linha;
     * `modelo = legado` = processos de antes do catálogo.
     *
     * @return array{modelo:string, porte:?string, atividade:?string, aplicado_em:?string}|null
     */
    private static function empresa(): ?array
    {
        $linha = ModeloEmpresa::query()->first();
        if ($linha === null) {
            return null;
        }

        return [
            'modelo' => (string) $linha->modelo,
            'porte' => $linha->porte,
            'atividade' => $linha->atividade,
            'aplicado_em' => $linha->aplicado_em?->toIso8601String(),
        ];
    }

    /**
     * Frase do card ("Porte e atividade batem"…), como no protótipo. O "porte vizinho" sai do
     * próprio encaixe: o que sobra depois de porte igual (2) e atividade igual (2) é o 1 do vizinho.
     */
    private static function motivo(array $m, string $porte, string $ativ, int $n): string
    {
        $p = in_array($porte, $m['portes'], true);
        $a = in_array($ativ, $m['atividades'], true);
        $vizinho = ($n - ($p ? 2 : 0) - ($a ? 2 : 0)) === 1;

        return match (true) {
            $p && $a => 'Porte e atividade batem',
            $a => $vizinho ? 'Atividade bate · feito para porte vizinho' : 'Atividade bate · porte diferente',
            $p => 'Porte bate · outra atividade',
            default => 'Outro porte e outra atividade',
        };
    }

    /** @param array<string,string> $mapa @return list<array{value:string,label:string}> */
    private static function opcoes(array $mapa): array
    {
        return array_map(fn ($v, $l) => ['value' => $v, 'label' => $l], array_keys($mapa), $mapa);
    }
}
