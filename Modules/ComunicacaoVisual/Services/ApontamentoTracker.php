<?php

namespace Modules\ComunicacaoVisual\Services;

use App\Util\OtelHelper;
use Illuminate\Support\Facades\Log;
use Modules\ComunicacaoVisual\Entities\Apontamento;
use Modules\ComunicacaoVisual\Entities\OrcamentoItem;
use Modules\ComunicacaoVisual\Entities\Os;
use RuntimeException;

/**
 * ApontamentoTracker — gerencia ciclo de vida dos apontamentos de produção.
 *
 * US-COMVIS-004: operador balcão registra início/fim de produção em uma OS.
 * O serviço calcula duracao_segundos e drift_percent server-side.
 *
 * Regras de negócio:
 *   - 1 spool ativo por operador: se há apontamento em andamento, não pode iniciar outro.
 *   - Drift = ((m2_prod - m2_orc) / m2_orc) × 100 (null se m2_orcado=0 ou null).
 *   - Cancelamento seta m2_produzido=0 e observacoes com prefixo "[CANCELADO]".
 *   - Append-only: sem soft delete — registros não podem ser alterados após finalizados.
 *
 * Multi-tenant Tier 0 ([ADR 0093](memory/decisions/0093-multi-tenant-isolation-tier-0.md)):
 * Todas as queries usam Models com global scope ativo — business_id da sessão.
 *
 * @see memory/requisitos/ComunicacaoVisual/SPEC.md US-COMVIS-004
 * @see memory/decisions/0093-multi-tenant-isolation-tier-0.md
 */
class ApontamentoTracker
{
    /**
     * Inicia um novo apontamento de produção.
     *
     * Valida OS (multi-tenant scope automático) e garante 1 spool ativo por operador.
     * Se orcamento_item_id fornecido, faz snapshot de area_m2 para m2_orcado.
     *
     * @param  int    $osId             ID da OS (filtrada pelo global scope business_id)
     * @param  int    $operadorId       ID do usuário que está operando
     * @param  int|null  $orcamentoItemId  Item de orçamento associado (opcional)
     * @param  string|null $maquina     Identificador da máquina (ex: 'plotter-roland-1')
     * @param  array  $opts             Opções extras (reservado para extensão futura)
     * @return Apontamento
     * @throws RuntimeException Se OS não encontrada ou operador já tem apontamento ativo
     */
    public function iniciar(
        int $osId,
        int $operadorId,
        ?int $orcamentoItemId = null,
        ?string $maquina = null,
        array $opts = []
    ): Apontamento {
        return OtelHelper::spanBiz('comvis.apontamento.iniciar', function () use (
            $osId, $operadorId, $orcamentoItemId, $maquina, $opts
        ) {
            return $this->iniciarInterno($osId, $operadorId, $orcamentoItemId, $maquina, $opts);
        }, ['os_id' => $osId, 'operador_id' => $operadorId, 'maquina' => $maquina ?? 'na']);
    }

    /**
     * Implementação interna de iniciar — envolvida pelo span OTel acima.
     */
    private function iniciarInterno(
        int $osId,
        int $operadorId,
        ?int $orcamentoItemId,
        ?string $maquina,
        array $opts
    ): Apontamento {
        // Valida OS existe no business atual (global scope filtra automaticamente)
        $os = Os::find($osId);
        if ($os === null) {
            throw new RuntimeException(
                "OS #{$osId} não encontrada ou não pertence a este business."
            );
        }

        // Garante 1 spool ativo por operador (global scope do Apontamento filtra business)
        $emAndamento = $this->emAndamento($operadorId);
        if ($emAndamento !== null) {
            throw new RuntimeException(
                "Operador #{$operadorId} já possui apontamento #{$emAndamento->id} em andamento. " .
                "Finalize ou cancele o apontamento ativo antes de iniciar um novo."
            );
        }

        // Snapshot de m2_orcado do item (se fornecido)
        $m2Orcado = null;
        if ($orcamentoItemId !== null) {
            // SUPERADMIN: withoutGlobalScopes pois buscamos por business_id explícito do OS
            $item = OrcamentoItem::withoutGlobalScopes()
                ->where('id', $orcamentoItemId)
                ->where('business_id', $os->business_id)
                ->first();

            if ($item !== null && $item->area_m2 !== null) {
                $m2Orcado = (float) $item->area_m2;
            }
        }

        return Apontamento::create([
            'os_id'             => $osId,
            'operador_id'       => $operadorId,
            'orcamento_item_id' => $orcamentoItemId,
            'maquina'           => $maquina,
            'iniciado_em'       => now(),
            'finalizado_em'     => null,
            'duracao_segundos'  => null,
            'm2_produzido'      => null,
            'm2_orcado'         => $m2Orcado,
            'drift_percent'     => null,
            'observacoes'       => $opts['observacoes'] ?? null,
        ]);
    }

