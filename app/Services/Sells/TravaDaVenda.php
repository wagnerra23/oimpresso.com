<?php

declare(strict_types=1);

namespace App\Services\Sells;

use App\Contracts\Fiscal\NotaDaVenda;
use App\Transaction;
use App\Utils\TransactionUtil;
use Illuminate\Support\Facades\DB;

/**
 * O que pode ser editado numa venda (US-SELL-072 · decisão [W] 2026-10-20, "Trava itens e
 * valores após nota").
 *
 *  - Venda com nota válida (NF-e/NFC-e/NFS-e autorizada ou em contingência): itens, valores,
 *    cliente e situação ficam travados — cancela-se a nota antes. Só os campos de texto livre
 *    ({@see self::CAMPOS_LIVRES}) seguem editáveis.
 *  - Venda com pagamento (sem nota): só os valores travam — quantidade e preço das linhas,
 *    descontos, imposto, frete e despesas. Cliente e texto seguem editáveis.
 *  - Venda sem nota nem pagamento: nada muda.
 *
 * Só RECUSA: não calcula nem grava valor. Para decidir se o pedido muda um valor, lê os números
 * com o mesmo `num_uf` e o mesmo multiplicador que o `createOrUpdateSellLines` usaria ao gravar,
 * e compara com o que está gravado.
 */
final class TravaDaVenda
{
    public const MSG_NOTA = 'Venda com nota autorizada: cancele a nota para alterar itens e valores.';

    public const MSG_PAGAMENTO = 'Venda com pagamento: valores não podem ser alterados; estorne o pagamento antes.';

    /** Campos de texto que seguem editáveis com a venda travada pela nota. */
    public const CAMPOS_LIVRES = ['additional_notes', 'staff_note', 'shipping_details', 'shipping_address', 'delivered_to'];

    public function __construct(private TransactionUtil $util) {}

    /** @return array{nota: bool, pagamento: bool, itens_valores: bool, cliente: bool, motivo: ?string, campos_livres: list<string>} */
    public function estado(Transaction $venda): array
    {
        $biz = (int) $venda->business_id;
        $id = (int) $venda->id;

        $nota = false;
        foreach (app()->tagged(NotaDaVenda::TAG) as $fonte) {
            if ($fonte instanceof NotaDaVenda && $fonte->temNotaValida($biz, $id)) {
                $nota = true;
                break;
            }
        }

        $pagamento = $this->temPagamento($biz, $id);

        return [
            'nota' => $nota,
            'pagamento' => $pagamento,
            'itens_valores' => $nota || $pagamento,
            'cliente' => $nota,
            'motivo' => $nota ? self::MSG_NOTA : ($pagamento ? self::MSG_PAGAMENTO : null),
            'campos_livres' => self::CAMPOS_LIVRES,
        ];
    }

    /**
     * Mensagem de recusa se o pedido de edição muda algo travado; null se pode seguir.
     *
     * @param  array<string, mixed>  $input  já com `status` normalizado pelo update
     * @param  array{nota: bool, pagamento: bool, itens_valores: bool, cliente: bool, motivo: ?string}  $estado
     */
    public function recusa(Transaction $venda, array $input, array $estado): ?string
    {
        if (! $estado['itens_valores']) {
            return null;
        }

        $muda = $this->mudaValores($venda, $input)
            || ($estado['cliente'] && $this->mudaClienteOuSituacao($venda, $input));

        return $muda ? (string) $estado['motivo'] : null;
    }

    private function temPagamento(int $biz, int $id): bool
    {
        $pago = (float) DB::table('transaction_payments')
            ->where('transaction_id', $id)
            ->where('is_return', 0)
            ->sum('amount');
        if ($pago > 0) {
            return true;
        }

        // Baixa feita pelo Financeiro (ADR 0434: "pago" é situação dos títulos).
        $situacao = app(SituacaoPagamentoVenda::class)->daVenda($biz, $id)['situacao'];

        return in_array($situacao, [SituacaoPagamentoVenda::PAGO, SituacaoPagamentoVenda::PARCIAL], true);
    }

