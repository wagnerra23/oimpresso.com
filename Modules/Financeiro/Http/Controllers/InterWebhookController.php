<?php

namespace Modules\Financeiro\Http\Controllers;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Eduardokum\LaravelBoleto\Webhook\Banco\Inter as InterWebhookParser;
use Eduardokum\LaravelBoleto\Webhook\Boleto as WebhookBoleto;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\Financeiro\Models\BoletoRemessa;
use Modules\Financeiro\Models\ContaBancaria;
use Modules\Financeiro\Models\InterWebhookEvent;
use Modules\Financeiro\Models\Titulo;
use Modules\Financeiro\Models\TituloBaixa;
use Modules\Financeiro\Services\TituloAutoService;

/**
 * Recebe notificações do Banco Inter (cobrança v3).
 *
 * URL pública: POST /financeiro/webhooks/inter/{token}
 * Segredo: o `token` no path é o `fin_contas_bancarias.webhook_token` (64 chars).
 *  - Token vaza → invalidar e gerar novo (re-registrar webhook no Inter).
 *  - Sem token / token inválido → 404 silencioso.
 *
 * Payload (Inter v3, ver Webhook\Banco\Inter da lib eduardokum):
 *   POST {token}
 *   Headers: x-conta-corrente: <numero>
 *   Body: [
 *     {
 *       "nossoNumero": "...",
 *       "seuNumero": "...",
 *       "codigoSolicitacao": "uuid-do-inter",
 *       "situacao": "PAGO|RECEBIDO|MARCADO_RECEBIDO|A_RECEBER|CANCELADO|EXPIRADO",
 *       "dataHoraSituacao": "2026-06-03T07:52:00-03:00",
 *       "valorNominal": 100.00,
 *       "valorTotalRecebimento": 100.00,
 *       "origemRecebimento": "BOLETO|PIX",
 *       "txid": "..."   // só se PIX
 *     },
 *     ...
 *   ]
 *
 * Idempotência: SHA-256 do JSON do item. INSERT em fin_inter_webhook_events
 * com UNIQUE(business_id, event_hash) — duplicado vira no-op.
 *
 * Resposta:
 *  - 200 sempre que processou (ou já tinha processado) — Inter NÃO redelivera.
 *  - 404 se token não bate (Inter loga e pára de tentar pra esse webhook).
 *  - 500 só pra erro infra (DB down) — Inter redelivera.
 */
class InterWebhookController extends Controller
{
    public function receive(Request $request, string $token): JsonResponse
    {
        $conta = ContaBancaria::where('webhook_token', $token)->first();

        if (! $conta) {
            Log::warning('[InterWebhook] token desconhecido', [
                'token_prefix' => substr($token, 0, 8),
                'ip' => $request->ip(),
            ]);

            return response()->json(['error' => 'not_found'], 404);
        }

        $raw = $request->getContent();
        $payload = json_decode($raw, true);

        if (! is_array($payload)) {
            Log::warning('[InterWebhook] payload não é JSON válido', [
                'conta_id' => $conta->id,
                'body_preview' => substr($raw, 0, 500),
            ]);

            return response()->json(['error' => 'invalid_payload'], 400);
        }

        $items = $this->normalizarItems($payload);

        $stats = ['recebidos' => count($items), 'processados' => 0, 'duplicados' => 0, 'sem_boleto' => 0, 'erros' => 0];

        foreach ($items as $item) {
            $resultado = $this->processarItem($conta, $item);
            $stats[$resultado]++;
        }

        Log::info('[InterWebhook] batch processado', [
            'conta_id' => $conta->id,
            'business_id' => $conta->business_id,
            'stats' => $stats,
        ]);

        return response()->json(['status' => 'ok', 'stats' => $stats]);
    }

    private function normalizarItems(array $payload): array
    {
        if (isset($payload[0])) {
            return $payload;
        }
        if (isset($payload['nossoNumero']) || isset($payload['codigoSolicitacao'])) {
            return [$payload];
        }

        return [];
    }

