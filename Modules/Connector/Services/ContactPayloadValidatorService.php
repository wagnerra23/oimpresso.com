<?php

declare(strict_types=1);

namespace Modules\Connector\Services;

use App\Util\OtelHelper;
use Illuminate\Support\Facades\DB;

/**
 * ContactPayloadValidatorService — Wave 18 D5.a.
 *
 * Centraliza pré-validação de payload de Contact (POS Android / Delphi / Woo)
 * ANTES de hit Controller. Hoje ficava espalhado entre `ContactController` e
 * `Crm/FollowUpController`. Trazê-lo pra Service permite:
 *   - Pest Unit test isolado (sem subir HTTP kernel)
 *   - Reuso pelo `DelphiSyncService` quando POS sincroniza contatos batch
 *   - Métricas OTel `connector.contact.validate.*` pra observability hot-path
 *
 * Tier 0 IRREVOGÁVEL (ADR 0093): caller passa `$businessId` resolvido pelo
 * token Passport. Service NUNCA aceita biz_id do input cliente.
 *
 * Regras canônicas:
 *   - CNPJ (tax_number): 14 dígitos numéricos OU 11 (CPF). Demais formatos rejeitados.
 *   - Email: max 191 chars, formato RFC 5321 simples.
 *   - Mobile: 11 dígitos (DDD+9 ou DDD+8 sem 9 inicial). PII — caller logs com PiiRedactor.
 *   - `contact_id`: único por business — duplicate detection via DB lookup OPCIONAL.
 *
 * @see Modules\Connector\Http\Controllers\Api\ContactController
 * @see Modules\Connector\Services\DelphiSyncService
 */
class ContactPayloadValidatorService
{
    /**
     * Verifica se `tax_number` é CNPJ (14) ou CPF (11) válido (só formato — não DV).
     */
    public function isValidTaxNumber(?string $taxNumber): bool
    {
        if ($taxNumber === null || $taxNumber === '') {
            return true; // null aceito (contato sem doc fiscal)
        }
        $digits = preg_replace('/\D/', '', $taxNumber);

        return in_array(strlen($digits), [11, 14], true);
    }

    /**
     * Verifica formato básico de email RFC 5321.
     */
    public function isValidEmail(?string $email): bool
    {
        if ($email === null || $email === '') {
            return true;
        }

        return strlen($email) <= 191
            && filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }

    /**
     * Verifica se mobile é DDD+8 ou DDD+9 dígitos.
     */
    public function isValidMobileBr(?string $mobile): bool
    {
        if ($mobile === null || $mobile === '') {
            return true;
        }
        $digits = preg_replace('/\D/', '', $mobile);

        return in_array(strlen($digits), [10, 11], true);
    }

    /**
     * Detecta duplicate de `contact_id` no business (lookup direto na tabela).
     *
     * Retorna true se NÃO existe duplicate (ok pra criar).
     */
    public function isContactIdAvailable(int $businessId, string $contactId): bool
    {
        return OtelHelper::spanBiz('connector.contact.validate.contact_id_check', function () use ($businessId, $contactId) {
            $exists = DB::table('contacts')
                ->where('business_id', $businessId)
                ->where('contact_id', $contactId)
                ->exists();

            return ! $exists;
        }, ['business_id' => $businessId]);
    }

    /**
     * Valida payload completo retornando array de erros (vazio = OK).
     *
     * @return array<int, string>
     */
    public function validatePayload(int $businessId, array $payload): array
    {
        return OtelHelper::spanBiz('connector.contact.validate.payload', function () use ($businessId, $payload) {
            $errors = [];

            if (! $this->isValidTaxNumber($payload['tax_number'] ?? null)) {
                $errors[] = 'tax_number inválido (deve ter 11 ou 14 dígitos).';
            }
            if (! $this->isValidEmail($payload['email'] ?? null)) {
                $errors[] = 'email inválido.';
            }
            if (! $this->isValidMobileBr($payload['mobile'] ?? null)) {
                $errors[] = 'mobile inválido (DDD + 8 ou 9 dígitos).';
            }
            if (! empty($payload['contact_id'])
                && ! $this->isContactIdAvailable($businessId, (string) $payload['contact_id'])) {
                $errors[] = "contact_id '{$payload['contact_id']}' já existe no business.";
            }

            return $errors;
        }, [
            'business_id'   => $businessId,
            'errors_count'  => 0, // será sobrescrito? OTel não suporta — manter constante
        ]);
    }
}
