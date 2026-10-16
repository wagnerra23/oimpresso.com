<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Fsm\Modelos\AplicarModelo;
use App\Domain\Fsm\Modelos\CatalogoModelos;
use App\Domain\Fsm\Modelos\RecomendarModelo;
use App\Domain\Fsm\Models\ModeloEmpresa;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
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

    public function index(): Response
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

        return Inertia::render('Fluxos/Index', [
            'aba' => in_array($aba, self::ABAS, true) ? $aba : 'modelos',
            'modelos' => $modelos,
            'encaixe' => $encaixe,
            'portes' => self::opcoes(CatalogoModelos::PORTES),
            'atividades' => self::opcoes(CatalogoModelos::ATIVIDADES),
            'casos_total' => count(CatalogoModelos::CASOS),
            'casos' => array_map(fn (array $c) => ['titulo' => $c[0], 'dominio' => $c[1]], CatalogoModelos::CASOS),
            'empresa' => self::empresa(),
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