    /**
     * @return string  'processados' | 'duplicados' | 'sem_boleto' | 'erros'
     */
    private function processarItem(ContaBancaria $conta, array $item): string
    {
        $eventHash = hash('sha256', json_encode($item, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        $nossoNumero = $item['nossoNumero'] ?? null;
        $codigoSolicitacao = $item['codigoSolicitacao'] ?? null;
        $situacao = $item['situacao'] ?? 'DESCONHECIDA';

        try {
            $event = InterWebhookEvent::create([
                'business_id' => $conta->business_id,
                'conta_bancaria_id' => $conta->id,
                'event_hash' => $eventHash,
                'nosso_numero' => $nossoNumero,
                'codigo_solicitacao' => $codigoSolicitacao,
                'situacao' => $situacao,
                'origem_recebimento' => $item['origemRecebimento'] ?? null,
                'valor_recebido' => $item['valorTotalRecebimento'] ?? $item['valorNominal'] ?? null,
                'data_situacao' => isset($item['dataHoraSituacao']) ? Carbon::parse($item['dataHoraSituacao']) : null,
                'payload' => $item,
            ]);
        } catch (\Illuminate\Database\QueryException $e) {
            if ($e->getCode() === '23000') {
                return 'duplicados';
            }
            throw $e;
        }

        $remessa = $this->localizarRemessa($conta, $codigoSolicitacao, $nossoNumero);

        if (! $remessa) {
            $event->processed_at = now();
            $event->processed_status = InterWebhookEvent::STATUS_BOLETO_NAO_ENCONTRADO;
            $event->processed_error = "codigo_solicitacao={$codigoSolicitacao} / nosso_numero={$nossoNumero} não encontrado em fin_boleto_remessas do business {$conta->business_id}";
            $event->save();

            return 'sem_boleto';
        }

        $event->boleto_remessa_id = $remessa->id;

        try {
            DB::transaction(function () use ($event, $remessa, $situacao, $conta) {
                if (InterWebhookEvent::ehSituacaoPaga($situacao)) {
                    $this->registrarBaixa($remessa, $event, $conta);
                } elseif ($situacao === InterWebhookEvent::SITUACAO_CANCELADO) {
                    $remessa->status = BoletoRemessa::STATUS_CANCELADO;
                    $remessa->save();
                } elseif ($situacao === InterWebhookEvent::SITUACAO_EXPIRADO) {
                    $remessa->status = BoletoRemessa::STATUS_VENCIDO;
                    $remessa->save();
                } else {
                    $event->processed_status = InterWebhookEvent::STATUS_IGNORADO;
                }

                $event->processed_at = now();
                if ($event->processed_status === null) {
                    $event->processed_status = InterWebhookEvent::STATUS_OK;
                }
                $event->save();
            });
        } catch (\Throwable $e) {
            $event->processed_at = now();
            $event->processed_status = InterWebhookEvent::STATUS_ERRO_BAIXA;
            $event->processed_error = $e->getMessage();
            $event->save();

            Log::error('[InterWebhook] erro processando item', [
                'event_id' => $event->id,
                'remessa_id' => $remessa->id,
                'erro' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return 'erros';
        }

        return 'processados';
    }

    private function localizarRemessa(ContaBancaria $conta, ?string $codigoSolicitacao, ?string $nossoNumero): ?BoletoRemessa
    {
        $base = BoletoRemessa::where('business_id', $conta->business_id)
            ->where('conta_bancaria_id', $conta->id);

        if ($codigoSolicitacao) {
            $r = (clone $base)
                ->where('metadata->codigo_solicitacao', $codigoSolicitacao)
                ->first();
            if ($r) {
                return $r;
            }
        }

        if ($nossoNumero) {
            return (clone $base)->where('nosso_numero', $nossoNumero)->first();
        }

        return null;
    }

    private function registrarBaixa(BoletoRemessa $remessa, InterWebhookEvent $event, ContaBancaria $conta): void
    {
        if ($remessa->status === BoletoRemessa::STATUS_PAGO) {
            $event->processed_status = InterWebhookEvent::STATUS_DUPLICADO;

            return;
        }

        $titulo = Titulo::where('business_id', $conta->business_id)
            ->where('id', $remessa->titulo_id)
            ->first();

        if (! $titulo) {
            throw new \RuntimeException("Titulo {$remessa->titulo_id} não existe pra remessa {$remessa->id}");
        }

        $valorPago = (float) ($event->valor_recebido ?? $remessa->valor_total);
        $idempotencyKey = "inter_webhook:{$event->id}";

        $baixa = TituloBaixa::create([
            'business_id' => $conta->business_id,
            'titulo_id' => $titulo->id,
            'conta_bancaria_id' => $conta->id,
            'valor_baixa' => $valorPago,
            'juros' => 0,
            'multa' => 0,
            'desconto' => 0,
            'data_baixa' => $event->data_situacao?->toDateString() ?? today()->toDateString(),
            'meio_pagamento' => $event->origem_recebimento === 'PIX' ? 'pix' : 'boleto',
            'idempotency_key' => $idempotencyKey,
            'observacoes' => "Baixa automática via webhook Inter (event #{$event->id}).",
            'created_by' => null,
        ]);

        $event->titulo_baixa_id = $baixa->id;

        $remessa->status = BoletoRemessa::STATUS_PAGO;
        $remessa->pago_em = $event->data_situacao ?? now();
        $metadata = $remessa->metadata ?? [];
        $metadata['baixa'] = [
            'event_id' => $event->id,
            'baixa_id' => $baixa->id,
            'valor' => $valorPago,
            'origem' => $event->origem_recebimento,
            'em' => now()->toIso8601String(),
        ];
        $remessa->metadata = $metadata;
        $remessa->save();

        $titulo->valor_aberto = max(0, (float) $titulo->valor_aberto - $valorPago);
        $titulo->status = $titulo->valor_aberto <= 0
            ? 'quitado'
            : ($titulo->valor_aberto < (float) $titulo->valor_total ? 'parcial' : $titulo->status);
        $titulo->save();
    }
}
