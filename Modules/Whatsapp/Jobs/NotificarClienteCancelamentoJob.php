<?php

declare(strict_types=1);

namespace Modules\Whatsapp\Jobs;

use App\Contact;
use App\Transaction;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Modules\Whatsapp\Entities\WhatsappBusinessPhone;
use Modules\Whatsapp\Templates\CancelamentoVendaTemplate;

/**
 * US-SELL-034 — Notifica cliente do cancelamento de uma venda via WhatsApp.
 *
 * Disparado pelo side-effect CancelarVendaCascade após cancelar NFes +
 * liberar reservas. Best-effort: falha de notificação não desfaz
 * cancelamento (já estaria em stage=cancelled com history audit).
 *
 * Multi-tenant Tier 0 (ADR 0093): $businessId no constructor — guard
 * defensivo confere `$transaction->business_id === $this->businessId` e
 * apenas loga (sem throw) em caso de mismatch pra não retryar infinito.
 *
 * Multi-números (ADR 0117): Resolve phone via
 * `WhatsappBusinessPhone::resolveForEvent($bizId, 'outbound_default')` —
 * notificação de venda usa o phone outbound default do business; quando
 * houver flag dedicada `handles_sell_status` (futura US), trocar aqui.
 *
 * Fluxo:
 *   1. Carrega Transaction + multi-tenant guard
 *   2. Carrega Contact (walk-in/null contact → skip)
 *   3. Resolve phone: mobile → landline → (fallback email — TODO)
 *   4. (TODO LGPD) Verifica consent — tabela contacts sem coluna hoje
 *   5. Renderiza template CancelamentoVendaTemplate
 *   6. Dispatch SendWhatsappMessageJob (kind=freeform)
 *
 * @see memory/requisitos/Whatsapp/SPEC.md US-SELL-034
 */
class NotificarClienteCancelamentoJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 2;

    public int $backoff = 30;

    public function __construct(
        public readonly int $businessId,
        public readonly int $transactionId,
        public readonly string $motivo,
    ) {}

    public function handle(): void
    {
        $transaction = Transaction::find($this->transactionId);

        if ($transaction === null) {
            Log::info('[whatsapp.notify_cancelamento] transaction não encontrada — skip', [
                'business_id' => $this->businessId,
                'transaction_id' => $this->transactionId,
            ]);
            return;
        }

        // Multi-tenant guard Tier 0 (ADR 0093): mismatch jamais aceita,
        // mas NÃO lança exception pra evitar retry infinito (best-effort).
        if ((int) $transaction->business_id !== $this->businessId) {
            Log::error('[whatsapp.notify_cancelamento] cross-tenant mismatch — abort', [
                'expected_business_id' => $this->businessId,
                'actual_business_id' => (int) $transaction->business_id,
                'transaction_id' => $this->transactionId,
            ]);
            return;
        }

        if (empty($transaction->contact_id)) {
            Log::info('[whatsapp.notify_cancelamento] venda sem contact_id (walk-in) — skip', [
                'business_id' => $this->businessId,
                'transaction_id' => $this->transactionId,
            ]);
            return;
        }

        $contact = Contact::find($transaction->contact_id);
        if ($contact === null) {
            Log::info('[whatsapp.notify_cancelamento] contact não encontrado — skip', [
                'business_id' => $this->businessId,
                'transaction_id' => $this->transactionId,
                'contact_id' => (int) $transaction->contact_id,
            ]);
            return;
        }

        // Resolve telefone: mobile → landline
        $phoneNumber = $this->resolvePhone($contact);

        // TODO US-LGPD-XXX: verificar consent WhatsApp do contact.
        // Tabela `contacts` HOJE não tem coluna whatsapp_consent / marketing_opt_in.
        // Quando a US LGPD adicionar a coluna, validar aqui ANTES de dispatch.
        // (Skipping check porque coluna inexistente — comportamento mantém best-effort.)

        if ($phoneNumber === null) {
            // CASCADE-NOTIFY-002 — fallback email quando contact sem telefone.
            // Best-effort: falha SMTP só loga, não retryar infinito.
            $this->tryFallbackEmail($transaction, $contact);
            return;
        }

        // Resolve qual número Whatsapp atende (ADR 0117 §Q2).
        // Não há flag `handles_sell_status` ainda — usar resolveForEvent com
        // evento conhecido falharia. Aqui buscamos diretamente phone marcado
        // como `handles_outbound_default=true`.
        $phone = $this->resolveOutboundDefaultPhone($this->businessId);
        if ($phone === null) {
            Log::info('[whatsapp.notify_cancelamento] business sem phone outbound_default — skip', [
                'business_id' => $this->businessId,
                'transaction_id' => $this->transactionId,
            ]);
            return;
        }

        $body = CancelamentoVendaTemplate::render($transaction, $contact, $this->motivo);

        SendWhatsappMessageJob::dispatch(
            $this->businessId,
            $phone->id,
            $phoneNumber,
            'freeform',
            ['body' => $body],
        );
    }

    private function resolvePhone(Contact $contact): ?string
    {
        $mobile = trim((string) ($contact->mobile ?? ''));
        if ($mobile !== '') {
            return $mobile;
        }
        $landline = trim((string) ($contact->landline ?? ''));
        if ($landline !== '') {
            return $landline;
        }

        return null;
    }

    private function resolveOutboundDefaultPhone(int $businessId): ?WhatsappBusinessPhone
    {
        // SUPERADMIN: job assíncrono sem session() — bypass global scope com
        // where('business_id') explícito (defensivo Tier 0).
        return WhatsappBusinessPhone::withoutGlobalScopes()
            ->where('business_id', $businessId)
            ->where('handles_outbound_default', true)
            ->orderBy('id')
            ->first();
    }

    /**
     * CASCADE-NOTIFY-002 — Fallback email quando WhatsApp não disponível.
     *
     * Best-effort: falha SMTP só loga; cancelamento já está confirmado
     * via FSM history audit. Sem PII em logs (apenas IDs).
     *
     * TODO US-LGPD-XXX: validar coluna `email_consent` em contacts quando
     * adicionada. Por ora consent implícito (mesma postura WhatsApp).
     */
    private function tryFallbackEmail(Transaction $transaction, Contact $contact): void
    {
        $email = trim((string) ($contact->email ?? ''));

        if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            Log::warning('[whatsapp.notify_cancelamento] contact sem phone E sem email válido — notificação manual necessária', [
                'business_id' => $this->businessId,
                'transaction_id' => $this->transactionId,
                'contact_id' => (int) $contact->id,
            ]);
            // TODO US futura: emit Event ClienteSemCanal pra workflow humano
            return;
        }

        $body = CancelamentoVendaTemplate::render($transaction, $contact, $this->motivo);
        $subject = "Venda #{$transaction->invoice_no} cancelada";

        try {
            Mail::raw($body, function ($message) use ($email, $subject) {
                $message->to($email)->subject($subject);
            });

            Log::info('[whatsapp.notify_cancelamento] fallback email enviado', [
                'business_id' => $this->businessId,
                'transaction_id' => $this->transactionId,
                'contact_id' => (int) $contact->id,
            ]);
        } catch (\Throwable $e) {
            Log::error('[whatsapp.notify_cancelamento] falha fallback email — sem retry (best-effort)', [
                'business_id' => $this->businessId,
                'transaction_id' => $this->transactionId,
                'contact_id' => (int) $contact->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
