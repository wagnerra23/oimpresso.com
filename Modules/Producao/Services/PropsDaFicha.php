<?php

declare(strict_types=1);

namespace Modules\Producao\Services;

use App\User;
use Illuminate\Support\Facades\Schema;
use Modules\Producao\Entities\Ordem;
use Modules\Producao\Exceptions\ApontamentoRecusado;

/**
 * O que a Ficha da OS por item recebe (UC-PCV-10), montado num lugar só para as duas entradas: a página
 * `/producao/ordens/{id}` e o painel lateral do Quadro (`/producao/quadro?os={id}`, decisão [W] no #10850:
 * a Ficha segue o protótipo e abre em painel). Cada controller decide como entrega as abas (defer ou optional).
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
}
