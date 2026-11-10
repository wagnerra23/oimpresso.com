<?php

declare(strict_types=1);

namespace Modules\NfeBrasil\Services;

use App\Contracts\Fiscal\CancelamentoFiscalDaVenda;
use App\Domain\Fsm\Models\TransactionDocument;
use Illuminate\Database\Eloquent\Collection;
use Modules\NfeBrasil\Models\NfeEmissao;

/**
 * NF-e/NFC-e da venda no cancelamento pela FSM. `nfe_emissoes` é a fonte: medido em 2026-11-10,
 * `transaction_documents` estava vazia em produção com 1.613 notas autorizadas, e gravar lá na
 * autorização não alcançaria as notas que já existem.
 *
 * A checagem roda ANTES de cancelar, dentro da transação da ação FSM: o cancelamento na SEFAZ é
 * assíncrono e irreversível, então o que já se sabe que vai falhar recusa a venda inteira.
 *
 * NFS-e fica de fora: `nfse_emissoes` tem dois donos com vocabulários diferentes e o cancelamento
 * é tratado pelos lotes do módulo NFSe.
 */
final class CancelamentoFiscalNfeBrasil implements CancelamentoFiscalDaVenda
{
    private const EM_PROCESSAMENTO = ['pendente', 'enviando', 'contingencia'];

    private const TIPO_POR_MODELO = [
        '55' => TransactionDocument::DOC_NFE55,
        '65' => TransactionDocument::DOC_NFCE65,
    ];

    public function impedimentos(int $businessId, int $transactionId): array
    {
        $motivos = [];
        foreach ($this->notas($businessId, $transactionId, ['autorizada', ...self::EM_PROCESSAMENTO]) as $nota) {
            $nome = ($nota->modelo === '65' ? 'NFC-e' : 'NF-e') . " {$nota->numero}";

            if ($nota->status !== 'autorizada') {
                $motivos[] = "A {$nome} ainda está em processamento na SEFAZ. Aguarde o retorno e cancele de novo.";
            } elseif (! $nota->isCancelavel()) {
                $prazo = $nota->modelo === '65' ? '24 horas' : '168 horas';
                $motivos[] = "A {$nome} passou do prazo de {$prazo} e não pode mais ser cancelada. Faça a devolução da venda.";
            } elseif (($nota->metadata['nProt'] ?? '') === '' || strlen((string) $nota->chave_44) !== 44) {
                $motivos[] = "A {$nome} não tem protocolo de autorização ou chave de acesso, e a SEFAZ não aceitaria o cancelamento. Cancele-a na tela Fiscal depois de regularizar.";
            }
        }

        return $motivos;
    }

    public function registrarDocumentos(int $businessId, int $transactionId): int
    {
        $notas = $this->notas($businessId, $transactionId, ['autorizada']);

        foreach ($notas as $nota) {
            TransactionDocument::query()->firstOrCreate(
                [
                    'business_id' => $businessId,
                    'transaction_id' => $transactionId,
                    'doc_type' => self::TIPO_POR_MODELO[$nota->modelo],
                    'doc_id' => $nota->id,
                ],
                [
                    'doc_class' => NfeEmissao::class,
                    'value_total' => $nota->valor_total,
                    'emitted_at' => $nota->emitido_em,
                    'status' => TransactionDocument::STATUS_AUTHORIZED,
                ],
            );
        }

        return $notas->count();
    }

    /**
     * @param  list<string>  $status
     * @return Collection<int, NfeEmissao>
     */
    private function notas(int $businessId, int $transactionId, array $status): Collection
    {
        return NfeEmissao::query()
            ->where('business_id', $businessId)
            ->where('transaction_id', $transactionId)
            ->whereIn('modelo', array_keys(self::TIPO_POR_MODELO))
            ->whereIn('status', $status)
            ->get();
    }
}
