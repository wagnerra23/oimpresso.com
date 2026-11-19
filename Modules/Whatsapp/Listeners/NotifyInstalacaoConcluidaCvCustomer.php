<?php

declare(strict_types=1);

namespace Modules\Whatsapp\Listeners;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\ComunicacaoVisual\Events\InstalacaoConcluida;
use Modules\Whatsapp\Services\Notify\NotificadorClienteWhatsapp;

/**
 * Listener — instalação da CV concluída → manda ao cliente o link do protocolo (US-COMVIS-007 · UC-PCV-25 · 1d).
 *
 * Irmão do `NotifyInstalacaoCvCustomer` (aviso de agendamento), com as mesmas travas:
 * - **Canal:** o de saída padrão da empresa; canal caído não envia.
 * - **Cliente:** OS → orçamento → `contato_id`, sempre filtrando o `business_id` da instalação. OS sem
 *   orçamento, orçamento balcão ou contato de outra empresa: não manda.
 * - **LGPD:** o notificador só envia se `Contact::canReceiveWhatsappNotification()` e se houver celular.
 * - **Mensagem:** OS, dia e hora da conclusão e o link do protocolo (assinado, com validade). Sem nome de
 *   quem instalou nem veículo. Os canais vivos só enviam texto: o link vai no fim da mensagem.
 *
 * Falta de pré-condição sai em log info e retorna, sem exceção: a instalação já foi concluída e não pode
 * falhar por causa do aviso. Tier 0 (ADR 0093): o evento traz `businessId`; toda leitura filtra por ele.
 */
class NotifyInstalacaoConcluidaCvCustomer
{
    public function handle(InstalacaoConcluida $event): void
    {
        $biz = $event->businessId;
        $ctx = ['business_id' => $biz, 'instalacao_id' => $event->instalacaoId];

        $inst = DB::table('cv_instalacoes as i')
            ->join('comvis_os as o', fn ($j) => $j->on('o.id', '=', 'i.os_id')->where('o.business_id', $biz))
            ->leftJoin('comvis_orcamentos as orc', fn ($j) => $j->on('orc.id', '=', 'o.orcamento_id')->where('orc.business_id', $biz))
            ->where('i.business_id', $biz)
            ->where('i.id', $event->instalacaoId)
            ->first(['o.numero', 'orc.contato_id', 'i.data_realizada']);
        if ($inst === null || $inst->contato_id === null) {
            Log::info('[whatsapp.notify_instalacao_concluida_cv] instalação sem cliente identificado — skip', $ctx);

            return;
        }

        $nome = (string) (DB::table('contacts')->where('business_id', $biz)->where('id', (int) $inst->contato_id)->value('name') ?? '');
        $primeiroNome = trim(explode(' ', trim($nome))[0]);
        $quando = Carbon::parse($inst->data_realizada ?? now());
        $texto = sprintf(
            '%s a instalação da OS %s foi concluída em %s às %s. O protocolo com as fotos e a sua assinatura fica disponível por %d dias no link abaixo.',
            $primeiroNome !== '' ? "Olá, {$primeiroNome}!" : 'Olá!',
            $inst->numero,
            $quando->format('d/m/Y'),
            $quando->format('H:i'),
            $event->diasDoLink,
        );

        app(NotificadorClienteWhatsapp::class)->notificar($biz, (int) $inst->contato_id, 'outbound_default', $texto, [
            ['url' => $event->linkProtocolo, 'type' => 'document', 'caption' => 'Protocolo da instalação'],
        ]);
    }
}
