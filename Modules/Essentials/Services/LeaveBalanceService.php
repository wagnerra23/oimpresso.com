<?php

declare(strict_types=1);

namespace Modules\Essentials\Services;

use Carbon\Carbon;
use Modules\Essentials\Entities\EssentialsLeave;
use Modules\Essentials\Entities\EssentialsLeaveType;

/**
 * HRM-O6 PR-3 — limite de dias por tipo de licença (achado A3).
 *
 * `essentials_leave_types.max_leave_count` + `leave_count_interval` existem no schema
 * desde 2019 e **nunca foram aplicados**: o charter os descreve como "informativos hoje"
 * (R8). Pedir 15 dias de um tipo de 30/ano com 22 já aprovados gravava; aprovar também.
 *
 * Este serviço é o **dono único** dessa regra e é chamado nos dois pontos onde o total
 * pode estourar: `store()` (pedir) e `changeStatus()` (aprovar).
 *
 * ## Convenções — todas herdadas, nenhuma inventada
 *
 * - **Dias do período** = `diffInDays + 1`, inclusivo nas duas pontas. É a regra R6 do
 *   charter e é literalmente o que `EssentialsLeaveController::getUserLeaveSummary()` já
 *   faz para montar o resumo na tela. Contar diferente aqui faria o saldo COBRADO
 *   divergir do saldo EXIBIDO.
 * - **Situações que consomem**: `approved` + `pending`. `cancelled` não consome — o
 *   pedido diz "aprovados + em análise", e `pending` reservado evita que dois pedidos
 *   simultâneos furem o limite.
 * - **Janela**: filtrada por `start_date` dentro do intervalo, mesma convenção do
 *   `getUserLeaveSummary` (que filtra `whereDate('start_date', …)`).
 *
 * ## Soma por COLABORADOR, limite vindo do TIPO
 *
 * O non-goal do charter diz "sem cota por colaborador (a cota é por tipo)": significa que
 * não existe cota individual por pessoa — o limite é atributo do tipo e vale igual para
 * todos. A soma, porém, é por colaborador: três evidências convergem —
 *  (a) UC-HRM-03 usa um único colaborador com 22 dias e pede mais 15;
 *  (b) `getUserLeaveSummary` soma por `user_id`, e é esse saldo que a tela mostra;
 *  (c) somar o negócio inteiro faria "Férias 30 dias/ano" esgotar no primeiro colaborador.
 *
 * ## Sem limite
 *
 * `max_leave_count` nulo **ou zero** = sem limite (UC-HRM-19: "tipo novo com limite 0 →
 * 'sem limite' aparece na lista e no saldo"). Intervalo nulo com limite preenchido = a
 * janela é a vida toda do tipo, que é a leitura literal de "limite sem período".
 *
 * @see Modules\Essentials\Http\Controllers\EssentialsLeaveController
 * @see prototipo-ui/design-docs/cowork-inbox/hrm/Licencas.casos.md (UC-HRM-03/09/19)
 * @see prototipo-ui/design-docs/cowork-inbox/hrm/Licencas.charter.md (R6, R8)
 */
class LeaveBalanceService
{
    /**
     * Situações que ocupam saldo. `cancelled` fica de fora.
     */
    public const STATUS_QUE_CONSOMEM = ['approved', 'pending'];

    /**
     * Dias de um período, inclusivo nas duas pontas (R6 do charter).
     */
    public function diasDoPeriodo(string $inicio, string $fim): int
    {
        // Cast explícito: no Carbon 3 `diffInDays()` devolve float, e o retorno
        // tipado `int` lança TypeError sem ele (pego rodando no CT 100).
        return (int) Carbon::parse($inicio)->diffInDays(Carbon::parse($fim)) + 1;
    }

