<?php

declare(strict_types=1);

namespace Modules\Whatsapp\Listeners;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;
use Modules\NfeBrasil\Events\NFeAutorizada;
use Modules\Whatsapp\Services\Notify\NotificadorClienteWhatsapp;

/**
 * NF-e autorizada → DANFE ao cliente por WhatsApp, como anexo (US-WA-321).
 *
 * Par do `EnviarDanfePorEmail` do NfeBrasil, pelo outro canal. O PDF vai por link assinado
 * (`nfe-brasil.danfe-publica`, 7 dias) que o driver busca; o envio passa pelo
 * `NotificadorClienteWhatsapp` (US-WA-319), que aplica número, empresa, LGPD e celular.
 *
 * Cliente = `transactions.contact_id` da venda da emissão, filtrado pela empresa da emissão
 * (Tier 0, ADR 0093). Emissão sem venda: não avisa.
 *
 * Desligado por padrão (`whatsapp.aviso_automatico.danfe_enabled`): ligar é decisão [W].
 * Falha aqui nunca afeta a NF-e, que já está autorizada.
 */
class EnviarDanfePorWhatsapp
{
    public const VALIDADE_DIAS = 7;

    public function handle(NFeAutorizada $event): void
    {
        if (! config('whatsapp.aviso_automatico.danfe_enabled', false)) {
            return;
        }
        $nfe = $event->emissao;
        $biz = (int) $nfe->business_id;
        $ctx = ['business_id' => $biz, 'emissao_id' => $nfe->id];
        if (! $nfe->isAutorizada() || empty($nfe->transaction_id)) {
            Log::info('[whatsapp.danfe] emissão sem venda ou não autorizada — skip', $ctx);

            return;
        }

        $contato = DB::table('transactions')
            ->where('business_id', $biz)
            ->where('id', (int) $nfe->transaction_id)
            ->value('contact_id');
        if (empty($contato)) {
            Log::info('[whatsapp.danfe] venda sem cliente — skip', $ctx);

            return;
        }

        $url = URL::temporarySignedRoute('nfe-brasil.danfe-publica', now()->addDays(self::VALIDADE_DIAS), [
            'business' => $biz,
            'emissao' => (int) $nfe->id,
        ]);
        $numero = $nfe->numero ? "nº {$nfe->numero}" : '';

        try {
            app(NotificadorClienteWhatsapp::class)->notificar(
                $biz,
                (int) $contato,
                'outbound_default',
                trim("Sua nota fiscal {$numero} foi emitida. Segue a DANFE em PDF."),
                [['url' => $url, 'type' => 'document', 'caption' => trim("DANFE NF-e {$numero}")]],
            );
        } catch (\Throwable $e) {
            Log::warning('[whatsapp.danfe] falhou', $ctx + ['erro' => $e->getMessage()]);
        }
    }
}
