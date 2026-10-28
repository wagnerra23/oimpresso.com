<?php

declare(strict_types=1);

namespace Modules\Marketplaces\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Marketplaces\Entities\ContaVendedor;
use Modules\Marketplaces\Entities\Marketplace;
use Modules\Marketplaces\Entities\WebhookRecebido;

/**
 * Recebe as notificações da Nuvemshop (ADR 0443, fatia b).
 *
 * Diferente do Mercado Livre, a Nuvemshop ASSINA: `x-linkedstore-hmac-sha256` = HMAC-SHA256 do
 * corpo cru com o client_secret do app. Sem assinatura válida nada é gravado (401). A empresa sai
 * da conta conectada àquela loja (`store_id`), nunca da requisição.
 *
 * Duas portas:
 *  - eventos de loja (`order/paid`, `product/updated`...), corpo `{store_id, event, id}`: grava o
 *    log PENDENTE para a fila tratar (pedido vira venda é a fatia c);
 *  - webhooks LGPD obrigatórios, um por URL, corpo sem `event`: `store/redact` desconecta a conta e
 *    apaga o token; `customers/redact` e `customers/data_request` ficam PENDENTES — o primeiro
 *    espera a decisão do [W] registrada na ADR 0443, o segundo é a empresa que atende.
 *
 * O log é append-only (trigger recusa UPDATE/DELETE), então e-mail, telefone e documento do
 * comprador que vêm nos webhooks LGPD NUNCA entram nele: guarda só os ids.
 * A Nuvemshop espera resposta em 3 s e reenvia até 16 vezes: responde rápido, processa na fila.
 */
class NotificacoesNuvemshopController extends Controller
{
    public const LGPD = ['store/redact', 'customers/redact', 'customers/data_request'];

    public function receber(Request $request, ?string $lgpd = null): JsonResponse
    {
        if (! config('services.nuvemshop.ativo', false)) {
            return response()->json(['ok' => false], 404);
        }

        $segredo = (string) config('services.nuvemshop.client_secret', '');
        $assinatura = (string) $request->header('x-linkedstore-hmac-sha256', '');
        if ($segredo === '' || $assinatura === '' || ! hash_equals(hash_hmac('sha256', $request->getContent(), $segredo), $assinatura)) {
            return response()->json(['ok' => false], 401);
        }

        $dados = $request->json()->all();
        $loja = (string) ($dados['store_id'] ?? '');
        $evento = $lgpd ?? (string) ($dados['event'] ?? '');
        if ($loja === '' || $evento === '' || ($lgpd === null && ! preg_match('#^[a-z_]+/[a-z_]+$#', $evento))) {
            return response()->json(['ok' => false], 400);
        }

        $canal = Marketplace::query()->where('slug', Marketplace::NUVEMSHOP)->first();
        if ($canal === null) {
            return response()->json(['ok' => true]);
        }

        // SUPERADMIN: chamada da Nuvemshop, sem sessão; a empresa vem de cada conta ligada a esta loja.
        $contas = ContaVendedor::withoutGlobalScopes()
            ->where('marketplace_id', $canal->id)
            ->where('seller_id_external', $loja)
            ->when($evento !== 'store/redact', fn ($q) => $q->where('status', ContaVendedor::STATUS_ATIVA))
            ->get();

        foreach ($contas as $conta) {
            $status = WebhookRecebido::PENDENTE;
            if ($evento === 'store/redact') {
                $conta->forceFill(['status' => ContaVendedor::STATUS_REVOGADA, 'access_token' => null])->save();
                $status = WebhookRecebido::PROCESSADO;
            }

            // SUPERADMIN: webhook público sem sessão; a empresa vem da conta e entra explícita.
            WebhookRecebido::withoutGlobalScopes()->create([
                'business_id' => (int) $conta->business_id,
                'marketplace_id' => (int) $canal->id,
                'topic' => mb_substr($evento, 0, 100),
                'resource_external' => isset($dados['id']) ? mb_substr('/'.explode('/', $evento)[0].'s/'.$dados['id'], 0, 500) : null,
                'payload' => $this->semDadosPessoais($dados),
                'signature_valid' => true,
                'processing_status' => $status,
                'received_at' => now(),
            ]);
        }

        return response()->json(['ok' => true]);
    }

    /**
     * Tira do corpo tudo que identifica a pessoa (e-mail, telefone, documento), mantendo o que a
     * empresa precisa para atender: id do cliente e ids de pedidos.
     *
     * @param  array<string, mixed>  $dados
     * @return array<string, mixed>
     */
    private function semDadosPessoais(array $dados): array
    {
        if (isset($dados['customer']) && is_array($dados['customer'])) {
            $dados['customer'] = array_intersect_key($dados['customer'], ['id' => true]);
        }

        return $dados;
    }
}
