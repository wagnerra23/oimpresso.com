<?php

declare(strict_types=1);

namespace Modules\Producao\Services;

use App\Contracts\Atendimento\AvisaClienteWhatsapp;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Manda o link da prova ao cliente por WhatsApp (US-COMVIS-NEW-004 · UC-PCART-06).
 *
 * Chamado depois que o link é gerado (`solicitar`) ou trocado (`trocar`). O envio é do Modules/Whatsapp
 * pelo contrato `AvisaClienteWhatsapp` (canal, cliente da mesma empresa, opt-out LGPD e celular são
 * conferidos lá). Aqui só se acha o cliente da OS: OS por item → venda → `contact_id`, sempre filtrando
 * pelo `business_id` (Tier 0, ADR 0093).
 *
 * Nunca lança: o link já foi gerado e o operador pode copiá-lo. Devolve null com a flag desligada; senão
 * `{enviado, motivo}`, para a tela avisar "contate o cliente manualmente" quando não saiu.
 */
final class EnvioDaProva
{
    /** @return array{enviado: bool, motivo: ?string}|null */
    public function enviar(int $businessId, int $ordemId, string $link, Carbon $expiraEm): ?array
    {
        if (! config('producao.aprovacao_arte.whatsapp')) {
            return null;
        }
        if (! app()->bound(AvisaClienteWhatsapp::class)) {
            return ['enviado' => false, 'motivo' => 'sem_canal'];
        }

        $venda = DB::table('pcp_ordens as o')
            ->join('transactions as t', fn ($j) => $j->on('t.id', '=', 'o.transaction_id')->where('t.business_id', $businessId))
            ->where('o.business_id', $businessId)->where('o.id', $ordemId)
            ->first(['t.contact_id', 't.invoice_no']);
        if ($venda === null || $venda->contact_id === null) {
            return ['enviado' => false, 'motivo' => 'sem_cliente'];
        }

        $nome = (string) (DB::table('contacts')->where('business_id', $businessId)->where('id', (int) $venda->contact_id)->value('name') ?? '');
        $primeiro = trim(explode(' ', trim($nome))[0]);
        $texto = sprintf(
            "%s A prova da arte do seu pedido%s está pronta. Veja e aprove por aqui (ou peça ajuste): %s\nO link vale até %s.",
            $primeiro !== '' ? "Olá, {$primeiro}!" : 'Olá!',
            $venda->invoice_no ? ' '.$venda->invoice_no : '',
            $link,
            $expiraEm->format('d/m/Y'),
        );

        try {
            return app(AvisaClienteWhatsapp::class)->avisar($businessId, (int) $venda->contact_id, $texto);
        } catch (\Throwable $e) {
            Log::warning('[producao.envio_prova] falha ao enviar o link da prova', ['business_id' => $businessId, 'ordem_id' => $ordemId, 'erro' => $e::class]);

            return ['enviado' => false, 'motivo' => 'falha_no_envio'];
        }
    }
}
