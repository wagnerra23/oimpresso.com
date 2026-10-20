<?php

declare(strict_types=1);

namespace Modules\Producao\Services;

use App\Scopes\ScopeByBusiness;
use App\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Modules\Producao\Entities\Evento;
use Modules\Producao\Entities\Ordem;
use Modules\Producao\Entities\Programacao;
use Modules\Producao\Entities\Refugo;
use Modules\Producao\Exceptions\ApontamentoRecusado;

/**
 * Refugo e retrabalho com motivo (UC-FLX-16 · US-PCP-027). O responsável da etapa aponta quantas peças
 * refugou e por quê (motivo de refugo do cadastro do posto, UC-PCV-33). Mexe em ESTOQUE e em VALOR
 * (regra mestre, proibicoes.md) quando repõe material — e aí NÃO baixa por conta própria: cada
 * material passa pelo ConsumoMaterialService (US-PCP-021), que baixa pelo lote que saiu.
 *
 * Por que o custo do refugo já entra no custo real: o material reposto vira `pcp_consumos` da OS e as
 * horas da etapa de retrabalho são apontamentos da OS — o CustoRealService soma os dois na conclusão.
 * Nada é somado aqui (uma conta só, um lugar só).
 *
 * Retrabalho: com `retrabalho = true` o refugo chama o "Reprovar no controle" do SituacaoDaOrdem
 * (motor FSM, papel `producao.qualidade`): a OS vai para Retrabalho e a etapa volta como etapa nova.
 * Sem o papel, o motor recusa e nada fica gravado. O operador não vê custo (UC-PCMAT-07).
 * Tier 0: `$businessId` em toda consulta (o app não tem sessão).
 */
final class RefugoService
{
    public function __construct(private readonly ConsumoMaterialService $consumo, private readonly SituacaoDaOrdem $situacao) {}

