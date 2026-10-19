<?php

declare(strict_types=1);

namespace Modules\Fisio\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Modules\Fisio\Entities\Checkin;
use Modules\Fisio\Entities\Patient;
use Modules\Fisio\Entities\Program;
use Modules\Fisio\Services\FisioAcesso;
use Modules\Fisio\Services\Whatsapp\EnvioWhatsappFisio;
use Modules\Fisio\Services\Whatsapp\MensagensFisio;
use Modules\Whatsapp\Jobs\SendWhatsappMessageJob;

/**
 * US-FISIO-009 · lembrete diário dos exercícios por WhatsApp, uma empresa por job (UC-FPN-07, UC-FPS-12).
 *
 * Quem recebe (etapa 02 §3.3, decisão nº 7 do README da suíte): paciente não bloqueado, com
 * programa ativo, consentimento de WhatsApp vigente, telefone com opt-in, e que ainda NÃO
 * registrou o dia. No máximo um lembrete por paciente por dia — idempotência persistida
 * (`EnvioWhatsappFisio::reservar`), então rodar o job de novo, ou retry de fila, não repete.
 *
 * Canal: o número de saída padrão do módulo `Whatsapp`. Sem número ativo (clínica no envio manual
 * `wa.me`, ou canal desconectado/banido) não há lembrete automático — o alerta de canal caído é do
 * módulo Whatsapp (`ChannelHealthSnapshotCommand`), e aqui fica um aviso no log com quantos
 * pacientes ficaram sem lembrete.
 *
 * Cada mensagem sai no horário que `AgendaDeEnvio` der (teto por hora, intervalo aleatório,
 * horário de silêncio), como `delay` do `SendWhatsappMessageJob`.
 */
class EnviarLembreteDiarioJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public int $backoff = 120;

    public function __construct(public readonly int $businessId) {}

    public function handle(): void
    {
        $ctx = ['business_id' => $this->businessId];
        if (! app(FisioAcesso::class)->pacoteAtivo($this->businessId)) {
            Log::info('[fisio.whatsapp.lembrete] empresa sem o pacote fisio_module — skip', $ctx);

            return;
        }

        $envio = app(EnvioWhatsappFisio::class);
        $hoje = $envio->hoje($this->businessId);
        $pacientes = $this->elegiveis($hoje);
        if ($pacientes === []) {
            return;
        }

        $telefone = $envio->canalAtivo($this->businessId);
        if ($telefone === null) {
            Log::warning('[fisio.whatsapp.lembrete] sem canal ativo no módulo Whatsapp — lembretes do dia não saíram (envio manual pelo wa.me)', $ctx + ['pacientes' => count($pacientes)]);

            return;
        }

        $clinica = $envio->nomeDaClinica($this->businessId);
        $enviados = 0;
        foreach ($pacientes as $paciente) {
            if (! $envio->consentimentoVigente($paciente)) {
                continue;
            }
            $para = $envio->telefoneDoPaciente($paciente);
            if ($para === null) {
                continue;
            }
            $reserva = $envio->reservar($paciente, EnvioWhatsappFisio::LEMBRETE, fn () => true);
            if ($reserva === null) {
                continue;
            }

            SendWhatsappMessageJob::dispatch($this->businessId, (int) $telefone->id, $para, 'freeform',
                ['body' => MensagensFisio::lembrete($envio->primeiroNome($paciente), $clinica)])
                ->delay($reserva['saida']);
            $enviados++;
        }

        Log::info('[fisio.whatsapp.lembrete] lembretes enfileirados', $ctx + ['enviados' => $enviados, 'dia' => $hoje]);
    }

    /** @return list<Patient> com programa ativo, não bloqueados e sem check-in hoje */
    private function elegiveis(string $hoje): array
    {
        // SUPERADMIN: job sem sessão — todas as leituras filtram business_id explicitamente (ADR 0093).
        $comPrograma = Program::query()->withoutGlobalScopes()
            ->where('business_id', $this->businessId)
            ->where('status', Program::STATUS_ATIVO)
            ->pluck('patient_id')->map('intval')->unique()->all();
        if ($comPrograma === []) {
            return [];
        }

        // SUPERADMIN: job sem sessão — filtro explícito por business_id (ADR 0093).
        $jaRegistraram = Checkin::query()->withoutGlobalScopes()
            ->where('business_id', $this->businessId)
            ->whereIn('patient_id', $comPrograma)
            ->whereDate('date', $hoje)
            ->pluck('patient_id')->map('intval')->all();

        // SUPERADMIN: job sem sessão — filtro explícito por business_id (ADR 0093).
        return Patient::query()->withoutGlobalScopes()
            ->where('business_id', $this->businessId)
            ->whereIn('id', array_values(array_diff($comPrograma, $jaRegistraram)))
            ->whereNull('blocked_at')
            ->orderBy('id')
            ->get()->all();
    }

    /** @return list<string> */
    public function tags(): array
    {
        return ["business:{$this->businessId}", 'fisio:whatsapp_lembrete'];
    }
}