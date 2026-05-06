<?php

declare(strict_types=1);

namespace Modules\NfeBrasil\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Modules\NfeBrasil\Events\NFeAutorizada;
use Modules\NfeBrasil\Mail\DanfeNotaFiscalMail;
use Modules\NfeBrasil\Services\DanfeService;
use Modules\RecurringBilling\Models\Invoice;
use Throwable;

/**
 * US-NFE-044 fase 2 · Envia DANFE PDF + XML autorizado por e-mail ao
 * destinatário quando NFe é autorizada.
 *
 * Resolve destinatário (email) via Invoice→Contact:
 *   - Vai em rb_invoices buscar pelo `transaction_id` da emissão
 *   - Carrega `contact()->email`
 *   - Sem email → log warning + skip (não retenta)
 *
 * DANFE bytes via `DanfeService::lerOuGerar()` (lazy: gera se ainda não foi).
 *
 * Flag: `nfebrasil.email_danfe_on_autorizada` (default `true` quando há flow
 * automático de cobrança recorrente; pode desligar pra emissão manual via UI).
 *
 * Fila: 'nfe' (mesma do listener Invoice→NFe — operação de email é leve mas
 * benefits from queue async porque envolve Mail driver remoto).
 *
 * Falha de email NÃO afeta NFe autorizada (já está fiscalmente correta).
 * Throwable é re-throwado pra retry da queue (3 tries, backoff 60s).
 *
 * Pra emissões NÃO vindas de Invoice (ex: emissão manual via UI futura),
 * Listener faz no-op silencioso (sem transaction_id em rb_invoices).
 */
class EnviarDanfePorEmail implements ShouldQueue
{
    public string $queue = 'nfe';
    public int $tries = 3;
    public int $backoff = 60;

    public function __construct(
        private readonly ?DanfeService $danfe = null,
    ) {}

    public function handle(NFeAutorizada $event): void
    {
        if (! config('nfebrasil.email_danfe_on_autorizada', true)) {
            Log::info('EnviarDanfePorEmail: flag desabilitada — no-op', [
                'emissao_id' => $event->emissao->id,
            ]);
            return;
        }

        $emissao = $event->emissao;

        if (! $emissao->isAutorizada() || ! $emissao->chave_44) {
            Log::warning('EnviarDanfePorEmail: emissão não está autorizada — skip', [
                'emissao_id' => $emissao->id,
                'status'     => $emissao->status,
            ]);
            return;
        }

        // Resolve destinatário via Invoice→Contact
        $email = $this->resolverEmail($emissao);
        if (! $email) {
            Log::info('EnviarDanfePorEmail: sem email do destinatário — skip', [
                'emissao_id'     => $emissao->id,
                'transaction_id' => $emissao->transaction_id,
            ]);
            return;
        }

        // PDF + XML
        $danfeService = $this->danfe ?? app(DanfeService::class);

        try {
            $pdfBytes = $danfeService->lerOuGerar($emissao);
        } catch (Throwable $e) {
            Log::error('EnviarDanfePorEmail: falha ao obter DANFE PDF', [
                'emissao_id' => $emissao->id,
                'error'      => $e->getMessage(),
            ]);
            throw $e; // queue retenta
        }

        if ($pdfBytes === null) {
            Log::warning('EnviarDanfePorEmail: DANFE não disponível — skip envio', [
                'emissao_id' => $emissao->id,
            ]);
            return;
        }

        $xmlString = $emissao->xml_path && Storage::exists($emissao->xml_path)
            ? Storage::get($emissao->xml_path)
            : '';

        try {
            Mail::to($email)->send(new DanfeNotaFiscalMail(
                emissao:        $emissao,
                danfePdfBytes:  $pdfBytes,
                xmlString:      $xmlString,
            ));
        } catch (Throwable $e) {
            Log::error('EnviarDanfePorEmail: falha no envio', [
                'emissao_id' => $emissao->id,
                'email'      => $email,
                'error'      => $e->getMessage(),
            ]);
            throw $e;
        }

        Log::info('EnviarDanfePorEmail: DANFE enviado', [
            'emissao_id' => $emissao->id,
            'chave_44'   => $emissao->chave_44,
            'email'      => $email,
        ]);
    }

    /**
     * Resolve email do destinatário via Invoice→Contact (caso recorrência).
     * Retorna null se não há `transaction_id` ou Invoice/Contact sem email.
     */
    private function resolverEmail(\Modules\NfeBrasil\Models\NfeEmissao $emissao): ?string
    {
        if (! $emissao->transaction_id) {
            return null;
        }

        $invoice = Invoice::with('contact')
            ->where('business_id', $emissao->business_id)
            ->where('id', $emissao->transaction_id)
            ->first();

        $email = $invoice?->contact?->email;
        return $email && filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : null;
    }

    public function failed(NFeAutorizada $event, Throwable $e): void
    {
        Log::error('EnviarDanfePorEmail: failed após retries', [
            'emissao_id' => $event->emissao->id,
            'chave_44'   => $event->emissao->chave_44,
            'error'      => $e->getMessage(),
        ]);
    }
}
