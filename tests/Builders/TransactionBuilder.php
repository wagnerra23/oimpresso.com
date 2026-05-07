<?php

declare(strict_types=1);

namespace Tests\Builders;

use App\Transaction;

/**
 * **TransactionBuilder** — fluent API pra criar transactions de teste.
 *
 * **Por que existe:**
 * `App\Transaction` tem ~80 colunas. Mass-assignment por array em cada test
 * vira ruído (90% das chaves repetidas). Builder elimina ruído + força
 * configuração explícita do que importa pro caso testado.
 *
 * Não persiste em DB — usa `forceFill` em instância non-persisted. Suficiente
 * pra testes que só LEEM propriedades (Listeners, Services, Validators).
 *
 * Pra testes que precisam persistir (Repository, Query Scope), usar factory
 * legacy `factory(Transaction::class)->create([...])` — Builder NÃO substitui
 * factories, complementa.
 *
 * **Uso típico:**
 * ```php
 * use Tests\Builders\TransactionBuilder;
 *
 * $tx = TransactionBuilder::venda()
 *     ->business(4)
 *     ->id(12345)
 *     ->ufOrigem('SP')->ufDestino('RJ')
 *     ->paid()
 *     ->finalTotal(100.00)
 *     ->build();
 * ```
 *
 * **Cenários canônicos pré-cozidos:**
 * - `venda()` — type=sell + status=final + payment_status=paid (caso típico NFC-e)
 * - `vendaRascunho()` — type=sell + status=draft (não emite)
 * - `vendaPendente()` — type=sell + status=final + payment_status=due (NFe55 prazo)
 * - `compra()` — type=purchase
 * - `transferencia()` — type=sell_transfer (entre filiais mesmo CNPJ)
 * - `devolucao()` — type=sell_return (CFOP 1.202)
 *
 * Refs:
 *   - `Tests/Feature/EmitirNfceAoFinalizarVendaTest.php` — listener filters
 *   - SPEC US-NFE-002, US-NFE-040 (foundation)
 */
class TransactionBuilder
{
    private array $attrs = [];

    /**
     * Helper privado pra setar valor + retornar $this (fluent).
     */
    private function set(string $key, mixed $value): self
    {
        $this->attrs[$key] = $value;
        return $this;
    }

    // ──────────────────────────────────────────────────────────────────
    // Cenários canônicos (entry points)
    // ──────────────────────────────────────────────────────────────────

    /**
     * Venda balcão típica — type=sell + status=final + payment_status=paid.
     * Caso canônico que dispara NFC-e (modelo 65) automática.
     */
    public static function venda(): self
    {
        return (new self)
            ->set('id', 1)
            ->set('business_id', 1)
            ->set('type', 'sell')
            ->set('status', 'final')
            ->set('payment_status', 'paid')
            ->set('final_total', 100.00)
            ->set('total_before_tax', 90.00)
            ->set('tax_amount', 10.00)
            ->set('discount_amount', 0.00)
            ->set('transaction_date', now());
    }

    /**
     * Venda em rascunho — não emite (status != final).
     */
    public static function vendaRascunho(): self
    {
        return self::venda()->set('status', 'draft');
    }

    /**
     * Venda a prazo — payment_status=due. Listener NFC-e ignora; usa NFe55 com cobrança.
     */
    public static function vendaPendente(): self
    {
        return self::venda()->set('payment_status', 'due');
    }

    /**
     * Compra (entrada de mercadoria de fornecedor).
     */
    public static function compra(): self
    {
        return (new self)
            ->set('id', 1)
            ->set('business_id', 1)
            ->set('type', 'purchase')
            ->set('status', 'received')
            ->set('payment_status', 'paid')
            ->set('final_total', 200.00);
    }

