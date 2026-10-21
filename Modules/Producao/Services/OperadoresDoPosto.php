<?php

declare(strict_types=1);

namespace Modules\Producao\Services;

use App\User;
use Illuminate\Support\Facades\DB;
use Modules\Producao\Entities\Posto;
use Modules\Producao\Entities\PostoOperador;
use Modules\Producao\Exceptions\ApontamentoRecusado;

/**
 * Operadores por posto (UC-FLX-08 · US-PCP-030). Fonte: fluxos-modelos.casos.md, processo "OS e
 * produção por item": Iniciar etapa "só se: Operador do setor da etapa"; aprovado por [W] 2026-10-20.
 *
 * A regra única, que o Liberar (responsável), o início da etapa (quem começa) e o alocador chamam:
 * - posto COM operadores cadastrados → só eles podem ser responsáveis ou começar a etapa;
 * - posto SEM operadores cadastrados → nada muda (vale o que cada chamador já exigia antes);
 * - etapa sem posto → nada muda.
 *
 * Tier 0 (ADR 0093): tudo por `$businessId` explícito — o app e os jobs não têm sessão.
 */
final class OperadoresDoPosto
{
    /** @return list<int> ids dos operadores cadastrados no posto (vazio = posto sem cadastro). */
    public function doPosto(int $businessId, int $postoId): array
    {
        return DB::table('pcp_posto_operadores')->where('business_id', $businessId)->where('posto_id', $postoId)
            ->orderBy('user_id')->pluck('user_id')->map(fn ($id) => (int) $id)->all();
    }

    public function podeAtuar(int $businessId, ?int $postoId, int $userId): bool
    {
        if ($postoId === null) {
            return true;
        }
        $operadores = $this->doPosto($businessId, $postoId);

        return $operadores === [] || in_array($userId, $operadores, true);
    }

    /**
     * Substitui a lista de operadores do posto. Lista vazia limpa o cadastro (o posto volta a aceitar
     * qualquer pessoa). Cada operador tem de ser da MESMA empresa e ter `producao.access`.
     *
     * @param  list<int>  $userIds
     * @return list<int>
     */
    public function definir(int $businessId, int $postoId, array $userIds, int $por): array
    {
        $posto = Posto::query()->where('business_id', $businessId)->find($postoId)
            ?? throw new ApontamentoRecusado(ApontamentoRecusado::NAO_ENCONTRADO, 'Posto não encontrado.');
        $ids = array_values(array_unique(array_map('intval', $userIds)));
        $usuarios = User::query()->where('business_id', $businessId)->whereIn('id', $ids)->get()->keyBy('id');
        foreach ($ids as $uid) {
            $u = $usuarios->get($uid);
            if (! $u || ! $u->can('producao.access')) {
                throw new ApontamentoRecusado(ApontamentoRecusado::RESPONSAVEL_INVALIDO,
                    'Operador não encontrado nesta empresa ou sem acesso à Produção.', ['user_id' => $uid]);
            }
        }

        DB::transaction(function () use ($businessId, $posto, $ids, $por) {
            DB::table('pcp_posto_operadores')->where('business_id', $businessId)->where('posto_id', $posto->id)->delete();
            foreach ($ids as $uid) {
                PostoOperador::create(['business_id' => $businessId, 'posto_id' => $posto->id, 'user_id' => $uid, 'created_by' => $por]);
            }
        });

        return $this->doPosto($businessId, (int) $posto->id);
    }
}
