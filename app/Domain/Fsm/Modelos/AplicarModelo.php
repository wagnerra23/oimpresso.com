<?php

declare(strict_types=1);

namespace App\Domain\Fsm\Modelos;

use App\Domain\Fsm\Models\ModeloEmpresa;
use App\Domain\Fsm\Models\SaleProcess;
use App\Domain\Fsm\Models\SaleProcessStage;
use App\Domain\Fsm\Models\SaleStageAction;
use App\Domain\Fsm\Models\SaleStageActionRole;
use App\Scopes\ScopeByBusiness;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;
use Spatie\Permission\Models\Role;

/**
 * "Usar modelo" (ADR 0435 itens 5–8): grava o modelo da empresa e monta os processos do modelo
 * no motor FSM daquele business — etapas na ordem com passo, a lateral Cancelado, ação de avançar
 * entre etapas vizinhas, ação de cancelar e as regras "ao entrar" como dado.
 *
 * Idempotente (firstOrCreate em tudo; aplicar de novo reaproveita). Tier 0: só lê e grava
 * processos do `$businessId` recebido. Nunca altera documento (`transactions`, OS…), nunca
 * desativa processo de outro modelo nem o legado. A ação de avançar só recebe side effect quando a
 * regra da etapa de destino aponta um que já existe — hoje nenhuma (todas planejadas). O cancelar
 * fica sem efeito até um documento usar o processo. Regra planejada com motivo conhecido grava a
 * `pendencia` (CatalogoModelos::PENDENCIAS) — o porquê de não executar. Aplicar de novo mantém o `ligada` que a
 * empresa já gravou nas regras (firstOrCreate não reescreve a etapa) e liga a regra de custo da
 * compra com os parâmetros do modelo (RegrasCompra).
 */
final class AplicarModelo
{
    /** Papel que avança cada domínio — os mesmos do processo legado; compra não tem (ainda). */
    private const PAPEL_AVANCAR = [
        'venda' => 'vendas.enviar',
        'producao' => 'producao.concluir',
        'entrega' => 'logistica.entregar',
        'financeiro' => 'financeiro.baixar',
    ];

    /** Cancelar é crítico (fail-secure exige papel). */
    private const PAPEL_CANCELAR = 'vendas.gerente';

    /** @return array<string, int> domínio => id do processo */
    public function aplicar(int $businessId, string $modeloId, ?string $porte = null, ?string $atividade = null, ?int $userId = null): array
    {
        $modelo = CatalogoModelos::modelo($modeloId);
        if ($modelo === null) {
            throw new InvalidArgumentException("Modelo de fluxo desconhecido: {$modeloId}");
        }
        if ($porte !== null && ! isset(CatalogoModelos::PORTES[$porte])) {
            throw new InvalidArgumentException("Porte desconhecido: {$porte}");
        }
        if ($atividade !== null && ! isset(CatalogoModelos::ATIVIDADES[$atividade])) {
            throw new InvalidArgumentException("Atividade desconhecida: {$atividade}");
        }
        if (! DB::table('business')->where('id', $businessId)->exists()) {
            throw new InvalidArgumentException("Empresa inexistente: {$businessId}");
        }

        return DB::transaction(function () use ($businessId, $modeloId, $modelo, $porte, $atividade, $userId) {
            $ids = [];
            foreach ($modelo['processos'] as $dominio => $def) {
                $ids[$dominio] = $this->montarProcesso($businessId, $modeloId, $modelo['nome'], $dominio, $def);
            }

            $linha = ModeloEmpresa::withoutGlobalScope(ScopeByBusiness::class)->firstOrNew(['business_id' => $businessId]);
            $linha->fill(['porte' => $porte, 'atividade' => $atividade, 'modelo' => $modeloId, 'aplicado_em' => now(), 'aplicado_por' => $userId]);
            // Como no protótipo (fluxos-page.jsx aplicar): a regra de custo da compra liga com os
            // parâmetros do modelo; o resto do que a empresa configurou na Compra fica.
            if (! empty($modelo['compra']) && Schema::hasColumn('fsm_modelos_empresa', 'regras_compra')) {
                $linha->regras_compra = RegrasCompra::mudar($linha->regras_compra, RegrasCompra::CUSTO, true, $modelo['compra']);
            }
            $linha->save();

            return $ids;
        });
    }