    /** @param array<string, mixed> $input */
    private function mudaValores(Transaction $venda, array $input): bool
    {
        $desconto = fn ($tipo, $valor) => $this->n($valor) == 0.0 ? '' : (string) $tipo;
        if (array_key_exists('discount_amount', $input)) {
            if ($this->difere($this->n($input['discount_amount']), $venda->discount_amount)
                || $desconto($input['discount_type'] ?? $venda->discount_type, $input['discount_amount'])
                    !== $desconto($venda->discount_type, $venda->discount_amount)) {
                return true;
            }
        }
        foreach (['shipping_charges', 'packing_charge', 'additional_expense_value_1', 'additional_expense_value_2',
            'additional_expense_value_3', 'additional_expense_value_4'] as $campo) {
            if (isset($input[$campo]) && $input[$campo] !== '' && $this->difere($this->n($input[$campo]), $venda->{$campo})) {
                return true;
            }
        }
        if (array_key_exists('tax_rate_id', $input) && (int) ($input['tax_rate_id'] ?: 0) !== (int) ($venda->tax_id ?: 0)) {
            return true;
        }

        return isset($input['products']) && is_array($input['products']) && $this->mudaLinhas($venda, $input['products']);
    }

    /** @param array<int, array<string, mixed>> $produtos */
    private function mudaLinhas(Transaction $venda, array $produtos): bool
    {
        $gravadas = DB::table('transaction_sell_lines')
            ->where('transaction_id', $venda->id)
            ->whereNull('parent_sell_line_id')
            ->get()->keyBy('id');

        $vistas = [];
        foreach ($produtos as $p) {
            $linha = $gravadas->get((int) ($p['transaction_sell_lines_id'] ?? 0));
            if ($linha === null) {
                return true; // linha nova
            }
            $vistas[] = (int) $linha->id;

            // Mesmo multiplicador do createOrUpdateSellLines (TransactionUtil :304-311).
            $mult = 1.0;
            if (! empty($p['sub_unit_id']) && ($p['sub_unit_id'] != ($p['product_unit_id'] ?? null))
                && ! empty($p['base_unit_multiplier'])) {
                $mult = (float) $p['base_unit_multiplier'];
            }
            $tipo = ! empty($p['line_discount_type']) ? (string) $p['line_discount_type'] : '';
            $desc = $this->n($p['line_discount_amount'] ?? 0);
            if ($tipo === 'fixed') {
                $desc = round($desc / $mult, 4);
            }

            if ((int) ($p['variation_id'] ?? 0) !== (int) $linha->variation_id
                || $this->difere($this->n($p['quantity'] ?? 0) * $mult, $linha->quantity)
                || $this->difere($this->n($p['unit_price_inc_tax'] ?? 0) / $mult, $linha->unit_price_inc_tax)
                || $this->difere($desc, $linha->line_discount_amount)
                || ($desc != 0.0 && $tipo !== (string) $linha->line_discount_type)) {
                return true;
            }
        }

        return count(array_unique($vistas)) !== $gravadas->count(); // linha removida
    }

    /** @param array<string, mixed> $input */
    private function mudaClienteOuSituacao(Transaction $venda, array $input): bool
    {
        if (array_key_exists('contact_id', $input) && (int) $input['contact_id'] !== (int) $venda->contact_id) {
            return true;
        }

        return isset($input['status']) && (string) $input['status'] !== (string) $venda->status;
    }

    /** Número do pedido, lido como o update leria (pt-BR via num_uf), em 4 casas. */
    private function n(mixed $v): float
    {
        return round((float) $this->util->num_uf((string) $v), 4);
    }

    /**
     * Mudou de verdade? Tolerância de meio centavo: o Blade manda preço e quantidade na precisão
     * da empresa (2 casas por padrão) e o banco guarda 4 — 33,3333 gravado volta como "33,33".
     */
    private function difere(float $pedido, mixed $gravado): bool
    {
        return abs($pedido - round((float) $gravado, 4)) >= 0.005;
    }
}
