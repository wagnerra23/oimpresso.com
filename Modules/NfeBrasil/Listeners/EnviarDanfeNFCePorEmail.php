<?php

declare(strict_types=1);

namespace Modules\NfeBrasil\Listeners;

use App\Transaction;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Modules\NfeBrasil\Events\NFCeAutorizada;
use Modules\NfeBrasil\Mail\DanfeNotaFiscalMail;
use Modules\NfeBrasil\Models\NfeEmissao;
use Modules\NfeBrasil\Services\DanfeService;
use Throwable;

/**
 * US-NFE-002 fase 2B parcial · Envia DANFE NFC-e PDF + XML por e-mail ao
 * consumidor quando NFC-e (modelo 65) é autorizada.
 *
 * **Diferença vs `EnviarDanfePorEmail` (NFe 55):**
 *   - NFe 55 resolve email via `rb_invoices.contact_id` (cobrança recorrente)
 *   - NFC-e 65 resolve email via `transactions.contact_id` (venda balcão POS)
 *   - Mesmo `DanfeNotaFiscalMail` é reusado (template é genérico — só usa numero/
 *     serie/chave_44/valor_total/emitido_em, sem amarração ao modelo)
 *
 * Resolve destinatário (email):
 *   - `Transaction::find($emissao->transaction_id)`
 *   - Cross-tenant guard: `tx.business_id === emissao.business_id`
 *   - `tx->contact->email` válido (filter_var FILTER_VALIDATE_EMAIL)
 *   - Sem email (consumidor anônimo / "consumidor final" sem cadastro) → no-op
 *     silencioso. NFC-e B2C frequentemente não tem email — caso normal, não erro.
 *
 * Flag: `nfebrasil.email_danfe_nfce_on_autorizada` (default `false` — opt-in).
 * Razão default false: NFC-e venda balcão muitas vezes é "consumidor final"
 * sem email, e mesmo quando tem email, é comum o consumidor preferir DANFE
 * impresso no balcão (NFC-e tem QR code suficiente). Cliente liga via UI
 * de configuração quando quer envio automático.
 *
 * Fila: 'nfe' (mesma das demais — share queue/retry policy).
 *
 * Falha de email NÃO afeta NFC-e autorizada (já está fiscalmente correta).
 * Throwable é re-throwado pra retry da queue (3 tries, backoff 60s).
 *
 * @see Modules/NfeBrasil/Listeners/EnviarDanfePorEmail.php (pattern NFe55)
 */
class EnviarDanfeNFCePorEmail implements ShouldQueue
{
    public string $queue = 'nfe';
    public int $tries = 3;
    public int $backoff = 60;

    public function __construct(
        private readonly ?DanfeService $danfe = null,
    ) {}

    public function handle(NFCeAutorizada $event): void
    {
        if (! config('nfebrasil.email_danfe_nfce_on_autorizada', false)) {
            Log::info('EnviarDanfeNFCePorEmail: flag desabilitada — no-op', [
                'emissao_id' => $event->emissao->id,
            ]);
            return;
        }

        $emissao = $event->emissao;

        if ((int) $emissao->modelo !== 65) {
            Log::warning('EnviarDanfeNFCePorEmail: emissão não é modelo 65 — skip', [
                'emissao_id' => $emissao->id,
                'modelo'     => $emissao->modelo,
            ]);
            return;
        }

        if (! $emissao->isAutorizada() || ! $emissao->chave_44) {
            Log::warning('EnviarDanfeNFCePorEmail: emissão não está autorizada — skip', [
                'emissao_id' => $emissao->id,
                'status'     => $emissao->status,
            ]);
            return;
        }

        $email = $this->resolverEmail($emissao);
        if (! $email) {
            // NFC-e consumidor anônimo é caso normal — info, não warning.
            Log::info('EnviarDanfeNFCePorEmail: sem email do consumidor — skip', [
                'emissao_id'     => $emissao->id,
                'transaction_id' => $emissao->transaction_id,
            ]);
            return;
        }

        $danfeService = $this->danfe ?? app(DanfeService::class);

        try {
            $pdfBytes = $danfeService->lerOuGerar($emissao);
        } catch (Throwable $e) {
            Log::error('EnviarDanfeNFCePorEmail: falha ao obter DANFE NFC-e PDF', [
                'emissao_id' => $emissao->id,
                'error'      => $e->getMessage(),
            ]);
            throw $e;
        }

        if ($pdfBytes === null) {
            Log::warning('EnviarDanfeNFCePorEmail: DANFE NFC-e não disponível — skip envio', [
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
            Log::error('EnviarDanfeNFCePorEmail: falha no envio', [
                'emissao_id' => $emissao->id,
                'email'      => $email,
                'error'      => $e->getMessage(),
            ]);
            throw $e;
        }

        Log::info('EnviarDanfeNFCePorEmail: DANFE NFC-e enviado', [
            'emissao_id' => $emissao->id,
            'chave_44'   => $emissao->chave_44,
            'email'      => $email,
        ]);
    }

    /**
     * Resolve email do consumidor via Transaction→Contact (venda POS).
     * Retorna null se:
     *   - emissão sem transaction_id (raro mas possível em NFC-e manual)
     *   - Transaction não encontrada
     *   - Cross-tenant (tx.business_id != emissao.business_id) — guard de seg
     *   - Contact sem email ou email inválido
     */
    private function resolverEmail(NfeEmissao $emissao): ?string
    {
        if (! $emissao->transaction_id) {
            return null;
        }

        $tx = Transaction::with('contact')->find($emissao->transaction_id);

        if (! $tx || (int) $tx->business_id !== (int) $emissao->business_id) {
            return null;
        }

        $email = $tx->contact?->email;
        return $email && filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : null;
    }

    public function failed(NFCeAutorizada $event, Throwable $e): void
    {
        Log::error('EnviarDanfeNFCePorEmail: failed após retries', [
            'emissao_id' => $event->emissao->id,
            'chave_44'   => $event->emissao->chave_44,
            'error'      => $e->getMessage(),
        ]);
    }
}
