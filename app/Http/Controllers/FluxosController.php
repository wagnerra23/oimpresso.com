<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Fsm\Modelos\CatalogoModelos;
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
            'pode_configurar' => auth()->user()->can('fluxos.configurar'),
        ]);
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
