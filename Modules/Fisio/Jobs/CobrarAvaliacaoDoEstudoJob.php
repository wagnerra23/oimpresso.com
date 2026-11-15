<?php

declare(strict_types=1);

namespace Modules\Fisio\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Modules\Fisio\Entities\Patient;
use Modules\Fisio\Entities\StudyParticipant;
use Modules\Fisio\Services\FisioAcesso;
use Modules\Fisio\Services\Pesquisa\EstudoService;
use Modules\Fisio\Services\Whatsapp\EnvioWhatsappFisio;
use Modules\Fisio\Services\Whatsapp\MensagensFisio;
use Modules\Whatsapp\Jobs\SendWhatsappMessageJob;
use Spatie\Activitylog\Models\Activity;

/**
 * Etapa 07 P-05 · aviso por WhatsApp da avaliação do estudo que está em aberto, uma empresa por job.
 *
 * Quem recebe: participante em estudo ativo, paciente não bloqueado, com consentimento de WhatsApp
 * vigente e telefone com opt-in (as mesmas regras do lembrete diário). Um aviso por participante e
 * momento, nunca diário: a janela pode durar semanas. O teto por hora e o horário de silêncio são
 * os do lembrete (`EnvioWhatsappFisio::reservar`).
 *
 * Sem canal ativo no módulo Whatsapp não há aviso automático; o portal mostra o mesmo aviso.
 */
class CobrarAvaliacaoDoEstudoJob implements ShouldQueue
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
            return;
        }
        $envio = app(EnvioWhatsappFisio::class);
        $estudos = app(EstudoService::class);
        $hoje = Carbon::parse($envio->hoje($this->businessId));

        // SUPERADMIN: job sem sessão — filtro explícito por business_id (ADR 0093).
        $pacientes = Patient::withoutGlobalScopes()
            ->where('business_id', $this->businessId)
            ->whereNull('blocked_at')
            ->whereIn('id', StudyParticipant::withoutGlobalScopes() // SUPERADMIN: idem.
                ->where('business_id', $this->businessId)->whereNull('exited_on')->select('patient_id'))
            ->orderBy('id')->get();
        if ($pacientes->isEmpty()) {
            return;
        }
        $telefone = $envio->canalAtivo($this->businessId);
        if ($telefone === null) {
            Log::warning('[fisio.whatsapp.avaliacao_estudo] sem canal ativo — o aviso fica só no portal', $ctx);

            return;
        }
        $clinica = $envio->nomeDaClinica($this->businessId);
        $enviados = 0;
        foreach ($pacientes as $paciente) {
            if (! $envio->consentimentoVigente($paciente) || ($para = $envio->telefoneDoPaciente($paciente)) === null) {
                continue;
            }
            foreach ($estudos->avaliacoesAbertas($paciente, $hoje) as $a) {
                if ($this->jaAvisado($paciente, $a['participant_id'], $a['moment_id'])) {
                    continue;
                }
                $texto = MensagensFisio::avaliacaoEstudo($envio->primeiroNome($paciente), $clinica, $a['momento'], Carbon::parse($a['ate'])->format('d/m'));
                $reserva = $envio->reservar($paciente, EnvioWhatsappFisio::AVALIACAO_ESTUDO, fn () => true,
                    ['participant_id' => $a['participant_id'], 'moment_id' => $a['moment_id']]);
                if ($reserva === null) {
                    break; // já houve um aviso hoje para este paciente; o próximo momento sai amanhã
                }
                SendWhatsappMessageJob::dispatch($this->businessId, (int) $telefone->id, $para, 'freeform', ['body' => $texto])
                    ->delay($reserva['saida']);
                $enviados++;
                break;
            }
        }
        Log::info('[fisio.whatsapp.avaliacao_estudo] avisos enfileirados', $ctx + ['enviados' => $enviados]);
    }

    /** Já saiu aviso deste participante neste momento (em qualquer dia)? */
    private function jaAvisado(Patient $paciente, int $participante, int $momento): bool
    {
        return Activity::query()
            ->where('log_name', EnvioWhatsappFisio::LOG)
            ->where('subject_type', $paciente->getMorphClass())
            ->where('subject_id', (int) $paciente->id)
            ->where('event', 'whatsapp_'.EnvioWhatsappFisio::AVALIACAO_ESTUDO)
            ->get(['properties'])
            ->contains(function ($a) use ($participante, $momento) {
                $p = $a->properties instanceof \Illuminate\Support\Collection ? $a->properties->all() : (array) $a->properties;

                return (int) ($p['participant_id'] ?? 0) === $participante && (int) ($p['moment_id'] ?? 0) === $momento;
            });
    }

    /** @return list<string> */
    public function tags(): array
    {
        return ["business:{$this->businessId}", 'fisio:whatsapp_avaliacao_estudo'];
    }
}
