<?php

declare(strict_types=1);

namespace Modules\Whatsapp\Templates;

use App\Contact;
use App\Transaction;
use Illuminate\Support\Facades\DB;

/**
 * Template canônico — notificação WhatsApp de cancelamento de venda.
 *
 * US-SELL-034 · CASCADE-NOTIFY-001 · Best-effort PT-BR.
 *
 * Variáveis renderizadas:
 *   - {primeiro_nome}  primeiro segmento de $contact->name
 *   - {invoice_no}     $venda->invoice_no
 *   - {motivo}         param informado pelo cancelador
 *   - {valor}          number_format($venda->final_total, 2, ',', '.')
 *   - {data}           $venda->transaction_date (d/m/Y)
 *   - {business_name}  business.name lookup direto (sem global scope)
 *
 * **Multi-tenant Tier 0 (ADR 0093):**
 * Lookup business_name via query direta na tabela `business` (sem Eloquent model
 * porque pode estar em job sem session()). Filtrado por $venda->business_id —
 * jamais aceita business externo.
 *
 * **LGPD:** sem PII em mensagens de erro/log; apenas IDs no caller.
 *
 * @see memory/requisitos/Whatsapp/SPEC.md US-SELL-034
 */
final class CancelamentoVendaTemplate
{
    public static function render(Transaction $venda, Contact $contact, string $motivo): string
    {
        $primeiroNome = self::primeiroNome((string) ($contact->name ?? ''));
        $invoiceNo = (string) ($venda->invoice_no ?? '');
        $valor = number_format((float) ($venda->final_total ?? 0), 2, ',', '.');

        // $venda->transaction_date pode vir como string ou Carbon (caster legacy).
        $data = self::formatDate($venda->transaction_date ?? null);

        $businessName = self::resolveBusinessName((int) $venda->business_id);

        return <<<TXT
Olá, {$primeiroNome}!

Informamos que sua compra #{$invoiceNo} foi cancelada.

Motivo: {$motivo}

Valor: R$ {$valor}
Data original: {$data}

Em caso de dúvida, entre em contato.

— {$businessName}
TXT;
    }

    private static function primeiroNome(string $nomeCompleto): string
    {
        $nomeCompleto = trim($nomeCompleto);
        if ($nomeCompleto === '') {
            return 'Cliente';
        }
        $parts = preg_split('/\s+/', $nomeCompleto);

        return $parts[0] ?? 'Cliente';
    }

    private static function formatDate(mixed $date): string
    {
        if ($date === null || $date === '') {
            return '';
        }
        try {
            // Aceita Carbon, DateTimeInterface ou string parseável
            if ($date instanceof \DateTimeInterface) {
                return $date->format('d/m/Y');
            }

            return \Carbon\Carbon::parse((string) $date)->format('d/m/Y');
        } catch (\Throwable) {
            return '';
        }
    }

    private static function resolveBusinessName(int $businessId): string
    {
        if ($businessId <= 0) {
            return '';
        }
        $name = DB::table('business')->where('id', $businessId)->value('name');

        return (string) ($name ?? '');
    }
}