    /**
     * Transferência entre filiais mesmo CNPJ (CFOP 5.151/6.151).
     */
    public static function transferencia(): self
    {
        return (new self)
            ->set('id', 1)
            ->set('business_id', 1)
            ->set('type', 'sell_transfer')
            ->set('status', 'final')
            ->set('payment_status', 'paid')
            ->set('final_total', 0.00); // transferência sem cobrança
    }

    /**
     * Devolução de cliente (CFOP 1.202 entrada por devolução).
     */
    public static function devolucao(): self
    {
        return (new self)
            ->set('id', 1)
            ->set('business_id', 1)
            ->set('type', 'sell_return')
            ->set('status', 'final')
            ->set('payment_status', 'paid')
            ->set('final_total', 100.00);
    }

    // ──────────────────────────────────────────────────────────────────
    // Setters fluent
    // ──────────────────────────────────────────────────────────────────

    public function id(int $id): self
    {
        return $this->set('id', $id);
    }

    public function business(int $businessId): self
    {
        return $this->set('business_id', $businessId);
    }

    /**
     * Define payment_status='paid' (pagamento completo).
     */
    public function paid(): self
    {
        return $this->set('payment_status', 'paid');
    }

    /**
     * Define payment_status='partial' (parcialmente pago).
     */
    public function partial(): self
    {
        return $this->set('payment_status', 'partial');
    }

    /**
     * Define payment_status='due' (a prazo, sem pagamento).
     */
    public function due(): self
    {
        return $this->set('payment_status', 'due');
    }

    /**
     * Define status='draft' (rascunho — não emite NFe).
     */
    public function draft(): self
    {
        return $this->set('status', 'draft');
    }

    /**
     * Define status='final' (efetivada).
     */
    public function final(): self
    {
        return $this->set('status', 'final');
    }

    public function finalTotal(float $total): self
    {
        return $this->set('final_total', $total);
    }

    public function discount(float $amount): self
    {
        return $this->set('discount_amount', $amount);
    }

    public function tax(float $amount): self
    {
        return $this->set('tax_amount', $amount);
    }

    /**
     * Define UF de origem (estado do emitente). Não é coluna direta da
     * Transaction — campo virtual setado em `metadata.uf_origem` pra Tests
     * que precisam dessa info sem persistir Business completo.
     */
    public function ufOrigem(string $uf): self
    {
        $meta = $this->attrs['metadata'] ?? [];
        if (! is_array($meta)) $meta = [];
        $meta['uf_origem'] = strtoupper($uf);
        return $this->set('metadata', $meta);
    }

    /**
     * Define UF de destino (estado do destinatário). Idem ufOrigem — virtual
     * em metadata, NÃO é coluna direta da Transaction.
     */
    public function ufDestino(string $uf): self
    {
        $meta = $this->attrs['metadata'] ?? [];
        if (! is_array($meta)) $meta = [];
        $meta['uf_destino'] = strtoupper($uf);
        return $this->set('metadata', $meta);
    }

    /**
     * Sobrescreve um campo arbitrário pra casos especiais.
     */
    public function with(string $key, mixed $value): self
    {
        return $this->set($key, $value);
    }

    /**
     * Sobrescreve múltiplos campos via array.
     *
     * @param  array<string, mixed>  $attrs
     */
    public function withAttrs(array $attrs): self
    {
        $this->attrs = array_merge($this->attrs, $attrs);
        return $this;
    }

    // ──────────────────────────────────────────────────────────────────
    // Build
    // ──────────────────────────────────────────────────────────────────

    /**
     * Cria instância não persistida da Transaction com os atributos setados.
     */
    public function build(): Transaction
    {
        $tx = new Transaction;
        $tx->forceFill($this->attrs);
        return $tx;
    }

    /**
     * Atalho: build() + retorna ID + business_id como tupla.
     *
     * @return array{0: int, 1: int}
     */
    public function buildIds(): array
    {
        $tx = $this->build();
        return [(int) $tx->id, (int) $tx->business_id];
    }
}
