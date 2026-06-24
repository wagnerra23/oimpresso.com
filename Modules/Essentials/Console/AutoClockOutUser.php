<?php

namespace Modules\Essentials\Console;

use App\Business;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Modules\Essentials\Entities\EssentialsAttendance;
use Modules\Essentials\Entities\Shift;
use Modules\Jana\Scopes\ScopeByBusiness;

/**
 * Auto clock-out de funcionários que esqueceram de bater a saída.
 *
 * Agendado `everyThirtyMinutes` (apenas env=live) via EssentialsServiceProvider::registerScheduleCommands().
 *
 * ⚠️ Multi-tenant Tier 0 (ADR 0093): este command roda em CLI, SEM auth()/session(),
 * então o global scope `ScopeByBusiness` NÃO filtra nada (ver ScopeByBusiness::apply()
 * — `if (! auth()->check()) return;`). A versão legada fazia um UPDATE de massa em
 * `essentials_attendances` SEM `business_id`, batendo a saída de funcionários de TODOS
 * os businesses de uma vez (vazamento cross-tenant — pior bug possível neste projeto).
 *
 * Além disso, `essentials_shifts.auto_clockout_time` é uma HORA LOCAL DE PAREDE (coluna
 * `time`) e `business.time_zone` varia por tenant. Comparar contra `Carbon::now()` no
 * fuso default do CLI marcava o ponto na hora ERRADA pra qualquer business fora desse
 * fuso. Por isso o loop seta o fuso de cada business antes de comparar — mesmo padrão
 * canônico de App\Console\Commands\RecurringInvoice (`date_default_timezone_set($business->time_zone)`).
 *
 * @see ScopeByBusiness — global scope é no-op em CLI por design
 * @see App\Console\Commands\RecurringInvoice — padrão per-business timezone em command agendado
 */
class AutoClockOutUser extends Command
{
    /**
     * The console command name.
     *
     * @var string
     */
    protected $signature = 'pos:autoClockOutUser';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Bate a saída automática (auto clock-out) por business, respeitando o fuso de cada tenant';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $tzOriginal = date_default_timezone_get();
        $totalBaixados = 0;
        $businessIds = collect();

        try {
            // Businesses que têm AO MENOS UM shift com auto clock-out habilitado.
            // withoutGlobalScope: em CLI o scope já é no-op, mas explicitar documenta
            // a intenção e mantém o command correto se um dia rodar em contexto autenticado.
            // CLI multi-tenant (ADR 0093): escopo aplicado manualmente por business no loop.
            $businessIds = Shift::withoutGlobalScope(ScopeByBusiness::class)
                ->where('is_allowed_auto_clockout', 1)
                ->whereNotNull('auto_clockout_time')
                ->distinct()
                ->pluck('business_id');

            foreach ($businessIds as $businessId) {
                $business = Business::find($businessId);
                if (! $business) {
                    continue;
                }

                try {
                    // Fuso local do business — auto_clockout_time é hora de parede local.
                    $tz = ! empty($business->time_zone) ? $business->time_zone : config('app.timezone');
                    date_default_timezone_set($tz);

                    $agora = Carbon::now($tz);
                    $janelaFim = $agora->copy()->addMinutes(30);

                    // auto_clockout_time é HORA DE PAREDE (coluna `time`), comparada como
                    // hora-do-dia no fuso do business. Os limites são strings 'HH:MM:SS'
                    // zero-padded, então comparação de string == comparação cronológica.
                    $janelaInicio = $agora->toTimeString();
                    $janelaFimStr = $janelaFim->toTimeString();

                    // Funcionários ainda "clocados" (sem clock_out_time) cujo shift do
                    // PRÓPRIO business tem auto_clockout_time dentro da janela [agora, agora+30min].
                    // Escopo explícito por business_id nas DUAS tabelas (attendance + shift).
                    $pendentes = EssentialsAttendance::withoutGlobalScope(ScopeByBusiness::class)
                        ->where('essentials_attendances.business_id', $businessId)
                        ->join('essentials_shifts as es', 'essentials_attendances.essentials_shift_id', '=', 'es.id')
                        ->where('es.business_id', $businessId)
                        ->where('es.is_allowed_auto_clockout', 1)
                        ->whereNull('essentials_attendances.clock_out_time')
                        ->where(function ($q) use ($janelaInicio, $janelaFimStr) {
                            if ($janelaInicio <= $janelaFimStr) {
                                // Janela normal dentro do mesmo dia.
                                $q->whereBetween('es.auto_clockout_time', [$janelaInicio, $janelaFimStr]);
                            } else {
                                // WRAP DE MEIA-NOITE (ex: agora=23:30 → janela [23:30:00, 00:00:00]):
                                // no MySQL `BETWEEN low AND high` com low > high retorna SEMPRE vazio,
                                // então o shift em (23:30, 00:00) era ignorado e a saída nunca batia.
                                // Parte a janela em duas faixas: [inicio, 23:59:59] OR [00:00:00, fim].
                                $q->whereBetween('es.auto_clockout_time', [$janelaInicio, '23:59:59'])
                                    ->orWhereBetween('es.auto_clockout_time', ['00:00:00', $janelaFimStr]);
                            }
                        })
                        ->select('essentials_attendances.*')
                        ->get();

                    foreach ($pendentes as $attendance) {
                        $attendance->clock_out_time = $agora->toDateTimeString();

                        // Marca a saída como automática (CLT Art. 74 §3): uma batida gerada
                        // pelo sistema precisa ser distinguível de uma batida manual do funcionário.
                        if (empty($attendance->clock_out_note)) {
                            $attendance->clock_out_note = 'Saída automática (auto clock-out do sistema)';
                        }

                        // ->save() (não bulk update) dispara LogsActivity → preserva a trilha
                        // de auditoria LGPD/CLT que o Model declara em getActivitylogOptions().
                        $attendance->save();
                        $totalBaixados++;
                    }
                } catch (\Throwable $e) {
                    Log::error('[pos:autoClockOutUser] falha no business ' . $businessId . ': ' . $e->getMessage(), [
                        'business_id' => $businessId,
                        'exception' => $e->getMessage(),
                    ]);
                }
            }
        } finally {
            // Restaura o fuso global pra não vazar estado entre execuções do scheduler.
            date_default_timezone_set($tzOriginal);
        }

        $this->info("[pos:autoClockOutUser] {$totalBaixados} saída(s) automática(s) registrada(s) em " . $businessIds->count() . ' business(es) elegível(is).');

        return self::SUCCESS;
    }
}
