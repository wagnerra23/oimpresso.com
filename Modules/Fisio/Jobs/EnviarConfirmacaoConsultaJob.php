<?php

declare(strict_types=1);

namespace Modules\Fisio\Jobs;

use Carbon\CarbonInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Modules\Fisio\Entities\Appointment;
use Modules\Fisio\Entities\Patient;
use Modules\Fisio\Services\Agenda\ConfirmacaoDaConsulta;
use Modules\Fisio\Services\FisioAcesso;
use Modules\Fisio\Services\Whatsapp\EnvioWhatsappFisio;
use Modules\Fisio\Services\Whatsapp\MensagensFisio;
use Modules\Whatsapp\Jobs\SendWhatsappMessageJob;

/**
 * Etapa 04 A6 · confirmação da consulta por WhatsApp, uma empresa por job.
 *
 * Quem recebe: atendimento ainda `agendado` que começa entre `fisio.confirmacao.minimo_horas` e
 * `fisio.confirmacao.horas_antes` à frente, de paciente não bloqueado, com consentimento de
 * WhatsApp vigente e celular com opt-in. Uma mensagem por ATENDIMENTO (idempotência persistida
 * com a chave do atendimento): rodar o job de hora em hora, ou o retry da fila, não repete.
 *
 * Canal e ritmo são os mesmos do lembrete diário (`EnvioWhatsappFisio`): sem número ativo não há
 * envio automático e fica um aviso no log com quantos atendimentos ficaram sem mensagem.
 */
class EnviarConfirmacaoConsultaJob implements ShouldQueue
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
            Log::info('[fisio.whatsapp.confirmacao] empresa sem o pacote fisio_module — skip', $ctx);

            return;
        }

        $confirmacao = app(ConfirmacaoDaConsulta::class);
        $consultas = $confirmacao->pendentes($this->businessId, now());
        if ($consultas === []) {
            return;
        }

        $envio = app(EnvioWhatsappFisio::class);
        $telefone = $envio->canalAtivo($this->businessId);
        if ($telefone === null) {
            Log::warning('[fisio.whatsapp.confirmacao] sem canal ativo no módulo Whatsapp — confirmações não saíram', $ctx + ['consultas' => count($consultas)]);

            return;
        }

        $clinica = $envio->nomeDaClinica($this->businessId);
        $enviados = 0;
        foreach ($consultas as $consulta) {
            // SUPERADMIN: job sem sessão — paciente da MESMA empresa do atendimento (ADR 0093).
            $paciente = Patient::withoutGlobalScopes()->where('business_id', $this->businessId)->find((int) $consulta->patient_id);
            if ($paciente === null || $paciente->blocked_at !== null || ! $envio->consentimentoVigente($paciente)) {
                continue;
            }
            $para = $envio->telefoneDoPaciente($paciente);
            if ($para === null) {
                continue;
            }

            $texto = MensagensFisio::confirmacao(
                $envio->primeiroNome($paciente),
                $clinica,
                self::quando($consulta->starts_at),
                $confirmacao->link($consulta),
            );
            $reserva = $envio->reservar(
                $paciente,
                EnvioWhatsappFisio::CONFIRMACAO,
                fn () => true,
                ['appointment_id' => (int) $consulta->id],
                function ($saida) use ($telefone, $para, $texto) {
                    SendWhatsappMessageJob::dispatch($this->businessId, (int) $telefone->id, $para, 'freeform', ['body' => $texto])
                        ->delay($saida);
                },
                'consulta-'.(int) $consulta->id,
            );
            if ($reserva !== null) {
                $enviados++;
            }
        }

        Log::info('[fisio.whatsapp.confirmacao] confirmações enfileiradas', $ctx + ['enviados' => $enviados]);
    }

    /**
     * "terça, 25/11, às 08:30". Mesmo relógio da agenda: `starts_at` é gravado e comparado no fuso
     * do app, sem conversão (AgendaService, AgendaDoPaciente).
     */
    public static function quando(CarbonInterface $inicio): string
    {
        $local = $inicio->copy()->locale('pt_BR');

        return $local->translatedFormat('l, d/m').', às '.$local->format('H:i');
    }

    /** @return list<string> */
    public function tags(): array
    {
        return ["business:{$this->businessId}", 'fisio:whatsapp_confirmacao'];
    }
}