    /**
     * Finaliza um apontamento em andamento.
     *
     * Calcula duracao_segundos e drift_percent server-side.
     * Drift = round(((m2_prod - m2_orc) / m2_orc) × 100, 2) — null se m2_orcado ≤ 0.
     *
     * @param  int    $apontamentoId  ID do apontamento (filtrado pelo global scope business_id)
     * @param  float  $m2Produzido   m² efetivamente produzidos (informado pelo operador)
     * @param  string|null $observacoes Observações opcionais
     * @return Apontamento
     * @throws RuntimeException Se apontamento não encontrado ou já finalizado
     */
    public function finalizar(int $apontamentoId, float $m2Produzido, ?string $observacoes = null): Apontamento
    {
        return OtelHelper::spanBiz('comvis.apontamento.finalizar', function () use (
            $apontamentoId, $m2Produzido, $observacoes
        ) {
            return $this->finalizarInterno($apontamentoId, $m2Produzido, $observacoes);
        }, ['apontamento_id' => $apontamentoId, 'm2_produzido' => $m2Produzido]);
    }

    /**
     * Implementação interna de finalizar — envolvida pelo span OTel acima.
     * Loga apontamento finalizado em canal estruturado (D9 observability).
     */
    private function finalizarInterno(int $apontamentoId, float $m2Produzido, ?string $observacoes): Apontamento
    {
        $apontamento = Apontamento::find($apontamentoId);

        if ($apontamento === null) {
            throw new RuntimeException(
                "Apontamento #{$apontamentoId} não encontrado ou não pertence a este business."
            );
        }

        if ($apontamento->finalizado_em !== null) {
            throw new RuntimeException(
                "Apontamento #{$apontamentoId} já foi finalizado em {$apontamento->finalizado_em->format('d/m/Y H:i:s')}."
            );
        }

        $finalizadoEm    = now();
        $duracaoSegundos = (int) $finalizadoEm->diffInSeconds($apontamento->iniciado_em);
        $m2Orcado        = $apontamento->m2_orcado !== null ? (float) $apontamento->m2_orcado : null;

        // Drift: null quando m2_orcado = 0 ou null (impossível dividir)
        $driftPercent = null;
        if ($m2Orcado !== null && $m2Orcado > 0) {
            $driftPercent = round((($m2Produzido - $m2Orcado) / $m2Orcado) * 100, 2);
        }

        $apontamento->update([
            'finalizado_em'    => $finalizadoEm,
            'duracao_segundos' => $duracaoSegundos,
            'm2_produzido'     => round($m2Produzido, 3),
            'drift_percent'    => $driftPercent,
            'observacoes'      => $observacoes,
        ]);

        // Log estruturado D9 — apontamento finalizado (audit + dashboard)
        Log::info('comvis.apontamento.finalizado', [
            'business_id'       => $apontamento->business_id ?? null,
            'apontamento_id'    => $apontamento->id,
            'os_id'             => $apontamento->os_id,
            'operador_id'       => $apontamento->operador_id,
            'duracao_segundos'  => $duracaoSegundos,
            'm2_produzido'      => round($m2Produzido, 3),
            'm2_orcado'         => $m2Orcado,
            'drift_percent'     => $driftPercent,
        ]);

        return $apontamento->fresh();
    }

    /**
     * Cancela um apontamento em andamento.
     *
     * Define finalizado_em=now(), m2_produzido=0 e prefixo "[CANCELADO]" em observacoes.
     * Não calcula drift (apontamento cancelado não representa produção real).
     *
     * @param  int    $apontamentoId  ID do apontamento
     * @param  string $motivo         Motivo do cancelamento (min 5 chars)
     * @return Apontamento
     * @throws RuntimeException Se apontamento não encontrado ou já finalizado
     */
    public function cancelar(int $apontamentoId, string $motivo): Apontamento
    {
        return OtelHelper::spanBiz('comvis.apontamento.cancelar', function () use ($apontamentoId, $motivo) {
            return $this->cancelarInterno($apontamentoId, $motivo);
        }, ['apontamento_id' => $apontamentoId]);
    }

    private function cancelarInterno(int $apontamentoId, string $motivo): Apontamento
    {
        $apontamento = Apontamento::find($apontamentoId);

        if ($apontamento === null) {
            throw new RuntimeException(
                "Apontamento #{$apontamentoId} não encontrado ou não pertence a este business."
            );
        }

        if ($apontamento->finalizado_em !== null) {
            throw new RuntimeException(
                "Apontamento #{$apontamentoId} já foi finalizado — não pode ser cancelado."
            );
        }

        $apontamento->update([
            'finalizado_em'    => now(),
            'duracao_segundos' => (int) now()->diffInSeconds($apontamento->iniciado_em),
            'm2_produzido'     => 0,
            'drift_percent'    => null,
            'observacoes'      => "[CANCELADO] {$motivo}",
        ]);

        return $apontamento->fresh();
    }

    /**
     * Retorna o apontamento em andamento do operador no business atual (ou null).
     *
     * Usado como helper para widget de status no frontend.
     * Multi-tenant: global scope do Apontamento filtra business_id da sessão.
     *
     * @param  int $operadorId
     * @return Apontamento|null
     */
    public function emAndamento(int $operadorId): ?Apontamento
    {
        return Apontamento::where('operador_id', $operadorId)
            ->whereNull('finalizado_em')
            ->with(['os', 'orcamentoItem'])
            ->first();
    }
}
