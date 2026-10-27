<?php

declare(strict_types=1);

namespace Modules\Whatsapp\Listeners;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\ComunicacaoVisual\Events\InstalacaoAgendada;
use Modules\Whatsapp\Services\Notify\NotificadorClienteWhatsapp;

/**
 * Listener — instalação da CV agendada → avisa o cliente por WhatsApp (US-COMVIS-007 · UC-FLX-21).
 *
 * Mesmo formato do `NotifyRepairCustomer`: ouve o evento do módulo dono, monta o texto e entrega ao
 * `NotificadorClienteWhatsapp` (US-WA-319), que enfileira — nada é enviado ao daemon no request.
 *
 * - **Canal:** o canal de saída padrão da empresa (`handles_outbound_default`), no schema omnichannel.
 *   Até 2026-10-26 resolvia por `whatsapp_business_phones`, que tem 0 linhas em produção (medido),
 *   e o aviso nunca saía. Canal caído (`disconnected`/`banned`) não envia.
 * - **Mensagem:** texto livre com OS, dia e hora. Não leva nome de quem instala nem veículo.
 * - **Cliente:** OS → orçamento → `contato_id`, sempre filtrando `business_id` da instalação. OS sem
 *   orçamento, orçamento balcão (sem contato) ou contato de outra empresa: não avisa.
 * - **LGPD:** o notificador só envia se `Contact::canReceiveWhatsappNotification()` (opt-out
 *   explícito bloqueia; null permite, como no resto do sistema) e se houver celular.
 *
 * Toda falta de pré-condição sai em log info e retorna, sem exceção: a agenda já gravou e não pode
 * falhar por causa do aviso.
 *
 * Tier 0 (ADR 0093): o evento traz `businessId`; toda leitura filtra por ele.
 */
class NotifyInstalacaoCvCustomer
{
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

        $nome = (string) (DB::table('contacts')->where('business_id', $biz)->where('id', (int) $inst->contato_id)->value('name') ?? '');
        $quando = Carbon::parse($inst->data_agendada);
        $primeiroNome = trim(explode(' ', trim($nome))[0]);
        $texto = sprintf(
            '%s a instalação da OS %s está agendada para %s às %s. Se precisar mudar, responda esta mensagem.',
            $primeiroNome !== '' ? "Olá, {$primeiroNome}!" : 'Olá!',
            $inst->numero,
            $quando->format('d/m/Y'),
            $quando->format('H:i'),
        );

        // Canal, cliente da mesma empresa, LGPD e celular: o notificador confere (US-WA-319).
        app(NotificadorClienteWhatsapp::class)->notificar($biz, (int) $inst->contato_id, 'outbound_default', $texto);
    }
}
