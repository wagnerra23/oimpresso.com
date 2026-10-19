<?php

declare(strict_types=1);

namespace Modules\Fisio\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Modules\Fisio\Contracts\LinkDoPortal;
use Modules\Fisio\Entities\Program;
use Modules\Fisio\Services\FisioAcesso;
use Modules\Fisio\Services\Whatsapp\EnvioWhatsappFisio;
use Modules\Fisio\Services\Whatsapp\MensagensFisio;
use Modules\Whatsapp\Jobs\SendWhatsappMessageJob;

/**
 * US-FISIO-009 · envia ao paciente o link do portal e o PIN pelo módulo `Whatsapp` (UC-FPS-11/12).
 *
 * Molde: `Modules/OficinaAuto/Jobs/EnviarLinkAprovacaoWhatsappJob` — duas mensagens freeform pelo
 * número de saída padrão da empresa, link primeiro e PIN atrasado (nunca juntos). O Fisio não
 * escolhe driver: o `SendWhatsappMessageJob` do módulo resolve o driver do número, com fallback.
 *
 * Guardas, nesta ordem (nada sai se qualquer uma falhar): pacote `fisio_module` da empresa →
 * programa ativo da MESMA empresa (cross-tenant só loga, sem throw, para não retentar) → paciente
 * não bloqueado → consentimento de WhatsApp vigente → telefone e opt-in do contato → número de
 * saída ativo no módulo Whatsapp (sem ele, o envio é manual pelo `wa.me` na ficha) → reserva
 * idempotente do dia. Só depois da reserva o link do portal é emitido (emitir revoga o anterior).
 *
 * `businessId` no constructor: fila não tem sessão (ADR 0093).
 */
class EnviarProgramaWhatsappJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public int $backoff = 60;

    public function __construct(
        public readonly int $businessId,
        public readonly int $programId,
    ) {}

    public function handle(): void
    {
        $envio = app(EnvioWhatsappFisio::class);
        $ctx = ['business_id' => $this->businessId, 'program_id' => $this->programId];

        if (! app(FisioAcesso::class)->pacoteAtivo($this->businessId)) {
            Log::info('[fisio.whatsapp.programa] empresa sem o pacote fisio_module — skip', $ctx);

            return;
        }

        // SUPERADMIN: job sem sessão — filtro explícito por business_id (Tier 0, ADR 0093).
        $programa = Program::query()->withoutGlobalScopes()
            ->where('business_id', $this->businessId)
            ->where('id', $this->programId)
            ->where('status', Program::STATUS_ATIVO)
            ->first();
        // SUPERADMIN: job sem sessão — paciente lido com o business_id do programa já conferido acima.
        $paciente = $programa?->patient()->withoutGlobalScopes()->where('business_id', $this->businessId)->first();
        if ($programa === null || $paciente === null || $paciente->estaBloqueado()) {
            Log::info('[fisio.whatsapp.programa] programa inativo, de outra empresa ou paciente bloqueado — skip', $ctx);

            return;
        }

        if (! $envio->consentimentoVigente($paciente)) {
            Log::info('[fisio.whatsapp.programa] sem consentimento de WhatsApp vigente — skip (LGPD)', $ctx);

            return;
        }

        $para = $envio->telefoneDoPaciente($paciente);
        $telefone = $envio->canalAtivo($this->businessId);
        if ($para === null || $telefone === null) {
            Log::info('[fisio.whatsapp.programa] sem telefone do paciente ou sem canal ativo no módulo Whatsapp — envio manual (wa.me)', $ctx);

            return;
        }

        $reserva = $envio->reservar($paciente, EnvioWhatsappFisio::PROGRAMA, fn () => app(LinkDoPortal::class)->emitir($paciente), ['program_id' => (int) $programa->id]);
        if ($reserva === null) {
            Log::info('[fisio.whatsapp.programa] já enviado hoje ou portal indisponível — skip', $ctx);

            return;
        }

        $nome = $envio->primeiroNome($paciente);
        $clinica = $envio->nomeDaClinica($this->businessId);
        $saida = $reserva['saida'];

        SendWhatsappMessageJob::dispatch($this->businessId, (int) $telefone->id, $para, 'freeform',
            ['body' => MensagensFisio::link($nome, $clinica, $reserva['dados']['url'])])
            ->delay($saida);

        SendWhatsappMessageJob::dispatch($this->businessId, (int) $telefone->id, $para, 'freeform',
            ['body' => MensagensFisio::pin($reserva['dados']['pin'])])
            ->delay($saida->copy()->addSeconds((int) config('fisio.whatsapp.pin_delay_segundos', 60)));

        Log::info('[fisio.whatsapp.programa] link + PIN enfileirados', $ctx + ['patient_id' => (int) $paciente->id, 'phone_id' => (int) $telefone->id]);
    }

    /** @return list<string> */
    public function tags(): array
    {
        return ["business:{$this->businessId}", "fisio_program:{$this->programId}", 'fisio:whatsapp_programa'];
    }
}