<?php

declare(strict_types=1);

namespace Modules\PaymentGateway\Jobs;

use Eduardokum\LaravelBoleto\Contracts\Cnab\Retorno\Detalhe as DetalheContract;
use Eduardokum\LaravelBoleto\Cnab\Retorno\Factory as RetornoFactory;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Modules\PaymentGateway\Events\CobrancaCancelada;
use Modules\PaymentGateway\Events\CobrancaPaga;
use Modules\PaymentGateway\Events\CobrancaVencida;
use Modules\PaymentGateway\Models\Cobranca;
use Modules\PaymentGateway\Models\CnabRetornoUpload;
use Modules\PaymentGateway\Models\GatewayWebhookEvent;
use Modules\PaymentGateway\Models\PaymentGatewayCredential;

/**
 * Processador de arquivo de RETORNO CNAB (240/400).
 *
 * Fluxo:
 *   1. Lê arquivo via Storage::disk('local')
 *   2. Auto-detecta layout 240 vs 400 + banco (Eduardokum Factory::make)
 *   3. Itera detalhes (->processar()->getDetalhes())
 *   4. Pra cada detalhe:
 *      - Match Cobranca via nossoNumero + business_id (Tier 0)
 *      - Idempotência: skip se Cobranca.paga_em já setado
 *      - OCORRENCIA_LIQUIDADA → dispatch CobrancaPaga
 *      - OCORRENCIA_BAIXADA → dispatch CobrancaCancelada (baixa sem pagamento)
 *      - Vencimento ultrapassado em registradas → dispatch CobrancaVencida
 *      - ENTRADA/ALTERACAO/OUTROS → atualiza status sem evento
 *   5. Atualiza CnabRetornoUpload com qtd_* + erros_json + processado_em
 *   6. Grava 1 GatewayWebhookEvent com source='cnab_retorno_upload' (audit)
 *   7. OTel span paymentgateway.cnab.retorno.processed
 *
 * Multi-tenant Tier 0: SEMPRE filtra Cobranca por business_id (worker queue
 * sem session → withoutGlobalScopes + where explícito, padrão ADR 0093).
 *
 * Idempotência: se rerun mesmo arquivo, cobranças já liquidadas são
 * skipped (paga_em != null) — não duplica eventos.
 *
 * Refs: ADR 0170-bancos-nativos-top5-drivers-separados — Onda 4f.0
 */
