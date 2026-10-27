<?php

declare(strict_types=1);

namespace Modules\Marketplaces\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Marketplaces\Entities\ContaVendedor;
use Modules\Marketplaces\Entities\Marketplace;
use Modules\Marketplaces\Entities\WebhookRecebido;
use Modules\Marketplaces\Jobs\ProcessarNotificacaoMercadoLivreJob;

/**
 * Recebe as notificações do Mercado Livre (US-MKT-006).
 *
 * A notificação do ML NÃO é assinada: traz `resource`, `user_id`, `topic` e `application_id`.
 * Por isso o conteúdo dela nunca é confiado — serve só de gatilho. A defesa é:
 *  1. `application_id` tem de ser o do nosso aplicativo (outro app → ignorado);
 *  2. `user_id` tem de ser um vendedor CONECTADO; a empresa sai da conta, nunca da requisição;
 *  3. o pedido é buscado na API do ML com o token daquela conta (um `resource` forjado de outro
 *     vendedor devolve erro do ML, não dado).
 *
 * Responde 200 rápido sempre que reconhece o formato — processar fica para a fila. Se a resposta
 * demorar ou falhar, o ML reenvia, e o processamento é idempotente (pedido único por id externo).
 */
class NotificacoesMercadoLivreController extends Controller
{
    public function receber(Request $request): JsonResponse
    {
        $dados = $request->json()->all();
        $topico = (string) ($dados['topic'] ?? '');
        $recurso = (string) ($dados['resource'] ?? '');
        $vendedor = (string) ($dados['user_id'] ?? '');

        if ($topico === '' || $recurso === '' || $vendedor === '' || ! str_starts_with($recurso, '/')) {
            return response()->json(['ok' => false], 400);
        }

        $nossoApp = (string) config('marketplaces.mercado_livre.client_id', '');
        if ($nossoApp === '' || (string) ($dados['application_id'] ?? '') !== $nossoApp) {
            return response()->json(['ok' => true]);
        }

        $ml = Marketplace::query()->where('slug', Marketplace::MERCADO_LIVRE)->first();
        if ($ml === null) {
            return response()->json(['ok' => true]);
        }

        // SUPERADMIN: Sem sessão (chamada do ML): a empresa vem de cada conta conectada deste vendedor.
        $contas = ContaVendedor::withoutGlobalScopes()
            ->where('marketplace_id', $ml->id)
            ->where('seller_id_external', $vendedor)
            ->where('status', ContaVendedor::STATUS_ATIVA)
            ->get(['id', 'business_id']);

        foreach ($contas as $conta) {
            // SUPERADMIN: webhook público sem sessão; a empresa vem da conta conectada e entra explícita na consulta.
            $log = WebhookRecebido::withoutGlobalScopes()->create([
                'business_id' => (int) $conta->business_id,
                'marketplace_id' => (int) $ml->id,
                'topic' => mb_substr($topico, 0, 100),
                'resource_external' => mb_substr($recurso, 0, 500),
                'payload' => $dados,
                'signature_valid' => true,
                'processing_status' => WebhookRecebido::PENDENTE,
                'received_at' => now(),
            ]);

            ProcessarNotificacaoMercadoLivreJob::dispatch((int) $conta->business_id, (int) $conta->id, (int) $log->id);
        }

        return response()->json(['ok' => true]);
    }
}
