<?php

namespace Modules\Financeiro\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Financeiro\Contracts\PagadorBanco;
use Modules\Financeiro\Models\Concerns\BusinessScopeImpl;
use Modules\Financeiro\Models\ContaBancaria;
use Modules\Financeiro\Models\PagamentoBanco;
use Modules\Financeiro\Models\Titulo;
use Modules\Financeiro\Models\TituloBaixa;

/**
 * Pagar título a pagar pelo banco — boleto ou Pix (US-FIN-079).
 *
 * Saída de dinheiro, então três travas:
 *  1. Nunca automático: só `solicitar()` envia, e ele exige o valor CONFIRMADO pelo usuário
 *     igual, centavo a centavo, ao aberto do título (a tela mostra valor, destino e conta).
 *  2. Um pedido em aberto por título — não há segundo envio enquanto o primeiro não termina.
 *  3. A baixa só acontece quando o banco responde `pago` (na hora ou na consulta), com a mesma
 *     chave de idempotência do pedido; título já quitado por outro caminho NÃO recebe 2ª baixa.
 * `business_id` vem sempre do chamador (sessão/job), nunca do payload.
 */
class PagamentoBancoService
{
    public function solicitar(int $businessId, int $tituloId, int $userId, string $meio, string $destino, int $contaId, string $valorConfirmado): PagamentoBanco
    {
        if (! config('financeiro.pagamento_banco.enabled')) {
            throw new \DomainException('Pagamento pelo banco está desligado.');
        }
        if (! in_array($meio, PagamentoBanco::MEIOS, true)) {
            throw new \DomainException('Meio de pagamento inválido.');
        }
        $destino = $this->normalizarDestino($meio, $destino);

        $pagamento = DB::transaction(function () use ($businessId, $tituloId, $userId, $meio, $destino, $contaId, $valorConfirmado) {
            $titulo = Titulo::withoutGlobalScope(BusinessScopeImpl::class)
                ->where('business_id', $businessId)->lockForUpdate()->findOrFail($tituloId);

            if ($titulo->tipo !== 'pagar' || in_array($titulo->status, ['quitado', 'cancelado'], true)) {
                throw new \DomainException('Só título a pagar em aberto pode ser pago pelo banco.');
            }

            $aberto = number_format((float) $titulo->valor_aberto, 2, '.', '');
            if (! preg_match('/^\d+\.\d{2}$/', $valorConfirmado) || $valorConfirmado !== $aberto || (float) $aberto <= 0) {
                throw new \DomainException('O valor confirmado não confere com o aberto do título.');
            }

            $conta = ContaBancaria::withoutGlobalScope(BusinessScopeImpl::class)
                ->where('business_id', $businessId)->find($contaId);
            if (! $conta) {
                throw new \DomainException('Conta bancária inválida.');
            }

            $emAberto = PagamentoBanco::withoutGlobalScope(BusinessScopeImpl::class)
                ->where('business_id', $businessId)->where('titulo_id', $titulo->id)
                ->whereIn('status', PagamentoBanco::STATUS_EM_ABERTO)->exists();
            if ($emAberto) {
                throw new \DomainException('Já existe um pagamento deste título aguardando o banco.');
            }

            return PagamentoBanco::withoutGlobalScope(BusinessScopeImpl::class)->create([
                'business_id' => $businessId,
                'titulo_id' => $titulo->id,
                'conta_bancaria_id' => $conta->id,
                'meio' => $meio,
                'destino' => $destino,
                'valor' => $aberto,
                'idempotency_key' => (string) Str::uuid(),
                'status' => PagamentoBanco::STATUS_ENVIANDO,
                'solicitado_por' => $userId,
                'solicitado_em' => now(),
            ]);
        });

        try {
            $resposta = app(PagadorBanco::class)->enviar($pagamento, $this->conta($pagamento));
        } catch (\Throwable $e) {
            $pagamento->update(['status' => PagamentoBanco::STATUS_ERRO, 'motivo' => Str::limit($e->getMessage(), 180)]);

            return $pagamento;
        }

        return $this->aplicarResposta($pagamento, $resposta);
    }