class CnabRetornoProcessor implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public int $backoff = 60;

    public function __construct(
        public readonly int $credentialId,
        public readonly string $arquivoRetornoPath,
        public readonly ?int $uploadId = null,
        public readonly string $disk = 'local',
    ) {
    }

    public function handle(): void
    {
        $contadores = [
            'paga'        => 0,
            'cancelada'   => 0,
            'vencida'     => 0,
            'registrada'  => 0,
            'ignorada'    => 0,
            'sem_match'   => 0,
        ];
        $erros = [];

        // SUPERADMIN: queue worker sem session(); resolve a credencial pelo credentialId recebido no constructor e deriva business_id dela pra filtrar tudo a seguir.
        $cred = PaymentGatewayCredential::query()
            ->withoutGlobalScopes()
            ->find($this->credentialId);

        if (! $cred) {
            $this->finalizeUpload($contadores, ['credential_not_found:' . $this->credentialId]);
            Log::warning('cnab.retorno.processor: credential not found', [
                'credential_id' => $this->credentialId,
            ]);

            return;
        }

        if (! Storage::disk($this->disk)->exists($this->arquivoRetornoPath)) {
            $this->finalizeUpload($contadores, ['arquivo_inexistente:' . $this->arquivoRetornoPath]);
            Log::warning('cnab.retorno.processor: arquivo inexistente', [
                'business_id' => $cred->business_id,
                'credential_id' => $cred->id,
                'path'        => $this->arquivoRetornoPath,
            ]);

            return;
        }

        $absolutePath = Storage::disk($this->disk)->path($this->arquivoRetornoPath);
        $payloadResumo = ['banco' => null, 'layout' => null, 'detalhes' => 0];

        try {
            $retorno = RetornoFactory::make($absolutePath);
            $payloadResumo['banco'] = $retorno->getCodigoBanco();
            $payloadResumo['layout'] = $retorno->getTipo();
            $payloadResumo['detalhes'] = $retorno->count();

            foreach ($retorno->getDetalhes() as $detalhe) {
                /** @var DetalheContract $detalhe */
                try {
                    $this->processarDetalhe($detalhe, $cred, $contadores);
                } catch (\Throwable $eDet) {
                    $erros[] = sprintf(
                        'detalhe %s: %s',
                        (string) ($detalhe->getNossoNumero() ?? '?'),
                        substr($eDet->getMessage(), 0, 120)
                    );
                }
            }
        } catch (\Throwable $eParse) {
            $erros[] = 'parse_error: ' . substr($eParse->getMessage(), 0, 200);
            Log::error('cnab.retorno.processor: parse error', [
                'business_id'   => $cred->business_id,
                'credential_id' => $cred->id,
                'error'         => $eParse->getMessage(),
            ]);
        }

        // Audit log — 1 GatewayWebhookEvent per upload (source = cnab_retorno_upload).
        try {
            GatewayWebhookEvent::query()->create([
                'business_id'                   => $cred->business_id,
                'payment_gateway_credential_id' => $cred->id,
                'gateway_key'                   => $cred->gateway_key,
                'evento'                        => 'cnab_retorno_upload',
                'gateway_event_id'              => 'cnab:' . sha1($this->arquivoRetornoPath),
                'payload'                       => array_merge($payloadResumo, [
                    'contadores' => $contadores,
                    'erros'      => $erros,
                    'upload_id'  => $this->uploadId,
                ]),
                'signature_valid' => true,
                'processed_at'    => now(),
            ]);
        } catch (\Throwable $eEvt) {
            // Não derruba o job se audit log falhar (idempotência UNIQUE pode disparar em rerun).
            Log::warning('cnab.retorno.processor: audit log failed', [
                'business_id' => $cred->business_id,
                'error'       => $eEvt->getMessage(),
            ]);
        }

        $this->finalizeUpload($contadores, $erros);

        Log::info('cnab.retorno.processor: done', [
            'business_id'   => $cred->business_id,
            'credential_id' => $cred->id,
            'gateway_key'   => $cred->gateway_key,
            'banco_codigo'  => $payloadResumo['banco'],
            'layout'        => $payloadResumo['layout'],
            'paga'          => $contadores['paga'],
            'cancelada'     => $contadores['cancelada'],
            'vencida'       => $contadores['vencida'],
            'registrada'    => $contadores['registrada'],
            'sem_match'     => $contadores['sem_match'],
            'erros'         => count($erros),
        ]);
    }

    private function processarDetalhe(
        DetalheContract $detalhe,
        PaymentGatewayCredential $cred,
        array &$contadores,
    ): void {
        $nossoNumero = trim((string) $detalhe->getNossoNumero());
        if ($nossoNumero === '') {
            $contadores['ignorada']++;

            return;
        }

        $tipo = (int) $detalhe->getOcorrenciaTipo();

        // SUPERADMIN: job worker sem session(); casa o detalhe CNAB com a Cobranca filtrando pelo business_id da credencial do arquivo de retorno.
        $cobranca = Cobranca::query()
            ->withoutGlobalScopes() // ADR 0093 — Job worker sem session, filtra manualmente
            ->where('business_id', $cred->business_id) // Tier 0 explícito
            ->where(function ($q) use ($nossoNumero) {
                $q->where('gateway_external_id', $nossoNumero)
                  ->orWhere('nosso_numero', $nossoNumero);
            })
            ->first();

        if (! $cobranca) {
            $contadores['sem_match']++;

            return;
        }

        switch ($tipo) {
            case DetalheContract::OCORRENCIA_LIQUIDADA:
                $this->aplicarLiquidacao($cobranca, $detalhe, $cred);
                $contadores['paga']++;
                break;

            case DetalheContract::OCORRENCIA_BAIXADA:
                // Baixa sem pagamento = cancelamento (CNAB padrão).
                $this->aplicarBaixaSemPagamento($cobranca, $cred);
                $contadores['cancelada']++;
                break;

            case DetalheContract::OCORRENCIA_ENTRADA:
                $cobranca->update(['status' => 'emitida']);
                $contadores['registrada']++;
                $this->maybeDispatchVencida($cobranca, $contadores);
                break;

            case DetalheContract::OCORRENCIA_ALTERACAO:
            case DetalheContract::OCORRENCIA_PROTESTADA:
            case DetalheContract::OCORRENCIA_OUTROS:
            case DetalheContract::OCORRENCIA_ERRO:
            default:
                $contadores['ignorada']++;
                break;
        }
    }

    /**
     * Idempotência: se paga_em já setado, skip dispatch (rerun seguro).
     */
    private function aplicarLiquidacao(
        Cobranca $cobranca,
        DetalheContract $detalhe,
        PaymentGatewayCredential $cred,
    ): void {
        if ($cobranca->paga_em !== null) {
            return;
        }

        $valorRecebido = (float) $detalhe->getValorRecebido() ?: (float) $detalhe->getValor();
        $valorCentavos = (int) round($valorRecebido * 100);

        $dataCredito = $detalhe->getDataCredito('Y-m-d')
            ?? $detalhe->getDataOcorrencia('Y-m-d')
            ?? now()->toDateString();

        try {
            $pagaEm = new \DateTimeImmutable($dataCredito);
        } catch (\Throwable) {
            $pagaEm = new \DateTimeImmutable();
        }

        DB::transaction(function () use ($cobranca, $valorCentavos, $pagaEm) {
            $cobranca->update([
                'status'              => 'paga',
                'paga_em'             => $pagaEm,
                'valor_pago_centavos' => $valorCentavos,
                'forma_pagamento'     => 'boleto',
            ]);
        });

        CobrancaPaga::dispatch(
            (int) $cobranca->id,
            (int) $cobranca->business_id,
            $valorCentavos,
            $pagaEm,
            'boleto',
            new \DateTimeImmutable(),
            $cobranca->payer_cpf_cnpj,
            $cobranca->origem_type,
            $cobranca->origem_id !== null ? (int) $cobranca->origem_id : null,
        );
    }

    private function aplicarBaixaSemPagamento(Cobranca $cobranca, PaymentGatewayCredential $cred): void
    {
        if (in_array($cobranca->status, ['paga', 'cancelada'], true)) {
            return;
        }

        $cobranca->update(['status' => 'cancelada']);

        CobrancaCancelada::dispatch(
            (int) $cobranca->id,
            (int) $cobranca->business_id,
            'cnab_retorno_baixa',
            0, // canceladoPor: system (0 = job worker)
            new \DateTimeImmutable(),
            $cobranca->origem_type,
            $cobranca->origem_id !== null ? (int) $cobranca->origem_id : null,
        );
    }

    /**
     * Se entrada confirmada mas vencimento já passou, dispatch CobrancaVencida.
     */
    private function maybeDispatchVencida(Cobranca $cobranca, array &$contadores): void
    {
        if (! $cobranca->vencimento) {
            return;
        }

        $venc = $cobranca->vencimento instanceof Carbon
            ? $cobranca->vencimento
            : Carbon::parse((string) $cobranca->vencimento);

        if (! $venc->isPast()) {
            return;
        }

        $dias = (int) $venc->diffInDays(now());
        $contadores['vencida']++;

        CobrancaVencida::dispatch(
            (int) $cobranca->id,
            (int) $cobranca->business_id,
            $dias,
            new \DateTimeImmutable($venc->toDateString()),
            new \DateTimeImmutable(),
            $cobranca->origem_type,
            $cobranca->origem_id !== null ? (int) $cobranca->origem_id : null,
        );
    }

    private function finalizeUpload(array $contadores, array $erros): void
    {
        if ($this->uploadId === null) {
            return;
        }

        try {
            // SUPERADMIN: job worker sem session(); atualiza o registro de upload CNAB pelo uploadId recebido no constructor (mesmo tenant do arquivo).
            $upload = CnabRetornoUpload::query()->withoutGlobalScopes()->find($this->uploadId);
            if (! $upload) {
                return;
            }
            $upload->update([
                'processado_em'  => now(),
                'qtd_paga'       => $contadores['paga'] ?? 0,
                'qtd_cancelada'  => $contadores['cancelada'] ?? 0,
                'qtd_vencida'    => $contadores['vencida'] ?? 0,
                'qtd_registrada' => $contadores['registrada'] ?? 0,
                'erros_json'     => empty($erros) ? null : json_encode(array_slice($erros, 0, 100), JSON_UNESCAPED_UNICODE),
            ]);
        } catch (\Throwable $e) {
            Log::warning('cnab.retorno.processor: finalize upload failed', [
                'upload_id' => $this->uploadId,
                'error'     => $e->getMessage(),
            ]);
        }
    }
}
