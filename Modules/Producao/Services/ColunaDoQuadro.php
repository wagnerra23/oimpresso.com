<?php

declare(strict_types=1);

namespace Modules\Producao\Services;

use App\User;
use Illuminate\Support\Facades\DB;
use Modules\Producao\Entities\Evento;
use Modules\Producao\Exceptions\ApontamentoRecusado;

/**
 * O PCP move a OS de coluna à mão (thread 06 · UC-PCV-07). Como no protótipo (`producao-cv-page.jsx`,
 * menu ⋮ e arrastar), mover só troca a coluna e registra "Moveu para X (à mão)": nenhuma etapa,
 * apontamento ou situação da OS muda. Vale para frente e para trás, para qualquer setor da empresa.
 *
 * A coluna manual deixa de valer sozinha quando o operador anda: um evento de etapa iniciada ou
 * concluída mais novo que o movimento devolve a OS à coluna derivada da etapa atual. Sem isso o quadro
 * mentiria depois que a produção seguiu.
 *
 * Mover pede `producao.etapa.mover` (quem confere é o controller). Tier 0 (ADR 0093): tudo por `$businessId`.
 */
final class ColunaDoQuadro
{
    public const EV_MOVIDA = 'os_movida_quadro';

    public function __construct(private readonly QuadroDaProducao $quadro) {}

    public function mover(int $businessId, string $osTipo, int $osId, string $setor, User $gestor): string
    {
        $setor = trim($setor);
        $cartao = collect($this->quadro->cartoes($businessId, now()))->first(fn ($c) => $c['os_tipo'] === $osTipo && $c['os_id'] === $osId)
            ?? throw new ApontamentoRecusado(ApontamentoRecusado::NAO_ENCONTRADO, 'OS não encontrada no quadro.');
        $setores = collect($this->quadro->postos($businessId))->map(fn ($p) => trim((string) $p['setor']) ?: QuadroDaProducao::SEM_SETOR)->unique();
        if (! $setores->contains($setor)) {
            throw new ApontamentoRecusado(ApontamentoRecusado::COLUNA_INVALIDA, 'Esta empresa não tem o setor escolhido.', ['setor' => $setor]);
        }
        $de = $cartao['coluna'];
        if ($de === $setor) {
            throw new ApontamentoRecusado(ApontamentoRecusado::COLUNA_INVALIDA, 'A OS já está nesta coluna.', ['setor' => $setor]);
        }

        DB::transaction(function () use ($businessId, $osTipo, $osId, $setor, $de, $gestor) {
            $chave = ['business_id' => $businessId, 'os_tipo' => $osTipo, 'os_id' => $osId];
            $existe = DB::table('pcp_quadro_colunas')->where($chave)->exists();
            $linha = ['setor' => $setor, 'ate_evento_id' => $this->ultimoAndar($businessId, $osTipo, $osId), 'movido_por' => $gestor->id, 'updated_at' => now()];
            $existe ? DB::table('pcp_quadro_colunas')->where($chave)->update($linha)
                : DB::table('pcp_quadro_colunas')->insert($chave + $linha + ['created_at' => now()]);

            Evento::create(['business_id' => $businessId, 'tipo' => self::EV_MOVIDA, 'os_tipo' => $osTipo, 'os_id' => $osId,
                'user_id' => $gestor->id, 'dados' => ['de' => $de, 'para' => $setor]]);
        });

        return $setor;
    }

    private function ultimoAndar(int $businessId, string $osTipo, int $osId): int
    {
        return (int) DB::table('pcp_eventos')->where('business_id', $businessId)->whereIn('tipo', QuadroDaProducao::ANDOU)
            ->where('os_tipo', $osTipo)->where('os_id', $osId)->max('id');
    }
}
