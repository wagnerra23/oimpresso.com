<?php

declare(strict_types=1);

namespace Modules\Financeiro\Services;

use App\Transaction;
use App\TransactionPayment;
use App\Utils\TransactionUtil;
use Illuminate\Support\Facades\DB;
use Modules\Financeiro\Models\Concerns\BusinessScopeImpl;
use Modules\Financeiro\Models\Titulo;
use Modules\NFSe\Models\NfseEmissao;

/**
 * US-FIN-072 · UC-FLX-30 — NFS-e autorizada com retenção → a retenção é lançada como BAIXA.
 *
 * Decisão [W] 2026-10-20: "venda parcial não deveria existir". O título mantém o BRUTO; a
 * venda recebe um pagamento `retencao` (transaction_payments) no valor retido (ISS retido +
 * PIS + COFINS + CSLL) e o observer Sells→Financeiro já existente transforma esse pagamento
 * na baixa `meio_pagamento=retencao` do título (sem movimento de caixa). Quando o cliente paga
 * o líquido, venda e título ficam pagos.
 *
 * ⚠️ REGRA MESTRE — CÁLCULO DE VALOR (memory/proibicoes.md). Travas:
 *  - chave POR NEGÓCIO `business.common_settings['financeiro_iss_retido_ajuste']['ativo']`,
 *    DEFAULT DESLIGADA;
 *  - só age na TRANSIÇÃO de status da nota (observer); nota já autorizada → só o dry-run;
 *  - a baixa vai só até o que falta pagar na venda (nunca saldo negativo); a diferença fica
 *    registrada como pendência em `metadata.retencao_nfse.pendente`;
 *  - idempotente: um pagamento de retenção por nota (`payment_ref_no = NFSE-RET-{id}`);
 *  - cancelar a nota apaga o pagamento → o observer grava a baixa de ESTORNO (append-only).
 *
 * Multi-tenant Tier 0 (ADR 0093): venda e título procurados pelo `business_id` DA NOTA.
 * Contas em centavos (int).
 */
class RetencaoNfseTituloService
{
    public const CHAVE = 'financeiro_iss_retido_ajuste';

    /** `transaction_payments.method` (varchar) do pagamento de retenção. */
    public const METODO = 'retencao';

    public function __construct(private readonly TransactionUtil $transactionUtil)
    {
    }

    public function ativo(int $businessId): bool
    {
        $bruto = DB::table('business')->where('id', $businessId)->value('common_settings');
        $settings = is_string($bruto) ? json_decode($bruto, true) : null;

        return (bool) (($settings[self::CHAVE] ?? [])['ativo'] ?? false);
    }

    /** @return array{iss: int, pis: int, cofins: int, csll: int, total: int} centavos */
    public function retencoes(NfseEmissao $nota): array
    {
        $iss = 0;
        if ($nota->iss_retido) {
            // Nota anterior à coluna (NULL): mesma conta do NfseEmissaoPayload::valorIssRetido().
            $iss = $nota->valor_iss_retido !== null
                ? $this->centavos($nota->valor_iss_retido)
                : $this->centavos(round((float) $nota->valor_servicos * (float) $nota->aliquota_iss, 2));
        }
        $r = [
            'iss' => $iss,
            'pis' => $this->centavos($nota->valor_ret_pis),
            'cofins' => $this->centavos($nota->valor_ret_cofins),
            'csll' => $this->centavos($nota->valor_ret_csll),
        ];
        $r['total'] = array_sum($r);

        return $r;
    }

    /**
     * O que aconteceria — sem gravar nada (dry-run e base do aplicar). Centavos.
     *
     * @return array{acao: string, titulo: ?Titulo, bruto: int, aberto: int, retido: int, baixa: int, pendente: int}
     */
    public function simular(NfseEmissao $nota): array
    {
        $retido = $this->retencoes($nota)['total'];
        $venda = $this->vendaDaNota($nota);
        $titulo = $venda ? $this->tituloDaVenda($venda) : null;
        $bruto = $venda ? $this->centavos($venda->final_total) : 0;
        $aberto = $venda ? max(0, $bruto - $this->centavos($this->transactionUtil->getTotalPaid($venda->id))) : 0;
        $baixa = min($retido, $aberto);

        $acao = match (true) {
            $venda === null || $titulo === null => 'sem_titulo',
            $retido === 0 => 'sem_retencao',
            $this->pagamentoDaNota($nota) !== null => 'ja_lancado',
            $baixa === 0 => 'pendente_sem_aberto',
            $baixa < $retido => 'baixa_parcial',
            default => 'baixa',
        };

        return compact('acao', 'titulo', 'bruto', 'aberto', 'retido', 'baixa') + ['pendente' => $retido - $baixa];
    }

