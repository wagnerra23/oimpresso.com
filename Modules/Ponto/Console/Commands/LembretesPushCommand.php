<?php

declare(strict_types=1);

namespace Modules\Ponto\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Modules\Ponto\Entities\Marcacao;
use Modules\Ponto\Jobs\EnviarLembretePontoJob;

/**
 * `ponto:lembretes-push` — lembrete de bater ponto conforme a escala (ADR 0423 §5-§6).
 *
 * Roda a cada 5 min pelo scheduler (Kernel). Para cada business com aparelho ativo, acha os
 * colaboradores cuja escala de HOJE tem um horário em [agora+antecedência, +janela) e que
 * ainda não fizeram aquela marcação; grava `ponto_push_envios` (único por dia+tipo) e
 * despacha o Job com o `$businessId`.
 *
 * Tier 0 ([ADR 0093]): sem sessão, toda query filtra `business_id` explicitamente.
 * Horários no fuso da aplicação (`app.timezone`), o mesmo das marcações.
 */
class LembretesPushCommand extends Command
{
    protected $signature = 'ponto:lembretes-push';

    protected $description = 'Envia o lembrete de bater ponto aos aparelhos dos colaboradores (ADR 0423).';

    /** coluna do turno => tipo da marcação */
    private const HORARIOS = [
        'hora_entrada' => Marcacao::TIPO_ENTRADA,
        'hora_almoco_inicio' => Marcacao::TIPO_ALMOCO_INICIO,
        'hora_almoco_fim' => Marcacao::TIPO_ALMOCO_FIM,
        'hora_saida' => Marcacao::TIPO_SAIDA,
    ];

    public function handle(): int
    {
        if (! config('pontowr2.push.enabled')) {
            $this->line('Lembrete por push desligado (PONTO_PUSH_ENABLED=false).');

            return self::SUCCESS;
        }

        $agora = now();
        $inicio = $agora->copy()->addMinutes((int) config('pontowr2.push.antecedencia_minutos', 5));
        $fim = $inicio->copy()->addMinutes((int) config('pontowr2.push.janela_minutos', 5));
        if (! $inicio->isSameDay($fim)) {
            return self::SUCCESS; // janela atravessa a meia-noite: fora do escopo da v1
        }

        $businesses = DB::table('ponto_push_dispositivos')->where('ativo', true)->distinct()->pluck('business_id');
        $despachados = 0;

        foreach ($businesses as $businessId) {
            foreach ($this->turnosDeHoje((int) $businessId, $agora->dayOfWeek) as $linha) {
                foreach (self::HORARIOS as $coluna => $tipo) {
                    $hora = $linha->{$coluna};
                    if ($hora === null || $hora < $inicio->format('H:i:s') || $hora >= $fim->format('H:i:s')) {
                        continue;
                    }
                    if ($this->jaMarcou((int) $businessId, (int) $linha->colaborador_id, $tipo, $agora->toDateString())) {
                        continue;
                    }

                    $gravou = DB::table('ponto_push_envios')->insertOrIgnore([
                        'business_id' => (int) $businessId,
                        'user_id' => (int) $linha->user_id,
                        'data' => $agora->toDateString(),
                        'tipo' => $tipo,
                    ]);
                    if ($gravou === 1) {
                        EnviarLembretePontoJob::dispatch((int) $businessId, (int) $linha->user_id, $tipo, substr($hora, 0, 5));
                        $despachados++;
                    }
                }
            }
        }

        $this->info("Lembretes despachados: {$despachados}");

        return self::SUCCESS;
    }

    /** Colaboradores ativos do business, com aparelho ativo, e o turno da escala para hoje. */
    private function turnosDeHoje(int $businessId, int $diaSemana)
    {
        return DB::table('ponto_colaborador_config as c')
            ->join('ponto_escalas as e', function ($j) {
                $j->on('e.id', '=', 'c.escala_atual_id')->on('e.business_id', '=', 'c.business_id');
            })
            ->join('ponto_escala_turnos as t', 't.escala_id', '=', 'e.id')
            ->where('c.business_id', $businessId)
            ->where('c.controla_ponto', true)
            ->whereNull('c.desligamento')
            ->where('t.dia_semana', $diaSemana)
            ->whereExists(function ($q) {
                $q->from('ponto_push_dispositivos as d')
                    ->whereColumn('d.business_id', 'c.business_id')
                    ->whereColumn('d.user_id', 'c.user_id')
                    ->where('d.ativo', true);
            })
            ->get(['c.id as colaborador_id', 'c.user_id', 't.hora_entrada', 't.hora_almoco_inicio', 't.hora_almoco_fim', 't.hora_saida']);
    }

    private function jaMarcou(int $businessId, int $colaboradorId, string $tipo, string $data): bool
    {
        return DB::table('ponto_marcacoes')
            ->where('business_id', $businessId)
            ->where('colaborador_config_id', $colaboradorId)
            ->where('tipo', $tipo)
            ->whereDate('momento', $data)
            ->where('origem', '!=', Marcacao::ORIGEM_ANULACAO)
            ->exists();
    }
}
