<?php

namespace Modules\Producao\Services;

use DomainException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Modules\Producao\Entities\Apontamento;
use Modules\Producao\Entities\Posto;
use Modules\Producao\Entities\Programacao;

/**
 * Apontamento append-only (thread 02 + E02). Abrir e fechar (pausa ou fim) são duas linhas.
 *
 * - Posto `maquina` grava `maquina_id` + `iniciado_por` e NÃO ocupa a pessoa (UC-PCV-31).
 * - Posto `pessoa` grava `user_id`; uma pessoa tem no máximo 1 apontamento de pessoa aberto.
 * - `chave_idempotencia` única por empresa: reenvio da fila offline devolve a mesma linha.
 * - `programacao_id` liga o apontamento à etapa programada (retrabalho no mesmo posto é outra etapa).
 * - O custo-hora é copiado do posto no início: mudar o posto depois não reescreve o passado.
 *
 * O `business_id` vem por parâmetro e entra em toda consulta (jobs e fila não têm sessão).
 */
class ApontamentoService
{
    public function iniciar(
        int $businessId,
        int $postoId,
        int $iniciadoPor,
        string $chave,
        ?string $osTipo = null,
        ?int $osId = null,
        string $origem = 'qr',
        ?string $justificativa = null,
        ?Carbon $momento = null,
        ?int $programacaoId = null,
    ): Apontamento {
        if ($existente = $this->porChave($businessId, $chave)) {
            return $existente;
        }
        if ($origem === 'manual' && blank($justificativa)) {
            throw new DomainException('Lançamento manual exige justificativa.');
        }

        $posto = Posto::query()->where('business_id', $businessId)->findOrFail($postoId);
        $maquina = $posto->tipo_custo === Posto::MAQUINA;
        // A etapa tem de ser da mesma empresa (Tier 0); quem decide se é do operador é a thread 04.
        $programacao = $programacaoId === null ? null
            : Programacao::query()->where('business_id', $businessId)->findOrFail($programacaoId);

        if (! $maquina && $this->pessoaAberto($businessId, $iniciadoPor)) {
            throw new DomainException('Você já tem uma etapa de pessoa em andamento. Encerre-a antes de iniciar outra.');
        }

        return Apontamento::query()->create([
            'business_id' => $businessId,
            'chave_idempotencia' => $chave,
            'tipo' => 'inicio',
            'programacao_id' => $programacao?->id,
            'os_tipo' => $osTipo,
            'os_id' => $osId,
            'posto_id' => $posto->id,
            'tipo_custo' => $posto->tipo_custo,
            'custo_hora' => $posto->custo_hora,
            'maquina_id' => $maquina ? $posto->id : null,
            'user_id' => $maquina ? null : $iniciadoPor,
            'iniciado_por' => $iniciadoPor,
            'momento' => $momento ?? now(),
            'origem' => $origem,
            'justificativa' => $justificativa,
        ]);
    }

    /** `$impressao`: m² impresso e tinta C/M/Y/K já normalizados (US-COMVIS-004), gravados na linha `fim`. */
    public function fechar(int $businessId, int $inicioId, int $por, string $chave, string $origem = 'qr', ?Carbon $momento = null, array $impressao = []): Apontamento
    {
        return $this->encerrar('fim', $businessId, $inicioId, $por, $chave, $origem, $momento, null, $impressao);
    }

    /** Pausa fecha o trecho sem concluir a etapa; retomar é um `iniciar` novo. Motivo vem do cadastro do posto (thread 03). */
    public function pausar(int $businessId, int $inicioId, int $por, string $chave, ?int $motivoId, string $origem = 'qr', ?Carbon $momento = null): Apontamento
    {
        return $this->encerrar('pausa', $businessId, $inicioId, $por, $chave, $origem, $momento, $motivoId);
    }

    private function encerrar(string $tipo, int $businessId, int $inicioId, int $por, string $chave, string $origem, ?Carbon $momento, ?int $motivoId, array $impressao = []): Apontamento
    {
        if ($existente = $this->porChave($businessId, $chave)) {
            return $existente;
        }

        /** @var Apontamento $inicio */
        $inicio = $this->abertos($businessId)->findOrFail($inicioId);

        return Apontamento::query()->create([
            'business_id' => $businessId,
            'chave_idempotencia' => $chave,
            'tipo' => $tipo,
            'inicio_id' => $inicio->id,
            'motivo_id' => $motivoId,
            'programacao_id' => $inicio->programacao_id,
            'os_tipo' => $inicio->os_tipo,
            'os_id' => $inicio->os_id,
            'posto_id' => $inicio->posto_id,
            'tipo_custo' => $inicio->tipo_custo,
            'custo_hora' => $inicio->custo_hora,
            'maquina_id' => $inicio->maquina_id,
            'user_id' => $inicio->user_id,
            'iniciado_por' => $por,
            'momento' => $momento ?? now(),
            'origem' => $origem,
        ] + $impressao);
    }

    /**
     * Inícios da empresa que ainda não têm linha de pausa ou fim.
     *
     * @return Builder<Apontamento>
     */
    public function abertos(int $businessId): Builder
    {
        return Apontamento::query()
            ->where('pcp_apontamentos.business_id', $businessId)
            ->where('pcp_apontamentos.tipo', 'inicio')
            ->whereNotExists(fn ($q) => $q->selectRaw('1')->from('pcp_apontamentos as f')
                ->whereColumn('f.inicio_id', 'pcp_apontamentos.id')
                ->where('f.business_id', $businessId));
    }

    public function pessoaAberto(int $businessId, int $userId): bool
    {
        return $this->abertos($businessId)->where('pcp_apontamentos.tipo_custo', Posto::PESSOA)
            ->where('pcp_apontamentos.user_id', $userId)->exists();
    }

    private function porChave(int $businessId, string $chave): ?Apontamento
    {
        return Apontamento::query()->where('business_id', $businessId)->where('chave_idempotencia', $chave)->first();
    }
}