    /**
     * Janela do intervalo do tipo, ancorada na data de início do pedido.
     *
     * @return array{0: string, 1: string}|null null = tipo sem intervalo (conta tudo)
     */
    public function janela(?string $intervalo, string $dataReferencia): ?array
    {
        $referencia = Carbon::parse($dataReferencia);

        return match ($intervalo) {
            'year'  => [$referencia->copy()->startOfYear()->toDateString(), $referencia->copy()->endOfYear()->toDateString()],
            'month' => [$referencia->copy()->startOfMonth()->toDateString(), $referencia->copy()->endOfMonth()->toDateString()],
            default => null,
        };
    }

    /**
     * Dias já ocupados pelo colaborador naquele tipo, dentro da janela.
     *
     * @param  int|null  $ignorarLeaveId  licença a excluir da soma — usada ao APROVAR,
     *                                    onde o próprio pedido já está contado como
     *                                    `pending` e contaria em dobro.
     */
    public function diasConsumidos(
        int $businessId,
        int $userId,
        int $tipoId,
        ?array $janela,
        ?int $ignorarLeaveId = null
    ): int {
        // Scope global ATIVO de propósito + `where` explícito: defesa em profundidade.
        // O scope cobre o request autenticado; o `where` cobre CLI/job (onde o scope
        // não filtra) e mantém o Tier 0 mesmo se a sessão trouxer outro business.
        $query = EssentialsLeave::where('business_id', $businessId)
            ->where('user_id', $userId)
            ->where('essentials_leave_type_id', $tipoId)
            ->whereIn('status', self::STATUS_QUE_CONSOMEM);

        if ($janela !== null) {
            $query->whereDate('start_date', '>=', $janela[0])
                  ->whereDate('start_date', '<=', $janela[1]);
        }

        if ($ignorarLeaveId !== null) {
            $query->where('id', '!=', $ignorarLeaveId);
        }

        return $query->get(['start_date', 'end_date'])->sum(
            fn ($leave) => $this->diasDoPeriodo((string) $leave->start_date, (string) $leave->end_date)
        );
    }

    /**
     * Avalia se o período cabe no limite do tipo.
     *
     * @return array{cabe: bool, limite: int|null, consumidos: int, solicitados: int, restante: int|null, mensagem: string|null}
     */
    public function avaliar(
        EssentialsLeaveType $tipo,
        int $businessId,
        int $userId,
        string $inicio,
        string $fim,
        ?int $ignorarLeaveId = null
    ): array {
        $limite = $tipo->max_leave_count !== null ? (int) $tipo->max_leave_count : null;
        $solicitados = $this->diasDoPeriodo($inicio, $fim);

        // null ou 0 = sem limite (UC-HRM-19)
        if ($limite === null || $limite <= 0) {
            return [
                'cabe' => true, 'limite' => null, 'consumidos' => 0,
                'solicitados' => $solicitados, 'restante' => null, 'mensagem' => null,
            ];
        }

        $janela = $this->janela($tipo->leave_count_interval, $inicio);
        $consumidos = $this->diasConsumidos($businessId, $userId, (int) $tipo->id, $janela, $ignorarLeaveId);
        $restante = max(0, $limite - $consumidos);
        $cabe = ($consumidos + $solicitados) <= $limite;

        return [
            'cabe'        => $cabe,
            'limite'      => $limite,
            'consumidos'  => $consumidos,
            'solicitados' => $solicitados,
            'restante'    => $restante,
            'mensagem'    => $cabe ? null : $this->mensagem($tipo, $solicitados, $restante),
        ];
    }

    /**
     * Mensagem de recusa — diz o saldo restante (UC-HRM-03/09).
     */
    private function mensagem(EssentialsLeaveType $tipo, int $solicitados, int $restante): string
    {
        $periodo = match ($tipo->leave_count_interval) {
            'year'  => ' no ano',
            'month' => ' no mês',
            default => '',
        };

        return sprintf(
            'O tipo "%s" permite %d dia(s)%s. Você pediu %d dia(s) e o saldo restante é %d dia(s).',
            (string) $tipo->leave_type,
            (int) $tipo->max_leave_count,
            $periodo,
            $solicitados,
            $restante
        );
    }
}