    /** Autorização da nota. Retorna a ação tomada. */
    public function aplicar(NfseEmissao $nota): string
    {
        if (! $this->ativo((int) $nota->business_id)) {
            return 'desligado';
        }

        return DB::transaction(function () use ($nota): string {
            $s = $this->simular($nota);
            if (! in_array($s['acao'], ['baixa', 'baixa_parcial', 'pendente_sem_aberto'], true)) {
                return $s['acao'];
            }
            $venda = $this->vendaDaNota($nota);

            // Grava o registro ANTES do pagamento: a re-sincronização que o pagamento dispara
            // (TituloAutoService) reconhece o título pelo registro e calcula o aberto pelas baixas.
            $this->registrar($s['titulo'], [
                'nfse_id' => $nota->id,
                'estado' => $s['acao'] === 'pendente_sem_aberto' ? 'pendente' : 'lancado',
                'retencoes' => array_map(fn (int $c) => $c / 100, $this->retencoes($nota)),
                'retido_total' => $s['retido'] / 100,
                'baixado' => $s['baixa'] / 100,
                'pendente' => $s['pendente'] / 100,
                'em' => now()->toDateTimeString(),
            ]);

            if ($s['baixa'] > 0) {
                // Observer TransactionPayment → TituloAutoService::registrarPagamento cria a baixa.
                TransactionPayment::create([
                    'business_id' => $venda->business_id,
                    'transaction_id' => $venda->id,
                    'amount' => $s['baixa'] / 100,
                    'method' => self::METODO,
                    'paid_on' => now(),
                    'created_by' => $venda->created_by,
                    'payment_for' => $venda->contact_id,
                    'payment_ref_no' => $this->refDaNota($nota),
                    'note' => sprintf('Retenção da NFS-e #%d (ISS/PIS/COFINS/CSLL retidos pelo tomador)', $nota->id),
                ]);
                $this->transactionUtil->updatePaymentStatus($venda->id, $venda->final_total);
            }

            return $s['acao'];
        });
    }

    /** Cancelamento da nota: estorna o pagamento/baixa de retenção. Não depende da chave. */
    public function estornar(NfseEmissao $nota): string
    {
        return DB::transaction(function () use ($nota): string {
            $venda = $this->vendaDaNota($nota);
            $titulo = $venda ? $this->tituloDaVenda($venda) : null;
            $registro = $titulo?->metadata['retencao_nfse'] ?? null;
            if (! is_array($registro) || (int) ($registro['nfse_id'] ?? 0) !== (int) $nota->id || $registro['estado'] === 'estornado') {
                return 'nada_a_estornar';
            }

            $tp = $this->pagamentoDaNota($nota);
            if ($tp !== null) {
                // Observer TransactionPayment::deleted → cancelarPagamento grava a baixa de estorno.
                $tp->delete();
                $this->transactionUtil->updatePaymentStatus($venda->id, $venda->final_total);
            }
            $this->registrar($titulo->fresh(), array_merge($registro, ['estado' => 'estornado', 'estornado_em' => now()->toDateTimeString()]));

            return 'estornado';
        });
    }

    private function registrar(Titulo $titulo, array $registro): void
    {
        $titulo->metadata = array_merge($titulo->metadata ?? [], ['retencao_nfse' => $registro]);
        $titulo->save();
    }

    private function vendaDaNota(NfseEmissao $nota): ?Transaction
    {
        if (empty($nota->transaction_id)) {
            return null;
        }

        return Transaction::query()
            ->where('business_id', (int) $nota->business_id)
            ->where('type', 'sell')
            ->where('id', (int) $nota->transaction_id)
            ->first();
    }

    private function tituloDaVenda(Transaction $venda): ?Titulo
    {
        // SUPERADMIN: observer/comando sem sessão — business_id explícito da venda (ADR 0093).
        return Titulo::query()
            ->withoutGlobalScope(BusinessScopeImpl::class)
            ->where('business_id', (int) $venda->business_id)
            ->where('tipo', 'receber')
            ->where('origem', 'venda')
            ->where('origem_id', (int) $venda->id)
            ->whereNull('parcela_numero')
            ->where('status', '!=', 'cancelado')
            ->first();
    }

    private function pagamentoDaNota(NfseEmissao $nota): ?TransactionPayment
    {
        return TransactionPayment::query()
            ->where('business_id', (int) $nota->business_id)
            ->where('transaction_id', (int) $nota->transaction_id)
            ->where('method', self::METODO)
            ->where('payment_ref_no', $this->refDaNota($nota))
            ->first();
    }

    private function refDaNota(NfseEmissao $nota): string
    {
        return 'NFSE-RET-'.$nota->id;
    }

    private function centavos(mixed $valor): int
    {
        return (int) round(((float) $valor) * 100);
    }
}
