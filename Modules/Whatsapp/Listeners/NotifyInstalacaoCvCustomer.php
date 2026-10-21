<?php

declare(strict_types=1);

namespace Modules\Whatsapp\Listeners;

use App\Contact;
use App\Scopes\ScopeByBusiness;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\ComunicacaoVisual\Events\InstalacaoAgendada;
use Modules\Whatsapp\Entities\WhatsappBusinessPhone;
use Modules\Whatsapp\Jobs\SendWhatsappMessageJob;

/**
 * Listener — instalação da CV agendada → avisa o cliente por WhatsApp (US-COMVIS-007 · UC-FLX-21).
 *
 * Mesmo formato do `NotifyRepairCustomer`: ouve o evento do módulo dono, resolve número e cliente,
 * e enfileira `SendWhatsappMessageJob` — nada é enviado no request.
 *
 * - **Canal:** o número de saída padrão da empresa (`handles_outbound_default`), o mesmo critério do
 *   Fisio e da OficinaAuto. Canal caído (`disconnected`/`banned`) não envia.
 * - **Mensagem:** texto livre com OS, dia e hora. Não leva nome de quem instala nem veículo.
 * - **Cliente:** OS → orçamento → `contato_id`, sempre filtrando `business_id` da instalação. OS sem
 *   orçamento, orçamento balcão (sem contato) ou contato de outra empresa: não avisa.
 * - **LGPD:** só envia se `Contact::canReceiveWhatsappNotification()` (opt-out explícito bloqueia;
 *   null permite, como no resto do sistema) e se houver celular.
 *
 * Toda falta de pré-condição sai em log info e retorna, sem exceção: a agenda já gravou e não pode
 * falhar por causa do aviso.
 *
 * Tier 0 (ADR 0093): o evento traz `businessId`; toda leitura filtra por ele.
 */
class NotifyInstalacaoCvCustomer
{
    /** Saúde em que o módulo Whatsapp não entrega nem pelo fallback. */
    private const CAIDO = ['disconnected', 'banned'];

    public function handle(InstalacaoAgendada $event): void
    {
        $biz = $event->businessId;
        $ctx = ['business_id' => $biz, 'instalacao_id' => $event->instalacaoId];

        $inst = DB::table('cv_instalacoes as i')
            ->join('comvis_os as o', fn ($j) => $j->on('o.id', '=', 'i.os_id')->where('o.business_id', $biz))
            ->leftJoin('comvis_orcamentos as orc', fn ($j) => $j->on('orc.id', '=', 'o.orcamento_id')->where('orc.business_id', $biz))
            ->where('i.business_id', $biz)
            ->where('i.id', $event->instalacaoId)
            ->first(['o.numero', 'orc.contato_id', 'i.data_agendada']);
        if ($inst === null || $inst->contato_id === null) {
            Log::info('[whatsapp.notify_instalacao_cv] instalação sem cliente identificado — skip', $ctx);

            return;
        }

        // SUPERADMIN: evento pode vir de job sem sessão; filtro explícito por business_id (ADR 0093).
        $phone = WhatsappBusinessPhone::query()
            ->withoutGlobalScope(ScopeByBusiness::class)
            ->where('business_id', $biz)
            ->where('handles_outbound_default', true)
            ->orderBy('id')
            ->first();
        if ($phone === null || in_array((string) $phone->driver_health, self::CAIDO, true)) {
            Log::info('[whatsapp.notify_instalacao_cv] sem número de saída ativo — skip', $ctx);

            return;
        }

        $contato = Contact::query()->where('business_id', $biz)->where('id', (int) $inst->contato_id)->first();
        if ($contato === null || ! $contato->canReceiveWhatsappNotification()) {
            Log::info('[whatsapp.notify_instalacao_cv] cliente fora da empresa ou sem consentimento de WhatsApp — skip', $ctx);

            return;
        }
        $para = trim((string) ($contato->mobile ?? ''));
        if ($para === '') {
            Log::info('[whatsapp.notify_instalacao_cv] cliente sem celular — skip', $ctx);

            return;
        }

        $quando = Carbon::parse($inst->data_agendada);
        $primeiroNome = trim(explode(' ', trim((string) $contato->name))[0]);
        $texto = sprintf(
            '%s a instalação da OS %s está agendada para %s às %s. Se precisar mudar, responda esta mensagem.',
            $primeiroNome !== '' ? "Olá, {$primeiroNome}!" : 'Olá!',
            $inst->numero,
            $quando->format('d/m/Y'),
            $quando->format('H:i'),
        );

        SendWhatsappMessageJob::dispatch($biz, (int) $phone->id, $para, 'freeform', ['body' => $texto]);
    }
}
