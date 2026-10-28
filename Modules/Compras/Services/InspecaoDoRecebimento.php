<?php

declare(strict_types=1);

namespace Modules\Compras\Services;

use App\Utils\ProductUtil;
use App\Utils\TransactionUtil;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Inspeção de qualidade no recebimento da compra — US-COM-034 (lacuna P2 de Compras, 2026-10-27).
 *
 * Cada linha da compra recebida é inspecionada uma vez: aprovado + reprovado = o que entrou por
 * ela (`quantity − quantity_returned`). O resultado sai da conta: nada reprovado = aprovado, nada
 * aprovado = reprovado, o resto = parcial.
 *
 * O reprovado não fica no estoque disponível. Ele sai pela devolução de compra do núcleo, a mesma
 * conta do `PurchaseReturnController@store` (regra mestre, valor e estoque):
 *   estoque  : `ProductUtil::decreaseProductQuantity` de `quantity_returned` antes → depois
 *   linha    : `quantity_returned` += reprovado
 *   devolução: UM `purchase_return` por compra (`return_parent_id`); total = Σ das linhas da compra
 *              de `purchase_price_inc_tax × quantity_returned`, mais o imposto que a devolução já tinha
 * A devolução vira crédito com o fornecedor (`updatePaymentStatus`), como a do núcleo.
 *
 * Desligada por padrão: `business.common_settings['compras_inspecao_recebimento'] = true` liga.
 * Tier 0 (ADR 0093): compra, linhas e devolução sempre filtradas pela empresa do argumento.
 */
final class InspecaoDoRecebimento
{
    public const CHAVE = 'compras_inspecao_recebimento';

    public function __construct(private ProductUtil $productUtil, private TransactionUtil $transactionUtil) {}

    public function ativa(int $businessId): bool
    {
        return (bool) ($this->settings($businessId)[self::CHAVE] ?? false);
    }

    public function definir(int $businessId, bool $ativa): void
    {
        $s = $this->settings($businessId);
        $s[self::CHAVE] = $ativa;
        DB::table('business')->where('id', $businessId)->update(['common_settings' => json_encode($s)]);
    }

    /**
     * @param  array<int, array{aprovada:string|int|float, reprovada:string|int|float, motivo?:?string}>  $linhas  purchase_line_id → resultado
     * @return array{inspecoes:list<int>, devolucao_id:?int}
     */
    public function registrar(int $businessId, int $compraId, int $userId, array $linhas): array
    {
        if (! $this->ativa($businessId)) {
            throw new InvalidArgumentException('A inspeção no recebimento está desligada para esta empresa.');
        }
        if ($linhas === []) {
            throw new InvalidArgumentException('Informe ao menos uma linha inspecionada.');
        }

        return DB::transaction(function () use ($businessId, $compraId, $userId, $linhas) {
            $compra = DB::table('transactions')->where('business_id', $businessId)->where('type', 'purchase')
                ->where('id', $compraId)->lockForUpdate()->first(['id', 'status', 'location_id', 'contact_id', 'tax_id']);
            if ($compra === null) {
                throw new InvalidArgumentException('Compra inválida para este negócio.');
            }
            if ($compra->status !== 'received') {
                throw new InvalidArgumentException('Só a compra recebida é inspecionada.');
            }

            $daCompra = DB::table('purchase_lines')->where('transaction_id', $compra->id)->lockForUpdate()->get()->keyBy('id');
            $jaInspecionadas = DB::table('compras_inspecoes')->where('business_id', $businessId)
                ->whereIn('purchase_line_id', array_keys($linhas))->pluck('purchase_line_id')->all();
            if ($jaInspecionadas !== []) {
                throw new InvalidArgumentException('Linha já inspecionada.');
            }

            $agora = now();
            $registros = [];
            $houveReprovado = false;
            foreach ($linhas as $lineId => $r) {
                $linha = $daCompra[(int) $lineId] ?? null;
                if ($linha === null) {
                    throw new InvalidArgumentException('Linha que não é desta compra.');
                }
                $entrou = self::dm((string) $linha->quantity) - self::dm((string) $linha->quantity_returned);
                $aprov = self::dm((string) $r['aprovada']);
                $reprov = self::dm((string) $r['reprovada']);
                if ($aprov < 0 || $reprov < 0 || $aprov + $reprov !== $entrou) {
                    throw new InvalidArgumentException('Aprovado + reprovado precisa ser igual ao recebido na linha.');
                }
                $motivo = trim((string) ($r['motivo'] ?? ''));
                if ($reprov > 0 && $motivo === '') {
                    throw new InvalidArgumentException('Informe o motivo da reprovação.');
                }

                if ($reprov > 0) {
                    $houveReprovado = true;
                    $antes = self::dm((string) $linha->quantity_returned);
                    $depois = self::txt($antes + $reprov);
                    DB::table('purchase_lines')->where('id', $linha->id)->update(['quantity_returned' => $depois, 'updated_at' => $agora]);
                    $this->productUtil->decreaseProductQuantity(
                        $linha->product_id, $linha->variation_id, $compra->location_id, $depois, self::txt($antes),
                    );
                }

                $registros[] = [
                    'business_id' => $businessId, 'transaction_id' => $compra->id, 'purchase_line_id' => $linha->id,
                    'contact_id' => (int) $compra->contact_id, 'quantidade_inspecionada' => self::txt($entrou),
                    'quantidade_aprovada' => self::txt($aprov), 'quantidade_reprovada' => self::txt($reprov),
                    'resultado' => $reprov === 0 ? 'aprovado' : ($aprov === 0 ? 'reprovado' : 'parcial'),
                    'motivo' => $motivo === '' ? null : mb_substr($motivo, 0, 255),
                    'inspecionado_por' => $userId, 'created_at' => $agora, 'updated_at' => $agora,
                ];
            }

            $devolucaoId = $houveReprovado ? $this->devolucao($businessId, $compra, $userId) : null;
            $ids = [];
            foreach ($registros as $reg) {
                $ids[] = (int) DB::table('compras_inspecoes')->insertGetId($reg + ['devolucao_id' => $devolucaoId]);
            }

            return ['inspecoes' => $ids, 'devolucao_id' => $devolucaoId];
        });
    }

