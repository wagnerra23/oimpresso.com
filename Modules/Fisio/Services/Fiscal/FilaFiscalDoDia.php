<?php

declare(strict_types=1);

namespace Modules\Fisio\Services\Fiscal;

use Illuminate\Support\Facades\DB;
use Modules\Fisio\Entities\ReciboSaude;
use Modules\NFSe\Models\NfseEmissao;

/**
 * Documentos fiscais do dia (US-FISIO-023, fonte `prototipo-ui/.../fisio/FiscalFila.dc.html`).
 *
 * Só LÊ dinheiro: pagamentos e vendas são do núcleo (`transaction_payments`, `transactions`),
 * a nota é do NFSe. Entra só venda cujo cliente é paciente do Fisio na mesma empresa. A única
 * escrita do Fisio é a marca de envio do recibo (`ReciboSaude`).
 */
final class FilaFiscalDoDia
{
    /**
     * Pagamentos de pacientes no dia, com a marca do Receita Saúde.
     *
     * @return list<array<string, mixed>>
     */
    public function recibos(int $businessId, string $dia): array
    {
        $linhas = DB::table('transaction_payments as tp')
            ->join('transactions as t', 't.id', '=', 'tp.transaction_id')
            ->join('fisio_patients as p', fn ($j) => $j->on('p.contact_id', '=', 't.contact_id')->where('p.business_id', $businessId))
            ->join('contacts as c', 'c.id', '=', 't.contact_id')
            ->where('tp.business_id', $businessId)->where('t.business_id', $businessId)
            ->where('t.type', 'sell')->where('tp.is_return', 0)
            ->whereDate('tp.paid_on', $dia)
            ->orderBy('tp.paid_on')
            ->get(['tp.id', 'tp.amount', 'tp.paid_on', 'c.name', 'c.cpf_cnpj']);

        $marcas = ReciboSaude::query()->where('business_id', $businessId)
            ->whereIn('payment_id', $linhas->pluck('id'))->get()->keyBy('payment_id');

        return $linhas->map(function ($l) use ($marcas) {
            $cpf = preg_replace('/\D/', '', (string) $l->cpf_cnpj);
            $marca = $marcas->get((int) $l->id);

            return [
                'payment_id' => (int) $l->id,
                'pagador' => $l->name,
                'valor' => (string) $l->amount,
                'cpf_ok' => strlen($cpf) === 11,
                // Só os 2 últimos dígitos: a tela confere, não exibe o CPF (LGPD art. 6º, III).
                'cpf_fim' => strlen($cpf) === 11 ? substr($cpf, -2) : null,
                'status' => $marca?->marca ?? 'pendente',
                'numero' => $marca?->receipt_number,
            ];
        })->values()->all();
    }

    /**
     * NFS-e do dia das vendas a pacientes (leitura do NFSe).
     *
     * @return list<array<string, mixed>>
     */
    public function notas(int $businessId, string $dia): array
    {
        if (! class_exists(NfseEmissao::class)) {
            return [];
        }
        $vendas = DB::table('transactions as t')
            ->join('fisio_patients as p', fn ($j) => $j->on('p.contact_id', '=', 't.contact_id')->where('p.business_id', $businessId))
            ->where('t.business_id', $businessId)->where('t.type', 'sell')->pluck('t.id');

        return NfseEmissao::query()->where('business_id', $businessId)->whereIn('transaction_id', $vendas)
            ->whereDate('created_at', $dia)->orderBy('id')
            ->get(['id', 'numero', 'status', 'tomador_nome', 'erro_mensagem'])
            ->map(fn (NfseEmissao $n) => [
                'id' => $n->id, 'numero' => $n->numero, 'status' => $n->status, 'tomador' => $n->tomador_nome,
                'erro' => $n->status === 'erro' ? $n->erro_mensagem : null,
            ])->values()->all();
    }

    /** Marca o recibo como enviado. Idempotente: marcar de novo só atualiza o número. */
    public function marcarEnviado(int $businessId, int $paymentId, ?string $numero, int $userId): ReciboSaude
    {
        return ReciboSaude::query()->updateOrCreate(
            ['business_id' => $businessId, 'payment_id' => $paymentId],
            ['marca' => ReciboSaude::ENVIADO, 'receipt_number' => $numero, 'marked_by' => $userId, 'marked_at' => now()],
        );
    }

    /** O pagamento existe na empresa e é de uma venda a paciente do Fisio? */
    public function pagamentoDePaciente(int $businessId, int $paymentId): bool
    {
        return DB::table('transaction_payments as tp')
            ->join('transactions as t', 't.id', '=', 'tp.transaction_id')
            ->join('fisio_patients as p', fn ($j) => $j->on('p.contact_id', '=', 't.contact_id')->where('p.business_id', $businessId))
            ->where('tp.id', $paymentId)->where('tp.business_id', $businessId)->where('t.business_id', $businessId)
            ->exists();
    }
}
