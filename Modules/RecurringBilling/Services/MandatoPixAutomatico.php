<?php

declare(strict_types=1);

namespace Modules\RecurringBilling\Services;

use Illuminate\Support\Facades\DB;
use Modules\PaymentGateway\Dto\EmitirCobrancaInput;
use Modules\PaymentGateway\Models\PaymentGatewayCredential;
use Modules\PaymentGateway\Services\Drivers\BcbPixDriver;
use Modules\RecurringBilling\Models\Subscription;

/**
 * Jornada de aprovação do mandato do Pix Automático (US-RB-020 · decisão [W] 2026-10-26).
 *
 * Jornada 2 da API Pix BCB v2.10.0 (QR só da recorrência):
 *   1. `POST /locrec` → location do QR;
 *   2. `POST /rec` com o `loc` → recorrência `CRIADA` (o `idRec`);
 *   3. `GET /rec/{idRec}` → `dadosQR.pixCopiaECola`, que o cliente lê no app do banco e aprova.
 * O mandato fica em `rb_subscriptions.metadata.pix_automatico` (`id_rec`, `status`, `pix_copia_cola`);
 * o webhook da recorrência (`AtualizarMandatoDaAssinatura`) troca o status para `APROVADA`, e só
 * aí o `CobradorPixAutomatico` passa a cobrar as faturas.
 *
 * VALOR (regra mestre): o `valorRec` mandado ao banco é o mesmo que o gerador fatura — valor do
 * plano, ou o valor negociado da assinatura quando ela não tem plano (UC-RBSUB-05) — em centavos
 * inteiros. Recorrência ainda `CRIADA` ou `APROVADA` não é recriada: devolve o QR que já existe.
 *
 * Tier 0 (ADR 0093): assinatura, contato e credencial lidos no business informado.
 */
class MandatoPixAutomatico
{
    private const PERIODICIDADE = [
        'monthly' => 'MENSAL', 'quarterly' => 'TRIMESTRAL', 'semiannual' => 'SEMESTRAL', 'yearly' => 'ANUAL',
        'mensal' => 'MENSAL', 'trimestral' => 'TRIMESTRAL', 'semestral' => 'SEMESTRAL', 'anual' => 'ANUAL',
    ];

    public function __construct(private readonly BcbPixDriver $driver) {}

    /**
     * @return array{id_rec: string, status: string, pix_copia_cola: ?string}
     */
    public function solicitar(int $businessId, int $subscriptionId): array
    {
        // SUPERADMIN: o business vem do chamador (sessão no controller) e filtra a busca.
        $sub = Subscription::withoutGlobalScopes()->with('plan')
            ->where('business_id', $businessId)->whereKey($subscriptionId)->firstOrFail();
        if ($sub->getAttribute('status') !== 'active') {
            throw new \DomainException('Só assinatura ativa pede autorização de Pix Automático.');
        }

        $credencial = PaymentGatewayCredential::withoutGlobalScopes()
            ->where('business_id', $businessId)->where('gateway_key', 'bcb_pix')->where('ativo', true)
            ->orderByDesc('id')->first();
        if ($credencial === null) {
            throw new \DomainException('Cadastre a credencial do Pix Automático (BCB) da empresa antes de pedir a autorização.');
        }

        $metadata = (array) ($sub->getAttribute('metadata') ?? []);
        $mandato = (array) ($metadata['pix_automatico'] ?? []);
        if (! empty($mandato['id_rec']) && in_array($mandato['status'] ?? null, ['CRIADA', 'APROVADA'], true)) {
            return $this->resultado($mandato);
        }

        [$centavos, $periodicidade] = $this->valorEPeriodicidade($sub, $metadata);
        $documento = (string) DB::table('contacts')->where('business_id', $businessId)
            ->where('id', (int) $sub->getAttribute('contact_id'))->value('tax_number');

        $loc = $this->driver->criarLocationRecorrencia($credencial);
        $rec = $this->driver->emitirPixAutomatico(new EmitirCobrancaInput(
            businessId: $businessId,
            contactId: (int) $sub->getAttribute('contact_id'),
            valorCentavos: $centavos,
            vencimento: new \DateTimeImmutable((string) $sub->getAttribute('next_due_date')->toDateString()),
            descricao: 'Assinatura '.$subscriptionId,
            idempotencyKey: 'RB-SUB-'.$subscriptionId,
            origemType: 'subscription',
            origemId: $subscriptionId,
            meta: ['payer_cpf_cnpj' => $documento, 'periodicidade' => $periodicidade, 'loc_id' => $loc['id']],
        ), $credencial);

        $completa = $this->driver->consultarRecorrencia($credencial, $rec->gatewayExternalId);
        $mandato = [
            'id_rec' => $rec->gatewayExternalId,
            'status' => (string) ($completa['status'] ?? 'CRIADA'),
            'pix_copia_cola' => $completa['dadosQR']['pixCopiaECola'] ?? null,
            'valor_centavos' => $centavos,
            'solicitado_em' => now()->toIso8601String(),
        ];
        $metadata['pix_automatico'] = $mandato;
        $sub->forceFill(['metadata' => $metadata])->save();

        return $this->resultado($mandato);
    }

    /** @return array{0:int, 1:string} centavos e periodicidade da spec */
    private function valorEPeriodicidade(Subscription $sub, array $metadata): array
    {
        $plano = $sub->getRelation('plan');
        if ($plano !== null) {
            return [CobradorPixAutomatico::centavos((string) $plano->getAttribute('valor')),
                self::PERIODICIDADE[(string) $plano->getAttribute('ciclo')] ?? 'MENSAL'];
        }
        $valor = $metadata['valor'] ?? null;
        $ciclo = self::PERIODICIDADE[(string) ($metadata['ciclo'] ?? '')] ?? null;
        if (! is_numeric($valor) || (float) $valor <= 0 || $ciclo === null) {
            throw new \DomainException('Assinatura sem plano e sem valor negociado: não há o que autorizar.');
        }

        return [CobradorPixAutomatico::centavos(number_format((float) $valor, 2, '.', '')), $ciclo];
    }

    /** @return array{id_rec: string, status: string, pix_copia_cola: ?string} */
    private function resultado(array $mandato): array
    {
        return [
            'id_rec' => (string) $mandato['id_rec'],
            'status' => (string) $mandato['status'],
            'pix_copia_cola' => isset($mandato['pix_copia_cola']) ? (string) $mandato['pix_copia_cola'] : null,
        ];
    }
}