    /** @param  list<array{variation_id: int, quantidade: string}>  $materiais  material reposto para as peças refugadas */
    public function registrar(int $businessId, int $userId, int $programacaoId, int $motivoId, string $quantidade, string $chave,
        CarbonInterface $momento, array $materiais = [], bool $retrabalho = false): Refugo
    {
        $quantidade = $this->quantidade($quantidade);
        $momento = Carbon::instance($momento)->setTimezone(config('app.timezone'))->startOfSecond();

        if ($repetido = Refugo::query()->where('business_id', $businessId)->where('chave', $chave)->first()) {
            $igual = (int) $repetido->programacao_id === $programacaoId && (int) $repetido->motivo_id === $motivoId
                && $this->quantidade((string) $repetido->quantidade) === $quantidade;

            return $igual ? $repetido : throw new ApontamentoRecusado(ApontamentoRecusado::IDEMPOTENCIA_CONFLITO, 'Esta chave já foi usada em outro apontamento.');
        }

        $etapa = Programacao::query()->where('business_id', $businessId)->where('os_tipo', Ordem::OS_TIPO)->find($programacaoId)
            ?? throw new ApontamentoRecusado(ApontamentoRecusado::NAO_ENCONTRADO, 'Etapa não encontrada.');
        if ((int) $etapa->user_id !== $userId) {
            throw new ApontamentoRecusado(ApontamentoRecusado::ETAPA_ALHEIA, 'Esta etapa está com outro responsável.',
                ['programacao_id' => $etapa->id, 'posto_id' => $etapa->posto_id, 'user_id' => $etapa->user_id]);
        }
        if (! in_array($etapa->status, ['iniciada', 'concluida'], true)) {
            throw new ApontamentoRecusado(ApontamentoRecusado::ETAPA_ENCERRADA, $etapa->status === 'pendente'
                ? 'Inicie a etapa antes de apontar refugo.' : 'Esta etapa foi cancelada.');
        }
        // Lista fechada por posto (UC-PCV-33): o motivo é de refugo, ativo, da empresa e do posto da etapa.
        $motivo = DB::table('pcp_motivos')->where('business_id', $businessId)->where('tipo', 'refugo')->where('ativo', true)->where('id', $motivoId)
            ->when($etapa->posto_id, fn ($q) => $q->where('posto_id', $etapa->posto_id))->value('nome')
            ?? throw new ApontamentoRecusado(ApontamentoRecusado::MOTIVO_OBRIGATORIO, 'Escolha um motivo de refugo do posto da etapa.');

        return DB::transaction(function () use ($businessId, $userId, $etapa, $motivoId, $motivo, $quantidade, $chave, $momento, $materiais, $retrabalho) {
            // Trava a OS: o refugo não passa pela conclusão que congela o custo.
            $ordem = Ordem::withoutGlobalScope(ScopeByBusiness::class)->where('business_id', $businessId)->with('currentStage')
                ->lockForUpdate()->find((int) $etapa->os_id)
                ?? throw new ApontamentoRecusado(ApontamentoRecusado::NAO_ENCONTRADO, 'OS não encontrada.');
            if (! in_array($ordem->currentStage?->key, ConsumoMaterialService::SITUACOES, true)) {
                throw new ApontamentoRecusado(ApontamentoRecusado::OS_NAO_LIBERADA, 'Esta OS não está em produção: o refugo não pode ser apontado.',
                    ['programacao_id' => $etapa->id, 'situacao' => $ordem->currentStage?->key]);
            }
            if ($this->emDezMilesimos($quantidade) > $this->emDezMilesimos((string) $ordem->quantidade)) {
                throw new ApontamentoRecusado(ApontamentoRecusado::QUANTIDADE_INVALIDA, 'O refugo não pode passar da quantidade da OS.');
            }

            $refugo = Refugo::query()->create([
                'business_id' => $businessId, 'chave' => $chave, 'ordem_id' => $ordem->id, 'programacao_id' => $etapa->id,
                'motivo_id' => $motivoId, 'motivo' => $motivo, 'quantidade' => $quantidade, 'user_id' => $userId, 'momento' => $momento,
            ]);
            foreach ($materiais as $i => $m) {
                $this->consumo->registrar($businessId, $userId, $etapa->id, (int) $m['variation_id'], (string) $m['quantidade'],
                    'refugo:'.sha1($businessId.':'.$chave).':'.$i, $momento);
            }
            if ($retrabalho) {
                $this->situacao->reprovar($businessId, $ordem->id, $etapa->id, $motivoId, User::findOrFail($userId));
                $nova = Evento::query()->where('business_id', $businessId)->where('tipo', SituacaoDaOrdem::EV_RETRABALHO)
                    ->where('os_tipo', Ordem::OS_TIPO)->where('os_id', $ordem->id)->latest('id')->first()?->dados['nova_programacao_id'] ?? null;
                $refugo->forceFill(['retrabalho_programacao_id' => $nova])->save();
            }

            return $refugo;
        });
    }

    /** Quantidade positiva com até 4 casas, ponto decimal (o app e a PWA mandam assim). Volta com 4 casas. */
    private function quantidade(string $quantidade): string
    {
        $q = trim($quantidade);
        if (! preg_match('/^\d{1,14}(\.\d{1,4})?$/', $q)) {
            throw new ApontamentoRecusado(ApontamentoRecusado::QUANTIDADE_INVALIDA, 'Informe a quantidade com ponto e até 4 casas (ex.: 2.5).');
        }
        [$inteiro, $fracao] = array_pad(explode('.', $q, 2), 2, '');
        $normal = (ltrim($inteiro, '0') === '' ? '0' : ltrim($inteiro, '0')).'.'.str_pad($fracao, 4, '0');
        if ($normal === '0.0000') {
            throw new ApontamentoRecusado(ApontamentoRecusado::QUANTIDADE_INVALIDA, 'A quantidade tem de ser maior que zero.');
        }

        return $normal;
    }

    /** Decimal lido como texto, em unidades de 0,0001 (sem float). */
    private function emDezMilesimos(string $valor): int
    {
        [$inteiro, $fracao] = array_pad(explode('.', trim($valor), 2), 2, '0');

        return (int) $inteiro * 10000 + (int) str_pad(substr($fracao, 0, 4), 4, '0');
    }
}
