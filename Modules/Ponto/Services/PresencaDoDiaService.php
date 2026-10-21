<?php

namespace Modules\Ponto\Services;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Modules\Ponto\Entities\Marcacao;

/**
 * Presença do dia de um usuário, SÓ LEITURA, para outro módulo consultar o ponto.
 *
 * Quem consome: o apontamento da Produção (Modules/Producao, thread 04 + E04 do playbook
 * PCP). Pela D-PONTO, confirmada por [W] em 2026-10-15, o ponto é a fonte legal e nunca é
 * alterado pelo apontamento: a saída do ponto fecha o cronômetro de pessoa, e as horas de
 * pessoa no dia não passam da presença. Este Service é a única porta da Produção para isso.
 *
 * Garantias:
 *  - Nada é escrito. `ponto_marcacoes` é append-only por força de lei (Portaria MTP 671/2021).
 *  - `business_id` vem explícito e entra em toda consulta: funciona com token (sem sessão)
 *    e nunca atravessa empresas (ADR 0093). Por isso `DB::table` e não o Model, cujo global
 *    scope lê a sessão.
 *  - Marcação anulada (há linha `ANULACAO` apontando para ela) não conta, nem a própria
 *    linha de anulação.
 *  - A escolha das batidas é a do `ApuracaoService::carregarMarcacoes`: 1ª ENTRADA, última
 *    SAIDA, 1º par de almoço. Quem mudar a regra lá muda aqui.
 */
class PresencaDoDiaService
{
    /**
     * @return array{entrada: ?CarbonImmutable, saida: ?CarbonImmutable, almoco_minutos: int, presenca_minutos: ?int}|null
     *         null = o usuário não tem cadastro no ponto desta empresa (não controla ponto aqui).
     *         `presenca_minutos` só vem quando há entrada e saída.
     */
    public function doUsuario(int $businessId, int $userId, CarbonInterface $dia): ?array
    {
        $colaboradorId = DB::table('ponto_colaborador_config')
            ->where('business_id', $businessId)
            ->where('user_id', $userId)
            ->value('id');

        if ($colaboradorId === null) {
            return null;
        }

        $anuladas = DB::table('ponto_marcacoes')
            ->where('business_id', $businessId)
            ->where('origem', Marcacao::ORIGEM_ANULACAO)
            ->whereNotNull('marcacao_anulada_id')
            ->select('marcacao_anulada_id');

        $marcacoes = DB::table('ponto_marcacoes')
            ->where('business_id', $businessId)
            ->where('colaborador_config_id', $colaboradorId)
            ->whereDate('momento', $dia->toDateString())
            ->where('origem', '!=', Marcacao::ORIGEM_ANULACAO)
            ->whereNotIn('id', $anuladas)
            ->orderBy('momento')
            ->get(['tipo', 'momento']);

        $momento = static fn ($m) => $m ? CarbonImmutable::parse($m->momento) : null;

        $entrada = $momento($marcacoes->firstWhere('tipo', Marcacao::TIPO_ENTRADA));
        $saida = $momento($marcacoes->where('tipo', Marcacao::TIPO_SAIDA)->last());
        $almocoInicio = $momento($marcacoes->firstWhere('tipo', Marcacao::TIPO_ALMOCO_INICIO));
        $almocoFim = $momento($marcacoes->firstWhere('tipo', Marcacao::TIPO_ALMOCO_FIM));

        $almoco = ($almocoInicio && $almocoFim) ? (int) $almocoInicio->diffInMinutes($almocoFim) : 0;

        return [
            'entrada' => $entrada,
            'saida' => $saida,
            'almoco_minutos' => $almoco,
            'presenca_minutos' => ($entrada && $saida)
                ? max(0, (int) $entrada->diffInMinutes($saida) - $almoco)
                : null,
        ];
    }
}