    /** @param array{etapas: list<string>, regras: list<array>} $def */
    private function montarProcesso(int $businessId, string $modeloId, string $nomeModelo, string $dominio, array $def): int
    {
        $process = SaleProcess::withoutGlobalScope(ScopeByBusiness::class)->firstOrCreate(
            ['business_id' => $businessId, 'key' => CatalogoModelos::chaveProcesso($modeloId, $dominio)],
            [
                'name' => $nomeModelo . ' · ' . CatalogoModelos::DOMINIOS[$dominio],
                'description' => 'Modelo de fluxo ' . $modeloId . ' (ADR 0435)',
                'dominio' => $dominio,
                'modelo' => $modeloId,
                'default_for_contact_type' => 'any',
                'active' => true,
            ],
        );

        $regrasPorEtapa = [];
        foreach ($def['regras'] as [$etapa, $efeito, $uc, $classe, $candidato]) {
            $regrasPorEtapa[$etapa][] = array_filter([
                'uc' => $uc !== '' ? $uc : null,
                'efeito' => $efeito,
                'estado' => $classe !== null ? 'ativo' : 'planejado',
                'side_effect' => $classe,
                'candidato' => $candidato,
                'pendencia' => $classe === null ? (CatalogoModelos::PENDENCIAS[$uc] ?? null) : null,
            ], fn ($v) => $v !== null);
        }

        $ultima = count($def['etapas']) - 1;
        $stages = [];
        foreach ($def['etapas'] as $i => $nome) {
            $chave = CatalogoModelos::chaveEtapa($nome);
            $stages[] = SaleProcessStage::firstOrCreate(
                ['process_id' => $process->id, 'key' => $chave],
                [
                    'name' => $nome,
                    'sort_order' => $i,
                    'is_initial' => $i === 0,
                    'is_terminal' => $i === $ultima,
                    'passo' => $chave,
                    'passo_rotulo' => $nome,
                    'passo_ordem' => $i,
                    'is_cancelled' => false,
                    'is_producao' => $dominio === 'producao' && $i !== $ultima,
                    'regras_ao_entrar' => $regrasPorEtapa[$nome] ?? null,
                ],
            );
        }
        $cancelado = SaleProcessStage::firstOrCreate(
            ['process_id' => $process->id, 'key' => 'cancelado'],
            ['name' => 'Cancelado', 'sort_order' => $ultima + 1, 'is_initial' => false, 'is_terminal' => true, 'is_cancelled' => true],
        );

        $papeis = $this->garantirPapeis($businessId);
        foreach ($stages as $i => $stage) {
            if ($i === $ultima) {
                continue;
            }
            $proxima = $stages[$i + 1];
            // Regra "ao entrar na próxima" com side effect existente vira a classe da ação.
            $classe = collect($regrasPorEtapa[$proxima->name] ?? [])->pluck('side_effect')->filter()->first();
            $this->garantirAcao($stage, 'avancar', 'Ir para ' . $proxima->name, $proxima, false, $papeis[self::PAPEL_AVANCAR[$dominio] ?? ''] ?? null, $classe);
            $this->garantirAcao($stage, 'cancelar', 'Cancelar', $cancelado, true, $papeis[self::PAPEL_CANCELAR]);
        }

        return (int) $process->id;
    }

    private function garantirAcao(SaleProcessStage $de, string $key, string $label, SaleProcessStage $para, bool $critica, ?string $papel, ?string $classe = null): void
    {
        $acao = SaleStageAction::firstOrCreate(
            ['stage_id' => $de->id, 'key' => $key],
            ['label' => $label, 'target_stage_id' => $para->id, 'side_effect_class' => $classe, 'requires_confirmation' => $critica, 'is_critical' => $critica],
        );
        if ($papel !== null && ! $acao->roles()->where('role_name', $papel)->exists()) {
            SaleStageActionRole::create(['action_id' => $acao->id, 'role_name' => $papel]);
        }
    }

    /**
     * Mesmos papéis do processo legado, com o sufixo `#{biz}` quando `roles.business_id` existe
     * (convenção UltimatePOS — ver FsmProcessoVendaComProducaoSeeder::ensureRoles).
     *
     * @return array<string, string> papel canônico => nome gravado
     */
    private function garantirPapeis(int $businessId): array
    {
        $porBiz = Schema::hasColumn('roles', 'business_id');
        $nomes = [];
        foreach ([...array_values(self::PAPEL_AVANCAR), self::PAPEL_CANCELAR] as $papel) {
            $nome = $porBiz ? "{$papel}#{$businessId}" : $papel;
            $attrs = ['name' => $nome, 'guard_name' => 'web'];
            if ($porBiz) {
                $attrs['business_id'] = $businessId;
            }
            Role::firstOrCreate($attrs);
            $nomes[$papel] = $nome;
        }

        return $nomes;
    }
}
