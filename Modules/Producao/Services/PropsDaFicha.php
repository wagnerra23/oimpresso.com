<?php

declare(strict_types=1);

namespace Modules\Producao\Services;

use App\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use Modules\Producao\Entities\Ordem;
use Modules\Producao\Exceptions\ApontamentoRecusado;

/**
 * O que a Ficha da OS por item recebe (UC-PCV-10), montado num lugar só para o painel lateral do Quadro e da Linha do
 * tempo (`?os={id}`, decisão [W] no #10850: a Ficha segue o protótipo e abre em painel), por `doPainel()`. O endereço
 * antigo `/producao/ordens/{id}` usa `montar()` só para conferir a OS antes de levar ao Quadro (UC-PCOSF-10).
 *
 * `base` sai na hora; `abas` são closures, para só rodar a consulta da aba que a tela pedir. As flags `pode_*`
 * só escondem botão: as rotas de ação conferem o papel de novo. Tier 0 (ADR 0093): tudo pelo `$biz` da sessão;
 * OS de outra empresa sai como NAO_ENCONTRADO, igual a OS que não existe.
 */
final class PropsDaFicha
{
    public function __construct(private readonly FichaDaOrdem $ficha, private readonly QuadroDaProducao $quadro,
        private readonly PrevistoRealizadoDaOrdem $previsto, private readonly CustoRealService $custo) {}

    /**
     * @return array{base: array{os: array<string, mixed>, etapas: list<array<string, mixed>>, pode: array<string, bool>}, abas: array<string, \Closure>}
     *
     * @throws ApontamentoRecusado NAO_ENCONTRADO quando a OS não é desta empresa
     */
    public function montar(int $biz, User $user, int $id): array
    {
        $dados = $this->ficha->montar($biz, $id);
        $papel = fn (string $r) => $user->can('superadmin') || $user->hasRole(Schema::hasColumn('roles', 'business_id') ? "{$r}#{$biz}" : $r);
        $postos = array_values(array_unique(array_filter(array_column($dados['etapas'], 'posto_id'))));
        $verCusto = $user->can('superadmin') || $user->can('producao.desempenho.ver');
        $podeLiberar = $papel('producao.liberar');

        return [
            'base' => ['os' => $dados['os'], 'etapas' => $dados['etapas'],
                'pode' => ['liberar' => $podeLiberar, 'cancelar' => $papel('vendas.gerente'), 'custo' => $verCusto]],
            'abas' => [
                // Aba Custo (lote 3.3): só leitura; sem `producao.desempenho.ver` a aba vem nula.
                'custo' => function () use ($biz, $id, $verCusto) {
                    if (! $verCusto) {
                        return null;
                    }
                    $pr = $this->previsto->daOrdem($biz, $id);

                    // Composição só enquanto a OS anda: concluída tem o total congelado no previsto × realizado.
                    return ['previsto_realizado' => $pr,
                        'composicao' => $pr['status'] === 'em_andamento' ? $this->custo->daOs($biz, Ordem::OS_TIPO, $id) : null];
                },
                'materiais' => fn () => $this->ficha->materiais($biz, $id, $dados['etapas'][0]['id'] ?? null),
                'artes' => fn () => $this->ficha->artes($biz, $id),
                'pessoas' => fn () => $dados['os']['situacao'] === 'aguardando_liberacao' && $podeLiberar
                    ? $this->quadro->pessoas($biz, $postos) : ['pessoas' => [], 'por_posto' => []],
            ],
        ];
    }

    /**
     * Props do painel lateral (UC-PCOSF-07 no Quadro, UC-PCOSF-09 na Linha do tempo): `?os={id}` faz a tela entregar
     * `ficha` (deferida, para o F5 abrir o painel sozinho) e as abas `ficha_*` como optional, que só rodam quando a
     * tela pede a aba. Só id inteiro positivo; a Ficha é montada uma vez por pedido. OS de outra empresa ou
     * inexistente → `ficha` nula, e a tela abre normal.
     *
     * @return array<string, mixed>
     */
    public function doPainel(Request $request, int $biz, User $user): array
    {
        $osId = is_string($v = $request->query('os')) && ctype_digit($v) ? (int) $v : 0;
        $memo = null;
        $ficha = function () use (&$memo, $osId, $biz, $user) {
            if ($memo === null) {
                try {
                    $memo = $osId > 0 ? $this->montar($biz, $user, $osId) : false;
                } catch (ApontamentoRecusado $e) {
                    if ($e->codigo !== ApontamentoRecusado::NAO_ENCONTRADO) {
                        throw $e;
                    }
                    $memo = false;
                }
            }

            return $memo ?: null;
        };
        $aba = fn (string $nome) => Inertia::optional(fn () => ($f = $ficha()) ? ($f['abas'][$nome])() : null);

        return [
            'ficha' => Inertia::defer(fn () => $ficha()['base'] ?? null),
            'ficha_custo' => $aba('custo'),
            'ficha_materiais' => $aba('materiais'),
            'ficha_artes' => $aba('artes'),
            'ficha_pessoas' => $aba('pessoas'),
        ];
    }
}