    /** Consulta o banco de um pedido que ainda aguarda resposta. */
    public function sincronizar(int $businessId, int $pagamentoId): PagamentoBanco
    {
        $pagamento = PagamentoBanco::withoutGlobalScope(BusinessScopeImpl::class)
            ->where('business_id', $businessId)->findOrFail($pagamentoId);

        if ($pagamento->status !== PagamentoBanco::STATUS_AGUARDANDO) {
            return $pagamento;
        }

        return $this->aplicarResposta($pagamento, app(PagadorBanco::class)->consultar($pagamento, $this->conta($pagamento)));
    }

    private function aplicarResposta(PagamentoBanco $pagamento, array $resposta): PagamentoBanco
    {
        $status = $resposta['status'] ?? null;
        $dados = [
            'codigo_transacao' => $resposta['codigo_transacao'] ?? $pagamento->codigo_transacao,
            'motivo' => isset($resposta['motivo']) ? Str::limit((string) $resposta['motivo'], 180) : null,
            'resposta_banco' => $resposta['raw'] ?? null,
        ];

        if ($status === PagamentoBanco::STATUS_PAGO) {
            return $this->baixar($pagamento, $dados);
        }

        $pagamento->update($dados + [
            'status' => $status === PagamentoBanco::STATUS_RECUSADO ? PagamentoBanco::STATUS_RECUSADO : PagamentoBanco::STATUS_AGUARDANDO,
        ]);

        return $pagamento;
    }

    private function baixar(PagamentoBanco $pagamento, array $dados): PagamentoBanco
    {
        return DB::transaction(function () use ($pagamento, $dados) {
            $pag = PagamentoBanco::withoutGlobalScope(BusinessScopeImpl::class)
                ->where('business_id', $pagamento->business_id)->lockForUpdate()->findOrFail($pagamento->id);
            if ($pag->baixa_id) {
                return $pag;
            }

            $titulo = Titulo::withoutGlobalScope(BusinessScopeImpl::class)
                ->where('business_id', $pag->business_id)->lockForUpdate()->findOrFail($pag->titulo_id);

            if ($titulo->status === 'quitado' || number_format((float) $titulo->valor_aberto, 2, '.', '') !== number_format((float) $pag->valor, 2, '.', '')) {
                // O banco pagou, mas o título mudou no meio (baixa manual, edição): não baixa de
                // novo — fica registrado para conferência humana.
                // O aviso vem à esquerda: no `+` do PHP a chave da esquerda vence, e $dados
                // sempre traz 'motivo' (null quando o banco não mandou nenhum).
                $pag->update(['status' => PagamentoBanco::STATUS_PAGO, 'confirmado_em' => now(),
                    'motivo' => 'Banco confirmou, mas o título já não tinha este aberto — conferir duplicidade.'] + $dados);

                return $pag;
            }

            $baixa = TituloBaixa::create([
                'business_id' => $pag->business_id,
                'titulo_id' => $titulo->id,
                'conta_bancaria_id' => $pag->conta_bancaria_id,
                'valor_baixa' => $pag->valor,
                'data_baixa' => now()->toDateString(),
                'meio_pagamento' => $pag->meio,
                'idempotency_key' => $pag->idempotency_key,
                'observacoes' => 'Pago pelo banco ('.$pag->meio.')',
                'created_by' => $pag->solicitado_por,
            ]);

            $titulo->fill(['valor_aberto' => 0, 'status' => 'quitado', 'conta_bancaria_id' => $pag->conta_bancaria_id])->save();
            $pag->update($dados + ['status' => PagamentoBanco::STATUS_PAGO, 'confirmado_em' => now(), 'baixa_id' => $baixa->id]);

            return $pag;
        });
    }

    private function conta(PagamentoBanco $pagamento): ContaBancaria
    {
        return ContaBancaria::withoutGlobalScope(BusinessScopeImpl::class)
            ->where('business_id', $pagamento->business_id)->findOrFail($pagamento->conta_bancaria_id);
    }

    private function normalizarDestino(string $meio, string $destino): string
    {
        $destino = trim($destino);
        if ($meio === 'boleto') {
            $destino = preg_replace('/\D/', '', $destino);
            if (! in_array(strlen($destino), [44, 47, 48], true)) {
                throw new \DomainException('Linha digitável ou código de barras inválido.');
            }
        } elseif ($destino === '' || mb_strlen($destino) > 512) {
            throw new \DomainException('Chave Pix ou código copia e cola inválido.');
        }

        return $destino;
    }
}