    /** Cria ou atualiza o `purchase_return` da compra com o total de todas as linhas devolvidas. */
    private function devolucao(int $businessId, object $compra, int $userId): int
    {
        $total = 0; // 10⁻⁸
        foreach (DB::table('purchase_lines')->where('transaction_id', $compra->id)->get(['purchase_price_inc_tax', 'quantity_returned']) as $l) {
            $total += self::dm((string) $l->purchase_price_inc_tax) * self::dm((string) $l->quantity_returned);
        }
        $semImposto = intdiv($total + 5000, 10000);

        $existente = DB::table('transactions')->where('business_id', $businessId)->where('type', 'purchase_return')
            ->where('return_parent_id', $compra->id)->first(['id', 'tax_amount']);
        $imposto = $existente !== null ? self::dm((string) $existente->tax_amount) : 0;
        $dados = [
            'total_before_tax' => self::txt($semImposto), 'final_total' => self::txt($semImposto + $imposto),
            'tax_amount' => self::txt($imposto), 'tax_id' => $compra->tax_id, 'updated_at' => now(),
        ];

        if ($existente !== null) {
            DB::table('transactions')->where('id', $existente->id)->update($dados);
            $id = (int) $existente->id;
        } else {
            $n = $this->transactionUtil->setAndGetReferenceCount('purchase_return', $businessId);
            $id = (int) DB::table('transactions')->insertGetId($dados + [
                'business_id' => $businessId, 'location_id' => $compra->location_id, 'contact_id' => $compra->contact_id,
                'type' => 'purchase_return', 'status' => 'final', 'return_parent_id' => $compra->id,
                'ref_no' => $this->transactionUtil->generateReferenceNumber('purchase_return', $n, $businessId),
                'transaction_date' => now(), 'created_by' => $userId, 'essentials_duration' => 0, 'created_at' => now(),
            ]);
        }
        $this->transactionUtil->updatePaymentStatus($id, $dados['final_total']);

        return $id;
    }

    private function settings(int $businessId): array
    {
        $s = json_decode((string) DB::table('business')->where('id', $businessId)->value('common_settings'), true);

        return is_array($s) ? $s : [];
    }

    private static function txt(int $n): string
    {
        return ($n < 0 ? '-' : '').sprintf('%d.%04d', intdiv(abs($n), 10000), abs($n) % 10000);
    }

    /** DECIMAL(22,4) em dez-milésimos inteiros, sem float. */
    private static function dm(string $v): int
    {
        $v = trim($v) === '' ? '0' : trim($v);
        $neg = str_starts_with($v, '-');
        [$i, $f] = array_pad(explode('.', ltrim($v, '-'), 2), 2, '');
        $n = (int) $i * 10000 + (int) substr(str_pad($f, 4, '0'), 0, 4);

        return $neg ? -$n : $n;
    }
}
